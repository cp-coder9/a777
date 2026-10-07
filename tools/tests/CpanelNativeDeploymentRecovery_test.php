<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/cpanel_git_deploy.php';
require_once dirname(__DIR__).'/CpanelNativeDeploymentRecovery.php';
$n=0;
function assertRecovery(bool $ok): void { global $n; if (!$ok) throw new RuntimeException('wrapper_test_failed'); ++$n; }
function recoveryRefused(callable $f): bool { try {$f();} catch(Throwable) {return true;} return false; }
$p=['expectedRecoveryCommit'=>str_repeat('a',40),'releaseCommit'=>str_repeat('b',40),'oldPlanSha256'=>str_repeat('c',64),'recoveredDeploymentId'=>str_repeat('d',32)];
$o=['status'=>'recovered_and_deployed','sourceAppliedPriorCount'=>8,'sourceMigrationsReplayed'=>false,'maintenanceRetained'=>false];
foreach(['sourceAlreadyCurrent','appPrivilegesRestored','operatorPrivilegesRevoked','appConfigDeltaVerified','readinessPassed','artifactVerified'] as $k) $o[$k]=true;
foreach(['operatorStateUnknown','sourcePrivilegeStateUnknown'] as $k) $o[$k]=false;
foreach(['archiveSha256','artifactTreeSha256','artifactManifestSha256','buildSha256'] as $k) $p[$k]=$o[$k]=str_repeat('f',64);
$p['backupId']=$o['backupId']='synthetic-backup';
$r=CpanelNativeDeploymentRecovery::receipt($o,$p,str_repeat('e',64));
assertRecovery($r['phase']==='complete' && $r['deployed'] && count($r)===27);
assertRecovery($r['planSha256']!==$r['oldPlanSha256'] && $r['commit']===$p['expectedRecoveryCommit'] && $r['releaseCommit']===$p['releaseCommit']);
foreach(array_keys($o) as $key) { $bad=$o; unset($bad[$key]); $r=CpanelNativeDeploymentRecovery::receipt($bad,$p,str_repeat('e',64)); assertRecovery($r['phase']==='recovery_required' && !$r['deployed']); }
$r=CpanelNativeDeploymentRecovery::receipt(['status'=>'recovered_and_deployed'],$p,str_repeat('e',64)); assertRecovery(!$r['deployed']);
$code=file_get_contents(dirname(__DIR__).'/cpanel_git_deploy.php');
assertRecovery(strpos($code,"$"."pointer=$"."root.'/recovery-plan-pointer.json'")<strpos($code,"$"."planPath = $"."root . '/operator-plan.json'"));
echo "PASS $n synthetic wrapper assertions\n";
if (PHP_OS_FAMILY!=='Linux') {echo "UNAVAILABLE native Linux filesystem assertions\n"; exit(0);}
$root=getenv('TMPDIR')?:sys_get_temp_dir();
$d=$root.'/a777-recovery-fixture-'.bin2hex(random_bytes(8)); mkdir($d,0700);
$f=$d.'/plan.json'; file_put_contents($f,"{\"fixture\":true}\n"); chmod($f,0600);
try {
    assertRecovery(CpanelNativeDeploymentRecovery::privateBytes($f)==="{\"fixture\":true}\n");
    chmod($f,0644); assertRecovery(recoveryRefused(fn()=>CpanelNativeDeploymentRecovery::privateBytes($f))); chmod($f,0600);
    link($f,$d.'/hardlink'); assertRecovery(recoveryRefused(fn()=>CpanelNativeDeploymentRecovery::privateBytes($f))); unlink($d.'/hardlink');
    symlink($f,$d.'/symlink'); assertRecovery(recoveryRefused(fn()=>CpanelNativeDeploymentRecovery::privateBytes($d.'/symlink'))); unlink($d.'/symlink');
    chmod($d,0755); assertRecovery(recoveryRefused(fn()=>CpanelNativeDeploymentRecovery::privateBytes($f))); chmod($d,0700);
    echo "PASS native Linux filesystem metadata, symlink, hardlink and mode refusals\n";
} finally {unlink($f);rmdir($d);}
