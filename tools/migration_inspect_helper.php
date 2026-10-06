<?php
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '0');
header('Cache-Control: no-store');
/*INSPECT_CONFIG*/
$level = ob_get_level();
$completed = false;
ob_start();
register_shutdown_function(static function () use ($level, &$completed): void {
    while (ob_get_level() > $level) ob_end_clean();
    if (!$completed) { http_response_code(503); echo '{"status":"inspection_refused"}'; }
});
// This separate helper has no mutation actions except deletion of its exact owned bytes.
try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !hash_equals($inspection['keyHash'], hash('sha256', $_SERVER['HTTP_X_A777_OPERATOR_KEY'] ?? ''))) throw new RuntimeException();
    $request = json_decode(file_get_contents('php://input', false, null, 0, 4097), true, 8, JSON_THROW_ON_ERROR);
    if (time() > $inspection['expiresAt'] && ($request['action'] ?? '') !== 'cleanup' && ($request['action'] ?? '') !== 'identity') throw new RuntimeException();
    $sha = hash_file('sha256', __FILE__);
    if (($request['action'] ?? '') === 'identity') {
        $result = ['sha256'=>$sha];
    } elseif (($request['action'] ?? '') === 'cleanup') {
        if (!hash_equals($sha, $request['sha256'] ?? '') || is_link(__FILE__) || !unlink(__FILE__)) throw new RuntimeException();
        $result = ['removed'=>true];
    } elseif (($request['action'] ?? '') === 'inspect') {
        $root = $inspection['runtimeRoot'];
        foreach ([$root, $inspection['documentRoot'], $root.'/config/runtime.php', $root.'/vendor/autoload.php', $root.'/src/Infrastructure/Config/EnvironmentLoader.php'] as $path) {
            if (realpath($path) !== $path || is_link($path)) throw new RuntimeException();
        }
        if (realpath(dirname(__FILE__)) !== $inspection['documentRoot']) throw new RuntimeException();
        $configFingerprint = hash_file('sha256', $root.'/config/runtime.php');
        // Never bootstrap: bootstrap enters the fence and creates lock files.
        require_once $root.'/src/Infrastructure/Config/EnvironmentLoader.php';
        \Architex\Infrastructure\Config\EnvironmentLoader::apply($root);
        require $root.'/vendor/autoload.php';
        $env = getenv();
        $config = \Architex\Infrastructure\Config\AppConfig::fromEnvironment($env);
        $pdo = \Architex\Infrastructure\Database\ConnectionFactory::create($config);
        $database = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!is_string($database) || $database !== $config->dbName || !preg_match('/^[a-zA-Z0-9_]{1,64}$/D', $database)) throw new RuntimeException();
        if ($inspection['database'] !== null && $database !== $inspection['database']) throw new RuntimeException();
        // Snapshot only; no GET_LOCK, ledger creation, MigrationRunner.migrate or status.
        $pdo->exec('SET TRANSACTION READ ONLY');
        $pdo->beginTransaction();
        $exists = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='schema_migrations'")->fetchColumn() === 1;
        $applied = $exists ? $pdo->query('SELECT version,checksum FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_ASSOC) : [];
        $pending = $inspection['migrations'];
        $ledgerMatches = true;
        if ($exists) {
            $table = $pdo->query("SELECT engine,table_collation FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='schema_migrations'")->fetch(PDO::FETCH_ASSOC);
            $columns = $pdo->query("SELECT column_name,column_type,is_nullable,column_default,column_key FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='schema_migrations' ORDER BY ordinal_position")->fetchAll(PDO::FETCH_ASSOC);
            $ledgerMatches = is_array($table) && strcasecmp($table['engine'],'InnoDB') === 0 && strcasecmp($table['table_collation'],'utf8mb4_unicode_ci') === 0 && count($columns) === 3;
            $expected = [['version','varchar(64)','PRI'],['checksum','char(64)',''],['applied_at','datetime(6)','']];
            foreach ($expected as $index=>$definition) {
                $column = $columns[$index] ?? [];
                if (($column['column_name'] ?? '') !== $definition[0] || strtolower($column['column_type'] ?? '') !== $definition[1] || ($column['is_nullable'] ?? '') !== 'NO' || ($column['column_key'] ?? '') !== $definition[2]) $ledgerMatches = false;
            }
            if (!str_contains(strtolower($columns[2]['column_default'] ?? ''),'current_timestamp(6)')) $ledgerMatches = false;
        }
        foreach ($applied as $row) {
            $id = $row['version'];
            // Unknown ledger values never leave the host, even if syntactically plausible.
            if (!isset($pending[$id]) || !hash_equals($pending[$id], $row['checksum'])) { $ledgerMatches = false; continue; }
            unset($pending[$id]);
        }
        $pdo->commit();
        $process = false;
        try {
            foreach ($pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN) as $grant) {
                if (preg_match('/^GRANT (?:ALL PRIVILEGES|[^\n]*\bPROCESS\b[^\n]*) ON \*\.\*/', $grant)) $process = true;
            }
        } catch (Throwable) {}
        $proc = is_dir('/proc') && function_exists('posix_geteuid');
        if ($proc) foreach (glob('/proc/[0-9]*') ?: [] as $path) {
            $stat = @stat($path);
            if (!$stat) { if (is_dir($path)) $proc = false; continue; }
            if ($stat['uid'] === posix_geteuid() && (!is_readable($path.'/cmdline') || !is_readable($path.'/stat'))) $proc = false;
        }
        $cli = false;
        if (function_exists('proc_open') && is_executable('/usr/local/bin/php')) {
            $child = proc_open(['/usr/local/bin/php','-r','exit(PHP_VERSION_ID >= 80300 ? 0 : 1);'], [0=>['pipe','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']], $pipes, null, []);
            if (is_resource($child)) { fclose($pipes[0]); $cli = proc_close($child) === 0; }
        }
        $storage = $env['FILE_STORAGE_PATH'] ?? '';
        $private = is_string($storage) && realpath($storage) === $storage && is_dir($storage) && !is_link($storage) && !str_starts_with($storage.'/', $root.'/') && !str_starts_with($storage.'/', $inspection['documentRoot'].'/');
        $zip = class_exists('ZipArchive') && method_exists('ZipArchive','isEncryptionMethodSupported') && ZipArchive::isEncryptionMethodSupported(ZipArchive::EM_AES_256, true) && ZipArchive::isEncryptionMethodSupported(ZipArchive::EM_AES_256, false);
        if (!hash_equals($configFingerprint, hash_file('sha256', $root.'/config/runtime.php'))) throw new RuntimeException();
        $result = ['databaseIdentity'=>['configDatabase'=>$config->dbName,'selectedDatabase'=>$database,'runtimeConfigSha256'=>$configFingerprint,'runtimeRoot'=>$root], 'targetDatabaseMatches'=>true, 'ledgerExists'=>$exists, 'ledgerChecksumsMatch'=>$ledgerMatches,
            'pendingVersions'=>$ledgerMatches ? array_keys($pending) : [], 'pendingCount'=>$ledgerMatches ? count($pending) : null,
            'capabilities'=>['processPrivilege'=>$process,'procReadable'=>$proc,'procOpen'=>function_exists('proc_open'),'sodium'=>function_exists('sodium_crypto_sign_verify_detached'),'cliPhp83'=>$cli,
                'zipAes256'=>$zip,'backupPasswordConfigured'=>strlen($env['BACKUP_ARCHIVE_PASSWORD'] ?? '') >= 20,'backupHmacKeyConfigured'=>strlen(base64_decode($env['APP_KEY'] ?? '',true) ?: '') === 32,
                'privateStorage'=>$private,'storageWritable'=>$private && is_writable($storage), 'providerInventoryEstablished'=>false,'quiescenceEstablished'=>false,'recoverableBackupEstablished'=>false],
            'ddlAuthorized'=>false];
    } else throw new RuntimeException();
    header('Content-Type: application/json');
    $encoded = json_encode(['challenge'=>$request['challenge'],'result'=>$result], JSON_THROW_ON_ERROR);
    while (ob_get_level() > $level) ob_end_clean();
    $completed = true;
    echo $encoded;
} catch (Throwable) {
    while (ob_get_level() > $level) ob_end_clean();
    $completed = true;
    http_response_code(503); echo '{"status":"inspection_refused"}';
}
