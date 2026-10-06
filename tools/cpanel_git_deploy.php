<?php
declare(strict_types=1);

// CLI-only staging hook. Never loads runtime config, opens PDO or promotes code.
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
            self::requireValue(($plan['mode'] ?? null) === 'stage-only' && ($plan['account'] ?? null) === 'archite4' && ($plan['repository'] ?? null) === self::REPO && ($plan['activeRoot'] ?? null) === self::ACTIVE && ($plan['documentRoot'] ?? null) === self::DOCROOT, 'plan_target_mismatch');
            self::requireValue(preg_match('/\A[a-f0-9]{40}\z/', $plan['expectedCommit'] ?? '') === 1 && preg_match('/\A[a-f0-9]{64}\z/', $plan['artifactTreeSha256'] ?? '') === 1, 'plan_digest_invalid');
            $commit = self::command(['/usr/local/cpanel/3rdparty/bin/git', 'rev-parse', '--verify', 'HEAD'], self::REPO);
            self::requireValue(hash_equals($plan['expectedCommit'], $commit), 'commit_mismatch');
            self::requireValue(self::command(['/usr/local/cpanel/3rdparty/bin/git', 'status', '--porcelain', '--untracked-files=all'], self::REPO) === '', 'repository_dirty');
            self::requireValue(self::command(['/usr/local/cpanel/3rdparty/bin/git', 'rev-parse', '--abbrev-ref', 'HEAD'], self::REPO) === 'master', 'branch_mismatch');
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
            self::journal($root . '/receipt-' . $commit . '.json', ['format'=>'a777-cpanel-stage-receipt-v1', 'status'=>'staged-not-promoted', 'commit'=>$commit, 'candidate'=>$candidate, 'artifactTreeSha256'=>$plan['artifactTreeSha256'], 'planSha256'=>hash_file('sha256', $planPath), 'databaseTouched'=>false, 'liveFilesTouched'=>false, 'migrationGate'=>'blocked: RestoreService requires architex_task namespace; cPanel archite4-prefixed owned schemas are incompatible with current DisposableDatabaseGuard', 'remainingGates'=>['owned_restore_namespace_compatibility','native_uapi_capability_and_grant_readback','apache_boundary_readback','exact_owned_cron_journal_and_readback','old_bootstrap_hook_drain','independently_verified_database_writer_users','encrypted_backup_and_matching_active_tree_restore','candidate_eight_migrations','readiness_and_atomic_promotion']]);
            print "staged-not-promoted; private receipt retained; live runtime and database untouched\n";
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try { CpanelGitDeploy::run(); }
    catch (Throwable $error) { fwrite(STDERR, "cPanel deployment refused; no automatic promotion or migration.\n"); exit(1); }
}
