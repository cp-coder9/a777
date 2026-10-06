<?php
declare(strict_types=1);
// POSIX filesystem fixture only; no account/provider/DB proof. Run with TMPDIR under private scratch.
require dirname(__DIR__) . '/CpanelRestoreRehearsal.php';
use Architex\Deployment\CpanelRestoreRehearsal;
if (PHP_OS_FAMILY !== 'Linux' || !function_exists('posix_geteuid')) { fwrite(STDERR,"BLOCKED: real Linux/POSIX filesystem required.\n"); exit(2); }
$base=getenv('TMPDIR');
if (!is_string($base) || !is_dir($base) || realpath($base)!==$base) throw new RuntimeException('Explicit canonical scratch required.');
$oldMask=umask(0077);
$root=$base.'/source-permissions-'.bin2hex(random_bytes(8)); mkdir($root,0700);
$source=$root.'/source'; mkdir($source,0755); chmod($source,0755);
mkdir($source.'/nested',0750); chmod($source.'/nested',0750);
$file=$source.'/nested/content'; file_put_contents($file,'synthetic private content'); chmod($file,0600);
$r=new ReflectionClass(CpanelRestoreRehearsal::class); $tree=$r->getMethod('sourceTree'); $output=$r->getMethod('privateTree');
$n=0;
function check(bool $ok): void { global $n; if (!$ok) throw new RuntimeException('Assertion failed.'); $n++; }
function reject(Closure $fn): void { try {$fn();} catch (RuntimeException) {check(true);return;} throw new LogicException('Refusal required.'); }
try {
    $inventory=$r->getMethod('sourceInventory'); $same=$r->getMethod('assertSourceIdentity');
    $before=hash_file('sha256',$file); check($tree->invoke(null,$source)===false);
    $bound=$inventory->invoke(null,$source);
    $same->invoke(null,$bound,$inventory->invoke(null,$source)); check(true);
    check($bound[$file]['sha256']===$before && $bound[$file]['size']===strlen('synthetic private content'));
    file_put_contents($file,'synthetic private CONTENT');
    reject(static fn()=>$same->invoke(null,$bound,$inventory->invoke(null,$source)));
    file_put_contents($file,'synthetic private content');
    $bound=$inventory->invoke(null,$source);
    $added=$source.'/writer-added'; file_put_contents($added,'writer'); chmod($added,0600);
    reject(static fn()=>$same->invoke(null,$bound,$inventory->invoke(null,$source))); unlink($added);
    // Warm PHP cache, then change mode in a separate real process (no parent cache clear).
    $tree->invoke(null,$source); lstat($file);
    $p=proc_open(['chmod','640',$file],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if (!is_resource($p)) throw new RuntimeException('External chmod unavailable.');
    foreach ($pipes as $pipe) fclose($pipe);
    if (proc_close($p)!==0) throw new RuntimeException('External chmod failed.');
    reject(static fn()=>$tree->invoke(null,$source)); chmod($file,0600);
    chmod($root,0777); reject(static fn()=>$tree->invoke(null,$source)); chmod($root,0700);
    // Replace source ancestor at the same pathname with an otherwise identical tree.
    $bound=$inventory->invoke(null,$source);
    rename($root,$root.'-old'); mkdir($root,0700); mkdir($source,0755); chmod($source,0755);
    mkdir($source.'/nested',0750); chmod($source.'/nested',0750); file_put_contents($file,'synthetic private content'); chmod($file,0600);
    reject(static fn()=>$same->invoke(null,$bound,$inventory->invoke(null,$source)));
    unlink($file); rmdir($source.'/nested'); rmdir($source); rmdir($root); rename($root.'-old',$root);
    check(file_get_contents($file)==='synthetic private content' && hash_file('sha256',$file)===$before);
    reject(static fn()=>$output->invoke(null,$source));
    chmod($source,0700); chmod($source.'/nested',0700); clearstatcache(); check($tree->invoke(null,$source)===true);
    chmod($source.'/nested',0755); clearstatcache(); check($tree->invoke(null,$source)===false);
    chmod($source,0775); clearstatcache(); reject(static fn()=>$tree->invoke(null,$source)); chmod($source,0755);
    chmod($file,0644); clearstatcache(); reject(static fn()=>$tree->invoke(null,$source)); chmod($file,0600);
    symlink($file,$source.'/link'); reject(static fn()=>$tree->invoke(null,$source)); unlink($source.'/link');
    link($file,$source.'/hardlink'); clearstatcache(); reject(static fn()=>$tree->invoke(null,$source)); unlink($source.'/hardlink');
    if (!posix_mkfifo($source.'/fifo',0600)) throw new RuntimeException('FIFO fixture unavailable.');
    reject(static fn()=>$tree->invoke(null,$source)); unlink($source.'/fifo');
    $r->getMethod('sourceLocation')->invoke(null,$source);
    reject(static fn()=>$r->getMethod('sourceLocation')->invoke(null,'/home/archite4/public_html/source'));
    $scope=$r->getMethod('sourceLocationInScope');
    reject(static fn()=>$scope->invoke(null,$source,$root));
    $scope->invoke(null,$source,$root,true); check($tree->invoke(null,$source,true)===false);
    // Outputs have no exception, even when their mode would otherwise be private.
    reject(static fn()=>$scope->invoke(null,$source,$root));
    // Optional native cross-owner test: sudo receives only this exact random fixture directory.
    $foreign=$root.'/foreign'; mkdir($foreign,0755); chmod($foreign,0755); mkdir($foreign.'/child',0700);
    $foreignUid=posix_geteuid()===65534?65533:65534;
    $p=@proc_open(['sudo','-n','chown',(string)$foreignUid,$foreign],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $native=false;
    if (is_resource($p)) { foreach ($pipes as $pipe) fclose($pipe); $native=proc_close($p)===0; }
    if ($native) {
        try { reject(static fn()=>$tree->invoke(null,$foreign.'/child')); reject(static fn()=>$r->getMethod('canonical')->invoke(null,$foreign)); }
        finally {
            $p=proc_open(['sudo','-n','chown',(string)posix_geteuid(),$foreign],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            if (!is_resource($p)) throw new RuntimeException('Fixture ownership restoration unavailable.');
            foreach ($pipes as $pipe) fclose($pipe);
            if (proc_close($p)!==0) throw new RuntimeException('Fixture ownership restoration failed.');
        }
        print("Native cross-owner fixture exercised.\n");
    } else print("LIMIT: native cross-owner unavailable; synthetic stat review only.\n");
    rmdir($foreign.'/child'); rmdir($foreign);
    // Foreign uid also tested synthetically, explicitly not real cross-owner evidence.
    reject(static fn()=>$r->getMethod('sourceDirectoryStat')->invoke(null,['mode'=>0040755,'uid'=>posix_geteuid()+1],posix_geteuid()));
    check((fileperms($source)&0777)===0755 && (fileperms($file)&0777)===0600);
    $recovery=$root.'/output'; mkdir($recovery,0700); $journal=$recovery.'/journal';
    $append=$r->getMethod('append'); $append->invoke(null,$journal,['synthetic'=>'private'],true);
    check((fileperms($journal)&0777)===0600); $prior=file_get_contents($journal); lstat($journal);
    $p=proc_open(['chmod','640',$journal],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if (!is_resource($p)) throw new RuntimeException('External output chmod unavailable.');
    foreach ($pipes as $pipe) fclose($pipe);
    if (proc_close($p)!==0) throw new RuntimeException('External output chmod failed.');
    reject(static fn()=>$append->invoke(null,$journal,['mustNotWrite'=>true]));
    check(file_get_contents($journal)===$prior); chmod($journal,0600);
    chmod($recovery,0755); reject(static fn()=>$append->invoke(null,$journal,['mustNotWrite'=>true])); chmod($recovery,0700);
    unlink($journal); rmdir($recovery);
    printf("POSIX synthetic filesystem assertions: %d passed; no provider/DB proof.\n",$n);
} finally {
    foreach (['link','hardlink','fifo'] as $name) if (file_exists($source.'/'.$name) || is_link($source.'/'.$name)) unlink($source.'/'.$name);
    unlink($file); rmdir($source.'/nested'); rmdir($source); rmdir($root);
    umask($oldMask);
}
