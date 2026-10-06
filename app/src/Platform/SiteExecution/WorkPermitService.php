<?php

declare(strict_types=1);
namespace Architex\Platform\SiteExecution;

use Architex\Platform\Audit\AuditService;
use Architex\Platform\Identity\Identifier;
use PDO;
use Throwable;

final readonly class WorkPermitService
{
    public function __construct(private PDO $pdo) {}

    public function workspace(string $o,string $u,string $p): array
    {
        $role=$this->scope($o,$u,$p);
        $s=$this->pdo->prepare('SELECT LOWER(HEX(id)) id FROM work_permits WHERE organisation_id=UNHEX(:o) AND project_id=UNHEX(:p) ORDER BY updated_at DESC,id DESC LIMIT 500');
        $s->execute(['o'=>$o,'p'=>$p]);
        $records=[];
        foreach($s->fetchAll(PDO::FETCH_COLUMN) as $id) $records[]=$this->get($o,$u,$p,$id);
        return ['permits'=>$records,'actorId'=>$u,'canDraft'=>in_array($role,['owner','admin','member'],true),'canAuthorize'=>in_array($role,['owner','admin'],true),'guardrails'=>['internalOnly'=>true,'statutoryApprovalClaimed'=>false,'externalSubmissionPerformed'=>false,'humanReviewRequired'=>true]];
    }

    public function get(string $o,string $u,string $p,string $id): array
    {
        // Locking reads are current reads even in an existing REPEATABLE READ transaction.
        // Never commit or roll back a transaction owned by our caller.
        $owned=!$this->pdo->inTransaction();
        if($owned)$this->pdo->beginTransaction();
        try{
            $role=$this->lockedScope($o,$u,$p);
            $record=$this->row($o,$p,$id,true);
            $record['history']=$this->verifiedHistory($o,$p,$id,$record);
            if($owned)$this->pdo->commit();
        }catch(Throwable $e){if($owned && $this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
        $allowed=[];
        if(in_array($role,['owner','admin','member'],true))foreach(WorkPermitPolicy::transitions($record['status']) as $action){
            if($action==='requested' && $u!==$record['createdBy'])continue;
            if($action==='reviewed' && ($u!==$record['payload']['responsiblePersonId'] || $u===$record['createdBy']))continue;
            if(in_array($action,['issued','revoked'],true) && !in_array($role,['owner','admin'],true))continue;
            if($action==='issued' && ($u===$record['reviewedBy'] || $u===$record['createdBy']))continue;
            if($action==='closed' && $u!==$record['payload']['responsiblePersonId'] && !in_array($role,['owner','admin'],true))continue;
            $allowed[]=$action;
        }
        $record['allowedTransitions']=$allowed;
        return $record;
    }

    public function create(string $o,string $u,string $p,array $input): array
    {
        $this->confirmed($input);
        $id=Identifier::generate();
        $reference=WorkPermitPolicy::text($input['reference']??null,120);
        $this->pdo->beginTransaction();
        try{
            $this->writer($this->lockedScope($o,$u,$p));
            $payload=$this->payload($o,$p,$input);
            if($payload['responsiblePersonId']===$u)throw new WorkPermitException('work_permit_independent_competent_review_required',403);
            $s=$this->pdo->prepare('INSERT INTO work_permits(id,organisation_id,project_id,reference_code,payload,created_by) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:p),:reference,:payload,UNHEX(:u))');
            $s->execute(['id'=>$id,'o'=>$o,'p'=>$p,'reference'=>$reference,'payload'=>$this->json($payload),'u'=>$u]);
            $this->receipt($o,$u,$p,$id,'draft','Initial human-entered draft');
            $this->pdo->commit();
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();if($e instanceof \PDOException && $e->getCode()==='23000')throw new WorkPermitException('work_permit_reference_conflict',409);throw $e;}
        return $this->get($o,$u,$p,$id);
    }

    public function transition(string $o,string $u,string $p,string $id,array $input): array
    {
        $this->confirmed($input);
        $next=WorkPermitPolicy::text($input['status']??null,30);
        $reason=WorkPermitPolicy::text($input['reason']??null,2000);
        $expected=$input['expectedVersion']??null;
        if(!is_int($expected)||$expected<1)throw new WorkPermitException('work_permit_version_required');
        $this->pdo->beginTransaction();
        try{
            $role=$this->lockedScope($o,$u,$p);$this->writer($role);
            $row=$this->row($o,$p,$id,true);
            $this->verifiedHistory($o,$p,$id,$row);
            if($row['version']!==$expected)throw new WorkPermitException('work_permit_version_conflict',409);
            WorkPermitPolicy::assertTransition($row['status'],$next);
            $payload=$row['payload'];$reviewer=$row['reviewedBy'];$issuer=$row['issuedBy'];
            if($next==='requested' && $u!==$row['createdBy'])throw new WorkPermitException('work_permit_actor_denied',403);
            if($next==='reviewed'){
                if($u!==$payload['responsiblePersonId'] || $u===$row['createdBy'])throw new WorkPermitException('work_permit_independent_competent_review_required',403);
                $files=$this->evidence($o,$p,$input['evidenceFileIds']??null,true);
                $payload['review']=['statement'=>WorkPermitPolicy::text($input['responsibilityStatement']??null,2000),'evidenceFileIds'=>$files,'actorId'=>$u,'reviewedAt'=>gmdate('Y-m-d\TH:i:s\Z')];$reviewer=$u;
            }
            if(in_array($next,['issued','revoked'],true) && !in_array($role,['owner','admin'],true))throw new WorkPermitException('work_permit_authorizer_required',403);
            if($next==='issued'){
                if($u===$reviewer || $u===$row['createdBy'])throw new WorkPermitException('work_permit_independent_authorizer_required',403);
                if(gmdate('Y-m-d\TH:i')>=$payload['validUntil'])throw new WorkPermitException('work_permit_expired',409);
                $this->writer($this->lockedScope($o,$payload['responsiblePersonId'],$p));
                $this->evidence($o,$p,$payload['competenceEvidenceFileIds'],true);
                $this->evidence($o,$p,$payload['review']['evidenceFileIds']??null,true);
                if($payload['externalPermitRequired'])$this->evidence($o,$p,$payload['externalPermitFileIds'],true);
                $payload['authorization']=['statement'=>WorkPermitPolicy::text($input['responsibilityStatement']??null,2000),'actorId'=>$u,'issuedAt'=>gmdate('Y-m-d\TH:i:s\Z')];$issuer=$u;
            }
            if($next==='closed'){
                if($u!==$payload['responsiblePersonId'] && !in_array($role,['owner','admin'],true))throw new WorkPermitException('work_permit_actor_denied',403);
                $payload['closure']=['evidenceFileIds'=>$this->evidence($o,$p,$input['evidenceFileIds']??null,true),'statement'=>WorkPermitPolicy::text($input['responsibilityStatement']??null,2000),'actorId'=>$u];
            }
            $s=$this->pdo->prepare('UPDATE work_permits SET status=:status,payload=:payload,reviewed_by=UNHEX(:reviewer),issued_by=UNHEX(:issuer),version=version+1 WHERE organisation_id=UNHEX(:o) AND project_id=UNHEX(:p) AND id=UNHEX(:id) AND version=:v');
            $s->execute(['status'=>$next,'payload'=>$this->json($payload),'reviewer'=>$reviewer,'issuer'=>$issuer,'o'=>$o,'p'=>$p,'id'=>$id,'v'=>$expected]);
            if($s->rowCount()!==1)throw new WorkPermitException('work_permit_version_conflict',409);
            $this->receipt($o,$u,$p,$id,$next,$reason);$this->pdo->commit();
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
        return $this->get($o,$u,$p,$id);
    }

    private function payload(string $o,string $p,array $input): array
    {
        $payload=[];
        foreach(['title'=>220,'location'=>500,'workDescription'=>4000,'hazards'=>4000,'controls'=>4000,'emergencyArrangements'=>4000] as $key=>$max)$payload[$key]=WorkPermitPolicy::text($input[$key]??null,$max);
        $payload['responsiblePersonId']=WorkPermitPolicy::id($input['responsiblePersonId']??null);
        $this->writer($this->lockedScope($o,$payload['responsiblePersonId'],$p));
        $payload['competenceEvidenceFileIds']=$this->evidence($o,$p,$input['competenceEvidenceFileIds']??null,true);
        $payload['evidenceFileIds']=$this->evidence($o,$p,$input['evidenceFileIds']??null,true);
        if(!is_bool($input['externalPermitRequired']??null))throw new WorkPermitException('work_permit_external_requirement_required');
        $payload['externalPermitRequired']=$input['externalPermitRequired'];
        $payload['externalPermitFileIds']=$this->evidence($o,$p,$input['externalPermitFileIds']??[],$payload['externalPermitRequired']);
        $payload['validFrom']=WorkPermitPolicy::date($input['validFrom']??null);$payload['validUntil']=WorkPermitPolicy::date($input['validUntil']??null);
        if($payload['validFrom']>=$payload['validUntil'])throw new WorkPermitException('work_permit_date_range_invalid');
        $payload['internalOnly']=true;$payload['statutoryApprovalClaimed']=false;
        return $payload;
    }
    /** Memberships (ordered by primary key), then permit, then receipts.
     * Lock the organisation set before discovering the responsible person from a permit.
     * Native membership UPDATE/DELETE takes the same InnoDB row lock; no mutator bypass.
     */
    private function lockedScope(string $o,string $u,string $p): string
    {
        WorkPermitPolicy::id($o);WorkPermitPolicy::id($u);WorkPermitPolicy::id($p);
        $s=$this->pdo->prepare('SELECT LOWER(HEX(user_id)) userId,role,status FROM organisation_memberships WHERE organisation_id=UNHEX(:o) ORDER BY id FOR UPDATE');
        $s->execute(['o'=>$o]);$role=null;
        foreach($s->fetchAll(PDO::FETCH_ASSOC) as $member)if($member['userId']===strtolower($u) && $member['status']==='active')$role=$member['role'];
        $s=$this->pdo->prepare('SELECT id FROM projects WHERE organisation_id=UNHEX(:o) AND id=UNHEX(:p)');
        $s->execute(['o'=>$o,'p'=>$p]);
        if(!is_string($role)||$s->fetchColumn()===false)throw new WorkPermitException('work_permit_project_not_found',404);
        return $role;
    }

    /** Validate the entire chain BEFORE any business state is consumed or resealed. */
    private function verifiedHistory(string $o,string $p,string $id,array $row): array
    {
        $s=$this->pdo->prepare('SELECT version,LOWER(HEX(actor_id)) actorId,action,reason,snapshot,sha256,created_at createdAt FROM work_permit_versions WHERE organisation_id=UNHEX(:o) AND project_id=UNHEX(:p) AND permit_id=UNHEX(:id) ORDER BY version FOR UPDATE');
        $s->execute(['o'=>$o,'p'=>$p,'id'=>$id]);$history=[];$expected=1;
        foreach($s->fetchAll(PDO::FETCH_ASSOC) as $event){
            try{$snapshot=json_decode($event['snapshot'],true,64,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new WorkPermitException('work_permit_integrity_failed',409);}
            if((int)$event['version']!==$expected || !hash_equals($event['sha256'],hash('sha256',$event['snapshot'])) || !is_array($snapshot)
                || ($snapshot['id']??null)!==$row['id'] || ($snapshot['organisationId']??null)!==$row['organisationId'] || ($snapshot['projectId']??null)!==$row['projectId']
                || ($snapshot['version']??null)!==$expected || ($snapshot['status']??null)!==$event['action'])throw new WorkPermitException('work_permit_integrity_failed',409);
            $event['version']=$expected++;$event['snapshot']=$snapshot;$history[]=$event;
        }
        $last=$history[array_key_last($history)]??null;
        if($last===null || $last['version']!==$row['version'] || $this->json($last['snapshot'])!==$this->json($row))throw new WorkPermitException('work_permit_integrity_failed',409);
        return $history;
    }

    private function scope(string $o,string $u,string $p): string
    {
        WorkPermitPolicy::id($o);WorkPermitPolicy::id($u);WorkPermitPolicy::id($p);
        $s=$this->pdo->prepare("SELECT m.role FROM projects p JOIN organisation_memberships m ON m.organisation_id=p.organisation_id AND m.user_id=UNHEX(:u) AND m.status='active' WHERE p.organisation_id=UNHEX(:o) AND p.id=UNHEX(:p)");
        $s->execute(['u'=>$u,'o'=>$o,'p'=>$p]);$role=$s->fetchColumn();
        if(!is_string($role))throw new WorkPermitException('work_permit_project_not_found',404);
        return $role;
    }
    private function writer(string $role): void{if(!in_array($role,['owner','admin','member'],true))throw new WorkPermitException('work_permit_write_denied',403);}
    private function confirmed(array $input): void{if(($input['humanConfirmed']??null)!==true)throw new WorkPermitException('work_permit_human_confirmation_required');}
    private function row(string $o,string $p,string $id,bool $lock=false): array
    {
        WorkPermitPolicy::id($id);
        $s=$this->pdo->prepare('SELECT LOWER(HEX(id)) id,LOWER(HEX(organisation_id)) organisationId,LOWER(HEX(project_id)) projectId,reference_code reference,status,payload,version,LOWER(HEX(created_by)) createdBy,LOWER(HEX(reviewed_by)) reviewedBy,LOWER(HEX(issued_by)) issuedBy,created_at createdAt,updated_at updatedAt FROM work_permits WHERE organisation_id=UNHEX(:o) AND project_id=UNHEX(:p) AND id=UNHEX(:id)'.($lock?' FOR UPDATE':''));
        $s->execute(['o'=>$o,'p'=>$p,'id'=>$id]);$row=$s->fetch(PDO::FETCH_ASSOC);
        if(!is_array($row))throw new WorkPermitException('work_permit_not_found',404);
        try{$row['payload']=json_decode($row['payload'],true,64,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new WorkPermitException('work_permit_integrity_failed',409);}
        if(!is_array($row['payload']))throw new WorkPermitException('work_permit_integrity_failed',409);
        $row['version']=(int)$row['version'];return $row;
    }
    private function evidence(string $o,string $p,mixed $ids,bool $required): array
    {
        if(!is_array($ids)||!array_is_list($ids)||count($ids)>50||($required && $ids===[]))throw new WorkPermitException('work_permit_evidence_required');
        $ids=array_values(array_unique(array_map(WorkPermitPolicy::id(...),$ids)));
        if($ids){$marks=implode(',',array_fill(0,count($ids),'UNHEX(?)'));$s=$this->pdo->prepare("SELECT COUNT(*) FROM stored_files WHERE organisation_id=UNHEX(?) AND project_id=UNHEX(?) AND id IN ($marks)");$s->execute([$o,$p,...$ids]);if((int)$s->fetchColumn()!==count($ids))throw new WorkPermitException('work_permit_evidence_not_found',404);}
        return $ids;
    }
    private function receipt(string $o,string $u,string $p,string $id,string $action,string $reason): void
    {
        $row=$this->row($o,$p,$id);$snapshot=$this->json($row);$hash=hash('sha256',$snapshot);
        $s=$this->pdo->prepare('INSERT INTO work_permit_versions(permit_id,organisation_id,project_id,version,actor_id,action,reason,snapshot,sha256) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:p),:v,UNHEX(:u),:action,:reason,:snapshot,:hash)');
        $s->execute(['id'=>$id,'o'=>$o,'p'=>$p,'v'=>$row['version'],'u'=>$u,'action'=>$action,'reason'=>$reason,'snapshot'=>$snapshot,'hash'=>$hash]);
        (new AuditService($this->pdo))->append($o,$u,'work_permit.'.$action,'work_permit',$id,['projectId'=>$p,'version'=>$row['version'],'reason'=>$reason,'snapshotSha256'=>$hash,'internalOnly'=>true,'statutoryApprovalClaimed'=>false]);
    }
    private function json(array $value): string{return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
}