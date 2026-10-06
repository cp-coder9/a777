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
// Test-only barrier in a private copy: keep the real journal and fsync intact.
// No instrumentation is added to the deployed production class.
function a777_fixture_swap_parent(string $parent, string $path): void {
    rename($parent, dirname($parent).'/old-config');
    mkdir($parent,0700); chmod($parent,0755);
    rename(dirname($parent).'/old-config/runtime.php',$path);
}
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
    $source=file_get_contents(dirname(__DIR__).'/cpanel_git_deploy.php');
    $anchor="            clearstatcache(true, \$parentPath);\n            self::requireValue(@lstat(\$parentPath) === \$parent";
    check(substr_count($source,$anchor)===1);
    $instrumented=str_replace('final class CpanelGitDeploy','final class CpanelGitDeploySwapFixture',$source);
    $instrumented=str_replace($anchor,"            a777_fixture_swap_parent(\$parentPath, \$path);\n".$anchor,$instrumented);
    $copy=$base.'/swap-fixture.php'; file_put_contents($copy,$instrumented); chmod($copy,0600); require $copy;
    $e=[];
    try { CpanelGitDeploySwapFixture::runtimePermissions($path,$base,$intent,true,$e,str_repeat('a',40),str_repeat('b',64)); throw new RuntimeException('swapped parent accepted'); }
    catch (RuntimeException $x) { check($x->getMessage()==='runtime_permission_parent_identity_refused'); }
    clearstatcache(); check(lstat($base.'/config')['ino']!==$originalParent['ino'] && (fileperms($path)&07777)===0644
        && (fileperms($base.'/config')&07777)===0755 && !$e['runtimeConfigParentPermissionsTouched'] && !$e['runtimeConfigPermissionsTouched']);
    check(json_decode(file_get_contents($intent),true)['parentInode']===$originalParent['ino']);
    rmdir($base.'/old-config');
    print "Linux permission fixture: 5 production cases and 1 instrumented real-filesystem race case passed\n";
} finally {
    if (is_link($path)||file_exists($path)) unlink($path);
    if (file_exists($intent)) unlink($intent);
    if (file_exists($base.'/hardlink')) unlink($base.'/hardlink');
    if (file_exists($base.'/swap-fixture.php')) unlink($base.'/swap-fixture.php');
    if (is_dir($base.'/old-config')) rmdir($base.'/old-config');
    flock($lock,LOCK_UN); fclose($lock); unlink($base.'/deploy.lock'); rmdir($base.'/config'); rmdir($base);
}
