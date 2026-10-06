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

    private static array $commandEvidence = [];

    public static function commandDiagnostics(): array { return self::$commandEvidence; }

    public static function stderrClass(string $bytes): string
    {
        // Only constant categories leave this process; raw output is never persisted.
        foreach (['permission_denied'=>'permission denied', 'not_found'=>'no such file or directory',
            'unknown_option'=>'unknown option', 'access_denied'=>'access denied',
            'command_not_found'=>'command not found'] as $class => $needle) {
            if (str_contains(strtolower($bytes), $needle)) return $class;
        }
        return $bytes === '' ? 'empty' : 'unclassified';
    }

    public static function command(array $argv, string $cwd, bool $raw = false): string
    {
        self::$commandEvidence = ['status'=>'unavailable', 'exitCode'=>null, 'stderrClass'=>'empty'];
        // File-backed pipes avoid stdout/stderr deadlock and never print command bytes.
        $out = tmpfile(); $err = tmpfile();
        self::requireValue(is_resource($out) && is_resource($err), 'command_unavailable');
        try {
            $process = @proc_open($argv, [['pipe','r'], $out, $err], $pipes, $cwd);
            self::requireValue(is_resource($process), 'command_unavailable');
            fclose($pipes[0]);
            $exit = proc_close($process);
            rewind($out); rewind($err);
            $output = stream_get_contents($out, 1048577);
            $stderr = stream_get_contents($err, 16384);
            self::$commandEvidence = ['status'=>$exit === 0 ? 'success' : 'failed', 'exitCode'=>$exit,
                'stderrClass'=>self::stderrClass(is_string($stderr) ? $stderr : '')];
            self::requireValue($exit === 0 && is_string($output), 'command_failed');
            self::requireValue(strlen($output) <= 1048576, 'command_output_too_large');
            return $raw ? $output : trim($output);
        } finally { fclose($out); fclose($err); }
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

    public static function inspectInventory(string $function, bool $apiAllowed, array &$evidence): array
    {
        $key = $function === 'list_databases' ? 'mysqlDatabaseTransport' : 'mysqlUserTransport';
        $evidence[$key] = 'native-cli';
        try {
            $identity = self::uapi('Variables', 'get_user_information', ['name'=>'home']);
            self::requireValue(($identity['home'] ?? null) === '/home/archite4', 'api_account_home_mismatch');
            $data = self::uapi('Mysql', $function);
            $evidence['apiAccountHomeValidated'] = true;
            return $data;
        }
        catch (Throwable $error) {
            $evidence[$key . 'Cli'] = self::commandDiagnostics();
            $evidence[$key . 'Cli']['absoluteExecutablePresent'] = is_file('/usr/local/cpanel/bin/uapi');
            $evidence[$key . 'Cli']['absoluteExecutableUsable'] = is_executable('/usr/local/cpanel/bin/uapi');
            $evidence[$key . 'Cli']['pathExecutableUsable'] = false;
            foreach (explode(PATH_SEPARATOR, (string)getenv('PATH')) as $path) {
                if ($path !== '' && is_file($path . '/uapi') && is_executable($path . '/uapi')) {
                    $evidence[$key . 'Cli']['pathExecutableUsable'] = true;
                }
            }
            if (!$apiAllowed) throw $error;
            require_once __DIR__ . '/CpanelReadOnlyApi.php';
            $evidence[$key] = 'authenticated-https-fallback-attempted';
            $data = CpanelReadOnlyApi::inventory('Mysql', $function);
            $evidence[$key] = 'authenticated-https';
            $evidence['apiAccountHomeValidated'] = true;
            return $data;
        }
    }

    /** Reviewed application parser; no environment overrides or runtime config rewrites. */
    public static function databaseConfig(array $config, array $users, array $databases, array &$evidence, string $expectedSha = '', string $actualSha = ''): \Architex\Infrastructure\Config\AppConfig
    {
        $evidence += ['databaseConfigParsed'=>false, 'databaseHostLocal'=>false,
            'databaseHostClass'=>'refused', 'databaseNameMatches'=>false,
            'databaseUserInventoryMatches'=>false, 'databaseInventoryMatches'=>false,
            'pdoDatabaseMatches'=>false, 'pdoUserMatches'=>false];
        try { $typed = \Architex\Infrastructure\Config\AppConfig::fromEnvironment($config); }
        catch (Throwable) { throw new RuntimeException('active_database_config_identity_mismatch'); }
        $evidence['databaseConfigParsed'] = true;
        $evidence['databaseHostClass'] = match ($typed->dbHost) {
            '127.0.0.1', '::1' => 'tcp-loopback', 'localhost' => 'local-socket', default => 'refused'
        };
        $evidence['databaseHostLocal'] = $evidence['databaseHostClass'] !== 'refused';
        $evidence['databaseNameMatches'] = $typed->dbName === 'archite4_dev_rel1';

        // Official Mysql/list_users result.data is a list of {user,shortuser,databases}.
        // Account ownership comes from the authenticated account's home-validated inventory,
        // not a guessed username prefix. Require its exact database assignment too.
        $matches = [];
        if (array_is_list($users)) foreach ($users as $row) {
            if (is_array($row) && ($row['user'] ?? null) === $typed->dbUser) $matches[] = $row;
        }
        $assigned = count($matches) === 1 ? ($matches[0]['databases'] ?? null) : null;
        $evidence['databaseUserInventoryMatches'] = is_array($assigned) && array_is_list($assigned)
            && in_array($typed->dbName, $assigned, true);
        $dbMatches = array_values(array_filter($databases, static fn($row) => is_array($row) && ($row['database'] ?? null) === $typed->dbName));
        $evidence['databaseInventoryMatches'] = array_is_list($databases) && count($dbMatches) === 1;
        $evidence['sourceConfigPinned'] = false;
        if ($expectedSha !== '') {
            self::requireValue(preg_match('/\A[a-f0-9]{64}\z/', $expectedSha) === 1 && hash_equals($expectedSha, $actualSha), 'runtime_config_pin_mismatch');
            $evidence['sourceConfigPinned'] = true;
        }
        self::requireValue($evidence['databaseNameMatches'] && $evidence['databaseUserInventoryMatches'] && $evidence['databaseInventoryMatches'], 'active_database_config_identity_mismatch');
        if (!$evidence['databaseHostLocal']) {
            self::requireValue($evidence['sourceConfigPinned'] && ($evidence['apiAccountHomeValidated'] ?? false) === true, 'active_database_config_identity_mismatch');
            $evidence['databaseHostClass'] = 'configured-managed';
        }
        return $typed;
    }

    public static function pdoIdentity(PDO $pdo, \Architex\Infrastructure\Config\AppConfig $config, array &$evidence): void
    {
        $evidence['pdoDatabaseMatches'] = $pdo->query('SELECT DATABASE()')->fetchColumn() === $config->dbName;
        $evidence['pdoUserMatches'] = explode('@', (string)$pdo->query('SELECT CURRENT_USER()')->fetchColumn(), 2)[0] === $config->dbUser;
        self::requireValue($evidence['pdoDatabaseMatches'] && $evidence['pdoUserMatches'], 'actual_source_pdo_identity_mismatch');
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

    /** Metadata-only by default. Hashing is local; no config bytes are returned or logged. */
    public static function runtimePermissions(string $path, string $root, string $intent, bool $authorized, array &$evidence, string $commit = '', string $planSha = ''): void
    {
        clearstatcache(true, $path);
        $s = @lstat($path);
        $uid = posix_geteuid();
        $regular = is_array($s) && ($s['mode'] & 0170000) === 0100000;
        $evidence += ['runtimeConfigRegular'=>$regular, 'runtimeConfigNotSymlink'=>!is_link($path),
            'runtimeConfigOwnerMatches'=>is_array($s) && $s['uid'] === $uid,
            'runtimeConfigPrivate600'=>is_array($s) && ($s['mode'] & 07777) === 0600,
            'privateConfigPermissionsChanged'=>false, 'runtimeConfigPermissionsTouched'=>false];
        if (is_array($s)) $evidence['runtimeConfigModeBefore'] = sprintf('%04o', $s['mode'] & 07777);
        $parentPath = dirname($path); $parent = @lstat($parentPath);
        $evidence['runtimeConfigParentModeBefore'] = is_array($parent) ? sprintf('%04o', $parent['mode'] & 07777) : '0000';
        $evidence['runtimeConfigParentOwnerMatches'] = is_array($parent) && $parent['uid'] === $uid;
        $evidence['runtimeConfigCanonical'] = $path === $root . '/config/runtime.php' && realpath($path) === $path;
        $evidence['runtimeConfigAncestorSafe'] = false;
        $evidence['runtimeConfigParentPermissionsTouched'] = false;
        $evidence['privateConfigParentPermissionsChanged'] = false;
        if (!$authorized) return;
        self::requireValue($regular && !is_link($path) && $s['uid'] === $uid && $s['nlink'] === 1, 'runtime_permission_identity_refused');
        self::requireValue($evidence['runtimeConfigCanonical'], 'runtime_permission_canonical_refused');
        for ($dir = dirname($path); ; $dir = dirname($dir)) {
            $d = @lstat($dir);
            self::requireValue(is_array($d) && !is_link($dir) && realpath($dir) === $dir && ($d['mode'] & 0170000) === 0040000, 'runtime_permission_ancestor_path_refused');
            self::requireValue(($d['mode'] & 0022) === 0, 'runtime_permission_ancestor_write_refused');
            self::requireValue(in_array($d['uid'], [0, $uid], true), 'runtime_permission_ancestor_owner_refused');
            if ($dir === '/') break;
        }
        $evidence['runtimeConfigAncestorSafe'] = true;
        self::requireValue($evidence['runtimeConfigParentOwnerMatches'], 'runtime_permission_parent_owner_refused');
        self::requireValue(($parent['mode'] & 07000) === 0, 'runtime_permission_parent_mode_refused');
        if ($evidence['runtimeConfigPrivate600'] && ($parent['mode'] & 07777) === 0700) return;
        self::requireValue(preg_match('/\\A[a-f0-9]{40}\\z/', $commit) === 1
            && preg_match('/\\A[a-f0-9]{64}\\z/', $planSha) === 1
            && preg_match('/\\Aruntime-permission-[a-f0-9]{16}\\.json\\z/', basename($intent)) === 1, 'runtime_permission_identity_refused');
        $repairParent = ($parent['mode'] & 07777) !== 0700;
        $h = fopen($path, 'rb');
        self::requireValue(is_resource($h), 'runtime_permission_identity_refused');
        try {
            self::requireValue(fstat($h) === $s, 'runtime_permission_identity_refused');
            $hash = hash_init('sha256'); hash_update_stream($hash, $h); $before = hash_final($hash);
            self::journal($intent, ['format'=>'a777-runtime-permission-intent-v1', 'operation'=>'private0600',
                'commit'=>$commit, 'planSha256'=>$planSha, 'path'=>$path, 'device'=>$s['dev'], 'inode'=>$s['ino'], 'owner'=>$uid, 'bytes'=>$s['size'],
                'beforeMode'=>sprintf('%04o', $s['mode'] & 07777), 'sha256'=>$before,
                'parentPath'=>$parentPath, 'parentDevice'=>$parent['dev'], 'parentInode'=>$parent['ino'], 'parentOwner'=>$uid,
                'parentBeforeMode'=>sprintf('%04o', $parent['mode'] & 07777), 'parentAfterMode'=>'0700', 'parentRepair'=>$repairParent]);
            $evidence['runtimePermissionIntent'] = basename($intent);
            $evidence['runtimePermissionDevice'] = $s['dev']; $evidence['runtimePermissionInode'] = $s['ino']; $evidence['runtimePermissionOwner'] = $uid;
            $evidence['runtimePermissionParentDevice'] = $parent['dev']; $evidence['runtimePermissionParentInode'] = $parent['ino']; $evidence['runtimePermissionParentOwner'] = $uid;
            clearstatcache(true, $parentPath);
            self::requireValue(@lstat($parentPath) === $parent && realpath($parentPath) === $parentPath && !is_link($parentPath), 'runtime_permission_parent_identity_refused');
            clearstatcache(true, $path);
            $current = @lstat($path);
            self::requireValue(is_array($current) && array_intersect_key($current, array_flip(['dev','ino','uid','mode','size','nlink'])) === array_intersect_key($s, array_flip(['dev','ino','uid','mode','size','nlink'])) && realpath($path) === $path && !is_link($path), 'runtime_permission_identity_refused');
            if ($repairParent) {
                $evidence['runtimeConfigParentPermissionsTouched'] = true;
                self::requireValue(chmod($parentPath, 0700), 'runtime_permission_parent_chmod_failed');
            }
            clearstatcache(true, $parentPath); $p = @lstat($parentPath);
            self::requireValue(is_array($p) && $p['dev'] === $parent['dev'] && $p['ino'] === $parent['ino'] && $p['uid'] === $uid
                && ($p['mode'] & 07777) === 0700 && realpath($parentPath) === $parentPath && !is_link($parentPath), 'runtime_permission_parent_readback_failed');
            $evidence['privateConfigParentPermissionsChanged'] = $repairParent;
            $evidence['runtimeConfigParentModeAfter'] = '0700';
            $evidence['runtimePermissionParentDevice'] = $parent['dev'];
            $evidence['runtimePermissionParentInode'] = $parent['ino'];
            $evidence['runtimePermissionParentOwner'] = $uid;
            clearstatcache(true, $path);
            self::requireValue(array_intersect_key(lstat($path), array_flip(['dev','ino','uid','mode','size','nlink'])) === array_intersect_key($s, array_flip(['dev','ino','uid','mode','size','nlink'])) && realpath($path) === $path, 'runtime_permission_identity_refused');
            $evidence['runtimeConfigPermissionsTouched'] = true;
            self::requireValue(chmod($path, 0600), 'runtime_permission_chmod_failed');
            clearstatcache(true, $path); clearstatcache(true, dirname($path)); $after = lstat($path); $opened = fstat($h);
            $parentAfter = lstat(dirname($path));
            self::requireValue($parentAfter['dev'] === $parent['dev'] && $parentAfter['ino'] === $parent['ino']
                && $parentAfter['uid'] === $uid && ($parentAfter['mode'] & 07777) === 0700
                && realpath(dirname($path)) === dirname($path), 'runtime_permission_readback_failed');
            rewind($h); $hash = hash_init('sha256'); hash_update_stream($hash, $h); $afterHash = hash_final($hash);
            self::requireValue($after['dev'] === $s['dev'] && $after['ino'] === $s['ino'] && $after['uid'] === $uid
                && $after['size'] === $s['size'] && $opened['ino'] === $s['ino'] && $opened['dev'] === $s['dev']
                && ($after['mode'] & 0170000) === 0100000 && $after['nlink'] === 1 && $opened['nlink'] === 1
                && ($opened['mode'] & 07777) === 0600 && ($after['mode'] & 07777) === 0600 && $before === $afterHash && hash_file('sha256', $path) === $before
                && realpath($path) === $path && !is_link($path), 'runtime_permission_readback_failed');
            $evidence['runtimePermissionIntent'] = basename($intent);
            $evidence['runtimePermissionDevice'] = $s['dev'];
            $evidence['runtimePermissionInode'] = $s['ino'];
            $evidence['runtimePermissionOwner'] = $uid;
            $evidence['privateConfigPermissionsChanged'] = true;
            $evidence['runtimeConfigPrivate600'] = true;
            $evidence['runtimeConfigModeAfter'] = '0600';
            $evidence['configContentUnchangedSHA'] = $before;
            $evidence['runtimeConfigBytesUnchanged'] = $s['size'];
        } finally { fclose($h); }
    }

    /** Observations only: canonical roots, not HTTP privacy or recoverability proof. */
    public static function storageInspection(string $configured, string $publicRoot, string $archiveRoot): array
    {
        $storage = $configured === '' ? false : realpath($configured);
        $public = realpath($publicRoot);
        $archive = realpath($archiveRoot);
        // Compare canonical path components consistently on Windows fixture hosts too.
        if (is_string($storage)) $storage = str_replace('\\', '/', $storage);
        if (is_string($public)) $public = str_replace('\\', '/', $public);
        if (is_string($archive)) $archive = str_replace('\\', '/', $archive);
        $exists = $configured !== '' && is_dir($configured);
        $canonical = $exists && is_string($storage);
        $outside = $canonical && is_string($public) && !str_starts_with($storage . '/', $public . '/');
        $archiveOutside = $canonical && is_string($archive) && !str_starts_with($archive . '/', $storage . '/');
        $class = !$exists ? 'missing' : (!$canonical || !is_string($public) || !is_string($archive) ? 'canonical-unproven' :
            (!$archiveOutside ? 'archive-inside-storage' : ($outside ? 'outside-public' : 'under-public-privacy-unproven')));
        return ['storageExists'=>$exists, 'storageCanonical'=>$canonical, 'storageOutsidePublic'=>$outside, 'sourceUnderPublicHtml'=>$canonical && is_string($public) && !$outside,
            'archiveOutsideStorage'=>$archiveOutside, 'storageClass'=>$class,
            'migrationAuthorized'=>false, 'backupAuthorized'=>false, 'promotionAuthorized'=>false,
            'liveDdlAuthorized'=>false,
            'blockingGate'=>($outside && $archiveOutside) ? 'unknown_writer_dispatch_exclusion_and_verified_promotion_not_implemented' : 'backup_storage_not_private_or_archive_inside_storage'];
    }

    /** Independently recheck the actual source and archive before any recovery side effect. */
    public static function requireRecoveryStorage(string $storage, string $publicRoot, string $archiveRoot): void
    {
        $observed = self::storageInspection($storage, $publicRoot, $archiveRoot);
        self::requireValue($observed['storageOutsidePublic'] && $observed['archiveOutsideStorage']
            && !is_link($storage) && (fileperms(realpath($storage)) & 0077) === 0
            && fileowner(realpath($storage)) === posix_geteuid()
            && !is_link($archiveRoot) && (fileperms($archiveRoot) & 0077) === 0
            && fileowner($archiveRoot) === posix_geteuid(), 'backup_storage_not_private_or_archive_inside_storage');
    }

    /** Classify SHOW GRANTS without persisting any grant text. Unknown syntax fails closed. */
    public static function scopedOnlyGrants(array $grants, string $database): bool
    {
        if ($grants === []) return false;
        $scope = '`' . $database . '`.*';
        foreach ($grants as $grant) {
            if (!is_string($grant) || str_contains($grant, 'WITH GRANT OPTION')) return false;
            if (preg_match('/\\AGRANT USAGE ON \\*\\.\\* TO /', $grant) === 1) continue;
            if (!str_starts_with($grant, 'GRANT ') || !str_contains($grant, ' ON ' . $scope . ' TO ')) return false;
        }
        return true;
    }

    /** Loads only installed, operator-owned config in RAM; never incoming artifact code. */
    public static function preflight(array &$evidence = [], bool $apiAllowed = false, bool $securePermissions = false, string $commit = '', string $planSha = '', string $expectedSha = ''): array
    {
        $evidence = ['zipAvailable'=>class_exists('ZipArchive'), 'curlAvailable'=>function_exists('curl_init'),
            'nativeRestoreCapabilityPresent'=>is_file(self::REPO . '/app/src/Infrastructure/Backup/AccountOwnedRestoreTarget.php'),
            'activeRootAvailable'=>realpath(self::ACTIVE) === self::ACTIVE,
            'documentRootAvailable'=>realpath(self::DOCROOT) === self::DOCROOT,
            'runtimeConfigAvailable'=>is_file(self::ACTIVE . '/config/runtime.php'),
            'managedRestoreSourceBound'=>false, 'managedRestoreProviderStatus'=>'not-rehearsed',
            'liveDdlAuthorized'=>false];
        $databases = self::inspectInventory('list_databases', $apiAllowed, $evidence);
        $evidence['mysqlInventoryAvailable'] = true;
        $users = self::inspectInventory('list_users', $apiAllowed, $evidence);
        $evidence['mysqlUserInventoryAvailable'] = true;
        $evidence['accountPhpWorkerInventoryAvailable'] = false;
        if (is_readable('/proc') && function_exists('posix_geteuid')) {
            $types = []; $complete = true;
            foreach (glob('/proc/[0-9]*') ?: [] as $process) {
                if (@fileowner($process) !== posix_geteuid()) continue;
                $comm = @file_get_contents($process . '/comm');
                if ($comm === false) { $complete = false; continue; }
                $name = trim($comm);
                $type = str_starts_with($name, 'php-fpm') ? 'php-fpm' : (preg_match('/\Aphp(?:[0-9.]*)?\z/', $name) === 1 ? 'php-cli' : (str_starts_with($name, 'lsphp') ? 'lsphp' : null));
                if ($type !== null) $types[$type] = ($types[$type] ?? 0) + 1;
            }
            ksort($types);
            $evidence['accountPhpWorkerInventoryAvailable'] = $complete;
            $evidence['accountPhpWorkerCount'] = array_sum($types);
            $evidence['accountPhpWorkerTypes'] = $types;
        }
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
        self::runtimePermissions($configPath, self::ACTIVE, self::PRIVATE_ROOT . '/runtime-permission-' . bin2hex(random_bytes(8)) . '.json', $securePermissions, $evidence, $commit, $planSha);
        self::requireValue(is_file($configPath) && !is_link($configPath) && fileowner($configPath) === posix_geteuid() && (fileperms($configPath) & 0077) === 0, 'active_config_missing_or_not_private');
        $configSha = hash_file('sha256', $configPath);
        $config = require $configPath;
        self::requireValue(is_array($config), 'active_database_config_identity_mismatch');
        // Load the reviewed repository's real classes, never candidate vendor/bootstrap.
        require_once self::REPO . '/app/src/Infrastructure/Config/AppConfig.php';
        require_once self::REPO . '/app/src/Infrastructure/Database/ConnectionFactory.php';
        $typed = self::databaseConfig($config, $users, $databases, $evidence, $expectedSha, $configSha);
        self::requireValue(hash_file('sha256', $configPath) === $configSha, 'runtime_config_pin_mismatch');
        $pdo = \Architex\Infrastructure\Database\ConnectionFactory::create($typed);
        self::pdoIdentity($pdo, $typed, $evidence);
        $evidence['managedRestoreSourceBound'] = false;
        $evidence['managedRestoreProviderStatus'] = 'not-rehearsed';
        if ($apiAllowed) {
            // Only reviewed clean-commit repository bytes; never incoming artifact code.
            foreach (['Backup/CpanelRestoreProvisioner', 'Backup/AccountOwnedRestoreTarget', 'Config/AppConfig', 'Database/ConnectionFactory'] as $relativeClass) {
                $relative = 'app/src/Infrastructure/' . $relativeClass . '.php';
                $path = self::REPO . '/' . $relative;
                self::requireValue(!is_link($path) && realpath($path) === $path, 'managed_restore_class_path_mismatch');
                $committed = self::command(['/usr/local/cpanel/3rdparty/bin/git', 'show', 'HEAD:' . $relative], self::REPO, true);
                self::requireValue(hash_equals((string)hash_file('sha256', $path), hash('sha256', $committed)), 'managed_restore_class_commit_mismatch');
                require_once $path;
                $class = 'Architex\\Infrastructure\\' . str_replace('/', '\\', $relativeClass);
                self::requireValue((new ReflectionClass($class))->getFileName() === $path, 'managed_restore_class_loading_mismatch');
            }
            $provider = \Architex\Infrastructure\Backup\CpanelRestoreProvisioner::fromAccountToken();
            \Architex\Infrastructure\Backup\AccountOwnedRestoreTarget::preflightManaged($pdo, $typed, $configSha, $provider);
            self::requireValue(hash_file('sha256', $configPath) === $configSha, 'runtime_config_pin_mismatch');
            $evidence['managedRestoreSourceBound'] = true;
            $evidence['managedRestoreProviderStatus'] = 'authenticated-inventory-read-only';
        }
        $exclusive = true;
        try { self::exclusiveGrantUser($databases, $typed->dbUser); } catch (RuntimeException) { $exclusive = false; }
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
        $evidence += self::storageInspection(is_string($config['FILE_STORAGE_PATH'] ?? null) ? $config['FILE_STORAGE_PATH'] : '', '/home/archite4/public_html', self::PRIVATE_ROOT);
        $evidence['appuserGrantsInspectionAvailable'] = false;
        try {
            $evidence['appuserHasScopedOnly'] = self::scopedOnlyGrants($pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN), $typed->dbName);
            $evidence['appuserGrantsInspectionAvailable'] = true;
        } catch (Throwable) { /* Unknown grants are not scoped-only proof. */ }
        return ['pdo'=>$pdo, 'config'=>$config, 'sql'=>$sql, 'cron'=>$cron, 'report'=>[
            'sourceDatabase'=>'archite4_dev_rel1', 'runtimeConfigSha256'=>hash_file('sha256', $configPath),
            'activeSqlTreeSha256'=>hash('sha256', json_encode($sql, JSON_THROW_ON_ERROR)),
            'exclusiveGrantUserVisible'=>$exclusive, 'cronInventoryAvailable'=>$cronAvailable,
            'cronEntryCount'=>$cronAvailable ? count($cron) : null, 'nativeRestoreCapabilityPresent'=>is_file(self::REPO . '/app/src/Infrastructure/Backup/AccountOwnedRestoreTarget.php'),
            'zipAvailable'=>class_exists('ZipArchive'), 'curlAvailable'=>function_exists('curl_init'),
            'managedRestoreSourceBound'=>$evidence['managedRestoreSourceBound'], 'managedRestoreProviderStatus'=>$evidence['managedRestoreProviderStatus'],
            'liveDdlAuthorized'=>false, 'blockingGate'=>$evidence['blockingGate']
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
        self::requireRecoveryStorage($config['FILE_STORAGE_PATH'] ?? '', '/home/archite4/public_html', self::PRIVATE_ROOT);
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
        self::requireValue(realpath($root) === $root && (fileperms($root) & 07777) === 0700 && fileowner($root) === posix_geteuid(), 'private_root_not_owned_0700');
        $planPath = $root . '/operator-plan.json';
        self::requireValue(is_file($planPath) && !is_link($planPath) && (fileperms($planPath) & 07777) === 0600 && fileowner($planPath) === posix_geteuid(), 'private_plan_not_owned_0600');
        $lockPath = $root . '/deploy.lock';
        self::requireValue(!is_link($lockPath), 'lock_symlink');
        $lock = fopen($lockPath, 'c+b');
        self::requireValue(is_resource($lock) && flock($lock, LOCK_EX | LOCK_NB), 'deployment_busy');
        try {
            $plan = json_decode((string)file_get_contents($planPath), true, 32, JSON_THROW_ON_ERROR);
            self::requireValue(in_array($plan['mode'] ?? null, ['stage-only', 'preflight', 'restore-rehearsal', 'deploy'], true) && ($plan['account'] ?? null) === 'archite4' && ($plan['repository'] ?? null) === self::REPO && ($plan['activeRoot'] ?? null) === self::ACTIVE && ($plan['documentRoot'] ?? null) === self::DOCROOT, 'plan_target_mismatch');
            $requested = $_SERVER['argv'][1] ?? '--preflight';
            self::requireValue(preg_match('/\A[a-f0-9]{40}\z/', $plan['expectedCommit'] ?? '') === 1, 'plan_commit_invalid');
            if ($requested === '--from-plan') {
                self::requireValue(in_array($plan['mode'], ['preflight', 'restore-rehearsal'], true), 'explicit_plan_mode_required');
                $requested = '--' . $plan['mode'];
            }
            if (in_array($requested, ['--stage-only', '--deploy'], true)) {
                self::requireValue(preg_match('/\A[a-f0-9]{64}\z/', $plan['artifactTreeSha256'] ?? '') === 1, 'plan_digest_invalid');
            }
            $commit = self::command(['/usr/local/cpanel/3rdparty/bin/git', 'rev-parse', '--verify', 'HEAD'], self::REPO);
            self::requireValue(hash_equals($plan['expectedCommit'], $commit), 'commit_mismatch');
            self::requireValue(self::command(['/usr/local/cpanel/3rdparty/bin/git', 'status', '--porcelain', '--untracked-files=all'], self::REPO) === '', 'repository_dirty');
            self::requireValue(self::command(['/usr/local/cpanel/3rdparty/bin/git', 'rev-parse', '--abbrev-ref', 'HEAD'], self::REPO) === 'master', 'branch_mismatch');
            $hookBytes = self::command(['/usr/local/cpanel/3rdparty/bin/git', 'show', 'HEAD:tools/cpanel_git_deploy.php'], self::REPO, true);
            self::requireValue((new ReflectionClass(self::class))->getFileName() === __FILE__ && hash_equals(hash('sha256', $hookBytes), (string)hash_file('sha256', __FILE__)), 'managed_restore_class_commit_mismatch');
            self::requireValue(count($_SERVER['argv']) === 2 && in_array($requested, ['--preflight', '--restore-rehearsal', '--deploy', '--stage-only'], true), 'invocation_mode_invalid');
            $apiAllowed = in_array($requested, ['--preflight', '--restore-rehearsal'], true) && $plan['mode'] === substr($requested, 2)
                && ($plan['readOnlyUapiTransport'] ?? null) === 'native-cli-then-private-token-https';
            $expectedSha = $plan['expectedRuntimeConfigSha256'] ?? '';
            $allowExistingPublicSourceForRehearsal = $plan['allowExistingPublicSourceForRehearsal'] ?? false;
            self::requireValue(is_bool($allowExistingPublicSourceForRehearsal) && (!$allowExistingPublicSourceForRehearsal || ($plan['mode'] === 'restore-rehearsal' && preg_match('/\A[a-f0-9]{64}\z/', $expectedSha) === 1)), 'runtime_config_pin_mismatch');
            self::requireValue(is_string($expectedSha) && ($expectedSha === '' || (in_array($requested, ['--preflight', '--restore-rehearsal'], true) && $plan['mode'] === substr($requested, 2) && preg_match('/\A[a-f0-9]{64}\z/', $expectedSha) === 1)), 'runtime_config_pin_mismatch');
            self::requireValue($plan['mode'] === substr($requested, 2), 'explicit_plan_mode_required');
            if ($plan['mode'] === 'restore-rehearsal') {
                self::journal($root . '/rehearsal-intent-' . hash_file('sha256', $planPath) . '.json', ['format'=>'a777-rehearsal-intent-v1','commit'=>$commit,'planSha256'=>hash_file('sha256', $planPath),'sourceDatabaseDdlTouched'=>false,'automaticResume'=>false]);
            }
            if ($plan['mode'] === 'restore-rehearsal') self::requireValue($apiAllowed && preg_match('/\A[a-f0-9]{64}\z/', $expectedSha) === 1, 'runtime_config_pin_mismatch');
            if ($plan['mode'] !== 'stage-only') {
                $report = ['format'=>'a777-cpanel-native-preflight-v1', 'mode'=>$plan['mode'], 'commit'=>$commit,
                    'planSha256'=>hash_file('sha256', $planPath), 'databaseDdlTouched'=>false, 'liveFilesTouched'=>false, 'sourceDatabaseDdlTouched'=>false, 'targetDatabaseDdlTouched'=>false, 'isolatedRehearsalPassed'=>false, 'apacheConfigurationTouched'=>false, 'cronConfigurationTouched'=>false];
                if ($plan['mode'] === 'restore-rehearsal') $report['format'] = 'a777-cpanel-restore-rehearsal-v1';
                $evidence = [];
                try {
                    $observed = self::preflight($evidence, $apiAllowed, in_array($requested, ['--preflight', '--restore-rehearsal'], true) && ($plan['secureRuntimeConfigPermissions'] ?? false) === true, $commit, $report['planSha256'], $expectedSha);
                    $report += $observed['report'];
                    $report['status'] = 'inspected-not-authorized';
                    if ($plan['mode'] === 'restore-rehearsal') {
                        spl_autoload_register(static function(string $class): void {
                            if (str_starts_with($class, 'Architex\\')) {
                                $path = self::REPO . '/app/src/' . str_replace('\\', '/', substr($class, 9)) . '.php';
                                if (is_file($path)) require_once $path;
                            }
                        }, true, true);
                        require_once __DIR__ . '/CpanelRestoreRehearsal.php';
                        $outcome = \Architex\Deployment\CpanelRestoreRehearsal::run(\Architex\Infrastructure\Config\AppConfig::fromEnvironment($observed['config']), $observed['pdo'], $observed['config']['FILE_STORAGE_PATH'], $expectedSha, $commit, $plan['mode'], self::REPO, $allowExistingPublicSourceForRehearsal);
                        $report = array_replace($report, $outcome);
                        $report['format'] = 'a777-cpanel-restore-rehearsal-v1';
                        $report['databaseDdlTouched'] = $outcome['targetDatabaseDdlTouched'];
                    }
                } catch (Throwable $error) {
                    $report['status'] = 'capability-inspection-refused';
                    // Do not persist PDO/provider error strings or config values.
                    $safeGates = ['managed_restore_class_path_mismatch','managed_restore_class_commit_mismatch','managed_restore_class_loading_mismatch','runtime_config_pin_mismatch','command_unavailable','command_failed','command_output_too_large','native_uapi_failed','native_uapi_inventory_missing',
                        'api_linux_identity_required','api_operator_identity_mismatch','api_token_path_invalid','api_token_root_not_private',
                        'api_token_missing_or_path_invalid','api_token_unreadable','api_token_not_private','api_token_invalid',
                        'api_http_failed','api_response_too_large','api_response_invalid','api_uapi_failed','api_readonly_function_required',
                        'api_curl_unavailable','api_transport_failed','api_account_home_mismatch','active_or_document_root_invalid',
                        'runtime_permission_identity_refused','runtime_permission_ancestry_refused','runtime_permission_canonical_refused','runtime_permission_ancestor_path_refused','runtime_permission_ancestor_write_refused','runtime_permission_ancestor_owner_refused','runtime_permission_parent_owner_refused','runtime_permission_parent_mode_refused','runtime_permission_parent_identity_refused','runtime_permission_parent_chmod_failed','runtime_permission_parent_readback_failed','runtime_permission_chmod_failed','runtime_permission_readback_failed','active_config_missing_or_not_private','active_database_config_identity_mismatch','active_database_password_missing',
                        'actual_source_pdo_identity_mismatch','active_sql_root_invalid','active_sql_entry_invalid','active_sql_empty',
                        'backup_storage_not_private_or_archive_inside_storage'];
                    $report['blockingGate'] = in_array($error->getMessage(), $safeGates, true) ? $error->getMessage() : 'native_preflight_failed';
                }
                $report['liveFilesTouched'] = ($evidence['runtimeConfigPermissionsTouched'] ?? false) === true || ($evidence['runtimeConfigParentPermissionsTouched'] ?? false) === true;
                $report += $evidence;
                self::journal($root . '/' . ($plan['mode'] === 'restore-rehearsal' ? 'restore-rehearsal-' : 'preflight-') . $commit . '-' . bin2hex(random_bytes(8)) . '.json', $report);
                if ($plan['mode'] === 'preflight' && $report['status'] === 'inspected-not-authorized') {
                    print "native preflight inspected; no DDL; permission metadata changes reported; private receipt retained\n";
                    return;
                }
                if ($plan['mode'] === 'restore-rehearsal' && ($report['isolatedRehearsalPassed'] ?? false) === true) { print "isolated restore rehearsal verified; no source DDL or promotion\n"; return; }
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
