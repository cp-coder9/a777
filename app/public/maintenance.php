<?php

declare(strict_types=1);

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Infrastructure\Config\AppConfig;
use Architex\Infrastructure\Database\ConnectionFactory;
use Architex\Infrastructure\Database\MigrationRunner;
use Architex\Infrastructure\Mail\MailRuntime;

require dirname(__DIR__) . '/bootstrap.php';

header('Cache-Control: no-store');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");

$request = Request::fromGlobals();
$environment = getenv();
$environment = is_array($environment) ? $environment : [];
$configuredToken = is_string($environment['MAINTENANCE_TOKEN'] ?? null) ? $environment['MAINTENANCE_TOKEN'] : '';
$provided = $request->header('Authorization');
$provided = is_string($provided) && str_starts_with($provided, 'Bearer ') ? substr($provided, 7) : '';
if (($environment['MAINTENANCE_ENABLED'] ?? '') !== '1' || strlen($configuredToken) < 32 || !hash_equals($configuredToken, $provided)) {
    Response::error(404, 'not_found', 'Route not found.')->emit();
    return;
}
if ($request->method() !== 'POST') {
    Response::error(405, 'method_not_allowed', 'Method not allowed.', headers: ['Allow'=>'POST'])->emit();
    return;
}

try {
    $body = $request->jsonBody();
    $action = $body['action'] ?? null;
    if (!in_array($action, ['preflight','migrate'], true)) {
        Response::error(422, 'validation_failed', 'The maintenance action is invalid.')->emit();
        return;
    }
    $config = AppConfig::fromEnvironment($environment);
    $pdo = ConnectionFactory::create($config);
    $migrations = new MigrationRunner($pdo, dirname(__DIR__) . '/database/migrations');
    if ($action === 'migrate') {
        $backup = $request->header('X-Backup-Id');
        $expectedBackup = $environment['LAST_VERIFIED_BACKUP_ID'] ?? null;
        if (($environment['MAINTENANCE_ALLOW_MIGRATIONS'] ?? '') !== '1' || !is_string($backup) || !is_string($expectedBackup) || strlen($expectedBackup) < 16 || !hash_equals($expectedBackup, $backup)) {
            Response::error(409, 'backup_gate_required', 'A verified backup acknowledgement is required.')->emit();
            return;
        }
        $migrations->migrate();
    }
    $runtime = MailRuntime::fromEnvironment($pdo, $environment);
    $storage = dirname(__DIR__) . '/storage';
    if (!is_dir($storage) && !mkdir($storage, 0700, true) && !is_dir($storage)) {
        throw new RuntimeException('storage');
    }
    $probe = $storage . '/.preflight-' . bin2hex(random_bytes(6));
    $writable = file_put_contents($probe, 'ok', LOCK_EX) === 2;
    if (is_file($probe)) unlink($probe);
    $status = $migrations->status();
    $allReady = PHP_VERSION_ID >= 80300 && $status->isCurrent() && $writable && $runtime->isReady();
    Response::success([
        'status'=>$allReady ? 'ready' : 'unavailable',
        'components'=>[
            'php'=>['status'=>PHP_VERSION_ID >= 80300 ? 'ready' : 'unavailable','version'=>PHP_VERSION],
            'database'=>['status'=>(int)$pdo->query('SELECT 1')->fetchColumn() === 1 ? 'ready' : 'unavailable'],
            'migrations'=>['status'=>$status->isCurrent() ? 'ready' : 'pending','pending'=>$status->pendingVersions],
            'storage'=>['status'=>$writable ? 'ready' : 'unavailable'],
            'mail'=>['status'=>$runtime->isReady() ? 'ready' : 'unavailable'],
        ],
    ], status: $allReady ? 200 : 503)->emit();
} catch (Throwable) {
    Response::error(503, 'maintenance_unavailable', 'Maintenance preflight could not be completed.')->emit();
}
