<?php
declare(strict_types=1);
require dirname(__DIR__) . '/cpanel_git_deploy.php';
if (PHP_OS_FAMILY !== 'Linux' || !function_exists('posix_geteuid')) { fwrite(STDERR, "Linux POSIX PHP required\n"); exit(2); }
umask(0077);
$base = realpath(getenv('HOME')) . '/a777-permission-fixture-' . bin2hex(random_bytes(8));
mkdir($base,0700); mkdir($base.'/config',0700);
$path=$base.'/config/runtime.php'; $intent=$base.'/runtime-permission-0123456789abcdef.json';
$lock=fopen($base.'/deploy.lock','x+b'); flock($lock,LOCK_EX);
function check(bool $ok): void { if (!$ok) throw new RuntimeException('fixture assertion failed'); }
// Test-only real filesystem swap at journal close, before either target chmod.
class SwapIntentStream {
    public $context;
    public static string $base;
    public static string $intent;
    private string $bytes = '';
    public function url_stat($path, $flags) { return false; }
    public function stream_open($path, $mode, $options, &$opened): bool { return true; }
    public function stream_metadata($path, $option, $value): bool { return $option === STREAM_META_ACCESS; }
    public function stream_write($bytes): int { $this->bytes .= $bytes; return strlen($bytes); }
    public function stream_flush(): bool { return true; }
    public function stream_close(): void {
        file_put_contents(self::$intent, $this->bytes); chmod(self::$intent,0600);
        rename(self::$base.'/config',self::$base.'/old-config'); mkdir(self::$base.'/config',0700); chmod(self::$base.'/config',0755);
        rename(self::$base.'/old-config/runtime.php',self::$base.'/config/runtime.php');
    }
}
stream_wrapper_register('swapintent',SwapIntentStream::class);
try {
    file_put_contents($path,'synthetic permission fixture only'); chmod($path,0644);
    $e=[]; CpanelGitDeploy::runtimePermissions($path,$base,$intent,false,$e);
    check(!$e['runtimeConfigPermissionsTouched'] && !file_exists($intent));
    chmod($base.'/config',0755);
    $before=hash_file('sha256',$path); $e=[];
    CpanelGitDeploy::runtimePermissions($path,$base,$intent,true,$e,str_repeat('a',40),str_repeat('b',64));
    clearstatcache(); check((fileperms($path)&07777)===0600 && hash_file('sha256',$path)===$before);
    check((fileperms($base.'/config')&07777)===0700 && $e['runtimeConfigParentPermissionsTouched']);
    $j=json_decode(file_get_contents($intent),true);
    check($j['parentBeforeMode']==='0755' && $j['parentAfterMode']==='0700' && $j['parentRepair']);
    check($j['commit']===str_repeat('a',40) && $j['planSha256']===str_repeat('b',64) && $e['runtimePermissionIntent']===basename($intent));
    unlink($intent); chmod($path,0644); link($path,$base.'/hardlink'); $e=[];
    try { CpanelGitDeploy::runtimePermissions($path,$base,$intent,true,$e); throw new RuntimeException('hardlink accepted'); }
    catch (RuntimeException $x) { check($x->getMessage()==='runtime_permission_identity_refused'); }
    unlink($base.'/hardlink'); unlink($path); symlink($base.'/deploy.lock',$path); $e=[];
    try { CpanelGitDeploy::runtimePermissions($path,$base,$intent,true,$e); throw new RuntimeException('symlink accepted'); }
    catch (RuntimeException $x) { check($x->getMessage()==='runtime_permission_identity_refused'); }
    unlink($path); file_put_contents($path,'synthetic permission fixture only'); chmod($path,0644);
    chmod($base.'/config',0775); $e=[];
    try { CpanelGitDeploy::runtimePermissions($path,$base,$intent,true,$e,str_repeat('a',40),str_repeat('b',64)); throw new RuntimeException('unsafe parent accepted'); }
    catch (RuntimeException $x) { check($x->getMessage()==='runtime_permission_ancestor_write_refused'); }
    clearstatcache(); check((fileperms($path)&07777)===0644 && (fileperms($base.'/config')&07777)===0775 && !file_exists($intent));
    chmod($base.'/config',0755); clearstatcache(); $originalParent=lstat($base.'/config');
    SwapIntentStream::$base=$base; SwapIntentStream::$intent=$intent; $e=[];
    try { CpanelGitDeploy::runtimePermissions($path,$base,'swapintent://fixture/'.basename($intent),true,$e,str_repeat('a',40),str_repeat('b',64)); throw new RuntimeException('swapped parent accepted'); }
    catch (RuntimeException $x) { check($x->getMessage()==='runtime_permission_parent_identity_refused'); }
    clearstatcache(); check(lstat($base.'/config')['ino']!==$originalParent['ino'] && (fileperms($path)&07777)===0644
        && (fileperms($base.'/config')&07777)===0755 && !$e['runtimeConfigParentPermissionsTouched'] && !$e['runtimeConfigPermissionsTouched']);
    check(json_decode(file_get_contents($intent),true)['parentInode']===$originalParent['ino']);
    rmdir($base.'/old-config');
    print "Linux permission fixture: 6 cases passed\n";
} finally {
    if (is_link($path)||file_exists($path)) unlink($path);
    if (file_exists($intent)) unlink($intent);
    if (file_exists($base.'/hardlink')) unlink($base.'/hardlink');
    flock($lock,LOCK_UN); fclose($lock); unlink($base.'/deploy.lock'); rmdir($base.'/config'); rmdir($base);
}
