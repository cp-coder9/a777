<?php

declare(strict_types=1);

namespace Architex\Platform\Governance;

use Architex\Platform\Audit\AuditService;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Inbox\InboxService;
use Architex\Platform\Project\ProjectContext;
use Architex\Platform\Project\ProjectPhase;
use Architex\Platform\Records\ProjectRecordService;
use JsonException;
use PDO;
use Throwable;

final readonly class ControlledRegisterService
{
    public function __construct(private PDO $pdo, private ControlledRegisterPolicy $policy) {}

    /** @return array{definition:array<string,mixed>,entries:list<array<string,mixed>>,guardrails:array<string,bool>} */
    public function workspace(string $organisationId, string $userId, string $projectId, string $registerKey): array
    {
        $definition = $this->policy->definition($registerKey);
        $this->projectPhase($organisationId, $userId, $projectId);
        $statement = $this->pdo->prepare("SELECT LOWER(HEX(id)) id,register_key,title,reference_code,status,owner_role,due_on,details,evidence_file_ids,version,LOWER(HEX(created_by)) created_by,LOWER(HEX(updated_by)) updated_by,DATE_FORMAT(created_at,'%Y-%m-%dT%H:%i:%s.%fZ') created_at,DATE_FORMAT(updated_at,'%Y-%m-%dT%H:%i:%s.%fZ') updated_at FROM controlled_register_entries WHERE organisation_id=UNHEX(:organisation) AND project_id=UNHEX(:project) AND register_key=:register ORDER BY updated_at DESC,id DESC");
        $statement->execute(['organisation'=>$organisationId,'project'=>$projectId,'register'=>$registerKey]);
        $entries = [];
        foreach ($statement as $row) $entries[] = $this->map($row);
        return [
            'definition'=>['key'=>$registerKey,'label'=>$definition['label'],'initialStatus'=>$definition['initial'],'transitions'=>$definition['transitions']],
            'entries'=>$entries,
            'guardrails'=>['humanConfirmationRequired'=>true,'externalSubmissionPerformed'=>false,'statutoryApprovalClaimed'=>false,'movesMoney'=>false],
        ];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function create(string $organisationId, string $userId, string $role, string $projectId, string $registerKey, array $input): array
    {
        $definition = $this->policy->definition($registerKey);
        $phase = $this->projectPhase($organisationId, $userId, $projectId);
        $this->confirmed($input);
        $title = $this->text($input['title'] ?? null, 220, 'controlled_register_title_required');
        $reference = $this->text($input['reference'] ?? null, 120, 'controlled_register_reference_required');
        $ownerRole = $this->text($input['ownerRole'] ?? null, 80, 'controlled_register_owner_role_required');
        $dueOn = $this->date($input['dueOn'] ?? null);
        $details = $this->object($input['details'] ?? null, 'controlled_register_details_required');
        $evidence = $this->evidence($organisationId, $projectId, $input['evidenceFileIds'] ?? []);
        $id = Identifier::generate();
        $payload = ['registerKey'=>$registerKey,'reference'=>$reference,'status'=>$definition['initial'],'ownerRole'=>$ownerRole,'dueOn'=>$dueOn,'details'=>$details,'evidenceFileIds'=>$evidence,'humanConfirmed'=>true,'externalSubmissionPerformed'=>false];
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare('INSERT INTO controlled_register_entries(id,organisation_id,project_id,register_key,title,reference_code,status,owner_role,due_on,details,evidence_file_ids,created_by,updated_by) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:project),:register,:title,:reference,:status,:owner_role,:due_on,:details,:evidence,UNHEX(:created_user),UNHEX(:updated_user))');
            $statement->execute(['id'=>$id,'organisation'=>$organisationId,'project'=>$projectId,'register'=>$registerKey,'title'=>$title,'reference'=>$reference,'status'=>$definition['initial'],'owner_role'=>$ownerRole,'due_on'=>$dueOn,'details'=>$this->json($details),'evidence'=>$this->json($evidence),'created_user'=>$userId,'updated_user'=>$userId]);
            $this->record($organisationId,$userId,$role,$projectId,$phase,$registerKey,$id,$title,'pending_review',$payload);
            (new InboxService($this->pdo))->create(new ProjectContext($organisationId,$userId,$projectId,$role),'controlled_register.review_required',$definition['label'].': '.$title,'Review the human-entered register evidence and choose an authorised transition.','medium',['sourceModule'=>$registerKey,'sourceObjectId'=>$id,'humanGate'=>'review','actionTarget'=>$registerKey]);
            (new AuditService($this->pdo))->append($organisationId,$userId,'controlled_register.created','controlled_register_entry',$id,$payload);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if ($exception instanceof ControlledRegisterException) throw $exception;
            throw new ControlledRegisterException(str_contains($exception->getMessage(), 'uq_controlled_register_reference') ? 'controlled_register_reference_conflict' : 'controlled_register_write_failed', $exception);
        }
        return $this->entry($organisationId, $id);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function transition(string $organisationId, string $userId, string $role, string $entryId, array $input): array
    {
        $this->confirmed($input);
        $row = $this->row($organisationId, $entryId);
        $projectId = (string)$row['project_id'];
        $phase = $this->projectPhase($organisationId, $userId, $projectId);
        $next = $this->text($input['status'] ?? null, 80, 'controlled_register_status_invalid');
        $reason = $this->text($input['reason'] ?? null, 1000, 'controlled_register_reason_required');
        $expected = $input['expectedVersion'] ?? null;
        if (!is_int($expected) || $expected < 1) throw new ControlledRegisterException('controlled_register_version_required');
        if ($expected !== (int)$row['version']) throw new ControlledRegisterException('controlled_register_version_conflict');
        $this->policy->assertTransition((string)$row['register_key'], (string)$row['status'], $next);
        $payload = ['registerKey'=>$row['register_key'],'reference'=>$row['reference_code'],'fromStatus'=>$row['status'],'status'=>$next,'reason'=>$reason,'humanConfirmed'=>true,'externalSubmissionPerformed'=>false];
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare('UPDATE controlled_register_entries SET status=:status,updated_by=UNHEX(:user),version=version+1 WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation) AND version=:version');
            $statement->execute(['status'=>$next,'user'=>$userId,'id'=>$entryId,'organisation'=>$organisationId,'version'=>$expected]);
            if ($statement->rowCount() !== 1) throw new ControlledRegisterException('controlled_register_version_conflict');
            $recordStatus = in_array($next, ['active','enrolled','compliant','resolved','closed'], true) ? 'approved' : 'pending_review';
            $this->record($organisationId,$userId,$role,$projectId,$phase,(string)$row['register_key'],$entryId,(string)$row['title'],$recordStatus,$payload);
            (new InboxService($this->pdo))->create(new ProjectContext($organisationId,$userId,$projectId,$role),'controlled_register.transitioned',(string)$row['title'].' moved to '.str_replace('_',' ',$next),$reason,'medium',['sourceModule'=>$row['register_key'],'sourceObjectId'=>$entryId,'humanGate'=>'review','actionTarget'=>$row['register_key']]);
            (new AuditService($this->pdo))->append($organisationId,$userId,'controlled_register.transitioned','controlled_register_entry',$entryId,$payload);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if ($exception instanceof ControlledRegisterException) throw $exception;
            throw new ControlledRegisterException('controlled_register_write_failed', $exception);
        }
        return $this->entry($organisationId, $entryId);
    }

    /** @param array<string,mixed> $payload */
    private function record(string $organisationId,string $userId,string $role,string $projectId,ProjectPhase $phase,string $key,string $entryId,string $title,string $status,array $payload): void
    {
        (new ProjectRecordService($this->pdo))->issue(new ProjectContext($organisationId,$userId,$projectId,$role),$phase,$key,$key.'_entry',$title,$status,$payload,['required'=>true,'humanApprovalConfirmed'=>$status==='approved','approvedBy'=>$status==='approved'?$userId:null],[$entryId]);
    }

    private function projectPhase(string $organisationId,string $userId,string $projectId): ProjectPhase
    {
        $statement=$this->pdo->prepare("SELECT p.current_phase FROM projects p JOIN organisation_memberships m ON m.organisation_id=p.organisation_id AND m.user_id=UNHEX(:user) AND m.status='active' WHERE p.id=UNHEX(:project) AND p.organisation_id=UNHEX(:organisation)");
        $statement->execute(['user'=>$userId,'project'=>$projectId,'organisation'=>$organisationId]);
        $phase=$statement->fetchColumn();
        if(!is_string($phase))throw new ControlledRegisterException('controlled_register_project_not_found');
        return ProjectPhase::from($phase);
    }

    /** @return array<string,mixed> */
    private function row(string $organisationId,string $entryId): array
    {
        if(preg_match('/\A[a-f0-9]{32}\z/i',$entryId)!==1)throw new ControlledRegisterException('controlled_register_entry_not_found');
        $statement=$this->pdo->prepare('SELECT LOWER(HEX(project_id)) project_id,register_key,title,reference_code,status,version FROM controlled_register_entries WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation)');
        $statement->execute(['id'=>$entryId,'organisation'=>$organisationId]);$row=$statement->fetch();
        if(!is_array($row))throw new ControlledRegisterException('controlled_register_entry_not_found');
        return$row;
    }

    /** @return array<string,mixed> */
    private function entry(string $organisationId,string $entryId): array
    {
        $statement=$this->pdo->prepare("SELECT LOWER(HEX(id)) id,register_key,title,reference_code,status,owner_role,due_on,details,evidence_file_ids,version,LOWER(HEX(created_by)) created_by,LOWER(HEX(updated_by)) updated_by,DATE_FORMAT(created_at,'%Y-%m-%dT%H:%i:%s.%fZ') created_at,DATE_FORMAT(updated_at,'%Y-%m-%dT%H:%i:%s.%fZ') updated_at FROM controlled_register_entries WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation)");
        $statement->execute(['id'=>$entryId,'organisation'=>$organisationId]);$row=$statement->fetch();
        if(!is_array($row))throw new ControlledRegisterException('controlled_register_entry_not_found');
        return$this->map($row);
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function map(array $row): array
    {
        return ['id'=>$row['id'],'registerKey'=>$row['register_key'],'title'=>$row['title'],'reference'=>$row['reference_code'],'status'=>$row['status'],'ownerRole'=>$row['owner_role'],'dueOn'=>$row['due_on'],'details'=>$this->decode($row['details']),'evidenceFileIds'=>$this->decode($row['evidence_file_ids']),'version'=>(int)$row['version'],'createdBy'=>$row['created_by'],'updatedBy'=>$row['updated_by'],'createdAt'=>$row['created_at'],'updatedAt'=>$row['updated_at']];
    }

    /** @param array<string,mixed> $input */ private function confirmed(array $input):void{if(($input['humanConfirmed']??false)!==true)throw new ControlledRegisterException('controlled_register_human_confirmation_required');}
    private function text(mixed $value,int $max,string $error):string{if(!is_string($value)||trim($value)===''||mb_strlen(trim($value))>$max)throw new ControlledRegisterException($error);return trim($value);}
    private function date(mixed $value):?string{if($value===null||$value==='')return null;if(!is_string($value)||preg_match('/\A\d{4}-\d{2}-\d{2}\z/D',$value)!==1||strtotime($value)===false)throw new ControlledRegisterException('controlled_register_due_date_invalid');return$value;}
    /** @return array<string,mixed> */ private function object(mixed$value,string$error):array{if(!is_array($value)||array_is_list($value)||count($value)>50)throw new ControlledRegisterException($error);$json=$this->json($value);if(strlen($json)>20_000)throw new ControlledRegisterException($error);return$value;}
    /** @return list<string> */ private function evidence(string$o,string$p,mixed$value):array{if(!is_array($value)||count($value)>50)throw new ControlledRegisterException('controlled_register_evidence_invalid');$ids=[];foreach($value as$id){if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new ControlledRegisterException('controlled_register_evidence_invalid');$ids[]=strtolower($id);}if($ids!==[]){$marks=implode(',',array_fill(0,count($ids),'UNHEX(?)'));$s=$this->pdo->prepare("SELECT COUNT(*) FROM stored_files WHERE organisation_id=UNHEX(?) AND project_id=UNHEX(?) AND id IN ($marks)");$s->execute([$o,$p,...$ids]);if((int)$s->fetchColumn()!==count(array_unique($ids)))throw new ControlledRegisterException('controlled_register_evidence_not_found');}return array_values(array_unique($ids));}
    private function json(array$value):string{return json_encode($value,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
    /** @return array<mixed> */ private function decode(mixed$value):array{if(!is_string($value))return[];try{$decoded=json_decode($value,true,64,JSON_THROW_ON_ERROR);}catch(JsonException){return[];}return is_array($decoded)?$decoded:[];}
}
