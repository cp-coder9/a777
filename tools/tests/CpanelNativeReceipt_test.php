<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/cpanel_git_deploy.php';
$n=0;
function receiptCheck(bool $ok,string $label): void { global $n; if (!$ok) throw new RuntimeException($label); $n++; }
$base=['status'=>'failed','lastCompletedStage'=>null,'sourceDatabaseDdlTouched'=>false,'sourceMutationDdlStarted'=>false,'maintenanceActive'=>false,'maintenanceRetained'=>false,'sourcePrivilegesRevoked'=>false,'sourcePrivilegeStateUnknown'=>false,'operatorStateUnknown'=>false,'operatorPrivilegesRevoked'=>true,'artifactVerified'=>false,'readinessPassed'=>false];
$commit=str_repeat('a',40); $sha=str_repeat('b',64);
$r=CpanelGitDeploy::nativeReceipt($base,$commit,$sha);
receiptCheck($r['phase']==='failed' && !$r['deployed'],'before any source effects safe failure');
foreach (['maintenanceActive','maintenanceRetained','sourcePrivilegesRevoked','sourcePrivilegeStateUnknown','operatorStateUnknown','sourceDatabaseDdlTouched','sourceMutationDdlStarted'] as $key) {
    $r=CpanelGitDeploy::nativeReceipt(array_replace($base,[$key=>true]),$commit,$sha);
    receiptCheck($r['phase']==='recovery_required' && !$r['deployed'],$key.' prevents safe failure');
}
$r=CpanelGitDeploy::nativeReceipt(array_replace($base,['status'=>'deployed','lastCompletedStage'=>'promoted']),$commit,$sha);
receiptCheck($r['phase']==='failed' && !$r['deployed'],'status alone cannot complete');
$r=CpanelGitDeploy::nativeReceipt(array_replace($base,['status'=>'deployed','lastCompletedStage'=>'deployed','sourceDatabaseDdlTouched'=>true,'sourceMutationDdlStarted'=>true,'artifactVerified'=>true,'readinessPassed'=>true,'sourceMigrationCount'=>8,'artifactTreeSha256'=>str_repeat('c',64),'artifactManifestSha256'=>str_repeat('d',64),'buildSha256'=>str_repeat('e',64)]),$commit,$sha);
receiptCheck($r['phase']==='complete' && $r['deployed'],'actual complete terminal');
receiptCheck($r['sourceMigrationCount']===8 && $r['buildSha256']===str_repeat('e',64),'evidence preserved');
receiptCheck($r['format']==='a777-cpanel-native-deploy-v1' && $r['mode']==='native-deploy','runner contract');
receiptCheck(CpanelGitDeploy::nativeReceipt(['status'=>'failed'],$commit,$sha)['phase']==='recovery_required','missing effects unknown not safe');
receiptCheck(CpanelGitDeploy::nativeReceipt(array_replace($base,['operatorPrivilegesRevoked'=>false]),$commit,$sha)['phase']==='recovery_required','unrevoked operator retains recovery');
echo "PASS $n wrapper assertions; fixture values only; no provider/database calls\n";
echo json_encode($r,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
