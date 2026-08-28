<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\SpecForge;

use Architex\Platform\Identity\Identifier;
use Architex\Platform\SpecForge\SpecForgeException;
use Architex\Platform\SpecForge\SpecForgeService;
use Architex\Tests\Support\MariaDbTestCase;

final class SpecForgeServiceTest extends MariaDbTestCase
{
    public function testPersistsVersionedSectionsItemsAndDerivedRisks(): void
    {
        [$organisationId,$ownerId,$projectId]=$this->seed('SPEC1');
        $service=new SpecForgeService($this->pdo);
        $workspace=$service->create($organisationId,$ownerId,'owner',['projectId'=>$projectId,'title'=>'Tender specification','revisionCode'=>'P01','issuePurpose'=>'for_tender']);
        self::assertSame('draft',$workspace['status']);self::assertSame(1,$workspace['version']);

        $section=$service->addSection($organisationId,$ownerId,'owner',$workspace['id'],1,['code'=>'09','title'=>'Internal finishes','discipline'=>'architectural','ownerRole'=>'architect','reviewerRole'=>'quantity_surveyor','approverRole'=>'architect']);
        self::assertSame(2,$section['workspaceVersion']);
        $item=$service->addItem($organisationId,$ownerId,'owner',$workspace['id'],$section['id'],2,[
            'code'=>'FIN-WT-001','title'=>'Large format porcelain wall tile','room'=>'Main Lobby','package'=>'Finishes','supplier'=>'Practice-selected supplier','model'=>'600x1200 matte porcelain','finish'=>'Warm limestone','dimensions'=>'600 x 1200 mm',
            'drawingReferences'=>['A-420 lobby elevations'],'clauseReferences'=>['Slip resistance requires professional verification'],'budgetAllowanceCents'=>11500000,'estimatedCostCents'=>12852600,'leadTimeDays'=>70,'requiresClientDecision'=>false,
            'ownerRole'=>'architect','reviewerRole'=>'quantity_surveyor','approverRole'=>'architect','sustainability'=>'Low VOC adhesive required','warranty'=>'Manufacturer warranty to be confirmed','notes'=>'Human-entered specification; no supplier integration.',
        ]);
        self::assertSame(3,$item['workspaceVersion']);
        $loaded=$service->get($organisationId,$ownerId,$workspace['id']);
        self::assertCount(1,$loaded['sections']);self::assertCount(1,$loaded['items']);
        self::assertSame(1352600,$loaded['riskSummary']['budgetDeltaCents']);
        self::assertSame(1,$loaded['riskSummary']['overBudgetCount']);self::assertSame(1,$loaded['riskSummary']['longLeadCount']);
        self::assertFalse($loaded['externalDeliveryClaimed']);self::assertFalse($loaded['aiSuggestionsAvailable']);

        $this->expectException(SpecForgeException::class);
        $service->addSection($organisationId,$ownerId,'owner',$workspace['id'],1,['code'=>'10','title'=>'Stale write','discipline'=>'architectural','ownerRole'=>'architect','reviewerRole'=>'architect','approverRole'=>'architect']);
    }

    public function testRoleReviewControlsIssueAndIssuedSnapshotIsImmutable(): void
    {
        [$organisationId,$ownerId,$projectId]=$this->seed('SPEC2');$service=new SpecForgeService($this->pdo);
        $workspace=$service->create($organisationId,$ownerId,'owner',['projectId'=>$projectId,'title'=>'Construction specification','revisionCode'=>'C01','issuePurpose'=>'for_construction']);
        $section=$service->addSection($organisationId,$ownerId,'owner',$workspace['id'],1,['code'=>'26','title'=>'Feature lighting','discipline'=>'electrical','ownerRole'=>'architect','reviewerRole'=>'engineer','approverRole'=>'architect']);
        $item=$service->addItem($organisationId,$ownerId,'owner',$workspace['id'],$section['id'],2,['code'=>'LGT-PD-001','title'=>'Reception pendant','room'=>'Reception','package'=>'Electrical','budgetAllowanceCents'=>7200000,'estimatedCostCents'=>7100000,'leadTimeDays'=>42,'requiresClientDecision'=>false,'ownerRole'=>'architect','reviewerRole'=>'engineer','approverRole'=>'architect']);
        try{$service->issue($organisationId,$ownerId,'owner',$workspace['id'],3,true,'architect','Exact specification reviewed.');self::fail('Unapproved specification must not issue.');}catch(SpecForgeException$exception){self::assertSame('specforge_issue_blocked',$exception->getMessage());}
        try{$service->decideItem($organisationId,$ownerId,'owner',$item['id'],['action'=>'approve','actingRole'=>'quantity_surveyor','humanConfirmed'=>true,'reason'=>'Wrong role.']);self::fail('Wrong approver must fail.');}catch(SpecForgeException$exception){self::assertSame('specforge_decision_role_denied',$exception->getMessage());}
        $decision=$service->decideItem($organisationId,$ownerId,'owner',$item['id'],['action'=>'approve','actingRole'=>'architect','humanConfirmed'=>true,'reason'=>'Reviewed product, cost and source references.']);
        self::assertSame('approved',$decision['status']);self::assertSame(4,$decision['workspaceVersion']);
        $issued=$service->issue($organisationId,$ownerId,'owner',$workspace['id'],4,true,'architect','Responsible professional reviewed the exact issued specification.');
        self::assertSame('issued',$issued['status']);self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/',$issued['snapshotSha256']);
        self::assertNotNull($issued['documentRevisionId']);self::assertNotNull($issued['projectRecordId']);self::assertFalse($issued['externalDeliveryClaimed']);
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM document_revisions WHERE status='issued' AND issue_purpose='for_construction'")->fetchColumn());
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM project_records WHERE module_key='specforge'")->fetchColumn());
        try{$service->addSection($organisationId,$ownerId,'owner',$workspace['id'],5,['code'=>'27','title'=>'Post issue edit','discipline'=>'electrical','ownerRole'=>'architect','reviewerRole'=>'engineer','approverRole'=>'architect']);self::fail('Issued workspace must be immutable.');}catch(SpecForgeException$exception){self::assertSame('specforge_workspace_not_draft',$exception->getMessage());}
    }

    public function testCrossTenantReadsFailClosed(): void
    {
        [$organisationId,$ownerId,$projectId]=$this->seed('SPEC3');[$otherOrganisation,$otherOwner]=$this->seed('SPEC4');$service=new SpecForgeService($this->pdo);
        $workspace=$service->create($organisationId,$ownerId,'owner',['projectId'=>$projectId,'title'=>'Private specification','revisionCode'=>'P01','issuePurpose'=>'for_review']);
        $this->expectException(SpecForgeException::class);$service->get($otherOrganisation,$otherOwner,$workspace['id']);
    }

    /** @return array{string,string,string} */
    private function seed(string $code): array
    {
        $organisationId=Identifier::generate();$ownerId=Identifier::generate();$projectId=Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$ownerId,'email'=>strtolower($code).'@specforge.test']);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id'=>$organisationId,'name'=>$code,'slug'=>strtolower($code).'-'.substr($organisationId,0,8)]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:user),'owner','active')")->execute(['id'=>Identifier::generate(),'organisation'=>$organisationId,'user'=>$ownerId]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:id),UNHEX(:organisation),:code,:name,'Client','Tshwane','Erf 123','Commercial','','architect','design_development',UNHEX(:user))")->execute(['id'=>$projectId,'organisation'=>$organisationId,'code'=>$code,'name'=>"$code specification",'user'=>$ownerId]);
        return[$organisationId,$ownerId,$projectId];
    }
}
