<?php
declare(strict_types=1);

// CLI-only native capability inspection and guarded recovery primitives.
// Default Git invocation never executes incoming code or authorizes live DDL.
final class CpanelGitDeploy
{
    public const REPO = '/home/archite4/repositories/a777';
    public const PRIVATE_ROOT = '/home/archite4/.a777-deploy';
    public const ACTIVE = '/home/archite4/public_html/.a777-runtime-active';
    public const DOCROOT = '/home/archite4/public_html/php.architex.co.za';

    public static function requireValue(bool $ok, string $gate): void
    {
        if (!$ok) throw new RuntimeException($gate);
    }

    public static function command(array $argv, string $cwd): string
    {
        $process = proc_open($argv, [['pipe','r'], ['pipe','w'], ['pipe','w']], $pipes, $cwd);
        self::requireValue(is_resource($process), 'command_unavailable');
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
        stream_get_contents($pipes[2]); fclose($pipes[2]);
        self::requireValue(proc_close($process) === 0 && is_string($output), 'command_failed');
        return trim($output);
    }

    // Tree digest includes the manifest itself. No executable artifact is loaded.
    public static function inventory(string $root): array
    {
        self::requireValue(is_dir($root) && !is_link($root), 'artifact_root_invalid');
        $files = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $item) {
            self::requireValue(!$item->isLink(), 'artifact_symlink');
            if ($item->isDir()) continue;
            self::requireValue($item->isFile(), 'artifact_special_file');
            $name = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
            self::requireValue(!preg_match('#(^|/)(?:\.git|\.private|\.env(?:\.[^/]*)?|runtime\.php)(/|$)#i', $name), 'artifact_private_content');
            $files[$name] = ['sha256'=>hash_file('sha256', $item->getPathname()), 'bytes'=>$item->getSize()];
        }
        ksort($files, SORT_STRING);
        return $files;
    }

    public static function digest(array $inventory): string
    {
        return hash('sha256', json_encode($inventory, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public static function journal(string $path, array $data): void
    {
        self::requireValue(!is_link($path) && !file_exists($path), 'receipt_already_exists');
        $handle = fopen($path, 'xb');
        self::requireValue(is_resource($handle), 'receipt_create_failed');
        try {
            self::requireValue(chmod($path, 0600), 'receipt_mode_failed');
            $bytes = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            self::requireValue(fwrite($handle, $bytes) === strlen($bytes) && fflush($handle), 'receipt_write_failed');
            if (function_exists('fsync')) self::requireValue(fsync($handle), 'receipt_sync_failed');
        } finally { fclose($handle); }
    }

    public static function stage(string $artifact, string $destination, string $expectedDigest): void
    {
        $before = self::inventory($artifact);
        self::requireValue(hash_equals($expectedDigest, self::digest($before)), 'artifact_digest_mismatch');
        self::requireValue(!file_exists($destination) && !is_link($destination), 'candidate_exists');
        self::requireValue(mkdir($destination, 0700), 'candidate_create_failed');
        foreach ($before as $name => $entry) {
            $target = $destination . '/' . $name;
            if (!is_dir(dirname($target))) self::requireValue(mkdir(dirname($target), 0700, true), 'candidate_directory_failed');
            self::requireValue(copy($artifact . '/' . $name, $target) && chmod($target, 0600), 'candidate_copy_failed');
        }
        self::requireValue(self::inventory($artifact) === $before && self::inventory($destination) === $before, 'candidate_readback_failed');
    }

    /** Native read-only UAPI. Never accepts a caller-supplied executable or credentials. */
    public static function uapi(string $module, string $function, array $arguments = []): array
    {
        $argv = ['/usr/local/cpanel/bin/uapi', '--output=json', '--user=archite4', $module, $function];
        foreach ($arguments as $key => $value) $argv[] = $key . '=' . $value;
        $response = json_decode(self::command($argv, self::REPO), true, 64, JSON_THROW_ON_ERROR);
        self::requireValue(is_array($response) && ($response['result']['status'] ?? null) === 1 && empty($response['result']['errors']), 'native_uapi_failed');
        self::requireValue(is_array($response['result']['data'] ?? null), 'native_uapi_inventory_missing');
        return $response['result']['data'];
    }

    public static function exclusiveGrantUser(array $databases, string $user): void
    {
        $matches = array_values(array_filter($databases, static fn($row) => is_array($row) && ($row['database'] ?? null) === 'archite4_dev_rel1'));
        self::requireValue(count($matches) === 1, 'source_inventory_not_unique');
        // Unknown provider shapes are not absence of grants. Native preflight must establish this shape.
        $users = $matches[0]['users'] ?? null;
        self::requireValue(is_array($users) && array_is_list($users) && $users === [$user], 'exclusive_database_grant_inventory_unproven');
    }

    public static function activeSql(string $root): array
    {
        self::requireValue(realpath($root) === $root && !is_link($root), 'active_sql_root_invalid');
        $files = [];
        foreach (new DirectoryIterator($root) as $entry) {
            if ($entry->isDot()) continue;
            self::requireValue(!$entry->isLink() && $entry->isFile() && preg_match('/\\A[0-9]{4}_[a-z0-9_]+\\.sql\\z/', $entry->getFilename()) === 1, 'active_sql_entry_invalid');
            $files[$entry->getFilename()] = hash_file('sha256', $entry->getPathname());
        }
        ksort($files); self::requireValue(count($files) > 0, 'active_sql_empty');
        return $files;
    }

    /** Loads only installed, operator-owned config in RAM; never incoming artifact code. */
    public static function preflight(array &$evidence = []): array
    {
        $evidence = ['zipAvailable'=>class_exists('ZipArchive'), 'curlAvailable'=>function_exists('curl_init'),
            'nativeRestoreCapabilityPresent'=>is_file(self::REPO . '/app/src/Infrastructure/Backup/AccountOwnedRestoreTarget.php'),
            'activeRootAvailable'=>realpath(self::ACTIVE) === self::ACTIVE,
            'documentRootAvailable'=>realpath(self::DOCROOT) === self::DOCROOT,
            'runtimeConfigAvailable'=>is_file(self::ACTIVE . '/config/runtime.php'),
            'liveDdlAuthorized'=>false];
        $databases = self::uapi('Mysql', 'list_databases');
        $evidence['mysqlInventoryAvailable'] = true;
        self::uapi('Mysql', 'list_users');
        $evidence['mysqlUserInventoryAvailable'] = true;
        $cron = [];
        $cronAvailable = false;
        try {
            $cronBytes = self::command(['/usr/bin/crontab', '-l'], self::REPO);
            $cron = array_values(array_filter(explode("\n", $cronBytes), static fn($line) => trim($line) !== '' && !str_starts_with(ltrim($line), '#')));
            $cronAvailable = true;
        } catch (Throwable) {
            // Unavailable inventory is not proof of an empty scheduler.
        }
        $evidence['cronInventoryAvailable'] = $cronAvailable;
        if ($cronAvailable) $evidence['cronEntryCount'] = count($cron);
        self::requireValue(realpath(self::ACTIVE) === self::ACTIVE && realpath(self::DOCROOT) === self::DOCROOT, 'active_or_document_root_invalid');
        $configPath = self::ACTIVE . '/config/runtime.php';
        self::requireValue(is_file($configPath) && !is_link($configPath) && fileowner($configPath) === posix_geteuid() && (fileperms($configPath) & 0077) === 0, 'active_config_missing_or_not_private');
        $config = require $configPath;
        self::requireValue(is_array($config) && ($config['DB_HOST'] ?? null) === '127.0.0.1' && ($config['DB_NAME'] ?? null) === 'archite4_dev_rel1' && preg_match('/\\Aarchite4_[A-Za-z0-9_]+\\z/', $config['DB_USER'] ?? '') === 1, 'active_database_config_identity_mismatch');
        self::requireValue(is_string($config['DB_PASSWORD'] ?? null), 'active_database_password_missing');
        $pdo = new PDO('mysql:host=127.0.0.1;dbname=archite4_dev_rel1;charset=utf8mb4', $config['DB_USER'], $config['DB_PASSWORD'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
        self::requireValue($pdo->query('SELECT DATABASE()')->fetchColumn() === 'archite4_dev_rel1' && explode('@', (string)$pdo->query('SELECT CURRENT_USER()')->fetchColumn(), 2)[0] === $config['DB_USER'], 'actual_source_pdo_identity_mismatch');
        $exclusive = true;
        try { self::exclusiveGrantUser($databases, $config['DB_USER']); } catch (RuntimeException) { $exclusive = false; }
        $evidence['exclusiveGrantUserVisible'] = $exclusive;
        if ($exclusive) {
            $own = (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
            $other = 0;
            foreach ($pdo->query('SHOW FULL PROCESSLIST')->fetchAll(PDO::FETCH_ASSOC) as $connection) {
                if (($connection['db'] ?? null) === 'archite4_dev_rel1' && (int)$connection['Id'] !== $own) $other++;
            }
            $evidence['otherSourceConnectionCount'] = $other;
            $evidence['sourceConnectionsDrainedAtInspection'] = $other === 0;
            // An observation is not an exclusion lease; do not use it to authorize DDL.
        }
        $sql = self::activeSql(self::ACTIVE . '/database/migrations');
        $storage = realpath($config['FILE_STORAGE_PATH'] ?? '');
        self::requireValue(is_string($storage) && !str_starts_with($storage . '/', '/home/archite4/public_html/') && !str_starts_with(self::PRIVATE_ROOT . '/', $storage . '/'), 'backup_storage_not_private_or_archive_inside_storage');
        return ['pdo'=>$pdo, 'config'=>$config, 'sql'=>$sql, 'cron'=>$cron, 'report'=>[
            'sourceDatabase'=>'archite4_dev_rel1', 'runtimeConfigSha256'=>hash_file('sha256', $configPath),
            'activeSqlTreeSha256'=>hash('sha256', json_encode($sql, JSON_THROW_ON_ERROR)),
            'exclusiveGrantUserVisible'=>$exclusive, 'cronInventoryAvailable'=>$cronAvailable,
            'cronEntryCount'=>$cronAvailable ? count($cron) : null, 'nativeRestoreCapabilityPresent'=>is_file(self::REPO . '/app/src/Infrastructure/Backup/AccountOwnedRestoreTarget.php'),
            'zipAvailable'=>class_exists('ZipArchive'), 'curlAvailable'=>function_exists('curl_init'),
            'liveDdlAuthorized'=>false, 'blockingGate'=>'unknown_writer_dispatch_exclusion_and_verified_promotion_not_implemented'
        ]];
    }

    public static function databaseSnapshot(PDO $pdo): array
    {
        $tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE' ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
        $snapshot = [];
        foreach ($tables as $table) {
            self::requireValue(is_string($table) && preg_match('/\\A[a-z][a-z0-9_]*\\z/', $table) === 1, 'snapshot_table_invalid');
            $create = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM);
            $rows = [];
            $query = $pdo->query('SELECT * FROM `' . $table . '`');
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                ksort($row); $rows[] = hash('sha256', serialize($row));
            }
            sort($rows, SORT_STRING);
            $snapshot[$table] = ['schema'=>hash('sha256', preg_replace('/ AUTO_INCREMENT=[0-9]+/', '', $create[1])), 'rows'=>count($rows), 'rowHash'=>hash('sha256', implode('', $rows))];
        }
        return $snapshot;
    }

    /** Real recovery rehearsal primitive. Caller must establish writer exclusion first. */
    public static function recovery(PDO $source, array $config, string $runRoot): array
    {
        self::requireValue(realpath(dirname($runRoot)) === self::PRIVATE_ROOT && !file_exists($runRoot), 'recovery_root_invalid');
        self::requireValue(mkdir($runRoot, 0700), 'recovery_root_create_failed');
        $password = bin2hex(random_bytes(32)); $hmac = base64_encode(random_bytes(32));
        self::journal($runRoot . '/recovery-secret.json', ['password'=>$password, 'hmac'=>$hmac]);
        $sqlBefore = self::activeSql(self::ACTIVE . '/database/migrations');
        // Reviewed repository loader, not incoming vendor. Restore uses byte-identical active SQL.
        spl_autoload_register(static function(string $class): void {
            if (str_starts_with($class, 'Architex\\')) {
                $path = self::REPO . '/app/src/' . str_replace('\\', '/', substr($class, 9)) . '.php';
                if (is_file($path)) require_once $path;
            }
        }, true, true);
        $sourceSnapshot = self::databaseSnapshot($source);
        $backup = (new \Architex\Infrastructure\Backup\BackupService($source, $config['FILE_STORAGE_PATH'], $password, $hmac))->create($runRoot . '/source.axbackup');
        self::journal($runRoot . '/backup.json', ['archiveSha256'=>hash_file('sha256', $backup->path), 'backupId'=>$backup->backupId]);
        $target = \Architex\Infrastructure\Backup\AccountOwnedRestoreTarget::provision($source, '127.0.0.1', $config['DB_USER']);
        self::journal($runRoot . '/target.json', ['database'=>$target->database(), 'source'=>'archite4_dev_rel1']);
        $pdo = new PDO('mysql:host=127.0.0.1;dbname=' . $target->database() . ';charset=utf8mb4', $config['DB_USER'], $config['DB_PASSWORD'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
        $restored = (new \Architex\Infrastructure\Backup\RestoreService(self::ACTIVE . '/database/migrations', $password, $hmac))->restoreIntoProvisionedEmptyDatabase($backup->path, $pdo, $target, $runRoot . '/restored-files');
        self::requireValue(self::activeSql(self::ACTIVE . '/database/migrations') === $sqlBefore && $restored->backupId === $backup->backupId && $restored->rowCount === $backup->rowCount && $restored->fileCount === $backup->fileCount, 'recovery_readback_mismatch');
        self::requireValue(self::databaseSnapshot($source) === $sourceSnapshot && self::databaseSnapshot($pdo) === $sourceSnapshot, 'restored_schema_or_rows_readback_mismatch');
        $restoredStorage = new \Architex\Platform\Files\LocalFileStorage($runRoot . '/restored-files');
        foreach ($pdo->query("SELECT LOWER(HEX(organisation_id)) organisation_id,storage_name,sha256,byte_size FROM stored_files WHERE status='active'")->fetchAll(PDO::FETCH_ASSOC) as $file) {
            $path = $restoredStorage->path($file['organisation_id'], $file['storage_name']);
            self::requireValue(is_file($path) && hash_file('sha256', $path) === $file['sha256'] && filesize($path) === (int)$file['byte_size'], 'restored_file_readback_mismatch');
        }
        $report = ['archiveSha256'=>hash_file('sha256', $backup->path), 'backupId'=>$backup->backupId, 'target'=>$target->database(), 'rows'=>$restored->rowCount, 'files'=>$restored->fileCount, 'retained'=>true];
        self::journal($runRoot . '/restore.json', $report);
        return $report;
    }

    public static function run(): void
    {
        self::requireValue(PHP_SAPI === 'cli' && PHP_OS_FAMILY === 'Linux' && PHP_VERSION_ID >= 80300, 'linux_php83_cli_required');
        self::requireValue(realpath(dirname(__DIR__)) === self::REPO, 'repository_root_mismatch');
        self::requireValue(function_exists('posix_geteuid') && posix_getpwuid(posix_geteuid())['name'] === 'archite4', 'account_mismatch');
        umask(0077);
        $root = self::PRIVATE_ROOT;
        self::requireValue(realpath($root) === $root && (fileperms($root) & 0077) === 0 && fileowner($root) === posix_geteuid(), 'private_root_not_owned_0700');
        $planPath = $root . '/operator-plan.json';
        self::requireValue(is_file($planPath) && !is_link($planPath) && (fileperms($planPath) & 0077) === 0 && fileowner($planPath) === posix_geteuid(), 'private_plan_not_owned_0600');
        $lockPath = $root . '/deploy.lock';
        self::requireValue(!is_link($lockPath), 'lock_symlink');
        $lock = fopen($lockPath, 'c+b');
        self::requireValue(is_resource($lock) && flock($lock, LOCK_EX | LOCK_NB), 'deployment_busy');
        try {
            $plan = json_decode((string)file_get_contents($planPath), true, 32, JSON_THROW_ON_ERROR);
            self::requireValue(in_array($plan['mode'] ?? null, ['stage-only', 'preflight', 'deploy'], true) && ($plan['account'] ?? null) === 'archite4' && ($plan['repository'] ?? null) === self::REPO && ($plan['activeRoot'] ?? null) === self::ACTIVE && ($plan['documentRoot'] ?? null) === self::DOCROOT, 'plan_target_mismatch');
            $requested = $_SERVER['argv'][1] ?? '--preflight';
            self::requireValue(preg_match('/\A[a-f0-9]{40}\z/', $plan['expectedCommit'] ?? '') === 1, 'plan_commit_invalid');
            if ($requested !== '--preflight') {
                self::requireValue(preg_match('/\A[a-f0-9]{64}\z/', $plan['artifactTreeSha256'] ?? '') === 1, 'plan_digest_invalid');
            }
            $commit = self::command(['/usr/local/cpanel/3rdparty/bin/git', 'rev-parse', '--verify', 'HEAD'], self::REPO);
            self::requireValue(hash_equals($plan['expectedCommit'], $commit), 'commit_mismatch');
            self::requireValue(self::command(['/usr/local/cpanel/3rdparty/bin/git', 'status', '--porcelain', '--untracked-files=all'], self::REPO) === '', 'repository_dirty');
            self::requireValue(self::command(['/usr/local/cpanel/3rdparty/bin/git', 'rev-parse', '--abbrev-ref', 'HEAD'], self::REPO) === 'master', 'branch_mismatch');
            $requested = $_SERVER['argv'][1] ?? '--preflight';
            self::requireValue(in_array($requested, ['--preflight', '--deploy', '--stage-only'], true), 'invocation_mode_invalid');
            if ($requested === '--preflight') $plan['mode'] = 'preflight';
            else self::requireValue($plan['mode'] === substr($requested, 2), 'explicit_plan_mode_required');
            if ($plan['mode'] !== 'stage-only') {
                $report = ['format'=>'a777-cpanel-native-preflight-v1', 'mode'=>$plan['mode'], 'commit'=>$commit,
                    'planSha256'=>hash_file('sha256', $planPath), 'databaseDdlTouched'=>false, 'liveFilesTouched'=>false];
                $evidence = [];
                try {
                    $observed = self::preflight($evidence);
                    $report += $observed['report'];
                    $report['status'] = 'inspected-not-authorized';
                } catch (Throwable $error) {
                    $report['status'] = 'capability-inspection-refused';
                    // Do not persist PDO/provider error strings or config values.
                    $report['blockingGate'] = preg_match('/\\A[a-z][a-z0-9_]+\\z/', $error->getMessage()) === 1 ? $error->getMessage() : 'native_preflight_failed';
                }
                $report += $evidence;
                self::journal($root . '/preflight-' . $commit . '-' . bin2hex(random_bytes(8)) . '.json', $report);
                if ($plan['mode'] === 'preflight' && $report['status'] === 'inspected-not-authorized') {
                    print "native preflight inspected; no DDL or live writes; private receipt retained\n";
                    return;
                }
                // Exclusive grant visibility alone cannot exclude another same-user dispatcher.
                // No caller-supplied boolean can substitute for this missing enforcement boundary.
                throw new RuntimeException($report['blockingGate']);
            }
            $artifact = $root . '/incoming/' . $commit;
            self::requireValue(realpath($artifact) === $artifact, 'artifact_path_mismatch');
            $metadata = json_decode((string)file_get_contents($artifact . '/release.json'), true, 32, JSON_THROW_ON_ERROR);
            self::requireValue(($metadata['sourceCommit'] ?? null) === $commit && ($metadata['sourceDirty'] ?? null) === false && ($metadata['environment'] ?? null) === 'production', 'locked_production_artifact_required');
            // Use reviewed repository verifier, not incoming vendor/bootstrap code.
            require_once self::REPO . '/app/src/Infrastructure/Deployment/ReleaseVerificationReport.php';
            require_once self::REPO . '/app/src/Infrastructure/Deployment/ReleaseVerifier.php';
            $verifier = new \Architex\Infrastructure\Deployment\ReleaseVerifier();
            self::requireValue($verifier->verify($artifact)->valid, 'release_verification_failed');
            $candidate = $root . '/candidate-' . $commit;
            self::journal($root . '/intent-' . $commit . '.json', ['format'=>'a777-cpanel-stage-intent-v1', 'planSha256'=>hash_file('sha256', $planPath), 'commit'=>$commit, 'artifactTreeSha256'=>$plan['artifactTreeSha256'], 'candidate'=>$candidate]);
            self::stage($artifact, $candidate, $plan['artifactTreeSha256']);
            self::requireValue($verifier->verify($candidate)->valid, 'candidate_verification_failed');
            self::journal($root . '/receipt-' . $commit . '.json', ['format'=>'a777-cpanel-stage-receipt-v1', 'status'=>'staged-not-promoted', 'commit'=>$commit, 'candidate'=>$candidate, 'artifactTreeSha256'=>$plan['artifactTreeSha256'], 'planSha256'=>hash_file('sha256', $planPath), 'databaseTouched'=>false, 'liveFilesTouched'=>false, 'migrationGate'=>'blocked: unknown writer dispatch exclusion and verified atomic promotion are not implemented; AccountOwnedRestoreTarget recovery capability is available but native UAPI remains unverified', 'remainingGates'=>['owned_restore_namespace_compatibility','native_uapi_capability_and_grant_readback','apache_boundary_readback','exact_owned_cron_journal_and_readback','old_bootstrap_hook_drain','independently_verified_database_writer_users','encrypted_backup_and_matching_active_tree_restore','candidate_eight_migrations','readiness_and_atomic_promotion']]);
            print "staged-not-promoted; private receipt retained; live runtime and database untouched\n";
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try { CpanelGitDeploy::run(); }
    catch (Throwable $error) { fwrite(STDERR, "cPanel deployment refused; no automatic promotion or migration.\n"); exit(1); }
}
