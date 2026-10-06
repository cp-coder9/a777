<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/CpanelNativeDeployment.php';
use Architex\Deployment\CpanelNativeDeployment as Deploy;
$root=dirname(__DIR__,2).'/app/public'; $count=0;
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)) as $file) {
 if(!preg_match('/\.(?:php[0-9]*|phtml|phar)\z/i',$file->getFilename()))continue;
 $relative=str_replace('\\','/',substr($file->getPathname(),strlen($root)+1)); $shim=Deploy::publicShim($relative);
 $expected='/home/archite4/public_html/.a777-runtime-active/public/'.$relative;
 if(!str_contains($shim,'require '.var_export($expected,true).';') || str_contains($shim,'REQUEST_URI') || str_contains($shim,'bootstrap') || str_contains($shim,'BYPASS'))throw new RuntimeException('Shim contract failed');
 token_get_all($shim,TOKEN_PARSE); $count++;
}
foreach(['../index.php','nested/../index.php','/index.php',"a\0.php"] as $relative) {
 try{Deploy::publicShim($relative);}catch(Throwable){continue;} throw new RuntimeException('Traversal accepted');
}
$token='fixture_token_1234567890';
$spec=CpanelMaintenanceApi::requestSpec('Mysql','create_user',$token);
if($spec['headers'][0]!==('Authorization: cpanel '.'archite4'.':'.$token))throw new RuntimeException('Exact authorization protocol failed');
foreach([
 ['create_user',['name'=>'archite4_existing','password'=>str_repeat('a',64)]],
 ['create_user',['name'=>'archite4_nd_'.str_repeat('a',16),'password'=>'weak']],
 ['set_privileges_on_database',['database'=>'unknown','user'=>'archite4_nd_'.str_repeat('a',16),'privileges'=>'ALL PRIVILEGES']],
 ['delete_user',['name'=>'archite4_nd_'.str_repeat('a',16)]]
] as [$operation,$args]) {try{CpanelMaintenanceApi::operator($operation,$args);}catch(Throwable){continue;}throw new RuntimeException('Operator scope accepted');}
// Execute the real public app renderer through a shim with only its fixed host root
// rebound to the local candidate. This is a logical renderer proof, not an auth bypass.
$uri='/app.php?shim-proof=1'; $_SERVER['REQUEST_URI']=$uri;
$code=str_replace('/home/archite4/public_html/.a777-runtime-active/public/',str_replace('\\','/',$root).'/',Deploy::publicShim('app.php'));
$temporary=getenv('TMPDIR').'/native-shim-render-'.bin2hex(random_bytes(8)).'.php';
file_put_contents($temporary,$code); http_response_code(200); ob_start();
try { require $temporary; $rendered=ob_get_clean(); } finally { unlink($temporary); }
if(http_response_code()!==200 || $_SERVER['REQUEST_URI']!==$uri || !str_contains($rendered,'Architex OS') || !str_contains($rendered,'publicAccess'))throw new RuntimeException('Actual app renderer proof failed');
echo "PASS actual app.php logical renderer 200 and original REQUEST_URI; no application authentication bypass.\n";
echo "PASS $count actual public executable paths bound to original private runtime; request untouched; exact Authorization and operator negative protocol fixtures. No actual HTTP/provider claim.\n";
