<?php
/** External bootstrap must load borrowed vendor THEN prepend candidate classes.
 * Native service proof uses reflection ONLY to issue a synthetic local capability;
 * it does not claim real cPanel authentication/provisioning was exercised.
 */
declare(strict_types=1);
$proofLog = 'E:/Hermes/home/cache/scratch' . '/owned-restore-proof-' . bin2hex(random_bytes(8)) . '.log';
fwrite(STDOUT, 'PROOF_LOG ' . $proofLog . "\n");
ob_start(static function(string $output) use ($proofLog): string { file_put_contents($proofLog, $output, FILE_APPEND | LOCK_EX); return $output; }, 1);
use Architex\Infrastructure\Backup\AccountOwnedRestoreTarget;
use Architex\Infrastructure\Backup\BackupService;
use Architex\Infrastructure\Backup\RestoreService;
use Architex\Infrastructure\Database\DisposableDatabaseGuard;
use Architex\Infrastructure\Database\MigrationRunner;

require 'E:/Hermes/home/cache/scratch/owned-restore-bootstrap.php';
$root = dirname(__DIR__, 2);
foreach ([RestoreService::class, AccountOwnedRestoreTarget::class] as $class) {
    $path = (new ReflectionClass($class))->getFileName();
    if (!str_starts_with(str_replace('\\','/',$path), str_replace('\\','/',$root) . '/src/')) throw new RuntimeException('Wrong autoloader: ' . $path);
    echo "CLASS $path\n";
}
$options = [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::MYSQL_ATTR_MULTI_STATEMENTS=>false];
$admin = new PDO('mysql:host=127.0.0.1;port=3398;charset=utf8mb4','root','',$options);
$identity=$admin->query('SELECT @@port port,@@version version,@@datadir datadir')->fetch();
if ((int)$identity['port'] !== 3398 || !str_starts_with($identity['version'],'11.4.3-MariaDB') || strtolower(str_replace('\\','/',$identity['datadir'])) !== 'e:/a777/.private/role-validation-db-20260917/') throw new RuntimeException('Refusing unexpected native server.');
echo 'SERVER ' . json_encode($identity) . "\n";
$sourceName='architex_taskrestore_'.bin2hex(random_bytes(4)).'_test';
$targetName='archite4_ar_'.bin2hex(random_bytes(8)).'_test';
DisposableDatabaseGuard::assertAllowed(['ARCHITEX_ALLOW_DISPOSABLE_DB'=>'1','APP_ENV'=>'test','DB_HOST'=>'127.0.0.1','DB_NAME'=>$sourceName]);
$work= 'E:/Hermes/home/cache/scratch'.'/owned-restore-'.bin2hex(random_bytes(8)); mkdir($work,0700,true);
$created=[]; $assertions=0;
$phase = $argv[1] ?? '--all';
if ($phase === '--restore') {
    $state=json_decode(file_get_contents($argv[2] ?? ''),true,64,JSON_THROW_ON_ERROR);
    $sourceName=$state['source'];$targetName=$state['target'];$work=$state['work'];
    if(!preg_match('/\Aarchitex_taskrestore_[a-f0-9]{8}_test\z/',$sourceName)||!preg_match('/\Aarchite4_ar_[a-f0-9]{16}_test\z/',$targetName)||!str_starts_with($work,'E:/Hermes/home/cache/scratch/owned-restore-')) throw new RuntimeException('Invalid owned proof state');
    $created=[$sourceName,$targetName];
}
function check(bool $condition,string $label): void { global $assertions; if (!$condition) throw new RuntimeException('FAIL '.$label); $assertions++; echo "PASS $label\n"; }
function rejects(callable $call,string $label): void { try {$call();} catch (RuntimeException $e) {check(true,$label);return;} throw new RuntimeException('Expected rejection: '.$label); }
function issueSynthetic(string $targetName, PDO $pdo): AccountOwnedRestoreTarget {
    $receipt=['account'=>'archite4','target'=>$targetName,'user'=>'root','nonce'=>bin2hex(random_bytes(32))];
    $reflection=new ReflectionClass(AccountOwnedRestoreTarget::class);
    $target=$reflection->newInstanceWithoutConstructor();
    $reflection->getConstructor()->invoke($target,$targetName,'archite4_dev_rel1','127.0.0.1',json_encode($pdo->query('SELECT @@hostname hostname, @@port port, @@server_id server_id')->fetch(),JSON_THROW_ON_ERROR),'root',$receipt);
    $property=$reflection->getProperty('issued'); $map=$property->getValue() ?? new WeakMap();
    $map[$target]=hash('sha256',json_encode($receipt,JSON_THROW_ON_ERROR)); $property->setValue(null,$map);
    return $target;
}
try {
    if ($phase !== '--restore') {
    foreach ([$sourceName,$targetName] as $name) {
        check((int)$admin->query("SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='$name'")->fetchColumn()===0,'unique new schema '.$name);
        $admin->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"); $created[]=$name;
    }
    $source=new PDO("mysql:host=127.0.0.1;port=3398;dbname=$sourceName;charset=utf8mb4",'root','',$options);
    $targetPdo=new PDO("mysql:host=127.0.0.1;port=3398;dbname=$targetName;charset=utf8mb4",'root','',$options);
    $target=issueSynthetic($targetName,$source);
    $target->assertEmpty($targetPdo);check(true,'fresh target exact identity and empty schema');
    rejects(fn()=>AccountOwnedRestoreTarget::provision($source,'127.0.0.1','archite4_test'),'no cPanel issuer on Windows');
    rejects(fn()=>DisposableDatabaseGuard::assertAllowed(['ARCHITEX_ALLOW_DISPOSABLE_DB'=>'1','APP_ENV'=>'test','DB_HOST'=>'127.0.0.1','DB_NAME'=>$targetName]),'legacy guard still rejects cPanel namespace');
    $unissued=(new ReflectionClass(AccountOwnedRestoreTarget::class))->newInstanceWithoutConstructor();
    rejects(fn()=>$unissued->assertEmpty($targetPdo),'unissued/lied receipt rejected');
    rejects(fn()=>issueSynthetic('archite4_dev_rel1',$source)->assertEmpty($source),'production database rejected without selecting production');
    rejects(fn()=>$target->assertEmpty($source),'actual PDO wrong database rejected');
    $targetPdo->exec('CREATE TABLE synthetic_nonempty(id INT)');
    rejects(fn()=>$target->assertEmpty($targetPdo),'nonempty table rejected');$targetPdo->exec('DROP TABLE synthetic_nonempty');
    $targetPdo->exec('CREATE VIEW synthetic_view AS SELECT 1 id');
    rejects(fn()=>$target->assertEmpty($targetPdo),'nonempty view rejected');$targetPdo->exec('DROP VIEW synthetic_view');
    $targetPdo->exec('CREATE PROCEDURE synthetic_routine() SELECT 1');
    rejects(fn()=>$target->assertEmpty($targetPdo),'nonempty routine rejected');$targetPdo->exec('DROP PROCEDURE synthetic_routine');
    $targetPdo->exec('CREATE EVENT synthetic_event ON SCHEDULE AT CURRENT_TIMESTAMP + INTERVAL 1 DAY DO SELECT 1');
    rejects(fn()=>$target->assertEmpty($targetPdo),'nonempty event rejected');$targetPdo->exec('DROP EVENT synthetic_event');
    $migration=(new MigrationRunner($source,$root.'/database/migrations'))->migrate();
    echo 'MIGRATIONS '.count($migration->appliedVersions)."\n";
    // Canonical schema plus synthetic private file metadata, with no professional evidence.
    $source->exec("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX('11111111111111111111111111111111'),'restore-native@example.test','synthetic','active',UTC_TIMESTAMP(6))");
    $source->exec("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX('22222222222222222222222222222222'),'Synthetic Restore Test','synthetic-restore-test','active')");
    $source->exec("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX('33333333333333333333333333333333'),UNHEX('22222222222222222222222222222222'),UNHEX('11111111111111111111111111111111'),'owner','active')");
    $source->exec("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,created_by) VALUES(UNHEX('44444444444444444444444444444444'),UNHEX('22222222222222222222222222222222'),'SYNTHETIC-RESTORE','Synthetic Restore','Synthetic','Synthetic','Synthetic','Office','','architect',UNHEX('11111111111111111111111111111111'))");
    mkdir($work.'/source-files');file_put_contents($work.'/fixture.pdf',"%PDF-1.4\nsynthetic-only\n%%EOF");
    (new Architex\Platform\Files\FileService($source,new Architex\Platform\Files\LocalFileStorage($work.'/source-files')))->upload('22222222222222222222222222222222','11111111111111111111111111111111','44444444444444444444444444444444',Architex\Http\UploadedFile::forTesting('synthetic.pdf',$work.'/fixture.pdf'));
    $password='synthetic native archive password';$key=base64_encode(str_repeat('s',32));
    $backup=(new BackupService($source,$work.'/source-files',$password,$key))->create($work.'/fixture');
    // Signed synthetic source metadata only. Never connects to/creates archite4_dev_rel1.
    $zip=new ZipArchive();$zip->open($backup->path);$zip->setPassword($password);$manifest=json_decode($zip->getFromName('manifest.json'),true,64,JSON_THROW_ON_ERROR);
    unset($manifest['backupId'],$manifest['integrityHmac']);$manifest['database']['name']='archite4_dev_rel1';
    $canonical=json_encode($manifest,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);$manifest['backupId']=hash('sha256',$canonical);$manifest['integrityHmac']=hash_hmac('sha256',$canonical,base64_decode($key));
    $zip->addFromString('manifest.json',json_encode($manifest,JSON_THROW_ON_ERROR));$zip->setEncryptionName('manifest.json',ZipArchive::EM_AES_256,$password);$zip->close();
    if ($phase === '--prepare') {
        $statePath=$work.'/state.json';file_put_contents($statePath,json_encode(['source'=>$sourceName,'target'=>$targetName,'work'=>$work]));
        echo 'PREPARED_STATE '.$statePath."\n"; return;
    }
    } else {
        $source=new PDO("mysql:host=127.0.0.1;port=3398;dbname=$sourceName;charset=utf8mb4",'root','',$options);
        $targetPdo=new PDO("mysql:host=127.0.0.1;port=3398;dbname=$targetName;charset=utf8mb4",'root','',$options);
        $target=issueSynthetic($targetName,$source);
        $password='synthetic native archive password';$key=base64_encode(str_repeat('s',32));
        $backup=(object)['path'=>$work.'/fixture.axbackup'];
    }
    $report=(new RestoreService($root.'/database/migrations',$password,$key))->restoreIntoProvisionedEmptyDatabase($backup->path,$targetPdo,$target,$work.'/restored-files');
    $tables=$source->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE' ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
    $total=0;
    foreach($tables as $table) {
        $a=$source->query("SELECT * FROM `$table`")->fetchAll();$b=$targetPdo->query("SELECT * FROM `$table`")->fetchAll();
        $normalize=static function(array $rows): array { $rows=array_map(static fn($row)=>serialize($row),$rows);sort($rows);return $rows;};
        check($normalize($a)===$normalize($b),'equal rows '.$table);$total+=count($a);
    }
    $metadata=$source->query("SELECT LOWER(HEX(organisation_id)) organisation,storage_name,sha256 FROM stored_files WHERE status='active'")->fetchAll();
    foreach($metadata as $file) { $path=$work.'/restored-files/'.substr($file['organisation'],0,2).'/'.$file['organisation'].'/'.$file['storage_name'];check(hash_file('sha256',$path)===$file['sha256'],'restored private file sha256'); }
    check($report->rowCount===$total,'report row total');check($report->fileCount===count($metadata),'report file total');
    echo 'RESULT '.json_encode(['tables'=>count($tables),'rows'=>$total,'files'=>$report->fileCount,'assertions'=>$assertions,'source'=>$sourceName,'target'=>$targetName,'work'=>$work])."\n";
} finally {
    foreach($phase === '--prepare' ? [] : array_reverse($created) as $name) {
        // Recheck exact owned native server before cleanup, never production names.
        $dir=strtolower(str_replace('\\','/',(string)$admin->query('SELECT @@datadir')->fetchColumn()));
        if($dir!=='e:/a777/.private/role-validation-db-20260917/' || !in_array($name,[$sourceName,$targetName],true)) throw new RuntimeException('Unsafe cleanup identity');
        $admin->exec("DROP DATABASE `$name`");check((int)$admin->query("SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='$name'")->fetchColumn()===0,'owned schema cleanup '.$name);
    }
}
