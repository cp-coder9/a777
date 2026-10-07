<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/CpanelNativeRecovery.php';
use Architex\Deployment\CpanelNativeRecovery as R;
if (PHP_OS_FAMILY!=='Linux') { fwrite(STDERR,"Linux filesystem required; no host claim\n"); exit(2); }
$root=(getenv('TMPDIR')?:'/tmp').'/recovery-fs-'.bin2hex(random_bytes(8));
mkdir($root,0700); mkdir($root.'/source',0700); mkdir($root.'/target',0755);
$source=$root.'/source'; $target=$root.'/target'; $gate="fixed fixture gate\n";
$method=new ReflectionMethod(R::class,'verifyPublic');
$h=['canonical'=>static function(string $path):void { if (is_link($path)||realpath($path)!==$path) throw new RuntimeException('fixture_path'); },'append'=>static function(string $path,array $record):void { file_put_contents($path,json_encode($record)."\n",FILE_APPEND); },'put'=>static function(string $path,string $bytes,int $mode):void { $f=fopen($path,'x'); if (!$f) throw new RuntimeException('fixture_exists'); fwrite($f,$bytes); fclose($f); chmod($path,$mode); }];
try {
    file_put_contents($source.'/.htaccess','normal'); file_put_contents($source.'/index.php',"<?php echo 'synthetic';"); file_put_contents($source.'/asset.txt','actual fixture bytes');
    file_put_contents($target.'/.htaccess',$gate); file_put_contents($target.'/index.php',Architex\Deployment\CpanelNativeDeployment::publicShim('index.php')); file_put_contents($target.'/asset.txt','actual fixture bytes');
    $method->invoke(null,$source,$target,$gate,$h,$root.'/journal',false);
    unlink($target.'/asset.txt');
    try { $method->invoke(null,$source,$target,$gate,$h,$root.'/journal',false); throw new LogicException('unowned partial accepted'); } catch (RuntimeException) {}
    $method->invoke(null,$source,$target,$gate,$h,$root.'/journal',true);
    if (file_get_contents($target.'/asset.txt')!=='actual fixture bytes' || count(file($root.'/journal'))!==1) throw new LogicException('completion evidence');
    file_put_contents($target.'/unknown.candidate-id','unknown');
    try { $method->invoke(null,$source,$target,$gate,$h,$root.'/journal',true); throw new LogicException('unknown accepted'); } catch (RuntimeException) {}
    echo "PASS native Linux filesystem full copy, owned completion, unowned partial and unknown marker refusal; reflection callbacks synthetic, no hosting proof\n";
} finally {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $f) { if ($f->isDir()) rmdir($f->getPathname()); else unlink($f->getPathname()); } rmdir($root);
}
