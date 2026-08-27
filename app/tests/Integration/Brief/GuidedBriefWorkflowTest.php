<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Brief;

use Architex\Platform\Brief\BriefException;
use Architex\Platform\Brief\GuidedBriefService;
use Architex\Platform\Identity\Identifier;
use Architex\Tests\Support\MariaDbTestCase;

final class GuidedBriefWorkflowTest extends MariaDbTestCase
{
    public function testResumesSubmitsAndProducesExplicitlyAdvisoryInterpretation(): void
    {
        [$organisationId,$userId] = $this->seedTenant('brief-alpha');
        $service = new GuidedBriefService($this->pdo);
        $draft = $service->create($organisationId,$userId,'Home alteration brief');
        $saved = $service->save($organisationId,$userId,$draft['id'],1,[
            'intent'=>'renovate_or_add','goal'=>'Add a bedroom and make the house work for a growing family.',
            'hasExistingPlans'=>false,'workAlreadyExists'=>true,'urgency'=>'soon','budgetBand'=>'needs_guidance',
            'supportNeeds'=>['plans','approvals','construction_pricing'],'currentStep'=>6,
        ]);
        self::assertSame(2,$saved['version']);

        $submitted = $service->submit($organisationId,$userId,$draft['id'],2);
        self::assertSame('submitted',$submitted['status']);
        self::assertContains('architect',$submitted['interpretation']['likelyProfessionalRequirements']);
        self::assertContains('quantity_surveyor',$submitted['interpretation']['likelyProfessionalRequirements']);
        self::assertContains('existing_work_requires_assessment',$submitted['interpretation']['riskFlags']);
        self::assertTrue($submitted['interpretation']['advisoryOnly']);
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM brief_interpretations')->fetchColumn());
        self::assertSame(3,(int)$this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
    }

    public function testRejectsIncompleteSubmissionAndStaleOrCrossTenantWrites(): void
    {
        [$organisationId,$userId] = $this->seedTenant('brief-beta');
        [$otherOrganisationId,$otherUserId] = $this->seedTenant('brief-gamma');
        $service = new GuidedBriefService($this->pdo);
        $draft = $service->create($organisationId,$userId,'Incomplete');
        try {$service->submit($organisationId,$userId,$draft['id'],1);self::fail('Incomplete brief must not submit.');}
        catch(BriefException $exception){self::assertSame('brief_incomplete',$exception->reason);}
        try {$service->save($organisationId,$userId,$draft['id'],99,['intent'=>'not_sure']);self::fail('Stale version must not write.');}
        catch(BriefException $exception){self::assertSame('stale_brief_version',$exception->reason);}
        $this->expectException(BriefException::class);
        $this->expectExceptionMessage('brief_not_found');
        $service->save($otherOrganisationId,$otherUserId,$draft['id'],1,['intent'=>'not_sure']);
    }

    public function testConvertsSubmittedBriefToImmutableProjectRecord(): void
    {
        [$organisationId,$userId] = $this->seedTenant('brief-delta');
        $projectId = $this->seedProject($organisationId,$userId);
        $service = new GuidedBriefService($this->pdo);
        $draft=$service->create($organisationId,$userId,'Approval brief');
        $service->save($organisationId,$userId,$draft['id'],1,['intent'=>'plans_approved','goal'=>'Prepare compliant approval drawings.','hasExistingPlans'=>true,'workAlreadyExists'=>false,'urgency'=>'normal','budgetBand'=>'defined','supportNeeds'=>['plans','approvals'],'currentStep'=>6]);
        $service->submit($organisationId,$userId,$draft['id'],2);
        $converted=$service->assignToProject($organisationId,$userId,'owner',$draft['id'],$projectId,3);

        self::assertSame('converted',$converted['status']);
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM project_records WHERE record_type='project_brief' AND status='approved'")->fetchColumn());
        $this->expectException(BriefException::class);
        $this->expectExceptionMessage('brief_locked');
        $service->save($organisationId,$userId,$draft['id'],4,['goal'=>'Overwrite history']);
    }

    /** @return array{string,string} */
    private function seedTenant(string $slug): array
    {
        $organisationId=Identifier::generate();$userId=Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$userId,'email'=>$slug.'@example.test']);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id'=>$organisationId,'name'=>ucfirst($slug),'slug'=>$slug]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'owner','active')")->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'user_id'=>$userId]);
        return [$organisationId,$userId];
    }

    private function seedProject(string $organisationId,string $userId): string
    {
        $id=Identifier::generate();
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,created_by) VALUES(UNHEX(:id),UNHEX(:organisation_id),'BRIEF-1','Brief project','Client','Tshwane','Erf 1','Residential','','architect',UNHEX(:user_id))")->execute(['id'=>$id,'organisation_id'=>$organisationId,'user_id'=>$userId]);
        return $id;
    }
}
