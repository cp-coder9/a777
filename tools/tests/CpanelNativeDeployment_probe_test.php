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
    foreach (['header_absent','header','hash','none','disabled','functions_disabled'] as $case) {
        $proof=$dir.'/proof.json'; $preflight=$dir.'/web-probe-preflight.json'; @unlink($proof); @unlink($preflight);
        file_put_contents($dir.'/.htaccess','fixed gate');
        file_put_contents($dir.'/public/index.php',"<?php http_response_code(503); echo json_encode(['data'=>['status'=>'not_ready','components'=>['database'=>['status'=>'unavailable']]],'private'=>'MUST_NOT_ESCAPE']); exit;");
        $code=Deploy::webProbeCode($dir,$proof,$preflight,'fixture-secret',$case==='hash'?str_repeat('0',64):hash('sha256','fixed gate'));
        $code=str_replace('declare(strict_types=1);','declare(strict_types=1); $_SERVER[\'HTTP_X_A777_NATIVE_PROBE\']='.var_export($case==='header_absent'?'':($case==='header'?'wrong':'fixture-secret'),true).';',$code);
        file_put_contents($dir.'/runner.php',$code);
        $command=[PHP_BINARY,'-n'];
        if (in_array($case,['disabled','functions_disabled'],true)) {
            $extension=ini_get('extension_dir').(PHP_OS_FAMILY==='Windows'?'/php_opcache.dll':'/opcache.so');
            if (!is_file($extension)) throw new RuntimeException('OPcache fixture extension missing');
            array_push($command,'-d','zend_extension='.$extension,'-d','opcache.enable=0');
            if ($case==='functions_disabled') array_push($command,'-d','disable_functions=opcache_invalidate');
        }
        $command[]=$dir.'/runner.php';
        $pipes=[]; $process=proc_open($command,[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
        fclose($pipes[0]); $output=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        if(proc_close($process)!==0 || $error!=='' || !is_file($preflight)) throw new RuntimeException('preflight fixture failed: '.$case.' '.$error);
        $diagnostic=json_decode(file_get_contents($preflight),true,32,JSON_THROW_ON_ERROR);
        if (array_keys($diagnostic)!==['headerPresent','headerMatched','gateMatched','opcacheAvailable','probeStage'] || str_contains(file_get_contents($preflight),'fixture-secret') || str_contains($output,'MUST_NOT_ESCAPE')) throw new RuntimeException('diagnostic whitelist failed');
        $authorized=!in_array($case,['header_absent','header','hash'],true);
        if(is_file($proof)!==$authorized || $diagnostic['probeStage']!==($authorized?($case==='functions_disabled'?'opcache':'bootstrap'):'authorization')) throw new RuntimeException('capture ordering failed: '.$case);
        if ($authorized && json_decode(file_get_contents($proof),true)['status']!==503) throw new RuntimeException('false healthy proof');
        if ($case==='functions_disabled' && json_decode(json_decode(file_get_contents($proof),true)['body'],true)['data']['status']!==null) throw new RuntimeException('bootstrap ran despite disabled invalidator');
        echo 'PASS preflight '.$case."\n";
    }
    echo "PASS returning and exiting index: private proof, real 503, redacted body\n";
} finally {
    @unlink($dir.'/.htaccess');
    foreach (glob($dir.'/public/*') as $file) unlink($file);
    foreach (glob($dir.'/*') as $file) if(is_file($file)) unlink($file);
    rmdir($dir.'/public'); rmdir($dir);
}
