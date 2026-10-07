<?php
declare(strict_types=1);
namespace Architex\Deployment;

use Architex\Infrastructure\Backup\{AccountOwnedRestoreTarget, BackupService, CpanelRestoreProvisioner, RestoreService};
use Architex\Infrastructure\Config\AppConfig;
use Architex\Infrastructure\Database\{ConnectionFactory, MigrationRunner};
use Architex\Infrastructure\Deployment\ReleaseVerifier;
use PDO;
use RuntimeException;
use Throwable;

require_once __DIR__.'/CpanelMaintenanceApi.php';
require_once __DIR__.'/CpanelNativeOperator.php';
require_once __DIR__.'/CpanelDatabaseWriterFence.php';
require_once __DIR__.'/CpanelFenceReceiptVerifier.php';

/** Account CLI only. One-use plan, no automatic retry, no target/archive cleanup on failure. */
final class CpanelNativeDeployment
{
    private const PRIVATE_ROOT='/home/archite4/.a777-deploy';
    private const RUNTIME='/home/archite4/public_html/.a777-runtime-active';
    private const DOCROOT='/home/archite4/public_html/php.architex.co.za';
    private const REPO='/home/archite4/repositories/a777';
    private const WEB='https://php.architex.co.za';
    private const STORAGE='/home/archite4/.a777-storage';
    private const SOURCE_PIN='5206e3a1f9447e6834fc3e2dd7cd254ce5dd47af33406c0c83085c4b4f28d0b8';

    public static function run(AppConfig $actualAppConfig, PDO $sourcePDO, string $privatePlan,
        string $verifiedProductionArtifactRoot, array $configValues, string $expectedCommit, string $expectedPin): array
    {
        $mask=umask(0077); self::$lastFailure=null; $journal=null; $work=null; $ddl=false; $gate=false; $original=[]; $fence=null;
        $result=['status'=>'refused','lastCompletedStage'=>null,'sourceDatabaseDdlTouched'=>false,'maintenanceRetained'=>false,
            'maintenanceActive'=>false,'sourcePrivilegesRevoked'=>false,'sourcePrivilegeStateUnknown'=>false,
            'operatorPrivilegesRevoked'=>true,'operatorStateUnknown'=>false,'sourceGrantMutationStarted'=>false,'sourceMutationDdlStarted'=>false,'artifactVerified'=>false,'readinessPassed'=>false];
        $stage='plan';
        try {
            self::account(); self::privateDirectory(self::PRIVATE_ROOT);
            $lockPath=self::PRIVATE_ROOT.'/native-activation.lock';
            if (!file_exists($lockPath) && !is_link($lockPath)) self::put($lockPath,'',0600);
            self::privateFile($lockPath); $activationLock=fopen($lockPath,'r+b');
            self::need(is_resource($activationLock) && self::statIdentity(fstat($activationLock))===self::statIdentity(lstat($lockPath)) && flock($activationLock,LOCK_EX|LOCK_NB),'native_activation_busy');
            self::privateFile($privatePlan);
            self::need(dirname($privatePlan)===self::PRIVATE_ROOT, 'plan_location');
            self::need(preg_match('/\A[a-f0-9]{40}\z/',$expectedCommit)===1 && preg_match('/\A[a-f0-9]{64}\z/',$expectedPin)===1 && $expectedPin===self::SOURCE_PIN,'plan_pins');
            $planBytes=file_get_contents($privatePlan); $plan=json_decode($planBytes,true,32,JSON_THROW_ON_ERROR);
            self::need(is_array($plan) && ($plan['mode']??null)==='native-deploy' && ($plan['expectedCommit']??null)===$expectedCommit
                && ($plan['runtimeConfigSha256']??null)===$expectedPin && ($plan['account']??null)==='archite4'
                && ($plan['activeRoot']??null)===self::RUNTIME && ($plan['documentRoot']??null)===self::DOCROOT
                && ($plan['sourceDatabase']??null)==='archite4_dev_rel1' && ($plan['artifactPath']??null)===$verifiedProductionArtifactRoot
                && preg_match('/\A[a-f0-9]{64}\z/',$plan['treeDigest']??'')===1,'plan_binding');
            self::need(self::git(self::REPO,['rev-parse','HEAD'])===$expectedCommit && self::git(self::REPO,['status','--porcelain','--untracked-files=all'])==='','clean_git');
            self::trackedTree(self::REPO,self::REPO.'/app/src');
            foreach ([self::class, AppConfig::class, ConnectionFactory::class, BackupService::class, RestoreService::class, AccountOwnedRestoreTarget::class, CpanelRestoreProvisioner::class, MigrationRunner::class, ReleaseVerifier::class, CpanelNativeOperator::class, \CpanelMaintenanceApi::class, \CpanelDatabaseWriterFence::class, \CpanelFenceReceiptVerifier::class, \CpanelPdoPermissionDenial::class, \Architex\Http\Controller\ReadinessController::class, \Architex\Infrastructure\Mail\MailRuntime::class] as $class) self::trustedClass($class,self::REPO);
            foreach (get_declared_classes() as $class) if (str_starts_with($class,'Architex\\')) self::trustedClass($class,self::REPO);
            self::privateDirectory($verifiedProductionArtifactRoot);
            self::need(str_starts_with($verifiedProductionArtifactRoot,self::PRIVATE_ROOT.'/'),'private_artifact');
            $inventory=self::artifactInventory($verifiedProductionArtifactRoot);
            self::need(hash_equals($plan['treeDigest'],self::treeDigest($inventory)),'artifact_digest');
            self::need((new ReleaseVerifier())->verify($verifiedProductionArtifactRoot)->valid,'release_verifier');
            foreach (['release.json','public/build-info.json'] as $file) {
                $meta=json_decode((string)file_get_contents($verifiedProductionArtifactRoot.'/'.$file),true,32,JSON_THROW_ON_ERROR);
                self::need(($meta['sourceCommit']??null)===$expectedCommit && ($meta['sourceDirty']??null)===false && ($meta['environment']??null)==='production','production_provenance');
            }
            self::canonical(self::RUNTIME); self::canonical(self::DOCROOT);
            $runtime=self::RUNTIME.'/config/runtime.php'; self::privateFile($runtime);
            self::need(hash_equals($expectedPin,(string)hash_file('sha256',$runtime)),'source_pin');
            $actual=require $runtime;
            self::need($actual===$configValues && AppConfig::fromEnvironment($actual)==$actualAppConfig && $actualAppConfig->dbName==='archite4_dev_rel1','actual_config');
            self::sourceIdentity($sourcePDO,$actualAppConfig,$expectedPin);
            self::need(!$sourcePDO->inTransaction(),'source_transaction');
            $key=$actual['APP_KEY']??null;
            self::need(is_string($key) && strlen((string)base64_decode($key,true))===32,'app_key');
            $id=bin2hex(random_bytes(16)); $work=self::PRIVATE_ROOT.'/deployment-'.$id;
            self::need(mkdir($work,0700),'work_create'); self::privateDirectory($work);
            $journal=$work.'/journal.jsonl';
            self::append($journal,['phase'=>'intent','commit'=>$expectedCommit,'planSha256'=>hash('sha256',$planBytes),'runtimeSha256'=>$expectedPin,'deploymentId'=>$id],true);
            // Prepare this private ancestor before freezing source-directory identities.
            if (!file_exists(self::STORAGE) && !is_link(self::STORAGE)) { self::append($journal,['phase'=>'storage-root-create-intent']); self::need(mkdir(self::STORAGE,0700),'storage_root_create'); }
            self::privateDirectory(self::STORAGE);
            // Exclusive create outside the work directory prevents replay of the exact plan.
            self::append(self::PRIVATE_ROOT.'/native-consumed-'.hash('sha256',$planBytes).'.jsonl',['deploymentId'=>$id,'commit'=>$expectedCommit],true);
            self::need(copy($runtime,$work.'/original-runtime.php') && chmod($work.'/original-runtime.php',0600),'config_backup');
            self::need(hash_file('sha256',$work.'/original-runtime.php')===$expectedPin,'config_backup_readback');
            $candidate=$work.'/candidate'; self::copyArtifact($verifiedProductionArtifactRoot,$candidate,$inventory);
            $result['artifactTreeSha256']=self::treeDigest(self::artifactInventory($candidate));
            $result['artifactManifestSha256']=self::openedFileIdentity($candidate.'/release-manifest.json')['sha256'];
            $result['buildSha256']=self::openedFileIdentity($candidate.'/release.json')['sha256'];
            $result['artifactVerified']=true;
            $pending=(new MigrationRunner($sourcePDO,$candidate.'/database/migrations'))->status()->pendingVersions;
            self::need(count($pending)===8 && ($plan['pendingMigrations']??null)===$pending,'eight_pending_migrations');
            $result+=['deploymentId'=>$id,'commit'=>$expectedCommit,'planSha256'=>hash('sha256',$planBytes),'recoveryDirectory'=>$work];
            self::completed($result,$journal,'staged');

            $stage='source-grants-snapshot';
            $originalPrivileges=\CpanelMaintenanceApi::target('get_privileges_on_database',['user'=>$actualAppConfig->dbUser,'database'=>$actualAppConfig->dbName])['data'];
            self::need(is_array($originalPrivileges) && array_is_list($originalPrivileges) && $originalPrivileges!==[],'original_grants');
            self::append($journal,['phase'=>'privileges-snapshot','privileges'=>$originalPrivileges]);
            $stage='apache-maintenance'; $lease=bin2hex(random_bytes(24));
            $gate=true; $result['maintenanceActive']=true; $result['maintenanceRetained']=true; self::installGate($work,$lease,$journal,$original);
            $gateBytes=self::gateBytes($lease);
            self::proveGate($lease,$gateBytes,$work);
            self::cronZero(); self::workersZero();
            self::sourceIdentity($sourcePDO,$actualAppConfig,$expectedPin);

            $issuerKey=random_bytes(32);
            $issuer=static function(string $kind,array $binding) use ($sourcePDO,$actualAppConfig,$expectedPin,$lease,$gateBytes,$issuerKey,$candidate,$actual,$work): array {
                if ($kind==='apache-maintenance') { self::proveGate($lease,$gateBytes,$work); self::cronZero(); self::workersZero(); }
                elseif ($kind==='source-config-identity') self::sourceIdentity($sourcePDO,$actualAppConfig,$expectedPin);
                elseif ($kind==='restore-readiness') self::ready($sourcePDO,is_dir($candidate)?$candidate:self::RUNTIME,$actual);
                else throw new RuntimeException('issuer_kind');
                $payload=json_encode(['kind'=>$kind,'binding'=>$binding,'issuedAt'=>time(),'receiptId'=>bin2hex(random_bytes(16)),'verified'=>true,'lease'=>$lease],JSON_THROW_ON_ERROR);
                return ['payload'=>$payload,'mac'=>hash_hmac('sha256',$payload,$issuerKey)];
            };
            $verifier=new \CpanelFenceReceiptVerifier($issuer,$issuerKey,$expectedPin,$actualAppConfig->dbUser);
            $fence=new \CpanelDatabaseWriterFence(\CpanelMaintenanceApi::target(...),$verifier(...),static fn()=>ConnectionFactory::create($actualAppConfig),$sourcePDO,$work.'/fence.json');
            self::append($journal,['phase'=>'source-grant-mutation-intent']);
            $result['sourceGrantMutationStarted']=true; $result['sourcePrivilegeStateUnknown']=true;
            $fenced=$fence->fence(['account'=>'archite4','database'=>$actualAppConfig->dbName,'user'=>$actualAppConfig->dbUser,'configSha256'=>$expectedPin,'probeTable'=>'schema_migrations']);
            self::observeGrants($actualAppConfig,$result);
            self::need($result['sourcePrivilegesRevoked']===true && !$result['sourcePrivilegeStateUnknown'],'revocation_readback');
            self::need(($fenced['remainingWriters']??null)===0,'drain');
            $stage='operator-provision';
            $operatorJournal=static fn(array $record)=>self::append($journal,$record);
            $operator=CpanelNativeOperator::prepare($actual,$operatorJournal);
            $result['operatorPrivilegesRevoked']=false; $result['operatorStateUnknown']=true;
            CpanelNativeOperator::create($operator,$operatorJournal);
            $operatorPDO=CpanelNativeOperator::connect($operator,$actual);
            $result['operatorStateUnknown']=false;
            // The cached app observer is used only for snapshot/readback.
            $sourcePDO->query('SELECT * FROM schema_migrations LIMIT 1');
            self::completed($result,$journal,'fenced');

            $stage='frozen-backup'; $storage=$actual['FILE_STORAGE_PATH'];
            self::need(is_string($storage) && !self::within(self::PRIVATE_ROOT,$storage) && !self::within($storage,self::PRIVATE_ROOT),'archive_source_isolation');
            $before=self::sourceInventory($storage,true); $password=bin2hex(random_bytes(32));
            self::append($journal,['phase'=>'backup-intent','archivePassword'=>$password]);
            $engines=$sourcePDO->query("SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
            self::need($engines!==[] && !array_filter($engines,static fn($v)=>$v!=='InnoDB'),'transactional_source');
            $sourcePDO->exec('SET TRANSACTION READ ONLY');
            $backup=(new BackupService($sourcePDO,$storage,$password,$key))->create($work.'/source.axbackup');
            $archiveSha=self::openedFileIdentity($backup->path)['sha256'];
            self::assertSourceIdentity($before,self::sourceInventory($storage,true));
            $result+=['archiveSha256'=>$archiveSha,'backupId'=>$backup->backupId,'rowCount'=>$backup->rowCount,'fileCount'=>$backup->fileCount];
            self::completed($result,$journal,'backedup');

            $stage='managed-restore';
            $provider=CpanelRestoreProvisioner::fromAccountToken();
            self::append($journal,['phase'=>'target-provision-intent']);
            $target=AccountOwnedRestoreTarget::provisionManaged($sourcePDO,$actualAppConfig,$expectedPin,$provider);
            self::append($journal,['phase'=>'target-provisioned','receipt'=>$target->receipt()]);
            $tv=$actual; $tv['DB_NAME']=$target->database();
            $targetPdo=ConnectionFactory::create(AppConfig::fromEnvironment($tv));
            $files=$work.'/restored-files'; self::need(mkdir($files,0700),'restore_storage');
            $restored=(new RestoreService(self::RUNTIME.'/database/migrations',$password,$key))->restoreIntoProvisionedEmptyDatabase($backup->path,$targetPdo,$target,$files);
            self::need($restored->backupId===$backup->backupId && $restored->rowCount===$backup->rowCount && $restored->fileCount===$backup->fileCount && $restored->database===$target->database(),'restore_report');
            self::privateTree($files);
            $targetPdo->exec('SET TRANSACTION READ ONLY');
            $comparison=(new BackupService($targetPdo,$files,$password,$key))->create($work.'/restore-verification.axbackup');
            self::assertSnapshotEquality(self::manifest($backup->path,$password),self::manifest($comparison->path,$password),$actualAppConfig->dbName,$target->database());
            self::need(hash_equals($archiveSha,self::openedFileIdentity($backup->path)['sha256']),'archive_immutable');
            $result['targetDatabase']=$target->database(); self::completed($result,$journal,'restored');
            $stage='candidate-migrations';
            $runner=new MigrationRunner($targetPdo,$candidate.'/database/migrations');
            self::need($runner->status()->pendingVersions===$pending,'target_pending');
            $migrated=$runner->migrate(); self::need($migrated->appliedCount===8 && $runner->status()->isCurrent(),'target_eight_applied');
            self::ready($targetPdo,$candidate,$tv);
            self::completed($result,$journal,'candidatevalidated');

            $stage='private-storage';
            if (!file_exists(self::STORAGE) && !is_link(self::STORAGE)) { self::append($journal,['phase'=>'storage-root-create-intent']); self::need(mkdir(self::STORAGE,0700),'storage_root_create'); }
            self::privateDirectory(self::STORAGE);
            $newStorage=self::STORAGE.'/'.$id;
            self::need(mkdir($newStorage,0700),'storage_create'); self::privateDirectory($newStorage);
            self::copyStorage($storage,$newStorage,$before);
            $newValues=$actual; $newValues['FILE_STORAGE_PATH']=$newStorage;
            $rewritten=self::rewriteStorage((string)file_get_contents($runtime),$newStorage);
            if (!is_dir($candidate.'/config')) self::need(mkdir($candidate.'/config',0700),'candidate_config_directory');
            self::privateDirectory($candidate.'/config');
            self::put($candidate.'/config/runtime.php',$rewritten,0600);
            $loaded=require $candidate.'/config/runtime.php'; self::need($loaded===$newValues,'config_only_storage');
            self::proveGate($lease,$gateBytes,$work); self::cronZero(); self::workersZero();
            self::assertSourceIdentity($before,self::sourceInventory($storage,true));
            self::need(hash_equals($archiveSha,self::openedFileIdentity($backup->path)['sha256']),'archive_immutable');
            self::sourceIdentity($sourcePDO,$actualAppConfig,$expectedPin);
            self::need(\CpanelMaintenanceApi::target('get_privileges_on_database',['user'=>$actualAppConfig->dbUser,'database'=>$actualAppConfig->dbName])['data']===[],'fence_still_empty');
            // This journal boundary is conservative: even a thrown first statement may have committed DDL.
            $operatorPDO=CpanelNativeOperator::connect($operator,$actual);
            $stage='source-ddl'; self::append($journal,['phase'=>'source-ddl-intent','pending'=>$pending]);
            $ddl=true; $result['sourceDatabaseDdlTouched']=true; $result['sourceMutationDdlStarted']=true;
            $sourceRunner=new MigrationRunner($operatorPDO,$candidate.'/database/migrations');
            self::need($sourceRunner->status()->pendingVersions===$pending,'source_pending_unchanged');
            $sourceReport=$sourceRunner->migrate(); $result['sourceMigrationCount']=$sourceReport->appliedCount;
            self::need($sourceReport->appliedCount===8 && $sourceRunner->status()->isCurrent(),'source_eight_applied');
            self::ready($operatorPDO,$candidate,$newValues); self::completed($result,$journal,'sourceDDL');
            $operatorPDO=null; $result['operatorStateUnknown']=true;
            CpanelNativeOperator::revoke($operator,$actual,$operatorJournal);
            $result['operatorPrivilegesRevoked']=true; $result['operatorStateUnknown']=false;

            $stage='promotion';
            // Candidate runtime stays owner-only and denied for direct Apache requests.
            self::put($candidate.'/.htaccess',"Require all denied\n",0600);
            $public=self::DOCROOT.'.candidate-'.$id; self::append($journal,['phase'=>'public-candidate-create-intent','path'=>$public]); self::need(mkdir($public,0755),'public_candidate');
            self::put($public.'/.htaccess',$gateBytes,0644);
            self::publishPublic($candidate.'/public',$public);

            // Intent written BEFORE every atomic same-filesystem rename; old runtime and docroot are retained.
            self::ownedRename(self::RUNTIME,$work.'/old-runtime',$journal);
            self::ownedRename($candidate,self::RUNTIME,$journal);
            self::ownedRename(self::DOCROOT,$work.'/old-docroot',$journal);
            self::ownedRename($public,self::DOCROOT,$journal);
            self::proveGate($lease,$gateBytes,$work);
            self::completed($result,$journal,'promoted');
            $stage='restore-grants';
            $binding=['account'=>'archite4','database'=>$actualAppConfig->dbName,'user'=>$actualAppConfig->dbUser,'configSha256'=>$expectedPin];
            // Native HMAC issuer checks promoted-tree readiness using the cached source observer.
            // Fence restores the exact snapshot only after negative read and privilege proofs.
            self::ready($sourcePDO,self::RUNTIME,$newValues);
            self::append($journal,['phase'=>'restore-grants-intent']);
            $restoration=$fence->restore(['binding'=>$binding,'commit'=>$expectedCommit,'planId'=>hash('sha256',$planBytes)]);
            self::observeGrants($actualAppConfig,$result);
            self::need(!$result['sourcePrivilegeStateUnknown'] && !$result['sourcePrivilegesRevoked'],'restored_grants_readback');
            self::append($journal,['phase'=>'grants-restored','fenceReceipt'=>$restoration]);
            $fresh=ConnectionFactory::create($actualAppConfig); $fresh->query('SELECT * FROM schema_migrations LIMIT 1');
            self::need((new MigrationRunner($fresh,self::RUNTIME.'/database/migrations'))->status()->isCurrent(),'fresh_current');
            self::need((require self::RUNTIME.'/config/runtime.php')===$newValues,'promoted_config');
            self::ready($fresh,self::RUNTIME,$newValues);
            $stage='web-pool';
            self::webPoolProbe($work,$lease,$expectedCommit,$journal);
            self::cronZero(); self::workersZero();
            $normal=(string)file_get_contents(self::RUNTIME.'/public/.htaccess');
            self::need($normal!=='' && !str_contains($normal,'a777-native-maintenance'),'normal_htaccess');
            self::append($journal,['phase'=>'remove-maintenance-intent','sha256'=>hash('sha256',$normal)]);
            self::replaceOwned(self::DOCROOT.'/.htaccess',$gateBytes,$normal,0644,$journal); $gate=false;
            $stage='final-http';
            try { self::httpReady(self::http('/api/v1/readiness')); self::httpBuild(self::http('/build-info.json'),$expectedCommit); }
            catch (Throwable $e) { try { self::replaceOwned(self::DOCROOT.'/.htaccess',$normal,$gateBytes,0644,$journal); $gate=true; } catch (Throwable) {} throw $e; }
            self::need((new MigrationRunner($fresh,self::RUNTIME.'/database/migrations'))->status()->isCurrent(),'final_current');
            self::need((require self::RUNTIME.'/config/runtime.php')===$newValues,'final_config');
            self::cronZero(); $result['maintenanceRetained']=false; $result['readinessPassed']=true;
            self::need($result['operatorPrivilegesRevoked'] && !$result['operatorStateUnknown'],'operator_final_state');
            self::completed($result,$journal,'deployed'); $result['status']='deployed';
        } catch (Throwable $error) {
            if ($ddl && !$gate && isset($gateBytes,$journal) && is_file(self::DOCROOT.'/.htaccess')) {
                try { self::replaceOwned(self::DOCROOT.'/.htaccess',(string)file_get_contents(self::DOCROOT.'/.htaccess'),$gateBytes,0644,$journal); $gate=true; } catch (Throwable) {}
            }
            $result['status']=$ddl?'recovery_required':'failed'; $result['failureStage']=$stage;
            $result['failureCode']=self::safeFailureCode($error);
            $result['failureType']= $error instanceof \PDOException ? 'PDOException' : ($error instanceof \JsonException ? 'JsonException' : ($error instanceof RuntimeException ? 'RuntimeException' : 'operation_refused'));
            $result['failureLine']= $error->getFile()===__FILE__ ? $error->getLine() : null;
            $result['maintenanceRetained']=$gate;
            // No filesystem rollback is ever represented as a database rollback.
            if (isset($operator) && !$result['operatorPrivilegesRevoked']) {
                $result['operatorStateUnknown']=true; $operatorPDO=null;
                try { CpanelNativeOperator::revoke($operator,$actual,$operatorJournal); $result['operatorPrivilegesRevoked']=true; $result['operatorStateUnknown']=false; } catch (Throwable) {}
            }
            if ($result['sourceGrantMutationStarted']) self::observeGrants($actualAppConfig,$result);
            if (!$ddl && $gate && isset($originalPrivileges,$actualAppConfig)) {
                try {
                    if ($result['sourceGrantMutationStarted']) self::restoreGrants($actualAppConfig,$originalPrivileges);
                    if ($result['sourceGrantMutationStarted']) {
                        self::observeGrants($actualAppConfig,$result);
                        self::need(!$result['sourcePrivilegeStateUnknown'] && !$result['sourcePrivilegesRevoked'],'original_grants_readback');
                    }
                    self::restoreOriginalGate($original,$journal); $gate=false; $result['maintenanceRetained']=false;
                } catch (Throwable) { $result['status']='recovery_required'; }
            }
            if ($gate) { $result['status']='recovery_required'; $result['maintenanceRetained']=true; }
            if ($journal!==null) { try { self::append($journal,$result+['phase'=>'failure','exceptionClass'=>get_class($error)]); } catch (Throwable) {} }
            // Original exception retained for the CLI caller; never put its message/SQL/secrets in public output.
            self::$lastFailure=$error;
        } finally {
            if (isset($activationLock) && is_resource($activationLock)) { flock($activationLock,LOCK_UN); fclose($activationLock); }
            umask($mask);
        }
        $result['maintenanceActive']=$gate;
        if ($result['operatorStateUnknown'] || !$result['operatorPrivilegesRevoked'] || $result['sourcePrivilegeStateUnknown'] || $result['sourcePrivilegesRevoked']) $result['status']='recovery_required';
        return $result;
    }
    /** Recovery-only audited helpers. No migration, fence, operator creation or backup issuance. */
    public static function recoveryHelpers(string $approvedCommit): array
    {
        self::account(); self::need(posix_geteuid()===2657,'recovery_uid');
        self::need(preg_match('/\A[a-f0-9]{40}\z/',$approvedCommit)===1 && self::git(self::REPO,['rev-parse','HEAD'])===$approvedCommit && self::git(self::REPO,['status','--porcelain','--untracked-files=all'])==='','clean_git');
        self::trackedFile(self::REPO,__FILE__);
        self::trackedFile(self::REPO,__DIR__.'/CpanelNativeRecovery.php');
        return [
            'privateDirectory'=>self::privateDirectory(...), 'privateFile'=>self::privateFile(...),
            'canonical'=>self::canonical(...), 'inventory'=>self::artifactInventory(...), 'digest'=>self::treeDigest(...),
            'append'=>self::append(...), 'put'=>self::put(...), 'replace'=>self::replaceOwned(...),
            'rename'=>self::ownedRename(...), 'gate'=>self::gateBytes(...), 'proveGate'=>self::proveGate(...),
            'cron'=>self::cronZero(...), 'workers'=>self::workersZero(...), 'storageInventory'=>self::sourceInventory(...),
            'privateTree'=>self::privateTree(...), 'restoreGrants'=>self::restoreGrants(...), 'ready'=>self::ready(...),
            'webProbe'=>self::webPoolProbe(...), 'http'=>self::http(...), 'httpReady'=>self::httpReady(...), 'httpBuild'=>self::httpBuild(...),
            'tracked'=>self::trackedFile(...), 'git'=>self::git(...)
        ];
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
    /** Bounded account-shell model: root and this uid are trusted. These fresh checks
     * detect observed changes, not hostile root/same-uid races or absolute TOCTOU elimination.
     * Never mutate source permissions. */
    private static function freshStat(string $path): array|false
    {
        clearstatcache(true, $path);
        return @lstat($path);
    }
    private static function ancestorDirectoryStat(array|false $stat, int $owner): void
    {
        if (!is_array($stat) || ($stat['mode'] & 0170000) !== 0040000
            || !in_array($stat['uid'], [0, $owner], true) || ($stat['mode'] & 0022) !== 0) {
            throw new RuntimeException('Unsafe path ancestry.');
        }
    }
    private static function canonical(string $path): void
    {
        clearstatcache(true);
        if (!str_starts_with($path,'/') || realpath($path) !== $path) throw new RuntimeException('Canonical path required.');
        $walk=$path;
        while (true) {
            $stat=self::freshStat($walk);
            if (!is_array($stat)) throw new RuntimeException('Canonical path required.');
            if (($stat['mode'] & 0170000) === 0120000) throw new RuntimeException('Symlink refused.');
            if ($walk !== $path || ($stat['mode'] & 0170000) === 0040000) self::ancestorDirectoryStat($stat, posix_geteuid());
            if ($walk === '/') break;
            $walk=dirname($walk);
        }
    }
    private static function privateDirectory(string $path): void
    {
        self::canonical($path);
        $stat=self::freshStat($path);
        if (!is_array($stat) || ($stat['mode']&0170000)!==0040000 || $stat['uid']!==posix_geteuid() || ($stat['mode']&07777)!==0700) throw new RuntimeException('Private directory required.');
        if (self::statIdentity($stat)!==self::openedDirectoryIdentity($path)) throw new RuntimeException('Unsafe path ancestry.');
    }
    private static function privateFile(string $path): void
    {
        self::canonical($path); $stat=self::freshStat($path);
        if (!is_array($stat) || ($stat['mode']&0170000)!==0100000 || ($stat['mode']&07777)!==0600 || $stat['uid']!==posix_geteuid() || $stat['nlink']!==1) throw new RuntimeException('Private file required.');
    }
    private static function noLinks(string $root): void
    {
        self::canonical($root);
        $it=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $entry) self::canonical($entry->getPathname());
    }
    /** Read-only source policy; directory names/metadata need not be confidential. */
    private static function sourceDirectoryStat(array|false $stat, int $owner): bool
    {
        if (!is_array($stat) || ($stat['mode'] & 0170000) !== 0040000 || $stat['uid'] !== $owner
            || ($stat['mode'] & 0022) !== 0 || !in_array($stat['mode'] & 07777, [0700,0750,0755], true)) {
            throw new RuntimeException('Source directory required.');
        }
        return ($stat['mode'] & 0077) === 0;
    }
    private static function sourceDirectory(string $path): bool
    {
        self::canonical($path);
        return self::sourceDirectoryStat(self::freshStat($path), posix_geteuid());
    }
    private static function sourceLocation(string $root, bool $allowExistingPublicSourceForRehearsal=false): void
    {
        self::sourceLocationInScope($root, '/home/archite4/public_html', $allowExistingPublicSourceForRehearsal);
    }
    private static function sourceLocationInScope(string $root, string $publicRoot, bool $allow=false): void
    {
        if (!$allow && self::within($root, $publicRoot)) throw new RuntimeException('Source public storage refused.');
    }
    private static function sourceTree(string $root, bool $allowExistingPublicSourceForRehearsal=false): bool
    {
        self::canonical($root);
        self::sourceLocation($root, $allowExistingPublicSourceForRehearsal);
        $ownerOnly = self::sourceDirectory($root);
        $it=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $entry) {
            // canonical() rejects links before type dispatch; privateFile rejects specials/hardlinks.
            $path=$entry->getPathname(); self::canonical($path);
            if ($entry->isDir()) { $private=self::sourceDirectory($path); $ownerOnly=$ownerOnly && $private; }
            else self::privateFile($path);
        }
        return $ownerOnly;
    }
    private static function assertSourceIdentity(array $before, array $after): void
    {
        if ($before !== $after) throw new RuntimeException('Source tree changed.');
    }
    private static function statIdentity(array $stat): array
    {
        return array_intersect_key($stat, array_flip(['dev','ino','mode','uid','nlink','size']));
    }
    /** Hash actual opened regular-file handles, checking fresh same-path identity on both sides. */
    private static function openedFileIdentity(string $path): array
    {
        self::privateFile($path);
        $handle=@fopen($path, 'rb');
        if (!is_resource($handle)) throw new RuntimeException('Private file required.');
        try {
            self::privateFile($path);
            $before=fstat($handle); $named=self::freshStat($path);
            if (!is_array($before) || !is_array($named) || self::statIdentity($before)!==self::statIdentity($named)) throw new RuntimeException('Source tree changed.');
            $hash=hash_init('sha256');
            if (hash_update_stream($hash,$handle)!==$before['size']) throw new RuntimeException('Source tree changed.');
            $digest=hash_final($hash);
            self::privateFile($path);
            $after=fstat($handle); $named=self::freshStat($path);
            if (!is_array($after) || !is_array($named) || self::statIdentity($before)!==self::statIdentity($after) || self::statIdentity($after)!==self::statIdentity($named)) throw new RuntimeException('Source tree changed.');
            return self::statIdentity($after)+['sha256'=>$digest];
        } finally { fclose($handle); }
    }
    private static function openedDirectoryIdentity(string $path): array
    {
        self::canonical($path);
        $handle=@fopen($path,'rb');
        if (!is_resource($handle)) throw new RuntimeException('Source tree changed.');
        try {
            $opened=fstat($handle); self::canonical($path); $named=self::freshStat($path);
            if (!is_array($opened) || !is_array($named) || self::statIdentity($opened)!==self::statIdentity($named)) throw new RuntimeException('Source tree changed.');
            return self::statIdentity($opened);
        } finally { fclose($handle); }
    }
    private static function sourceInventory(string $root, bool $allowExistingPublicSourceForRehearsal=false): array
    {
        self::sourceTree($root, $allowExistingPublicSourceForRehearsal);
        $out=[]; $walk=$root;
        while (true) {
            self::canonical($walk); $stat=self::freshStat($walk);
            if (!is_array($stat)) throw new RuntimeException('Source tree changed.');
            $out[$walk]=self::openedDirectoryIdentity($walk);
            if ($walk==='/') break;
            $walk=dirname($walk);
        }
        $it=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $entry) {
            $path=$entry->getPathname(); self::canonical($path); $stat=self::freshStat($path);
            if (!is_array($stat)) throw new RuntimeException('Source tree changed.');
            if (($stat['mode']&0170000)===0040000) { self::sourceDirectory($path); $out[$path]=self::openedDirectoryIdentity($path); }
            else $out[$path]=self::openedFileIdentity($path);
        }
        ksort($out,SORT_STRING);
        return $out;
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
            self::privateDirectory(dirname($path)); self::privateFile($path);
            $stat=self::freshStat($path); $opened=fstat($handle);
            if (!is_array($stat) || !is_array($opened) || $stat['dev']!==$opened['dev'] || $stat['ino']!==$opened['ino'] || $opened['uid']!==posix_geteuid() || ($opened['mode']&0777)!==0600 || ($opened['mode']&0170000)!==0100000 || $opened['nlink']!==1) throw new RuntimeException('Opened journal identity refused.');
            $bytes=json_encode($record, JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
            if (fwrite($handle,$bytes)!==strlen($bytes) || !fflush($handle) || !fsync($handle)) throw new RuntimeException('Journal durability refused.');
        } finally { fclose($handle); }
        self::privateFile($path);
        if ($create) {
            $dir=fopen(dirname($path),'rb'); self::need(is_resource($dir),'journal_directory');
            try { self::need(fsync($dir),'journal_directory_durability'); } finally { fclose($dir); }
        }
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

    private static ?Throwable $lastFailure=null;
    public static function lastFailure(): ?Throwable { return self::$lastFailure; }
    private const FAILURE_GATES=['actual_config','additional_php_workers','apache_readback','app_key','archive_immutable','archive_source_isolation','artifact_digest','artifact_private','artifact_special','atomic_rename_same_filesystem','build_http','build_identity','candidate_config_directory','candidate_copy','candidate_create','candidate_dirs','candidate_readback','candidate_verifier','clean_git','config_backup','config_backup_readback','config_only_storage','cron_inventory','cron_inventory_unknown','cron_nonzero','direct_runtime_denied','drain','eight_pending_migrations','fence_still_empty','file_durability','file_exists','file_open','file_readback','final_config','final_current','fresh_current','gate_owner','gate_remove','gate_remove_readback','gate_rollback_owner','gate_rollback_ownership','gate_symlink','grants_readback_shape','http_curl','http_scope','http_transport','identity_config','identity_pdo','identity_pin','journal_directory','journal_directory_durability','native_activation_busy','normal_htaccess','operator_final_state','original_grants','original_grants_readback','plan_binding','plan_pins','private_artifact','probe_absence','probe_cleanup','process_inventory_unknown','production_provenance','promoted_config','public_candidate','public_copy_readback','public_directory','public_shim_path','readiness_component','readiness_http','readiness_status','release_verifier','rename_dir','rename_dir_sync','rename_failed','rename_readback','rename_scope','replace_identity','replace_readback','replace_rename','restore_privileges','restore_privileges_readback','restore_report','restore_storage','restored_grants_readback','revocation_readback','source_eight_applied','source_pending_unchanged','source_pin','source_transaction','storage_copy','storage_create','storage_directory','storage_hash','storage_literal_arrow','storage_literal_expression','storage_literal_unique','storage_literal_value','storage_root_create','target_eight_applied','target_pending','transactional_source','web_pool_identity','web_pool_probe','web_pool_readback','work_create'];
    private static ?\WeakMap $gateFailures=null;
    public static function safeFailureCode(Throwable $error): string { return self::$gateFailures[$error] ?? 'native_operation_refused'; }
    private static function need(bool $ok,string $gate): void { if (!$ok) { $error=new RuntimeException($gate); self::$gateFailures ??= new \WeakMap(); self::$gateFailures[$error]=in_array($gate,self::FAILURE_GATES,true)?$gate:'native_operation_refused'; throw $error; } }
    private static function completed(array &$result,string $journal,string $stage): void
    { $result['lastCompletedStage']=$stage; $result['status']=$stage; self::append($journal,['phase'=>$stage]+$result); }
    private static function treeDigest(array $files): string { return hash('sha256',json_encode($files,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)); }
    private static function artifactInventory(string $root): array
    {
        self::canonical($root); $out=[];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::SELF_FIRST) as $entry) {
            $path=$entry->getPathname(); self::canonical($path);
            if ($entry->isDir()) continue;
            self::need($entry->isFile(),'artifact_special');
            $name=substr($path,strlen($root)+1);
            self::need(!preg_match('#(^|/)(?:\.git|\.private|\.env(?:\.[^/]*)?|runtime\.php)(/|$)#i',$name),'artifact_private');
            $out[$name]=['sha256'=>hash_file('sha256',$path),'bytes'=>$entry->getSize()];
        }
        ksort($out,SORT_STRING); return $out;
    }
    private static function copyArtifact(string $source,string $target,array $inventory): void
    {
        self::need(mkdir($target,0700),'candidate_create');
        foreach ($inventory as $name=>$entry) {
            $to=$target.'/'.$name;
            if (!is_dir(dirname($to))) self::need(mkdir(dirname($to),0700,true),'candidate_dirs');
            self::need(copy($source.'/'.$name,$to) && chmod($to,0600),'candidate_copy');
        }
        self::need(self::artifactInventory($source)===$inventory && self::artifactInventory($target)===$inventory,'candidate_readback');
        self::need((new ReleaseVerifier())->verify($target)->valid,'candidate_verifier');
    }
    private static function sourceIdentity(PDO $pdo,AppConfig $config,string $pin): void
    {
        self::privateFile(self::RUNTIME.'/config/runtime.php');
        self::need(hash_equals($pin,(string)hash_file('sha256',self::RUNTIME.'/config/runtime.php')),'identity_pin');
        self::need(AppConfig::fromEnvironment(require self::RUNTIME.'/config/runtime.php')==$config,'identity_config');
        self::need($pdo->query('SELECT DATABASE()')->fetchColumn()==='archite4_dev_rel1'
            && explode('@',(string)$pdo->query('SELECT CURRENT_USER()')->fetchColumn())[0]===$config->dbUser,'identity_pdo');
    }
    /** Exact literal token replacement; no eval and no regenerated configuration. */
    public static function rewriteStorage(string $php,string $path): string
    {
        $tokens=token_get_all($php,TOKEN_PARSE); $offset=0; $matches=[];
        for ($i=0;$i<count($tokens);$i++) {
            $token=$tokens[$i]; $text=is_array($token)?$token[1]:$token;
            if (is_array($token) && $token[0]===T_CONSTANT_ENCAPSED_STRING && in_array($text,["'FILE_STORAGE_PATH'",'"FILE_STORAGE_PATH"'],true)) {
                $j=$i+1; $pos=$offset+strlen($text);
                while (isset($tokens[$j]) && is_array($tokens[$j]) && in_array($tokens[$j][0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true)) { $pos+=strlen($tokens[$j][1]); $j++; }
                self::need(isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][0]===T_DOUBLE_ARROW,'storage_literal_arrow'); $pos+=strlen($tokens[$j][1]); $j++;
                while (isset($tokens[$j]) && is_array($tokens[$j]) && in_array($tokens[$j][0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true)) { $pos+=strlen($tokens[$j][1]); $j++; }
                self::need(isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][0]===T_CONSTANT_ENCAPSED_STRING,'storage_literal_value');
                $end=$pos+strlen($tokens[$j][1]); $k=$j+1;
                while (isset($tokens[$k]) && is_array($tokens[$k]) && in_array($tokens[$k][0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true)) $k++;
                self::need(isset($tokens[$k]) && in_array($tokens[$k],[',',']',')'],true),'storage_literal_expression');
                $matches[]=[$pos,$end-$pos];
            }
            $offset+=strlen($text);
        }
        self::need(count($matches)===1,'storage_literal_unique');
        return substr_replace($php,var_export($path,true),$matches[0][0],$matches[0][1]);
    }
    private static function copyStorage(string $source,string $target,array $before): void
    {
        self::assertSourceIdentity($before,self::sourceInventory($source,true));
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::SELF_FIRST) as $entry) {
            $to=$target.'/'.substr($entry->getPathname(),strlen($source)+1);
            if ($entry->isDir()) self::need(mkdir($to,0700),'storage_directory');
            else {
                self::need(copy($entry->getPathname(),$to) && chmod($to,0600),'storage_copy');
                self::need(self::openedFileIdentity($to)['sha256']===$before[$entry->getPathname()]['sha256'],'storage_hash');
            }
        }
        self::privateTree($target); self::assertSourceIdentity($before,self::sourceInventory($source,true));
    }
    private static function put(string $path,string $bytes,int $mode): void
    {
        self::need(!file_exists($path) && !is_link($path),'file_exists'); $h=fopen($path,'xb'); self::need(is_resource($h),'file_open');
        try { self::need(chmod($path,$mode) && fwrite($h,$bytes)===strlen($bytes) && fflush($h) && fsync($h),'file_durability'); } finally { fclose($h); }
        self::need(file_get_contents($path)===$bytes,'file_readback');
    }
    private static function replaceOwned(string $path,string $expected,string $bytes,int $mode,string $journal): void
    {
        self::canonical($path); self::need(fileowner($path)===posix_geteuid() && file_get_contents($path)===$expected,'replace_identity');
        $tmp=$path.'.native-'.bin2hex(random_bytes(8)); self::put($tmp,$bytes,$mode);
        self::append($journal,['phase'=>'replace-intent','path'=>$path,'beforeSha256'=>hash('sha256',$expected),'afterSha256'=>hash('sha256',$bytes),'temporary'=>$tmp]);
        self::need(rename($tmp,$path),'replace_rename'); self::need(file_get_contents($path)===$bytes,'replace_readback');
    }
    public static function ownedRename(string $from,string $to,string $journal): void
    {
        self::canonical($from); self::need(fileowner($from)===posix_geteuid() && !file_exists($to) && !is_link($to),'rename_scope');
        $stat=lstat($from); self::canonical(dirname($to));
        self::need(lstat(dirname($to))['dev']===$stat['dev'],'atomic_rename_same_filesystem');
        self::append($journal,['phase'=>'rename-intent','from'=>$from,'to'=>$to,'device'=>$stat['dev'],'inode'=>$stat['ino']]);
        self::need(rename($from,$to),'rename_failed'); clearstatcache(true);
        self::need(!file_exists($from) && !is_link($from) && is_array($after=lstat($to)) && $after['dev']===$stat['dev'] && $after['ino']===$stat['ino'],'rename_readback');
        $h=fopen(dirname($to),'rb'); self::need(is_resource($h),'rename_dir'); try { self::need(fsync($h),'rename_dir_sync'); } finally { fclose($h); }
        self::append($journal,['phase'=>'rename-complete','from'=>$from,'to'=>$to]);
    }
    private static function gateBytes(string $lease): string
    {
        return "# a777-native-maintenance\nRewriteEngine On\nRewriteRule ^ - [R=503,L]\nHeader always set X-A777-Deployment-Lease \"$lease\"\nHeader always set Cache-Control \"no-store\"\nErrorDocument 503 \"Deployment maintenance\"\n";
    }
    private static function installGate(string $work,string $lease,string $journal,array &$out): void
    {
        $out=[];
        foreach ([self::DOCROOT.'/.htaccess'=>self::gateBytes($lease),self::RUNTIME.'/.htaccess'=>"Require all denied\n"] as $path=>$bytes) {
            self::canonical(dirname($path)); self::need(!is_link($path),'gate_symlink');
            $exists=file_exists($path); $old=$exists?(string)file_get_contents($path):'';
            if ($exists) { self::canonical($path); self::need(fileowner($path)===posix_geteuid() && is_file($path),'gate_owner'); }
            $entry=['path'=>$path,'existed'=>$exists,'bytes'=>$old,'mode'=>$exists?(fileperms($path)&0777):0644,'installed'=>$bytes];
            // Persist byte journal before first mutation, including absence.
            self::append($journal,['phase'=>'apache-intent']+$entry); $out[]=$entry;
        }
        // All original bytes and absence maps are durable before either gate changes.
        foreach ($out as $entry) {
            $path=$entry['path']; $bytes=$entry['installed'];
            if ($entry['existed']) self::replaceOwned($path,$entry['bytes'],$bytes,0644,$journal); else self::put($path,$bytes,0644);
        }
    }
    private static function restoreOriginalGate(array $entries,string $journal): void
    {
        foreach (array_reverse($entries) as $entry) {
            $path=$entry['path'];
            clearstatcache(true,$path);
            if (!$entry['existed'] && !file_exists($path) && !is_link($path)) continue;
            self::canonical($path); self::need(is_file($path) && fileowner($path)===posix_geteuid(),'gate_rollback_owner');
            $current=file_get_contents($path);
            if ($entry['existed'] && $current===$entry['bytes'] && (fileperms($path)&0777)===$entry['mode']) continue;
            self::need($current===$entry['installed'],'gate_rollback_ownership');
            self::append($journal,['phase'=>'apache-rollback-intent','path'=>$path]);
            if ($entry['existed']) self::replaceOwned($path,$entry['installed'],$entry['bytes'],$entry['mode'],$journal);
            else { self::need(unlink($path),'gate_remove'); clearstatcache(true,$path); self::need(!file_exists($path),'gate_remove_readback'); }
        }
    }
    private static function proveGate(string $lease,string $bytes,?string $work=null): void
    {
        self::need(file_get_contents(self::DOCROOT.'/.htaccess')===$bytes && file_get_contents(self::RUNTIME.'/.htaccess')==="Require all denied\n",'apache_readback');
        foreach (['/','/index.php','/api/v1/readiness','/build-info.json'] as $path) {
            $r=self::http($path); self::need($r['status']===503 && ($r['headers']['x-a777-deployment-lease']??null)===$lease,'apache_http_503_lease');
        }
        $r=self::http('/.a777-runtime-active/config/runtime.php','https://architex.co.za');
        if (!in_array($r['status'],[403,404],true)) {
            // Authenticate the exact account/home through the existing read-only API.
            // Provider DNS is fixed, not an operator-supplied origin or arbitrary URL.
            \CpanelMaintenanceApi::inventory('list_databases');
            self::need(\CpanelMaintenanceApi::ORIGIN==='https://cp73.domains.co.za:2083','runtime_origin_provider');
            $records=dns_get_record('cp73.domains.co.za',DNS_A|DNS_AAAA);
            self::need(is_array($records) && $records!==[],'runtime_origin_dns');
            $ips=[];
            foreach ($records as $record) {
                $ip=$record['ip']??$record['ipv6']??null;
                self::need(is_string($ip),'runtime_origin_dns_shape');
                self::runtimeOriginResolve($ip); $ips[$ip]=true;
            }
            $originDenied=true;
            foreach (array_keys($ips) as $ip) {
                if (!in_array(self::runtimeOriginHttp($ip),[403,404],true)) $originDenied=false;
            }
            self::need($ips!==[],'direct_runtime_origin_unavailable');
            if (!$originDenied) {
                // This proves only the owned Apache virtualhost root, not public TLS.
                // Authenticate account first and require the exact installed deny bytes.
                self::need(file_get_contents(self::RUNTIME.'/.htaccess')==="Require all denied\n",'apache_readback');
                self::need($work!==null,'runtime_origin_diagnostic_work');
                self::privateDirectory($work);
                $check=self::runtimeLoopbackHttp();
                $diagnostic=$work.'/runtime-origin-check.json';
                $encoded=json_encode($check,JSON_THROW_ON_ERROR)."\n";
                if (file_exists($diagnostic) || is_link($diagnostic)) {
                    self::privateFile($diagnostic);
                    self::replaceOwned($diagnostic,(string)file_get_contents($diagnostic),$encoded,0600,$work.'/journal.jsonl');
                } else self::put($diagnostic,$encoded,0600);
                self::need(file_get_contents(self::DOCROOT.'/.htaccess')===$bytes && file_get_contents(self::RUNTIME.'/.htaccess')==="Require all denied\n",'apache_readback');
                self::need(self::runtimeLoopbackDenied($check),'direct_runtime_origin_denied');
            }
        }
    }
    private static function runtimeLoopbackOptions(): array
    {
        // No caller-controlled URL, host, port, address or headers; HTTP is local only.
        return [CURLOPT_URL=>'http://architex.co.za:80/.a777-runtime-active/config/runtime.php',
            CURLOPT_RESOLVE=>['architex.co.za:80:127.0.0.1'],CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_MAXREDIRS=>0,CURLOPT_PROXY=>'',CURLOPT_NOPROXY=>'*',
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTP,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTP,
            CURLOPT_HTTPGET=>true,CURLOPT_HTTPHEADER=>[],CURLOPT_NETRC=>CURL_NETRC_IGNORED,
            CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>45];
    }
    private static function runtimeLoopbackDenied(array $check): bool
    {
        return ($check['transport']??null)==='loopback-http'
            && ($check['peerVerified']??null)===true && ($check['curlErrorCode']??null)===0
            && in_array($check['status']??null,[403,404],true);
    }
    private static function runtimeLoopbackHttp(): array
    {
        $curl=curl_init(); self::need($curl!==false,'runtime_loopback_curl'); $received=0;
        try {
            $options=self::runtimeLoopbackOptions();
            $options[CURLOPT_WRITEFUNCTION]=static function($c,string $chunk) use (&$received): int {
                $received+=strlen($chunk); return $received<=1048576?strlen($chunk):0;
            };
            self::need(curl_setopt_array($curl,$options),'runtime_loopback_options');
            $ok=curl_exec($curl); $peer=curl_getinfo($curl,CURLINFO_PRIMARY_IP);
            return ['transport'=>'loopback-http','status'=>(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE),
                'peerVerified'=>is_string($peer) && $peer==='127.0.0.1',
                'curlErrorCode'=>$ok===false?max(1,curl_errno($curl)):curl_errno($curl)];
        } finally { curl_close($curl); }
    }
    private static function runtimeOriginResolve(string $ip): string
    {
        self::need(filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)!==false,'runtime_origin_public_ip');
        return 'architex.co.za:443:'.(str_contains($ip,':')?'['.$ip.']':$ip);
    }
    private static function runtimeOriginHttp(string $ip): int
    {
        $resolve=self::runtimeOriginResolve($ip);
        $curl=curl_init('https://architex.co.za/.a777-runtime-active/config/runtime.php');
        self::need($curl!==false,'runtime_origin_curl'); $received=0;
        try {
            curl_setopt_array($curl,[CURLOPT_RESOLVE=>[$resolve],CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,
                CURLOPT_PROXY=>'',CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
                CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>45,CURLOPT_HTTPHEADER=>[],
                // Discard all private-route body bytes: never retain/log configuration.
                CURLOPT_WRITEFUNCTION=>static function($c,string $chunk) use (&$received): int {
                    $received+=strlen($chunk); return $received<=1048576?strlen($chunk):0;
                }]);
            self::need(curl_exec($curl)!==false,'runtime_origin_transport');
            $peer=curl_getinfo($curl,CURLINFO_PRIMARY_IP);
            self::need(is_string($peer) && inet_pton($peer)===inet_pton($ip),'runtime_origin_peer');
            return (int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
        } finally { curl_close($curl); }
    }
    private static function http(string $path,string $origin=self::WEB,array $headers=[]): array
    {
        self::need(in_array($origin,[self::WEB,'https://architex.co.za'],true) && str_starts_with($path,'/') && !str_starts_with($path,'//'),'http_scope');
        $curl=curl_init($origin.$path); self::need($curl!==false,'http_curl'); $body=''; $received=[]; $receivedBytes=0;
        try {
            curl_setopt_array($curl,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,CURLOPT_PROXY=>'',CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>45,CURLOPT_HTTPHEADER=>$headers,
                CURLOPT_WRITEFUNCTION=>static function($c,string $b) use (&$body,&$receivedBytes,$path): int { $receivedBytes+=strlen($b); if ($receivedBytes>1048576) return 0; if ($path!=='/.a777-runtime-active/config/runtime.php') $body.=$b; return strlen($b); },
                CURLOPT_HEADERFUNCTION=>static function($c,string $b) use (&$received): int { $p=explode(':',$b,2); if(count($p)===2) $received[strtolower(trim($p[0]))]=trim($p[1]); return strlen($b); }]);
            self::need(curl_exec($curl)!==false,'http_transport'); return ['status'=>(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE),'body'=>$body,'headers'=>$received];
        } finally { curl_close($curl); }
    }
    private static function cronZero(): void
    {
        // Exit 1 with an actual native "no crontab for archite4" is known empty; every other error is unknown.
        $out=tmpfile(); $err=tmpfile(); self::need(is_resource($out)&&is_resource($err),'cron_inventory');
        try {
            $p=proc_open(['/usr/bin/crontab','-l'],[['pipe','r'],$out,$err],$pipes); self::need(is_resource($p),'cron_inventory'); fclose($pipes[0]); $exit=proc_close($p);
            rewind($out); rewind($err); $bytes=stream_get_contents($out); $errors=trim((string)stream_get_contents($err));
            if ($exit===1 && $bytes==='' && preg_match('/\Ano crontab for archite4\z/i',$errors)) return;
            self::need($exit===0 && $errors==='','cron_inventory_unknown');
            $rows=array_filter(explode("\n",$bytes),static fn($r)=>trim($r)!==''&&!str_starts_with(ltrim($r),'#'));
            self::need(count($rows)===0,'cron_nonzero');
        } finally { fclose($out); fclose($err); }
    }
    private static function workersZero(): void
    {
        self::need(is_readable('/proc'),'process_inventory_unknown'); $list=glob('/proc/[0-9]*'); self::need(is_array($list)&&$list!==[],'process_inventory_unknown');
        foreach ($list as $path) {
            $owner=@fileowner($path);
            if ($owner===false) { self::need(!is_dir($path),'process_inventory_unknown'); continue; }
            if ($owner!==posix_geteuid()) continue;
            $pid=(int)basename($path); if($pid===getmypid()) continue;
            $comm=@file_get_contents($path.'/comm'); self::need(is_string($comm),'process_inventory_unknown');
            self::need(!preg_match('/\A(?:php(?:[0-9.]*)?|php-fpm.*|lsphp.*)\z/',trim($comm)),'additional_php_workers');
        }
    }
    private static function observeGrants(AppConfig $config,array &$result): void
    {
        try {
            $read=\CpanelMaintenanceApi::target('get_privileges_on_database',['user'=>$config->dbUser,'database'=>$config->dbName])['data'];
            self::need(is_array($read) && array_is_list($read),'grants_readback_shape');
            $result['sourcePrivilegesRevoked']=$read===[]; $result['sourcePrivilegeStateUnknown']=false;
        } catch (Throwable) { $result['sourcePrivilegeStateUnknown']=true; }
    }
    private static function restoreGrants(AppConfig $config,array $privileges): void
    {
        self::need($privileges!==[] && array_is_list($privileges),'restore_privileges');
        try { \CpanelMaintenanceApi::target('set_privileges_on_database',['database'=>$config->dbName,'user'=>$config->dbUser,'privileges'=>implode(',',$privileges)]); } catch (Throwable) {}
        $read=\CpanelMaintenanceApi::target('get_privileges_on_database',['database'=>$config->dbName,'user'=>$config->dbUser])['data'];
        self::need(is_array($read),'restore_privileges_readback'); sort($read); sort($privileges); self::need($read===$privileges,'restore_privileges_readback');
        ConnectionFactory::create($config)->query('SELECT * FROM schema_migrations LIMIT 1');
    }
    private static function ready(PDO $pdo,string $root,array $values): void
    {
        $mail=\Architex\Infrastructure\Mail\MailRuntime::fromEnvironment($pdo,$values);
        $controller=new \Architex\Http\Controller\ReadinessController(
            static fn(): bool=>(int)$pdo->query('SELECT 1')->fetchColumn()===1,
            static fn(): bool=>(new MigrationRunner($pdo,$root.'/database/migrations'))->status()->isCurrent(),
            static fn(): bool=>(int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('scheduled_jobs','scheduled_job_deduplication_keys')")->fetchColumn()===2,
            static fn(): bool=>$mail->isReady());
        $response=$controller(\Architex\Http\Request::fromGlobals());
        self::httpReady(['status'=>$response->status(),'body'=>$response->body()]);
    }
    private static function httpReady(array $response): void
    {
        self::need($response['status']===200,'readiness_http'); $json=json_decode($response['body'],true,32,JSON_THROW_ON_ERROR); $data=$json['data']??null;
        self::need(is_array($data)&&($data['status']??null)==='ready','readiness_status');
        foreach (['database','migrations','jobs','mail'] as $name) self::need(($data['components'][$name]['status']??null)==='ready','readiness_component');
    }
    private static function httpBuild(array $response,string $commit): void
    {
        self::need($response['status']===200,'build_http'); $json=json_decode($response['body'],true,32,JSON_THROW_ON_ERROR);
        self::need(($json['sourceCommit']??null)===$commit && ($json['sourceDirty']??null)===false && ($json['environment']??null)==='production','build_identity');
    }
    public static function publicShim(string $relative): string
    {
        self::need(preg_match('#\A[A-Za-z0-9_.-]+(?:/[A-Za-z0-9_.-]+)*\z#D',$relative)===1 && !in_array('..',explode('/',$relative),true),'public_shim_path');
        // require preserves REQUEST_URI and executes the original file with its original __DIR__.
        return "<?php\ndeclare(strict_types=1);\nrequire ".var_export(self::RUNTIME.'/public/'.$relative,true).";\n";
    }
    private static function publishPublic(string $source,string $target): void
    {
        foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::SELF_FIRST) as $entry) {
            self::canonical($entry->getPathname()); $name=substr($entry->getPathname(),strlen($source)+1);
            if ($name==='.htaccess') continue; $to=$target.'/'.$name;
            if ($entry->isDir()) self::need(mkdir($to,0755),'public_directory');
            elseif (preg_match('/\.(?:php[0-9]*|phtml|phar)\z/i',$name)) self::put($to,self::publicShim($name),0644);
            else self::need(copy($entry->getPathname(),$to) && chmod($to,0644) && hash_file('sha256',$to)===hash_file('sha256',$entry->getPathname()),'public_copy_readback');
        }
    }
    private static function webPoolProbe(string $work,string $lease,string $commit,string $journal): void
    {
        $secret=bin2hex(random_bytes(32)); $name='native-probe-'.bin2hex(random_bytes(24)).'.php'; $path=self::DOCROOT.'/'.$name;
        $proof=$work.'/web-proof.json'; $gate=self::gateBytes($lease);
        $code="<?php\ndeclare(strict_types=1);\nif (!hash_equals(".var_export($secret,true).", (string)(\$_SERVER['HTTP_X_A777_NATIVE_PROBE']??'')) || !hash_equals(".var_export(hash('sha256',$gate),true).", (string)hash_file('sha256',__DIR__.'/.htaccess'))) { http_response_code(503); exit; }\n";
        $code.="if (!function_exists('opcache_invalidate')) { http_response_code(503); exit; }\n\$root=".var_export(self::RUNTIME,true)."; \$failed=[]; foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(\$root,FilesystemIterator::SKIP_DOTS)) as \$f) if(\$f->isFile()&&str_ends_with(\$f->getFilename(),'.php')&&function_exists('opcache_is_script_cached')&&opcache_is_script_cached(\$f->getPathname())&&!opcache_invalidate(\$f->getPathname(),true)) \$failed[]=true;\nif(\$failed!==[]) { http_response_code(503); exit; }\n\$_SERVER['REQUEST_URI']='/api/v1/readiness'; \$_SERVER['REQUEST_METHOD']='GET'; ob_start(); require \$root.'/public/index.php'; \$body=ob_get_clean(); \$proof=['status'=>http_response_code(),'body'=>\$body,'build'=>json_decode(file_get_contents(\$root.'/public/build-info.json'),true),'webSapi'=>PHP_SAPI]; file_put_contents(".var_export($proof,true).",json_encode(\$proof,JSON_THROW_ON_ERROR),LOCK_EX); chmod(".var_export($proof,true).",0600); header('Content-Type: application/json'); echo json_encode(\$proof,JSON_THROW_ON_ERROR);\n";
        self::append($journal,['phase'=>'probe-create-intent','path'=>$path,'sha256'=>hash('sha256',$code)]); self::put($path,$code,0600);
        $rule="RewriteCond %{HTTP:X-A777-Native-Probe} ^$secret$\nRewriteRule ^".str_replace('.','\\.',$name)."$ - [L]\n";
        $temporary=str_replace("RewriteRule ^ - [R=503,L]",$rule."RewriteRule ^ - [R=503,L]",$gate);
        self::replaceOwned(self::DOCROOT.'/.htaccess',$gate,$temporary,0644,$journal);
        // Authentication reads the exact temporary lease hash, not any application-wide bypass.
        $newCode=str_replace(hash('sha256',$gate),hash('sha256',$temporary),$code);
        self::replaceOwned($path,$code,$newCode,0600,$journal);
        $primary=null;
        try {
            $r=self::http('/'.$name,self::WEB,['X-A777-Native-Probe: '.$secret]); self::need($r['status']===200,'web_pool_probe');
            $p=json_decode($r['body'],true,32,JSON_THROW_ON_ERROR); self::need(is_array($p)&&($p['webSapi']??'cli')!=='cli','web_pool_identity'); self::httpReady($p);
            self::httpBuild(['status'=>200,'body'=>json_encode($p['build'],JSON_THROW_ON_ERROR)],$commit);
            self::privateFile($proof); self::need(json_decode(file_get_contents($proof),true,32,JSON_THROW_ON_ERROR)===$p,'web_pool_readback');
        } catch (Throwable $error) { $primary=$error; throw $error; } finally {
            try {
                self::replaceOwned(self::DOCROOT.'/.htaccess',$temporary,$gate,0644,$journal);
                self::need(file_get_contents($path)===$newCode && unlink($path),'probe_cleanup'); clearstatcache(true,$path); self::need(!file_exists($path),'probe_absence');
            } catch (Throwable $cleanup) { if ($primary===null) throw $cleanup; }
        }
    }
}
