<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/cpanel_git_deploy.php';
require_once dirname(__DIR__).'/CpanelNativeDeploymentRecovery.php';
function diagCheck(bool $ok): void { if (!$ok) throw new RuntimeException('diagnostic_test_failed'); }
try { \Architex\Deployment\CpanelNativeRecovery::validateReceipt([],str_repeat('a',64)); } catch (Throwable $e) {
    $d=\Architex\Deployment\CpanelNativeRecovery::safeFailure($e);
    diagCheck($d['reason']==='recovery_evidence_refused');
    diagCheck($d['sourceFile']==='CpanelNativeRecovery.php' && $d['sourceLine']>21);
    diagCheck(array_keys($d)===['exceptionType','sourceFile','sourceFunction','sourceLine','reason']);
}
$d=\Architex\Deployment\CpanelNativeRecovery::safeFailure(new RuntimeException('password=DO_NOT_EXPORT /private/config.php'));
diagCheck($d['reason']==='unclassified' && $d['sourceFile']===null && $d['sourceFunction']===null && $d['sourceLine']===null);
diagCheck(!str_contains(json_encode($d),'DO_NOT_EXPORT') && !str_contains(json_encode($d),'config.php'));
$w=file_get_contents(dirname(__DIR__).'/CpanelNativeDeploymentRecovery.php');
diagCheck(str_contains($w,"'/recoverydiag-'"));
diagCheck(str_contains($w,"$"."outcome['failureDiagnostic']"));
echo "PASS safe recovery diagnostics assertions\n";
