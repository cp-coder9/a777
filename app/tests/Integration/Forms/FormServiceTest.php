<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Forms;

use Architex\Platform\Forms\FormException;
use Architex\Platform\Forms\FormService;
use Architex\Platform\Identity\Identifier;
use Architex\Tests\Support\MariaDbTestCase;

final class FormServiceTest extends MariaDbTestCase
{
    public function testVersionedFormRequiresHumanApprovalAndIssuesImmutableExports():void
    {
        [$organisationId,$ownerId,$projectId]=$this->seed();$service=new FormService($this->pdo);
        $templates=$service->listTemplates($organisationId,$ownerId);self::assertNotEmpty($templates);$template=$templates[0];self::assertSame('verified',$template['status']);
        $draft=$service->createDraft($organisationId,$ownerId,'owner',['projectId'=>$projectId,'templateId'=>$template['id'],'title'=>'Project appointment review','values'=>['scopeSummary'=>'Architectural services through municipal submission.']]);
        self::assertSame(1,$draft['revision']);self::assertSame('draft',$draft['status']);self::assertSame('FORM-1',$draft['autoFillSnapshot']['project']['projectCode']);
        $revision=$service->revise($organisationId,$ownerId,'owner',$draft['id'],['values'=>$draft['values']+['municipality'=>'City of Tshwane'],'reason'=>'Municipality confirmed.']);
        self::assertSame(2,$revision['revision']);self::assertSame('superseded',$service->getDraft($organisationId,$ownerId,$draft['id'])['status']);
        $review=$service->submitForReview($organisationId,$ownerId,'owner',$revision['id'],'Ready for professional review.');self::assertSame('review_required',$review['status']);
        try{$service->approve($organisationId,$ownerId,'owner',$revision['id'],false,'');self::fail('Approval must be an explicit human action.');}catch(FormException $e){self::assertSame('form_approval_confirmation_required',$e->reason);}
        $approved=$service->approve($organisationId,$ownerId,'owner',$revision['id'],true,'Every auto-filled and edited field was reviewed.');self::assertSame('approved',$approved['status']);
        try{$service->issue($organisationId,$ownerId,'owner',$revision['id'],false,'');self::fail('Issue must be an explicit human action.');}catch(FormException $e){self::assertSame('form_issue_confirmation_required',$e->reason);}
        $issued=$service->issue($organisationId,$ownerId,'owner',$revision['id'],true,'Responsible professional approved this exact revision.');self::assertSame('issued',$issued['status']);self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/',$issued['auditHash']);self::assertSame('not_configured',$issued['signatureStatus']);
        $exports=$service->listExports($organisationId,$ownerId,$revision['id']);self::assertSame(['html','json'],array_column($exports,'format'));self::assertCount(2,array_unique(array_column($exports,'checksum')));
        try{$service->revise($organisationId,$ownerId,'owner',$revision['id'],['values'=>[],'reason'=>'']);self::fail('Issued forms must be revised from an immutable snapshot with a reason.');}catch(FormException $e){self::assertSame('form_revision_reason_required',$e->reason);}
    }

    /** @return array{string,string,string} */
    private function seed():array
    {
        $organisationId=Identifier::generate();$ownerId=Identifier::generate();$projectId=Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'forms-owner@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$ownerId]);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Forms Practice','forms-practice','active')")->execute(['id'=>$organisationId]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'owner','active')")->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'user_id'=>$ownerId]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:id),UNHEX(:organisation_id),'FORM-1','Versioned Forms Project','Form Client','Tshwane','Erf 55','Residential','','architect','appointment',UNHEX(:user_id))")->execute(['id'=>$projectId,'organisation_id'=>$organisationId,'user_id'=>$ownerId]);
        return[$organisationId,$ownerId,$projectId];
    }
}
