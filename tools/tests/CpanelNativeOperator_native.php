<?php
declare(strict_types=1);
// Synthetic SQL proof only: no provider authentication, production credentials or application bypass.
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
$admin=new PDO('mysql:host=127.0.0.1;port=3398;charset=utf8mb4','root','',$options);
$r=$admin->query('SELECT @@port port,@@version version,@@datadir datadir')->fetch(PDO::FETCH_ASSOC);
if((int)$r['port']!==3398 || !str_starts_with($r['version'],'11.4.3-MariaDB') || strtolower(str_replace('\\','/',$r['datadir']))!=='e:/a777/.private/role-validation-db-20260917/') throw new RuntimeException('Fixture server refused');
$id=bin2hex(random_bytes(8)); $db='architex_taskoperator_'.$id.'_source__test'; $app='fixture_app_'.$id; $operator='fixture_op_'.$id; $password=bin2hex(random_bytes(24));
if(!preg_match('/\Aarchitex_taskoperator_[a-f0-9]{16}_source__test\z/',$db)) throw new RuntimeException('Fixture namespace refused');
foreach([$app,$operator] as $user) if((int)$admin->query("SELECT COUNT(*) FROM mysql.user WHERE User='$user'")->fetchColumn()!==0) throw new RuntimeException('Fixture collision');
if((int)$admin->query("SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='$db'")->fetchColumn()!==0) throw new RuntimeException('Fixture collision');
$created=[]; $dbCreated=false;
$connect=static fn(string $user)=>new PDO("mysql:host=127.0.0.1;port=3398;dbname=$db;charset=utf8mb4",$user,$password,$options);
$denied=static function(callable $f): void { try{$f();}catch(PDOException $e){if(in_array((int)($e->errorInfo[1]??0),[1044,1045,1142],true))return;throw $e;}throw new RuntimeException('Expected permission denial');};
try {
 $admin->exec("CREATE DATABASE `$db`"); $dbCreated=true;
 foreach([$app,$operator] as $user){$admin->exec("CREATE USER '$user'@'127.0.0.1' IDENTIFIED BY '$password'");$created[]=$user;}
 $admin->exec("GRANT ALL PRIVILEGES ON `$db`.* TO '$app'@'127.0.0.1'");
 $observer=$connect($app); $observer->exec('CREATE TABLE records(id INT PRIMARY KEY)'); $observer->exec('INSERT INTO records VALUES(1)');
 $admin->exec("REVOKE ALL PRIVILEGES ON `$db`.* FROM '$app'@'127.0.0.1'");
 $denied(fn()=>$connect($app)->query('SELECT * FROM records'));
 $admin->exec("GRANT ALL PRIVILEGES ON `$db`.* TO '$operator'@'127.0.0.1'");
 $pdo=$connect($operator);
 $capabilities=$pdo->query("SELECT PRIVILEGE_TYPE FROM information_schema.SCHEMA_PRIVILEGES WHERE TABLE_SCHEMA=DATABASE() AND GRANTEE=CONCAT(CHAR(39),SUBSTRING_INDEX(CURRENT_USER(),'@',1),CHAR(39),'@',CHAR(39),SUBSTRING_INDEX(CURRENT_USER(),'@',-1),CHAR(39))")->fetchAll(PDO::FETCH_COLUMN);
 foreach(['ALTER','ALTER ROUTINE','CREATE','CREATE ROUTINE','CREATE TEMPORARY TABLES','CREATE VIEW','DELETE','DROP','EVENT','EXECUTE','INDEX','INSERT','LOCK TABLES','REFERENCES','SELECT','SHOW VIEW','TRIGGER','UPDATE'] as $required)if(!in_array($required,$capabilities,true))throw new RuntimeException('Actual full schema privileges readback failed');
 $pdo->exec('CREATE TABLE durable_proof(id INT PRIMARY KEY)'); $pdo->exec('ALTER TABLE durable_proof ADD COLUMN proof INT'); $pdo->exec('INSERT INTO durable_proof VALUES(1,7)');
 if((int)$pdo->query('SELECT proof FROM durable_proof')->fetchColumn()!==7 || (int)$observer->query('SELECT COUNT(*) FROM records')->fetchColumn()!==1) throw new RuntimeException('Native durable/read proof failed');
 $pdo=null; $admin->exec("REVOKE ALL PRIVILEGES ON `$db`.* FROM '$operator'@'127.0.0.1'"); $denied(fn()=>$connect($operator)->query('SELECT * FROM records'));
 $admin->exec("GRANT ALL PRIVILEGES ON `$db`.* TO '$app'@'127.0.0.1'"); if((int)$connect($app)->query('SELECT COUNT(*) FROM records')->fetchColumn()!==1)throw new RuntimeException('App restoration failed');
 echo "PASS synthetic native operator durable CREATE/ALTER/table write; revoked fresh app denied; warm observer read; operator revoked fresh denied; app restored. No cPanel/provider claim.\n";
} finally { $observer=null; $pdo=null; foreach($created as $user)$admin->exec("DROP USER '$user'@'127.0.0.1'"); if($dbCreated)$admin->exec("DROP DATABASE `$db`"); }
