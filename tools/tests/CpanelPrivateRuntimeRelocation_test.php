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
        print "PASS native POSIX uid=$uid atomic rename/inode/hash/mode/resume/foreign-path fixtures\n";
    } else print "UNAVAILABLE native POSIX assertions on this platform\n";
} finally { removeFixture($root); }
print "PASS $n relocation and shim fixture assertions; no provider/database calls\n";
