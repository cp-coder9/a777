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
$r=['status'=>'recovery_required','lastCompletedStage'=>'sourceDDL','failureStage'=>'promotion','sourceMigrationCount'=>8,'sourceDatabaseDdlTouched'=>true,'operatorPrivilegesRevoked'=>true,'operatorStateUnknown'=>false,'maintenanceRetained'=>true,'commit'=>'6f21a24c6fc7bca06b14d4d48d352d12135a2f9f','deploymentId'=>'7eee8a25050fadb3d36b74c40f485061','archiveSha256'=>'5b9e44eb47a24c7f0d47d11609e8c53564d54971379fe8d91fd7da5bb71ed87e','planSha256'=>$sha];
R::validateReceipt($r,$sha); check(true);
foreach (array_keys($r) as $key) { $bad=$r; unset($bad[$key]); check(refused(fn()=>R::validateReceipt($bad,$sha))); }
check(refused(fn()=>R::validateReceipt($r,str_repeat('0',64))));
$fail=R::run('/not-private','/not-private',str_repeat('a',40),$sha); check($fail['status']==='recovery_required' && $fail['sourceMigrationsReplayed']===false && $fail['protectedTokenRetained']===true);
echo "PASS $n synthetic recovery assertions; no provider or database accessed\n";
