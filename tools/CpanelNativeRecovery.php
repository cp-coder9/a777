<?php
declare(strict_types=1);
namespace Architex\Deployment;
use Architex\Infrastructure\Config\AppConfig;
use Architex\Infrastructure\Database\{ConnectionFactory,MigrationRunner};
use RuntimeException;
use Throwable;
require_once __DIR__.'/CpanelNativeDeployment.php';

/** Explicit native-recover entry. Caller supplies private plan/old receipt, never secrets. */
final class CpanelNativeRecovery
{
    private const ROOT='/home/archite4/.a777-deploy';
    private const RUNTIME='/home/archite4/public_html/.a777-runtime-active';
    private const PUBLIC='/home/archite4/public_html/php.architex.co.za';
    private const REPO='/home/archite4/repositories/a777';
    private const OLD='6f21a24c6fc7bca06b14d4d48d352d12135a2f9f';
    private const ID='7eee8a25050fadb3d36b74c40f485061';
    private const PIN='5206e3a1f9447e6834fc3e2dd7cd254ce5dd47af33406c0c83085c4b4f28d0b8';
    private const BACKUP='5b9e44eb47a24c7f0d47d11609e8c53564d54971379fe8d91fd7da5bb71ed87e';
    private static function need(bool $ok): void { if (!$ok) throw new RuntimeException('recovery_evidence_refused'); }
    public static function validateReceipt(array $r,string $planSha): void
    {
        self::need(($r['status']??null)==='recovery_required' && ($r['lastCompletedStage']??null)==='sourceDDL'
            && ($r['failureStage']??null)==='promotion' && ($r['sourceMigrationCount']??null)===8
            && ($r['sourceDatabaseDdlTouched']??null)===true && ($r['operatorPrivilegesRevoked']??null)===true
            && ($r['operatorStateUnknown']??null)===false && ($r['maintenanceRetained']??null)===true
            && ($r['commit']??null)===self::OLD && ($r['deploymentId']??null)===self::ID
            && ($r['archiveSha256']??null)===self::BACKUP && ($r['planSha256']??null)===$planSha);
    }
    /** Pure inventory comparator: exactly the old artifact plus two owned runtime controls. */
    public static function compareInventory(array $expected,array $actual): void
    {
        unset($actual['config/runtime.php'],$actual['.htaccess']); ksort($expected,SORT_STRING); ksort($actual,SORT_STRING);
        self::need($expected===$actual);
    }
    public static function run(string $privatePlan,string $privateReceipt,string $expectedApprovedRecoveryGitSHA,string $expectedOldPlanSHA256): array
    {
        $result=['status'=>'recovery_required','sourceMigrationsReplayed'=>false,'maintenanceRetained'=>true,'protectedTokenRetained'=>true];
        $mask=umask(0077); $h=[]; $lock=null; $journal=null; $normal=null; $gate=null;
        try {
            $h=CpanelNativeDeployment::recoveryHelpers($expectedApprovedRecoveryGitSHA);
            self::need(preg_match('/\A[a-f0-9]{64}\z/',$expectedOldPlanSHA256)===1);
            foreach ([$privatePlan,$privateReceipt] as $path) {
                self::need(dirname($path)===self::ROOT); $h['canonical']($path); $s=lstat($path);
                self::need(is_array($s) && ($s['mode']&0170000)===0100000 && in_array($s['mode']&07777,[0400,0600],true) && $s['uid']===2657 && $s['nlink']===1);
            }
            // Plans are operator-readable only. The backend does not invent a new approval.
            $planBytes=file_get_contents($privatePlan); self::need(hash('sha256',$planBytes)===$expectedOldPlanSHA256);
            $p=json_decode($planBytes,true,32,JSON_THROW_ON_ERROR);
            $r=json_decode(file_get_contents($privateReceipt),true,32,JSON_THROW_ON_ERROR);
            self::validateReceipt($r,$expectedOldPlanSHA256);
            self::need(($p['mode']??null)==='native-deploy' && ($p['expectedCommit']??null)===self::OLD
                && ($p['runtimeConfigSha256']??null)===self::PIN && ($p['account']??null)==='archite4'
                && ($p['activeRoot']??null)===self::RUNTIME && ($p['documentRoot']??null)===self::PUBLIC
                && ($p['sourceDatabase']??null)==='archite4_dev_rel1');
            $lockPath=self::ROOT.'/native-activation.lock'; $h['privateFile']($lockPath); $lock=fopen($lockPath,'r+b');
            self::need(is_resource($lock) && fstat($lock)['ino']===lstat($lockPath)['ino'] && flock($lock,LOCK_EX|LOCK_NB));
            $work=self::ROOT.'/deployment-'.self::ID; $h['privateDirectory']($work);
            $journal=$work.'/recovery-journal.jsonl';
            if (!file_exists($journal)) $h['append']($journal,['phase'=>'recovery-intent','oldReleaseSHA'=>self::OLD,'approvedRecoveryGitSHA'=>$expectedApprovedRecoveryGitSHA,'planSha256'=>$expectedOldPlanSHA256],true);
            else $h['privateFile']($journal);
            $oldJournal=$work.'/journal.jsonl'; $h['privateFile']($oldJournal);
            // Never export this password-bearing journal. Select only exact owned state locally.
            $records=[]; foreach (file($oldJournal,FILE_IGNORE_NEW_LINES) as $line) $records[]=json_decode($line,true,64,JSON_THROW_ON_ERROR);
            $intent=$records[0]??[]; self::need(($intent['commit']??null)===self::OLD && ($intent['planSha256']??null)===$expectedOldPlanSHA256 && ($intent['deploymentId']??null)===self::ID);
            $lease=null; $privileges=null; $operator=null; $ddlProof=false;
            foreach ($records as $record) {
                if (($record['phase']??null)==='apache-intent' && ($record['path']??null)===self::PUBLIC.'/.htaccess') {
                    self::need(preg_match('/X-A777-Deployment-Lease "([a-f0-9]{48})"/',$record['installed']??'',$m)===1); $lease=$m[1];
                }
                if (($record['phase']??null)==='privileges-snapshot') $privileges=$record['privileges']??null;
                if (($record['phase']??null)==='sourceDDL') $ddlProof=($record['sourceMigrationCount']??null)===8;
                if (isset($record['name']) && preg_match('/\Aarchite4_nd_[a-f0-9]{16}\z/',$record['name'])) $operator=$record['name'];
            }
            self::need(is_string($lease) && $ddlProof); $gate=$h['gate']($lease);
            $h['privateFile']($work.'/original-runtime.php'); self::need(hash_file('sha256',$work.'/original-runtime.php')===self::PIN);
            $original=require $work.'/original-runtime.php'; $new=$original; $new['FILE_STORAGE_PATH']='/home/archite4/.a777-storage/'.self::ID;
            $artifact=$p['artifactPath']??''; self::need(is_string($artifact) && str_starts_with($artifact,self::ROOT.'/') && !str_contains($artifact,'..')); $h['privateDirectory']($artifact);
            $expected=$h['inventory']($artifact); self::need($h['digest']($expected)===($p['treeDigest']??null)
                && hash_file('sha256',$artifact.'/release-manifest.json')===($r['artifactManifestSha256']??null)
                && hash_file('sha256',$artifact.'/release.json')===($r['buildSha256']??null)
                && $h['digest']($expected)===($r['artifactTreeSha256']??null));
            $h['privateFile']($work.'/source.axbackup'); self::need(hash_file('sha256',$work.'/source.axbackup')===self::BACKUP);
            // Freeze future autoloads too, not merely classes already loaded.
            foreach ($expected as $name=>$entry) if (str_starts_with($name,'src/') || str_starts_with($name,'vendor/') || $name==='bootstrap.php') {
                // cPanel Git has no Composer vendor: dependency bytes come only from the verified artifact.
                $path=str_starts_with($name,'vendor/')?$artifact.'/'.$name:self::REPO.'/app/'.$name;
                $h['canonical']($path); self::need(hash_file('sha256',$path)===$entry['sha256']);
            }
            self::need((new \Architex\Infrastructure\Deployment\ReleaseVerifier())->verify($artifact)->valid);
            // Every loaded application class must still be exactly the OLD release's bytes.
            foreach (get_declared_classes() as $class) if (str_starts_with($class,'Architex\\') && !str_starts_with($class,__NAMESPACE__.'\\')) {
                $file=(new \ReflectionClass($class))->getFileName(); self::need(is_string($file) && str_starts_with($file,self::REPO.'/app/'));
                $relative=substr($file,strlen(self::REPO.'/app/') ); self::need(isset($expected[$relative]) && hash_file('sha256',$file)===$expected[$relative]['sha256']);
            }
            require_once $artifact.'/vendor/autoload.php';
            // Composer prepends its loader; restore the reviewed, byte-matched repository App loader first.
            spl_autoload_register(static function(string $class): void {
                if (str_starts_with($class,'Architex\\')) {
                    $path=self::REPO.'/app/src/'.str_replace('\\','/',substr($class,9)).'.php';
                    if (is_file($path)) require_once $path;
                }
            },true,true);
            foreach (get_declared_classes() as $class) {
                $file=(new \ReflectionClass($class))->getFileName();
                if (is_string($file) && str_contains($file,'/vendor/')) {
                    self::need(str_starts_with($file,$artifact.'/vendor/'));
                    $relative=substr($file,strlen($artifact)+1);
                    self::need(isset($expected[$relative]) && hash_file('sha256',$file)===$expected[$relative]['sha256']);
                }
            }
            $candidate=$work.'/candidate'; $public=self::PUBLIC.'.candidate-'.self::ID;
            // Only a sequential rename prefix can be resumed. No guessing from directory names alone.
            $state=self::renameState(is_dir($candidate),is_dir(self::RUNTIME),is_dir($work.'/old-runtime'),is_dir($public),is_dir(self::PUBLIC),is_dir($work.'/old-docroot'));
            $runtime=$state<2?$candidate:self::RUNTIME;
            $h['privateTree']($runtime); $actual=self::runtimeInventory($runtime); self::compareInventory($expected,$actual);
            self::need(file_get_contents($runtime.'/config/runtime.php')===CpanelNativeDeployment::rewriteStorage((string)file_get_contents($work.'/original-runtime.php'),$new['FILE_STORAGE_PATH']));
            self::need((require $runtime.'/config/runtime.php')===$new && file_get_contents($runtime.'/.htaccess')==="Require all denied\n");
            if ($state<1) self::need(hash_file('sha256',self::RUNTIME.'/config/runtime.php')===self::PIN);
            if ($state>=1) self::need(hash_file('sha256',$work.'/old-runtime/config/runtime.php')===self::PIN);
            $h['privateTree']($new['FILE_STORAGE_PATH']);
            self::need(self::storageHashes($original['FILE_STORAGE_PATH'])===self::storageHashes($new['FILE_STORAGE_PATH']));
            $h['storageInventory']($original['FILE_STORAGE_PATH'],true);
            $publicRoot=$state<4?$public:self::PUBLIC; $h['canonical']($publicRoot);
            $publicOwned=false;
            foreach ($records as $record) if (($record['phase']??null)==='public-candidate-create-intent' && ($record['path']??null)===$public) $publicOwned=true;
            self::verifyPublic($runtime.'/public',$publicRoot,$gate,$h,$journal,$state<4 && $publicOwned);
            self::need(file_get_contents(($state<3?self::PUBLIC:$work.'/old-docroot').'/.htaccess')===$gate);
            // Provider proof of the already-revoked operator, never create/regrant it.
            if ($operator===null) {
                foreach ($records as $record) if (isset($record['operator']['user'])) $operator=$record['operator']['user'];
            }
            self::need(is_string($operator) && preg_match('/\Aarchite4_nd_[a-f0-9]{16}\z/',$operator)===1);
            self::need(\CpanelMaintenanceApi::operator('get_privileges_on_database',['user'=>$operator,'database'=>'archite4_dev_rel1'])===[]);
            $h['cron'](); $h['workers']();
            if ($state!==3) foreach (['/','/index.php','/api/v1/readiness','/build-info.json'] as $uri) {
                $observed=$h['http']($uri); self::need($observed['status']===503 && ($observed['headers']['x-a777-deployment-lease']??null)===$lease);
            }
            $fencePath=$work.'/fence.json'; $h['privateFile']($fencePath); $fence=json_decode(file_get_contents($fencePath),true,64,JSON_THROW_ON_ERROR);
            self::need(($fence['database']??null)==='archite4_dev_rel1' && ($fence['configSha256']??null)===self::PIN && ($fence['user']??null)===$original['DB_USER']);
            self::need(is_array($privileges) && $privileges!==[] && is_array($fence['originalPrivileges']??null));
            $fp=$fence['originalPrivileges']; sort($fp,SORT_STRING); sort($privileges,SORT_STRING); self::need($fp===$privileges);
            $config=AppConfig::fromEnvironment($new);
            $read=\CpanelMaintenanceApi::target('get_privileges_on_database',['database'=>'archite4_dev_rel1','user'=>$config->dbUser])['data'];
            $a=$read; $b=$privileges; self::need(is_array($a)); sort($a); sort($b); self::need($a===[] || $a===$b);
            // Exact snapshot restore is necessary to obtain a fresh read-only status connection.
            $h['append']($journal,['phase'=>'recovery-restore-grants-intent','privileges'=>$privileges]);
            $h['restoreGrants']($config,$privileges);
            $h['append']($journal,['phase'=>'recovery-grants-readback']);
            $pdo=ConnectionFactory::create($config); self::need($pdo->query('SELECT DATABASE()')->fetchColumn()==='archite4_dev_rel1');
            $status=(new MigrationRunner($pdo,$runtime.'/database/migrations'))->status();
            self::need($status->isCurrent() && count(glob($runtime.'/database/migrations/*.sql'))===69 && (int)$pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn()===69);
            $priorIds=$p['pendingMigrations']??null;
            self::need(is_array($priorIds) && count($priorIds)===8 && count(array_unique($priorIds))===8);
            $applied=$pdo->query('SELECT version, checksum FROM schema_migrations')->fetchAll(\PDO::FETCH_KEY_PAIR);
            foreach ($priorIds as $version) {
                self::need(is_string($version) && preg_match('/\A[0-9]{4}_[a-z0-9_]+\z/',$version)===1);
                self::need(isset($applied[$version]) && hash_file('sha256',$runtime.'/database/migrations/'.$version.'.sql')===$applied[$version]);
            }
            $h['ready']($pdo,$runtime,$new);
            $moves=[[self::RUNTIME,$work.'/old-runtime'],[$candidate,self::RUNTIME],[self::PUBLIC,$work.'/old-docroot'],[$public,self::PUBLIC]];
            foreach ($moves as $i=>[$from,$to]) if ($i>=$state) $h['rename']($from,$to,$journal);
            $h['proveGate']($lease,$gate);
            self::need((require self::RUNTIME.'/config/runtime.php')===$new);
            $h['ready']($pdo,self::RUNTIME,$new); $h['webProbe']($work,$lease,self::OLD,$journal);
            $normal=file_get_contents(self::RUNTIME.'/public/.htaccess'); self::need(is_string($normal) && $normal!=='' && !str_contains($normal,'a777-native-maintenance'));
            $h['append']($journal,['phase'=>'recovery-maintenance-release-intent','sha256'=>hash('sha256',$normal)]);
            $h['replace'](self::PUBLIC.'/.htaccess',$gate,$normal,0644,$journal);
            $h['httpReady']($h['http']('/api/v1/readiness')); $h['httpBuild']($h['http']('/build-info.json'),self::OLD);
            foreach (['/api.php','/maintenance.php'] as $entry) if (is_file(self::RUNTIME.'/public'.$entry)) self::need(in_array($h['http']($entry)['status'],[200,401,403,404],true));
            self::need((new MigrationRunner($pdo,self::RUNTIME.'/database/migrations'))->status()->isCurrent() && hash_file('sha256',$work.'/source.axbackup')===self::BACKUP);
            $h['httpReady']($h['http']('/api/v1/readiness'));
            $result+=['oldReleaseSHA'=>self::OLD,'approvedRecoveryGitSHA'=>$expectedApprovedRecoveryGitSHA,'planSha256'=>$expectedOldPlanSHA256,'deploymentId'=>self::ID];
            foreach (['backupId','archiveSha256','artifactTreeSha256','artifactManifestSha256','buildSha256'] as $key) $result[$key]=$r[$key];
            $result['sourceAlreadyCurrent']=true; $result['sourceAppliedPriorCount']=8;
            $result['appPrivilegesRestored']=true; $result['operatorPrivilegesRevoked']=true;
            $result['operatorStateUnknown']=false; $result['sourcePrivilegeStateUnknown']=false;
            $result['appConfigDeltaVerified']=true; $result['readinessPassed']=true; $result['artifactVerified']=true;
            $result['status']='recovered_and_deployed'; $result['maintenanceRetained']=false;
            $h['append']($journal,['phase'=>'recovery-complete']+$result);
        } catch (Throwable) {
            // Never report exception text, journal content, credentials or SQL.
            $result['status']='recovery_required'; $result['maintenanceRetained']=true;
            $result['failureCode']='recovery_evidence_or_operation_refused';
            if (isset($h['replace']) && is_string($gate) && is_file(self::PUBLIC.'/.htaccess')) {
                try { $current=file_get_contents(self::PUBLIC.'/.htaccess'); if ($current!==$gate && ($current===$normal || str_contains($current,'# a777-native-maintenance'))) $h['replace'](self::PUBLIC.'/.htaccess',$current,$gate,0644,$journal); } catch (Throwable) { $result['maintenanceStateUnknown']=true; }
            }
        } finally { if (is_resource($lock)) { flock($lock,LOCK_UN); fclose($lock); } umask($mask); }
        return $result;
    }
    public static function renameState(bool $candidate,bool $active,bool $oldRuntime,bool $publicCandidate,bool $public,bool $oldPublic): int
    {
        $states=[[true,true,false,true,true,false],[true,false,true,true,true,false],[false,true,true,true,true,false],[false,true,true,true,false,true],[false,true,true,false,true,true]];
        $i=array_search([$candidate,$active,$oldRuntime,$publicCandidate,$public,$oldPublic],$states,true); self::need($i!==false); return $i;
    }
    private static function runtimeInventory(string $root): array
    {
        $out=[]; foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root,\FilesystemIterator::SKIP_DOTS)) as $f) {
            self::need(!$f->isLink() && $f->isFile()); $out[substr($f->getPathname(),strlen($root)+1)]=['sha256'=>hash_file('sha256',$f->getPathname()),'bytes'=>$f->getSize()];
        } ksort($out,SORT_STRING); return $out;
    }
    private static function storageHashes(string $root): array { $out=self::runtimeInventory($root); ksort($out,SORT_STRING); return $out; }
    private static function verifyPublic(string $source,string $target,string $gate,array $h,string $journal,bool $completeOwnedPartial=false): void
    {
        $expected=[];
        foreach (self::runtimeInventory($source) as $name=>$info) {
            if ($name==='.htaccess') continue;
            $bytes=preg_match('/\.(?:php[0-9]*|phtml|phar)\z/i',$name)?CpanelNativeDeployment::publicShim($name):file_get_contents($source.'/'.$name);
            $expected[$name]=['sha256'=>hash('sha256',$bytes),'bytes'=>strlen($bytes)];
        }
        $actual=self::runtimeInventory($target); unset($actual['.htaccess']);
        self::need(file_get_contents($target.'/.htaccess')===$gate);
        foreach ($actual as $name=>$info) self::need(isset($expected[$name]) && $expected[$name]===$info);
        if ($actual!==$expected && $completeOwnedPartial) {
            $s=lstat($target); self::need($s['uid']===posix_geteuid() && ($s['mode']&0022)===0);
            foreach ($expected as $name=>$info) if (!isset($actual[$name])) {
                $path=$target.'/'.$name;
                self::need(!file_exists($path) && !is_link($path));
                if (!is_dir(dirname($path))) self::need(mkdir(dirname($path),0755,true));
                $h['canonical'](dirname($path));
                $bytes=preg_match('/\.(?:php[0-9]*|phtml|phar)\z/i',$name)?CpanelNativeDeployment::publicShim($name):file_get_contents($source.'/'.$name);
                self::need(hash('sha256',$bytes)===$info['sha256']);
                $h['append']($journal,['phase'=>'recovery-public-completion-intent','path'=>$path,'sha256'=>$info['sha256']]);
                $h['put']($path,$bytes,0644);
            }
            $actual=self::runtimeInventory($target); unset($actual['.htaccess']);
        }
        // Reuse exact matched shim; no second exclusive-create index write.
        ksort($expected,SORT_STRING); ksort($actual,SORT_STRING); self::need($actual===$expected && file_get_contents($target.'/.htaccess')===$gate);
        foreach (array_keys($expected) as $name) $h['canonical']($target.'/'.$name);
    }
}
