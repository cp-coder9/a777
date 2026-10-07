<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/CpanelNativeDeployment.php';
use Architex\Deployment\CpanelNativeDeployment as Deploy;
$n=0;
function check(bool $ok,string $label): void { global $n; if(!$ok) throw new RuntimeException($label); $n++; }
function refuses(Closure $f,string $label): void { try { $f(); } catch (Throwable) { check(true,$label); return; } throw new RuntimeException($label); }
$source="<?php\nreturn [\n 'APP_KEY'=>'untouched',\n 'FILE_STORAGE_PATH' /* field */ => /* literal */ '/old/path',\n 'DB_PASSWORD'=>'exact bytes',\n];\n";
$updated=Deploy::rewriteStorage($source,"/home/archite4/.a777-storage/a'b");
check($updated===str_replace("'/old/path'",var_export("/home/archite4/.a777-storage/a'b",true),$source),'literal-only byte surgery');
refuses(fn()=>Deploy::rewriteStorage("<?php return ['FILE_STORAGE_PATH'=>getenv('X')];",'/x'),'nonliteral refused');
refuses(fn()=>Deploy::rewriteStorage("<?php return ['FILE_STORAGE_PATH'=>'/old'.'/more'];",'/x'),'concatenation refused');
refuses(fn()=>Deploy::rewriteStorage("<?php return ['FILE_STORAGE_PATH'=>'/a','FILE_STORAGE_PATH'=>'/b'];",'/x'),'duplicate refused');
refuses(fn()=>Deploy::rewriteStorage("<?php return ['OTHER'=>'/a'];",'/x'),'missing refused');
check(CpanelMaintenanceApi::decode('{"result":{"status":1,"errors":null,"data":null}}',200)===null,'wrapped null mutation success');
check(CpanelMaintenanceApi::decode('{"status":1,"errors":[],"data":[]}',200)===[],'direct inventory success');
foreach ([false,0,'',new stdClass(),[null]] as $errors) {
    refuses(fn()=>CpanelMaintenanceApi::decode(json_encode(['status'=>1,'errors'=>$errors,'data'=>null]),200),'strict errors');
}
refuses(fn()=>CpanelMaintenanceApi::decode('{"status":1,"result":{"status":1,"errors":null,"data":null}}',200),'mixed envelope refused');
refuses(fn()=>CpanelMaintenanceApi::decode('{"status":true,"errors":null,"data":null}',200),'status strict');
refuses(fn()=>CpanelMaintenanceApi::decode('{"status":1,"data":null}',200),'errors required');
$contract=json_decode(file_get_contents(dirname(__DIR__).'/native-deploy-contract.json'),true,32,JSON_THROW_ON_ERROR);
check($contract['requiredPlanFields']['mode']==='native-deploy','contract mode');
check(count($contract['stages'])===8 && end($contract['stages'])==='deployed','contract stages');
$resolve=new ReflectionMethod(Deploy::class,'runtimeOriginResolve');
check($resolve->invoke(null,'8.8.8.8')==='architex.co.za:443:8.8.8.8','fixed domain IPv4 resolve');
check($resolve->invoke(null,'2606:4700:4700::1111')==='architex.co.za:443:[2606:4700:4700::1111]','fixed domain IPv6 resolve');
foreach (['127.0.0.1','10.0.0.1','192.168.1.1','169.254.169.254','0.0.0.0','::1','fc00::1','fe80::1','::ffff:127.0.0.1','host.example','8.8.8.8:443','8.8.8.8,1.1.1.1',"8.8.8.8\n"] as $ip) {
    refuses(fn()=>$resolve->invoke(null,$ip),'non-public or malformed origin refused');
}
$loopOptions=new ReflectionMethod(Deploy::class,'runtimeLoopbackOptions');
$options=$loopOptions->invoke(null);
check($loopOptions->getNumberOfParameters()===0,'loopback accepts no configurable scope');
check($options[CURLOPT_URL]==='http://architex.co.za:80/.a777-runtime-active/config/runtime.php','exact fixed original virtualhost path');
check($options[CURLOPT_RESOLVE]===['architex.co.za:80:127.0.0.1'],'only fixed loopback resolution');
check($options[CURLOPT_PROTOCOLS]===CURLPROTO_HTTP && $options[CURLOPT_REDIR_PROTOCOLS]===CURLPROTO_HTTP,'explicit local HTTP transport');
check($options[CURLOPT_FOLLOWLOCATION]===false && $options[CURLOPT_MAXREDIRS]===0,'redirects disabled');
check($options[CURLOPT_PROXY]==='' && $options[CURLOPT_NOPROXY]==='*','environment proxies disabled');
check($options[CURLOPT_HTTPHEADER]===[] && $options[CURLOPT_NETRC]===CURL_NETRC_IGNORED,'no credentials or bypass headers');
$loopDenied=new ReflectionMethod(Deploy::class,'runtimeLoopbackDenied');
$metadata=['transport'=>'loopback-http','status'=>403,'peerVerified'=>true,'curlErrorCode'=>0];
foreach ([403,404] as $status) check($loopDenied->invoke(null,array_replace($metadata,['status'=>$status])),'loopback denial accepted');
foreach ([0,200,301,302,307,308,401,500,503,'403'] as $status) check(!$loopDenied->invoke(null,array_replace($metadata,['status'=>$status])),'non-denial or noninteger status refused');
foreach ([['peerVerified'=>false],['peerVerified'=>'127.0.0.1'],['curlErrorCode'=>7],['transport'=>'public-https'],['transport'=>'arbitrary-host']] as $change) check(!$loopDenied->invoke(null,array_replace($metadata,$change)),'unverified peer/error/transport refused');
echo "PASS $n assertions; literal/config and protocol fixtures only; no host/database calls\n";
