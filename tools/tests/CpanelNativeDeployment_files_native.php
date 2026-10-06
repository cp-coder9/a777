<?php
declare(strict_types=1);
namespace Architex\Deployment {
    // Fault injection exists ONLY in this fixture process, not in the production coordinator API.
    function rename(string $from,string $to): bool {
        $fault=$GLOBALS['nativeRenameFault']??null;
        if($fault==='before') throw new \RuntimeException('fixture-before-rename');
        $ok=\rename($from,$to);
        if($fault==='after') throw new \RuntimeException('fixture-after-rename');
        return $ok;
    }
}
namespace {
require_once dirname(__DIR__).'/CpanelNativeDeployment.php';
use Architex\Deployment\CpanelNativeDeployment as Deploy;
if(PHP_OS_FAMILY!=='Linux'||!function_exists('posix_geteuid')) { fwrite(STDERR,"UNAVAILABLE native POSIX fixtures require Linux; Windows chmod is not proof\n"); exit(77); }
$parent=getenv('A777_NATIVE_FIXTURE_ROOT');
if(!is_string($parent)||$parent===''||realpath($parent)!==$parent||is_link($parent)||fileowner($parent)!==posix_geteuid()||(fileperms($parent)&0777)!==0700) throw new RuntimeException('explicit owned0700 fixture parent required');
$mask=umask(0077); $root=$parent.'/native-file-fixture-'.bin2hex(random_bytes(16));
if(!mkdir($root,0700)) throw new RuntimeException('fixture create');
$n=0;
function test(bool $ok,string $name): void { global $n; if(!$ok) throw new RuntimeException($name); $n++; }
function callPrivate(string $method,mixed ...$args): mixed { return (new ReflectionMethod(Deploy::class,$method))->invoke(null,...$args); }
try {
    $journal=$root.'/journal.jsonl'; callPrivate('append',$journal,['phase'=>'fixture-start'],true);
    foreach(['before','after',null] as $fault) {
        $label=$fault??'success'; $from=$root.'/from-'.$label; $to=$root.'/to-'.$label;
        mkdir($from,0700); file_put_contents($from.'/bytes','original'); chmod($from.'/bytes',0600);
        $GLOBALS['nativeRenameFault']=$fault;
        try { Deploy::ownedRename($from,$to,$journal); test($fault===null,'expected thrown fault'); }
        catch(RuntimeException $e) { if($fault===null) throw $e; test(true,'injected filesystem fault'); }
        clearstatcache(true);
        test($fault==='before'?is_dir($from)&&!file_exists($to):!file_exists($from)&&is_dir($to),'actual effect reconciled');
        $records=array_map(static fn($r)=>json_decode($r,true,32,JSON_THROW_ON_ERROR),array_filter(explode("\n",file_get_contents($journal))));
        $intent=array_values(array_filter($records,static fn($r)=>($r['phase']??null)==='rename-intent'&&($r['from']??null)===$from));
        test(count($intent)===1,'intent persisted before each fault');
        $done=array_values(array_filter($records,static fn($r)=>($r['phase']??null)==='rename-complete'&&($r['from']??null)===$from));
        test(count($done)===($fault===null?1:0),'no invented completion after ambiguous effect');
        test(file_get_contents(($fault==='before'?$from:$to).'/bytes')==='original','file bytes preserved');
    }
    $GLOBALS['nativeRenameFault']=null;
    // A partial install: one installed file, one untouched file, and an absent original.
    $a=$root.'/gate-installed'; $b=$root.'/gate-untouched'; $c=$root.'/gate-absent';
    file_put_contents($a,'installed-a'); chmod($a,0644);
    file_put_contents($b,'original-b'); chmod($b,0600);
    $entries=[['path'=>$a,'existed'=>true,'bytes'=>'original-a','mode'=>0600,'installed'=>'installed-a'],
        ['path'=>$b,'existed'=>true,'bytes'=>'original-b','mode'=>0600,'installed'=>'installed-b'],
        ['path'=>$c,'existed'=>false,'bytes'=>'','mode'=>0644,'installed'=>'installed-c']];
    callPrivate('restoreOriginalGate',$entries,$journal);
    test(file_get_contents($a)==='original-a' && (fileperms($a)&0777)===0600,'partial gate restored bytes and mode');
    test(file_get_contents($b)==='original-b' && !file_exists($c),'untouched and absent originals preserved');
    file_put_contents($a,'foreign');
    try { callPrivate('restoreOriginalGate',$entries,$journal); throw new RuntimeException('foreign gate accepted'); }
    catch(RuntimeException $e) { test($e->getMessage()!=='foreign gate accepted','foreign gate never overwritten'); }
    $source=$root.'/source'; mkdir($source,0755); chmod($source,0755); file_put_contents($source.'/f','first'); chmod($source.'/f',0600);
    $target=$root.'/copy'; mkdir($target,0700); $before=callPrivate('sourceInventory',$source,true);
    callPrivate('copyStorage',$source,$target,$before); test(file_get_contents($target.'/f')==='first','actual storage hash copy');
    file_put_contents($source.'/f','changed');
    try { callPrivate('copyStorage',$source,$target,$before); throw new RuntimeException('changed source accepted'); }
    catch(RuntimeException $e) { test($e->getMessage()!=='changed source accepted','source mutation refused'); }
    symlink($source.'/f',$source.'/link');
    try { callPrivate('sourceInventory',$source,true); throw new RuntimeException('symlink accepted'); }
    catch(RuntimeException $e) { test($e->getMessage()!=='symlink accepted','actual symlink refused'); }
    unlink($source.'/link'); link($source.'/f',$source.'/hard');
    try { callPrivate('sourceInventory',$source,true); throw new RuntimeException('hardlink accepted'); }
    catch(RuntimeException $e) { test($e->getMessage()!=='hardlink accepted','actual hardlink refused'); }
    test((fileperms($journal)&0777)===0600,'actual private journal mode');
    echo "PASS $n native filesystem assertions; no provider/database/production calls; evidence $root\n";
} finally { $GLOBALS['nativeRenameFault']=null; umask($mask); }
}
