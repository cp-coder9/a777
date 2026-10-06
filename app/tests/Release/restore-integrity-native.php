<?php
/** Guarded native synthetic fixtures; reflection receipt ONLY, not cPanel issuance. */
declare(strict_types=1);
require 'E:/Hermes/home/cache/scratch/owned-restore-bootstrap.php';
use Architex\Infrastructure\Backup\RestoreService;
use Architex\Infrastructure\Backup\AccountOwnedRestoreTarget;
use Architex\Infrastructure\Database\MigrationRunner;
use Architex\Infrastructure\Database\DisposableDatabaseGuard;
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC];
class RestoreFaultPdo extends PDO {
 public ?string $blockedRoot = null;
 public function beginTransaction(): bool {
  $result=parent::beginTransaction();
  if($this->blockedRoot!==null && (int)$this->query('SELECT @@FOREIGN_KEY_CHECKS')->fetchColumn()===0) {
   $directory=$this->blockedRoot.'/aa/'.str_repeat('a',32);mkdir($directory,0700,true);file_put_contents($directory.'/'.str_repeat('b',48).'.part','block');$this->blockedRoot=null;
  }
  return $result;
 }
}
$admin=new PDO('mysql:host=127.0.0.1;port=3398;charset=utf8mb4','root','',$options);
function guard(PDO $pdo): void {
 $r=$pdo->query('SELECT @@port port,@@version version,@@datadir datadir')->fetch();
 if((int)$r['port']!==3398 || !str_starts_with($r['version'],'11.4.3-MariaDB') || strtolower(str_replace('\\','/',$r['datadir']))!=='e:/a777/.private/role-validation-db-20260917/') throw new RuntimeException('Wrong native identity');
}
guard($admin);
$work='E:/Hermes/home/cache/scratch/restore-integrity-'.bin2hex(random_bytes(8));mkdir($work);mkdir($work.'/migrations');
$sql="-- architex:migration-mode=ddl\nCREATE TABLE IF NOT EXISTS parents(a INT NOT NULL,b INT NOT NULL,PRIMARY KEY(a,b)) ENGINE=InnoDB;\nCREATE TABLE IF NOT EXISTS children(id INT PRIMARY KEY,a INT NULL,b INT NULL,FOREIGN KEY(a,b) REFERENCES parents(a,b)) ENGINE=InnoDB;\n";
file_put_contents($work.'/migrations/0001_fixture.sql',$sql);
$password='synthetic integrity archive password';$key=str_repeat('i',32);$owned=[];$assertions=0;
function ok(bool $v,string $s): void {global $assertions;if(!$v)throw new RuntimeException('FAIL '.$s);$assertions++;echo 'PASS '.$s."\n";}
function encodeRow(array $r): string {foreach($r as &$v)$v=$v===null?['type'=>'null']:['type'=>'integer','value'=>$v];return json_encode($r,JSON_THROW_ON_ERROR)."\n";}
try {
 foreach(['owned','legacy'] as $path) foreach(['success','rowfail','filefail','orphan','unlisted','binding','duplicate','filebinding'] as $case) {
  $name=$path==='owned'?'archite4_ar_'.bin2hex(random_bytes(8)).'_test':'architex_taskrestore_'.bin2hex(random_bytes(4)).'_test';
  if($path==='legacy')DisposableDatabaseGuard::assertAllowed(['ARCHITEX_ALLOW_DISPOSABLE_DB'=>'1','APP_ENV'=>'test','DB_HOST'=>'127.0.0.1','DB_NAME'=>$name]);
  ok((int)$admin->query("SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='$name'")->fetchColumn()===0,'new owned schema');
  $admin->exec("CREATE DATABASE `$name`");$owned[]=$name;
  $pdo=new RestoreFaultPdo("mysql:host=127.0.0.1;port=3398;dbname=$name;charset=utf8mb4",'root','',$options);
  $org=str_repeat('a',32);$storage=str_repeat('b',48);$fileEntry="files/$org/$storage";
  $migrationRow=['version'=>['type'=>'base64','value'=>base64_encode('0001_fixture')],'checksum'=>['type'=>'base64','value'=>base64_encode(hash('sha256',$sql))],'applied_at'=>['type'=>'base64','value'=>base64_encode('2026-01-01 00:00:00.000000')]];
  $data=['database/schema_migrations.jsonl'=>json_encode($migrationRow)."\n",'database/parents.jsonl'=>encodeRow(['a'=>1,'b'=>2]),'database/children.jsonl'=>encodeRow(['id'=>1,'a'=>$case==='orphan'?9:1,'b'=>2]).encodeRow(['id'=>2,'a'=>null,'b'=>99]),$fileEntry=>'synthetic file'];
  if($case==='rowfail')$data['database/children.jsonl'].=encodeRow(['id'=>1,'a'=>1,'b'=>2]);
  $m=['format'=>'architex-backup-v1','database'=>['name'=>'archite4_dev_rel1','migrations'=>['0001_fixture'],'tables'=>[]],'files'=>[],'entries'=>[]];
  foreach($data as $entry=>$bytes)$m['entries'][$entry]=['sha256'=>hash('sha256',$bytes),'bytes'=>strlen($bytes)];
  foreach(['schema_migrations'=>1,'parents'=>1,'children'=>$case==='rowfail'?3:2] as $table=>$rows)$m['database']['tables'][$table]=['entry'=>'database/'.$table.'.jsonl','rows'=>$rows];
  $m['files'][]=['organisationId'=>$org,'storageName'=>$storage,'entry'=>$fileEntry]+$m['entries'][$fileEntry];
  if($case==='binding')unset($m['entries']['database/parents.jsonl']);
  if($case==='duplicate')$m['files'][]=$m['files'][0];
  if($case==='filebinding')$m['files'][0]['bytes']++;
  $canonical=json_encode($m,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);$m['backupId']=hash('sha256',$canonical);$m['integrityHmac']=hash_hmac('sha256',$canonical,$key);
  $archive=$work."/$path-$case.zip";$zip=new ZipArchive();$zip->open($archive,ZipArchive::CREATE);foreach($data as $entry=>$bytes)$zip->addFromString($entry,$bytes);if($case==='unlisted')$zip->addFromString('database/unlisted.jsonl','');$zip->addFromString('manifest.json',json_encode($m));$zip->close();
  $root=$work."/$path-$case-files";
  // A real write refusal, after rows imported: fileRoot descendant blocked by a regular file.
  if($case==='filefail')$pdo->blockedRoot=$root;
  $service=new RestoreService($work.'/migrations',$password,base64_encode($key));
  $preflight=in_array($case,['unlisted','binding','duplicate','filebinding'],true);
  try {
   if($case==='filefail' && $path==='legacy') {
    // Public roots must be empty. Invoke the same shared import engine to force a native filesystem failure at its transaction boundary.
    $z=new ZipArchive();$z->open($archive);(new ReflectionMethod($service,'verifyManifest'))->invoke($service,$z,$m);
    (new ReflectionMethod($service,'importVerifiedArchive'))->invoke($service,$pdo,$z,$m,$root);$z->close();
   } elseif($path==='owned') {
    $r=new ReflectionClass(AccountOwnedRestoreTarget::class);$target=$r->newInstanceWithoutConstructor();$receipt=['account'=>'archite4','target'=>$name,'user'=>'root','nonce'=>bin2hex(random_bytes(32))];
    $r->getConstructor()->invoke($target,$name,'archite4_dev_rel1','127.0.0.1',json_encode($pdo->query('SELECT @@hostname hostname,@@port port,@@server_id server_id')->fetch()),'root',$receipt);
    $p=$r->getProperty('issued');$map=$p->getValue()??new WeakMap();$map[$target]=hash('sha256',json_encode($receipt));$p->setValue(null,$map);
    $service->restoreIntoProvisionedEmptyDatabase($archive,$pdo,$target,$root);
   } else $service->restore($archive,['ARCHITEX_ALLOW_DISPOSABLE_DB'=>'1','APP_ENV'=>'test','DB_HOST'=>'127.0.0.1','DB_PORT'=>'3398','DB_NAME'=>$name,'DB_USER'=>'root','DB_PASSWORD'=>''],$name,$root);
   ok($case==='success',"$path $case accepted only success");
  } catch(Throwable $e) {if($case==='success')throw $e;echo 'REJECTION '.$e->getMessage()."\n";ok(true,"$path $case rejected");}
  $tables=(int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn();
  if($preflight)ok($tables===0,"$path $case before first mutation");
  else {
   ok($tables===3,"$path $case DDL retained not rollbackable");
   ok((int)$pdo->query('SELECT COUNT(*) FROM parents')->fetchColumn()===($case==='success'?1:0),"$path $case parent rollback");
   ok((int)$pdo->query('SELECT COUNT(*) FROM children')->fetchColumn()===($case==='success'?2:0),"$path $case child rollback / NULL semantics");
   ok((int)$pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn()===1,"$path $case migration DELETE rollback");
   ok((int)$pdo->query('SELECT @@FOREIGN_KEY_CHECKS')->fetchColumn()===1,"$path $case FK checks restored");
  }
  if($case==='filefail')ok(file_get_contents("$root/aa/$org/$storage.part")==='block',"$path failed staging retained");
  if($case==='success')ok(hash_file('sha256',"$root/aa/$org/$storage")===hash('sha256','synthetic file'),"$path validated file output");
 }
 echo 'RESULT '.json_encode(['assertions'=>$assertions,'work'=>$work,'receipt'=>'reflection synthetic ONLY; no native cPanel issuance'])."\n";
} finally {
 foreach($owned as $name){guard($admin);$s=$admin->prepare('SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE DB=:db AND ID<>CONNECTION_ID()');$s->execute(['db'=>$name]);if((int)$s->fetchColumn()>1)throw new RuntimeException('Active owned child; retained');$admin->exec("DROP DATABASE `$name`");ok((int)$admin->query("SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='$name'")->fetchColumn()===0,'exact owned cleanup');}
}
