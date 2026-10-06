<?php
declare(strict_types=1);
namespace Architex\Deployment;

use Architex\Infrastructure\Backup\{AccountOwnedRestoreTarget, BackupService, CpanelRestoreProvisioner, RestoreService};
use Architex\Infrastructure\Config\AppConfig;
use Architex\Infrastructure\Database\ConnectionFactory;
use PDO;
use RuntimeException;
use Throwable;

/** Account-shell only. No HTTP entrypoint, dependency injection, cleanup, promotion or resume. */
final class CpanelRestoreRehearsal
{
    private const PRIVATE_ROOT = '/home/archite4/.a777-deploy';
    private const RUNTIME = '/home/archite4/public_html/.a777-runtime-active';

    /** The caller supplies its actual preflight objects; this component independently revalidates them.
     * $trustedRepository is the canonical, clean checkout of $expectedCommit, not an artifact.
     * Returns only allowlisted evidence. A failed attempt retains private journals and archives.
     */
    public static function run(AppConfig $config, PDO $source, string $canonicalStorage, string $runtimePin,
        string $expectedCommit, string $planMode, string $trustedRepository): array
    {
        $result = ['status'=>'failed', 'isolatedRehearsalPassed'=>false,
            'sourceDatabaseDdlTouched'=>false, 'liveFilesTouched'=>false,
            'targetDatabaseDdlTouched'=>false, 'lastCompletedStage'=>null];
        $currentStage = 'plan';
        $journal = null; $oldMask = umask(0077);
        try {
            if ($planMode !== 'restore-rehearsal' || !preg_match('/\A[a-f0-9]{40}\z/', $expectedCommit)) throw new RuntimeException('Plan refused.');
            $currentStage = 'account';
            self::account();
            $currentStage = 'repository';
            $result += self::metadata($trustedRepository, $canonicalStorage);
            self::privateDirectory($trustedRepository);
            if (self::git($trustedRepository, ['rev-parse','HEAD']) !== $expectedCommit || self::git($trustedRepository, ['status','--porcelain','--untracked-files=all']) !== '') throw new RuntimeException('Clean commit required.');
            $currentStage = 'trustedclasses';
            // Never load incoming PHP: verify the already loaded implementation and runtime dependencies.
            foreach (get_declared_classes() as $class) {
                if (str_starts_with($class, 'Architex\\')) self::trustedClass($class, $trustedRepository);
            }
            foreach ([self::class, AppConfig::class, ConnectionFactory::class, BackupService::class,
                RestoreService::class, AccountOwnedRestoreTarget::class, CpanelRestoreProvisioner::class,
                \Architex\Infrastructure\Backup\BackupReport::class, \Architex\Infrastructure\Backup\RestoreReport::class,
                \Architex\Platform\Files\LocalFileStorage::class,
                \Architex\Infrastructure\Database\MigrationRunner::class,
                \Architex\Infrastructure\Database\MigrationReport::class,
                \Architex\Infrastructure\Database\SqlStatementLexer::class,
                \Architex\Infrastructure\Database\MigrationExecutionPolicy::class,
                \Architex\Infrastructure\Database\DdlSchemaValidator::class] as $class) self::trustedClass($class, $trustedRepository);
            // Hash every application source too, covering classes autoloaded later by real services.
            self::trackedTree($trustedRepository, $trustedRepository . '/app/src');
            $currentStage = 'runtimeconfig';
            self::privateDirectory(self::PRIVATE_ROOT);
            self::privateDirectory(self::RUNTIME . '/config');
            $runtimeFile = self::RUNTIME . '/config/runtime.php';
            self::privateFile($runtimeFile);
            if (!preg_match('/\A[a-f0-9]{64}\z/', $runtimePin) || !hash_equals($runtimePin, (string)hash_file('sha256', $runtimeFile))) throw new RuntimeException('Pinned config refused.');
            $values = require $runtimeFile;
            if (!is_array($values) || AppConfig::fromEnvironment($values) != $config || ($values['FILE_STORAGE_PATH'] ?? null) !== $canonicalStorage) throw new RuntimeException('Actual config mismatch.');
            $key = $values['APP_KEY'] ?? null;
            if (!is_string($key) || strlen((string)base64_decode($key, true)) !== 32) throw new RuntimeException('Stable key refused.');
            $currentStage = 'source-storage';
            self::privateTree($canonicalStorage);
            if (self::within(self::PRIVATE_ROOT, $canonicalStorage) || self::within(self::PRIVATE_ROOT, '/home/archite4/public_html')) throw new RuntimeException('Recovery root isolation refused.');
            if ($source->inTransaction()) throw new RuntimeException('Source transaction already active.');
            $currentStage = 'provider';
            $provider = CpanelRestoreProvisioner::fromAccountToken();
            AccountOwnedRestoreTarget::preflightManaged($source, $config, $runtimePin, $provider);
            $currentStage = 'source-engine';
            // A read-only consistent transactional snapshot is not a production writer fence.
            $engines = $source->query("SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
            if ($engines === [] || array_filter($engines, static fn($engine)=>$engine !== 'InnoDB')) throw new RuntimeException('Transactional source required.');
            $currentStage = 'archive';
            $migrations = self::RUNTIME . '/database/migrations';
            self::canonical($migrations); // RestoreService checks every active checksum against archived schema_migrations.
            self::noLinks($migrations);
            $recovery = self::PRIVATE_ROOT . '/recovery-' . $expectedCommit . '-' . bin2hex(random_bytes(16));
            if (!mkdir($recovery, 0700)) throw new RuntimeException('Recovery directory unavailable.');
            self::privateDirectory($recovery);
            $password = bin2hex(random_bytes(32));
            $journal = $recovery . '/journal.jsonl';
            self::append($journal, ['phase'=>'intent','commit'=>$expectedCommit,'runtimeSha256'=>$runtimePin,
                'sourceDatabase'=>$config->dbName,'archivePassword'=>$password,'automaticResume'=>false], true);
            $source->exec('SET TRANSACTION READ ONLY');
            $backup = (new BackupService($source, $canonicalStorage, $password, $key))->create($recovery . '/source.axbackup');
            self::privateFile($backup->path);
            $sha = hash_file('sha256', $backup->path);
            if (!is_string($sha)) throw new RuntimeException('Archive digest unavailable.');
            $result += ['archiveSha256'=>$sha,'backupId'=>$backup->backupId,'rowCount'=>$backup->rowCount,'fileCount'=>$backup->fileCount];
            $result['status']='backup-created'; $result['lastCompletedStage']='backup-created';
            self::append($journal, $result);
            // Durable provider issuance receipts retain even ambiguous failures; NEVER drop a target.
            $currentStage = 'create';
            $result['targetDatabaseDdlTouched']=true; // conservative: a lost provisioning reply can have effected DDL
            self::append($journal, ['phase'=>'target-provision-intent']);
            $target = AccountOwnedRestoreTarget::provisionManaged($source, $config, $runtimePin, $provider);
            $result['targetDatabase']=$target->database();
            self::append($journal, ['phase'=>'target-provisioned','receipt'=>$target->receipt()]);
            $result['status']='target-provisioned'; $result['lastCompletedStage']='target-provisioned';
            $currentStage = 'grant'; // ProvisionManaged combines creation and grant; conservative DDL flag remains set.
            $targetValues = $values; $targetValues['DB_NAME']=$target->database();
            $targetConfig = AppConfig::fromEnvironment($targetValues);
            $targetPdo = ConnectionFactory::create($targetConfig);
            $fileRoot = $recovery . '/restored-files';
            if (!mkdir($fileRoot, 0700)) throw new RuntimeException('Private target storage unavailable.');
            $currentStage = 'restore';
            $report = (new RestoreService($migrations, $password, $key))->restoreIntoProvisionedEmptyDatabase($backup->path, $targetPdo, $target, $fileRoot);
            if ($report->backupId !== $backup->backupId || $report->database !== $target->database() || $report->rowCount !== $backup->rowCount || $report->fileCount !== $backup->fileCount) throw new RuntimeException('Restore report mismatch.');
            self::privateTree($fileRoot);
            $currentStage = 'compare';
            // Reuse the real snapshot encoder: compare target against the IMMUTABLE archive,
            // never SELECT changing source rows after its snapshot has ended.
            $targetPdo->exec('SET TRANSACTION READ ONLY');
            $verified = (new BackupService($targetPdo, $fileRoot, $password, $key))->create($recovery . '/target-verification.axbackup');
            $sourceManifest = self::manifest($backup->path, $password);
            $targetManifest = self::manifest($verified->path, $password);
            self::assertSnapshotEquality($sourceManifest, $targetManifest, $config->dbName, $target->database());
            if (!hash_equals($sha, (string)hash_file('sha256', $backup->path))) throw new RuntimeException('Immutable source archive changed.');
            $result['status']='rehearsal-verified'; $result['lastCompletedStage']='rehearsal-verified';
            $result['isolatedRehearsalPassed']=true;
            $result['tableCount']=count($sourceManifest['database']['tables']);
            self::append($journal, $result);
        } catch (Throwable $error) {
            $result += self::failure($error, $currentStage);
            $result['status']='failed'; $result['isolatedRehearsalPassed']=false;
            // Never echo exception messages, credentials, paths, SQL, provider responses or stacks.
            if ($journal !== null) {
                try { self::append($journal, $result + ['operatorRecoveryRequired'=>true]); } catch (Throwable) {}
            }
        } finally { umask($oldMask); }
        return $result;
    }

    /** Only constants from this implementation, never exception text or arbitrary trace names. */
    private static function failure(Throwable $error, string $stage): array
    {
        $stages = ['plan','account','repository','trustedclasses','runtimeconfig','source-storage','provider','source-engine','archive','create','grant','restore','compare'];
        $reasons = [
            'Plan refused.'=>'plan_refused', 'Linux account required.'=>'linux_account_required',
            'Account refused.'=>'account_refused', 'Private directory required.'=>'private_directory_required',
            'Canonical path required.'=>'canonical_path_required', 'Symlink refused.'=>'symlink_refused',
            'Clean commit required.'=>'clean_commit_required', 'Git unavailable.'=>'git_unavailable',
            'Git refused.'=>'git_refused', 'Pinned config refused.'=>'pinned_config_refused',
            'Actual config mismatch.'=>'actual_config_mismatch', 'Stable key refused.'=>'stable_key_refused',
            'Private file required.'=>'private_file_required', 'Transactional source required.'=>'transactional_source_required',
            'Recovery root isolation refused.'=>'recovery_root_isolation_refused',
            'Source transaction already active.'=>'source_transaction_active',
            'Recovery directory unavailable.'=>'recovery_directory_unavailable',
            'Archive digest unavailable.'=>'archive_digest_unavailable',
            'Class source unavailable.'=>'class_source_unavailable', 'Untrusted class location.'=>'untrusted_class_location',
            'Class digest mismatch.'=>'class_digest_mismatch', 'Restore report mismatch.'=>'restore_report_mismatch',
            'Immutable source archive changed.'=>'immutable_source_archive_changed',
            'Journal unavailable.'=>'journal_unavailable', 'Opened journal identity refused.'=>'journal_identity_refused',
            'Journal durability refused.'=>'journal_durability_refused',
            'Private target storage unavailable.'=>'target_storage_unavailable',
            'Archive unavailable.'=>'archive_unavailable', 'Manifest unavailable.'=>'manifest_unavailable',
            'Archived identity mismatch.'=>'archived_identity_mismatch',
            'Snapshot table evidence mismatch.'=>'snapshot_table_mismatch',
            'Snapshot content evidence mismatch.'=>'snapshot_content_mismatch', 'Snapshot evidence absent.'=>'snapshot_evidence_absent',
        ];
        $out = ['failureStage'=>in_array($stage,$stages,true)?$stage:'plan', 'knownReason'=>'unclassified'];
        // Foreign exceptions cannot impersonate our static reasons.
        if ($error->getFile() === __FILE__) $out['knownReason']=$reasons[$error->getMessage()] ?? 'unclassified';
        $functions = ['run','account','canonical','privateDirectory','privateFile','privateTree','noLinks','git','trackedFile','trackedTree','trustedClass','append','manifest','assertSnapshotEquality'];
        foreach ($error->getTrace() as $frame) {
            if (($frame['class'] ?? null) === self::class && in_array($frame['function'] ?? null,$functions,true)) {
                $out['failureFunction']=$frame['function'];
                break;
            }
        }
        if ($error->getFile() === __FILE__) {
            $out['failureFile']='CpanelRestoreRehearsal.php';
            $out['failureLine']=$error->getLine();
        }
        return $out;
    }

    /** Read-only metadata; no resolved paths or owner identifiers leave this component. */
    private static function metadata(string $repository, string $storage): array
    {
        $out=[];
        foreach (['repository'=>$repository,'storage'=>$storage] as $label=>$path) {
            $stat=@lstat($path);
            $out[$label.'Mode']=is_array($stat)?sprintf('%04o',$stat['mode'] & 0777):'unavailable';
            $out[$label.'Owner']=is_array($stat)&&function_exists('posix_geteuid')?($stat['uid']===posix_geteuid()?'account':'other'):'unavailable';
            $out[$label.'Canonical']=realpath($path)===$path?'canonical':'noncanonical';
        }
        // Observe fixed cPanel binary separately without changing the current PATH-based git guard.
        $fixed='/usr/local/cpanel/3rdparty/bin/git';
        $out['fixedGitBinaryAvailability']=is_file($fixed)&&is_executable($fixed)?'available':'unavailable';
        $out['gitCommandAvailability']='unavailable';
        if (function_exists('proc_open')) {
            $process=@proc_open(['git','--version'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);
            if (is_resource($process)) {
                fclose($pipes[0]); stream_get_contents($pipes[1]); fclose($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[2]);
                $out['gitCommandAvailability']=proc_close($process)===0?'available':'unavailable';
            }
        }
        return $out;
    }

    private static function assertSnapshotEquality(array $source, array $target, string $sourceName, string $targetName): void
    {
        if ($sourceName === $targetName || ($source['database']['name'] ?? null) !== $sourceName || ($target['database']['name'] ?? null) !== $targetName) throw new RuntimeException('Archived identity mismatch.');
        foreach (['migrations','tables'] as $field) if (($source['database'][$field] ?? null) !== ($target['database'][$field] ?? null)) throw new RuntimeException('Snapshot table evidence mismatch.');
        foreach (['entries','files'] as $field) if (($source[$field] ?? null) !== ($target[$field] ?? null)) throw new RuntimeException('Snapshot content evidence mismatch.');
        if (!is_array($source['database']['tables'] ?? null) || $source['database']['tables'] === [] || !is_array($source['entries'] ?? null) || !is_array($source['files'] ?? null)) throw new RuntimeException('Snapshot evidence absent.');
    }

    private static function manifest(string $archive, string $password): array
    {
        self::privateFile($archive);
        $zip = new \ZipArchive();
        if ($zip->open($archive) !== true || !$zip->setPassword($password)) throw new RuntimeException('Archive unavailable.');
        try { $json=$zip->getFromName('manifest.json'); if (!is_string($json)) throw new RuntimeException('Manifest unavailable.'); return json_decode($json, true, 64, JSON_THROW_ON_ERROR); }
        finally { $zip->close(); }
    }
    private static function account(): void
    {
        if (PHP_OS_FAMILY !== 'Linux' || !function_exists('posix_geteuid') || !function_exists('fsync')) throw new RuntimeException('Linux account required.');
        $owner=posix_getpwuid(posix_geteuid());
        if (($owner['name'] ?? '') !== 'archite4' || ($owner['dir'] ?? '') !== '/home/archite4') throw new RuntimeException('Account refused.');
    }
    private static function within(string $path, string $root): bool { return $path === $root || str_starts_with($path, $root . '/'); }
    private static function canonical(string $path): void
    {
        if (!str_starts_with($path,'/') || realpath($path) !== $path) throw new RuntimeException('Canonical path required.');
        $walk=''; foreach (explode('/',ltrim($path,'/')) as $part) { $walk.='/'.$part; if (is_link($walk)) throw new RuntimeException('Symlink refused.'); }
    }
    private static function privateDirectory(string $path): void
    {
        self::canonical($path);
        if (!is_dir($path) || fileowner($path)!==posix_geteuid() || (fileperms($path)&0777)!==0700) throw new RuntimeException('Private directory required.');
    }
    private static function privateFile(string $path): void
    {
        self::canonical($path); $stat=lstat($path);
        if (!is_array($stat) || ($stat['mode']&0170000)!==0100000 || ($stat['mode']&0777)!==0600 || $stat['uid']!==posix_geteuid() || $stat['nlink']!==1) throw new RuntimeException('Private file required.');
    }
    private static function noLinks(string $root): void
    {
        self::canonical($root);
        $it=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $entry) self::canonical($entry->getPathname());
    }
    private static function privateTree(string $root): void
    {
        self::privateDirectory($root);
        $it=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $entry) $entry->isDir() ? self::privateDirectory($entry->getPathname()) : self::privateFile($entry->getPathname());
    }
    private static function append(string $path, array $record, bool $create=false): void
    {
        self::privateDirectory(dirname($path));
        if (!$create) self::privateFile($path);
        $handle=fopen($path,$create?'xb':'ab');
        if (!is_resource($handle)) throw new RuntimeException('Journal unavailable.');
        try {
            $stat=lstat($path); $opened=fstat($handle);
            if (!is_array($stat) || !is_array($opened) || $stat['dev']!==$opened['dev'] || $stat['ino']!==$opened['ino'] || $opened['uid']!==posix_geteuid() || ($opened['mode']&0777)!==0600 || ($opened['mode']&0170000)!==0100000 || $opened['nlink']!==1) throw new RuntimeException('Opened journal identity refused.');
            $bytes=json_encode($record, JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
            if (fwrite($handle,$bytes)!==strlen($bytes) || !fflush($handle) || !fsync($handle)) throw new RuntimeException('Journal durability refused.');
        } finally { fclose($handle); }
        self::privateFile($path);
    }
    private static function git(string $root, array $args): string
    {
        $p=proc_open(array_merge(['git','-C',$root],$args),[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);
        if (!is_resource($p)) throw new RuntimeException('Git unavailable.');
        fclose($pipes[0]); $out=stream_get_contents($pipes[1]); fclose($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[2]);
        if (proc_close($p)!==0 || !is_string($out)) throw new RuntimeException('Git refused.');
        return rtrim($out,"\r\n");
    }
    private static function trustedClass(string $class, string $root): void
    {
        $path=(new \ReflectionClass($class))->getFileName();
        if (!is_string($path)) throw new RuntimeException('Class source unavailable.');
        self::trackedFile($root,$path);
    }
    private static function trackedFile(string $root, string $path): void
    {
        self::canonical($path);
        if (!self::within($path,$root) || !is_file($path)) throw new RuntimeException('Untrusted class location.');
        $relative=substr($path,strlen($root)+1);
        // hash-object and committed blob identity preserve exact bytes (no text trimming).
        if (self::git($root,['hash-object','--no-filters',$path]) !== self::git($root,['rev-parse','HEAD:'.$relative])) throw new RuntimeException('Class digest mismatch.');
    }
    private static function trackedTree(string $root, string $tree): void
    {
        self::noLinks($tree);
        $it=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tree, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $entry) if ($entry->isFile()) self::trackedFile($root,$entry->getPathname());
    }
}
