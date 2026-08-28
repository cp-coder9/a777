<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Procurement;

use Architex\Platform\Identity\Identifier;
use Architex\Platform\Documents\DocumentService;
use Architex\Platform\Procurement\ProcurementException;
use Architex\Platform\Procurement\ProcurementService;
use Architex\Platform\Procurement\ProcurementScopeClassifier;
use Architex\Tests\Support\MariaDbTestCase;

final class ProcurementFoundationServiceTest extends MariaDbTestCase
{
    public function testMarketplacePackageIssueMatchingInvitationAndTenantBoundary():void
    {
        [$buyerOrganisation,$buyer,$project]=$this->tenant('Buyer','buyer');
        [$sellerOrganisation,$seller]=$this->tenant('Seller','seller');
        $reviewer=Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'reviewer@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$reviewer]);
        $this->pdo->prepare("INSERT INTO professional_profiles(id,organisation_id,user_id,display_name,profession,bio,service_areas,directory_visible) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'Verified Builder','contractor_supplier','Reviewed contractor marketplace evidence.','[\"Gauteng\"]',1)")->execute(['id'=>Identifier::generate(),'o'=>$sellerOrganisation,'u'=>$seller]);
        $verification=Identifier::generate();
        $this->pdo->prepare("INSERT INTO verification_records(id,organisation_id,user_id,kind,grade,registration_body,registration_number,popia_consent_at,processing_purpose,retention_until,reviewed_by,reviewed_at) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'contractor_supplier_compliance','manually_reviewed','Test review body','TEST-CON-1',UTC_TIMESTAMP(6),'Marketplace verification test',DATE_ADD(UTC_DATE(),INTERVAL 1 YEAR),UNHEX(:reviewer),UTC_TIMESTAMP(6))")->execute(['id'=>$verification,'o'=>$sellerOrganisation,'u'=>$seller,'reviewer'=>$reviewer]);

        $service=new ProcurementService($this->pdo,new ProcurementScopeClassifier());
        $listing=$service->createListing($sellerOrganisation,$seller,'owner',[
            'listingType'=>'contractor','name'=>'Verified Builder','publicSummary'=>'General building and structural coordination capacity.',
            'disciplinesTrades'=>['general_building','structural_steel'],'serviceAreas'=>['Gauteng'],'capacityStatus'=>'available','supervisionRequired'=>false,
        ]);
        self::assertSame('draft',$listing['status']);
        $listing=$service->publishListing($sellerOrganisation,$seller,'owner',$listing['id'],$listing['version'],true,'Reviewed public marketplace facts.');
        self::assertSame('published',$listing['status']);
        self::assertSame('verified',$listing['verificationStatus']);
        $listingTwo=$service->createListing($sellerOrganisation,$seller,'owner',[
            'listingType'=>'contractor','name'=>'Alternative Builder','publicSummary'=>'Alternative general building and plumbing capacity.',
            'disciplinesTrades'=>['general_building','plumbing'],'serviceAreas'=>['Gauteng'],'capacityStatus'=>'available','supervisionRequired'=>false,
        ]);
        $listingTwo=$service->publishListing($sellerOrganisation,$seller,'owner',$listingTwo['id'],$listingTwo['version'],true,'Reviewed second public marketplace listing.');

        $public=$service->searchMarketplace(['trade'=>'general_building','serviceArea'=>'Gauteng']);
        self::assertCount(2,$public);
        self::assertArrayNotHasKey('organisationId',$public[0]);
        self::assertArrayNotHasKey('createdBy',$public[0]);

        $documents=new DocumentService($this->pdo);
        $document=$documents->create($buyerOrganisation,$buyer,'owner',['projectId'=>$project,'title'=>'Controlled tender scope','documentType'=>'tender_pack','discipline'=>'general','phase'=>'tender_procurement','issuePurpose'=>'for_tender','revisionCode'=>'T01','notes'=>'Exact scope for procurement test.']);
        $document=$documents->submitForReview($buyerOrganisation,$buyer,'owner',$document['id'],'Exact tender scope ready for review.');
        $document=$documents->approve($buyerOrganisation,$buyer,'owner',$document['id'],true,'Reviewed exact tender scope.');
        $document=$documents->issue($buyerOrganisation,$buyer,'owner',$document['id'],true,'Issued exact tender scope for RFQ.');
        $package=$service->createPackage($buyerOrganisation,$buyer,'owner',$project,[
            'packageNumber'=>'RFQ-001','title'=>'Main contractor RFQ','procurementNeed'=>'Appoint main contractor and structural steel support',
            'procurementType'=>'main_contractor','procurementMethod'=>'invited_tender','methodReason'=>'Project team selected a controlled invited tender.',
            'scopeSummary'=>'Residential alterations with structural and drainage coordination.','estimatedValueZar'=>2_800_000,
            'complexity'=>'high','urgency'=>'normal','province'=>'Gauteng','location'=>'Johannesburg',
            'requiredTrades'=>['general_building','structural_steel'],'requiredSpecialists'=>['structural_engineer'],
            'requiredReturnables'=>['priced_scope','programme','company_profile','tax_clearance','references','exclusions_schedule'],
            'evaluationCriteria'=>['price'=>40,'returnables'=>20,'lead_time'=>15,'risk'=>25],
            'documentRevisionIds'=>[$document['id']],'municipalReadinessScore'=>72,'deadlineAt'=>'2026-09-30T12:00:00Z',
        ]);
        self::assertSame('draft',$package['status']);
        $issued=$service->issuePackage($buyerOrganisation,$buyer,'owner',$package['id'],$package['version'],true,'Reviewed exact scope, documents, returnables, criteria and deadline.');
        self::assertSame('issued',$issued['status']);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/',$issued['snapshotSha256']);
        self::assertFalse($issued['statutoryThresholdClaimed']);

        $matches=$service->matchMarketplace($buyerOrganisation,$buyer,$issued['id']);
        self::assertCount(2,$matches);
        self::assertTrue($matches[0]['advisoryOnly']);
        self::assertContains('verified marketplace evidence',$matches[0]['reasons']);

        $invitation=$service->invite($buyerOrganisation,$buyer,'owner',$issued['id'],$listing['id'],true);
        self::assertSame('in_app_ready',$invitation['deliveryStatus']);
        self::assertFalse($invitation['emailDeliveryClaimed']);
        self::assertMatchesRegularExpression('/\A[A-Za-z0-9_-]{43}\z/',$invitation['accessToken']);
        $invitationTwo=$service->invite($buyerOrganisation,$buyer,'owner',$issued['id'],$listingTwo['id'],true);
        $service->acceptInvitation($invitation['accessToken']);
        $service->acceptInvitation($invitationTwo['accessToken']);
        $clarification=$service->submitClarification($invitation['accessToken'],'Confirm whether drainage upgrades are included.',true);
        self::assertTrue($clarification['material']);
        $addendum=$service->issueAddendum($buyerOrganisation,$buyer,'owner',$issued['id'],[
            'title'=>'Addendum 1 · drainage scope','content'=>'Drainage upgrades shown on the controlled tender drawings are included.',
            'clarificationIds'=>[$clarification['id']],'humanConfirmed'=>true,'reason'=>'Reviewed material response for equal issue to every active bidder.',
        ]);
        self::assertTrue($addendum['equalInformationCompliant']);
        self::assertCount(2,$addendum['distributions']);

        $quote=$service->submitQuote($invitation['accessToken'],[
            'priceZar'=>2_650_000,'leadTimeWeeks'=>22,'providedReturnables'=>$issued['requiredReturnables'],
            'exclusions'=>['municipal fees'],'qualifications'=>['subject to final approved drawings'],'conflictDeclared'=>false,'conflictDetail'=>null,
        ]);
        self::assertSame(1,$quote['revision']);
        $quote=$service->submitQuote($invitation['accessToken'],[
            'priceZar'=>2_620_000,'leadTimeWeeks'=>21,'providedReturnables'=>$issued['requiredReturnables'],
            'exclusions'=>['municipal fees'],'qualifications'=>['subject to final approved drawings'],'conflictDeclared'=>false,'conflictDetail'=>null,
        ]);
        self::assertSame(2,$quote['revision']);
        $returnables=array_values(array_diff($issued['requiredReturnables'],['tax_clearance']));
        $service->submitQuote($invitationTwo['accessToken'],[
            'priceZar'=>2_520_000,'leadTimeWeeks'=>26,'providedReturnables'=>$returnables,
            'exclusions'=>['drainage upgrade'],'qualifications'=>['30 percent deposit requested'],'conflictDeclared'=>true,'conflictDetail'=>'Director disclosed a prior supplier relationship.',
        ]);
        $comparison=$service->compareQuotes($buyerOrganisation,$buyer,$issued['id']);
        self::assertTrue($comparison['advisoryOnly']);
        self::assertFalse($comparison['automaticAward']);
        self::assertCount(2,$comparison['quotes']);
        self::assertContains('tax_clearance',$comparison['quotes'][1]['missingReturnables']);
        self::assertContains('drainage upgrade',$comparison['quotes'][1]['exclusions']);
        self::assertTrue($comparison['quotes'][1]['conflictDeclared']);
        $recommendation=$service->recommendAward($buyerOrganisation,$buyer,'owner',$issued['id'],[
            'comparisonId'=>$comparison['id'],'quoteRevisionId'=>$comparison['quotes'][0]['revisionId'],
            'reason'=>'Top normalized score with complete returnables; all exclusions and qualifications remain subject to human review.',
            'riskNotes'=>['Confirm final programme','Retain municipal fees outside contractor price'],'humanConfirmed'=>true,
        ]);
        self::assertSame('recommended',$recommendation['status']);
        self::assertTrue($recommendation['clientApprovalRequired']);
        self::assertTrue($recommendation['professionalApprovalRequired']);
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM procurement_appointment_placeholders')->fetchColumn());

        $clientApproval=$service->approveAward($buyerOrganisation,$buyer,'owner',$recommendation['id'],[
            'approvalType'=>'client','approverCapacity'=>'client_authorised_representative','decision'=>'approved',
            'reason'=>'Client reviewed the exact recommendation, price, exclusions, qualifications and risk notes.','humanConfirmed'=>true,
        ]);
        self::assertSame('approved',$clientApproval['decision']);
        try{$service->finaliseAward($buyerOrganisation,$buyer,'owner',$recommendation['id'],true,'Attempt before professional approval.');self::fail('Dual approval must be complete before an appointment placeholder.');}catch(ProcurementException$exception){self::assertSame('procurement_award_approvals_incomplete',$exception->reason);}

        $professional=Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'qs@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$professional]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'member','active')")->execute(['id'=>Identifier::generate(),'o'=>$buyerOrganisation,'u'=>$professional]);
        $this->pdo->prepare("INSERT INTO professional_profiles(id,organisation_id,user_id,display_name,profession,bio,service_areas,directory_visible) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'Review QS','quantity_surveyor','Independent procurement review.','[\"Gauteng\"]',0)")->execute(['id'=>Identifier::generate(),'o'=>$buyerOrganisation,'u'=>$professional]);
        $this->pdo->prepare("INSERT INTO verification_records(id,organisation_id,user_id,kind,grade,registration_body,registration_number,popia_consent_at,processing_purpose,retention_until,reviewed_by,reviewed_at) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'professional_registration','manually_reviewed','Test QS body','TEST-QS-1',UTC_TIMESTAMP(6),'Procurement professional authority test',DATE_ADD(UTC_DATE(),INTERVAL 1 YEAR),UNHEX(:reviewer),UTC_TIMESTAMP(6))")->execute(['id'=>Identifier::generate(),'o'=>$buyerOrganisation,'u'=>$professional,'reviewer'=>$reviewer]);
        $this->pdo->prepare("INSERT INTO project_team_members(id,organisation_id,project_id,user_id,discipline,role_label,verification_grade,status,invited_by,accepted_at) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:p),UNHEX(:u),'quantity_surveyor','Procurement review QS','manually_reviewed','active',UNHEX(:buyer),UTC_TIMESTAMP(6))")->execute(['id'=>Identifier::generate(),'o'=>$buyerOrganisation,'p'=>$project,'u'=>$professional,'buyer'=>$buyer]);
        $professionalApproval=$service->approveAward($buyerOrganisation,$professional,'member',$recommendation['id'],[
            'approvalType'=>'professional','approverCapacity'=>'quantity_surveyor','decision'=>'approved',
            'reason'=>'Registered QS reviewed the exact comparison, returnables, exclusions, qualifications, conflicts and recommendation.','humanConfirmed'=>true,
        ]);
        self::assertSame('approved',$professionalApproval['decision']);
        $placeholder=$service->finaliseAward($buyerOrganisation,$buyer,'owner',$recommendation['id'],true,'Both distinct human approvals recorded; create appointment placeholder for formalisation.');
        self::assertSame('appointment_pending_formalisation',$placeholder['status']);
        self::assertFalse($placeholder['signedAppointment']);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/',$placeholder['snapshotSha256']);
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM project_records WHERE record_type='procurement_appointment_placeholder'")->fetchColumn());
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM project_records WHERE record_type='rfq_package'")->fetchColumn());
        self::assertSame(3,(int)$this->pdo->query("SELECT COUNT(*) FROM procurement_quote_revisions")->fetchColumn());
        self::assertGreaterThanOrEqual(15,(int)$this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE action LIKE 'procurement.%'")->fetchColumn());

        $this->expectException(ProcurementException::class);
        $service->getPackage($sellerOrganisation,$seller,$issued['id']);
    }

    /** @return array{string,string,string}|array{string,string} */
    private function tenant(string$name,string$prefix):array
    {
        $organisation=Identifier::generate();$user=Identifier::generate();$project=Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$user,'email'=>$prefix.'@example.test']);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id'=>$organisation,'name'=>$name,'slug'=>$prefix.'-'.substr($organisation,0,8)]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'owner','active')")->execute(['id'=>Identifier::generate(),'o'=>$organisation,'u'=>$user]);
        if($prefix==='buyer'){$this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:p),UNHEX(:o),'BUY-1','Buyer Project','Client','Johannesburg','Erf 1','Residential','','architect','tender_procurement',UNHEX(:u))")->execute(['p'=>$project,'o'=>$organisation,'u'=>$user]);return[$organisation,$user,$project];}
        return[$organisation,$user];
    }
}
