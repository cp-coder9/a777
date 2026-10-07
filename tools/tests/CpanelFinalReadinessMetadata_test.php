<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/CpanelNativeDeployment.php';
use Architex\Deployment\CpanelNativeDeployment as D;
function checkFinal(bool $ok): void { if (!$ok) throw new RuntimeException('final_metadata_failed'); }
$components=[]; foreach(['database','migrations','jobs','mail'] as $n) $components[$n]=['status'=>'ready','secret'=>'DO_NOT_EXPORT'];
$r=['status'=>200,'body'=>json_encode(['data'=>['status'=>'ready','components'=>$components],'secret'=>'DO_NOT_EXPORT']),'headers'=>['x-a777-deployment-lease'=>'DO_NOT_EXPORT']];
$m=D::readinessMetadata($r);
checkFinal(array_keys($m)===['status','hasJson','readinessStatus','components','maintenanceLeasePresent']);
checkFinal($m['status']===200 && $m['hasJson'] && $m['readinessStatus']==='ready' && $m['maintenanceLeasePresent']);
checkFinal(array_keys($m['components'])===['database','migrations','jobs','mail'] && count(array_filter($m['components'],static fn($v)=>$v==='ready'))===4);
checkFinal(!str_contains(json_encode($m),'DO_NOT_EXPORT'));
foreach(['Deployment maintenance','null','"secret"','{invalid'] as $body) {
 $m=D::readinessMetadata(['status'=>503,'body'=>$body]);
 checkFinal($m['status']===503 && !$m['hasJson'] && $m['readinessStatus']===null && !$m['maintenanceLeasePresent']);
}
$m=D::readinessMetadata(['status'=>'200','body'=>'{"data":{"status":"secret","components":{"database":{"status":"secret"}}}}']);
checkFinal($m['status']===0 && $m['readinessStatus']===null && $m['components']['database']===null);
$ready=new ReflectionMethod(D::class,'httpReady');
try { $ready->invoke(null,['status'=>'200','body'=>$r['body']]); throw new LogicException('string accepted'); } catch(RuntimeException $e) { checkFinal(D::safeFailureCode($e)==='readiness_http'); }
try { $ready->invoke(null,['status'=>503,'body'=>$r['body']]); throw new LogicException('503 accepted'); } catch(RuntimeException $e) { checkFinal(D::safeFailureCode($e)==='readiness_http'); }
require_once dirname(__DIR__).'/CpanelNativeDeploymentRecovery.php';
use Architex\Deployment\CpanelNativeRecovery as R;
$writer=new ReflectionMethod(R::class,'writeReadinessMetadata');
try {
 $writer->invoke(null,__DIR__.'/nonexistent-final-metadata-dir',['status'=>503,'body'=>'secret'],[
  'put'=>static function() use ($ready): void { $ready->invoke(null,['status'=>503,'body'=>'secret']); }
 ],'unused');
 throw new LogicException('diagnostic call did not fail');
} catch(RuntimeException $e) {
 $d=R::safeFailure($e);
 checkFinal($d['sourceFile']==='CpanelNativeRecovery.php' && $d['sourceFunction']==='writeReadinessMetadata');
 checkFinal(!str_contains(json_encode($d),'secret'));
}
echo "PASS fixed-shape normal readiness metadata; no secrets; strict readiness guards unchanged; nearest owned recovery caller selected\n";
