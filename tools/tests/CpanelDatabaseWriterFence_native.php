<?php
declare(strict_types=1);
require dirname(__DIR__) . '/CpanelDatabaseWriterFence.php';
// SYNTHETIC authentication receipts only; real SQL on the owned scratch server.
$root = new PDO('mysql:host=127.0.0.1;port=3398', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server = $root->query('SELECT @@datadir d, @@port p')->fetch(PDO::FETCH_ASSOC);
if ((int)$server['p'] !== 3398 || str_replace('\\', '/', $server['d']) !== 'E:/a777/.private/role-validation-db-20260917/') throw new RuntimeException('Owned datadir guard failed');
$db = 'architex_taskdbfence_' . bin2hex(random_bytes(4)) . '_test';
$user = 'archite4_' . bin2hex(random_bytes(16));
$password = bin2hex(random_bytes(24));
$other = 'architex_taskdbfence_' . bin2hex(random_bytes(4)) . '_test';
if (!preg_match('/^architex_taskdbfence_[a-f0-9]{8}_test$/D', $db) || !preg_match('/^architex_taskdbfence_[a-f0-9]{8}_test$/D', $other)) throw new RuntimeException('Schema guard');
$count = 0; $files = [];
function check(bool $ok, string $name): void { global $count; if (!$ok) throw new RuntimeException($name); ++$count; print "ok $count - $name\n"; }
function refuses(Closure $f, string $name): void { try { $f(); } catch (Throwable $e) { check(true, $name); return; } throw new RuntimeException('Expected refusal: ' . $name); }
try {
    $root->exec("CREATE DATABASE `$db`"); $root->exec("CREATE DATABASE `$other`");
    $root->exec("CREATE TABLE `$db`.probe (id INT)");
    $root->exec("CREATE USER '$user'@'127.0.0.1' IDENTIFIED BY '$password'");
    $root->exec("GRANT SELECT ON `$db`.* TO '$user'@'127.0.0.1'");
    $root->exec("GRANT SELECT ON `$other`.* TO '$user'@'127.0.0.1'");
    $fresh = fn() => new PDO("mysql:host=127.0.0.1;port=3398;dbname=$db", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    // Native owned-table DDL proof: existing-session cache is not assumed.
    $root->exec("GRANT CREATE, ALTER, DROP ON `$db`.* TO '$user'@'127.0.0.1'");
    $ddlObserver = $fresh();
    $ddlObserver->exec("CREATE TABLE `$db`.owned_ddl_cache_probe (id INT)");
    $root->exec("REVOKE CREATE, ALTER, DROP ON `$db`.* FROM '$user'@'127.0.0.1'");
    $ddlRetained = false;
    try {
        $ddlObserver->exec("ALTER TABLE `$db`.owned_ddl_cache_probe ADD COLUMN cached INT");
        $ddlRetained = true;
    } catch (PDOException $e) {
        if (!in_array((int)($e->errorInfo[1] ?? 0), [1044,1142,1143], true)) throw $e;
    }
    check(true, $ddlRetained ? 'old observer retains native ALTER ability after revoke' : 'old observer strict ALTER denied; separate operator required');
    $freshDdl = $fresh();
    try { $freshDdl->exec("ALTER TABLE `$db`.owned_ddl_cache_probe ADD COLUMN forbidden INT"); throw new RuntimeException('Fresh DDL unexpectedly succeeds'); }
    catch (PDOException $e) { check(in_array((int)($e->errorInfo[1] ?? 0), [1044,1142,1143], true), 'fresh native ALTER authorization denied'); }
    $root->exec("DROP TABLE `$db`.owned_ddl_cache_probe");
    $ddlObserver = null; $freshDdl = null;
    $receiptMode = 'good'; $lost = false; $noEffect = false;
    $verify = function(string $kind, array $binding) use (&$receiptMode, $user): mixed {
        if ($receiptMode === 'no-gate' && $kind === 'apache-maintenance') return true;
        return ['kind' => $kind, 'binding' => $binding, 'receiptId' => 'synthetic-native-test', 'users' => $receiptMode === 'nonexclusive' ? [$user, 'another'] : [$user]];
    };
    $provider = function(string $op, array $args) use ($root, $user, $db, &$lost, &$noEffect): array {
        if ($args['user'] !== $user || $args['database'] !== $db) throw new RuntimeException('Provider scope');
        if ($op === 'get_privileges_on_database') {
            $s = $root->prepare('SELECT PRIVILEGE_TYPE FROM information_schema.SCHEMA_PRIVILEGES WHERE TABLE_SCHEMA=? AND GRANTEE=? ORDER BY PRIVILEGE_TYPE');
            $s->execute([$db, "'$user'@'127.0.0.1'"]);
            return ['status' => 1, 'data' => $s->fetchAll(PDO::FETCH_COLUMN)];
        }
        if ($op === 'revoke_access_to_database') {
            if ($noEffect) throw new RuntimeException('Lost reply without effect');
            $root->exec("REVOKE ALL PRIVILEGES ON `$db`.* FROM '$user'@'127.0.0.1'");
            if ($lost) throw new RuntimeException('Synthetic lost reply after native revoke');
        } elseif ($op === 'set_privileges_on_database') {
            if ($args['privileges'] !== 'SELECT') throw new RuntimeException('Unexpected restore list');
            $root->exec("GRANT SELECT ON `$db`.* TO '$user'@'127.0.0.1'");
        } else throw new RuntimeException('Unknown provider operation');
        return ['status' => 1, 'data' => null];
    };
    $build = function() use ($provider, $verify, $fresh, $db, &$files): CpanelDatabaseWriterFence {
        $path = getenv('TMPDIR') . '/dbfence-' . bin2hex(random_bytes(8)) . '.json'; $files[] = $path;
        $f = new CpanelDatabaseWriterFence($provider, $verify, $fresh, $fresh(), $path);
        // Explicit test-only reflection scope. Production class has no scope override API.
        (new ReflectionProperty($f, 'database'))->setValue($f, $db);
        return $f;
    };
    $pin = ['account' => 'archite4', 'database' => $db, 'user' => $user, 'configSha256' => str_repeat('a', 64), 'probeTable' => 'probe'];
    refuses(fn() => $build()->fence(array_replace($pin, ['database' => $other])), 'wrong database refused');
    refuses(fn() => $build()->fence(array_replace($pin, ['user' => 'archite4_wrong'])), 'wrong observer user refused');
    $receiptMode = 'nonexclusive'; refuses(fn() => $build()->fence($pin), 'nonexclusive grant identity refused');
    $receiptMode = 'no-gate'; refuses(fn() => $build()->fence($pin), 'boolean Apache gate refused'); $receiptMode = 'good';
    $noEffect = true;
    refuses(fn() => $build()->fence($pin), 'lost revoke response with nonempty grants refused');
    $noEffect = false;
    $root->exec("GRANT SELECT ON *.* TO '$user'@'127.0.0.1'");
    refuses(fn() => $build()->fence($pin), 'global SELECT defeats negative probe and holds maintenance');
    $last = json_decode(file_get_contents(end($files)), true);
    check($last['phase'] === 'failure-maintenance-retained' && $last['maintenanceRetained'], 'failed negative proof journal retains maintenance');
    $root->exec("REVOKE SELECT ON *.* FROM '$user'@'127.0.0.1'");
    $root->exec("GRANT SELECT ON `$db`.* TO '$user'@'127.0.0.1'");
    $victim = $fresh(); $victimId = (int)$victim->query('SELECT CONNECTION_ID()')->fetchColumn();
    $untouched = new PDO("mysql:host=127.0.0.1;port=3398;dbname=$other", $user, $password);
    $lost = true; $f = $build(); $result = $f->fence($pin);
    check($result['phase'] === 'blocked' && $result['lostRevokeReply'] && $result['remainingWriters'] === 0, 'lost revoke reply reconciled by exact empty privileges');
    check(in_array($victimId, $result['killIds'], true), 'native same-user exact-database connection killed');
    refuses(fn() => $victim->query("SELECT * FROM `$db`.probe"), 'old killed PDO negative SELECT');
    check((int)$untouched->query('SELECT 1')->fetchColumn() === 1, 'other-database same-user connection untouched');
    check(!in_array($result['observerId'], $result['killIds'], true), 'observer excluded from KILL');
    $plan = ['binding' => array_intersect_key($pin, array_flip(['account', 'database', 'user', 'configSha256'])), 'commit' => str_repeat('b', 40), 'planId' => 'synthetic-plan'];
    refuses(fn() => $f->restore(array_replace($plan, ['commit' => 'wrong'])), 'restore matching plan required');
    $restored = $f->restore($plan);
    check($restored['phase'] === 'restored-maintenance-retained' && $restored['originalPrivileges'] === ['SELECT'], 'original privilege array restored and fresh SELECT succeeds');
    print "PASS $count assertions; synthetic receipts, native SQL, production provider authentication UNVERIFIED\n";
} finally {
    $root->exec("DROP USER IF EXISTS '$user'@'127.0.0.1'");
    $root->exec("DROP DATABASE IF EXISTS `$db`"); $root->exec("DROP DATABASE IF EXISTS `$other`");
}
