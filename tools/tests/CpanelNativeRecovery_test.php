<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/CpanelNativeRecovery.php';
use Architex\Deployment\CpanelNativeRecovery as R;
$n=0;
function check(bool $ok): void { global $n; if (!$ok) throw new RuntimeException('test_failed'); ++$n; }
function refused(callable $f): bool { try { $f(); } catch (RuntimeException) { return true; } return false; }
$states=[[true,true,false,true,true,false],[true,false,true,true,true,false],[false,true,true,true,true,false],[false,true,true,true,false,true],[false,true,true,false,true,true]];
for ($mask=0;$mask<64;$mask++) { $bits=[]; for ($j=0;$j<6;$j++) $bits[]=(bool)($mask&(1<<$j)); $i=array_search($bits,$states,true); if ($i===false) check(refused(fn()=>R::renameState(...$bits))); else check(R::renameState(...$bits)===$i); }
$expected=['public/index.php'=>['sha256'=>str_repeat('a',64),'bytes'=>10],'src/A.php'=>['sha256'=>str_repeat('b',64),'bytes'=>20]];
$actual=array_reverse($expected,true)+['config/runtime.php'=>['sha256'=>'private','bytes'=>50],'.htaccess'=>['sha256'=>'gate','bytes'=>10]];
R::compareInventory($expected,$actual); check(true);
$actual['src/A.php']['sha256']=str_repeat('c',64); check(refused(fn()=>R::compareInventory($expected,$actual)));
$actual=$expected+['unknown.php'=>['sha256'=>'evil','bytes'=>5]]; check(refused(fn()=>R::compareInventory($expected,$actual)));
check(refused(fn()=>R::compareInventory($expected,['public/index.php'=>$expected['public/index.php']])));
$sha=str_repeat('f',64);
$r=['format'=>'a777-cpanel-native-deploy-v1','phase'=>'recovery_required','lastCompletedStage'=>'sourceDDL','failureStage'=>'promotion','sourceMigrationCount'=>8,'sourceDatabaseDdlTouched'=>true,'operatorPrivilegesRevoked'=>true,'operatorStateUnknown'=>false,'maintenanceRetained'=>true,'commit'=>'6f21a24c6fc7bca06b14d4d48d352d12135a2f9f','deploymentId'=>'7eee8a25050fadb3d36b74c40f485061','archiveSha256'=>'5b9e44eb47a24c7f0d47d11609e8c53564d54971379fe8d91fd7da5bb71ed87e','planSha256'=>$sha];
R::validateReceipt($r,$sha); check(true);
foreach (array_keys($r) as $key) { $bad=$r; unset($bad[$key]); check(refused(fn()=>R::validateReceipt($bad,$sha))); }
check(refused(fn()=>R::validateReceipt($r,str_repeat('0',64))));
$fail=R::run('/not-private','/not-private',str_repeat('a',40),$sha); check($fail['status']==='recovery_required' && $fail['sourceMigrationsReplayed']===false && $fail['protectedTokenRetained']===true);
require_once dirname(__DIR__,2).'/app/src/Infrastructure/Database/SqlStatementLexer.php';
$ids=['0062_wingman_tutorial_progress','0063_v1_feedback_records','0064_v1_practice_programme_milestones_fees','0065_v1_module_records','0066_v1_refuse_area_calculator','0067_production_integration_lifecycles','0069_internal_work_permits','0070_toolbox_pdf_exports'];
$tables=R::affectedTables(dirname(__DIR__,2).'/app/database/migrations',$ids); check(in_array('tool_run_exports',$tables,true)); check(count($tables)>8);
check(refused(fn()=>R::affectedTables(dirname(__DIR__,2).'/app/database/migrations',array_slice($ids,1))));
$target='archite4_ar_0123456789abcdef_test';
$bound=$r+['backupId'=>str_repeat('b',32)];
$source=['phase'=>'sourceDDL','targetDatabase'=>$target]+$bound;
$provision=['phase'=>'target-provisioned','receipt'=>['account'=>'archite4','managed'=>true,'source'=>'archite4_dev_rel1','target'=>$target,'user'=>'archite4_fixture','binding'=>['source'=>'archite4_dev_rel1','user'=>'archite4_fixture','runtimeSha256'=>'5206e3a1f9447e6834fc3e2dd7cd254ce5dd47af33406c0c83085c4b4f28d0b8']]];
check(R::journalTarget([$provision,$source],$bound,'archite4_fixture')===$target);
foreach (['deploymentId','planSha256','archiveSha256','backupId','targetDatabase'] as $key) { $bad=$source; $bad[$key]='wrong'; check(refused(fn()=>R::journalTarget([$provision,$bad],$bound,'archite4_fixture'))); }
check(refused(fn()=>R::journalTarget([$provision,$source,$source],$bound,'archite4_fixture')));
check(refused(fn()=>R::journalTarget([$source],$bound,'archite4_fixture')));
$a="CREATE TABLE `t` (`v` varchar(50) DEFAULT ' AUTO_INCREMENT=123', FOREIGN KEY (`v`) REFERENCES `archite4_dev_rel1`.`x` (`v`)) ENGINE=InnoDB AUTO_INCREMENT=42";
$b=str_replace(['`archite4_dev_rel1`','AUTO_INCREMENT=42'],['`'.$target.'`','AUTO_INCREMENT=99'],$a);
check(R::normalizeDefinition($a,'archite4_dev_rel1',$target)===R::normalizeDefinition($b,'archite4_dev_rel1',$target));
check(str_contains(R::normalizeDefinition($a,'archite4_dev_rel1',$target),"' AUTO_INCREMENT=123'"));
check(R::normalizeDefinition(str_replace('`archite4_dev_rel1`','`external_db`',$a),'archite4_dev_rel1',$target)!==R::normalizeDefinition($b,'archite4_dev_rel1',$target));
check(refused(fn()=>R::auditApis(static fn($path)=>['status'=>500,'body'=>'{}'])));
$responses=['/api/v1/health'=>['status'=>200,'body'=>'{"data":{"status":"ok"}}'],'/v1-candidate.php'=>['status'=>401],'/app.php'=>['status'=>200],'/maintenance.php'=>['status'=>404]];
$notices=[]; foreach (['terms_of_service','privacy_policy','platform_disclaimer','signup_privacy_notice'] as $type) $notices[]=['noticeType'=>$type,'version'=>'synthetic-1','title'=>'Synthetic fixture','contentSha256'=>str_repeat('a',64),'effectiveAt'=>'2026-01-01T00:00:00Z','href'=>'/fixture'];
$responses['/api/v1/legal-notices/signup']=['status'=>200,'body'=>json_encode(['data'=>$notices])];
R::auditApis(static fn($path)=>$responses[$path]); check(true);
foreach (array_keys($responses) as $path) { $bad=$responses; $bad[$path]['status']=500; check(refused(fn()=>R::auditApis(static fn($uri)=>$bad[$uri]))); }
$bad=$responses; $bad['/api/v1/health']['body']='{"data":{"status":"ready"}}'; check(refused(fn()=>R::auditApis(static fn($uri)=>$bad[$uri])));
$bad=$responses; $bad['/api/v1/legal-notices/signup']['body']='{"data":[]}'; check(refused(fn()=>R::auditApis(static fn($uri)=>$bad[$uri])));
check((new ReflectionClass(\Architex\Infrastructure\Database\SqlStatementLexer::class))->getFileName()===realpath(dirname(__DIR__,2).'/app/src/Infrastructure/Database/SqlStatementLexer.php'));
echo "PASS $n synthetic recovery assertions; real canonical SQL lexer, no provider or database accessed\n";
