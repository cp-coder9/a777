<?php

declare(strict_types=1);

namespace Architex\Platform\Team;

use Architex\Platform\Audit\AuditService;
use Architex\Platform\Identity\Identifier;
use PDO;
use PDOException;
use Throwable;

final readonly class ProjectTeamService
{
    private AuditService $audit;
    public function __construct(private PDO $pdo){$this->audit=new AuditService($pdo);}

    /** @return array<string,mixed> */
    public function invite(string $organisationId,string $projectId,string $inviterId,string $candidateId,string $discipline,string $roleLabel):array
    {
        $this->assertProjectManager($organisationId,$projectId,$inviterId);
        $discipline=$this->text($discipline,80,'invalid_discipline');$roleLabel=$this->text($roleLabel,120,'invalid_role_label');
        if(hash_equals(strtolower($inviterId),strtolower($candidateId)))throw new TeamException('self_invitation_forbidden');
        $grade=$this->verificationGrade($candidateId);if($grade===null)throw new TeamException('candidate_verification_required');
        return $this->transaction(function()use($organisationId,$projectId,$inviterId,$candidateId,$discipline,$roleLabel,$grade):array{
            $existing=$this->pdo->prepare("SELECT LOWER(HEX(id)) id,status FROM project_team_members WHERE project_id=UNHEX(:project_id) AND user_id=UNHEX(:user_id) AND discipline=:discipline AND status IN ('invited','active') FOR UPDATE");
            $existing->execute(['project_id'=>$projectId,'user_id'=>$candidateId,'discipline'=>$discipline]);$row=$existing->fetch();
            if(is_array($row))throw new TeamException('team_member_already_present');
            $id=Identifier::generate();
            $statement=$this->pdo->prepare("INSERT INTO project_team_members(id,organisation_id,project_id,user_id,discipline,role_label,verification_grade,status,invited_by) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:project_id),UNHEX(:user_id),:discipline,:role_label,:grade,'invited',UNHEX(:invited_by))");
            try{$statement->execute(['id'=>$id,'organisation_id'=>$organisationId,'project_id'=>$projectId,'user_id'=>$candidateId,'discipline'=>$discipline,'role_label'=>$roleLabel,'grade'=>$grade,'invited_by'=>$inviterId]);}
            catch(PDOException $exception){if((string)$exception->getCode()==='23000')throw new TeamException('team_member_already_present');throw$exception;}
            $this->inbox($organisationId,$projectId,$candidateId,'project_team_invitation','Project team invitation','You were invited as '.$roleLabel,['teamMemberId'=>$id,'discipline'=>$discipline,'invitedBy'=>$inviterId]);
            $this->audit->append($organisationId,$inviterId,'project_team.invited','project_team_member',$id,['projectId'=>$projectId,'candidateId'=>$candidateId,'discipline'=>$discipline,'verificationGrade'=>$grade]);
            return ['id'=>$id,'projectId'=>$projectId,'userId'=>$candidateId,'discipline'=>$discipline,'roleLabel'=>$roleLabel,'verificationGrade'=>$grade,'status'=>'invited'];
        });
    }

    /** @return array<string,mixed> */
    public function accept(string $userId,string $invitationId):array
    {
        return $this->transaction(function()use($userId,$invitationId):array{
            $statement=$this->pdo->prepare("SELECT LOWER(HEX(id)) id,LOWER(HEX(organisation_id)) organisation_id,LOWER(HEX(project_id)) project_id,LOWER(HEX(invited_by)) invited_by,discipline,role_label,verification_grade,status FROM project_team_members WHERE id=UNHEX(:id) AND user_id=UNHEX(:user_id) FOR UPDATE");$statement->execute(['id'=>$invitationId,'user_id'=>$userId]);$row=$statement->fetch();
            if(!is_array($row)||$row['status']!=='invited')throw new TeamException('invitation_not_found');
            $current=$this->verificationGrade($userId);if($current===null)throw new TeamException('candidate_verification_required');
            $this->pdo->prepare("UPDATE project_team_members SET status='active',verification_grade=:grade,accepted_at=UTC_TIMESTAMP(6) WHERE id=UNHEX(:id) AND status='invited'")->execute(['grade'=>$current,'id'=>$invitationId]);
            $this->inbox((string)$row['organisation_id'],(string)$row['project_id'],(string)$row['invited_by'],'project_team_invitation_accepted','Project invitation accepted',$row['role_label'].' accepted the team invitation.',['teamMemberId'=>$invitationId,'acceptedBy'=>$userId]);
            $this->audit->append((string)$row['organisation_id'],$userId,'project_team.accepted','project_team_member',$invitationId,['projectId'=>$row['project_id'],'discipline'=>$row['discipline'],'verificationGrade'=>$current]);
            return ['id'=>$invitationId,'projectId'=>$row['project_id'],'userId'=>$userId,'discipline'=>$row['discipline'],'roleLabel'=>$row['role_label'],'verificationGrade'=>$current,'status'=>'active'];
        });
    }

    /** @return array<string,mixed> */
    public function remove(string $organisationId,string $projectId,string $actorId,string $teamMemberId,string $reason):array
    {
        $this->assertProjectManager($organisationId,$projectId,$actorId);$reason=$this->text($reason,500,'removal_reason_required');
        return $this->transaction(function()use($organisationId,$projectId,$actorId,$teamMemberId,$reason):array{
            $statement=$this->pdo->prepare("SELECT LOWER(HEX(user_id)) user_id,status FROM project_team_members WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation_id) AND project_id=UNHEX(:project_id) FOR UPDATE");$statement->execute(['id'=>$teamMemberId,'organisation_id'=>$organisationId,'project_id'=>$projectId]);$row=$statement->fetch();if(!is_array($row)||!in_array($row['status'],['invited','active'],true))throw new TeamException('team_member_not_found');
            $this->pdo->prepare("UPDATE project_team_members SET status='removed',removed_by=UNHEX(:actor),removed_at=UTC_TIMESTAMP(6),removal_reason=:reason WHERE id=UNHEX(:id)")->execute(['actor'=>$actorId,'reason'=>$reason,'id'=>$teamMemberId]);
            $this->inbox($organisationId,$projectId,(string)$row['user_id'],'project_team_removed','Project team assignment ended',$reason,['teamMemberId'=>$teamMemberId,'removedBy'=>$actorId]);
            $this->audit->append($organisationId,$actorId,'project_team.removed','project_team_member',$teamMemberId,['projectId'=>$projectId,'reason'=>$reason]);
            return ['id'=>$teamMemberId,'projectId'=>$projectId,'userId'=>$row['user_id'],'status'=>'removed','removalReason'=>$reason];
        });
    }

    /** @return list<array<string,mixed>> */
    public function list(string $organisationId,string $projectId,string $actorId):array
    {
        $this->assertProjectManager($organisationId,$projectId,$actorId);
        $statement=$this->pdo->prepare("SELECT LOWER(HEX(t.id)) id,LOWER(HEX(t.user_id)) user_id,t.discipline,t.role_label,t.verification_grade,t.status,t.invited_at,t.accepted_at,p.display_name,p.profession,CASE WHEN m.user_id IS NULL THEN 0 ELSE 1 END communication_eligible FROM project_team_members t LEFT JOIN professional_profiles p ON p.id=(SELECT pp.id FROM professional_profiles pp WHERE pp.user_id=t.user_id AND pp.directory_visible=1 ORDER BY pp.created_at DESC,pp.id DESC LIMIT 1) LEFT JOIN organisation_memberships m ON m.organisation_id=t.organisation_id AND m.user_id=t.user_id AND m.status='active' WHERE t.organisation_id=UNHEX(:organisation_id) AND t.project_id=UNHEX(:project_id) AND t.status IN ('invited','active') ORDER BY t.discipline,t.invited_at");$statement->execute(['organisation_id'=>$organisationId,'project_id'=>$projectId]);$rows=[];foreach($statement as $row)$rows[]=['id'=>$row['id'],'userId'=>$row['user_id'],'displayName'=>$row['display_name'],'profession'=>$row['profession'],'discipline'=>$row['discipline'],'roleLabel'=>$row['role_label'],'verificationGrade'=>$row['verification_grade'],'status'=>$row['status'],'invitedAt'=>$row['invited_at'],'acceptedAt'=>$row['accepted_at'],'communicationEligible'=>(bool)$row['communication_eligible']];return$rows;
    }

    /** @return list<array<string,mixed>> */
    public function pendingForUser(string $userId):array
    {
        $statement=$this->pdo->prepare("SELECT LOWER(HEX(t.id)) id,LOWER(HEX(t.organisation_id)) organisation_id,LOWER(HEX(t.project_id)) project_id,t.discipline,t.role_label,t.verification_grade,t.invited_at,o.name organisation_name,p.project_name,u.email inviter_email FROM project_team_members t JOIN organisations o ON o.id=t.organisation_id JOIN projects p ON p.id=t.project_id JOIN users u ON u.id=t.invited_by WHERE t.user_id=UNHEX(:user_id) AND t.status='invited' ORDER BY t.invited_at DESC");
        $statement->execute(['user_id'=>$userId]);$rows=[];
        foreach($statement as $row)$rows[]=['id'=>$row['id'],'organisationId'=>$row['organisation_id'],'organisationName'=>$row['organisation_name'],'projectId'=>$row['project_id'],'projectName'=>$row['project_name'],'discipline'=>$row['discipline'],'roleLabel'=>$row['role_label'],'verificationGrade'=>$row['verification_grade'],'invitedAt'=>$row['invited_at'],'inviterEmail'=>$row['inviter_email']];
        return$rows;
    }

    private function verificationGrade(string $userId):?string
    {
        $statement=$this->pdo->prepare("SELECT grade FROM verification_records WHERE user_id=UNHEX(:user_id) AND kind='professional_registration' AND grade IN ('manually_reviewed','externally_verified') AND (expires_on IS NULL OR expires_on>=UTC_DATE()) ORDER BY FIELD(grade,'externally_verified','manually_reviewed'),created_at DESC LIMIT 1");$statement->execute(['user_id'=>$userId]);$grade=$statement->fetchColumn();return is_string($grade)?$grade:null;
    }
    private function assertProjectManager(string $organisationId,string $projectId,string $userId):void{$statement=$this->pdo->prepare("SELECT COUNT(*) FROM projects p JOIN organisation_memberships m ON m.organisation_id=p.organisation_id AND m.user_id=UNHEX(:member_user_id) AND m.status='active' WHERE p.id=UNHEX(:project_id) AND p.organisation_id=UNHEX(:organisation_id) AND (m.role IN ('owner','admin') OR p.created_by=UNHEX(:creator_user_id))");$statement->execute(['member_user_id'=>$userId,'creator_user_id'=>$userId,'project_id'=>$projectId,'organisation_id'=>$organisationId]);if((int)$statement->fetchColumn()!==1)throw new TeamException('project_not_found');}
    /** @param array<string,mixed> $payload */
    private function inbox(string $organisationId,string $projectId,string $userId,string $event,string $title,string $detail,array $payload):void{$this->pdo->prepare("INSERT INTO inbox_items(id,organisation_id,project_id,assigned_user_id,event_type,title,detail,priority,status,payload) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:project_id),UNHEX(:user_id),:event,:title,:detail,'medium','open',:payload)")->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'project_id'=>$projectId,'user_id'=>$userId,'event'=>$event,'title'=>$title,'detail'=>$detail,'payload'=>json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)]);}
    private function text(string $value,int $max,string $reason):string{$value=trim($value);if($value===''||mb_strlen($value)>$max)throw new TeamException($reason);return$value;}
    private function transaction(callable $operation):mixed{$this->pdo->beginTransaction();try{$result=$operation();$this->pdo->commit();return$result;}catch(Throwable $exception){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$exception;}}
}
