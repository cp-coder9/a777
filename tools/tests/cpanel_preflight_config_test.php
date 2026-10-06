<?php
declare(strict_types=1);
require dirname(__DIR__) . '/cpanel_git_deploy.php';
require dirname(__DIR__, 2) . '/app/src/Infrastructure/Config/AppConfig.php';
require dirname(__DIR__, 2) . '/app/src/Infrastructure/Database/ConnectionFactory.php';
use Architex\Infrastructure\Config\AppConfig;
use Architex\Infrastructure\Database\ConnectionFactory;
$checks = 0;
function verifyConfig(bool $ok): void { global $checks; if (!$ok) throw new RuntimeException('assertion failed'); $checks++; }
function rejectConfig(callable $call, string $gate): void {
    try { $call(); } catch (RuntimeException $e) { verifyConfig($e->getMessage() === $gate); return; }
    throw new RuntimeException('expected refusal');
}
// Official OpenAPI /Mysql/list_users result.data shape, not live inventory.
$config = ['APP_ENV'=>'development', 'DB_HOST'=>'localhost', 'DB_NAME'=>'archite4_dev_rel1', 'DB_USER'=>'archite4', 'DB_PASSWORD'=>'fixture-not-a-secret', 'DB_PORT'=>'43307'];
$users = [['user'=>'archite4', 'shortuser'=>'archite4', 'databases'=>['archite4_dev_rel1']]];
$dbs = [['database'=>'archite4_dev_rel1', 'users'=>['archite4']]];
$before = serialize($config);
foreach (['localhost'=>'local-socket', '127.0.0.1'=>'tcp-loopback', '::1'=>'tcp-loopback'] as $host=>$class) {
    $e = []; $input = array_replace($config, ['DB_HOST'=>$host]);
    $typed = CpanelGitDeploy::databaseConfig($input, $users, $dbs, $e);
    verifyConfig($e['databaseHostClass'] === $class && $e['databaseUserInventoryMatches'] && $typed->dbPort === 43307);
}
verifyConfig(serialize($config) === $before);
foreach ([['DB_HOST'=>'remote.example'], ['DB_HOST'=>'127.0.0.2'], ['DB_NAME'=>'archite4_wrong'], ['DB_USER'=>'archite4_other'], ['DB_PORT'=>'0'], ['DB_PASSWORD'=>false]] as $change) {
    $e = [];
    rejectConfig(fn()=>CpanelGitDeploy::databaseConfig(array_replace($config, $change), $users, $dbs, $e), 'active_database_config_identity_mismatch');
}
foreach ([[], [['user'=>'archite4','databases'=>[]]], [$users[0],$users[0]], ['archite4'=>true], [['username'=>'archite4','databases'=>['archite4_dev_rel1']]]] as $inventory) {
    $e = [];
    rejectConfig(fn()=>CpanelGitDeploy::databaseConfig($config, $inventory, $dbs, $e), 'active_database_config_identity_mismatch');
}
class IdentityStatement extends PDOStatement {
    public function __construct(private mixed $answer) {}
    public function fetchColumn(int $column = 0): mixed { return $this->answer; }
}
class IdentityPdo extends PDO {
    public function __construct(private string $database, private string $user) {}
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
        return new IdentityStatement($query === 'SELECT DATABASE()' ? $this->database : $this->user);
    }
}
$e = []; $typed = CpanelGitDeploy::databaseConfig($config, $users, $dbs, $e);
CpanelGitDeploy::pdoIdentity(new IdentityPdo('archite4_dev_rel1', 'archite4@localhost'), $typed, $e);
verifyConfig($e['pdoDatabaseMatches'] && $e['pdoUserMatches']);
rejectConfig(fn()=>CpanelGitDeploy::pdoIdentity(new IdentityPdo('archite4_wrong','archite4@localhost'), $typed, $e), 'actual_source_pdo_identity_mismatch');
rejectConfig(fn()=>CpanelGitDeploy::pdoIdentity(new IdentityPdo('archite4_dev_rel1','other@localhost'), $typed, $e), 'actual_source_pdo_identity_mismatch');
foreach ([AppConfig::class, ConnectionFactory::class] as $class) {
    $path = str_replace('\\', '/', (new ReflectionClass($class))->getFileName());
    verifyConfig(str_starts_with($path, str_replace('\\', '/', realpath(dirname(__DIR__, 2))) . '/app/src/'));
    print 'class-path ' . $path . "\n";
}
// Contract check on the real factory: port is not replaced by a fixed 3306.
$factory = file_get_contents((new ReflectionClass(ConnectionFactory::class))->getFileName());
verifyConfig(str_contains($factory, 'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4') && str_contains($factory, '$config->dbPort'));
verifyConfig(!str_contains(json_encode($e), 'fixture-not-a-secret') && !str_contains(json_encode($e), 'archite4'));
print "$checks config/PDO-fixture assertions passed; factory DSN contract checked; native host/port connection NOT proven\n";
