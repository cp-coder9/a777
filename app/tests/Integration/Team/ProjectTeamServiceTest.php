<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Team;

use Architex\Platform\Identity\Identifier;
use Architex\Platform\Team\ProjectTeamService;
use Architex\Platform\Team\TeamException;
use Architex\Tests\Support\MariaDbTestCase;

final class ProjectTeamServiceTest extends MariaDbTestCase
{
    public function testInvitesIndependentlyVerifiedProfessionalAndTheyAccept(): void
    {
        [$organisationId,$ownerId,$projectId]=$this->seedProjectTenant('team-alpha');
        [$professionalOrganisation,$professionalId]=$this->seedUserTenant('team-structural');
        $this->seedVerifiedProfessional($professionalOrganisation,$professionalId,'engineer');
        $service=new ProjectTeamService($this->pdo);

        $invitation=$service->invite($organisationId,$projectId,$ownerId,$professionalId,'structure','Structural engineer');
        self::assertSame('invited',$invitation['status']);
        self::assertSame('manually_reviewed',$invitation['verificationGrade']);
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM inbox_items WHERE event_type='project_team_invitation'")->fetchColumn());
        $pending=$service->pendingForUser($professionalId);
        self::assertCount(1,$pending);
        self::assertSame('Team project',$pending[0]['projectName']);

        $accepted=$service->accept($professionalId,$invitation['id']);
        self::assertSame('active',$accepted['status']);
        self::assertSame($projectId,$accepted['projectId']);
        self::assertSame(2,(int)$this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
        self::assertSame(['structure'],array_column($service->list($organisationId,$projectId,$ownerId),'discipline'));
        self::assertSame([],$service->pendingForUser($professionalId));
    }

    public function testBlocksUnverifiedOrExpiredCandidatesAndCrossTenantReads(): void
    {
        [$organisationId,$ownerId,$projectId]=$this->seedProjectTenant('team-beta');
        [, $candidateId]=$this->seedUserTenant('team-unverified');
        $service=new ProjectTeamService($this->pdo);
        try{$service->invite($organisationId,$projectId,$ownerId,$candidateId,'architecture','Architect');self::fail('Unverified candidate must be blocked.');}
        catch(TeamException $exception){self::assertSame('candidate_verification_required',$exception->reason);}

        $ordinaryMember=Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'team-ordinary@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$ordinaryMember]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'member','active')")->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'user_id'=>$ordinaryMember]);
        try{$service->list($organisationId,$projectId,$ordinaryMember);self::fail('An unrelated organisation member must not manage the project team.');}
        catch(TeamException $exception){self::assertSame('project_not_found',$exception->reason);}

        [$otherOrganisation,$otherOwner]=$this->seedUserTenant('team-other');
        $this->expectException(TeamException::class);
        $this->expectExceptionMessage('project_not_found');
        $service->list($otherOrganisation,$projectId,$otherOwner);
    }

    public function testRemovalPreservesHistoryAndOnlyInviteeMayAccept(): void
    {
        [$organisationId,$ownerId,$projectId]=$this->seedProjectTenant('team-gamma');
        [$professionalOrganisation,$professionalId]=$this->seedUserTenant('team-planner');
        $this->seedVerifiedProfessional($professionalOrganisation,$professionalId,'town_planner');
        [, $otherUser]=$this->seedUserTenant('team-wrong-user');
        $service=new ProjectTeamService($this->pdo);
        $invitation=$service->invite($organisationId,$projectId,$ownerId,$professionalId,'planning','Town planner');
        try{$service->accept($otherUser,$invitation['id']);self::fail('Only invitee may accept.');}
        catch(TeamException $exception){self::assertSame('invitation_not_found',$exception->reason);}
        $service->accept($professionalId,$invitation['id']);
        $removed=$service->remove($organisationId,$projectId,$ownerId,$invitation['id'],'Scope no longer requires planning input.');
        self::assertSame('removed',$removed['status']);
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM project_team_members WHERE status='removed' AND removed_at IS NOT NULL")->fetchColumn());
        self::assertSame([], $service->list($organisationId,$projectId,$ownerId));
        $replacement=$service->invite($organisationId,$projectId,$ownerId,$professionalId,'planning','Town planner — renewed scope');
        self::assertNotSame($invitation['id'],$replacement['id']);
        self::assertSame(2,(int)$this->pdo->query("SELECT COUNT(*) FROM project_team_members WHERE discipline='planning'")->fetchColumn());
        $history=$this->pdo->query("SELECT status,removal_reason FROM project_team_members WHERE status='removed'")->fetch();
        self::assertSame('Scope no longer requires planning input.',$history['removal_reason']);
    }

    /** @return array{string,string,string} */
    private function seedProjectTenant(string $slug):array
    {
        [$organisationId,$userId]=$this->seedUserTenant($slug);$projectId=Identifier::generate();
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,created_by) VALUES(UNHEX(:id),UNHEX(:organisation_id),:code,'Team project','Client','Tshwane','Erf 1','Residential','','architect',UNHEX(:user_id))")->execute(['id'=>$projectId,'organisation_id'=>$organisationId,'code'=>strtoupper(substr($slug,0,20)),'user_id'=>$userId]);
        return[$organisationId,$userId,$projectId];
    }

    /** @return array{string,string} */
    private function seedUserTenant(string $slug):array
    {
        $organisationId=Identifier::generate();$userId=Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$userId,'email'=>$slug.'@example.test']);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id'=>$organisationId,'name'=>ucfirst($slug),'slug'=>$slug]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'owner','active')")->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'user_id'=>$userId]);
        return[$organisationId,$userId];
    }

    private function seedVerifiedProfessional(string $organisationId,string $userId,string $profession):void
    {
        $this->pdo->prepare("INSERT INTO professional_profiles(id,organisation_id,user_id,display_name,profession,bio,service_areas,directory_visible) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),:name,:profession,'','[]',1)")->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'user_id'=>$userId,'name'=>ucfirst(str_replace('_',' ',$profession)),'profession'=>$profession]);
        $this->pdo->prepare("INSERT INTO verification_records(id,organisation_id,user_id,kind,grade,popia_consent_at,processing_purpose,retention_until) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'professional_registration','manually_reviewed',UTC_TIMESTAMP(6),'Project team eligibility','2031-08-27')")->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'user_id'=>$userId]);
    }
}
