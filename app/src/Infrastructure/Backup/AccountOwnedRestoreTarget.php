<?php

declare(strict_types=1);

namespace Architex\Infrastructure\Backup;

use PDO;
use RuntimeException;

/** In-process capability issued only by the fixed, authenticated account UAPI executable.
 * Receipts cannot be imported from JSON/environment. Retain failed targets for operator review.
 */
final class AccountOwnedRestoreTarget
{
    private static ?\WeakMap $issued = null;
    private function __construct(
        private readonly string $name,
        private readonly string $source,
        private readonly string $host,
        private readonly string $server,
        private readonly string $databaseUser,
        private readonly array $receipt,
    ) {}

    public function __serialize(): array { throw new RuntimeException('Restore capabilities cannot be serialized.'); }
    public function __unserialize(array $data): void { throw new RuntimeException('Restore capabilities cannot be imported.'); }
    private function __clone() {}

    /** Run as archite4 over an authenticated account shell, not through application HTTP.
     * Source identity is read from the actual application PDO, never a caller assertion.
     */
    public static function provision(PDO $sourcePdo, string $host, string $databaseUser): self
    {
        if (!in_array($host, ['127.0.0.1', '::1'], true)) throw new RuntimeException('A literal loopback host is required.');
        if (PHP_OS_FAMILY === 'Windows' || !function_exists('posix_geteuid') || (posix_getpwuid(posix_geteuid())['name'] ?? '') !== 'archite4') throw new RuntimeException('Authenticated archite4 account shell is required.');
        if (!preg_match('/\Aarchite4_[a-zA-Z0-9_]+\z/', $databaseUser)) throw new RuntimeException('Database user must belong to archite4.');
        $connection = (string)$sourcePdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
        if (!str_starts_with($connection, $host . ' ')) throw new RuntimeException('Actual source PDO must use the bound loopback transport.');
        $source = $sourcePdo->query('SELECT DATABASE()')->fetchColumn();
        if ($source !== 'archite4_dev_rel1') throw new RuntimeException('Unexpected actual application source database.');
        $name = 'archite4_ar_' . bin2hex(random_bytes(8)) . '_test';
        $before = self::uapi('list_databases');
        self::assertInventory($before, $name, false);
        $create = self::uapi('create_database', ['name'=>$name]);
        $grant = self::uapi('set_privileges_on_database', ['user'=>$databaseUser, 'database'=>$name, 'privileges'=>'ALL PRIVILEGES']);
        $after = self::uapi('list_databases');
        self::assertInventory($after, $name, true);
        $privileges = self::uapi('get_privileges_on_database', ['user'=>$databaseUser, 'database'=>$name]);
        $data = $privileges['result']['data'] ?? null;
        if (!is_array($data) || !in_array('ALL PRIVILEGES', $data, true)) throw new RuntimeException('Exact database grant readback failed; retain target for operator.');
        $receipt = ['account'=>'archite4', 'target'=>$name, 'source'=>$source, 'host'=>$host, 'server'=>self::serverIdentity($sourcePdo), 'user'=>$databaseUser, 'nonce'=>bin2hex(random_bytes(32)), 'create'=>$create, 'grant'=>$grant, 'inventory'=>$after, 'privileges'=>$privileges];
        $target = new self($name, $source, $host, self::serverIdentity($sourcePdo), $databaseUser, $receipt);
        self::$issued ??= new \WeakMap();
        self::$issued[$target] = hash('sha256', json_encode($receipt, JSON_THROW_ON_ERROR));
        return $target;
    }

    /** Read-only caller rehearsal; validates the actual pinned runtime, source PDO and provider inventory. */
    public static function preflightManaged(PDO $sourcePdo, \Architex\Infrastructure\Config\AppConfig $config, string $runtimeSha256, CpanelRestoreProvisioner $provider): array
    {
        $pin = '5206e3a1f9447e6834fc3e2dd7cd254ce5dd47af33406c0c83085c4b4f28d0b8';
        $runtime = '/home/archite4/public_html/.a777-runtime-active/config/runtime.php';
        if (PHP_OS_FAMILY !== 'Linux' || !function_exists('posix_geteuid') || (posix_getpwuid(posix_geteuid())['name'] ?? '') !== 'archite4') throw new RuntimeException('Actual managed account owner mismatch.');
        foreach (['/home', '/home/archite4', '/home/archite4/public_html', '/home/archite4/public_html/.a777-runtime-active', dirname($runtime)] as $directory) {
            if (is_link($directory) || realpath($directory) !== $directory) throw new RuntimeException('Actual managed runtime canonical path mismatch.');
        }
        if (fileowner(dirname($runtime)) !== posix_geteuid() || (fileperms(dirname($runtime)) & 0777) !== 0700 || fileowner($runtime) !== posix_geteuid() || (fileperms($runtime) & 0777) !== 0600) throw new RuntimeException('Actual managed runtime privacy mismatch.');
        if ($runtimeSha256 !== $pin || is_link($runtime) || realpath($runtime) !== $runtime || !is_file($runtime) || !hash_equals($pin, (string)hash_file('sha256', $runtime))) throw new RuntimeException('Actual managed runtime pin mismatch.');
        $values = require $runtime;
        if (!is_array($values)) throw new RuntimeException('Actual runtime unavailable.');
        $actual = \Architex\Infrastructure\Config\AppConfig::fromEnvironment($values);
        self::assertManagedSourceConfig($actual, $config);
        if (!hash_equals($pin, (string)hash_file('sha256', $runtime))) throw new RuntimeException('Actual managed runtime changed during load.');
        $connection = (string)$sourcePdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
        if (!str_starts_with($connection, $config->dbHost . ' ')) throw new RuntimeException('Actual configured source transport mismatch.');
        if ($sourcePdo->query('SELECT DATABASE()')->fetchColumn() !== $config->dbName) throw new RuntimeException('Actual configured source database mismatch.');
        $user = (string)$sourcePdo->query('SELECT CURRENT_USER()')->fetchColumn();
        if (explode('@', $user, 2)[0] !== $config->dbUser) throw new RuntimeException('Actual configured source grant owner mismatch.');
        $server = self::serverIdentity($sourcePdo);
        $tuple = json_decode($server, true, 64, JSON_THROW_ON_ERROR);
        if ((int)($tuple['port'] ?? 0) !== $config->dbPort || empty($tuple['hostname']) || !isset($tuple['server_id'])) throw new RuntimeException('Actual configured source server tuple mismatch.');
        $provider->preflight($config->dbName, $config->dbUser);
        return ['host'=>$config->dbHost,'port'=>$config->dbPort,'source'=>$config->dbName,'user'=>$config->dbUser,'currentUser'=>$user,'server'=>$server,'runtimeSha256'=>$pin];
    }

    /** AppConfig validates APP_ENV; production is valid for the fixed source, never a target. */
    private static function assertManagedSourceConfig(\Architex\Infrastructure\Config\AppConfig $actual, \Architex\Infrastructure\Config\AppConfig $config): void
    {
        if ($actual != $config || $config->dbName !== 'archite4_dev_rel1') throw new RuntimeException('Actual managed source configuration mismatch.');
    }

    /** Capability factory: managed transport accepted only after pinned source + authenticated inventory proof. */
    public static function provisionManaged(PDO $sourcePdo, \Architex\Infrastructure\Config\AppConfig $actualConfig, string $pinnedRuntimeSha256, CpanelRestoreProvisioner $provider): self
    {
        $binding = self::preflightManaged($sourcePdo, $actualConfig, $pinnedRuntimeSha256, $provider);
        $name = 'archite4_ar_' . bin2hex(random_bytes(8)) . '_test';
        $receipt = $provider->provision($name, $binding['source'], $binding['user'], $binding);
        $receipt['host'] = $binding['host']; $receipt['server'] = $binding['server'];
        $receipt['managed'] = true; $receipt['nonce'] = bin2hex(random_bytes(32));
        $target = new self($name, $binding['source'], $binding['host'], $binding['server'], $binding['user'], $receipt);
        self::$issued ??= new \WeakMap();
        self::$issued[$target] = hash('sha256', json_encode($receipt, JSON_THROW_ON_ERROR));
        return $target;
    }

    public function database(): string { return $this->name; }
    public function receipt(): array { return $this->receipt; }

    public function assertArchiveSource(mixed $source): void
    {
        if (!is_string($source) || $source !== $this->source || $source === $this->name) throw new RuntimeException('Archive source identity mismatch.');
    }

    public function assertEmpty(PDO $pdo): void
    {
        if (self::$issued === null || !isset(self::$issued[$this]) || !hash_equals(self::$issued[$this], hash('sha256', json_encode($this->receipt, JSON_THROW_ON_ERROR)))) throw new RuntimeException('Unissued or altered provisioning receipt.');
        if (!preg_match('/\Aarchite4_ar_[a-f0-9]{16}_test\z/', $this->name) || $this->source !== 'archite4_dev_rel1' || $this->name === $this->source || (!(($this->receipt['managed'] ?? false) === true && ($this->receipt['binding']['host'] ?? null) === $this->host && ($this->receipt['binding']['runtimeSha256'] ?? '') === '5206e3a1f9447e6834fc3e2dd7cd254ce5dd47af33406c0c83085c4b4f28d0b8') && !in_array($this->host, ['127.0.0.1','::1'], true)) || ($this->receipt['account'] ?? '') !== 'archite4' || ($this->receipt['target'] ?? '') !== $this->name || ($this->receipt['user'] ?? '') !== $this->databaseUser) throw new RuntimeException('Owned target binding mismatch.');
        if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== $this->name || self::serverIdentity($pdo) !== $this->server) throw new RuntimeException('Actual target PDO identity mismatch.');
        $connection = (string)$pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
        if (!str_starts_with($connection, $this->host . ' ')) throw new RuntimeException('Actual PDO must use the bound loopback transport.');
        $user = (string)$pdo->query('SELECT CURRENT_USER()')->fetchColumn();
        if (($this->receipt['managed'] ?? false) === true && $user !== ($this->receipt['binding']['currentUser'] ?? null)) throw new RuntimeException('Exact managed CURRENT_USER binding mismatch.');
        if (explode('@', $user, 2)[0] !== $this->databaseUser) throw new RuntimeException('Actual target grant owner mismatch.');
        foreach (['tables'=>'table_schema','routines'=>'routine_schema','events'=>'event_schema'] as $table=>$column) {
            $query = $pdo->prepare("SELECT COUNT(*) FROM information_schema.$table WHERE $column = :database");
            $query->execute(['database'=>$this->name]);
            if ((int)$query->fetchColumn() !== 0) throw new RuntimeException('Provisioned target is not empty (tables/views/routines/events).');
        }
    }

    private static function serverIdentity(PDO $pdo): string
    {
        return json_encode($pdo->query('SELECT @@hostname hostname, @@port port, @@server_id server_id')->fetch(PDO::FETCH_ASSOC), JSON_THROW_ON_ERROR);
    }

    private static function assertInventory(array $response, string $name, bool $present): void
    {
        $rows = $response['result']['data'] ?? null;
        if (!is_array($rows)) throw new RuntimeException('Database inventory unavailable.');
        $matches = array_values(array_filter($rows, static fn($row) => is_array($row) && ($row['database'] ?? null) === $name));
        if (count($matches) !== ($present ? 1 : 0)) throw new RuntimeException('Exact unique owned database inventory mismatch.');
    }

    private static function uapi(string $function, array $arguments = []): array
    {
        $binary = '/usr/local/cpanel/bin/uapi';
        if (!is_file($binary) || !is_executable($binary)) throw new RuntimeException('Official cPanel UAPI CLI is unavailable.');
        $command = [$binary, '--output=json', '--user=archite4', 'Mysql', $function];
        foreach ($arguments as $key=>$value) $command[] = $key . '=' . $value;
        $process = proc_open($command, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, null, null, ['bypass_shell'=>true]);
        if (!is_resource($process)) throw new RuntimeException('UAPI process failed.');
        fclose($pipes[0]); $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
        stream_get_contents($pipes[2]); fclose($pipes[2]); $exit = proc_close($process);
        return self::validatedResponse($output, $exit);
    }

    private static function validatedResponse(string $output, int $exit): array
    {
        $response = json_decode($output, true, 64, JSON_THROW_ON_ERROR);
        if ($exit !== 0 || !is_array($response) || ($response['result']['status'] ?? null) !== 1 || !empty($response['result']['errors'])) throw new RuntimeException('Authenticated UAPI operation failed; retain any created target for operator.');
        return $response;
    }
}
