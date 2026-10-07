<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/CpanelNativeRecovery.php';
use Architex\Deployment\{CpanelPrivateRuntimeRelocation as R,CpanelNativeRecovery as Recovery,CpanelNativeDeployment as D};
$n=0;
function check(bool $ok): void { global $n; if (!$ok) throw new RuntimeException('fixture_assertion_failed'); ++$n; }
function refuse(callable $fn): void { try {$fn();} catch (Throwable) {check(true); return;} throw new RuntimeException('fixture_expected_refusal'); }
$approve=['privateRuntimeRelocation'=>true,'privateRuntimeDestination'=>R::DESTINATION];
R::approval($approve); check(true);
foreach ([[],['privateRuntimeRelocation'=>1,'privateRuntimeDestination'=>R::DESTINATION],['privateRuntimeRelocation'=>'true','privateRuntimeDestination'=>R::DESTINATION],['privateRuntimeRelocation'=>false,'privateRuntimeDestination'=>R::DESTINATION],['privateRuntimeRelocation'=>true,'privateRuntimeDestination'=>R::SOURCE],['privateRuntimeRelocation'=>true,'privateRuntimeDestination'=>R::DESTINATION.'/']] as $bad) refuse(fn()=>R::approval($bad));
check(str_contains(R::shim('index.php'),R::DESTINATION.'/public/index.php'));
check(!str_contains(R::shim('index.php'),R::SOURCE));
check(str_contains(R::shim('index.php'),"umask(0077);\nrequire "));
$code=['code.php'=>['sha256'=>'fixed','bytes'=>5]];
Recovery::compareInventory($code,$code+['storage/logs/application.jsonl'=>['sha256'=>'mutable','bytes'=>10]]); check(true);
Recovery::compareInventory($code+['storage/logs/application.jsonl'=>['sha256'=>'old','bytes'=>1]],$code); check(true);
refuse(fn()=>Recovery::compareInventory($code,$code+['storage/logs/other.jsonl'=>['sha256'=>'unknown','bytes'=>1]]));
refuse(fn()=>Recovery::compareInventory($code,['code.php'=>['sha256'=>'changed','bytes'=>5]]));
refuse(fn()=>R::shim('../index.php'));
refuse(fn()=>R::documentRoots(['main_domain'=>[]]));
$base=getenv('TMPDIR'); check(is_string($base) && is_dir($base));
$root=str_replace('\\','/',rtrim($base,'/\\')).'/a777-relocation-fixture-'.bin2hex(random_bytes(8));
check(mkdir($root,0700));
function putFixture(string $p,string $bytes,int $mode=0600): void { check(file_put_contents($p,$bytes)===strlen($bytes)); check(chmod($p,$mode)); }
function removeFixture(string $root): void {
    if (is_link($root) || is_file($root)) {unlink($root); return;}
    foreach (new FilesystemIterator($root,FilesystemIterator::SKIP_DOTS) as $f) removeFixture($f->getPathname()); rmdir($root);
}
$append=static function(string $path,array $record): void {
    $h=fopen($path,'ab'); if (!$h) throw new RuntimeException('fixture_journal');
    try { $b=json_encode($record,JSON_THROW_ON_ERROR)."\n"; check(fwrite($h,$b)===strlen($b)); check(fflush($h)); if (function_exists('fsync')) check(fsync($h)); }
    finally {fclose($h);} chmod($path,0600);
};
try {
    mkdir($root.'/runtime-public',0700); mkdir($root.'/web',0700);
    putFixture($root.'/runtime-public/.htaccess','normal'); putFixture($root.'/web/.htaccess','lease');
    foreach (['index.php','app.php'] as $name) {putFixture($root.'/runtime-public/'.$name,'<?php /* immutable application */'); putFixture($root.'/web/'.$name,D::publicShim($name));}
    putFixture($root.'/runtime-public/asset.txt','immutable static'); putFixture($root.'/web/asset.txt','immutable static');
    $replace=static function(string $p,string $old,string $new,int $mode,string $j) use ($append): void {
        check(file_get_contents($p)===$old); $append($j,['phase'=>'shim-intent','path'=>$p]); putFixture($p,$new,$mode);
    };
    $h=['canonical'=>static fn(string $p)=>check(str_replace('\\','/',(string)realpath($p))===str_replace('\\','/',$p)),'replace'=>$replace,'append'=>$append];
    $method=new ReflectionMethod(Recovery::class,'verifyRelocationPublic');
    $j=$root.'/journal.jsonl'; putFixture($j,'');
    $method->invoke(null,$root.'/runtime-public',$root.'/web','lease',$h,$j,false);
    putFixture($root.'/web/asset.txt','foreign');
    refuse(fn()=>$method->invoke(null,$root.'/runtime-public',$root.'/web','lease',$h,$j,true));
    check(file_get_contents($root.'/web/index.php')===D::publicShim('index.php'));
    putFixture($root.'/web/asset.txt','immutable static');
    putFixture($root.'/web/index.php','<?php /* foreign shim */');
    refuse(fn()=>$method->invoke(null,$root.'/runtime-public',$root.'/web','lease',$h,$j,true));
    check(file_get_contents($root.'/web/app.php')===D::publicShim('app.php'));
    // A previous interrupted update already replaced one exact known shim.
    putFixture($root.'/web/index.php',R::shim('index.php'));
    $method->invoke(null,$root.'/runtime-public',$root.'/web','lease',$h,$j,true);
    check(file_get_contents($root.'/web/app.php')===R::shim('app.php'));
    check(file_get_contents($root.'/web/asset.txt')==='immutable static');
    check(file_get_contents($root.'/web/.htaccess')==='lease');
    putFixture($root.'/web/index.php',R::legacyShim('index.php'));
    $method->invoke(null,$root.'/runtime-public',$root.'/web','lease',$h,$j,true);
    check(file_get_contents($root.'/web/index.php')===R::shim('index.php'));
    $method->invoke(null,$root.'/runtime-public',$root.'/web','lease',$h,$j,true);
    if (PHP_OS_FAMILY==='Linux' && function_exists('posix_geteuid')) {
        $uid=posix_geteuid(); $engine=new ReflectionMethod(R::class,'transition');
        $temp=$root.'/web/index.php.native-0123456789abcdef';
        putFixture($temp,R::shim('index.php'),0644);
        refuse(fn()=>$method->invoke(null,$root.'/runtime-public',$root.'/web','lease',$h,$root.'/journal.jsonl',true));
        check(file_exists($temp));
        $append($root.'/journal.jsonl',['phase'=>'replace-intent','path'=>$root.'/web/index.php','temporary'=>$temp,
            'beforeSha256'=>hash('sha256',D::publicShim('index.php')),'afterSha256'=>hash('sha256',R::shim('index.php'))]);
        $method->invoke(null,$root.'/runtime-public',$root.'/web','lease',$h,$root.'/journal.jsonl',true);
        check(!file_exists($temp));
        check(file_get_contents($root.'/web/index.php')===R::shim('index.php'));
        mkdir($root.'/public',0700); mkdir($root.'/public/active',0755); mkdir($root.'/public/active/config',0750);
        putFixture($root.'/public/active/config/runtime.php','synthetic config',0644);
        $src=$root.'/public/active'; $dst=$root.'/private/deployment'; $j=$root.'/relocation.jsonl'; putFixture($j,'');
        $ino=lstat($src)['ino']; $fileIno=lstat($src.'/config/runtime.php')['ino'];
        $engine->invoke(null,$src,$dst,$uid,[$root.'/public'],$h,$j);
        clearstatcache(true); check(!file_exists($src)); check(lstat($dst)['ino']===$ino); check(lstat($dst.'/config/runtime.php')['ino']===$fileIno);
        check((lstat($dst)['mode']&07777)===0700); check((lstat(dirname($dst))['mode']&07777)===0700); check((lstat($dst.'/config')['mode']&07777)===0700); check((lstat($dst.'/config/runtime.php')['mode']&07777)===0600);
        check(file_get_contents($dst.'/config/runtime.php')==='synthetic config');
        $engine->invoke(null,$src,$dst,$uid,[$root.'/public'],$h,$j); check(true);
        putFixture($dst.'/config/runtime.php','tampered'); refuse(fn()=>$engine->invoke(null,$src,$dst,$uid,[$root.'/public'],$h,$j));
        // Lost reply after rename: journal is durable but completion/modes are not.
        mkdir($root.'/public/next',0755); putFixture($root.'/public/next/file','original',0644);
        $src2=$root.'/public/next'; $dst2=$root.'/private2/deployment'; $j2=$root.'/lost.jsonl'; putFixture($j2,'');
        $snap=(new ReflectionMethod(R::class,'snapshot'))->invoke(null,$src2,$uid);
        $append($j2,['phase'=>'private-runtime-rename-intent','source'=>$src2,'destination'=>$dst2,'inventory'=>$snap]);
        mkdir(dirname($dst2),0700); check(rename($src2,$dst2));
        $engine->invoke(null,$src2,$dst2,$uid,[$root.'/public'],$h,$j2); check((lstat($dst2.'/file')['mode']&07777)===0600);
        refuse(fn()=>$engine->invoke(null,$src2,$dst2,$uid,[$root.'/private2'],$h,$j2));
        $j3=$root.'/foreign.jsonl'; putFixture($j3,''); refuse(fn()=>$engine->invoke(null,$src2,$dst2,$uid,[$root.'/public'],$h,$j3));
        mkdir($root.'/public/linked',0700); check(symlink($dst2.'/file',$root.'/public/linked/file'));
        refuse(fn()=>$engine->invoke(null,$root.'/public/linked',$root.'/private3/deployment',$uid,[$root.'/public'],$h,$j3));
        // Retry after a web request creates, appends, deletes, or recreates the ONE log.
        mkdir($root.'/public/logretry',0700);
        putFixture($root.'/public/logretry/code.php','immutable');
        $ls=$root.'/public/logretry'; $ld=$root.'/logprivate/runtime'; $lj=$root.'/logretry.jsonl'; putFixture($lj,'');
        $snap=(new ReflectionMethod(R::class,'snapshot'))->invoke(null,$ls,$uid);
        $append($lj,['phase'=>'private-runtime-rename-intent','source'=>$ls,'destination'=>$ld,'inventory'=>$snap]);
        mkdir($ls.'/storage',0700); mkdir($ls.'/storage/logs',0700);
        putFixture($ls.'/storage/logs/application.jsonl','synthetic private log',0644);
        $logIno=lstat($ls.'/storage/logs/application.jsonl')['ino']; $logHash=hash_file('sha256',$ls.'/storage/logs/application.jsonl');
        $engine->invoke(null,$ls,$ld,$uid,[$root.'/public'],$h,$lj);
        check(lstat($ld.'/storage/logs/application.jsonl')['ino']===$logIno); check(hash_file('sha256',$ld.'/storage/logs/application.jsonl')===$logHash);
        check((lstat($ld.'/storage/logs/application.jsonl')['mode']&07777)===0600);
        file_put_contents($ld.'/storage/logs/application.jsonl',"\nappend",FILE_APPEND);
        $engine->invoke(null,$ls,$ld,$uid,[$root.'/public'],$h,$lj);
        unlink($ld.'/storage/logs/application.jsonl'); $engine->invoke(null,$ls,$ld,$uid,[$root.'/public'],$h,$lj);
        putFixture($ld.'/storage/logs/application.jsonl','recreated',0644);
        (new ReflectionMethod(R::class,'repairLogPrivacy'))->invoke(null,$ld,$uid,$h,$lj);
        check((lstat($ld.'/storage/logs/application.jsonl')['mode']&07777)===0600);
        $engine->invoke(null,$ls,$ld,$uid,[$root.'/public'],$h,$lj);
        // The immutable original intent itself may contain an earlier log.
        $withLog=(new ReflectionMethod(R::class,'snapshot'))->invoke(null,$ld,$uid);
        file_put_contents($ld.'/storage/logs/application.jsonl','changed again');
        (new ReflectionMethod(R::class,'compare'))->invoke(null,$withLog,(new ReflectionMethod(R::class,'snapshot'))->invoke(null,$ld,$uid),$lj,$uid); check(true);
        putFixture($ld.'/storage/logs/unknown.jsonl','not approved');
        refuse(fn()=>$engine->invoke(null,$ls,$ld,$uid,[$root.'/public'],$h,$lj));
        $diag=json_decode(file_get_contents($root.'/relocation-inventory-check.json'),true,512,JSON_THROW_ON_ERROR);
        check(array_keys($diag)===['expectedImmutableCount','actualImmutableCount','mutableLogPresent','changedImmutableCount']); check($diag['changedImmutableCount']===1 && $diag['mutableLogPresent']===true);
        unlink($ld.'/storage/logs/unknown.jsonl'); putFixture($ld.'/code.php','mutated');
        try { $engine->invoke(null,$ls,$ld,$uid,[$root.'/public'],$h,$lj); check(false); } catch (RuntimeException $failure) {
            $safe=Recovery::safeFailure($failure); check($safe['sourceFile']==='CpanelPrivateRuntimeRelocation.php');
        }
        putFixture($ld.'/code.php','immutable'); unlink($ld.'/storage/logs/application.jsonl');
        check(symlink($ld.'/code.php',$ld.'/storage/logs/application.jsonl')); refuse(fn()=>$engine->invoke(null,$ls,$ld,$uid,[$root.'/public'],$h,$lj));
        unlink($ld.'/storage/logs/application.jsonl'); check(link($ld.'/code.php',$ld.'/storage/logs/application.jsonl')); refuse(fn()=>$engine->invoke(null,$ls,$ld,$uid,[$root.'/public'],$h,$lj));
        unlink($ld.'/storage/logs/application.jsonl');
        print "PASS native POSIX uid=$uid atomic rename/inode/hash/mode/resume/foreign-path/mutable-log fixtures\n";
    } else print "UNAVAILABLE native POSIX assertions on this platform\n";
} finally { removeFixture($root); }
print "PASS $n relocation and shim fixture assertions; no provider/database calls\n";
