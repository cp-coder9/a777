<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/CpanelNativeDeployment.php';
use Architex\Deployment\CpanelNativeDeployment as Deploy;
$dir=sys_get_temp_dir().'/native-probe-test-'.bin2hex(random_bytes(8));
mkdir($dir.'/public',0700,true);
try {
    foreach ([false,true,'fence'] as $terminate) {
        $proof=$dir.'/proof.json'; @unlink($proof);
        file_put_contents($dir.'/public/build-info.json','{"sourceCommit":"fixture","sourceDirty":false,"environment":"production"}');
        file_put_contents($dir.'/public/index.php',"<?php http_response_code(503); echo json_encode(['data'=>['status'=>'not_ready','components'=>['database'=>['status'=>'unavailable']]],'private'=>'MUST_NOT_ESCAPE']);".($terminate?' exit;':''));
        if ($terminate==='fence') {
            file_put_contents($dir.'/lease.json',json_encode(['owner'=>'fixture','fingerprint'=>'fixture','phase'=>'maintenance']));
            $fence=dirname(__DIR__,2).'/app/src/Infrastructure/Deployment/MaintenanceFence.php';
            file_put_contents($dir.'/public/index.php','<?php require '.var_export($fence,true).'; try { (new \\Architex\\Infrastructure\\Deployment\\MaintenanceFence('.var_export($dir,true).'))->enter(); } catch (Throwable) { http_response_code(503); exit("Application maintenance in progress."); }');
        }
        $code=Deploy::webProbeCaptureCode($dir,$proof);
        file_put_contents($dir.'/runner.php',"<?php\n".$code);
        $pipes=[]; $process=proc_open([PHP_BINARY,$dir.'/runner.php'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
        fclose($pipes[0]); $output=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        if(proc_close($process)!==0 || $error!=='' || !is_file($proof)) throw new RuntimeException('proof absent after index termination: '.$error);
        $data=json_decode($output,true,32,JSON_THROW_ON_ERROR);
        if($data['status']!==503 || json_decode(file_get_contents($proof),true)!==$data || str_contains($output,'MUST_NOT_ESCAPE')) throw new RuntimeException('status/readback/redaction failed');
    }
    echo "PASS returning and exiting index: private proof, real 503, redacted body\n";
} finally {
    foreach (glob($dir.'/public/*') as $file) unlink($file);
    foreach (glob($dir.'/*') as $file) if(is_file($file)) unlink($file);
    rmdir($dir.'/public'); rmdir($dir);
}
