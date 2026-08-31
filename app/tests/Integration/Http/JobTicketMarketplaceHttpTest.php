<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Http;

use Architex\Http\AuthenticationKernelFactory;
use Architex\Http\Request;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Identity\PasswordResetNotifier;
use Architex\Platform\Identity\RegistrationNotifier;
use Architex\Platform\Identity\SessionService;
use Architex\Tests\Support\MariaDbTestCase;

final class JobTicketMarketplaceHttpTest extends MariaDbTestCase
{
    public function testAuthenticatedTicketDiscoveryQuotationExpansionAndSecurityBoundaries(): void
    {
        [$clientOrganisation,$client,$project]=$this->tenant('http-ticket-client',true);
        [$bepOrganisation,$bep]=$this->tenant('http-ticket-bep');
        [$directOrganisation,$directUser]=$this->tenant('http-ticket-direct');
        [$outsiderOrganisation,$outsider]=$this->tenant('http-ticket-outsider');
        $this->verifiedProfessional($bepOrganisation,$bep);
        $this->listing($bepOrganisation,$bep);

        $notifier=new class implements RegistrationNotifier,PasswordResetNotifier{public function isAvailable():bool{return false;}public function send(string $email,string $token,string $expiresAt):void{}};
        $kernel=(new AuthenticationKernelFactory($this->pdo,$notifier,$notifier))->create();
        $sessions=new SessionService($this->pdo);
        $clientHeaders=$this->headers($clientOrganisation,$sessions->rotate($client));
        $bepHeaders=$this->headers($bepOrganisation,$sessions->rotate($bep));
        $directHeaders=$this->headers($directOrganisation,$sessions->rotate($directUser));
        $outsiderHeaders=$this->headers($outsiderOrganisation,$sessions->rotate($outsider));

        $withoutCsrf=$clientHeaders;unset($withoutCsrf['X-CSRF-Token']);
        self::assertSame(419,$kernel->handle($this->json('POST','/api/v1/job-tickets',$withoutCsrf,$this->ticketPayload($project,'public',[])))->status());

        $created=$kernel->handle($this->json('POST','/api/v1/job-tickets',$clientHeaders,$this->ticketPayload($project,'public',[])));
        self::assertSame(201,$created->status(),$created->body());$ticket=$created->json()['data'];
        self::assertFalse($ticket['automaticAward']);
        self::assertSame('architectural_design',$ticket['classification']['primaryCategory']);

        $direct=$kernel->handle($this->json('POST','/api/v1/job-tickets',$clientHeaders,[...$this->ticketPayload($project,'direct',[$directUser]),'title'=>'Direct controlled drawing support']));
        self::assertSame(201,$direct->status(),$direct->body());$directTicket=$direct->json()['data'];
        self::assertSame(404,$kernel->handle(Request::json('GET','/api/v1/job-tickets/'.$directTicket['id'],$outsiderHeaders))->status());
        self::assertSame(200,$kernel->handle(Request::json('GET','/api/v1/job-tickets/'.$directTicket['id'],$directHeaders))->status());

        $discovery=$kernel->handle(Request::json('GET','/api/v1/job-tickets?category=architectural_design&location=Johannesburg',$bepHeaders));
        self::assertSame(200,$discovery->status(),$discovery->body());
        self::assertContains($ticket['id'],array_column($discovery->json()['data']['public'],'id'));
        self::assertContains($ticket['id'],array_column($discovery->json()['data']['recommended'],'id'));
        self::assertNotContains($directTicket['id'],array_column($discovery->json()['data']['direct'],'id'));

        $quoted=$kernel->handle($this->json('POST','/api/v1/job-tickets/'.$ticket['id'].'/quotations',$bepHeaders,[
            'bidderRole'=>'registered_bep','amount'=>125000,'currency'=>'ZAR','scope'=>'Verified architectural service.','exclusions'=>[],
            'qualifications'=>['SACAP verification reviewed'],'deliverables'=>['Working drawings','Municipal submission pack'],
            'timeline'=>'Six weeks','conflictDeclared'=>false,'conflictDetail'=>null,
        ]));
        self::assertSame(201,$quoted->status(),$quoted->body());$quote=$quoted->json()['data'];
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/',$quote['snapshotSha256']);

        $crossTenant=$kernel->handle($this->json('POST','/api/v1/job-tickets/'.$ticket['id'].'/quotations/'.$quote['id'].'/accept',$outsiderHeaders,['expectedVersion'=>$quote['ticketVersion'],'humanConfirmed'=>true,'reason'=>'Cross-tenant attempt.']));
        self::assertContains($crossTenant->status(),[403,404]);
        $stale=$kernel->handle($this->json('POST','/api/v1/job-tickets/'.$ticket['id'].'/quotations/'.$quote['id'].'/accept',$clientHeaders,['expectedVersion'=>1,'humanConfirmed'=>true,'reason'=>'Stale version must fail.']));
        self::assertSame(409,$stale->status(),$stale->body());
        self::assertSame('job_ticket_version_conflict',$stale->json()['error']['code']);

        $accepted=$kernel->handle($this->json('POST','/api/v1/job-tickets/'.$ticket['id'].'/quotations/'.$quote['id'].'/accept',$clientHeaders,['expectedVersion'=>$quote['ticketVersion'],'humanConfirmed'=>true,'reason'=>'Client reviewed the exact quote and selected the verified professional.']));
        self::assertSame(200,$accepted->status(),$accepted->body());$assigned=$accepted->json()['data'];
        self::assertSame($bep,$assigned['holderUserId']);

        $expanded=$kernel->handle($this->json('POST','/api/v1/job-tickets/'.$ticket['id'].'/expansions',$bepHeaders,[
            'expectedVersion'=>$assigned['version'],'roleKey'=>'freelancer_draughtsperson','ticket'=>$this->childPayload($directUser),
        ]));
        self::assertSame(201,$expanded->status(),$expanded->body());
        self::assertSame($ticket['id'],$expanded->json()['data']['ticket']['parentTicketId']);
        $tree=$kernel->handle(Request::json('GET','/api/v1/job-tickets/'.$ticket['id'].'/tree',$clientHeaders));
        self::assertSame(200,$tree->status(),$tree->body());
        self::assertCount(1,$tree->json()['data']['children']);
    }

    public function testSupervisedFreelancerExecutionLifecyclePersistsThroughAuthenticatedRoutes(): void
    {
        [$clientOrganisation,$client,$project]=$this->tenant('http-exec-client',true);
        [$bepOrganisation,$bep]=$this->tenant('http-exec-bep');
        [$freelancerOrganisation,$freelancer]=$this->tenant('http-exec-freelancer');
        $this->verifiedProfessional($bepOrganisation,$bep);$this->professionalProfile($bepOrganisation,$bep,'registered_architect');$this->listing($bepOrganisation,$bep);$this->verifiedProfessional($freelancerOrganisation,$freelancer);
        $notifier=new class implements RegistrationNotifier,PasswordResetNotifier{public function isAvailable():bool{return false;}public function send(string $email,string $token,string $expiresAt):void{}};
        $kernel=(new AuthenticationKernelFactory($this->pdo,$notifier,$notifier))->create();$sessions=new SessionService($this->pdo);
        $clientHeaders=$this->headers($clientOrganisation,$sessions->rotate($client));$bepHeaders=$this->headers($bepOrganisation,$sessions->rotate($bep));$freelancerHeaders=$this->headers($freelancerOrganisation,$sessions->rotate($freelancer));

        $profile=$kernel->handle($this->json('PUT','/api/v1/freelancers/me',$freelancerHeaders,['displayName'=>'HTTP BIM specialist','discipline'=>'draughtsperson_bim_modeller','registrationStatus'=>'specialist','council'=>null,'councilRegistrationNumber'=>null,'councilExpiryDate'=>null,'publicSummary'=>'Supervised Revit production support.','portfolioRefs'=>['HTTP-PORTFOLIO-25'],'serviceAreas'=>['Johannesburg'],'capacityStatus'=>'available','expectedVersion'=>null]));
        self::assertSame(200,$profile->status(),$profile->body());$profileData=$profile->json()['data'];
        $published=$kernel->handle($this->json('POST','/api/v1/freelancers/me/publish',$freelancerHeaders,['expectedVersion'=>$profileData['version'],'humanConfirmed'=>true,'reason'=>'Reviewed current profile and verification evidence.']));
        self::assertSame(200,$published->status(),$published->body());self::assertFalse($published->json()['data']['independentAppointmentPermitted']);

        $rootResponse=$kernel->handle($this->json('POST','/api/v1/job-tickets',$clientHeaders,$this->ticketPayload($project,'public',[])));self::assertSame(201,$rootResponse->status(),$rootResponse->body());$root=$rootResponse->json()['data'];
        $bepQuoteResponse=$kernel->handle($this->json('POST','/api/v1/job-tickets/'.$root['id'].'/quotations',$bepHeaders,['bidderRole'=>'registered_bep','amount'=>125000,'currency'=>'ZAR','scope'=>'Lead architectural service.','exclusions'=>[],'qualifications'=>['Verified Pr Arch'],'deliverables'=>['Working drawings'],'timeline'=>'Six weeks','conflictDeclared'=>false,'conflictDetail'=>null]));self::assertSame(201,$bepQuoteResponse->status(),$bepQuoteResponse->body());$bepQuote=$bepQuoteResponse->json()['data'];
        $rootResponse=$kernel->handle($this->json('POST','/api/v1/job-tickets/'.$root['id'].'/quotations/'.$bepQuote['id'].'/accept',$clientHeaders,['expectedVersion'=>$bepQuote['ticketVersion'],'humanConfirmed'=>true,'reason'=>'Client selected the verified lead professional.']));self::assertSame(200,$rootResponse->status(),$rootResponse->body());$root=$rootResponse->json()['data'];
        $childResponse=$kernel->handle($this->json('POST','/api/v1/job-tickets/'.$root['id'].'/expansions',$bepHeaders,['expectedVersion'=>$root['version'],'roleKey'=>'freelancer_draughtsperson','ticket'=>$this->childPayload($freelancer)]));self::assertSame(201,$childResponse->status(),$childResponse->body());$child=$childResponse->json()['data']['ticket'];
        $freelancerQuoteResponse=$kernel->handle($this->json('POST','/api/v1/job-tickets/'.$child['id'].'/quotations',$freelancerHeaders,['bidderRole'=>'freelancer_specialist','amount'=>16000,'currency'=>'ZAR','scope'=>'Supervised Revit production support.','exclusions'=>[],'qualifications'=>['Registered-professional supervision required.'],'deliverables'=>['Working drawing set'],'timeline'=>'Four days','conflictDeclared'=>false,'conflictDetail'=>null]));self::assertSame(201,$freelancerQuoteResponse->status(),$freelancerQuoteResponse->body());$freelancerQuote=$freelancerQuoteResponse->json()['data'];
        $engagementResponse=$kernel->handle($this->json('POST','/api/v1/job-tickets/'.$child['id'].'/freelancer-quotations/'.$freelancerQuote['id'].'/accept',$bepHeaders,['expectedVersion'=>$freelancerQuote['ticketVersion'],'supervisorOrganisationId'=>$bepOrganisation,'supervisorUserId'=>$bep,'supervisorDiscipline'=>'registered_architect','humanConfirmed'=>true,'reason'=>'Lead architect reviewed and locked the supervised engagement route.']));self::assertSame(201,$engagementResponse->status(),$engagementResponse->body());$engagement=$engagementResponse->json()['data'];self::assertSame('locked',$engagement['supervisionRoute']['status']);

        $timesheetResponse=$kernel->handle($this->json('POST','/api/v1/freelancer-engagements/'.$engagement['id'].'/timesheets',$freelancerHeaders,['weekEnding'=>'2026-09-06','milestones'=>['Floor plans drafted'],'notes'=>'Worked from controlled markups.','entries'=>[['workDate'=>'2026-09-01','hours'=>8,'description'=>'Floor plan production']]]));self::assertSame(201,$timesheetResponse->status(),$timesheetResponse->body());$timesheet=$timesheetResponse->json()['data'];
        $timesheetResponse=$kernel->handle($this->json('POST','/api/v1/freelancer-engagements/'.$engagement['id'].'/timesheets/'.$timesheet['id'].'/review',$bepHeaders,['decision'=>'approved','reason'=>'Checked against controlled drawing work.']));self::assertSame(200,$timesheetResponse->status(),$timesheetResponse->body());
        $claimResponse=$kernel->handle($this->json('POST','/api/v1/freelancer-engagements/'.$engagement['id'].'/claims',$freelancerHeaders,['timesheetIds'=>[$timesheet['id']],'amountClaimed'=>8000,'currency'=>'ZAR','supportingNotes'=>'Approved production time.']));self::assertSame(201,$claimResponse->status(),$claimResponse->body());$claim=$claimResponse->json()['data'];
        $claimResponse=$kernel->handle($this->json('POST','/api/v1/freelancer-engagements/'.$engagement['id'].'/claims/'.$claim['id'].'/review',$bepHeaders,['decision'=>'approved','approvedAmount'=>8000,'reason'=>'Approved against the reviewed timesheet.']));self::assertSame(200,$claimResponse->status(),$claimResponse->body());
        $paymentResponse=$kernel->handle($this->json('POST','/api/v1/freelancer-engagements/'.$engagement['id'].'/claims/'.$claim['id'].'/payment-evidence',$bepHeaders,['reference'=>'HTTP-EFT-25','paidAt'=>'2026-09-10','note'=>'Payer remittance evidence recorded.']));self::assertSame(200,$paymentResponse->status(),$paymentResponse->body());self::assertSame('recorded_evidence_not_bank_confirmation',$paymentResponse->json()['data']['verificationBoundary']);self::assertFalse($paymentResponse->json()['data']['paymentExecutionClaimed']);

        $deliverableResponse=$kernel->handle($this->json('POST','/api/v1/freelancer-engagements/'.$engagement['id'].'/deliverables',$freelancerHeaders,['name'=>'Working drawing set','description'=>'Controlled production issue.','files'=>[['storedFileId'=>null,'name'=>'A-101.pdf','sha256'=>str_repeat('a',64)]],'submissionNote'=>'Revision 1 for review.']));self::assertSame(201,$deliverableResponse->status(),$deliverableResponse->body());$deliverable=$deliverableResponse->json()['data'];
        $deliverableResponse=$kernel->handle($this->json('POST','/api/v1/freelancer-engagements/'.$engagement['id'].'/deliverables/'.$deliverable['id'].'/revisions',$freelancerHeaders,['expectedVersion'=>$deliverable['version'],'files'=>[['storedFileId'=>null,'name'=>'A-101-r2.pdf','sha256'=>str_repeat('b',64)]],'submissionNote'=>'Revision 2 addresses markups.']));self::assertSame(201,$deliverableResponse->status(),$deliverableResponse->body());$deliverable=$deliverableResponse->json()['data'];
        $signoffResponse=$kernel->handle($this->json('POST','/api/v1/freelancer-engagements/'.$engagement['id'].'/deliverables/'.$deliverable['id'].'/revisions/'.$deliverable['currentRevision']['id'].'/sign-off',$bepHeaders,['decision'=>'approved','declaration'=>'I reviewed this exact revision under the locked route.']));self::assertSame(200,$signoffResponse->status(),$signoffResponse->body());
        $issueResponse=$kernel->handle($this->json('POST','/api/v1/freelancer-engagements/'.$engagement['id'].'/deliverables/'.$deliverable['id'].'/issue',$bepHeaders,['humanConfirmed'=>true,'reason'=>'Lead architect approved external issue of the signed revision.']));self::assertSame(200,$issueResponse->status(),$issueResponse->body());self::assertSame('externally_issued',$issueResponse->json()['data']['status']);
        $completeResponse=$kernel->handle($this->json('POST','/api/v1/freelancer-engagements/'.$engagement['id'].'/complete',$bepHeaders,['expectedVersion'=>$engagement['version'],'humanAccepted'=>true,'reason'=>'Lead architect accepted the completed supervised scope.']));self::assertSame(200,$completeResponse->status(),$completeResponse->body());self::assertSame('completed',$completeResponse->json()['data']['status']);
        $reviewResponse=$kernel->handle($this->json('POST','/api/v1/freelancer-engagements/'.$engagement['id'].'/reviews',$bepHeaders,['rating'=>5,'reviewText'=>'Reliable supervised production with controlled revisions.']));self::assertSame(201,$reviewResponse->status(),$reviewResponse->body());self::assertTrue($reviewResponse->json()['data']['completedEngagementVerified']);
        $read=$kernel->handle(Request::json('GET','/api/v1/freelancer-engagements/'.$engagement['id'],$freelancerHeaders));self::assertSame(200,$read->status(),$read->body());self::assertSame('completed',$read->json()['data']['status']);
    }

    /** @return array{string,string,?string} */
    private function tenant(string $slug,bool $withProject=false): array
    {
        $organisation=Identifier::generate();$user=Identifier::generate();$project=null;
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$user,'email'=>$slug.'@example.test']);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id'=>$organisation,'name'=>$slug,'slug'=>$slug]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'owner','active')")->execute(['id'=>Identifier::generate(),'o'=>$organisation,'u'=>$user]);
        if($withProject){$project=Identifier::generate();$this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:id),UNHEX(:o),'HTTP-JT25','HTTP Job Ticket','Client','Johannesburg','Erf 25','Residential','','architect','tender_procurement',UNHEX(:u))")->execute(['id'=>$project,'o'=>$organisation,'u'=>$user]);}
        return[$organisation,$user,$project];
    }

    private function verifiedProfessional(string $organisation,string $user): void
    {
        $reviewer=Identifier::generate();$this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$reviewer,'email'=>$reviewer.'@review.test']);
        $this->pdo->prepare("INSERT INTO verification_records(id,organisation_id,user_id,kind,grade,registration_body,registration_number,popia_consent_at,processing_purpose,retention_until,reviewed_by,reviewed_at) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'professional_registration','manually_reviewed','SACAP','HTTP-PR-25',UTC_TIMESTAMP(6),'HTTP role evidence',DATE_ADD(UTC_DATE(),INTERVAL 1 YEAR),UNHEX(:reviewer),UTC_TIMESTAMP(6))")->execute(['id'=>Identifier::generate(),'o'=>$organisation,'u'=>$user,'reviewer'=>$reviewer]);
    }

    private function listing(string $organisation,string $user): void
    {
        $this->pdo->prepare("INSERT INTO marketplace_listings(id,organisation_id,created_by,listing_type,name,public_summary,disciplines_trades,service_areas,capacity_status,verification_status,supervision_required,status,published_at) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'specialist_consultant','HTTP architect','Verified capacity.',JSON_ARRAY('architectural_design'),JSON_ARRAY('Johannesburg'),'available','verified',0,'published',UTC_TIMESTAMP(6))")->execute(['id'=>Identifier::generate(),'o'=>$organisation,'u'=>$user]);
    }

    private function professionalProfile(string $organisation,string $user,string $profession): void
    {
        $this->pdo->prepare("INSERT INTO professional_profiles(id,organisation_id,user_id,display_name,profession,bio,service_areas,directory_visible) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'HTTP supervisor',:profession,'Verified supervision profile.',JSON_ARRAY('Johannesburg'),1)")->execute(['id'=>Identifier::generate(),'o'=>$organisation,'u'=>$user,'profession'=>$profession]);
    }

    /** @param list<string> $invitees @return array<string,mixed> */
    private function ticketPayload(string $project,string $invitation,array $invitees): array { return ['projectId'=>$project,'posterType'=>'client','title'=>'House alterations architect required','description'=>'Prepare architectural working drawings and coordinate a municipal submission pack.','deliverables'=>['Working drawings','Municipal submission pack'],'budgetMin'=>50000,'budgetMax'=>150000,'currency'=>'ZAR','deadline'=>'2026-12-15','location'=>'Johannesburg','phase'=>'design','requiredRegistrations'=>['SACAP'],'invitationType'=>$invitation,'invitedUserIds'=>$invitees,'platformFeePercent'=>5.0,'platformFeeDisclosure'=>'Architex platform fee: 5% of an accepted quotation; payment processing is not claimed.']; }
    /** @return array<string,mixed> */
    private function childPayload(string $invitee): array { return ['posterType'=>'registered_bep','title'=>'Production drawing and BIM support','description'=>'Prepare controlled Revit working drawings under the lead architect.','deliverables'=>['Working drawings','Drawing register'],'budgetMin'=>8000,'budgetMax'=>20000,'currency'=>'ZAR','deadline'=>'2026-11-30','location'=>'Johannesburg','phase'=>'design','requiredRegistrations'=>[],'invitationType'=>'direct','invitedUserIds'=>[$invitee],'platformFeePercent'=>5.0,'platformFeeDisclosure'=>'Architex platform fee: 5% of an accepted quotation; payment processing is not claimed.']; }
    /** @return array<string,string> */ private function headers(string $organisation,object $session):array{return['Cookie'=>'architex_session='.$session->token,'X-Organisation-Id'=>$organisation,'X-CSRF-Token'=>$session->csrfToken];}
    /** @param array<string,string> $headers @param array<string,mixed> $body */ private function json(string $method,string $path,array $headers,array $body):Request{return Request::json($method,$path,$headers+['Content-Type'=>'application/json'],json_encode((object)$body,JSON_THROW_ON_ERROR));}
}
