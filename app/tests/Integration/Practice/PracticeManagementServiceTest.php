<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Practice;

use Architex\Platform\Identity\Identifier;
use Architex\Platform\Practice\PracticeException;
use Architex\Platform\Practice\PracticeManagementService;
use Architex\Tests\Support\MariaDbTestCase;

final class PracticeManagementServiceTest extends MariaDbTestCase
{
    public function testConnectedOfficeOpsScenarioPreservesProfessionalAndCommercialBoundaries(): void
    {
        [$organisation,$owner,$project]=$this->tenant('Practice','practice');
        $candidate=$this->member($organisation,'candidate@example.test');
        $service=new PracticeManagementService($this->pdo);

        $pipeline=$service->createPipelineItem($organisation,$owner,$project,['title'=>'Civic library lead','stage'=>'qualified','probabilityPercent'=>60,'forecastValueCents'=>15000000,'humanConfirmed'=>true]);
        self::assertSame('qualified',$pipeline['stage']);
        self::assertSame(9000000,$pipeline['weightedValueCents']);
        self::assertSame('won',$service->transitionPipelineItem($organisation,$owner,$pipeline['id'],['stage'=>'won','reason'=>'Appointment accepted.','humanConfirmed'=>true])['stage']);

        $task=$service->createTask($organisation,$owner,$project,['title'=>'Issue coordinated drawings','assignedUserId'=>$candidate,'dueAt'=>'2026-09-02T12:00:00Z','slaHours'=>24,'priority'=>'high']);
        self::assertSame('open',$task['status']);
        $otherMember=$this->member($organisation,'other-member@example.test');
        try{$service->transitionTask($organisation,$otherMember,'member',$task['id'],['status'=>'in_progress','reason'=>'Not my assigned task.','humanConfirmed'=>true]);self::fail('An unrelated member must not transition another member task.');}catch(PracticeException$e){self::assertSame('practice_task_transition_forbidden',$e->reason);}
        self::assertSame('in_progress',$service->transitionTask($organisation,$candidate,'member',$task['id'],['status'=>'in_progress','reason'=>'Assigned work started.','humanConfirmed'=>true])['status']);
        self::assertSame('completed',$service->transitionTask($organisation,$candidate,'member',$task['id'],['status'=>'completed','reason'=>'Deliverable issued for review.','humanConfirmed'=>true])['status']);
        $allocation=$service->allocateResource($organisation,$owner,$project,['userId'=>$candidate,'startsOn'=>'2026-09-01','endsOn'=>'2026-09-05','allocatedHours'=>48,'capacityHours'=>40,'humanConfirmed'=>true]);
        self::assertTrue($allocation['overAllocated']);

        $time=$service->captureTimesheet($organisation,$candidate,$project,['workstage'=>'design_development','workDate'=>'2026-08-29','minutes'=>450,'billable'=>true,'description'=>'Coordinated drawing package.','taskId'=>$task['id']]);
        self::assertSame('submitted',$service->submitTimesheet($organisation,$candidate,$time['id'],['humanConfirmed'=>true])['status']);
        self::assertSame('approved',$service->reviewTimesheet($organisation,$owner,'owner',$time['id'],['decision'=>'approved','reason'=>'Reviewed against task evidence.','humanConfirmed'=>true])['status']);

        $review=$service->createSupervisionReview($organisation,$candidate,$project,['candidateUserId'=>$candidate,'mentorUserId'=>$owner,'periodStart'=>'2026-08-01','periodEnd'=>'2026-08-31','summary'=>'Design coordination learning record.','competencies'=>['documentation','coordination'],'humanConfirmed'=>true]);
        try{$service->signOffSupervision($organisation,$candidate,'member',$review['id'],['reason'=>'Self sign-off.','humanConfirmed'=>true]);self::fail('A candidate must not sign off their own record.');}catch(PracticeException$e){self::assertSame('practice_registered_professional_required',$e->reason);}
        self::assertSame('signed_off',$service->signOffSupervision($organisation,$owner,'owner',$review['id'],['reason'=>'Mentor reviewed the evidence.','humanConfirmed'=>true])['status']);

        $readiness=$service->assessInvoiceReadiness($organisation,$owner,$project,['expensesReady'=>true,'disbursementsReady'=>true,'humanConfirmed'=>true]);
        self::assertSame('ready_for_review',$readiness['status']);
        self::assertFalse($readiness['createsInvoice']);
        self::assertFalse($readiness['movesMoney']);

        try{$service->recordRegistrationRenewal($organisation,$otherMember,'member',['userId'=>$candidate,'professionalBody'=>'SACAP','registrationNumber'=>'CAND-001','expiresOn'=>'2026-09-20','requiredCpdPoints'=>25,'recordedCpdPoints'=>18,'piExpiresOn'=>'2026-09-15','humanConfirmed'=>true]);self::fail('A member must not record another user registration reminder.');}catch(PracticeException$e){self::assertSame('practice_registration_forbidden',$e->reason);}
        $registration=$service->recordRegistrationRenewal($organisation,$owner,'owner',['userId'=>$candidate,'professionalBody'=>'SACAP','registrationNumber'=>'CAND-001','expiresOn'=>'2026-09-20','requiredCpdPoints'=>25,'recordedCpdPoints'=>18,'piExpiresOn'=>'2026-09-15','humanConfirmed'=>true]);
        self::assertSame('cpd_shortfall',$registration['status']);
        self::assertFalse($registration['verificationGuaranteed']);

        try{$service->createTemplate($organisation,$candidate,'member',['name'=>'Uncontrolled template','category'=>'appointment','allowedRoles'=>['member'],'content'=>'Member-authored controlled content.','humanConfirmed'=>true]);self::fail('A member must not mutate the controlled template library.');}catch(PracticeException$e){self::assertSame('practice_template_management_forbidden',$e->reason);}
        $template=$service->createTemplate($organisation,$owner,'owner',['name'=>'Professional appointment letter','category'=>'appointment','allowedRoles'=>['owner','admin','member'],'content'=>'Version one controlled content.','humanConfirmed'=>true]);
        $revised=$service->reviseTemplate($organisation,$owner,'owner',$template['id'],['content'=>'Version two controlled content.','reason'=>'Updated reviewed wording.','humanConfirmed'=>true]);
        self::assertSame(2,$revised['version']);
        self::assertSame('superseded',$service->template($organisation,$owner,$template['id'])['status']);

        $workspace=$service->workspace($organisation,$owner,$project);
        self::assertCount(1,$workspace['pipelineItems']);self::assertCount(1,$workspace['tasks']);self::assertCount(1,$workspace['resourceAllocations']);self::assertCount(1,$workspace['timesheets']);self::assertCount(1,$workspace['supervisionReviews']);self::assertCount(1,$workspace['invoiceReadiness']);self::assertCount(1,$workspace['registrationRenewals']);self::assertCount(2,$workspace['templates']);
        self::assertGreaterThanOrEqual(8,(int)$this->pdo->query("SELECT COUNT(*) FROM project_records WHERE module_key='practice_management_office_ops'")->fetchColumn());
        self::assertGreaterThanOrEqual(5,(int)$this->pdo->query("SELECT COUNT(*) FROM inbox_items WHERE event_type LIKE 'practice.%'")->fetchColumn());
        self::assertGreaterThanOrEqual(10,(int)$this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE action LIKE 'practice.%'")->fetchColumn());
    }

    public function testTenantIsolationHidesAnotherOrganisationsProject(): void
    {
        [$organisation,$owner]=$this->tenant('Tenant A','practice-a');[,,$otherProject]=$this->tenant('Tenant B','practice-b');
        try{(new PracticeManagementService($this->pdo))->workspace($organisation,$owner,$otherProject);self::fail('Cross-tenant project must be hidden.');}catch(PracticeException$e){self::assertSame('practice_project_not_found',$e->reason);}
    }

    /** @return array{string,string,string} */
    private function tenant(string$name,string$prefix):array{$organisation=Identifier::generate();$user=Identifier::generate();$project=Identifier::generate();$this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$user,'email'=>$prefix.'@example.test']);$this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id'=>$organisation,'name'=>$name,'slug'=>$prefix.'-'.substr($organisation,0,8)]);$this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'owner','active')")->execute(['id'=>Identifier::generate(),'o'=>$organisation,'u'=>$user]);$this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:p),UNHEX(:o),:code,:name,'Client','Johannesburg','Erf 1','Office','','architect','design_development',UNHEX(:u))")->execute(['p'=>$project,'o'=>$organisation,'u'=>$user,'code'=>strtoupper(substr($prefix,0,8)),'name'=>$name.' Project']);return[$organisation,$user,$project];}
    private function member(string$o,string$email):string{$u=Identifier::generate();$this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$u,'email'=>$email]);$this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'member','active')")->execute(['id'=>Identifier::generate(),'o'=>$o,'u'=>$u]);return$u;}
}
