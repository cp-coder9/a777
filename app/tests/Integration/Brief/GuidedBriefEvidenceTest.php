<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Brief;

use Architex\Http\UploadedFile;
use Architex\Platform\Brief\BriefEvidenceService;
use Architex\Platform\Brief\BriefException;
use Architex\Platform\Brief\GuidedBriefService;
use Architex\Platform\Files\LocalFileStorage;
use Architex\Platform\Identity\Identifier;
use Architex\Tests\Support\MariaDbTestCase;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class GuidedBriefEvidenceTest extends MariaDbTestCase
{
    private string $storageRoot;
    protected function setUp():void{parent::setUp();$this->storageRoot=dirname(__DIR__,3).'/storage/tmp/brief-evidence-'.bin2hex(random_bytes(8));mkdir($this->storageRoot,0700,true);}
    protected function tearDown():void{if(isset($this->storageRoot)&&is_dir($this->storageRoot)){$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->storageRoot,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($iterator as $item)$item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname());rmdir($this->storageRoot);}parent::tearDown();}

    public function testStoresStandaloneEvidenceWithConsentIntegrityAndPrivateDownload():void
    {
        [$organisationId,$userId]=$this->seedTenant('brief-evidence');$brief=(new GuidedBriefService($this->pdo))->create($organisationId,$userId,'Evidence brief');$service=$this->service();
        $contents="%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF";$path=$this->fixture('plan.pdf',$contents);
        $stored=$service->upload($organisationId,$userId,$brief['id'],UploadedFile::forTesting('../../Existing Plan.pdf',$path),'existing_plan','Assess existing project information','2031-08-27',true);
        self::assertSame('Existing Plan.pdf',$stored['originalName']);self::assertSame(hash('sha256',$contents),$stored['sha256']);
        self::assertSame('existing_plan',$service->list($organisationId,$userId,$brief['id'])[0]['category']);
        $download=$service->download($organisationId,$userId,$stored['id']);self::assertSame($contents,$download->contents);self::assertStringStartsWith(realpath($this->storageRoot),realpath($download->path));
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE action='guided_brief.file_uploaded'")->fetchColumn());
    }

    public function testRejectsMissingConsentAndCrossTenantAccess():void
    {
        [$organisationId,$userId]=$this->seedTenant('brief-evidence-owner');[$otherOrganisation,$otherUser]=$this->seedTenant('brief-evidence-other');$brief=(new GuidedBriefService($this->pdo))->create($organisationId,$userId,'Private brief');$service=$this->service();$path=$this->fixture('photo.png',"\x89PNG\r\n\x1a\n");
        try{$service->upload($organisationId,$userId,$brief['id'],UploadedFile::forTesting('site.png',$path),'site_photo','Site context','2031-08-27',false);self::fail('Consent is required.');}catch(BriefException $exception){self::assertSame('brief_evidence_consent_required',$exception->reason);}
        $this->expectException(BriefException::class);$this->expectExceptionMessage('brief_not_found');$service->list($otherOrganisation,$otherUser,$brief['id']);
    }

    private function service():BriefEvidenceService{return new BriefEvidenceService($this->pdo,new LocalFileStorage($this->storageRoot));}
    private function fixture(string $name,string $contents):string{$path=$this->storageRoot.'/'.$name;file_put_contents($path,$contents);return$path;}
    /** @return array{string,string} */
    private function seedTenant(string $slug):array{$organisationId=Identifier::generate();$userId=Identifier::generate();$this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$userId,'email'=>$slug.'@example.test']);$this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id'=>$organisationId,'name'=>$slug,'slug'=>$slug]);$this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'owner','active')")->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'user_id'=>$userId]);return[$organisationId,$userId];}
}
