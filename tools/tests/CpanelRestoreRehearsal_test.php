<?php
declare(strict_types=1);
// Synthetic reflection evidence only: NO authenticated provider or production database proof.
require dirname(__DIR__, 2) . '/app/src/Infrastructure/Config/AppConfig.php';
require dirname(__DIR__) . '/CpanelRestoreRehearsal.php';
use Architex\Deployment\CpanelRestoreRehearsal;
use Architex\Infrastructure\Config\AppConfig;
$n=0;
function check(bool $ok): void { global $n; if (!$ok) throw new RuntimeException('Synthetic assertion failed.'); $n++; }
function refused(Closure $fn): void { try { $fn(); } catch (RuntimeException) { check(true); return; } throw new RuntimeException('Expected refusal.'); }
$r=new ReflectionClass(CpanelRestoreRehearsal::class);
check($r->isFinal());
check($r->getMethod('run')->isStatic());
$equal=$r->getMethod('assertSnapshotEquality');
$s=['database'=>['name'=>'archite4_dev_rel1','migrations'=>['0001_base'],'tables'=>['records'=>['entry'=>'database/records.jsonl','rows'=>2]]],
    'entries'=>['database/records.jsonl'=>['sha256'=>str_repeat('a',64),'bytes'=>20]],'files'=>[]];
$t=$s; $t['database']['name']='archite4_ar_0123456789abcdef_test';
$invoke=static fn(array $a,array $b)=>$equal->invoke(null,$a,$b,'archite4_dev_rel1','archite4_ar_0123456789abcdef_test');
$invoke($s,$t); check(true);
foreach (['row','hash','bytes','files','migration','missing-table','extra-table','source','target'] as $fault) {
    $bad=$t;
    switch ($fault) {
        case 'row': $bad['database']['tables']['records']['rows']=1; break;
        case 'hash': $bad['entries']['database/records.jsonl']['sha256']=str_repeat('b',64); break;
        case 'bytes': $bad['entries']['database/records.jsonl']['bytes']=21; break;
        case 'files': $bad['files']=[['sha256'=>'changed']]; break;
        case 'migration': $bad['database']['migrations'][]='0002_candidate'; break;
        case 'missing-table': $bad['database']['tables']=[]; break;
        case 'extra-table': $bad['database']['tables']['extra']=['rows'=>0]; break;
        case 'source': $bad['database']['name']='archite4_dev_rel1'; break;
        case 'target': $bad['database']['name']='other'; break;
    }
    refused(static fn()=>$invoke($s,$bad));
}
refused(static fn()=>$equal->invoke(null,$s,$s,'archite4_dev_rel1','archite4_dev_rel1'));
$within=$r->getMethod('within');
check($within->invoke(null,'/private/recovery','/private'));
check(!$within->invoke(null,'/private-other/recovery','/private'));
check($within->invoke(null,'/private','/private'));
$config=AppConfig::fromEnvironment(['APP_ENV'=>'production','DB_HOST'=>'db.example.test','DB_PORT'=>'3307','DB_NAME'=>'archite4_dev_rel1','DB_USER'=>'archite4_synthetic','DB_PASSWORD'=>'synthetic-not-a-real-credential']);
$pdo=new class extends PDO { public function __construct() {} public function query(string $query, ?int $fetchMode=null, mixed ...$fetchModeArgs): PDOStatement|false { throw new RuntimeException('Source must not be used before guards.'); } };
foreach (['stage','restore','promotion','restore-rehearsal'] as $mode) {
    $out=CpanelRestoreRehearsal::run($config,$pdo,'/synthetic-storage',str_repeat('a',64),$mode==='restore-rehearsal'?'bad-commit':str_repeat('a',40),$mode,'/synthetic-repo');
    check($out['status']==='failed'); check($out['sourceDatabaseDdlTouched']===false);
    check($out['liveFilesTouched']===false); check($out['targetDatabaseDdlTouched']===false);
    check($out['isolatedRehearsalPassed']===false);
    check($out['failureStage']==='plan' && $out['knownReason']==='plan_refused');
    check($out['failureFile']==='CpanelRestoreRehearsal.php' && $out['failureFunction']==='run' && is_int($out['failureLine']));
    check(!str_contains(json_encode($out,JSON_THROW_ON_ERROR),'synthetic-not-a-real-credential'));
}
// The valid mode still cannot substitute a mocked host or bypass Linux/account checks.
if (PHP_OS_FAMILY !== 'Linux' || !function_exists('posix_geteuid') || (posix_getpwuid(posix_geteuid())['name']??'')!=='archite4') {
    $out=CpanelRestoreRehearsal::run($config,$pdo,'/synthetic-storage',str_repeat('a',64),str_repeat('a',40),'restore-rehearsal','/synthetic-repo');
    check($out['status']==='failed' && !$out['targetDatabaseDdlTouched']);
}
$failure=$r->getMethod('failure');
foreach (['SELECT password FROM users', '/private/secret credential', 'Private directory required.'] as $secret) {
    $diagnostic=$failure->invoke(null,new RuntimeException($secret),'repository');
    check($diagnostic['knownReason']==='unclassified');
    check(!str_contains(json_encode($diagnostic,JSON_THROW_ON_ERROR),$secret));
    check(!isset($diagnostic['failureFile']) && !isset($diagnostic['failureLine']));
}
$metadata=$r->getMethod('metadata')->invoke(null,__DIR__,__DIR__);
check(!str_contains(json_encode($metadata,JSON_THROW_ON_ERROR),__DIR__));
check(in_array($metadata['fixedGitBinaryAvailability'],['available','unavailable'],true));
$directory=$r->getMethod('sourceDirectoryStat');
foreach ([0700=>true,0750=>false,0755=>false] as $mode=>$private) check($directory->invoke(null,['mode'=>0040000|$mode,'uid'=>123],123)===$private);
foreach ([0770,0775,0777,0720,0702,01700,02755,0644] as $mode) refused(static fn()=>$directory->invoke(null,['mode'=>0040000|$mode,'uid'=>123],123));
refused(static fn()=>$directory->invoke(null,['mode'=>0040755,'uid'=>124],123));
refused(static fn()=>$directory->invoke(null,['mode'=>0120755,'uid'=>123],123));
refused(static fn()=>$directory->invoke(null,false,123));
$location=$r->getMethod('sourceLocation');
foreach (['/home/archite4/public_html','/home/archite4/public_html/storage'] as $path) refused(static fn()=>$location->invoke(null,$path));
$location->invoke(null,'/home/archite4/public_html-other/storage'); check(true);
$ancestor=$r->getMethod('ancestorDirectoryStat');
foreach ([0,123] as $uid) { $ancestor->invoke(null,['mode'=>0040755,'uid'=>$uid],123); check(true); }
foreach ([['mode'=>0040777,'uid'=>123],['mode'=>0040755,'uid'=>124],['mode'=>0120755,'uid'=>123]] as $stat) refused(static fn()=>$ancestor->invoke(null,$stat,123));
$identity=$r->getMethod('assertSourceIdentity');
$inventory=['/source'=>['dev'=>1,'ino'=>2,'mode'=>0040755,'uid'=>123,'nlink'=>2,'size'=>4096]];
$identity->invoke(null,$inventory,$inventory); check(true);
foreach (['ino','mode','uid','nlink','size','dev'] as $field) { $changed=$inventory; $changed['/source'][$field]++; refused(static fn()=>$identity->invoke(null,$inventory,$changed)); }
$changed=$inventory; $changed['/source/new']=['sha256'=>'new']; refused(static fn()=>$identity->invoke(null,$inventory,$changed));
$inventory['/source/file']=['sha256'=>str_repeat('a',64),'size'=>20];
$changed=$inventory; $changed['/source/file']['sha256']=str_repeat('b',64); refused(static fn()=>$identity->invoke(null,$inventory,$changed));
printf("Synthetic reflection/caller-contract assertions: %d passed; provider host authentication NOT exercised.\n",$n);
