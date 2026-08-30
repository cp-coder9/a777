<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\PlatformCompleteness;

use Architex\Platform\Identity\Identifier;
use Architex\Platform\PlatformCompleteness\PlatformCompletenessException;
use Architex\Platform\PlatformCompleteness\PlatformCompletenessRegistry;
use Architex\Platform\PlatformCompleteness\PlatformCompletenessService;
use Architex\Tests\Support\MariaDbTestCase;

final class PlatformCompletenessServiceTest extends MariaDbTestCase
{
    public function testCatalogueRolesOptimisticReviewEvidenceAndHumanSignoffAreGoverned():void
    {
        [$organisation,$owner,$admin,$member]=$this->tenant('pc-a');
        [$otherOrganisation,$otherOwner]=$this->tenant('pc-b');
        $service=new PlatformCompletenessService($this->pdo,new PlatformCompletenessRegistry());
        $workspace=$service->workspace($organisation,$owner,'owner');
        self::assertCount(12,$workspace['workstreams']);self::assertCount(14,$workspace['sections']);self::assertCount(95,$workspace['items']);
        self::assertSame('platform_super_admin',$workspace['access']['platformRole']);self::assertTrue($workspace['access']['canAssignRoles']);
        $workstream=$workspace['workstreams'][0];$service->updateWorkstream($organisation,$owner,'owner',$workstream['id'],['expectedVersion'=>1,'implementationStatus'=>'implemented','qcStatus'=>'pass','branchOrPr'=>'release-21','blockers'=>'']);$wsSignoff=$service->signOffWorkstream($organisation,$owner,'owner',$workstream['id'],['decision'=>'accepted','humanConfirmed'=>true,'reason'=>'Owner accepted the current workstream evidence.']);self::assertSame('accepted',$wsSignoff['decision']);
        self::assertSame(95,(int)$this->pdo->query('SELECT COUNT(*) FROM platform_completeness_items')->fetchColumn());
        self::assertCount(95,$service->workspace($organisation,$owner,'owner')['items']);
        self::assertSame(95,(int)$this->pdo->query('SELECT COUNT(*) FROM platform_completeness_items')->fetchColumn());
        self::assertCount(95,$service->workspace($otherOrganisation,$otherOwner,'owner')['items']);
        self::assertSame(190,(int)$this->pdo->query('SELECT COUNT(*) FROM platform_completeness_items')->fetchColumn());

        $service->assignRole($organisation,$owner,'owner',$member,'developer');
        $developer=$service->workspace($organisation,$member,'member');self::assertSame('developer',$developer['access']['platformRole']);self::assertTrue($developer['access']['canDevelop']);self::assertFalse($developer['access']['canReview']);
        $item=$developer['items'][0];
        $updated=$service->updateItem($organisation,$member,'member',$item['id'],['expectedVersion'=>1,'implementationStatus'=>'implemented','priority'=>'critical','routeOrFilePath'=>'/register','branchOrPr'=>'release-21','nextAction'=>'Run browser acceptance','acceptanceCriteria'=>'Authenticated registration persists.']);
        self::assertSame(2,$updated['version']);
        $this->expectReason(fn()=>$service->updateItem($organisation,$member,'member',$item['id'],['expectedVersion'=>1,'implementationStatus'=>'in_review']),'platform_completeness_version_conflict');
        $this->expectReason(fn()=>$service->reviewItem($organisation,$member,'member',$item['id'],['result'=>'pass','notes'=>'Developer cannot self-QC.']),'platform_completeness_review_forbidden');

        $service->assignRole($organisation,$owner,'owner',$member,'qa_reviewer');
        $review=$service->reviewItem($organisation,$member,'member',$item['id'],['result'=>'pass','notes'=>'Current implementation evidence reviewed.','requiredChanges'=>'']);
        self::assertSame('pass',$review['result']);
        $this->expectReason(fn()=>$service->signOff($organisation,$admin,'admin',$item['id'],['decision'=>'accepted','humanConfirmed'=>false,'reason'=>'No confirmation']),'platform_completeness_human_confirmation_required');
        $signed=$service->signOff($organisation,$admin,'admin',$item['id'],['decision'=>'accepted','humanConfirmed'=>true,'reason'=>'Product owner reviewed current implementation and passing QC.']);
        self::assertSame('accepted',$signed['decision']);self::assertSame('accepted',$service->workspace($organisation,$owner,'owner')['items'][0]['implementationStatus']);

        $project=$this->project($organisation,$owner,'pc-a');$file=$this->file($organisation,$project,$owner,'evidence-a.pdf');
        $foreignProject=$this->project($otherOrganisation,$otherOwner,'pc-b');$foreignFile=$this->file($otherOrganisation,$foreignProject,$otherOwner,'foreign.pdf');
        self::assertSame($file,$service->linkUpload($organisation,$owner,'owner',$item['id'],['storedFileId'=>$file,'category'=>'test_report'])['storedFileId']);
        $this->expectReason(fn()=>$service->linkUpload($organisation,$owner,'owner',$item['id'],['storedFileId'=>$foreignFile,'category'=>'test_report']),'platform_completeness_file_not_found');
        $evidence=$service->addEvidence($organisation,$owner,'owner',$item['id'],['category'=>'test_report','label'=>'Release 21 suite','url'=>'https://evidence.example.test/release-21','notes'=>'Executed current candidate.']);
        self::assertSame('test_report',$evidence['category']);
        self::assertSame(0,(int)$this->pdo->query("SELECT COUNT(*) FROM platform_completeness_signoffs WHERE organisation_id=UNHEX('{$otherOrganisation}')")->fetchColumn());
    }

    private function expectReason(callable$callback,string$reason):void{try{$callback();self::fail('Expected '.$reason);}catch(PlatformCompletenessException$e){self::assertSame($reason,$e->reason);}}
    /** @return array{string,string,string,string} */
    private function tenant(string$slug):array{$o=Identifier::generate();$owner=Identifier::generate();$admin=Identifier::generate();$member=Identifier::generate();$this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id'=>$o,'name'=>$slug,'slug'=>$slug]);foreach([[$owner,'owner'],[$admin,'admin'],[$member,'member']]as$i=>[$u,$role]){$this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$u,'email'=>$slug.$i.'@example.test']);$this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),:role,'active')")->execute(['id'=>Identifier::generate(),'o'=>$o,'u'=>$u,'role'=>$role]);}return[$o,$owner,$admin,$member];}
    private function project(string$o,string$u,string$slug):string{$id=Identifier::generate();$this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,created_by) VALUES(UNHEX(:id),UNHEX(:o),:code,:name,'Client','City','Erf 1','Office','','architect',UNHEX(:u))")->execute(['id'=>$id,'o'=>$o,'code'=>strtoupper(substr(hash('sha256',$slug),0,8)),'name'=>$slug,'u'=>$u]);return$id;}
    private function file(string$o,string$p,string$u,string$name):string{$id=Identifier::generate();$this->pdo->prepare("INSERT INTO stored_files(id,organisation_id,project_id,uploaded_by,original_name,storage_name,mime_type,byte_size,sha256) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:p),UNHEX(:u),:name,:storage,'application/pdf',4,:sha)")->execute(['id'=>$id,'o'=>$o,'p'=>$p,'u'=>$u,'name'=>$name,'storage'=>substr(hash('sha256',$id),0,48),'sha'=>hash('sha256',$name)]);return$id;}
}
