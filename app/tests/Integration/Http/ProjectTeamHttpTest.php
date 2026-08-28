<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Http;

use Architex\Http\AuthenticationKernelFactory;
use Architex\Http\Request;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Identity\PasswordResetNotifier;
use Architex\Platform\Identity\RegistrationNotifier;
use Architex\Platform\Identity\SessionService;
use Architex\Platform\Team\ProjectTeamService;
use Architex\Tests\Support\MariaDbTestCase;

final class ProjectTeamHttpTest extends MariaDbTestCase
{
    public function testInvitationRoutesWorkForExternalProfessionalWithoutOrganisationMembership():void
    {
        [$organisationId,$ownerId,$projectId]=$this->seedProject();
        $candidateId=$this->seedCandidate();
        $invitation=(new ProjectTeamService($this->pdo))->invite($organisationId,$projectId,$ownerId,$candidateId,'structure','Structural engineer');
        $notifier=new class implements RegistrationNotifier,PasswordResetNotifier{public function isAvailable():bool{return false;}public function send(string $email,string $token,string $expiresAt):void{}};
        $kernel=(new AuthenticationKernelFactory($this->pdo,$notifier,$notifier))->create();
        $sessions=new SessionService($this->pdo);$candidateSession=$sessions->rotate($candidateId);$ownerSession=$sessions->rotate($ownerId);

        self::assertSame(401,$kernel->handle(Request::json('GET','/api/v1/team-invitations'))->status());
        $pending=$kernel->handle(Request::json('GET','/api/v1/team-invitations',['Cookie'=>'architex_session='.$candidateSession->token]));
        self::assertSame(200,$pending->status());self::assertSame($invitation['id'],$pending->json()['data'][0]['id']);
        $accepted=$kernel->handle(Request::json('POST','/api/v1/team-invitations/'.$invitation['id'].'/accept',['Cookie'=>'architex_session='.$candidateSession->token,'X-CSRF-Token'=>$candidateSession->csrfToken]));
        self::assertSame(200,$accepted->status(),json_encode($accepted->json(),JSON_THROW_ON_ERROR));self::assertSame('active',$accepted->json()['data']['status']);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'member','active')")->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'user_id'=>$candidateId]);
        $projects=$kernel->handle(Request::json('GET','/api/v1/projects',['Cookie'=>'architex_session='.$candidateSession->token,'X-Organisation-Id'=>$organisationId]));
        self::assertSame(200,$projects->status(),json_encode($projects->json(),JSON_THROW_ON_ERROR));self::assertSame('structure',$projects->json()['data'][0]['effectiveRole']);
        $team=$kernel->handle(Request::json('GET','/api/v1/projects/'.$projectId.'/team',['Cookie'=>'architex_session='.$ownerSession->token,'X-Organisation-Id'=>$organisationId]));
        self::assertSame(200,$team->status(),json_encode($team->json(),JSON_THROW_ON_ERROR));self::assertSame('active',$team->json()['data'][0]['status']);
    }

    /** @return array{string,string,string} */
    private function seedProject():array
    {
        $organisationId=Identifier::generate();$ownerId=Identifier::generate();$projectId=Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'team-owner@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$ownerId]);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Team owner','team-http-owner','active')")->execute(['id'=>$organisationId]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'owner','active')")->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'user_id'=>$ownerId]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,created_by) VALUES(UNHEX(:id),UNHEX(:organisation_id),'TEAM-HTTP','HTTP Team Project','Client','Tshwane','Erf 9','Residential','','architect',UNHEX(:user_id))")->execute(['id'=>$projectId,'organisation_id'=>$organisationId,'user_id'=>$ownerId]);
        return[$organisationId,$ownerId,$projectId];
    }

    private function seedCandidate():string
    {
        $organisationId=Identifier::generate();$userId=Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'team-candidate@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$userId]);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Candidate practice','team-http-candidate','active')")->execute(['id'=>$organisationId]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'owner','active')")->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'user_id'=>$userId]);
        $this->pdo->prepare("INSERT INTO professional_profiles(id,organisation_id,user_id,display_name,profession,bio,service_areas,directory_visible) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'Candidate Engineer','engineer','','[]',1)")->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'user_id'=>$userId]);
        $this->pdo->prepare("INSERT INTO verification_records(id,organisation_id,user_id,kind,grade,popia_consent_at,processing_purpose,retention_until) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'professional_registration','manually_reviewed',UTC_TIMESTAMP(6),'Project team eligibility','2031-08-27')")->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'user_id'=>$userId]);
        return$userId;
    }
}
