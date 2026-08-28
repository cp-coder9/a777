<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Documents;

use Architex\Platform\Documents\DocumentException;
use Architex\Platform\Documents\DocumentService;
use Architex\Platform\Identity\Identifier;
use Architex\Tests\Support\MariaDbTestCase;

final class DocumentServiceTest extends MariaDbTestCase
{
    public function testIssuedDrawingIsImmutableAndNewRevisionSupersedesIt(): void
    {
        [$organisationId,$ownerId,$projectId]=$this->seed();
        $service=new DocumentService($this->pdo);
        $draft=$service->create($organisationId,$ownerId,'owner',[
            'projectId'=>$projectId,'title'=>'Ground floor plan','documentType'=>'drawing','discipline'=>'architectural','phase'=>'design_development','issuePurpose'=>'for_review','revisionCode'=>'P01','notes'=>'Initial coordinated draft.','drawing'=>['drawingNumber'=>'A-101','sheetType'=>'floor_plan','scale'=>'1:100'],
        ]);
        self::assertSame('draft',$draft['status']);self::assertSame('A-101',$draft['drawing']['drawingNumber']);
        $edited=$service->updateDraft($organisationId,$ownerId,'owner',$draft['id'],['notes'=>'Updated before review.','drawing'=>['drawingNumber'=>'A-101','sheetType'=>'floor_plan','scale'=>'1:50']]);
        self::assertSame('1:50',$edited['drawing']['scale']);
        $review=$service->submitForReview($organisationId,$ownerId,'owner',$draft['id'],'Ready for professional drawing review.');
        try{$service->approve($organisationId,$ownerId,'owner',$draft['id'],false,'Reviewed.');self::fail('Approval must require explicit human confirmation.');}catch(DocumentException $e){self::assertSame('document_approval_confirmation_required',$e->reason);}
        $approved=$service->approve($organisationId,$ownerId,'owner',$draft['id'],true,'Responsible professional reviewed drawing metadata and linked evidence.');
        $issued=$service->issue($organisationId,$ownerId,'owner',$approved['id'],true,'Issued for construction after coordinated review.','for_construction');
        self::assertSame('issued',$issued['status']);self::assertSame('for_construction',$issued['issuePurpose']);self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/',$issued['auditHash']);
        try{$service->updateDraft($organisationId,$ownerId,'owner',$issued['id'],['notes'=>'Silent mutation']);self::fail('Issued content must be immutable.');}catch(DocumentException $e){self::assertSame('document_not_draft',$e->reason);}
        $revision=$service->revise($organisationId,$ownerId,'owner',$issued['id'],['revisionCode'=>'C02','reason'=>'Coordinated structural opening changed.','notes'=>'Opening revised.','issuePurpose'=>'for_review','drawing'=>['drawingNumber'=>'A-101','sheetType'=>'floor_plan','scale'=>'1:50']]);
        self::assertSame('draft',$revision['status']);self::assertSame($issued['id'],$revision['previousRevisionId']);self::assertSame('superseded',$service->get($organisationId,$ownerId,$issued['id'])['status']);self::assertSame('Updated before review.',$service->get($organisationId,$ownerId,$issued['id'])['notes']);
        self::assertCount(2,$service->list($organisationId,$ownerId,$projectId));
    }

    /** @return array{string,string,string} */
    private function seed(): array
    {
        $organisationId=Identifier::generate();$ownerId=Identifier::generate();$projectId=Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'documents-owner@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$ownerId]);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Document Practice','document-practice','active')")->execute(['id'=>$organisationId]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'owner','active')")->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'user_id'=>$ownerId]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:id),UNHEX(:organisation_id),'DOC-1','Document Control Project','Document Client','Tshwane','Erf 99','Commercial','','architect','design_development',UNHEX(:user_id))")->execute(['id'=>$projectId,'organisation_id'=>$organisationId,'user_id'=>$ownerId]);
        return[$organisationId,$ownerId,$projectId];
    }
}
