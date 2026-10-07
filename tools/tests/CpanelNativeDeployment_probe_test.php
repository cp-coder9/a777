<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/CpanelNativeDeployment.php';
use Architex\Deployment\CpanelNativeDeployment as Deploy;
$dir=sys_get_temp_dir().'/native-probe-test-'.bin2hex(random_bytes(8));
mkdir($dir.'/public',0700,true);
try {
    $invoke=static fn(string $method,...$args)=>(new ReflectionMethod(Deploy::class,$method))->invoke(null,...$args);
    $name='native-probe-'.str_repeat('a',48).'.php'; $secret=str_repeat('b',64);
    $options=$invoke('webProbeLoopbackOptions',$name,$secret);
    foreach ([CURLOPT_URL=>'http://php.architex.co.za:80/'.$name,CURLOPT_RESOLVE=>['php.architex.co.za:80:127.0.0.1'],CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,CURLOPT_PROXY=>'',CURLOPT_NOPROXY=>'*',CURLOPT_NETRC=>CURL_NETRC_IGNORED,CURLOPT_HTTPHEADER=>['X-A777-Native-Probe: '.$secret],CURLOPT_PROTOCOLS=>CURLPROTO_HTTP,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTP,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>45] as $key=>$expected) {
        if (($options[$key]??null)!==$expected) throw new RuntimeException('loopback option scope');
    }
    foreach (['index.php',$name.'?x=1','../'.$name,'https://example.com/'.$name,'native-probe-'.str_repeat('a',47).'.php'] as $bad) {
        try { $invoke('webProbeLoopbackOptions',$bad,$secret); throw new LogicException('scope accepted'); } catch (RuntimeException) {}
    }
    try { $invoke('webProbeLoopbackOptions',$name,$secret."\r\nAuthorization: planted"); throw new LogicException('header injection accepted'); } catch (RuntimeException) {}
    if (!$invoke('webProbeFallback',['status'=>503],false) || $invoke('webProbeFallback',['status'=>503],true) || $invoke('webProbeFallback',['status'=>301],false)) throw new RuntimeException('fallback scope');
    if (!$invoke('webProbeIdentity',['probeId'=>$name],$name) || $invoke('webProbeIdentity',['probeId'=>'old'],$name) || $invoke('webProbeIdentity',[],$name)) throw new RuntimeException('stale proof accepted');
    // Real curl, fixed port 80 and virtualhost. Never borrow an existing listener.
    $listener=@stream_socket_server('tcp://127.0.0.1:80',$errno,$errstr);
    if ($listener===false) { echo "UNAVAILABLE real loopback fixture: port 80 in use or inaccessible\n"; }
    else {
        fclose($listener);
        file_put_contents($dir.'/router.php','<?php $status=(int)file_get_contents(__DIR__."/status"); if ($_SERVER["HTTP_HOST"]!=="php.architex.co.za" && $_SERVER["HTTP_HOST"]!=="php.architex.co.za:80") { http_response_code(400); exit; } if (($_SERVER["HTTP_X_A777_NATIVE_PROBE"]??"")!=='.var_export($secret,true).') { http_response_code(401); exit; } http_response_code($status); if ($status===301) header("Location: https://example.invalid/no-follow"); echo $status===200?str_repeat("x",(int)file_get_contents(__DIR__."/size")):"fixture";');
        file_put_contents($dir.'/status','200'); file_put_contents($dir.'/size','2');
        $server=proc_open([PHP_BINARY,'-S','127.0.0.1:80',$dir.'/router.php'],[['pipe','r'],['file',$dir.'/server.out','a'],['file',$dir.'/server.err','a']],$serverPipes);
        if (!is_resource($server)) throw new RuntimeException('local fixture launch');
        fclose($serverPipes[0]);
        try {
            $ready=false;
            for ($attempt=0;$attempt<30;$attempt++) { $socket=@stream_socket_client('tcp://127.0.0.1:80',$errno,$errstr,0.1); if ($socket) { fclose($socket); $ready=true; break; } usleep(100000); }
            if (!$ready) throw new RuntimeException('local fixture unavailable');
            foreach ([200,301,401,503] as $status) {
                file_put_contents($dir.'/status',(string)$status);
                $r=$invoke('webProbeLoopbackHttp',$name,$secret);
                if ($r['status']!==$status || $r['curlErrorCode']!==0 || !$r['peerVerified']) throw new RuntimeException('real curl status/peer');
                echo 'PASS real loopback curl '.$status."\n";
            }
            file_put_contents($dir.'/status','200'); file_put_contents($dir.'/size','1048577');
            if ($invoke('webProbeLoopbackHttp',$name,$secret)['curlErrorCode']===0) throw new RuntimeException('body bound bypass');
            echo "PASS real loopback body bound\n";
        } finally { proc_terminate($server); proc_close($server); }
    }
    echo "PASS fixed loopback options, header/path scope and stale proof binding\n";
    foreach ([false,true,'fence'] as $terminate) {
        $proof=$dir.'/proof.json'; @unlink($proof);
        file_put_contents($dir.'/public/build-info.json','{"sourceCommit":"fixture","sourceDirty":false,"environment":"production"}');
        file_put_contents($dir.'/public/index.php',"<?php http_response_code(503); echo json_encode(['data'=>['status'=>'not_ready','components'=>['database'=>['status'=>'unavailable']]],'private'=>'MUST_NOT_ESCAPE']);".($terminate?' exit;':''));
        if ($terminate==='fence') {
            file_put_contents($dir.'/lease.json',json_encode(['owner'=>'fixture','fingerprint'=>'fixture','phase'=>'maintenance']));
            $fence=dirname(__DIR__,2).'/app/src/Infrastructure/Deployment/MaintenanceFence.php';
            file_put_contents($dir.'/public/index.php','<?php require '.var_export($fence,true).'; try { (new \\Architex\\Infrastructure\\Deployment\\MaintenanceFence('.var_export($dir,true).'))->enter(); } catch (Throwable) { http_response_code(503); exit("Application maintenance in progress."); }');
        }
        $code=Deploy::webProbeCaptureCode($dir,$proof,'',$name);
        file_put_contents($dir.'/runner.php',"<?php\n".$code);
        $pipes=[]; $process=proc_open([PHP_BINARY,$dir.'/runner.php'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
        fclose($pipes[0]); $output=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        if(proc_close($process)!==0 || $error!=='' || !is_file($proof)) throw new RuntimeException('proof absent after index termination: '.$error);
        $data=json_decode($output,true,32,JSON_THROW_ON_ERROR);
        if($data['probeId']!==$name || $data['status']!==503 || json_decode(file_get_contents($proof),true)!==$data || str_contains($output,'MUST_NOT_ESCAPE')) throw new RuntimeException('status/readback/redaction failed');
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
