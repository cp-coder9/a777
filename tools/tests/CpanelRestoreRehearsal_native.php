<?php
declare(strict_types=1);
// Guarded LOCAL synthetic integration, not provider issuance / production rehearsal.
require 'E:/Hermes/home/cache/scratch/owned-restore-bootstrap.php';
require dirname(__DIR__) . '/CpanelRestoreRehearsal.php';
use Architex\Infrastructure\Backup\{BackupService,RestoreService};
use Architex\Infrastructure\Database\{MigrationRunner,DisposableDatabaseGuard};
use Architex\Deployment\CpanelRestoreRehearsal;
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_STRINGIFY_FETCHES=>false];
$admin=new PDO('mysql:host=127.0.0.1;port=3398;charset=utf8mb4','root','',$options);
function serverGuard(PDO $pdo): void {
    $r=$pdo->query('SELECT @@port port,@@version version,@@datadir datadir')->fetch();
    if ((int)$r['port']!==3398 || !str_starts_with($r['version'],'11.4.3-MariaDB') || strtolower(str_replace('\\','/',$r['datadir']))!=='e:/a777/.private/role-validation-db-20260917/') throw new RuntimeException('Native fixture server refused.');
}
serverGuard($admin);
$owned=[]; $n=0;
function nativeCheck(bool $v): void { global $n; if (!$v) throw new RuntimeException('Native assertion failed.'); $n++; }
function archiveManifest(string $path,string $password): array { $z=new ZipArchive();nativeCheck($z->open($path)===true);$z->setPassword($password);try{return json_decode($z->getFromName('manifest.json'),true,64,JSON_THROW_ON_ERROR);}finally{$z->close();} }
$work='E:/Hermes/home/cache/scratch/rehearsal-native-'.bin2hex(random_bytes(8));mkdir($work,0700);mkdir($work.'/migrations',0700);mkdir($work.'/source-files',0700);
$sql="-- architex:migration-mode=ddl\nCREATE TABLE IF NOT EXISTS records(id INT PRIMARY KEY,value VARBINARY(100) NULL) ENGINE=InnoDB;\nCREATE TABLE IF NOT EXISTS stored_files(organisation_id BINARY(16),storage_name VARCHAR(48),sha256 CHAR(64),byte_size BIGINT,status VARCHAR(16)) ENGINE=InnoDB;\n";
file_put_contents($work.'/migrations/0001_fixture.sql',$sql);
$password=bin2hex(random_bytes(32));$key=base64_encode(random_bytes(32));
try {
    $sourceName='architex_taskrehearsal_'.bin2hex(random_bytes(4)).'_test';
    $targetName='architex_taskrehearsal_'.bin2hex(random_bytes(4)).'_test';
    file_put_contents($work.'/owned-fixture.json',json_encode(['synthetic'=>true,'server'=>'127.0.0.1:3398','source'=>$sourceName,'target'=>$targetName],JSON_THROW_ON_ERROR));
    foreach ([$sourceName,$targetName] as $name) {
        DisposableDatabaseGuard::assertAllowed(['ARCHITEX_ALLOW_DISPOSABLE_DB'=>'1','APP_ENV'=>'test','DB_HOST'=>'127.0.0.1','DB_NAME'=>$name]);
        nativeCheck((int)$admin->query("SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='$name'")->fetchColumn()===0);
        $owned[]=$name;
    }
    $admin->exec("CREATE DATABASE `$sourceName`");
    $pdo=new PDO("mysql:host=127.0.0.1;port=3398;dbname=$sourceName;charset=utf8mb4",'root','',$options);
    (new MigrationRunner($pdo,$work.'/migrations'))->migrate();
    $pdo->exec("INSERT INTO records VALUES(1,UNHEX('00ff1020')),(2,NULL)");
    $org=str_repeat('a',32);$storage=str_repeat('b',48);$dir=$work.'/source-files/aa/'.$org;mkdir($dir,0700,true);
    $bytes="synthetic private file\0\xff";file_put_contents($dir.'/'.$storage,$bytes);chmod($dir.'/'.$storage,0600);
    $q=$pdo->prepare('INSERT INTO stored_files VALUES(UNHEX(:org),:name,:sha,:bytes,\'active\')');$q->execute(['org'=>$org,'name'=>$storage,'sha'=>hash('sha256',$bytes),'bytes'=>strlen($bytes)]);
    $pdo->exec('SET TRANSACTION READ ONLY');
    $backup=(new BackupService($pdo,$work.'/source-files',$password,$key))->create($work.'/source.axbackup');
    // Deliberately mutate fixture source AFTER snapshot: comparison must ignore current source.
    $pdo->exec("UPDATE records SET value='changed' WHERE id=1");
    $report=(new RestoreService($work.'/migrations',$password,$key))->restore($backup->path,['ARCHITEX_ALLOW_DISPOSABLE_DB'=>'1','APP_ENV'=>'test','DB_HOST'=>'127.0.0.1','DB_PORT'=>'3398','DB_NAME'=>$targetName,'DB_USER'=>'root','DB_PASSWORD'=>''],$targetName,$work.'/target-files');
    nativeCheck($report->backupId===$backup->backupId && $report->rowCount===$backup->rowCount && $report->fileCount===$backup->fileCount);
    $target=new PDO("mysql:host=127.0.0.1;port=3398;dbname=$targetName;charset=utf8mb4",'root','',$options);
    $target->exec('SET TRANSACTION READ ONLY');
    $verification=(new BackupService($target,$work.'/target-files',$password,$key))->create($work.'/target.axbackup');
    $a=archiveManifest($backup->path,$password);$b=archiveManifest($verification->path,$password);
    $method=(new ReflectionClass(CpanelRestoreRehearsal::class))->getMethod('assertSnapshotEquality');
    $method->invoke(null,$a,$b,$sourceName,$targetName);nativeCheck(true);
    nativeCheck($pdo->query('SELECT HEX(value) FROM records WHERE id=1')->fetchColumn()!==$target->query('SELECT HEX(value) FROM records WHERE id=1')->fetchColumn());
    nativeCheck(hash_file('sha256',$work.'/target-files/aa/'.$org.'/'.$storage)===hash('sha256',$bytes));
    $target->exec('UPDATE records SET value=\'corrupt\' WHERE id=1');
    $bad=(new BackupService($target,$work.'/target-files',$password,$key))->create($work.'/corrupt.axbackup');
    try { $method->invoke(null,$a,archiveManifest($bad->path,$password),$sourceName,$targetName);throw new LogicException('Corruption accepted.'); } catch (RuntimeException) { nativeCheck(true); }
    unset($pdo,$target,$q);
} finally {
    unset($pdo,$target,$q);
    foreach ($owned as $name) {
        serverGuard($admin);
        $q=$admin->prepare('SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE DB=:db');$q->execute(['db'=>$name]);
        if ((int)$q->fetchColumn()!==0) throw new RuntimeException('Active native fixture; retained.');
        $admin->exec("DROP DATABASE IF EXISTS `$name`");
        nativeCheck((int)$admin->query("SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='$name'")->fetchColumn()===0);
    }
}
printf("Native synthetic archive/restore/component-reflection assertions: %d passed; provider authentication NOT exercised.\n",$n);
