<?php

declare(strict_types=1);

namespace Architex\Platform\Knowledge;

use Architex\Platform\Audit\AuditService;
use Architex\Platform\Identity\Identifier;
use PDO;
use Throwable;

final readonly class KnowledgeService
{
    private const CATEGORIES = ['nbr_sans','municipal_guideline','land_use_scheme','professional_body_rule','template_clause','practice_note','case_study'];

    public function __construct(private PDO $pdo) {}

    /** @return array<string,mixed> */
    public function workspace(string $organisationId,string $userId,string $role,?string $projectId,?string $query):array
    {
        $this->membership($organisationId,$userId,$role);
        if($projectId!==null)$this->project($organisationId,$userId,$projectId);
        $governor=in_array($role,['owner','admin'],true);
        $sql="SELECT LOWER(HEX(k.id)) id,LOWER(HEX(k.project_id)) project_id,k.permission_key,k.source_type,g.source_category,g.source_key,g.source_version,k.source_reference,k.route_target,k.title,LEFT(k.content,2400) content,k.content_sha256,k.status,g.review_status,g.review_reason,LOWER(HEX(k.created_by)) created_by,LOWER(HEX(g.reviewed_by)) reviewed_by,k.effective_at,k.expires_at,g.record_version,k.created_at,k.updated_at FROM wingman_knowledge_chunks k JOIN knowledge_source_governance g ON g.knowledge_chunk_id=k.id AND g.organisation_id=k.organisation_id WHERE k.organisation_id=UNHEX(:o) AND (k.project_id IS NULL".($projectId===null?")":" OR k.project_id=UNHEX(:p))").($governor?'':" AND g.review_status='approved' AND k.status='active'");
        $params=['o'=>$organisationId];if($projectId!==null)$params['p']=$projectId;
        if($query!==null&&trim($query)!==''){$sql.=' AND (k.title LIKE :query_title OR k.content LIKE :query_content OR k.source_reference LIKE :query_reference)';$like='%'.mb_substr(trim($query),0,180).'%';$params['query_title']=$like;$params['query_content']=$like;$params['query_reference']=$like;}
        $sql.=' ORDER BY g.review_status,k.updated_at DESC LIMIT 100';$s=$this->pdo->prepare($sql);$s->execute($params);
        return['items'=>array_map($this->map(...),$s->fetchAll()),'categories'=>self::CATEGORIES,'canGovern'=>$governor,'boundaries'=>['citationRequired'=>true,'humanReviewRequired'=>true,'aiMayApprove'=>false,'privateProjectDataPublic'=>false]];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function create(string$o,string$u,string$r,array$input):array
    {
        $this->governor($o,$u,$r);$this->confirmed($input);
        $category=$this->choice($input['sourceCategory']??null,self::CATEGORIES,'knowledge_category_invalid');
        $key=$this->key($input['sourceKey']??null);$title=$this->text($input['title']??null,220,'knowledge_title_required');$reference=$this->text($input['sourceReference']??null,500,'knowledge_source_reference_required');$content=$this->text($input['content']??null,80_000,'knowledge_content_required');
        $project=$this->optionalId($input['projectId']??null,'knowledge_project_invalid');if($project!==null)$this->project($o,$u,$project);
        $permission=$this->text($input['permissionKey']??'projects.read',120,'knowledge_permission_invalid');
        $version=(int)$this->scalar('SELECT COALESCE(MAX(source_version),0)+1 FROM knowledge_source_governance WHERE organisation_id=UNHEX(:o) AND source_key=:k',['o'=>$o,'k'=>$key]);$id=Identifier::generate();$sha=hash('sha256',$content);
        $this->pdo->beginTransaction();try{$this->pdo->prepare("INSERT INTO wingman_knowledge_chunks(id,organisation_id,project_id,permission_key,source_type,source_id,source_reference,route_target,title,content,content_sha256,status,effective_at,expires_at,created_by) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:p),:permission,'knowledge',:source_id,:reference,:route,:title,:content,:sha,'active',:effective,:expires,UNHEX(:u))")->execute(['id'=>$id,'o'=>$o,'p'=>$project,'permission'=>$permission,'source_id'=>$key,'reference'=>$reference,'route'=>$input['routeTarget']??'/knowledge','title'=>$title,'content'=>$content,'sha'=>$sha,'effective'=>$this->date($input['effectiveAt']??null),'expires'=>$this->date($input['expiresAt']??null),'u'=>$u]);$this->pdo->prepare("INSERT INTO knowledge_source_governance(knowledge_chunk_id,organisation_id,source_category,source_key,source_version,review_status) VALUES(UNHEX(:id),UNHEX(:o),:category,:source_key,:source_version,'review_required')")->execute(['id'=>$id,'o'=>$o,'category'=>$category,'source_key'=>$key,'source_version'=>$version]);(new AuditService($this->pdo))->append($o,$u,'knowledge.source_created','knowledge_source',$id,['sourceKey'=>$key,'sourceVersion'=>$version,'reviewStatus'=>'review_required','humanConfirmed'=>true]);$this->pdo->commit();}catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw new KnowledgeException(str_contains($e->getMessage(),'uq_wingman_knowledge_sha')?'knowledge_content_exists':'knowledge_write_failed',$e);}return$this->item($o,$id);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function review(string$o,string$u,string$r,string$id,array$input):array
    {
        $this->governor($o,$u,$r);$this->confirmed($input);$decision=$this->choice($input['decision']??null,['approved','rejected'],'knowledge_review_decision_invalid');$reason=$this->text($input['reason']??null,1000,'knowledge_review_reason_required');$expected=$input['expectedVersion']??null;if(!is_int($expected)||$expected<1)throw new KnowledgeException('knowledge_version_required');$row=$this->row($o,$id);if((int)$row['record_version']!==$expected)throw new KnowledgeException('knowledge_version_conflict');if($row['review_status']!=='review_required')throw new KnowledgeException('knowledge_review_immutable');if(strtolower((string)$row['created_by'])===$u)throw new KnowledgeException('knowledge_independent_review_required');
        $this->pdo->beginTransaction();try{if($decision==='approved')$this->pdo->prepare("UPDATE wingman_knowledge_chunks k JOIN knowledge_source_governance g ON g.knowledge_chunk_id=k.id SET k.status='superseded',g.record_version=g.record_version+1 WHERE k.organisation_id=UNHEX(:o) AND g.source_key=:k AND g.review_status='approved' AND k.status='active' AND k.id<>UNHEX(:id)")->execute(['o'=>$o,'k'=>$row['source_key'],'id'=>$id]);$s=$this->pdo->prepare('UPDATE knowledge_source_governance SET review_status=:decision,review_reason=:reason,reviewed_by=UNHEX(:u),reviewed_at=UTC_TIMESTAMP(6),record_version=record_version+1 WHERE organisation_id=UNHEX(:o) AND knowledge_chunk_id=UNHEX(:id) AND record_version=:version');$s->execute(['decision'=>$decision,'reason'=>$reason,'u'=>$u,'o'=>$o,'id'=>$id,'version'=>$expected]);if($s->rowCount()!==1)throw new KnowledgeException('knowledge_version_conflict');(new AuditService($this->pdo))->append($o,$u,'knowledge.source_reviewed','knowledge_source',$id,['decision'=>$decision,'reason'=>$reason,'humanConfirmed'=>true,'aiMayApprove'=>false]);$this->pdo->commit();}catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();if($e instanceof KnowledgeException)throw$e;throw new KnowledgeException('knowledge_write_failed',$e);}return$this->item($o,$id);
    }

    private function membership(string$o,string$u,string$r):void{$actual=$this->scalar("SELECT role FROM organisation_memberships WHERE organisation_id=UNHEX(:o) AND user_id=UNHEX(:u) AND status='active'",['o'=>$o,'u'=>$u]);if($actual!==$r)throw new KnowledgeException('knowledge_permission_denied');}
    private function governor(string$o,string$u,string$r):void{$this->membership($o,$u,$r);if(!in_array($r,['owner','admin'],true))throw new KnowledgeException('knowledge_governance_forbidden');}
    private function project(string$o,string$u,string$p):void{if($this->scalar("SELECT 1 FROM projects p JOIN organisation_memberships m ON m.organisation_id=p.organisation_id AND m.user_id=UNHEX(:u) AND m.status='active' WHERE p.organisation_id=UNHEX(:o) AND p.id=UNHEX(:p)",['o'=>$o,'u'=>$u,'p'=>$p])===false)throw new KnowledgeException('knowledge_project_not_found');}
    /** @return array<string,mixed> */private function row(string$o,string$id):array{$id=$this->id($id,'knowledge_source_not_found');$s=$this->pdo->prepare('SELECT k.*,g.*,LOWER(HEX(k.created_by)) created_by FROM wingman_knowledge_chunks k JOIN knowledge_source_governance g ON g.knowledge_chunk_id=k.id AND g.organisation_id=k.organisation_id WHERE k.organisation_id=UNHEX(:o) AND k.id=UNHEX(:id)');$s->execute(['o'=>$o,'id'=>$id]);$row=$s->fetch();if(!is_array($row))throw new KnowledgeException('knowledge_source_not_found');return$row;}
    /** @return array<string,mixed> */private function item(string$o,string$id):array{$s=$this->pdo->prepare('SELECT LOWER(HEX(k.id)) id,LOWER(HEX(k.project_id)) project_id,k.permission_key,k.source_type,g.source_category,g.source_key,g.source_version,k.source_reference,k.route_target,k.title,k.content,k.content_sha256,k.status,g.review_status,g.review_reason,LOWER(HEX(k.created_by)) created_by,LOWER(HEX(g.reviewed_by)) reviewed_by,k.effective_at,k.expires_at,g.record_version,k.created_at,k.updated_at FROM wingman_knowledge_chunks k JOIN knowledge_source_governance g ON g.knowledge_chunk_id=k.id AND g.organisation_id=k.organisation_id WHERE k.organisation_id=UNHEX(:o) AND k.id=UNHEX(:id)');$s->execute(['o'=>$o,'id'=>$id]);$row=$s->fetch();if(!is_array($row))throw new KnowledgeException('knowledge_source_not_found');return$this->map($row);}
    /** @param array<string,mixed> $r @return array<string,mixed> */private function map(array$r):array{return['id'=>$r['id'],'projectId'=>$r['project_id']?:null,'permissionKey'=>$r['permission_key'],'sourceType'=>$r['source_type'],'sourceCategory'=>$r['source_category'],'sourceKey'=>$r['source_key'],'sourceVersion'=>(int)$r['source_version'],'sourceReference'=>$r['source_reference'],'routeTarget'=>$r['route_target'],'title'=>$r['title'],'content'=>$r['content'],'contentSha256'=>$r['content_sha256'],'status'=>$r['status'],'reviewStatus'=>$r['review_status'],'reviewReason'=>$r['review_reason'],'createdBy'=>$r['created_by'],'reviewedBy'=>$r['reviewed_by']?:null,'effectiveAt'=>$r['effective_at'],'expiresAt'=>$r['expires_at'],'recordVersion'=>(int)$r['record_version'],'createdAt'=>$r['created_at'],'updatedAt'=>$r['updated_at']];}
    private function scalar(string$sql,array$params):mixed{$s=$this->pdo->prepare($sql);$s->execute($params);return$s->fetchColumn();}
    private function confirmed(array$i):void{if(($i['humanConfirmed']??false)!==true)throw new KnowledgeException('knowledge_human_confirmation_required');}
    private function key(mixed$v):string{$v=$this->text($v,160,'knowledge_source_key_invalid');if(preg_match('/\A[a-z][a-z0-9_.-]{1,159}\z/D',$v)!==1)throw new KnowledgeException('knowledge_source_key_invalid');return$v;}
    private function choice(mixed$v,array$a,string$e):string{if(!is_string($v)||!in_array($v,$a,true))throw new KnowledgeException($e);return$v;}
    private function text(mixed$v,int$m,string$e):string{$v=is_string($v)?trim($v):'';if($v===''||mb_strlen($v)>$m)throw new KnowledgeException($e);return$v;}
    private function id(mixed$v,string$e):string{if(!is_string($v)||preg_match('/\A[a-f0-9]{32}\z/i',$v)!==1)throw new KnowledgeException($e);return strtolower($v);}
    private function optionalId(mixed$v,string$e):?string{return$v===null||$v===''?null:$this->id($v,$e);}
    private function date(mixed$v):?string{if($v===null||$v==='')return null;if(!is_string($v)||strtotime($v)===false)throw new KnowledgeException('knowledge_date_invalid');return date('Y-m-d H:i:s',strtotime($v));}
}
