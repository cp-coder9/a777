<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Http;

use Architex\Http\AuthenticationKernelFactory;
use Architex\Http\Request;
use Architex\Platform\Files\FileService;
use Architex\Platform\Files\LocalFileStorage;
use Architex\Http\UploadedFile;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Identity\PasswordResetNotifier;
use Architex\Platform\Identity\RegistrationNotifier;
use Architex\Platform\Identity\SessionService;
use Architex\Tests\Support\MariaDbTestCase;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class ModelMetadataHttpTest extends MariaDbTestCase
{
    private string$storageRoot;
    protected function setUp():void{parent::setUp();$this->storageRoot=dirname(__DIR__,3).'/storage/tmp/model-http-'.bin2hex(random_bytes(8));mkdir($this->storageRoot,0700,true);}
    protected function tearDown():void{if(isset($this->storageRoot)&&is_dir($this->storageRoot)){$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->storageRoot,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($iterator as$item){$item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname());}rmdir($this->storageRoot);}parent::tearDown();}

    public function testAuthenticatedModelWorkflowPersistsAndIssuesThroughHttp():void
    {
        [$organisationId,$ownerId,$projectId]=$this->seed();$notifier=new class implements RegistrationNotifier,PasswordResetNotifier{public function isAvailable():bool{return false;}public function send(string$email,string$token,string$expiresAt):void{}};$kernel=(new AuthenticationKernelFactory($this->pdo,$notifier,$notifier,fileStorageRoot:$this->storageRoot))->create();$session=(new SessionService($this->pdo))->rotate($ownerId);$headers=['Cookie'=>'architex_session='.$session->token,'X-Organisation-Id'=>$organisationId,'X-CSRF-Token'=>$session->csrfToken];
        $ifcPath=$this->storageRoot.'/http.ifc';file_put_contents($ifcPath,"ISO-10303-21;\nHEADER;\nFILE_SCHEMA(('IFC4'));\nENDSEC;\nDATA;\nENDSEC;\nEND-ISO-10303-21;");$file=(new FileService($this->pdo,new LocalFileStorage($this->storageRoot.'/files')))->upload($organisationId,$ownerId,$projectId,UploadedFile::forTesting('HTTP Model.ifc',$ifcPath));
        $missing=$headers;unset($missing['X-CSRF-Token']);self::assertSame(419,$kernel->handle($this->json('POST',"/api/v1/projects/$projectId/model-sources",$missing,['title'=>'Blocked']))->status());
        $created=$kernel->handle($this->json('POST',"/api/v1/projects/$projectId/model-sources",$headers,['title'=>'HTTP coordination model','format'=>'ifc','schemaVersion'=>'IFC4','sourceRevision'=>'P04','fileId'=>$file->id,'extractionMode'=>'human_entered','providerName'=>'','responsibleRole'=>'architect','assumptions'=>['Human-transcribed structured metadata.']]));self::assertSame(201,$created->status(),$created->body());$source=$created->json()['data'];self::assertFalse($source['nativeParsingAvailable']);
        $revisionResponse=$kernel->handle($this->json('POST','/api/v1/model-sources/'.$source['id'].'/revisions',$headers,['expectedVersion'=>1,'revisionLabel'=>'P04 metadata','notes'=>'HTTP controlled capture.','facts'=>[['category'=>'space','externalId'=>'SPACE-HTTP-01','label'=>'Reception','level'=>'Level 00','value'=>'38.5','unit'=>'m2','sourceReference'=>'IfcSpace SPACE-HTTP-01','confidence'=>90,'requiresVerification'=>false],['category'=>'title_block','externalId'=>'TITLE-HTTP-01','label'=>'Model issue P04','level'=>'','value'=>null,'unit'=>'','sourceReference'=>'IFC header','confidence'=>100,'requiresVerification'=>false]]]));self::assertSame(201,$revisionResponse->status(),$revisionResponse->body());$revision=$revisionResponse->json()['data'];
        $approved=$kernel->handle($this->json('POST','/api/v1/model-metadata-revisions/'.$revision['id'].'/decisions',$headers,['action'=>'approve','actingRole'=>'architect','humanConfirmed'=>true,'reason'=>'Source provenance and facts reviewed.']));self::assertSame(200,$approved->status(),$approved->body());self::assertSame('approved',$approved->json()['data']['status']);
        $issued=$kernel->handle($this->json('POST','/api/v1/model-sources/'.$source['id'].'/issue',$headers,['revisionId'=>$revision['id'],'expectedVersion'=>3,'humanConfirmed'=>true,'professionalRole'=>'architect','issueReason'=>'Responsible professional reviewed this exact model metadata snapshot.']));self::assertSame(200,$issued->status(),$issued->body());self::assertSame('issued',$issued->json()['data']['status']);self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/',$issued->json()['data']['snapshotSha256']);
        $list=$kernel->handle(Request::json('GET',"/api/v1/projects/$projectId/model-sources",$headers));self::assertSame(200,$list->status(),$list->body());self::assertCount(1,$list->json()['data']);
        $show=$kernel->handle(Request::json('GET','/api/v1/model-sources/'.$source['id'],$headers));self::assertSame(200,$show->status(),$show->body());self::assertSame($issued->json()['data']['snapshotSha256'],$show->json()['data']['snapshotSha256']);
        self::assertSame(404,$kernel->handle(Request::json('GET',"/api/v1/projects/$projectId/model-sources",array_replace($headers,['X-Organisation-Id'=>Identifier::generate()])))->status());
    }

    /** @return array{string,string,string} */private function seed():array{$organisationId=Identifier::generate();$ownerId=Identifier::generate();$projectId=Identifier::generate();$this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'model-http@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$ownerId]);$this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Model HTTP',:slug,'active')")->execute(['id'=>$organisationId,'slug'=>'model-http-'.substr($organisationId,0,8)]);$this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:user),'owner','active')")->execute(['id'=>Identifier::generate(),'organisation'=>$organisationId,'user'=>$ownerId]);$this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:id),UNHEX(:organisation),'MODEL-HTTP','Model HTTP project','Client','Tshwane','Erf 1','Commercial','','architect','design_development',UNHEX(:user))")->execute(['id'=>$projectId,'organisation'=>$organisationId,'user'=>$ownerId]);return[$organisationId,$ownerId,$projectId];}
    /** @param array<string,string> $headers @param array<string,mixed> $body */private function json(string$m,string$p,array$headers,array$body):Request{return Request::json($m,$p,$headers+['Content-Type'=>'application/json'],json_encode($body,JSON_THROW_ON_ERROR));}
}
