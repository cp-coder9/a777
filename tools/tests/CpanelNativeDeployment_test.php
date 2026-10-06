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
echo "PASS $n assertions; literal/config and protocol fixtures only; no host/database calls\n";
