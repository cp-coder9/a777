<?php
declare(strict_types=1);
require_once __DIR__ . '/CpanelMaintenanceApi.php';

/** Authenticated issuer envelopes, not DI assertions. Issuer integration remains a separate gate. */
final class CpanelFenceReceiptVerifier
{
    public function __construct(private Closure $fetchEnvelope, private string $issuerKey, private string $configSha256, private string $appUser)
    {
        if (strlen($issuerKey) < 32 || !preg_match('/^[a-f0-9]{64}$/D', $configSha256) || !preg_match('/^archite4_[A-Za-z0-9_]+$/D', $appUser)) throw new RuntimeException('Receipt trust configuration missing');
    }
    public function __invoke(string $kind, array $binding): array
    {
        $scope = $kind === 'restore-readiness' ? ($binding['binding'] ?? []) : $binding;
        if ($scope !== ['account'=>'archite4','database'=>'archite4_dev_rel1','user'=>$this->appUser,'configSha256'=>$this->configSha256]) throw new RuntimeException('Receipt scope mismatch');
        if ($kind === 'sole-grant-user') {
            $rows = CpanelMaintenanceApi::inventory('list_databases');
            $matches = array_values(array_filter($rows, static fn($r) => is_array($r) && ($r['database'] ?? null) === 'archite4_dev_rel1'));
            if (count($matches) !== 1 || !is_array($matches[0]['users'] ?? null)) throw new RuntimeException('Authenticated grant inventory unavailable');
            $users = $matches[0]['users']; sort($users, SORT_STRING);
            if ($users !== [$this->appUser]) throw new RuntimeException('Authenticated grant inventory not exclusive');
            return ['kind'=>$kind,'binding'=>$binding,'receiptId'=>hash('sha256', json_encode($matches, JSON_THROW_ON_ERROR)), 'users'=>$users];
        }
        if (!in_array($kind, ['apache-maintenance','source-config-identity','restore-readiness'], true)) throw new RuntimeException('Receipt kind refused');
        $envelope = ($this->fetchEnvelope)($kind, $binding);
        if (!is_array($envelope) || !is_string($envelope['payload'] ?? null) || strlen($envelope['payload']) > 65536 || !is_string($envelope['mac'] ?? null) || !hash_equals(hash_hmac('sha256', $envelope['payload'], $this->issuerKey), $envelope['mac'])) throw new RuntimeException('Receipt authentication failed');
        $receipt = json_decode($envelope['payload'], true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($receipt) || ($receipt['kind'] ?? null) !== $kind || ($receipt['binding'] ?? null) !== $binding || !is_int($receipt['issuedAt'] ?? null) || $receipt['issuedAt'] > time() || $receipt['issuedAt'] < time()-60 || !is_string($receipt['receiptId'] ?? null) || $receipt['receiptId'] === '' || ($receipt['verified'] ?? null) !== true) throw new RuntimeException('Receipt validation failed');
        return $receipt;
    }
}
