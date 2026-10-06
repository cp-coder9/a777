<?php
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '0');
// Generated operator-only endpoint. Authentication happens before any runtime load.
/*OPERATOR_CONFIG*/
header('Cache-Control: no-store');
function canon(mixed $v): string {
    $sort = function (mixed $x) use (&$sort): mixed {
        if (!is_array($x)) return $x;
        if (!array_is_list($x)) ksort($x, SORT_STRING);
        foreach ($x as &$y) $y = $sort($y);
        return $x;
    };
    return json_encode($sort($v), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
}
function exactPath(string $path): bool {
    if (preg_match('/[?#%\x00-\x20]/', $path) || str_contains($path, '://') ||
        preg_match('~(?:^|/)(?:\.|\.\.)(?:/|$)~', $path)) return false;
    $resolved = realpath($path);
    return $resolved !== false && str_replace(chr(92),'/',$resolved) === str_replace(chr(92),'/',$path);
}
function persistPrivate(string $path, array $value): void {
    if (is_link($path) || !exactPath(dirname($path))) throw new RuntimeException();
    $tmp = $path . '.' . bin2hex(random_bytes(12));
    $out = fopen($tmp, 'xb');
    if (!$out || !chmod($tmp,0600)) throw new RuntimeException();
    $bytes = canon($value);
    if (fwrite($out,$bytes) !== strlen($bytes) || !fflush($out) ||
        (function_exists('fsync') && !fsync($out))) throw new RuntimeException();
    fclose($out);
    if (!rename($tmp,$path)) throw new RuntimeException();
}
function tree(string $path): string {
    if (!exactPath($path)) throw new RuntimeException('Noncanonical SQL tree');
    $entries = [];
    foreach (glob($path . '/*.sql') ?: [] as $file) {
        if (is_link($file) || !preg_match('/^\d{4}_[a-z0-9_]+\.sql$/D', basename($file))) throw new RuntimeException();
        $entries[basename($file)] = hash_file('sha256', $file);
    }
    if (!$entries) throw new RuntimeException();
    return hash('sha256', canon($entries));
}
try {
    $hex = $_SERVER['HTTP_X_A777_OPERATOR_KEY'] ?? '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !preg_match('/^[a-f0-9]{64}$/D', $hex)) throw new RuntimeException();
    $key = hex2bin($hex);
    if (!hash_equals($operator['keySha256'], hash('sha256', $key))) throw new RuntimeException();
    $body = file_get_contents('php://input', false, null, 0, 65537);
    if (strlen($body) > 65536) throw new RuntimeException();
    $request = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
    $action = $request['action'];
    $target = $operator['target'];
    $root = $target['runtimeRoot'];
    if ($action !== 'helper_identity') {
        foreach ([$root, $target['documentRoot'], $operator['candidateRoot']] as $path) {
            if (!exactPath($path)) throw new RuntimeException('Noncanonical runtime path');
            if (PHP_OS_FAMILY !== 'Windows' && !str_starts_with($path, '/home/' . $target['account'] . '/')) throw new RuntimeException();
        }
        foreach (['config/runtime.php','vendor/autoload.php'] as $relative) {
            if (file_exists($root . '/' . $relative) && !exactPath($root . '/' . $relative)) throw new RuntimeException();
        }
        $lockPath = $root . '/.operator.lock';
        if (is_link($lockPath)) throw new RuntimeException();
        $operatorLock = fopen($lockPath,'c');
        if (!$operatorLock || !chmod($lockPath,0600) || !flock($operatorLock, LOCK_EX | LOCK_NB)) throw new RuntimeException('Concurrent operator');
    }
    if (realpath(dirname(__FILE__)) !== $target['documentRoot']) throw new RuntimeException();
    $payload = ['stage'=>$action, 'challenge'=>$request['challenge'], 'target'=>$target, 'lease'=>$request['lease']];
    if ($action === 'helper_identity') {
        $payload['helperSha256'] = hash_file('sha256', __FILE__);
    } elseif (in_array($action,['install_hooks','reconcile_hooks','restore_hooks'],true)) {
        if (!exactPath($root) || is_link($root . '/bootstrap.php')) throw new RuntimeException();
        $binding = $request['binding'];
        if ($action === 'install_hooks' && !hash_equals(hash_file('sha256', $root . '/bootstrap.php'), $binding['approvedBootstrapSha256'])) throw new RuntimeException('Active bootstrap not approved');
        $values = require $root . '/config/runtime.php';
        $storage = $values['FILE_STORAGE_PATH'] ?? null;
        if (!is_string($storage) || !is_dir($storage) || is_link($storage) ||
            str_starts_with(realpath($storage) . '/', $root . '/') ||
            str_starts_with(realpath($storage) . '/', $target['documentRoot'] . '/')) throw new RuntimeException();
        $private = rtrim($storage, '/') . '/maintenance';
        if ($private !== ($operator['recoveryPaths']['maintenanceDirectory'] ?? null) || !exactPath($storage)) throw new RuntimeException('Recovery storage binding');
        if (!is_dir($private) && !mkdir($private,0700)) throw new RuntimeException();
        if (!exactPath($private) || ($action !== 'reconcile_hooks' && is_file($private . '/lease.json'))) throw new RuntimeException();
        $allowed = ['src/Infrastructure/Config/EnvironmentLoader.php',
            'src/Infrastructure/Deployment/MaintenanceFence.php', 'bootstrap.php'];
        $receipt = $private . '/owned-hooks-' . $operator['keySha256'] . '.json';
        if ($action !== 'install_hooks') {
            if (!exactPath($receipt)) throw new RuntimeException();
            $journal = json_decode(file_get_contents($receipt),true,32,JSON_THROW_ON_ERROR);
            foreach ($allowed as $relative) {
                $path = $root . '/' . $relative;
                if (file_exists($path) && !exactPath($path)) throw new RuntimeException();
                $original = $journal['original'][$relative] === null ? null : base64_decode($journal['original'][$relative],true);
                $actual = is_file($path) ? hash_file('sha256',$path) : null;
                $before = $original === null ? null : hash('sha256',$original);
                if ($actual !== $before && $actual !== $journal['installed'][$relative]) throw new RuntimeException('Unowned hook bytes');
                $journal['observed'][$relative] = $actual;
            }
            persistPrivate($receipt,$journal);
            if ($action === 'restore_hooks') {
                $journal['restoreIntent'] = true; persistPrivate($receipt,$journal);
                foreach (array_reverse($allowed) as $relative) {
                    $path = $root . '/' . $relative;
                    $original = $journal['original'][$relative];
                    if ($original === null) {
                        if (is_file($path)) {
                            // Invalidate while the canonical path still exists.
                            if (function_exists('opcache_get_status') && opcache_get_status(false) !== false && !opcache_invalidate($path,true)) throw new RuntimeException();
                            if (!unlink($path)) throw new RuntimeException();
                        }
                    }
                    else {
                        $bytes = base64_decode($original,true); $tmp = $path . '.restore-' . bin2hex(random_bytes(8));
                        if (file_put_contents($tmp,$bytes) !== strlen($bytes) || !rename($tmp,$path)) throw new RuntimeException();
                        if (hash_file('sha256',$path) !== hash('sha256',$bytes)) throw new RuntimeException();
                    }
                    if ($original !== null && function_exists('opcache_get_status') && opcache_get_status(false) !== false && !opcache_invalidate($path,true)) throw new RuntimeException();
                }
                $journal['restored'] = true; persistPrivate($receipt,$journal);
            }
            $payload['hooks'] = $journal;
        } else {
        $journal = ['approvedBootstrapSha256'=>$binding['approvedBootstrapSha256'],'original'=>[], 'installed'=>[]];
        $decoded = [];
        foreach ($allowed as $relative) {
            $path = $root . '/' . $relative;
            if (is_link($path)) throw new RuntimeException();
            $decoded[$relative] = base64_decode($binding['sources'][$relative] ?? '',true);
            if (!is_string($decoded[$relative]) || !str_starts_with($decoded[$relative],'<?php')) throw new RuntimeException();
            token_get_all($decoded[$relative], TOKEN_PARSE);
            $parent = dirname($path);
            while (!file_exists($parent)) $parent = dirname($parent);
            if (!exactPath($parent)) throw new RuntimeException('Hook ancestry');
            $journal['original'][$relative] = is_file($path) ? base64_encode(file_get_contents($path)) : null;
            $journal['installed'][$relative] = hash('sha256',$decoded[$relative]);
        }
        $receipt = $private . '/owned-hooks-' . $operator['keySha256'] . '.json';
        if (file_exists($receipt)) throw new RuntimeException();
        persistPrivate($receipt,$journal);
        foreach ($allowed as $relative) {
            $path = $root . '/' . $relative;
            $content = $decoded[$relative];
            if (!is_string($content) || !str_starts_with($content,'<?php')) throw new RuntimeException();
            $parent = dirname($path);
            if (!is_dir($parent) && !mkdir($parent,0700,true)) throw new RuntimeException();
            if (!exactPath($parent)) throw new RuntimeException();
            $tmp = $path . '.operator-' . bin2hex(random_bytes(8));
            if (file_put_contents($tmp,$content,LOCK_EX) !== strlen($content) || !chmod($tmp,0644) || !rename($tmp,$path)) throw new RuntimeException();
            if (hash_file('sha256',$path) !== hash('sha256',$content)) throw new RuntimeException();
            if (function_exists('opcache_get_status') && opcache_get_status(false) !== false &&
                !opcache_invalidate($path,true)) throw new RuntimeException();
            $journal['installed'][$relative] = hash('sha256',$content);
            $journal['observed'][$relative] = hash_file('sha256',$path);
            persistPrivate($receipt,$journal);
        }
        $payload['installedSha256'] = $journal['installed'];
        }
    } else {
        // Never require bootstrap: a normal bootstrap correctly rejects the leased
        foreach (['src/Infrastructure/Config/EnvironmentLoader.php','src/Infrastructure/Deployment/MaintenanceFence.php',
                  'vendor/autoload.php','config/runtime.php'] as $relative) {
            if (!exactPath($root . '/' . $relative)) throw new RuntimeException('Unsafe runtime load path');
        }
        // operator too. There is no header/constant bypass in the application.
        require_once $root . '/src/Infrastructure/Config/EnvironmentLoader.php';
        \Architex\Infrastructure\Config\EnvironmentLoader::apply($root);
        $storage = getenv('FILE_STORAGE_PATH');
        if (!$storage || is_link($storage) || !is_dir($storage)) throw new RuntimeException();
        $directory = rtrim($storage, '/') . '/maintenance';
        if (!exactPath($storage) || $directory !== ($operator['recoveryPaths']['maintenanceDirectory'] ?? null)) throw new RuntimeException('Private state binding');
        if (!is_dir($directory) && !mkdir($directory, 0700)) throw new RuntimeException();
        require_once $root . '/src/Infrastructure/Deployment/MaintenanceFence.php';
        $fence = new \Architex\Infrastructure\Deployment\MaintenanceFence($directory);
        $fingerprint = hash('sha256', canon($target));
        $statePath = $directory . '/operator-' . $operator['keySha256'] . '.json';
        if (is_link($statePath)) throw new RuntimeException();
        $state = is_file($statePath) ? json_decode(file_get_contents($statePath), true, 32, JSON_THROW_ON_ERROR) : [];
        if ($action === 'recovery_state') {
            $payload['recovery'] = $state;
            $payload['fence'] = $fence->status();
            header('Content-Type: application/json');
            echo json_encode(['payload'=>$payload,'signature'=>hash_hmac('sha256',canon($payload),$key)],JSON_THROW_ON_ERROR);
            exit;
        }
        $save = static function () use (&$state, $statePath): void {
            persistPrivate($statePath,$state);
        };
        if (!in_array($action, ['identity', 'active_tree', 'quiesce'], true)) {
            if (($state['challenge'] ?? null) !== $request['challenge'] || ($state['expiresAt'] ?? 0) <= time()) throw new RuntimeException();
            $lease = $fence->status();
            if (!$lease || $lease['phase'] !== 'drained' || !isset($state['lease']) ||
                !hash_equals($state['lease'], (string)$request['lease']) ||
                !hash_equals($lease['owner'], hash('sha256', $state['lease'])) ||
                !hash_equals($lease['fingerprint'], $fingerprint)) throw new RuntimeException();
        }
        require $root . '/vendor/autoload.php';
        $environment = getenv();
        $config = \Architex\Infrastructure\Config\AppConfig::fromEnvironment($environment);
        $pdo = \Architex\Infrastructure\Database\ConnectionFactory::create($config);
        if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== $target['database']) throw new RuntimeException();
        $identity = ['runtimeSha256'=>hash_file('sha256', $root . '/config/runtime.php'),
            'activeTreeSha256'=>tree($root . '/database/migrations'),
            'candidateTreeSha256'=>tree($operator['candidateRoot'] . '/database/migrations')];
        $payload += $identity;
        foreach (($request['binding'] ?? []) as $name=>$value) {
            $known = $identity + ($state['backup'] ?? []);
            if ($name === 'restoreReceiptSha256') {
                if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/D', $value)) throw new RuntimeException();
            } elseif (!array_key_exists($name, $known) || $known[$name] !== $value) throw new RuntimeException();
            $payload[$name] = $value;
        }
        $drain = static function () use ($pdo, $target, $operator, $fingerprint, $fence, $root): void {
            $model = $operator['processModel'] ?? [];
            $proofPath = $model['inventoryPath'] ?? '';
            if (!exactPath($proofPath) || !str_starts_with($proofPath,'/home/' . $target['account'] . '/') ||
                !function_exists('sodium_crypto_sign_verify_detached')) throw new RuntimeException('Provider signed process inventory required');
            $proof = json_decode(file_get_contents($proofPath),true,32,JSON_THROW_ON_ERROR);
            if (!sodium_crypto_sign_verify_detached(base64_decode($proof['signature'],true),canon($proof['payload']),
                base64_decode($model['publicKey'],true))) throw new RuntimeException('Provider process proof invalid');
            $inventory = $proof['payload'];
            if (($inventory['target'] ?? null) !== $target || ($inventory['gateFingerprint'] ?? '') !== $fingerprint ||
                ($inventory['leaseOwner'] ?? '') !== ($fence->status()['owner'] ?? null) ||
                ($inventory['bootstrapSha256'] ?? '') !== hash_file('sha256',$root . '/bootstrap.php') ||
                ($inventory['expiresAt'] ?? 0) <= time() || ($inventory['observedAt'] ?? 0) < time()-5 ||
                ($inventory['observedAt'] ?? PHP_INT_MAX) > time() ||
                !in_array($inventory['model'] ?? '',['fpm','lsapi'],true)) throw new RuntimeException('Provider process proof stale/unbound');
            // PROCESS is necessary to see other accounts connected to this DB.
            $grants = implode('\n', $pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN));
            if (!preg_match('/GRANT (?:ALL PRIVILEGES|[^\n]*\bPROCESS\b[^\n]*) ON \*\.\*/', $grants)) throw new RuntimeException('Full DB process visibility required');
            $self = (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
            foreach ($pdo->query('SHOW FULL PROCESSLIST') as $connection) {
                if ($connection['db'] === $target['database'] && (int)$connection['Id'] !== $self) throw new RuntimeException('Existing target DB connection');
            }
            // No broad kills. Unknown same-account PHP/worker processes fail closed.
            if (!is_dir('/proc') || !function_exists('posix_geteuid')) throw new RuntimeException('Independent worker visibility required');
            foreach (glob('/proc/[0-9]*/cmdline') ?: [] as $path) {
                $pid = (int)basename(dirname($path));
                if ($pid === getmypid()) continue;
                $info = @stat(dirname($path));
                if (!$info) {
                    if (is_dir(dirname($path))) throw new RuntimeException('Process ownership unavailable');
                    continue;
                }
                if ($info['uid'] !== posix_geteuid()) continue;
                $cmd = @file_get_contents($path);
                $entry = $inventory['processes'][(string)$pid] ?? null;
                $stat = @file_get_contents(dirname($path) . '/stat');
                $fields = $stat === false ? [] : explode(' ',substr($stat,strrpos($stat,')')+2));
                if ($cmd === false || !is_array($entry) || ($entry['commandSha256'] ?? '') !== hash('sha256',$cmd) ||
                    ($entry['startTicks'] ?? '') !== ($fields[19] ?? null) ||
                    ($entry['state'] ?? '') !== 'idle' || ($entry['gateAware'] ?? false) !== true ||
                    !in_array($entry['kind'] ?? '',['fpm-master','fpm-worker','lsapi-worker'],true))
                    throw new RuntimeException('Unknown writer or unverified gate-aware idle process');
            }
        };
        if ($action === 'identity') {
            // Observational only, not a quiescence attestation.
        } elseif ($action === 'active_tree') {
            $payload['files'] = [];
            foreach (glob($root . '/database/migrations/*.sql') ?: [] as $file) {
                if (is_link($file)) throw new RuntimeException();
                $payload['files'][basename($file)] = base64_encode(file_get_contents($file));
            }
        } elseif ($action === 'quiesce') {
            // Old unhooked bootstraps must be upgraded/read back independently.
            $bootstrap = file_get_contents($root . '/bootstrap.php');
            if (!str_contains($bootstrap, 'MaintenanceFence') || !str_contains($bootstrap, "getenv('FILE_STORAGE_PATH')")) throw new RuntimeException('Active entry hooks not installed');
            if (isset($state['lease']) || $fence->status() !== null || !preg_match('/^[a-f0-9]{64}$/D', $request['challenge'])) throw new RuntimeException();
            $state['challenge'] = $request['challenge'];
            $state['lease'] = bin2hex(random_bytes(32));
            $state['identity'] = $identity;
            $state['stage'] = 'quiesce_intent';
            $state['expiresAt'] = time()+1800;
            $save();
            $fence->stop($state['lease'], $fingerprint, 30);
            if (!function_exists('proc_open')) throw new RuntimeException('CLI gate probe unavailable');
            $probe = proc_open([$operator['cliBinary'], $root . '/bootstrap.php'],
                [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
            if (!is_resource($probe)) throw new RuntimeException();
            fclose($pipes[0]); stream_get_contents($pipes[1]); fclose($pipes[1]);
            stream_get_contents($pipes[2]); fclose($pipes[2]);
            if (proc_close($probe) !== 75) throw new RuntimeException('CLI maintenance gate unproven');
            $deadline = microtime(true)+30;
            do {
                try { $drain(); break; }
                catch (Throwable $error) {
                    if (microtime(true) >= $deadline) throw $error;
                    usleep(100000);
                }
            } while (true);
            $payload['lease'] = $state['lease'];
            $state['stage'] = 'quiescent'; $save();
            $payload += ['web'=>'blocked','dispatch'=>'stopped','cron'=>'stopped','expiresAt'=>$state['expiresAt'],'drainedAt'=>time()];
        } elseif ($action === 'quiescence') {
            $drain();
            $payload += ['web'=>'blocked','dispatch'=>'stopped','cron'=>'stopped','expiresAt'=>$state['expiresAt'],'drainedAt'=>$fence->status()['drainedAt']];
        } elseif ($action === 'backup') {
            if (isset($state['backupIntent']) || isset($state['backup'])) throw new RuntimeException('Backup immutable; reconcile existing intent');
            $drain();
            $backupDirectory = dirname($storage) . '/operator-recovery';
            if ($backupDirectory !== ($operator['recoveryPaths']['archiveDirectory'] ?? null)) throw new RuntimeException('Archive directory binding');
            if (is_link($backupDirectory) || str_starts_with($backupDirectory . '/', $target['documentRoot'] . '/')) throw new RuntimeException();
            if (!is_dir($backupDirectory) && !mkdir($backupDirectory, 0700)) throw new RuntimeException();
            if (!exactPath($backupDirectory) || str_starts_with($backupDirectory . '/',rtrim($storage,'/') . '/') ||
                str_starts_with($backupDirectory . '/',$root . '/')) throw new RuntimeException('Archive outside storage/runtime required');
            $archive = $backupDirectory . '/recovery-' . bin2hex(random_bytes(32)) . '.axbackup';
            $state['backupIntent'] = ['retainedPath'=>$archive,'identity'=>$identity,'startedAt'=>time()];
            $save();
            $report = (new \Architex\Infrastructure\Backup\BackupService($pdo, $storage,
                $environment['BACKUP_ARCHIVE_PASSWORD'], $environment['APP_KEY']))->create($archive);
            $state['backup'] = ['retainedPath'=>$report->path,'archiveSha256'=>hash_file('sha256',$report->path),
                'bytes'=>filesize($report->path),'backupId'=>$report->backupId];
            $state['stage'] = 'backup_retained';
            $save();
            $payload += $state['backup'];
        } elseif ($action === 'download') {
            $archive = $state['backup']['retainedPath'];
            if (is_link($archive) || filesize($archive) > 1073741824 ||
                hash_file('sha256',$archive) !== $state['backup']['archiveSha256']) throw new RuntimeException();
            header('Content-Type: application/octet-stream');
            header('Content-Length: ' . filesize($archive));
            readfile($archive); exit;
        } elseif ($action === 'readiness') {
            $drain();
            $candidate = $operator['candidateRoot'];
            spl_autoload_register(static function ($class) use ($candidate) {
                if (str_starts_with($class, 'Architex\\')) {
                    $file = $candidate . '/src/' . str_replace('\\','/',substr($class,9)) . '.php';
                    if (is_file($file)) { if (!exactPath($file)) throw new RuntimeException('Candidate load path'); require $file; }
                }
            }, true, true);
            $runtime = \Architex\Infrastructure\Mail\MailRuntime::fromEnvironment($pdo, $environment);
            $runner = new \Architex\Infrastructure\Database\MigrationRunner($pdo, $candidate . '/database/migrations');
            $controller = new \Architex\Http\Controller\ReadinessController(
                static fn(): bool => (int)$pdo->query('SELECT 1')->fetchColumn() === 1,
                static fn(): bool => $runner->status()->isCurrent(),
                static fn(): bool => (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('scheduled_jobs','scheduled_job_deduplication_keys')")->fetchColumn() === 2,
                static fn(): bool => $runtime->isReady());
            $response = $controller(\Architex\Http\Request::fromGlobals());
            if ($response->status() !== 200) throw new RuntimeException('Candidate readiness refused');
            $payload['readiness'] = $response->json()['data'];
            $payload['build'] = json_decode(file_get_contents($candidate . '/release.json'), true, 32, JSON_THROW_ON_ERROR);
        } elseif (in_array($action, ['status','migrate'], true)) {
            $drain();
            $binding = $request['binding'];
            foreach ($identity + ['archiveSha256'=>$state['backup']['archiveSha256'], 'backupId'=>$state['backup']['backupId']] as $name=>$value) {
                if (($binding[$name] ?? null) !== $value) throw new RuntimeException('Recovery binding mismatch');
            }
            if (!preg_match('/^[a-f0-9]{64}$/D', $binding['restoreReceiptSha256'] ?? '')) throw new RuntimeException();
            if (isset($state['restoreAuthorization']) && $state['restoreAuthorization'] !== $binding) throw new RuntimeException('Immutable restore authorization mismatch');
            if (!isset($state['restoreAuthorization'])) {
                if ($action !== 'status') throw new RuntimeException('Status authorization required before DDL');
                $state['restoreAuthorization'] = $binding;
                $state['stage'] = 'restore_authorized';
                $save();
            }
            if ($action === 'migrate' && isset($state['ddlIntent'])) throw new RuntimeException('DDL replay prohibited; inspect status and recovery');
            if ($action === 'migrate') { $state['ddlIntent'] = $binding; $state['stage'] = 'ddl_started'; $save(); }
            $state['intent'] = ['action'=>$action,'binding'=>$binding,'startedAt'=>time(),'ddlMayHaveCommitted'=>$action==='migrate'];
            $save();
            // Load candidate classes first in an isolated request; active Composer
            // optimized classmaps must not select the active MigrationRunner.
            $candidate = $operator['candidateRoot'];
            spl_autoload_register(static function ($class) use ($candidate) {
                if (str_starts_with($class, 'Architex\\')) {
                    $file = $candidate . '/src/' . str_replace('\\','/',substr($class,9)) . '.php';
                    if (is_file($file)) { if (!exactPath($file)) throw new RuntimeException('Candidate load path'); require $file; }
                }
            }, true, true);
            try {
                $runner = new \Architex\Infrastructure\Database\MigrationRunner($pdo, $candidate . '/database/migrations');
                if (!str_starts_with((new ReflectionClass($runner))->getFileName(), $candidate . '/')) throw new RuntimeException();
                $report = $action === 'migrate' ? $runner->migrate() : $runner->status();
                $state['result'] = get_object_vars($report);
                $state['stage'] = $action === 'migrate' ? 'ddl_completed' : ($state['stage'] ?? 'status_observed');
                $save();
                $payload += $binding; $payload['report'] = $state['result'];
            } catch (Throwable $error) {
                $state['partialDDL'] = $action === 'migrate'; $state['failedAt'] = time(); $save(); throw $error;
            }
        } else {
            throw new RuntimeException('Unsupported observed readiness contract');
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['payload'=>$payload,'signature'=>hash_hmac('sha256', canon($payload), $key)], JSON_THROW_ON_ERROR);
} catch (Throwable) {
    http_response_code(409);
    echo '{"error":"Operator operation refused; maintenance and recovery retained"}';
}
