<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/CpanelNativeDeployment.php';
use Architex\Deployment\CpanelNativeDeployment as Deploy;
$next=static fn(int $before,int $now): int => Deploy::nextGateMtime($before,$now);
if (!method_exists(Deploy::class,'nextGateMtime')) throw new RuntimeException('missing bounded gate mtime policy');
foreach ([[100,100,101],[99,100,100],[0,100,100]] as [$before,$now,$expected]) {
    if ($next($before,$now)!==$expected) throw new RuntimeException('gate mtime policy');
}
foreach ([[101,100],[-1,100],[100,-1],[PHP_INT_MAX,PHP_INT_MAX]] as [$before,$now]) {
    try { $next($before,$now); throw new LogicException('invalid/future timestamp accepted'); }
    catch (RuntimeException) {}
}
echo "PASS bounded gate mtime policy (pure clock cases)\n";
// Wiring assertions supplement (not replace) the unavailable fixed-root Linux integration.
$source=file_get_contents(dirname(__DIR__).'/CpanelNativeDeployment.php');
foreach (['$gate=$path===self::DOCROOT.\'/.htaccess\'', 'self::nextGateMtime($before[\'mtime\'],time())',
    'hrtime(true)+1250000000', 'touch($tmp,$target)', '$after[\'mtime\']>$before[\'mtime\']',
    "'beforeMtime'=>\$before['mtime']", "'afterMtime'=>\$after['mtime']", 'fsync($h)', 'fsync($dir)',
    "['dev','ino','uid','gid','mode','nlink','size','mtime','ctime']", 'file_get_contents($path)===$expected',
    'flock($activationLock,LOCK_EX|LOCK_NB)'] as $required) {
    if (!str_contains($source,$required)) throw new RuntimeException('gate mtime wiring/lock contract');
}
echo "PASS fixed-root timestamp/journal/source-readback/activation-lock wiring (source assertions only)\n";
// Real filesystem timestamp/atomic rename exercise; NOT account-owner integration.
$dir=getenv('TMPDIR') ?: sys_get_temp_dir();
$root=$dir.'/native-gate-mtime-'.bin2hex(random_bytes(8));
mkdir($root,0700);
$path=$root.'/.htaccess'; $tmp=$path.'.tmp';
try {
    file_put_contents($path,'original');
    $now=time(); touch($path,$now); clearstatcache(true,$path); $before=lstat($path);
    file_put_contents($tmp,'maintenance'); rename($tmp,$path); touch($path,$now); clearstatcache(true,$path);
    if (lstat($path)['mtime']!==$before['mtime'] || file_get_contents($path)!=='maintenance') throw new RuntimeException('same-second condition not reproduced');
    foreach (['nonce-gate','restored-maintenance'] as $bytes) {
        clearstatcache(true,$path); $old=lstat($path); $target=$next($old['mtime'],time());
        $deadline=microtime(true)+1.25;
        while (time()<$target && microtime(true)<$deadline) usleep(10000);
        if (time()<$target) throw new RuntimeException('bounded clock wait');
        file_put_contents($tmp,$bytes); chmod($tmp,0644); touch($tmp,$target);
        $h=fopen($tmp,'r+b'); if (!fsync($h)) throw new RuntimeException('file fsync'); fclose($h);
        rename($tmp,$path); clearstatcache(true,$path); $after=lstat($path);
        if ($after['mtime']<=$old['mtime'] || file_get_contents($path)!==$bytes) throw new RuntimeException('replacement timestamp/bytes');
        if (PHP_OS_FAMILY==='Linux' && (($after['mode']&07777)!==0644 || $after['uid']!==posix_geteuid() || $after['nlink']!==1 || ($after['mode']&0170000)!==0100000 || $after['ino']===$old['ino'])) throw new RuntimeException('POSIX replacement metadata');
    }
    $engine=new ReflectionMethod(Deploy::class,'gateNotifyEngine');
    $policy=new ReflectionMethod(Deploy::class,'gateNotifyStat');
    $good=['mode'=>0100644,'uid'=>123,'nlink'=>1];
    if (!$policy->invoke(null,$good,123)) throw new RuntimeException('valid notify stat');
    foreach (['mode'=>0120644,'uid'=>124,'nlink'=>2] as $field=>$value) {
        $bad=$good; $bad[$field]=$value;
        if ($policy->invoke(null,$bad,123)) throw new RuntimeException('notify stat guard');
    }
    $bad=$good; $bad['mode']=0100600;
    if ($policy->invoke(null,$bad,123)) throw new RuntimeException('notify mode guard');
    $rootGuard=new ReflectionMethod(Deploy::class,'notifyRootGate');
    try { $rootGuard->invoke(null,$path,'x',0,$root.'/journal'); throw new LogicException('generic root path accepted'); }
    catch (RuntimeException) {}
    $bytes="# maintenance\nRewriteEngine On\nRewriteRule ^ - [R=503,L]\nErrorDocument 503 \"Deployment maintenance\"\n";
    file_put_contents($path,$bytes); chmod($path,0644); clearstatcache(true,$path); $identity=lstat($path);
    $events=[];
    $validate=static function () use ($path,$policy): void {
        clearstatcache(true,$path); $stat=lstat($path);
        if (is_link($path) || !is_file($path)) throw new RuntimeException('fixture canonical guard');
        if (PHP_OS_FAMILY==='Linux' && !$policy->invoke(null,$stat,posix_geteuid())) throw new RuntimeException('fixture POSIX guard');
    };
    try { $engine->invoke(null,$path,$bytes.'wrong',$identity['mtime'],$validate,static function(array $entry): void { throw new LogicException('mismatched bytes reached journal'); }); throw new LogicException('mismatched bytes accepted'); }
    catch (RuntimeException) {}
    if (file_get_contents($path)!==$bytes) throw new RuntimeException('refusal changed bytes');
    if (PHP_OS_FAMILY==='Linux') {
        $alias=$root.'/alias';
        link($path,$alias);
        try { $engine->invoke(null,$path,$bytes,0,$validate,static function(array $entry): void {}); throw new LogicException('hardlink accepted'); } catch (RuntimeException) {} finally { unlink($alias); }
        symlink($path,$alias);
        try { $engine->invoke(null,$alias,$bytes,0,static function() use ($alias): void { if (is_link($alias)) throw new RuntimeException('symlink refused'); },static function(array $entry): void {}); throw new LogicException('symlink accepted'); } catch (RuntimeException) {} finally { unlink($alias); }
        chmod($path,0600);
        try { $engine->invoke(null,$path,$bytes,0,$validate,static function(array $entry): void {}); throw new LogicException('mode accepted'); } catch (RuntimeException) {} finally { chmod($path,0644); }
        echo "PASS Linux own-account actual hardlink/symlink/mode refusals (fixture validator, not production root)\n";
    } else echo "UNAVAILABLE Linux own-account native link/UID/mode proof; synthetic policy only\n";
    $engine->invoke(null,$path,$bytes,$identity['mtime'],$validate,static function(array $entry) use (&$events): void { $events[]=$entry; });
    clearstatcache(true,$path); $after=lstat($path);
    if (file_get_contents($path)!==$bytes || hash_file('sha256',$path)!==hash('sha256',$bytes) || $after['ino']!==$identity['ino'] || $after['dev']!==$identity['dev'] || $after['size']!==strlen($bytes) || $after['mtime']<=$identity['mtime']) throw new RuntimeException('same-byte preservation');
    if (array_column($events,'phase')!==['gate-notify-intent','gate-notify-complete'] || str_contains(json_encode($events),'Deployment maintenance')) throw new RuntimeException('notify metadata');
    foreach (['fopen($path,\'r+b\')','fwrite($h,$bytes[0])===1','self::notifyRootGate($path,$bytes,$before[\'mtime\'],$journal)'] as $required) if (!str_contains($source,$required)) throw new RuntimeException('notify source contract');
    echo "PASS real same-byte nontruncating notification: exact complete bytes/SHA/inode/status/body directive and metadata phases; synthetic UID/mode/link policy guards\n";
    echo 'PASS real '.PHP_OS_FAMILY." filesystem same-second condition and successive increasing replacement mtimes/bytes\n";
    echo "NOT PROVEN production fixed-root replaceOwned/account lock or web-server cache causation\n";
} finally { if (file_exists($tmp)) unlink($tmp); unlink($path); rmdir($root); }
