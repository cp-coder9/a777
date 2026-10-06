<?php
declare(strict_types=1);
namespace Architex\Tests\Release;
use Architex\Infrastructure\Backup\RestoreService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use ZipArchive;
final class RestoreIntegrityTest extends TestCase
{
 public function testAuthenticatedUnlistedEntryRejected(): void {$this->rejectFixture('unlisted');}
 public function testAuthenticatedMissingBindingRejected(): void {$this->rejectFixture('missing');}
 public function testAuthenticatedNoncanonicalTableRejected(): void {$this->rejectFixture('canonical');}
 public function testAuthenticatedDuplicateFileRejected(): void {$this->rejectFixture('duplicate');}
 public function testAuthenticatedInconsistentFileRejected(): void {$this->rejectFixture('filehash');}
 private function rejectFixture(string $case): void {
  $path='E:/Hermes/home/cache/scratch/restore-unit-'.bin2hex(random_bytes(8)).'.zip';
  $entry='files/'.str_repeat('a',32).'/'.str_repeat('b',48);
  $m=['format'=>'architex-backup-v1','database'=>['tables'=>['parents'=>['entry'=>'database/parents.jsonl','rows'=>0]]],'files'=>[['organisationId'=>str_repeat('a',32),'storageName'=>str_repeat('b',48),'entry'=>$entry,'sha256'=>hash('sha256','x'),'bytes'=>1]],'entries'=>['database/parents.jsonl'=>['sha256'=>hash('sha256',''),'bytes'=>0],$entry=>['sha256'=>hash('sha256','x'),'bytes'=>1]]];
  if($case==='missing')unset($m['entries']['database/parents.jsonl']);
  if($case==='canonical')$m['database']['tables']['parents']['entry']='database/other.jsonl';
  if($case==='duplicate')$m['files'][]=$m['files'][0];
  if($case==='filehash')$m['files'][0]['sha256']=str_repeat('0',64);
  $canonical=json_encode($m,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);$m['backupId']=hash('sha256',$canonical);$m['integrityHmac']=hash_hmac('sha256',$canonical,str_repeat('i',32));
  $z=new ZipArchive();$z->open($path,ZipArchive::CREATE);$z->addFromString('manifest.json',json_encode($m));$z->addFromString('database/parents.jsonl','');$z->addFromString($entry,'x');if($case==='unlisted')$z->addFromString('database/extra.jsonl','');$z->close();$z->open($path);
  try {$this->expectException(RuntimeException::class);(new ReflectionMethod(RestoreService::class,'verifyManifest'))->invoke(new RestoreService('unused','synthetic archive password',base64_encode(str_repeat('i',32))),$z,$m);} finally {$z->close();unlink($path);}
 }
}
