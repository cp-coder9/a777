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
    echo 'PASS real '.PHP_OS_FAMILY." filesystem same-second condition and successive increasing replacement mtimes/bytes\n";
    echo "NOT PROVEN production fixed-root replaceOwned/account lock or web-server cache causation\n";
} finally { if (file_exists($tmp)) unlink($tmp); unlink($path); rmdir($root); }
