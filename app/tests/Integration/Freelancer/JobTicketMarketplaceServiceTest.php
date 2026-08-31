<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Freelancer;

use Architex\Platform\Freelancer\JobTicketMarketplaceException;
use Architex\Platform\Freelancer\JobTicketMarketplacePolicy;
use Architex\Platform\Freelancer\JobTicketMarketplaceService;
use Architex\Platform\Identity\Identifier;
use Architex\Tests\Support\MariaDbTestCase;

final class JobTicketMarketplaceServiceTest extends MariaDbTestCase
{
    public function testPublicAndDirectDiscoveryQuotationAcceptanceAndHolderExpansion():void
    {
        [$clientOrg,$client,$project]=$this->tenant('ticket-client',true);
        [$bepOrg,$bep]=$this->tenant('ticket-bep');
        [$freelancerOrg,$freelancer]=$this->tenant('ticket-freelancer');
        [$outsiderOrg,$outsider]=$this->tenant('ticket-outsider');
        $this->verifiedProfessional($bepOrg,$bep,'SACAP','PrArch TEST-25');
        $this->listing($bepOrg,$bep,'specialist_consultant',['architectural_design'],['Johannesburg']);
        $service=new JobTicketMarketplaceService($this->pdo,new JobTicketMarketplacePolicy());

        $public=$service->createTicket($clientOrg,$client,'owner',$project,$this->ticketPayload('public',[]));
        self::assertSame('open',$public['status']);
        self::assertSame('architectural_design',$public['classification']['primaryCategory']);
        self::assertFalse($public['automaticAward']);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/',$public['classificationSha256']);

        $direct=$service->createTicket($clientOrg,$client,'owner',$project,[...$this->ticketPayload('direct',[$freelancer]),'title'=>'Direct production drawing support','description'=>'Revit production drawing and BIM support under supervision.','deliverables'=>['Working drawings','Drawing register'],'requiredRegistrations'=>[]]);
        $bepDiscovery=$service->discover($bepOrg,$bep,'owner',[]);
        self::assertContains($public['id'],array_column($bepDiscovery['public'],'id'));
        self::assertNotContains($direct['id'],array_column($bepDiscovery['direct'],'id'));
        self::assertContains($public['id'],array_column($bepDiscovery['recommended'],'id'));
        $freelancerDiscovery=$service->discover($freelancerOrg,$freelancer,'owner',[]);
        self::assertContains($direct['id'],array_column($freelancerDiscovery['direct'],'id'));
        $outsiderDiscovery=$service->discover($outsiderOrg,$outsider,'owner',[]);
        self::assertNotContains($direct['id'],array_column($outsiderDiscovery['direct'],'id'));

        $quote=$service->submitQuotation($bepOrg,$bep,'owner','registered_bep',$public['id'],[
            'amount'=>125000,'currency'=>'ZAR','scope'=>'Full architectural service with controlled deliverables.',
            'exclusions'=>['Structural engineering'],'qualifications'=>['SACAP professional registration verified'],
            'deliverables'=>['Working drawings','Municipal submission pack'],'timeline'=>'Six weeks from confirmed appointment','conflictDeclared'=>false,'conflictDetail'=>null,
        ]);
        self::assertSame('submitted',$quote['status']);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/',$quote['snapshotSha256']);
        self::assertSame($quote['id'],$service->listQuotations($clientOrg,$client,'owner',$public['id'])[0]['id']);

        $accepted=$service->acceptQuotation($clientOrg,$client,'owner',$public['id'],$quote['id'],$quote['ticketVersion'],true,'Client reviewed the quotation, exclusions and platform fee disclosure.');
        self::assertSame('assigned',$accepted['status']);
        self::assertSame($bep,$accepted['holderUserId']);
        self::assertSame($bepOrg,$accepted['holderOrganisationId']);

        try{$service->expandTicket($outsiderOrg,$outsider,'owner',$public['id'],$accepted['version'],'consultant',$this->childPayload('public',[]));self::fail('Only the current holder may expand.');}catch(JobTicketMarketplaceException$e){self::assertSame('job_ticket_holder_required',$e->reason);}
        $child=$service->expandTicket($bepOrg,$bep,'owner',$public['id'],$accepted['version'],'freelancer_draughtsperson',$this->childPayload('direct',[$freelancer]));
        self::assertSame($public['id'],$child['ticket']['parentTicketId']);
        self::assertSame('production_drawings',$child['ticket']['classification']['primaryCategory']);
        self::assertSame('direct',$child['ticket']['invitationType']);

        $tree=$service->ticketTree($clientOrg,$client,'owner',$public['id']);
        self::assertSame($public['id'],$tree['ticket']['id']);
        self::assertSame($child['ticket']['id'],$tree['children'][0]['ticket']['id']);
        self::assertSame('freelancer_draughtsperson',$tree['children'][0]['role']);

        $clarification=$service->addClarification($clientOrg,$client,'owner',$public['id'],['title'=>'Scope clarification','content'=>'Municipal fees remain excluded from every quotation.','material'=>true]);
        self::assertTrue($clarification['equalInformation']);
        self::assertSame('public_ticket_audience',$clarification['visibility']);
        $overridden=$service->overrideClassification($clientOrg,$client,'owner',$public['id'],[
            'expectedVersion'=>$child['parentVersion'],'primaryCategory'=>'architectural_design','subCategory'=>'residential_alterations',
            'requiredCompetence'=>'registered_professional','requiredRegistrations'=>['SACAP'],'suggestedSupervisorDiscipline'=>null,
            'reason'=>'The client and lead architect confirmed the root appointment scope after human review.',
        ]);
        self::assertSame('human_override_after_advisory',$overridden['classification']['classifiedBy']);
        self::assertSame(2,$overridden['classificationRevision']);

        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM project_records WHERE record_type='job_ticket_assignment'")->fetchColumn());
        self::assertGreaterThanOrEqual(4,(int)$this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE action LIKE 'job_ticket.%'")->fetchColumn());
        self::assertGreaterThanOrEqual(2,(int)$this->pdo->query("SELECT COUNT(*) FROM inbox_items WHERE event_type LIKE 'job_ticket_%'")->fetchColumn());

        $withdrawTicket=$service->createTicket($clientOrg,$client,'owner',$project,[...$this->ticketPayload('public',[]),'title'=>'Withdrawable architect opportunity']);
        $withdrawQuote=$service->submitQuotation($bepOrg,$bep,'owner','registered_bep',$withdrawTicket['id'],[
            'amount'=>95000,'currency'=>'ZAR','scope'=>'Architectural service offered for withdrawal test.','exclusions'=>[],
            'qualifications'=>['SACAP professional registration verified'],'deliverables'=>['Working drawings'],
            'timeline'=>'Five weeks','conflictDeclared'=>false,'conflictDetail'=>null,
        ]);
        $withdrawn=$service->withdrawQuotation($bepOrg,$bep,'owner',$withdrawTicket['id'],$withdrawQuote['id'],'Bidder capacity changed before selection.');
        self::assertSame('withdrawn',$withdrawn['status']);
        self::assertSame([],$service->listQuotations($clientOrg,$client,'owner',$withdrawTicket['id'],false));

        try{$service->acceptQuotation($outsiderOrg,$outsider,'owner',$public['id'],$quote['id'],$overridden['version'],true,'Attempt cross tenant.');self::fail('Cross-tenant acceptance must fail.');}catch(JobTicketMarketplaceException$e){self::assertContains($e->reason,['job_ticket_not_found','job_ticket_poster_required']);}
    }

    public function testFreelancerSupervisionExecutionPaymentEvidenceCloseoutAndReview():void
    {
        [$clientOrg,$client,$project]=$this->tenant('execution-client',true);
        [$bepOrg,$bep]=$this->tenant('execution-bep');
        [$freelancerOrg,$freelancer]=$this->tenant('execution-freelancer');
        [$outsiderOrg,$outsider]=$this->tenant('execution-outsider');
        $this->verifiedProfessional($bepOrg,$bep,'SACAP','PrArch EXEC-25');
        $this->verifiedProfessional($freelancerOrg,$freelancer,'Portfolio review','SPEC EXEC-25');
        $this->professionalProfile($bepOrg,$bep,'registered_architect');
        $this->listing($bepOrg,$bep,'specialist_consultant',['architectural_design'],['Johannesburg']);
        $service=new JobTicketMarketplaceService($this->pdo,new JobTicketMarketplacePolicy());

        $profile=$service->upsertFreelancerProfile($freelancerOrg,$freelancer,'owner',[
            'displayName'=>'Release 25 BIM specialist','discipline'=>'draughtsperson_bim_modeller','registrationStatus'=>'specialist',
            'council'=>null,'councilRegistrationNumber'=>null,'councilExpiryDate'=>null,
            'publicSummary'=>'Supervised Revit and CAD production support.','portfolioRefs'=>['portfolio-exec-25'],
            'serviceAreas'=>['Johannesburg'],'capacityStatus'=>'available','expectedVersion'=>null,
        ]);
        self::assertSame('draft',$profile['status']);
        $profile=$service->publishFreelancerProfile($freelancerOrg,$freelancer,'owner',$profile['version'],true,'Reviewed the public-safe profile and current trust evidence.');
        self::assertSame('published',$profile['status']);
        self::assertTrue($profile['identityVerified']);
        self::assertTrue($profile['supervisorRequired']);
        self::assertFalse($profile['independentAppointmentPermitted']);

        $root=$service->createTicket($clientOrg,$client,'owner',$project,$this->ticketPayload('public',[]));
        $bepQuote=$service->submitQuotation($bepOrg,$bep,'owner','registered_bep',$root['id'],['amount'=>125000,'currency'=>'ZAR','scope'=>'Lead architectural service.','exclusions'=>[],'qualifications'=>['Verified Pr Arch'],'deliverables'=>['Working drawings'],'timeline'=>'Six weeks','conflictDeclared'=>false,'conflictDetail'=>null]);
        $root=$service->acceptQuotation($clientOrg,$client,'owner',$root['id'],$bepQuote['id'],$bepQuote['ticketVersion'],true,'Client selected the verified lead professional.');
        $child=$service->expandTicket($bepOrg,$bep,'owner',$root['id'],$root['version'],'freelancer_draughtsperson',$this->childPayload('direct',[$freelancer]));
        $freelancerQuote=$service->submitQuotation($freelancerOrg,$freelancer,'owner','freelancer_specialist',$child['ticket']['id'],['amount'=>16000,'currency'=>'ZAR','scope'=>'Supervised Revit production support.','exclusions'=>[],'qualifications'=>['All output remains under registered-professional supervision.'],'deliverables'=>['Working drawing set'],'timeline'=>'Four working days','conflictDeclared'=>false,'conflictDetail'=>null]);

        try{$service->acceptFreelancerQuotation($bepOrg,$bep,'owner',$child['ticket']['id'],$freelancerQuote['id'],$freelancerQuote['ticketVersion'],$freelancerOrg,$freelancer,'registered_architect',true,'Invalid self-supervision attempt.');self::fail('A freelancer cannot supervise their own engagement.');}catch(JobTicketMarketplaceException$e){self::assertSame('freelancer_self_supervision_prohibited',$e->reason);}
        $engagement=$service->acceptFreelancerQuotation($bepOrg,$bep,'owner',$child['ticket']['id'],$freelancerQuote['id'],$freelancerQuote['ticketVersion'],$bepOrg,$bep,'registered_architect',true,'Lead architect reviewed the supervised scope, quote and route.');
        self::assertSame('active',$engagement['status']);
        self::assertSame('locked',$engagement['supervisionRoute']['status']);
        self::assertSame($bep,$engagement['supervisionRoute']['supervisorUserId']);
        try{$service->reassignSupervisor($bepOrg,$bep,'owner',$engagement['id'],$outsiderOrg,$outsider,'registered_architect');self::fail('A locked route must be immutable.');}catch(JobTicketMarketplaceException$e){self::assertSame('freelancer_supervision_route_locked',$e->reason);}

        $timesheet=$service->submitTimesheet($freelancerOrg,$freelancer,'owner',$engagement['id'],[
            'weekEnding'=>'2026-09-06','milestones'=>['Floor plans drafted','Elevations coordinated'],'notes'=>'Worked from lead architect markups.',
            'entries'=>[['workDate'=>'2026-09-01','hours'=>8,'description'=>'Floor plan production'],['workDate'=>'2026-09-02','hours'=>8,'description'=>'Elevation production']],
        ]);
        self::assertSame(16.0,$timesheet['totalHours']);
        $timesheet=$service->reviewTimesheet($bepOrg,$bep,'owner',$engagement['id'],$timesheet['id'],'approved','Supervisor checked entries against the controlled drawing work.');
        self::assertSame('approved',$timesheet['status']);

        $claim=$service->submitClaim($freelancerOrg,$freelancer,'owner',$engagement['id'],['timesheetIds'=>[$timesheet['id']],'amountClaimed'=>16000,'currency'=>'ZAR','supportingNotes'=>'Two approved production days.']);
        $claim=$service->reviewClaim($bepOrg,$bep,'owner',$engagement['id'],$claim['id'],'approved',16000,'Approved against the signed-off timesheet.');
        $claim=$service->recordClaimPayment($bepOrg,$bep,'owner',$engagement['id'],$claim['id'],['reference'=>'EFT-EXEC-25','paidAt'=>'2026-09-10','note'=>'Payment evidence recorded from the payer remittance.']);
        self::assertSame('recorded_paid',$claim['status']);
        self::assertSame('recorded_evidence_not_bank_confirmation',$claim['verificationBoundary']);
        self::assertFalse($claim['paymentExecutionClaimed']);

        $deliverable=$service->submitDeliverable($freelancerOrg,$freelancer,'owner',$engagement['id'],['name'=>'Working drawing set','description'=>'Controlled production drawing issue.','files'=>[['storedFileId'=>null,'name'=>'A-101.pdf','sha256'=>str_repeat('a',64)],['storedFileId'=>null,'name'=>'A-201.pdf','sha256'=>str_repeat('b',64)]],'submissionNote'=>'Revision 1 for supervisor review.']);
        $firstRevision=$deliverable['currentRevision'];
        $deliverable=$service->reviseDeliverable($freelancerOrg,$freelancer,'owner',$engagement['id'],$deliverable['id'],$deliverable['version'],['files'=>[['storedFileId'=>null,'name'=>'A-101-r2.pdf','sha256'=>str_repeat('c',64)],['storedFileId'=>null,'name'=>'A-201-r2.pdf','sha256'=>str_repeat('d',64)]],'submissionNote'=>'Revision 2 addresses coordinated markups.']);
        self::assertSame(2,$deliverable['currentRevision']['revision']);
        self::assertNotSame($firstRevision['fileManifestSha256'],$deliverable['currentRevision']['fileManifestSha256']);
        self::assertSame(2,(int)$this->pdo->query("SELECT COUNT(*) FROM freelancer_deliverable_revisions")->fetchColumn());
        try{$service->signOffDeliverable($bepOrg,$bep,'owner',$engagement['id'],$deliverable['id'],$firstRevision['id'],'approved','Attempt to sign superseded revision.');self::fail('Only the exact current revision can be signed.');}catch(JobTicketMarketplaceException$e){self::assertSame('freelancer_deliverable_revision_not_current',$e->reason);}
        try{$service->issueDeliverable($bepOrg,$bep,'owner',$engagement['id'],$deliverable['id'],true,'Attempt before supervisor sign-off.');self::fail('Unsigned deliverables cannot be externally issued.');}catch(JobTicketMarketplaceException$e){self::assertSame('current_revision_supervisor_signoff_required',$e->reason);}
        $deliverable=$service->signOffDeliverable($bepOrg,$bep,'owner',$engagement['id'],$deliverable['id'],$deliverable['currentRevision']['id'],'approved','I reviewed this exact revision under the locked supervision route.');
        self::assertTrue($deliverable['externalIssueReady']);
        $deliverable=$service->issueDeliverable($bepOrg,$bep,'owner',$engagement['id'],$deliverable['id'],true,'Lead architect approved external issue of the exact signed revision.');
        self::assertSame('externally_issued',$deliverable['status']);

        $engagement=$service->completeEngagement($bepOrg,$bep,'owner',$engagement['id'],$engagement['version'],true,'Lead architect accepted the completed supervised scope.');
        self::assertSame('completed',$engagement['status']);
        $review=$service->createPerformanceReview($bepOrg,$bep,'owner',$engagement['id'],5,'Reliable supervised production work with clear revision control.');
        self::assertSame(5,$review['rating']);
        try{$service->createPerformanceReview($freelancerOrg,$freelancer,'owner',$engagement['id'],5,'Self review.');self::fail('Self reviews are prohibited.');}catch(JobTicketMarketplaceException$e){self::assertSame('freelancer_review_authority_required',$e->reason);}
        try{$service->getEngagement($outsiderOrg,$outsider,'owner',$engagement['id']);self::fail('Unrelated tenants cannot read an engagement.');}catch(JobTicketMarketplaceException$e){self::assertSame('freelancer_engagement_not_found',$e->reason);}

        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM freelancer_claims WHERE status='recorded_paid' AND payment_execution_claimed=0")->fetchColumn());
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM freelancer_deliverables WHERE status='externally_issued' AND external_issue_ready=1")->fetchColumn());
        self::assertGreaterThanOrEqual(8,(int)$this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE action LIKE 'freelancer.%'")->fetchColumn());
    }

    /** @return array{string,string,?string} */
    private function tenant(string$slug,bool$project=false):array
    {
        $organisation=Identifier::generate();$user=Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$user,'email'=>$slug.'@example.test']);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id'=>$organisation,'name'=>$slug,'slug'=>$slug]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'owner','active')")->execute(['id'=>Identifier::generate(),'o'=>$organisation,'u'=>$user]);
        $projectId=null;if($project){$projectId=Identifier::generate();$this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:id),UNHEX(:o),'JT-25','Job Ticket Project','Ticket Client','Johannesburg','Erf JT25','Residential','Marketplace test','architect','tender_procurement',UNHEX(:u))")->execute(['id'=>$projectId,'o'=>$organisation,'u'=>$user]);}
        return[$organisation,$user,$projectId];
    }

    private function verifiedProfessional(string$organisation,string$user,string$council,string$number):void
    {
        $reviewer=Identifier::generate();$this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$reviewer,'email'=>$number.'@review.test']);
        $this->pdo->prepare("INSERT INTO verification_records(id,organisation_id,user_id,kind,grade,registration_body,registration_number,popia_consent_at,processing_purpose,retention_until,reviewed_by,reviewed_at) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'professional_registration','manually_reviewed',:council,:number,UTC_TIMESTAMP(6),'Job ticket role evidence',DATE_ADD(UTC_DATE(),INTERVAL 1 YEAR),UNHEX(:reviewer),UTC_TIMESTAMP(6))")->execute(['id'=>Identifier::generate(),'o'=>$organisation,'u'=>$user,'council'=>$council,'number'=>$number,'reviewer'=>$reviewer]);
    }

    private function professionalProfile(string$organisation,string$user,string$profession):void
    {
        $this->pdo->prepare("INSERT INTO professional_profiles(id,organisation_id,user_id,display_name,profession,bio,service_areas,directory_visible) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'Release 25 supervisor',:profession,'Verified supervision test profile.',JSON_ARRAY('Johannesburg'),1)")->execute(['id'=>Identifier::generate(),'o'=>$organisation,'u'=>$user,'profession'=>$profession]);
    }

    /** @param list<string> $disciplines @param list<string> $areas */
    private function listing(string$organisation,string$user,string$type,array$disciplines,array$areas):void
    {
        $this->pdo->prepare("INSERT INTO marketplace_listings(id,organisation_id,created_by,listing_type,name,public_summary,disciplines_trades,service_areas,capacity_status,verification_status,supervision_required,status,published_at) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),:type,'Release 25 professional','Public verified capacity only.',:disciplines,:areas,'available','verified',0,'published',UTC_TIMESTAMP(6))")->execute(['id'=>Identifier::generate(),'o'=>$organisation,'u'=>$user,'type'=>$type,'disciplines'=>json_encode($disciplines,JSON_THROW_ON_ERROR),'areas'=>json_encode($areas,JSON_THROW_ON_ERROR)]);
    }

    /** @param list<string> $invitees @return array<string,mixed> */
    private function ticketPayload(string$type,array$invitees):array{return['posterType'=>'client','title'=>'House alterations - architect required','description'=>'Prepare architectural working drawings and coordinate a municipal submission pack.','deliverables'=>['Working drawings','Municipal submission pack'],'budgetMin'=>50000,'budgetMax'=>150000,'currency'=>'ZAR','deadline'=>'2026-12-15','location'=>'Johannesburg','phase'=>'design','requiredRegistrations'=>['SACAP'],'invitationType'=>$type,'invitedUserIds'=>$invitees,'platformFeePercent'=>5.0,'platformFeeDisclosure'=>'Architex platform fee: 5% of an accepted quotation; payment processing is not claimed.'];}
    /** @param list<string> $invitees @return array<string,mixed> */
    private function childPayload(string$type,array$invitees):array{return['posterType'=>'registered_bep','title'=>'Production drawing and BIM support','description'=>'Prepare controlled Revit and CAD working drawings under the lead architect.','deliverables'=>['Working drawings','Drawing register'],'budgetMin'=>8000,'budgetMax'=>20000,'currency'=>'ZAR','deadline'=>'2026-11-30','location'=>'Johannesburg','phase'=>'design','requiredRegistrations'=>[],'invitationType'=>$type,'invitedUserIds'=>$invitees,'platformFeePercent'=>5.0,'platformFeeDisclosure'=>'Architex platform fee: 5% of an accepted quotation; payment processing is not claimed.'];}
}
