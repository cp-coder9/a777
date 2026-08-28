<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Models;

use Architex\Http\UploadedFile;
use Architex\Platform\Files\FileException;
use Architex\Platform\Files\FileService;
use Architex\Platform\Files\LocalFileStorage;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Models\ModelMetadataException;
use Architex\Platform\Models\ModelMetadataService;
use Architex\Tests\Support\MariaDbTestCase;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class ModelMetadataServiceTest extends MariaDbTestCase
{
    private string $storageRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storageRoot=dirname(__DIR__,3).'/storage/tmp/model-metadata-'.bin2hex(random_bytes(8));
        mkdir($this->storageRoot,0700,true);
    }

    protected function tearDown(): void
    {
        if(isset($this->storageRoot)&&is_dir($this->storageRoot)){
            $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->storageRoot,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
            foreach($iterator as$item){$item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname());}
            rmdir($this->storageRoot);
        }
        parent::tearDown();
    }

    public function testSafeIfcSourceVersionedFactsAndProviderBoundary(): void
    {
        [$organisationId,$ownerId,$projectId]=$this->seed('BIM1');
        $path=$this->fixture('coordination.ifc',"ISO-10303-21;\nHEADER;\nFILE_SCHEMA(('IFC4'));\nENDSEC;\nDATA;\nENDSEC;\nEND-ISO-10303-21;");
        $stored=(new FileService($this->pdo,new LocalFileStorage($this->storageRoot)))->upload($organisationId,$ownerId,$projectId,UploadedFile::forTesting('Coordination Model.ifc',$path));
        self::assertSame(hash_file('sha256',$path),$stored->sha256);

        $service=new ModelMetadataService($this->pdo);
        $source=$service->createSource($organisationId,$ownerId,'owner',[
            'projectId'=>$projectId,'title'=>'Architectural coordination model','format'=>'ifc','schemaVersion'=>'IFC4','sourceRevision'=>'P03','fileId'=>$stored->id,
            'extractionMode'=>'human_entered','providerName'=>'','responsibleRole'=>'architect','assumptions'=>['Metadata transcribed from the authoring-system export.'],
        ]);
        self::assertSame(1,$source['version']);
        self::assertSame($stored->sha256,$source['sourceSha256']);
        self::assertFalse($source['nativeParsingAvailable']);
        self::assertFalse($source['externalProviderVerified']);

        $invalid=$service->addRevision($organisationId,$ownerId,'owner',$source['id'],1,[
            'revisionLabel'=>'Metadata P03-a','notes'=>'Initial human capture.','facts'=>[
                $this->fact('space','SPACE-01','Reception','Level 00','44.20','m2','IfcSpace SPACE-01',90,false),
                $this->fact('door','SPACE-01','Main entrance door','Level 00','1200','mm','IfcDoor SPACE-01',85,true),
            ],
        ]);
        self::assertSame(2,$invalid['sourceVersion']);
        self::assertSame(['duplicate_external_id:SPACE-01'],$invalid['validation']['errors']);
        try{$service->decide($organisationId,$ownerId,'owner',$invalid['id'],['action'=>'approve','actingRole'=>'architect','humanConfirmed'=>true,'reason'=>'Attempt invalid approval.']);self::fail('Validation errors must block approval.');}catch(ModelMetadataException$exception){self::assertSame('model_revision_validation_blocked',$exception->reason);}

        $valid=$service->addRevision($organisationId,$ownerId,'owner',$source['id'],2,[
            'revisionLabel'=>'Metadata P03-b','notes'=>'Corrected controlled metadata.','facts'=>[
                $this->fact('level','LEVEL-00','Ground floor','Level 00','0.000','m','IfcBuildingStorey LEVEL-00',100,false),
                $this->fact('space','SPACE-01','Reception','Level 00','44.20','m2','IfcSpace SPACE-01',90,false),
                $this->fact('door','DOOR-01','Main entrance door','Level 00','1200','mm','IfcDoor DOOR-01',85,true),
                $this->fact('title_block','TITLE-01','Model issue P03','',null,'','IFC header and issue sheet',100,false),
            ],
        ]);
        self::assertSame(2,$valid['revisionNumber']);
        self::assertSame(3,$valid['sourceVersion']);
        self::assertSame(['door'=>1,'level'=>1,'space'=>1,'title_block'=>1],$valid['validation']['categoryCounts']);
        self::assertSame(['DOOR-01'],$valid['validation']['verificationRequired']);
        try{$service->addRevision($organisationId,$ownerId,'owner',$source['id'],2,['revisionLabel'=>'Stale','facts'=>[]]);self::fail('Stale writes must fail.');}catch(ModelMetadataException$exception){self::assertSame('model_source_version_conflict',$exception->reason);}
        try{$service->decide($organisationId,$ownerId,'owner',$valid['id'],['action'=>'approve','actingRole'=>'engineer','humanConfirmed'=>true,'reason'=>'Wrong role.']);self::fail('Role impersonation must fail.');}catch(ModelMetadataException$exception){self::assertSame('model_decision_role_denied',$exception->reason);}

        $approved=$service->decide($organisationId,$ownerId,'owner',$valid['id'],['action'=>'approve','actingRole'=>'architect','humanConfirmed'=>true,'reason'=>'Source, identifiers and verification flags reviewed.']);
        self::assertSame('approved',$approved['status']);self::assertSame(4,$approved['sourceVersion']);
        $issued=$service->issue($organisationId,$ownerId,'owner',$source['id'],$valid['id'],4,true,'architect','Responsible professional issues this exact metadata snapshot for coordination.');
        self::assertSame('issued',$issued['status']);self::assertSame(5,$issued['version']);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/',$issued['snapshotSha256']);
        self::assertNotNull($issued['projectRecordId']);self::assertFalse($issued['nativeParsingAvailable']);
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM project_records WHERE module_key='model_metadata' AND status='issued'")->fetchColumn());
        try{$service->addRevision($organisationId,$ownerId,'owner',$source['id'],5,['revisionLabel'=>'Post issue','facts'=>[]]);self::fail('Issued sources must be immutable.');}catch(ModelMetadataException$exception){self::assertSame('model_source_issued',$exception->reason);}
    }

    public function testUnsafeIfcAndUnsupportedNativeParserClaimsFailClosed(): void
    {
        [$organisationId,$ownerId,$projectId]=$this->seed('BIM2');
        try{(new FileService($this->pdo,new LocalFileStorage($this->storageRoot)))->upload($organisationId,$ownerId,$projectId,UploadedFile::forTesting('malicious.ifc',$this->fixture('fake.ifc','not an IFC exchange file')));self::fail('A fake IFC must not upload.');}catch(FileException$exception){self::assertSame('unsafe_file',$exception->reason);}
        $this->expectException(ModelMetadataException::class);
        $this->expectExceptionMessage('model_native_parser_unavailable');
        (new ModelMetadataService($this->pdo))->createSource($organisationId,$ownerId,'owner',['projectId'=>$projectId,'title'=>'False native parse','format'=>'ifc','schemaVersion'=>'IFC4','sourceRevision'=>'P01','extractionMode'=>'native_parser','responsibleRole'=>'architect','assumptions'=>[]]);
    }

    public function testCrossTenantReadsFailClosed(): void
    {
        [$organisationId,$ownerId,$projectId]=$this->seed('BIM3');[$otherOrganisation,$otherOwner]=$this->seed('BIM4');
        $source=(new ModelMetadataService($this->pdo))->createSource($organisationId,$ownerId,'owner',['projectId'=>$projectId,'title'=>'Private manual model register','format'=>'manual','schemaVersion'=>'Not applicable','sourceRevision'=>'P01','extractionMode'=>'human_entered','responsibleRole'=>'architect','assumptions'=>['No source file supplied.']]);
        $this->expectException(ModelMetadataException::class);
        $this->expectExceptionMessage('model_source_not_found');
        (new ModelMetadataService($this->pdo))->get($otherOrganisation,$otherOwner,$source['id']);
    }

    /** @return array<string,mixed> */
    private function fact(string$category,string$externalId,string$label,string$level,?string$value,string$unit,string$sourceReference,int$confidence,bool$requiresVerification):array
    {
        return compact('category','externalId','label','level','value','unit','sourceReference','confidence','requiresVerification');
    }

    private function fixture(string$name,string$contents):string{$path=$this->storageRoot.'/'.$name;file_put_contents($path,$contents);return$path;}

    /** @return array{string,string,string} */
    private function seed(string$code):array
    {
        $organisationId=Identifier::generate();$ownerId=Identifier::generate();$projectId=Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$ownerId,'email'=>strtolower($code).'@models.test']);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id'=>$organisationId,'name'=>$code,'slug'=>strtolower($code).'-'.substr($organisationId,0,8)]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:user),'owner','active')")->execute(['id'=>Identifier::generate(),'organisation'=>$organisationId,'user'=>$ownerId]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:id),UNHEX(:organisation),:code,:name,'Client','Tshwane','Erf 123','Commercial','','architect','design_development',UNHEX(:user))")->execute(['id'=>$projectId,'organisation'=>$organisationId,'code'=>$code,'name'=>"$code model coordination",'user'=>$ownerId]);
        return[$organisationId,$ownerId,$projectId];
    }
}
