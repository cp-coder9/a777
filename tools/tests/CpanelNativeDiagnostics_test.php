<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/CpanelNativeDeployment.php';
use Architex\Deployment\CpanelNativeDeployment as D;
$method=new ReflectionMethod(D::class,'need');
try { $method->invoke(null,false,'original_grants'); } catch (Throwable $e) { if(D::safeFailureCode($e)!=='original_grants') throw new RuntimeException('gate code absent'); }
foreach ([new RuntimeException('original_grants'),new PDOException('password SQL original_grants')] as $e) if(D::safeFailureCode($e)!=='native_operation_refused') throw new RuntimeException('untrusted message disclosed');
try { $method->invoke(null,false,'password arbitrary'); } catch (Throwable $e) { if(D::safeFailureCode($e)!=='native_operation_refused') throw new RuntimeException('unknown gate disclosed'); }
echo "PASS branded fixed-gate diagnostics; dependency messages never exported\n";
