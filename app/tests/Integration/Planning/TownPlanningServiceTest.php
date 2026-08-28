<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Planning;

use Architex\Platform\Identity\Identifier;
use Architex\Platform\Planning\TownPlanningException;
use Architex\Platform\Planning\TownPlanningService;
use Architex\Tests\Support\MariaDbTestCase;

final class TownPlanningServiceTest extends MariaDbTestCase
{
    public function testApplicationRegistersAndHumanGovernedSubmissionArePersistent(): void
    {
        [$organisationId,$ownerId,$projectId]=$this->seed('PLAN1');
        $service=new TownPlanningService($this->pdo);
        $application=$service->create($organisationId,$ownerId,'owner',[
            'projectId'=>$projectId,'applicationNumber'=>'APP-2026-101','applicationType'=>'rezoning','title'=>'Mixed-use rezoning','municipality'=>'City of Tshwane','propertyReference'=>'Erf 21 Menlyn','responsibleRole'=>'architect',
            'sourceBasis'=>[['title'=>'City of Tshwane Land Use Management By-law','url'=>'https://www.tshwane.gov.za/','retrievedAt'=>'2026-08-28']],
        ]);
        self::assertSame('draft',$application['status']);self::assertSame(1,$application['version']);self::assertFalse($application['externalSubmissionClaimed']);

        $document=$service->addEntry($organisationId,$ownerId,'owner',$application['id'],1,['entryType'=>'document','title'=>'Signed application form','status'=>'complete','required'=>true,'basis'=>'Municipality application checklist','payload'=>['documentRevision'=>'P01']]);
        self::assertSame(2,$document['applicationVersion']);
        $deadline=$service->addEntry($organisationId,$ownerId,'owner',$application['id'],2,['entryType'=>'deadline','title'=>'Public notice closes','status'=>'pending','required'=>true,'basis'=>'Published notice','dueAt'=>'2026-10-30T15:00:00Z','payload'=>['statutoryBasis'=>'Municipal notice']]);
        self::assertSame(3,$deadline['applicationVersion']);
        $service->addEntry($organisationId,$ownerId,'owner',$application['id'],3,['entryType'=>'fee','title'=>'Application fee','status'=>'paid','required'=>true,'basis'=>'Municipality tariff','amountCents'=>425000,'payload'=>['receiptReference'=>'RCT-101']]);

        try{$service->submit($organisationId,$ownerId,'owner',$application['id'],4,false,'architect','Reviewed exact pack.','CITY-REF-101');self::fail('Submission must require human confirmation.');}catch(TownPlanningException$e){self::assertSame('planning_submission_confirmation_required',$e->reason);}
        try{$service->submit($organisationId,$ownerId,'owner',$application['id'],3,true,'architect','Reviewed exact pack.','CITY-REF-101');self::fail('Stale writes must fail.');}catch(TownPlanningException$e){self::assertSame('planning_version_conflict',$e->reason);}

        $submitted=$service->submit($organisationId,$ownerId,'owner',$application['id'],4,true,'architect','Signed pack and evidence register reviewed before recording external handoff.','CITY-REF-101');
        self::assertSame('active',$submitted['status']);self::assertSame('submission',$submitted['stage']);self::assertTrue($submitted['externalSubmissionClaimed']);self::assertSame('CITY-REF-101',$submitted['externalReference']);self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/',$submitted['snapshotSha256']);self::assertNotNull($submitted['projectRecordId']);self::assertCount(3,$submitted['entries']);
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM project_records WHERE module_key='town_planning' AND status='issued'")->fetchColumn());
        self::assertGreaterThanOrEqual(5,(int)$this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE action LIKE 'planning.%'")->fetchColumn());
        try{$service->addEntry($organisationId,$ownerId,'owner',$application['id'],5,['entryType'=>'message','title'=>'Late mutation','status'=>'open','required'=>false,'basis'=>'','payload'=>[]]);self::fail('Submitted snapshot must be immutable.');}catch(TownPlanningException$e){self::assertSame('planning_application_submitted',$e->reason);}
    }

    public function testRequiredEvidenceAndTenantIsolationFailClosed(): void
    {
        [$organisationId,$ownerId,$projectId]=$this->seed('PLAN2');[$otherOrganisation,$otherOwner]=$this->seed('PLAN3');$service=new TownPlanningService($this->pdo);
        $application=$service->create($organisationId,$ownerId,'owner',['projectId'=>$projectId,'applicationNumber'=>'APP-2026-102','applicationType'=>'consent_use','title'=>'Consent use','municipality'=>'City of Tshwane','propertyReference'=>'Erf 22 Menlyn','responsibleRole'=>'architect','sourceBasis'=>[['title'=>'Scheme source','url'=>'https://example.test/scheme','retrievedAt'=>'2026-08-28']]]);
        try{$service->submit($organisationId,$ownerId,'owner',$application['id'],1,true,'architect','Reviewed empty pack.','CITY-REF-102');self::fail('Required document evidence must block submission.');}catch(TownPlanningException$e){self::assertSame('planning_submission_evidence_incomplete',$e->reason);}
        try{$service->get($otherOrganisation,$otherOwner,$application['id']);self::fail('Other tenant must not read.');}catch(TownPlanningException$e){self::assertSame('planning_application_not_found',$e->reason);}
        self::assertSame([], $service->list($otherOrganisation,$otherOwner,null));
    }

    /** @return array{string,string,string} */
    private function seed(string$code):array
    {
        $organisationId=Identifier::generate();$ownerId=Identifier::generate();$projectId=Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$ownerId,'email'=>strtolower($code).'@planning.test']);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id'=>$organisationId,'name'=>$code,'slug'=>strtolower($code).'-'.substr($organisationId,0,8)]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:user),'owner','active')")->execute(['id'=>Identifier::generate(),'organisation'=>$organisationId,'user'=>$ownerId]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:id),UNHEX(:organisation),:code,:name,'Client','City of Tshwane','Erf 21','Mixed use','','architect','design_development',UNHEX(:user))")->execute(['id'=>$projectId,'organisation'=>$organisationId,'code'=>$code,'name'=>"$code planning",'user'=>$ownerId]);
        return[$organisationId,$ownerId,$projectId];
    }
}
