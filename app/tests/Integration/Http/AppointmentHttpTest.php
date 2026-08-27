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

final class AppointmentHttpTest extends MariaDbTestCase
{
    public function testOwnerCreatesConfirmsAndIssuesAppointmentThenReadsKickoffByProject(): void
    {
        [$organisationId,$ownerId,$projectId,$proposalId]=$this->seedAcceptedProposal();
        $notifier=new class implements RegistrationNotifier,PasswordResetNotifier{public function isAvailable():bool{return false;}public function send(string $email,string $token,string $expiresAt):void{}};
        $kernel=(new AuthenticationKernelFactory($this->pdo,$notifier,$notifier))->create();
        $session=(new SessionService($this->pdo))->rotate($ownerId);
        $headers=['Cookie'=>'architex_session='.$session->token,'X-Organisation-Id'=>$organisationId,'X-CSRF-Token'=>$session->csrfToken];

        $created=$kernel->handle($this->json('POST','/api/v1/appointments',$headers,[
            'proposalId'=>$proposalId,'projectFacts'=>['municipality'=>'City of Tshwane','province'=>'Gauteng','propertyDescription'=>'Erf 16, Pretoria','erfNumber'=>'16','landUseOrZoningKnown'=>true,'professionalBody'=>'SACAP','professionalRegistrationNumber'=>'PrArch 54321'],
        ]));
        self::assertSame(201,$created->status(),$created->body());
        $appointmentId=$created->json()['data']['id'];
        self::assertSame('blocked',$created->json()['data']['kickoffReadiness']);

        $confirmed=$kernel->handle($this->json('POST','/api/v1/appointments/'.$appointmentId.'/professional-confirmation',$headers,['responsibilityConfirmed'=>true,'confirmationReason'=>'Responsibility and exact snapshot reviewed.']));
        self::assertSame(200,$confirmed->status(),$confirmed->body());
        self::assertSame('ready',$confirmed->json()['data']['kickoffReadiness']);

        $issued=$kernel->handle($this->json('POST','/api/v1/appointments/'.$appointmentId.'/issue',$headers,['humanApprovalConfirmed'=>true,'issueReason'=>'Exact appointment revision approved for issue.']));
        self::assertSame(200,$issued->status(),$issued->body());
        self::assertNotNull($issued->json()['data']['formallyIssuedAt']);

        $kickoff=$kernel->handle(Request::json('GET','/api/v1/projects/'.$projectId.'/kickoff-checklist',$headers));
        self::assertSame(200,$kickoff->status(),$kickoff->body());
        self::assertSame($appointmentId,$kickoff->json()['data']['id']);
        self::assertCount(3,$kickoff->json()['data']['initialTasks']);
    }

    /** @return array{string,string,string,string} */
    private function seedAcceptedProposal():array
    {
        $organisationId=Identifier::generate();$ownerId=Identifier::generate();$projectId=Identifier::generate();$proposalId=Identifier::generate();$feeRunId=Identifier::generate();$acceptanceId=Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'appointment-http@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$ownerId]);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Appointment HTTP','appointment-http','active')")->execute(['id'=>$organisationId]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'owner','active')")->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'user_id'=>$ownerId]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,created_by) VALUES(UNHEX(:id),UNHEX(:organisation_id),'APPT-HTTP','HTTP Appointment Project','HTTP Client','City of Tshwane','Erf 16','Residential','','architect',UNHEX(:user_id))")->execute(['id'=>$projectId,'organisation_id'=>$organisationId,'user_id'=>$ownerId]);
        $this->pdo->prepare("INSERT INTO tool_runs(id,organisation_id,user_id,tool_id,tool_version,executed_role,assignment_mode,project_id,status,input_payload,output_payload,audit_hash,audit_reason,issued_at) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'professional_fee_calculator','1.0.0','owner','internal_project',UNHEX(:project_id),'issued','{}','{\"totalInclVat\":57900}',:hash,'Reviewed',UTC_TIMESTAMP(6))")->execute(['id'=>$feeRunId,'organisation_id'=>$organisationId,'user_id'=>$ownerId,'project_id'=>$projectId,'hash'=>hash('sha256','appointment-http-fee')]);
        $document=['title'=>'HTTP Proposal','project'=>['name'=>'HTTP Appointment Project'],'feeSnapshot'=>['toolVersion'=>'1.0.0','output'=>['totalInclVat'=>57900]],'assumptions'=>['Accurate information.'],'exclusions'=>['Statutory fees.'],'terms'=>[['key'=>'standard','clauses'=>['Reviewed terms.']]],'issue'=>['professionalConfirmed'=>true,'legalReviewConfirmed'=>true]];
        $snapshot=json_encode($document,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);$hash=hash('sha256',$snapshot);
        $this->pdo->prepare("INSERT INTO professional_proposals(id,organisation_id,project_id,fee_run_id,created_by,title,client_name,client_email,status,validity_days,document_snapshot,audit_hash,issued_at,accepted_at) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:project_id),UNHEX(:fee_run_id),UNHEX(:user_id),'HTTP Proposal','HTTP Client','http-client@example.test','accepted',30,:snapshot,:hash,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))")->execute(['id'=>$proposalId,'organisation_id'=>$organisationId,'project_id'=>$projectId,'fee_run_id'=>$feeRunId,'user_id'=>$ownerId,'snapshot'=>$snapshot,'hash'=>$hash]);
        $this->pdo->prepare("INSERT INTO proposal_acceptance_requests(id,organisation_id,proposal_id,recipient_email,token_hash,status,accepted_by,responsibility_confirmed,accepted_at,expires_at) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:proposal_id),'http-client@example.test',:token_hash,'accepted','HTTP Client',1,UTC_TIMESTAMP(6),DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 30 DAY))")->execute(['id'=>$acceptanceId,'organisation_id'=>$organisationId,'proposal_id'=>$proposalId,'token_hash'=>hash('sha256','accepted-http-token')]);
        return[$organisationId,$ownerId,$projectId,$proposalId];
    }

    /** @param array<string,string> $headers @param array<string,mixed> $body */
    private function json(string $method,string $path,array $headers,array $body):Request{return Request::json($method,$path,$headers+['Content-Type'=>'application/json'],json_encode($body,JSON_THROW_ON_ERROR));}
}
