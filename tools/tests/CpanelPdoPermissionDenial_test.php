<?php
declare(strict_types=1);
require dirname(__DIR__).'/CpanelPdoPermissionDenial.php';
require dirname(__DIR__).'/CpanelDatabaseWriterFence.php';
$n=0;
function denialCheck(bool $ok): void { global $n; if (!$ok) throw new RuntimeException('permission-denial regression failed'); $n++; }
foreach ([1044,1045,1142,1143,2006,2013,1049,0] as $code) {
 $pdo=new PDOException('Synthetic driver exception'); $pdo->errorInfo=['42000',$code,'Synthetic detail'];
 $wrapped=new RuntimeException('Database connection failed.',0,$pdo);
 $expected=in_array($code,[1044,1045,1142,1143],true);
 denialCheck(CpanelPdoPermissionDenial::isDenied($pdo,[1044,1045,1142,1143])===$expected);
 denialCheck(CpanelPdoPermissionDenial::isDenied($wrapped,[1044,1045,1142,1143])===$expected);
 denialCheck(!CpanelPdoPermissionDenial::isDenied(new RuntimeException('Other failure',0,$pdo),[1044,1045]));
}
denialCheck(!CpanelPdoPermissionDenial::isDenied(new RuntimeException('Database connection failed.'),[1044,1045]));
class DenialFixturePdo extends PDO { public function __construct() {} }
foreach ([1044,1045,2006,1049] as $code) {
 $driver=new PDOException('Synthetic driver'); $driver->errorInfo=['42000',$code,'Synthetic'];
 $failure=new RuntimeException('Database connection failed.',0,$driver);
 $fence=new CpanelDatabaseWriterFence(static fn()=>[],static fn()=>[],static function() use($failure){throw $failure;},new DenialFixturePdo(),__FILE__.'.unused-journal');
 try {
  $denied=(new ReflectionMethod($fence,'freshDenied'))->invoke($fence);
  denialCheck(in_array($code,[1044,1045],true) && $denied===true);
 } catch(RuntimeException $caught) {
  denialCheck(!in_array($code,[1044,1045],true) && $caught===$failure);
 }
}
echo "PASS $n exact PDO/factory exception-shape assertions; no database/provider calls\n";
