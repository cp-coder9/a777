<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Documents;

use Architex\Platform\Documents\DocumentService;
use Architex\Platform\Documents\DocumentTransmittalService;
use Architex\Platform\Identity\Identifier;
use Architex\Tests\Support\MariaDbTestCase;

final class DocumentTransmittalServiceTest extends MariaDbTestCase
{
    public function testIssuesAnImmutableExactRevisionTransmittalWithEvidence():void
    {
        [$organisationId,$ownerId,$projectId]=$this->seed();$documents=new DocumentService($this->pdo);$revision=$documents->create($organisationId,$ownerId,'owner',['projectId'=>$projectId,'title'=>'Tender specification','documentType'=>'specification','discipline'=>'general','phase'=>'tender_procurement','issuePurpose'=>'for_tender','revisionCode'=>'A','notes'=>'Exact tender issue.']);$documents->submitForReview($organisationId,$ownerId,'owner',$revision['id'],'Ready for review.');$documents->approve($organisationId,$ownerId,'owner',$revision['id'],true,'Reviewed exact specification.');$issuedRevision=$documents->issue($organisationId,$ownerId,'owner',$revision['id'],true,'Issued for tender.');
        $service=new DocumentTransmittalService($this->pdo);$draft=$service->create($organisationId,$ownerId,'owner',['projectId'=>$projectId,'code'=>'TR-001','title'=>'Tender issue 1','purpose'=>'for_tender','recipientRoles'=>['quantity_surveyor','contractor'],'revisionIds'=>[$issuedRevision['id']],'notes'=>'Issue exact controlled revision.']);
        self::assertSame('draft',$draft['status']);self::assertCount(1,$draft['items']);self::assertSame($issuedRevision['id'],$draft['items'][0]['revisionId']);self::assertSame(['quantity_surveyor','contractor'],$draft['recipientRoles']);
        $issued=$service->issue($organisationId,$ownerId,'owner',$draft['id'],true,'Approved exact transmittal and recipients.');self::assertSame('issued',$issued['status']);self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/',$issued['snapshotSha256']);self::assertSame('for_tender',$issued['snapshot']['purpose']);self::assertSame($issuedRevision['auditHash'],$issued['snapshot']['items'][0]['documentAuditHash']);self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM project_records WHERE record_type='document_transmittal' AND status='issued'")->fetchColumn());self::assertSame(2,(int)$this->pdo->query("SELECT COUNT(*) FROM inbox_items WHERE event_type='document_transmittal_issued'")->fetchColumn());self::assertSame(2,(int)$this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE action LIKE 'document_transmittal.%'")->fetchColumn());

        try{$service->issue($organisationId,$ownerId,'owner',$draft['id'],true,'Issue again.');self::fail('Issued transmittal must be immutable.');}catch(\Throwable){self::assertTrue(true);}
        self::assertSame($issued['snapshotSha256'],$service->get($organisationId,$ownerId,$draft['id'])['snapshotSha256']);
    }

    /** @return array{string,string,string} */private function seed():array{$o=Identifier::generate();$u=Identifier::generate();$p=Identifier::generate();$this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'transmittal@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$u]);$this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Transmittal Org',:slug,'active')")->execute(['id'=>$o,'slug'=>'transmittal-'.substr($o,0,8)]);$this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'owner','active')")->execute(['id'=>Identifier::generate(),'o'=>$o,'u'=>$u]);$this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:p),UNHEX(:o),'TR-TEST','Transmittal Project','Client','Tshwane','Erf 1','Commercial','','architect','tender_procurement',UNHEX(:u))")->execute(['p'=>$p,'o'=>$o,'u'=>$u]);return[$o,$u,$p];}
}
