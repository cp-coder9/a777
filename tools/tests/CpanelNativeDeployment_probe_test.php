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
    foreach ([CURLOPT_URL=>'http://php.architex.co.za:80/'.$name,CURLOPT_RESOLVE=>['php.architex.co.za:80:169.239.218.73'],CURLOPT_INTERFACE=>'169.239.218.73',CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,CURLOPT_PROXY=>'',CURLOPT_NOPROXY=>'*',CURLOPT_NETRC=>CURL_NETRC_IGNORED,CURLOPT_HTTPHEADER=>['X-A777-Native-Probe: '.$secret],CURLOPT_PROTOCOLS=>CURLPROTO_HTTP,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTP,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>45] as $key=>$expected) {
        if (($options[$key]??null)!==$expected) throw new RuntimeException('loopback option scope');
    }
    foreach (['index.php',$name.'?x=1','../'.$name,'https://example.com/'.$name,'native-probe-'.str_repeat('a',47).'.php'] as $bad) {
        try { $invoke('webProbeLoopbackOptions',$bad,$secret); throw new LogicException('scope accepted'); } catch (RuntimeException) {}
    }
    try { $invoke('webProbeLoopbackOptions',$name,$secret."\r\nAuthorization: planted"); throw new LogicException('header injection accepted'); } catch (RuntimeException) {}
    if (!$invoke('webProbeFallback',['status'=>503],false) || $invoke('webProbeFallback',['status'=>503],true) || $invoke('webProbeFallback',['status'=>301],false)) throw new RuntimeException('fallback scope');
    if (!$invoke('webProbeIdentity',['probeId'=>$name],$name) || $invoke('webProbeIdentity',['probeId'=>'old'],$name) || $invoke('webProbeIdentity',[],$name)) throw new RuntimeException('stale proof accepted');
    // No substituted loopback listener establishes the pinned hosting-account self-route.
    echo "UNAVAILABLE native account-origin HTTP fixture: requires authenticated hosting account and locally assigned 169.239.218.73:80; not proven on Windows\n";
    foreach ([["169.239.218.73","169.239.218.73",true],["127.0.0.1","169.239.218.73",false],["169.239.218.73","127.0.0.1",false],["169.239.218.73","8.8.8.8",false],["8.8.8.8","169.239.218.73",false],[false,"169.239.218.73",false],["169.239.218.73:80","169.239.218.73",false]] as [$local,$peer,$expected]) {
        if ($invoke('accountOriginPeers',$local,$peer)!==$expected) throw new RuntimeException('account-origin peer binding');
    }
    foreach ([$options,$invoke('runtimeLoopbackOptions')] as $base) {
        $https=$invoke('accountOriginHttpsOptions',$base); $url=$https[CURLOPT_URL];
        foreach ([CURLOPT_INTERFACE=>'169.239.218.73',CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS] as $key=>$expected) {
            if ($https[$key]!==$expected) throw new RuntimeException('HTTPS pin/TLS contract');
        }
        if (!$invoke('accountOriginExpectedLocation',$url,$base) || !$invoke('accountOriginExpectedLocation',str_replace(':443/','/',$url),$base)) throw new RuntimeException('canonical upgrade refused');
        foreach ([$url.'?x=1',$url.'#fragment',str_replace(':443',':444',$url),str_replace('https:','http:',$url),str_replace('architex.co.za','evil.example',$url),'/same-path', $url.'/changed'] as $bad) {
            if ($invoke('accountOriginExpectedLocation',$bad,$base)) throw new RuntimeException('redirect scope escaped');
        }
        $host=parse_url($url,PHP_URL_HOST);
        if ($https[CURLOPT_RESOLVE]!==[$host.':443:169.239.218.73'] || $https[CURLOPT_HTTPHEADER]!==$base[CURLOPT_HTTPHEADER]) throw new RuntimeException('HTTPS target/header changed');
    }
    foreach ([200,301,302,503] as $status) if ($invoke('runtimeLoopbackDenied',['transport'=>'account-origin-https','status'=>$status,'peerVerified'=>true,'curlErrorCode'=>0])) throw new RuntimeException('non-denial accepted');
    foreach (['account-origin-http','account-origin-https'] as $transport) if (!$invoke('runtimeLoopbackDenied',['transport'=>$transport,'status'=>403,'peerVerified'=>true,'curlErrorCode'=>0])) throw new RuntimeException('denial refused');
    echo "PASS scoped HTTPS upgrade, TLS/interface/resolve pins and challenge refusal (synthetic contracts only)\n";
    $source=file_get_contents(dirname(__DIR__).'/CpanelNativeDeployment.php');
    if ($invoke('webProbeScriptMode',true)!==0644 || $invoke('webProbeScriptMode',false)!==0600) throw new RuntimeException('public nonce / legacy private script modes');
    // Synthetic stat-policy checks, not fabricated observations of Windows file modes.
    $scriptStat=['mode'=>0100644,'uid'=>123,'nlink'=>1];
    if (!$invoke('webProbeScriptStat',$scriptStat,123,0644)) throw new RuntimeException('public script stat policy');
    foreach (['mode'=>0100600,'uid'=>124,'nlink'=>2] as $key=>$bad) {
        $changed=$scriptStat; $changed[$key]=$bad;
        if ($invoke('webProbeScriptStat',$changed,123,0644)) throw new RuntimeException('unsafe public script stat accepted');
    }
    if ($invoke('webProbeScriptStat',false,123,0644) || $invoke('webProbeScriptStat',['mode'=>0120644,'uid'=>123,'nlink'=>1],123,0644)) throw new RuntimeException('nonregular public script accepted');
    foreach (['self::put($path,$code,$scriptMode)', 'self::replaceOwned($path,$code,$newCode,$scriptMode,$journal)', 'self::webProbeScriptStat(self::freshStat($path),posix_geteuid(),$scriptMode)', '!file_exists($path) && !is_link($path)', 'self::privateFile($proof)', 'chmod($proofPath,0600)', 'chmod($diagnosticPath,0600)', "(\$stat['mode']&07777)!==0700", "(\$stat['mode']&07777)!==0600"] as $required) {
        if (!str_contains($source,$required)) throw new RuntimeException('probe mode/cleanup/private policy missing');
    }
    echo "PASS public nonce 0644, legacy/private proof 0600, private directory 0700 and strict cleanup (synthetic policy/source assertions)\n";
    echo "UNAVAILABLE native public-script 0644/private-proof 0600 Apache execution fixture on Windows; host retry required\n";
    $preflightStart=strpos($source,'private static function accountOriginPreflight');
    $preflightEnd=strpos($source,'private static function runtimeLoopbackOptions',$preflightStart);
    $preflight=substr($source,$preflightStart,$preflightEnd-$preflightStart);
    foreach (["CpanelMaintenanceApi::inventory('list_databases')",'dns_get_record($host,DNS_A)','stream_socket_get_name($socket,false)','stream_socket_get_name($socket,true)'] as $required) {
        if (!str_contains($preflight,$required)) throw new RuntimeException('self-route preflight omitted');
    }
    foreach (['runtimeLoopbackHttp','webProbeLoopbackHttp'] as $method) {
        $start=strpos($source,'private static function '.$method); $end=strpos($source,'private static function ',$start+25);
        $body=substr($source,$start,$end-$start);
        if (strpos($body,'self::accountOriginPreflight();')===false || strpos($body,'self::accountOriginPreflight();')>strpos($body,'self::accountOriginRequest(')) throw new RuntimeException('secret transport precedes self-route proof');
    }
    echo "PASS fixed account-origin options, self-route ordering, header/path scope and stale proof binding (synthetic contracts only)\n";
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
    $fixed='/home/archite4/.a777-runtime-private/7eee8a25050fadb3d36b74c40f485061';
    $installed='/home/archite4/public_html/php.architex.co.za/'.$name;
    foreach ([$dir,$fixed.'/other','/home/archite4/.a777-runtime-private/other'] as $badRoot) {
        try { Deploy::webProbeCode($badRoot,'proof','diagnostic',$secret,str_repeat('0',64),$name,$installed); throw new LogicException('arbitrary root accepted'); } catch (RuntimeException) {}
    }
    foreach ([$installed.'.extra','/other/'.$name] as $badFile) {
        try { Deploy::webProbeCode($fixed,'proof','diagnostic',$secret,str_repeat('0',64),$name,$badFile); throw new LogicException('arbitrary file accepted'); } catch (RuntimeException) {}
    }
    $rule=$invoke('webProbeRule',$name,$secret,true);
    if ($rule!=="RewriteCond %{REQUEST_URI} !^/".str_replace('.','\\.',$name)."$\n" || str_contains($rule,$secret)) throw new RuntimeException('capability Apache scope');
    $pattern=substr(trim($rule),strlen('RewriteCond %{REQUEST_URI} !'));
    foreach (['/'.$name=>false,'/'=>true,'/index.php'=>true,'/app/index.php'=>true,'/api/v1/readiness'=>true,'/other/'.$name=>true,'/wrong-prefix-'.str_repeat('a',48).'.php'=>true,'/native-probe-'.str_repeat('c',48).'.php'=>true,'/'.$name.'.extra'=>true,'/'.$name.'/'=>true] as $uri=>$denied) {
        if ((preg_match('~'.$pattern.'~D',$uri)!==1)!==$denied) throw new RuntimeException('exact capability maintenance exclusion');
    }
    if ($invoke('webProbeRule',$name,$secret,false)!=="RewriteCond %{HTTP:X-A777-Native-Probe} ^$secret$\nRewriteRule ^".str_replace('.','\\.',$name)."$ - [END]\n") throw new RuntimeException('legacy gate changed');
    $gate=$invoke('gateBytes','fixture-lease');
    $temporary=str_replace("RewriteRule ^ - [R=503,L]",$rule."RewriteRule ^ - [R=503,L]",$gate);
    if (!str_contains($temporary,$rule."RewriteRule ^ - [R=503,L]\n") || str_contains($temporary,'[END]')) throw new RuntimeException('condition not directly on catchall');
    foreach ([['body'=>'Deployment maintenance','headers'=>['x-a777-deployment-lease'=>'fixture-lease']],['body'=>'','headers'=>[]],['body'=>'Deployment maintenance\n','headers'=>['x-a777-deployment-lease'=>'wrong']]] as $response) {
        foreach ([false,true] as $present) {
            $diagnostic=$invoke('webProbeResponse',$response,'fixture-lease',$present);
            $expected=['maintenanceBody'=>$response['body']==='Deployment maintenance','leaseMatched'=>($response['headers']['x-a777-deployment-lease']??null)==='fixture-lease','emptyBody'=>$response['body']==='','proofPresent'=>$present];
            if ($diagnostic!==$expected || count(array_filter($diagnostic,'is_bool'))!==4) throw new RuntimeException('response boolean whitelist');
        }
    }
    foreach (["'/web-probe-response.json'",'self::webProbeResponse($r,$lease,is_file($proof))','self::put($responsePath,$responseBytes,0600)'] as $required) {
        if (!str_contains($source,$required)) throw new RuntimeException('private actual-response receipt missing');
    }
    echo "PASS exact nonce catchall exclusion, ordinary/wrong-path denial and response boolean whitelist (synthetic contracts)\n";
    foreach (['valid','uri','method','query','uri_query','filename','hash','unhealthy'] as $case) {
        $proof=$dir.'/proof.json'; $preflight=$dir.'/web-probe-preflight.json'; @unlink($proof); @unlink($preflight);
        file_put_contents($dir.'/.htaccess','fixed gate');
        $status=$case==='unhealthy'?503:200;
        $ready=$case==='unhealthy'?'not_ready':'ready';
        file_put_contents($dir.'/public/index.php',"<?php http_response_code($status); echo json_encode(['data'=>['status'=>'$ready','components'=>array_fill_keys(['database','migrations','jobs','mail'],['status'=>'$ready'])]]); exit;");
        file_put_contents($dir.'/runner.php',''); $localFile=realpath($dir.'/runner.php');
        $code=Deploy::webProbeCode($fixed,$proof,$preflight,$secret,$case==='hash'?str_repeat('0',64):hash('sha256','fixed gate'),$name,$installed);
        // Synthetic subprocess fixture: substitute only validated fixed literals, never production scope.
        $code=str_replace([var_export($fixed,true),var_export($installed,true)],[var_export($dir,true),var_export($localFile,true)],$code);
        $server=['REQUEST_URI'=>$case==='uri'?'/wrong':('/'.$name.($case==='uri_query'?'?x=1':'')), 'REQUEST_METHOD'=>$case==='method'?'POST':'GET','QUERY_STRING'=>$case==='query'?'x=1':'','SCRIPT_FILENAME'=>$case==='filename'?$localFile.'.wrong':$localFile];
        $code=str_replace('declare(strict_types=1);','declare(strict_types=1); $_SERVER='.var_export($server,true).'; register_shutdown_function(static function(){ file_put_contents('.var_export($dir.'/status.json',true).',json_encode(http_response_code())); });',$code);
        file_put_contents($dir.'/runner.php',$code);
        $process=proc_open([PHP_BINARY,'-n',$dir.'/runner.php'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
        fclose($pipes[0]); $output=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process)!==0 || $error!=='') throw new RuntimeException('capability fixture execution failed: '.$case.' '.$error);
        $accepted=in_array($case,['valid','unhealthy'],true);
        if (is_file($proof)!==$accepted) throw new RuntimeException('capability proof authorization failed: '.$case);
        if (!$accepted && json_decode(file_get_contents($dir.'/status.json'),true)!==503) throw new RuntimeException('capability refusal status');
        if ($accepted) {
            $p=json_decode(file_get_contents($proof),true,32,JSON_THROW_ON_ERROR);
            if ($p['status']!==$status || !$invoke('webProbeIdentity',$p,$name) || json_decode($output,true)!==$p) throw new RuntimeException('capability status/readback/binding');
            if ($case==='valid') $invoke('httpReady',$p);
            else { try { $invoke('httpReady',$p); throw new LogicException('unhealthy accepted'); } catch (RuntimeException) {} }
        }
        echo 'PASS URL capability '.$case." (synthetic subprocess)\n";
    }
    echo "PASS returning and exiting index: private proof, real 503, redacted body\n";
} finally {
    @unlink($dir.'/.htaccess');
    foreach (glob($dir.'/public/*') as $file) unlink($file);
    foreach (glob($dir.'/*') as $file) if(is_file($file)) unlink($file);
    rmdir($dir.'/public'); rmdir($dir);
}
