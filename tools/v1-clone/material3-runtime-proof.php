<?php
declare(strict_types=1);
// Synthetic isolated native-runtime proof. Never loads app/bootstrap or runtime.php.
$root = realpath(__DIR__ . '/../..');
$work = 'E:/Hermes/home/cache/scratch/material3-runtime';
require 'E:/a777/vendor/autoload.php';
spl_autoload_register(static function (string $class) use ($root): void {
    if (str_starts_with($class, 'Architex\\')) {
        $relative = substr($class, 9);
        $path = $root . '/app/src/' . str_replace('\\', '/', $relative) . '.php';
        if (str_starts_with($relative, 'Tests\\')) $path = $root . '/app/tests/' . str_replace('\\', '/', substr($relative, 6)) . '.php';
        if (is_file($path)) require $path;
    }
}, true, true);
$statePath = $work . '/state.json';
if (!is_file($statePath)) {
    $state = ['schema'=>'architex_taskm3' . bin2hex(random_bytes(3)) . '_' . bin2hex(random_bytes(4)) . '_test', 'dbPort'=>59946];
    $handle = fopen($statePath, 'x');
    if ($handle === false) throw new RuntimeException('State collision');
    fwrite($handle, json_encode($state, JSON_PRETTY_PRINT)); fflush($handle); fclose($handle);
}
$state = json_decode(file_get_contents($statePath), true, 512, JSON_THROW_ON_ERROR);
$env = ['ARCHITEX_ALLOW_DISPOSABLE_DB'=>'1','APP_ENV'=>'test','APP_DEBUG'=>'false','DB_HOST'=>'127.0.0.1','DB_PORT'=>(string)$state['dbPort'],'DB_NAME'=>$state['schema'],'DB_USER'=>'root','DB_PASSWORD'=>''];
Architex\Infrastructure\Database\DisposableDatabaseGuard::assertAllowed($env);
$paths = [];
foreach ([Architex\Infrastructure\Database\DisposableDatabaseGuard::class, Architex\Infrastructure\Database\MigrationRunner::class, Architex\Tests\Support\MariaDbTestCase::class] as $class) {
    $path = str_replace('\\', '/', (new ReflectionClass($class))->getFileName());
    if (!str_starts_with($path, str_replace('\\', '/', $root) . '/app/')) throw new RuntimeException('Wrong-source autoload');
    $paths[$class] = $path;
}
$pdo = new PDO('mysql:host=127.0.0.1;port=' . $state['dbPort'] . ';charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
$datadir = str_replace('\\', '/', (string)$pdo->query('SELECT @@datadir')->fetchColumn());
if (rtrim($datadir, '/') !== $work . '/data') throw new RuntimeException('Not owned scratch server');
$pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $state['schema'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('USE `' . $state['schema'] . '`');
$report = (new Architex\Infrastructure\Database\MigrationRunner($pdo, $root . '/app/database/migrations'))->migrate();
$receipt = ['synthetic'=>true,'schema'=>$state['schema'],'dbPort'=>$state['dbPort'],'datadirVerified'=>true,'candidatePaths'=>$paths,'migrationCount'=>(int)$pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn(),'pending'=>$report->pendingCount,'lenses'=>(new Architex\Platform\Demo\DemoRoleLensRegistry())->all(),'httpRuntime'=>'not-established','roleMenuWorkflows'=>'not-exercised'];
file_put_contents($work . '/receipt.json', json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo json_encode(['schema'=>$receipt['schema'],'migrationCount'=>$receipt['migrationCount'],'pending'=>$receipt['pending'],'lensCount'=>count($receipt['lenses']),'datadirVerified'=>true]) . PHP_EOL;
