<?php
declare(strict_types=1);
require dirname(__DIR__) . '/CpanelMaintenanceApi.php';
require dirname(__DIR__) . '/CpanelFenceReceiptVerifier.php';
$count = 0;
function ok(bool $b): void { global $count; if (!$b) throw new RuntimeException('Assertion failed'); ++$count; }
function reject(Closure $f): void { try { $f(); } catch (Throwable) { ok(true); return; } throw new RuntimeException('Expected rejection'); }
ok(CpanelMaintenanceApi::decode('{"status":1,"errors":null,"data":null}',200) === null);
reject(fn()=>CpanelMaintenanceApi::decode('{"status":0,"data":null}',200));
reject(fn()=>CpanelMaintenanceApi::decode('{"status":1,"errors":["error"],"data":null}',200));
reject(fn()=>CpanelMaintenanceApi::decode('{"status":1,"data":null}',302));
reject(fn()=>CpanelMaintenanceApi::requestSpec('Mysql','delete_database','synthetic'));
reject(fn()=>CpanelMaintenanceApi::target('revoke_access_to_database',['database'=>'wrong','user'=>'archite4_test']));
$key = str_repeat('k',32); $sha = str_repeat('a',64);
$binding=['account'=>'archite4','database'=>'archite4_dev_rel1','user'=>'archite4_test','configSha256'=>$sha];
$fetch=static function($kind,$binding) use ($key) { $payload=json_encode(['kind'=>$kind,'binding'=>$binding,'receiptId'=>'synthetic-signed','issuedAt'=>time(),'verified'=>true], JSON_THROW_ON_ERROR); return ['payload'=>$payload,'mac'=>hash_hmac('sha256',$payload,$key)]; };
$v=new CpanelFenceReceiptVerifier($fetch,$key,$sha,'archite4_test');
ok($v('apache-maintenance',$binding)['receiptId']==='synthetic-signed');
$bad=new CpanelFenceReceiptVerifier(fn()=>['kind'=>'apache-maintenance','binding'=>$binding,'receiptId'=>'plausible'], $key,$sha,'archite4_test');
reject(fn()=> $bad('apache-maintenance',$binding));
reject(fn()=> $v('apache-maintenance',array_replace($binding,['configSha256'=>str_repeat('b',64)])));
print "PASS $count assertions; synthetic transport/envelope fixtures; no live calls\n";
