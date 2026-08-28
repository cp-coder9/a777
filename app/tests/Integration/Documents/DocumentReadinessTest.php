<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Documents;

use Architex\Platform\Documents\DocumentService;
use Architex\Platform\Identity\Identifier;
use Architex\Tests\Support\MariaDbTestCase;

final class DocumentReadinessTest extends MariaDbTestCase
{
    public function testStructuredReadinessAndSupersededConstructionAlert(): void
    {
        [$organisationId,$ownerId,$projectId]=$this->seed();$service=new DocumentService($this->pdo);
        $initial=$service->readiness($organisationId,$ownerId,$projectId);
        self::assertFalse($initial['municipal_submission']['ready']);
        self::assertContains('MUNICIPAL_SHEET_MISSING',array_column($initial['municipal_submission']['findings'],'code'));
        self::assertContains('NO_CURRENT_CONSTRUCTION_DRAWINGS',array_column($initial['construction_issue']['findings'],'code'));

        $draft=$service->create($organisationId,$ownerId,'owner',['projectId'=>$projectId,'title'=>'Construction floor plan','documentType'=>'drawing','discipline'=>'architectural','phase'=>'construction_execution','issuePurpose'=>'for_review','revisionCode'=>'C01','notes'=>'Coordinated construction issue.','drawing'=>['drawingNumber'=>'A-201','sheetType'=>'floor_plan','scale'=>'1:100']]);
        $service->submitForReview($organisationId,$ownerId,'owner',$draft['id'],'Ready.');$service->approve($organisationId,$ownerId,'owner',$draft['id'],true,'Reviewed.');$issued=$service->issue($organisationId,$ownerId,'owner',$draft['id'],true,'Construction issue reviewed.','for_construction');
        self::assertTrue($service->readiness($organisationId,$ownerId,$projectId)['construction_issue']['ready']);
        $service->revise($organisationId,$ownerId,'owner',$issued['id'],['revisionCode'=>'C02','reason'=>'Opening coordinated.','notes'=>'Revised opening.','issuePurpose'=>'for_review','drawing'=>['drawingNumber'=>'A-201','sheetType'=>'floor_plan','scale'=>'1:100']]);
        $construction=$service->readiness($organisationId,$ownerId,$projectId)['construction_issue'];$codes=array_column($construction['findings'],'code');
        self::assertFalse($construction['ready']);self::assertContains('SUPERSEDED_CONSTRUCTION_DRAWING',$codes);self::assertContains('NO_CURRENT_CONSTRUCTION_DRAWINGS',$codes);
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM project_records WHERE module_key='documents' AND record_type='technical_drawings'")->fetchColumn());
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM inbox_items WHERE event_type='superseded_construction_drawing'")->fetchColumn());
    }

    /** @return array{string,string,string} */
    private function seed(): array
    {
        $organisationId=Identifier::generate();$ownerId=Identifier::generate();$projectId=Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'readiness-owner@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$ownerId]);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Readiness Practice','readiness-practice','active')")->execute(['id'=>$organisationId]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'owner','active')")->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'user_id'=>$ownerId]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:id),UNHEX(:organisation_id),'READY-1','Readiness Project','Readiness Client','Tshwane','Erf 100','Commercial','','architect','construction_execution',UNHEX(:user_id))")->execute(['id'=>$projectId,'organisation_id'=>$organisationId,'user_id'=>$ownerId]);
        return[$organisationId,$ownerId,$projectId];
    }
}
