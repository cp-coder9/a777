<?php
declare(strict_types=1);

/** Phase-one only. Transport authentication and receipt verification are caller obligations.
 * No operator provisioning, source data mutation, or maintenance release occurs here.
 */
final class CpanelDatabaseWriterFence
{
    private string $database = 'archite4_dev_rel1';
    private string $account = 'archite4';
    private array $state = [];

    /** provider(operation, parameters): decoded verified HTTPS UAPI status/data envelope.
     * verify(kind, binding): actual authenticated receipt (not a boolean).
     * fresh(): new application PDO. observer is an existing application PDO.
     */
    public function __construct(
        private Closure $provider,
        private Closure $verify,
        private Closure $fresh,
        private PDO $observer,
        private string $journal
    ) {
        if (file_exists($journal) || is_link($journal) || !is_dir(dirname($journal))) {
            throw new RuntimeException('Private unused journal required');
        }
    }

    private function receipt(string $kind, array $binding): array
    {
        $r = ($this->verify)($kind, $binding);
        if (!is_array($r) || ($r['kind'] ?? null) !== $kind || ($r['binding'] ?? null) !== $binding
            || !is_string($r['receiptId'] ?? null) || $r['receiptId'] === '') {
            throw new RuntimeException('Authenticated receipt missing: ' . $kind);
        }
        return $r;
    }

    private function api(string $operation, array $extra = []): mixed
    {
        $r = ($this->provider)($operation, ['user' => $this->state['user'], 'database' => $this->database] + $extra);
        if (!is_array($r) || ($r['status'] ?? null) !== 1 || !empty($r['errors']) || !array_key_exists('data', $r)) {
            throw new RuntimeException('Unverified provider response');
        }
        return $r['data'];
    }

    private function privileges(): array
    {
        $p = $this->api('get_privileges_on_database');
        if (!is_array($p) || !array_is_list($p)) throw new RuntimeException('Invalid privileges');
        foreach ($p as $v) if (!is_string($v) || !preg_match('/^[A-Z][A-Z ]*$/D', $v)) throw new RuntimeException('Invalid privilege');
        if (count(array_unique($p)) !== count($p)) throw new RuntimeException('Duplicate privileges');
        sort($p, SORT_STRING);
        return $p;
    }

    private function save(string $phase): void
    {
        $this->state['phase'] = $phase;
        if (is_link($this->journal)) throw new RuntimeException('Journal symlink refused');
        $s = json_encode($this->state, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        $tmp = $this->journal . '.intent-' . bin2hex(random_bytes(16));
        $mask = umask(0077);
        try { $h = fopen($tmp, 'x+b'); } finally { umask($mask); }
        if (!$h) throw new RuntimeException('Journal unavailable');
        try {
            if (!chmod($tmp, 0600) || fwrite($h, $s) !== strlen($s) || !fflush($h) || !fsync($h)) throw new RuntimeException('Journal durability failed');
        } finally { fclose($h); }
        if (!rename($tmp, $this->journal)) throw new RuntimeException('Journal atomic replacement failed');
        if (PHP_OS_FAMILY !== 'Windows') {
            $dir = fopen(dirname($this->journal), 'r');
            if (!$dir) throw new RuntimeException('Journal directory unavailable');
            try { if (!fsync($dir)) throw new RuntimeException('Journal directory durability failed'); } finally { fclose($dir); }
        }
        if (file_get_contents($this->journal) !== $s) throw new RuntimeException('Journal readback failed');
    }

    private function denied(PDO $pdo): bool
    {
        try { $pdo->query($this->state['probe']); return false; }
        catch (PDOException $e) {
            return in_array((int)($e->errorInfo[1] ?? 0), [1044, 1045, 1142, 1143], true);
        }
    }

    private function freshDenied(): bool
    {
        try { return $this->denied(($this->fresh)()); }
        catch (PDOException $e) { return in_array((int)($e->errorInfo[1] ?? 0), [1044, 1045], true); }
    }

    private function sessions(): array
    {
        return $this->observer->query('SHOW FULL PROCESSLIST')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fence(array $pin): array
    {
        if ($this->state !== []) throw new RuntimeException('Single-use fence');
        if (($pin['database'] ?? null) !== $this->database || ($pin['account'] ?? null) !== $this->account
            || !preg_match('/^archite4_[A-Za-z0-9_]+$/D', $pin['user'] ?? '')
            || !preg_match('/^[a-f0-9]{64}$/D', $pin['configSha256'] ?? '')
            || !preg_match('/^[A-Za-z0-9_]+$/D', $pin['probeTable'] ?? '')) throw new RuntimeException('Scope rejected');
        $identity = $this->observer->query('SELECT DATABASE() db, CURRENT_USER() identity, CONNECTION_ID() id')->fetch(PDO::FETCH_ASSOC);
        if ($identity['db'] !== $this->database || explode('@', $identity['identity'])[0] !== $pin['user']) throw new RuntimeException('Observer identity rejected');
        $binding = ['account' => $this->account, 'database' => $this->database, 'user' => $pin['user'], 'configSha256' => $pin['configSha256']];
        $this->state = $binding + ['observerId' => (int)$identity['id'], 'probe' => 'SELECT * FROM `' . $this->database . '`.`' . $pin['probeTable'] . '` LIMIT 1', 'maintenanceRetained' => true];
        try {
            $this->state['maintenance'] = $this->receipt('apache-maintenance', $binding);
            $this->state['identityReceipt'] = $this->receipt('source-config-identity', $binding);
            $sole = $this->receipt('sole-grant-user', $binding);
            if (($sole['users'] ?? null) !== [$pin['user']]) throw new RuntimeException('Nonexclusive user');
            $this->state['soleGrant'] = $sole;
            $this->state['originalPrivileges'] = $this->privileges();
            if (!$this->state['originalPrivileges']) throw new RuntimeException('No original privileges');
            $this->observer->query($this->state['probe']);
            $this->save('revoke-intent');
            try { $this->api('revoke_access_to_database'); }
            catch (Throwable $e) { $this->state['lostRevokeReply'] = true; }
            if ($this->privileges() !== []) throw new RuntimeException('Revoke not reconciled');
            $this->save('revoked');
            if (!$this->freshDenied()) throw new RuntimeException('Fresh application read still succeeds');
            $this->state['killIds'] = [];
            foreach ($this->sessions() as $row) {
                if ($row['User'] === $pin['user'] && $row['db'] === $this->database && (int)$row['Id'] !== $this->state['observerId']) {
                    $this->state['killIds'][] = (int)$row['Id'];
                    $this->save('kill-intent');
                    $this->observer->exec('KILL CONNECTION ' . (int)$row['Id']);
                }
            }
            $remaining = array_filter($this->sessions(), fn(array $r): bool => $r['User'] === $pin['user'] && $r['db'] === $this->database && (int)$r['Id'] !== $this->state['observerId']);
            $this->state['oldReadDenied'] = $this->denied($this->observer);
            if (count($remaining) !== 0) throw new RuntimeException('Existing writers remain');
            $this->state['remainingWriters'] = count($remaining);
            $this->save('blocked');
            return $this->state;
        } catch (Throwable $e) {
            $this->save('failure-maintenance-retained');
            throw $e;
        }
    }

    public function restore(array $plan): array
    {
        if (($this->state['phase'] ?? '') !== 'blocked') throw new RuntimeException('Fence not proven');
        $binding = array_intersect_key($this->state, array_flip(['account', 'database', 'user', 'configSha256']));
        if (($plan['binding'] ?? null) !== $binding || !preg_match('/^[a-f0-9]{40}$/D', $plan['commit'] ?? '') || !is_string($plan['planId'] ?? null) || $plan['planId'] === '') throw new RuntimeException('Restore plan mismatch');
        try {
            $this->state['restoreReceipt'] = $this->receipt('restore-readiness', $plan);
            if ($this->privileges() !== [] || !$this->freshDenied()) throw new RuntimeException('Negative pre-restore proof failed');
            $this->save('restore-intent');
            try { $this->api('set_privileges_on_database', ['privileges' => implode(',', $this->state['originalPrivileges'])]); }
            catch (Throwable $e) { $this->state['lostRestoreReply'] = true; }
            if ($this->privileges() !== $this->state['originalPrivileges']) throw new RuntimeException('Original privilege array not restored');
            ($this->fresh)()->query($this->state['probe']);
            $this->save('restored-maintenance-retained');
            return $this->state;
        } catch (Throwable $e) { $this->save('failure-maintenance-retained'); throw $e; }
    }
}
