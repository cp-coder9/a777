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
try {
    file_put_contents($path,'synthetic permission fixture only'); chmod($path,0644);
    $e=[]; CpanelGitDeploy::runtimePermissions($path,$base,$intent,false,$e);
    check(!$e['runtimeConfigPermissionsTouched'] && !file_exists($intent));
    $before=hash_file('sha256',$path); $e=[];
    CpanelGitDeploy::runtimePermissions($path,$base,$intent,true,$e,str_repeat('a',40),str_repeat('b',64));
    clearstatcache(); check((fileperms($path)&07777)===0600 && hash_file('sha256',$path)===$before);
    $j=json_decode(file_get_contents($intent),true);
    check($j['commit']===str_repeat('a',40) && $j['planSha256']===str_repeat('b',64) && $e['runtimePermissionIntent']===basename($intent));
    unlink($intent); chmod($path,0644); link($path,$base.'/hardlink'); $e=[];
    try { CpanelGitDeploy::runtimePermissions($path,$base,$intent,true,$e); throw new RuntimeException('hardlink accepted'); }
    catch (RuntimeException $x) { check($x->getMessage()==='runtime_permission_identity_refused'); }
    unlink($base.'/hardlink'); unlink($path); symlink($base.'/deploy.lock',$path); $e=[];
    try { CpanelGitDeploy::runtimePermissions($path,$base,$intent,true,$e); throw new RuntimeException('symlink accepted'); }
    catch (RuntimeException $x) { check($x->getMessage()==='runtime_permission_identity_refused'); }
    print "Linux permission fixture: 4 cases passed\n";
} finally {
    if (is_link($path)||file_exists($path)) unlink($path);
    if (file_exists($intent)) unlink($intent);
    if (file_exists($base.'/hardlink')) unlink($base.'/hardlink');
    flock($lock,LOCK_UN); fclose($lock); unlink($base.'/deploy.lock'); rmdir($base.'/config'); rmdir($base);
}
