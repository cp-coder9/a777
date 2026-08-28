<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Municipal;

use Architex\Platform\Identity\Identifier;
use Architex\Platform\Municipal\MunicipalReadinessException;
use Architex\Platform\Municipal\MunicipalReadinessService;
use Architex\Platform\Municipal\MunicipalRequirementRegistry;
use Architex\Tests\Support\MariaDbTestCase;

final class MunicipalReadinessServiceTest extends MariaDbTestCase
{
    public function testPersistsWholeTeamHighComplexityAssessmentAcrossEightCategories(): void
    {
        [$organisationId,$ownerId,$projectId] = $this->seedProject('HIGH','City of Johannesburg');
        $matrix=(new MunicipalRequirementRegistry($this->pdo))->ensureImplementationSeed($organisationId,$ownerId,'owner','City of Johannesburg');
        $service=new MunicipalReadinessService($this->pdo,new MunicipalRequirementRegistry($this->pdo));

        $assessment=$service->assess($organisationId,$ownerId,'owner',$projectId,$matrix['id'],[
            'occupancyType'=>'single_residential','changesLoadBearing'=>true,'changesDrainageOrStormwater'=>true,
            'publicAccessOrAssembly'=>false,'envelopeEnergyImpact'=>true,'zoningKnown'=>false,'coverageOrParkingRisk'=>true,
            'boundaryOrServitudeUnclear'=>true,'heritagePotential'=>false,'environmentalSensitivity'=>false,'trafficImpact'=>false,
            'estimatedConstructionValueZar'=>2800000,'nbrAdvisoryReviewed'=>false,'availableSupportingDocuments'=>[],
            'professionalSignoffs'=>[],'clientAuthorityConfirmed'=>false,
        ]);

        self::assertSame('high',$assessment['complexity']['level']);
        self::assertCount(5,$assessment['complexity']['triggers']);
        self::assertCount(8,$assessment['categories']);
        self::assertFalse($assessment['readyForProfessionalSubmissionReview']);
        self::assertSame('review_required',$assessment['matrix']['status']);
        self::assertFalse($assessment['professionalApprovalEligible']);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/',$assessment['snapshotSha256']);

        $required=array_column(array_filter($assessment['professionalRouting'],static fn(array$r):bool=>$r['status']==='required'),'discipline');
        foreach(['client','lead_professional','architect','municipal_coordinator','structural_engineer','civil_engineer','energy_consultant','town_planner','land_surveyor']as$discipline)self::assertContains($discipline,$required);
        $excluded=array_column(array_filter($assessment['professionalRouting'],static fn(array$r):bool=>$r['status']==='not_currently_required'),'discipline');
        foreach(['heritage_practitioner','environmental_practitioner','traffic_engineer']as$discipline)self::assertContains($discipline,$excluded);
        self::assertNotEmpty($assessment['missingDocuments']);
        self::assertNotEmpty($assessment['requiredHumanSignoffs']);
        self::assertCount(6,$assessment['evidencePack']);
        self::assertSame($assessment['id'],$service->get($organisationId,$ownerId,$assessment['id'])['id']);
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM project_records WHERE module_key='municipal_readiness'")->fetchColumn());

        $openBefore=(int)$this->pdo->query("SELECT COUNT(*) FROM inbox_items WHERE event_type LIKE 'municipal_readiness_%' AND status='open'")->fetchColumn();
        $service->assess($organisationId,$ownerId,'owner',$projectId,$matrix['id'],[
            'occupancyType'=>'single_residential','changesLoadBearing'=>true,'changesDrainageOrStormwater'=>true,'publicAccessOrAssembly'=>false,
            'envelopeEnergyImpact'=>true,'zoningKnown'=>false,'coverageOrParkingRisk'=>true,'boundaryOrServitudeUnclear'=>true,
            'heritagePotential'=>false,'environmentalSensitivity'=>false,'trafficImpact'=>false,'estimatedConstructionValueZar'=>2800000,
            'nbrAdvisoryReviewed'=>false,'availableSupportingDocuments'=>[],'professionalSignoffs'=>[],'clientAuthorityConfirmed'=>false,
        ]);
        self::assertSame($openBefore,(int)$this->pdo->query("SELECT COUNT(*) FROM inbox_items WHERE event_type LIKE 'municipal_readiness_%' AND status='open'")->fetchColumn());

        $this->expectException(MunicipalReadinessException::class);
        $service->approveForProfessionalReview($organisationId,$ownerId,'owner',$assessment['id'],true,'Reviewed exact readiness assessment.');
    }

    public function testLowComplexityExcludesUntriggeredSpecialistsAndTenantReadsFailClosed(): void
    {
        [$organisationId,$ownerId,$projectId] = $this->seedProject('LOW','Tshwane');
        [$otherOrganisationId,$otherOwnerId] = $this->seedProject('OTHER','Tshwane');
        $registry=new MunicipalRequirementRegistry($this->pdo);$matrix=$registry->ensureImplementationSeed($organisationId,$ownerId,'owner','Tshwane');$service=new MunicipalReadinessService($this->pdo,$registry);
        $facts=[
            'occupancyType'=>'single_residential','changesLoadBearing'=>false,'changesDrainageOrStormwater'=>false,'publicAccessOrAssembly'=>false,
            'envelopeEnergyImpact'=>false,'zoningKnown'=>true,'coverageOrParkingRisk'=>false,'boundaryOrServitudeUnclear'=>false,
            'heritagePotential'=>false,'environmentalSensitivity'=>false,'trafficImpact'=>false,'estimatedConstructionValueZar'=>500000,
            'nbrAdvisoryReviewed'=>true,'availableSupportingDocuments'=>['title_deed','sg_diagram'],'professionalSignoffs'=>['lead_professional'],'clientAuthorityConfirmed'=>true,
        ];$assessment=$service->assess($organisationId,$ownerId,'owner',$projectId,$matrix['id'],$facts);
        self::assertSame('low',$assessment['complexity']['level']);self::assertSame([],$assessment['complexity']['triggers']);
        $refuse=array_values(array_filter($assessment['checks'],static fn(array$check):bool=>$check['id']==='refuse_precheck'));self::assertCount(1,$refuse);self::assertSame('missing',$refuse[0]['status']);self::assertSame('Compliance ToolRuns + human review',$refuse[0]['evidenceSource']);
        $required=array_column(array_filter($assessment['professionalRouting'],static fn(array$r):bool=>$r['status']==='required'),'discipline');
        self::assertSame(['client','lead_professional','architect','municipal_coordinator'],$required);
        self::assertContains('structural_engineer',array_column(array_filter($assessment['professionalRouting'],static fn(array$r):bool=>$r['status']==='not_currently_required'),'discipline'));
        $this->pdo->prepare("INSERT INTO tool_runs(id,organisation_id,user_id,tool_id,tool_version,executed_role,assignment_mode,project_id,status,input_payload,output_payload,snapshot_payload,audit_hash,audit_reason,issued_at) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:user),'refuse_area_calculator','1.0.0','owner','internal_project',UNHEX(:project),'issued','{}',:output,'{}',:hash,'Reviewed refuse evidence.',UTC_TIMESTAMP(6))")->execute(['id'=>Identifier::generate(),'organisation'=>$organisationId,'user'=>$ownerId,'project'=>$projectId,'output'=>json_encode(['sourceStatus'=>'verified','professionalAuthority'=>['profileProfession'=>'architect'],'municipalApproval'=>false],JSON_THROW_ON_ERROR),'hash'=>str_repeat('a',64)]);
        $withRefuse=$service->assess($organisationId,$ownerId,'owner',$projectId,$matrix['id'],$facts);$refuse=array_values(array_filter($withRefuse['checks'],static fn(array$check):bool=>$check['id']==='refuse_precheck'));self::assertSame('complete',$refuse[0]['status']);
        try{$service->get($otherOrganisationId,$otherOwnerId,$assessment['id']);self::fail('Cross-tenant assessment read must fail.');}catch(MunicipalReadinessException){self::assertTrue(true);}
    }

    /** @return array{string,string,string} */
    private function seedProject(string$code,string$municipality):array
    {
        $organisationId=Identifier::generate();$ownerId=Identifier::generate();$projectId=Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$ownerId,'email'=>strtolower($code).'@municipal.test']);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id'=>$organisationId,'name'=>$code,'slug'=>strtolower($code).'-'.substr($organisationId,0,8)]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:user),'owner','active')")->execute(['id'=>Identifier::generate(),'organisation'=>$organisationId,'user'=>$ownerId]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:id),UNHEX(:organisation),:code,:name,'Client',:municipality,'Erf 123','Residential','','architect','design_development',UNHEX(:user))")->execute(['id'=>$projectId,'organisation'=>$organisationId,'code'=>$code,'name'=>"$code readiness",'municipality'=>$municipality,'user'=>$ownerId]);
        return[$organisationId,$ownerId,$projectId];
    }
}
