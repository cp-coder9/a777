<?php
declare(strict_types=1);
// Proof only: fixed owned disposable database; no production bootstrap or fixture resets.
require 'E:/Hermes/home/cache/scratch/material3-runtime/http-bootstrap.php';
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Identity\PasswordHasher;
use Architex\Platform\Identity\SessionService;
use Architex\Platform\Demo\DemoRoleLensRegistry;
use Architex\Platform\Demo\DemoSeedCatalog;
use Architex\Platform\Demo\DemoSandboxService;
$private='E:/Hermes/home/cache/scratch/material3-demo-lenses';
if(!is_dir($private))mkdir($private,0700,true);
$reportPath=$root.'/docs/material3-expressive/demo-lens-acceptance.json';
$paths=[];
foreach([DemoSandboxService::class,DemoRoleLensRegistry::class,DemoSeedCatalog::class,PasswordHasher::class,SessionService::class] as $c){$p=str_replace('\\','/',(new ReflectionClass($c))->getFileName());if(!str_starts_with($p,$root.'/app/src/'))throw new RuntimeException('Wrong candidate class');$paths[$c]=$p;}
function demoProofKernel(PDO $pdo,array $env,string $private):Architex\Http\Kernel {
 $mail=Architex\Infrastructure\Mail\MailRuntime::fromEnvironment($pdo,$env);
 return (new Architex\Http\AuthenticationKernelFactory($pdo,$mail->registrationNotifier,$mail->passwordResetNotifier,fileStorageRoot:$private.'/storage',appMode:'demo',appUrl:'http://127.0.0.1:59949'))->create();
}
if(PHP_SAPI!=='cli'){
 if(($_SERVER['HTTP_HOST']??'')!=='127.0.0.1:59949'||($_SERVER['REMOTE_ADDR']??'')!=='127.0.0.1'){http_response_code(403);exit;}
 $path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
 if($path==='/demo'){putenv('APP_MODE=demo');require $root.'/app/public/app.php';return;}
 if(is_string($path)&&str_starts_with($path,'/assets/')){$asset=realpath($root.'/app/public/'.$path);$base=$root.'/app/public/assets/';if($asset!==false&&str_starts_with(str_replace('\\','/',$asset),$base)&&is_file($asset)){$types=['js'=>'application/javascript','css'=>'text/css','svg'=>'image/svg+xml','png'=>'image/png','woff2'=>'font/woff2'];$ext=pathinfo($asset,PATHINFO_EXTENSION);if(isset($types[$ext])){header('Content-Type: '.$types[$ext]);readfile($asset);return;}}http_response_code(404);return;}
 demoProofKernel($pdo,$env,$private)->handle(Architex\Http\Request::fromGlobals())->emit();return;
}
$mode=$argv[1]??'';
if($mode==='--prepare'){
 if(file_exists($private.'/identity.json'))throw new RuntimeException('Dedicated identity exists; refusing reseed');
 $id=['organisationId'=>Identifier::generate(),'userId'=>Identifier::generate(),'membershipId'=>Identifier::generate()];
 $h=fopen($private.'/intent.json','x');if(!$h)throw new RuntimeException('Intent exists; refusing replay');fwrite($h,json_encode($id));fflush($h);fclose($h);
 $password=bin2hex(random_bytes(32));$hash=(new PasswordHasher())->hash($password);unset($password);
 $pdo->beginTransaction();try{
 $pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(?),'Synthetic demo lens acceptance tenant',?,'active')")->execute([$id['organisationId'],'synthetic-demo-lenses-'.$id['organisationId']]);
 $pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(?),?,?,'active',UTC_TIMESTAMP(6))")->execute([$id['userId'],'synthetic-demo-lenses-'.$id['userId'].'@example.test',$hash]);
 $pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(?),UNHEX(?),UNHEX(?),'owner','active')")->execute([$id['membershipId'],$id['organisationId'],$id['userId']]);
 $pdo->commit();}catch(Throwable $e){$pdo->rollBack();throw $e;}
 $s=(new SessionService($pdo))->rotate($id['userId'],null,'127.0.0.1','synthetic-demo-lens-proof');
 $id['token']=$s->token;$id['csrf']=$s->csrfToken;file_put_contents($private.'/identity.json',json_encode($id));chmod($private.'/identity.json',0600);
 echo json_encode(['dedicatedSyntheticTenantPrepared'=>true,'existingFixturesChanged'=>false]).PHP_EOL;return;
}
if($mode==='--refresh-session'){
 $p=$private.'/identity.json';$i=json_decode(file_get_contents($p),true,512,JSON_THROW_ON_ERROR);
 $q=$pdo->prepare("SELECT COUNT(*) FROM organisation_memberships WHERE id=UNHEX(?) AND organisation_id=UNHEX(?) AND user_id=UNHEX(?) AND role='owner' AND status='active'");$q->execute([$i['membershipId'],$i['organisationId'],$i['userId']]);if((int)$q->fetchColumn()!==1)throw new RuntimeException('Dedicated identity mismatch');
 $s=(new SessionService($pdo))->rotate($i['userId'],null,'127.0.0.1','synthetic-demo-lens-proof');$i['token']=$s->token;$i['csrf']=$s->csrfToken;file_put_contents($p,json_encode($i));echo "Dedicated synthetic session refreshed\n";return;
}
if($mode!=='--prove')throw new RuntimeException('Use --prepare, --refresh-session or --prove');
$id=json_decode(file_get_contents($private.'/identity.json'),true,512,JSON_THROW_ON_ERROR);
$report=['synthetic'=>true,'schema'=>$env['DB_NAME'],'dbPort'=>59946,'httpOrigin'=>'http://127.0.0.1:59949','organisationId'=>$id['organisationId'],'disposableGuardVerified'=>true,'datadirVerified'=>true,'migrationCount'=>69,'pendingMigrations'=>0,'existingFixturesChanged'=>false,'sourceMode'=>'candidate actual classes; appMode demo','dataSource'=>'native MariaDB owned disposable schema','normalProductionLogin'=>'NOT_PROVEN','nativeService'=>'RUNNING','http'=>'RUNNING','ui'=>'UNSUPPORTED_NOT_EXERCISED','professionalEvidence'=>false,'cpdAccreditation'=>false,'candidatePaths'=>$paths,'lenses'=>[]];
$save=static function()use(&$report,$reportPath,$private):void{$json=json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);file_put_contents($reportPath,$json);file_put_contents($private.'/receipt.json',$json);file_put_contents($private.'/progress.jsonl',json_encode(['lensCount'=>count($report['lenses']),'http'=>$report['http']]).PHP_EOL,FILE_APPEND);};
$save();
function callDemo(array $id,string $method,string $path,?array $body=null,bool $auth=true,bool $csrf=true):array {
 $h=['Content-Type: application/json'];if($auth){$h[]='Cookie: architex_session='.$id['token'];$h[]='X-Organisation-Id: '.$id['organisationId'];if($csrf)$h[]='X-CSRF-Token: '.$id['csrf'];}
 $c=curl_init('http://127.0.0.1:59949'.$path);curl_setopt_array($c,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$h,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>60,CURLOPT_PROXY=>'']);if($body!==null)curl_setopt($c,CURLOPT_POSTFIELDS,json_encode($body===[]?(object)[]:$body));$raw=curl_exec($c);if($raw===false)throw new RuntimeException('Loopback transport failed');$status=curl_getinfo($c,CURLINFO_RESPONSE_CODE);curl_close($c);return ['status'=>$status,'json'=>json_decode($raw,true,512,JSON_THROW_ON_ERROR)];
}
function need(bool $b,string $s):void{if(!$b)throw new RuntimeException($s);}
try{
 $report['unauthenticatedStatus']=callDemo($id,'POST','/api/v1/demo/sandbox/ensure',[],false)['status'];need($report['unauthenticatedStatus']===401,'Session gate');
 $report['missingCsrfStatus']=callDemo($id,'POST','/api/v1/demo/sandbox/ensure',[],true,false)['status'];need($report['missingCsrfStatus']===419,'CSRF gate');
 $ensured=callDemo($id,'POST','/api/v1/demo/sandbox/ensure',[]);need($ensured['status']===200,'HTTP ensure');
 $service=new DemoSandboxService($pdo);$native=$service->ensure($id['organisationId'],$id['userId'],'owner');$report['counts']=$native['counts'];$report['relianceLabels']=$native['relianceLabels'];need($native['counts']['projects']===12,'Native twelve projects');
 $roles=(new DemoRoleLensRegistry())->all();$catalog=(new DemoSeedCatalog())->projects();need(count($roles)===22,'Registry count');
 $codeByKey=array_column($catalog,'projectCode','sourceKey');
 $q=$pdo->prepare('SELECT LOWER(HEX(id)) id,project_code FROM projects WHERE organisation_id=UNHEX(?)');$q->execute([$id['organisationId']]);$uuidByCode=array_column($q->fetchAll(),'id','project_code');need(count($uuidByCode)===12,'Independent project map');
 $foreign=$pdo->query("SELECT LOWER(HEX(id)) FROM projects WHERE LOWER(HEX(id)) LIKE '85e4ce%' LIMIT 1")->fetchColumn();need(is_string($foreign),'Known foreign negative project absent');
 foreach($roles as $key=>$lens){
 $q=$pdo->prepare('SELECT canonical_role FROM demo_personas WHERE sandbox_id=UNHEX(?) AND organisation_id=UNHEX(?) AND role_key=?');$q->execute([$native['sandboxId'],$id['organisationId'],$key]);need($q->fetchColumn()===$lens['canonicalRole'],'Persona canonical mapping DB readback');
 $expectedCodes=array_map(static fn($k)=>$codeByKey[$k],$lens['projectKeys']);sort($expectedCodes);$expectedIds=array_map(static fn($c)=>$uuidByCode[$c],$expectedCodes);sort($expectedIds);
 $sel=callDemo($id,'PUT','/api/v1/demo/sandbox/role',['role'=>$key]);need($sel['status']===200&&$sel['json']['data']['selectedRole']===$key,'HTTP selection '.$key);need($sel['json']['data']['roleLens']===$lens,'Exact registry restrictions '.$key);
 $q=$pdo->prepare('SELECT selected_role FROM demo_sandboxes WHERE id=UNHEX(?) AND organisation_id=UNHEX(?)');$q->execute([$native['sandboxId'],$id['organisationId']]);need($q->fetchColumn()===$key,'Selected role DB readback');
 $list=callDemo($id,'GET','/api/v1/demo/sandbox/projects');need($list['status']===200,'HTTP list');$codes=array_column($list['json']['data'],'projectCode');sort($codes);$ids=array_column($list['json']['data'],'id');sort($ids);need($codes===$expectedCodes&&$ids===$expectedIds,'Exact HTTP allowlist '.$key);
 $n=$service->projects($id['organisationId'],$id['userId']);$ni=array_column($n,'id');sort($ni);need($ni===$expectedIds,'Native allowlist');
 $q=$pdo->prepare('SELECT LOWER(HEX(project_id)) FROM demo_project_role_assignments WHERE sandbox_id=UNHEX(?) AND organisation_id=UNHEX(?) AND role_key=?');$q->execute([$native['sandboxId'],$id['organisationId'],$key]);$stored=$q->fetchAll(PDO::FETCH_COLUMN);sort($stored);need($stored===$expectedIds,'Assignment DB readback');
 foreach($expectedIds as $p){$detail=callDemo($id,'GET','/api/v1/demo/sandbox/projects/'.$p);need($detail['status']===200&&$detail['json']['data']['id']===$p,'Visible detail HTTP');need($service->project($id['organisationId'],$id['userId'],$p)['id']===$p,'Visible detail native');}
 $outside=array_values(array_diff(array_values($uuidByCode),$expectedIds));$negative=$outside[0]??$foreign;$denied=callDemo($id,'GET','/api/v1/demo/sandbox/projects/'.$negative);need($denied['status']===404&&$denied['json']['error']['code']==='demo_project_not_visible','Negative HTTP');$caught=false;try{$service->project($id['organisationId'],$id['userId'],$negative);}catch(Architex\Platform\Demo\DemoException $e){$caught=true;}need($caught,'Native negative');
 $report['lenses'][$key]=['status'=>'PASS','canonicalRole'=>$lens['canonicalRole'],'toolCategories'=>$lens['toolCategories'],'expectedProjectKeys'=>$lens['projectKeys'],'expectedProjectCodes'=>$expectedCodes,'projectIds'=>$expectedIds,'visibleDetails200'=>count($expectedIds),'negativeStatus'=>$denied['status'],'negativeScope'=>$outside===[]?'foreign_organisation':'outside_lens','selectedRoleDbReadback'=>true,'assignmentRowsDbReadback'=>count($stored),'nativeService'=>'PASS','http'=>'PASS'];$save();
 }
 $learning=callDemo($id,'GET','/api/v1/demo/sandbox/learning');need($learning['status']===200&&$learning['json']['data']['relianceLabel']==='not_accredited_demo_record','Learning label');
 $report['expectedLensKeys']=array_keys($roles);$report['verifiedLensCount']=count($report['lenses']);$report['assignmentCount']=array_sum(array_column($report['lenses'],'assignmentRowsDbReadback'));$q=$pdo->prepare('SELECT COUNT(*) FROM demo_project_role_assignments WHERE sandbox_id=UNHEX(?)');$q->execute([$native['sandboxId']]);need((int)$q->fetchColumn()===$report['assignmentCount'],'All assignment count');$report['nativeService']='PASS';$report['http']='PASS';$save();echo json_encode(['verifiedLensCount'=>$report['verifiedLensCount'],'assignmentCount'=>$report['assignmentCount'],'nativeService'=>'PASS','http'=>'PASS','ui'=>$report['ui']]).PHP_EOL;
}catch(Throwable $e){$report['http']='FAIL';$report['failure']=$e->getMessage();$save();throw $e;}
