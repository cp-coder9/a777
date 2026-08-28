<?php

declare(strict_types=1);

namespace Architex\Platform\Municipal;

use Architex\Platform\Audit\AuditService;
use Architex\Platform\Identity\Identifier;
use DateTimeImmutable;
use PDO;
use PDOException;
use Throwable;

final readonly class MunicipalRequirementRegistry
{
    private const CATEGORIES = ['property_and_municipal_facts','land_use_and_zoning','professional_team','nbr_sans_advisory_precheck','drawing_register','supporting_documents','professional_signoffs','client_authority'];

    public function __construct(private PDO $pdo) {}

    /** @return array<string,mixed> */
    public function ensureImplementationSeed(string $organisationId,string $userId,string $role,string $municipality): array
    {
        $this->assertGovernor($organisationId,$userId,$role);
        [$key,$name]=$this->municipality($municipality);$requirements=$this->seedRequirements();$json=$this->json($requirements);$id=Identifier::generate();
        $this->pdo->prepare("INSERT IGNORE INTO municipal_requirement_versions(id,organisation_id,municipality_key,municipality_name,version,status,source_reference,effective_date,requirements_payload,payload_sha256,created_by) VALUES(UNHEX(:id),UNHEX(:organisation_id),:municipality_key,:municipality_name,'implementation-seed-v0.2','review_required','Supplied generic implementation checklist with refuse evidence; current official municipality requirements must be reviewed','2026-08-28',:payload,:sha256,UNHEX(:user_id))")->execute(['id'=>$id,'organisation_id'=>$organisationId,'municipality_key'=>$key,'municipality_name'=>$name,'payload'=>$json,'sha256'=>hash('sha256',$json),'user_id'=>$userId]);
        $statement=$this->pdo->prepare("SELECT LOWER(HEX(id)) FROM municipal_requirement_versions WHERE organisation_id=UNHEX(:organisation_id) AND municipality_key=:municipality_key AND version='implementation-seed-v0.2'");$statement->execute(['organisation_id'=>$organisationId,'municipality_key'=>$key]);$stored=$statement->fetchColumn();
        if(!is_string($stored))throw new MunicipalReadinessException('municipal_matrix_seed_failed');return$this->get($organisationId,$stored);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createVersion(string $organisationId,string $userId,string $role,array $input): array
    {
        $this->assertGovernor($organisationId,$userId,$role);[$key,$name]=$this->municipality($input['municipality']??null);
        $version=$this->pattern($input['version']??null,'/\A[a-zA-Z0-9][a-zA-Z0-9._-]{1,99}\z/','invalid_municipal_matrix_version');if(str_starts_with($version,'implementation-seed'))throw new MunicipalReadinessException('reserved_municipal_matrix_version');
        $source=$this->text($input['sourceReference']??null,500,'invalid_municipal_source_reference');$effective=$this->date($input['effectiveDate']??null);$requirements=$input['requirements']??null;if(!is_array($requirements))throw new MunicipalReadinessException('invalid_municipal_requirements');$this->validate($requirements);$json=$this->json($requirements);$id=Identifier::generate();
        try{$this->pdo->prepare("INSERT INTO municipal_requirement_versions(id,organisation_id,municipality_key,municipality_name,version,status,source_reference,effective_date,requirements_payload,payload_sha256,created_by) VALUES(UNHEX(:id),UNHEX(:organisation_id),:key,:name,:version,'review_required',:source,:effective,:payload,:sha256,UNHEX(:user_id))")->execute(['id'=>$id,'organisation_id'=>$organisationId,'key'=>$key,'name'=>$name,'version'=>$version,'source'=>$source,'effective'=>$effective,'payload'=>$json,'sha256'=>hash('sha256',$json),'user_id'=>$userId]);}
        catch(PDOException$exception){if((string)$exception->getCode()==='23000')throw new MunicipalReadinessException('duplicate_municipal_matrix_version');throw$exception;}
        (new AuditService($this->pdo))->append($organisationId,$userId,'municipal_matrix.created','municipal_requirement_version',$id,['municipalityKey'=>$key,'version'=>$version,'payloadSha256'=>hash('sha256',$json),'status'=>'review_required']);return$this->get($organisationId,$id);
    }

    /** @return array<string,mixed> */
    public function verify(string $organisationId,string $userId,string $role,string $id,bool $confirmed,string $reason): array
    {
        $this->assertGovernor($organisationId,$userId,$role);if(!$confirmed)throw new MunicipalReadinessException('municipal_matrix_confirmation_required');$reason=$this->text($reason,500,'municipal_matrix_review_reason_required');$existing=$this->get($organisationId,$id);if($existing['status']!=='review_required')throw new MunicipalReadinessException('municipal_matrix_not_reviewable');if(str_starts_with((string)$existing['version'],'implementation-seed'))throw new MunicipalReadinessException('implementation_seed_not_verifiable');
        $this->pdo->beginTransaction();try{$this->pdo->prepare("UPDATE municipal_requirement_versions SET status='withdrawn' WHERE organisation_id=UNHEX(:organisation_id) AND municipality_key=:key AND status='verified' AND id<>UNHEX(:id)")->execute(['organisation_id'=>$organisationId,'key'=>$existing['municipalityKey'],'id'=>$id]);$statement=$this->pdo->prepare("UPDATE municipal_requirement_versions SET status='verified',reviewed_by=UNHEX(:user_id),reviewed_at=UTC_TIMESTAMP(6),review_reason=:reason WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation_id) AND status='review_required'");$statement->execute(['user_id'=>$userId,'reason'=>$reason,'id'=>$id,'organisation_id'=>$organisationId]);if($statement->rowCount()!==1)throw new MunicipalReadinessException('municipal_matrix_not_reviewable');(new AuditService($this->pdo))->append($organisationId,$userId,'municipal_matrix.verified','municipal_requirement_version',$id,['municipalityKey'=>$existing['municipalityKey'],'version'=>$existing['version'],'payloadSha256'=>$existing['payloadSha256'],'reason'=>$reason]);$this->pdo->commit();}catch(Throwable$exception){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$exception;}return$this->get($organisationId,$id);
    }

    /** @return array<string,mixed> */
    public function current(string $organisationId,string $municipality,bool $verifiedOnly=false): array
    {
        [$key]=$this->municipality($municipality);$status=$verifiedOnly?"status='verified'":"status IN ('verified','review_required')";$statement=$this->pdo->prepare("SELECT LOWER(HEX(id)) FROM municipal_requirement_versions WHERE organisation_id=UNHEX(:organisation_id) AND municipality_key=:key AND $status ORDER BY FIELD(status,'verified','review_required'),effective_date DESC,created_at DESC LIMIT 1");$statement->execute(['organisation_id'=>$organisationId,'key'=>$key]);$id=$statement->fetchColumn();if(!is_string($id))throw new MunicipalReadinessException('municipal_matrix_not_found');return$this->get($organisationId,$id);
    }

    /** @return list<array<string,mixed>> */
    public function list(string $organisationId,?string $municipality=null): array
    {
        $where='organisation_id=UNHEX(:organisation_id)';$params=['organisation_id'=>$organisationId];if($municipality!==null&&trim($municipality)!==''){[$key]=$this->municipality($municipality);$where.=' AND municipality_key=:key';$params['key']=$key;}$statement=$this->pdo->prepare("SELECT LOWER(HEX(id)) id,municipality_key,municipality_name,version,status,source_reference,effective_date,requirements_payload,payload_sha256,LOWER(HEX(created_by)) created_by,LOWER(HEX(reviewed_by)) reviewed_by,DATE_FORMAT(reviewed_at,'%Y-%m-%dT%H:%i:%s.%fZ') reviewed_at,review_reason,DATE_FORMAT(created_at,'%Y-%m-%dT%H:%i:%s.%fZ') created_at FROM municipal_requirement_versions WHERE $where ORDER BY municipality_key,FIELD(status,'verified','review_required','withdrawn'),effective_date DESC,created_at DESC");$statement->execute($params);$items=[];foreach($statement as$row)$items[]=$this->map($row);return$items;
    }

    /** @return array<string,mixed> */
    public function get(string $organisationId,string $id): array
    {
        if(preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new MunicipalReadinessException('municipal_matrix_not_found');$statement=$this->pdo->prepare("SELECT LOWER(HEX(id)) id,municipality_key,municipality_name,version,status,source_reference,effective_date,requirements_payload,payload_sha256,LOWER(HEX(created_by)) created_by,LOWER(HEX(reviewed_by)) reviewed_by,DATE_FORMAT(reviewed_at,'%Y-%m-%dT%H:%i:%s.%fZ') reviewed_at,review_reason,DATE_FORMAT(created_at,'%Y-%m-%dT%H:%i:%s.%fZ') created_at FROM municipal_requirement_versions WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation_id)");$statement->execute(['id'=>$id,'organisation_id'=>$organisationId]);$row=$statement->fetch();if(!is_array($row))throw new MunicipalReadinessException('municipal_matrix_not_found');return$this->map($row);
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function map(array$row):array{$requirements=json_decode((string)$row['requirements_payload'],true,64,JSON_THROW_ON_ERROR);$checksum=hash('sha256',$this->json($requirements));if(!hash_equals((string)$row['payload_sha256'],$checksum))throw new MunicipalReadinessException('municipal_matrix_integrity_failed');return['id'=>(string)$row['id'],'municipalityKey'=>(string)$row['municipality_key'],'municipalityName'=>(string)$row['municipality_name'],'version'=>(string)$row['version'],'status'=>(string)$row['status'],'assessmentApprovalEligible'=>$row['status']==='verified','sourceReference'=>(string)$row['source_reference'],'effectiveDate'=>(string)$row['effective_date'],'requirements'=>$requirements,'payloadSha256'=>(string)$row['payload_sha256'],'createdBy'=>(string)$row['created_by'],'reviewedBy'=>$row['reviewed_by'],'reviewedAt'=>$row['reviewed_at'],'reviewReason'=>$row['review_reason'],'createdAt'=>(string)$row['created_at']];}

    /** @param array<int,mixed> $requirements */
    private function validate(array$requirements):void{if(count($requirements)!==8)throw new MunicipalReadinessException('invalid_municipal_requirements');$seen=[];foreach($requirements as$category){if(!is_array($category))throw new MunicipalReadinessException('invalid_municipal_requirements');$key=$category['category']??null;$items=$category['items']??null;if(!is_string($key)||!in_array($key,self::CATEGORIES,true)||isset($seen[$key])||!is_array($items)||$items===[])throw new MunicipalReadinessException('invalid_municipal_requirements');$seen[$key]=true;foreach($items as$item)if(!is_array($item)||!is_string($item['id']??null)||!is_string($item['label']??null)||!is_string($item['owner']??null))throw new MunicipalReadinessException('invalid_municipal_requirements');}if(count($seen)!==8)throw new MunicipalReadinessException('invalid_municipal_requirements');}
    private function assertGovernor(string$o,string$u,string$r):void{if(!in_array($r,['owner','admin'],true))throw new MunicipalReadinessException('municipal_matrix_role_denied');$s=$this->pdo->prepare("SELECT COUNT(*) FROM organisation_memberships WHERE organisation_id=UNHEX(:o) AND user_id=UNHEX(:u) AND role=:r AND status='active'");$s->execute(['o'=>$o,'u'=>$u,'r'=>$r]);if((int)$s->fetchColumn()!==1)throw new MunicipalReadinessException('membership_not_found');}
    /** @return array{string,string} */private function municipality(mixed$v):array{$name=$this->text($v,180,'invalid_municipality');$key=strtolower(trim((string)preg_replace('/[^a-z0-9]+/i','-',$name),'-'));if($key===''||strlen($key)>120)throw new MunicipalReadinessException('invalid_municipality');return[$key,$name];}
    private function pattern(mixed$v,string$p,string$e):string{if(!is_string($v)||preg_match($p,$v)!==1)throw new MunicipalReadinessException($e);return$v;}
    private function text(mixed$v,int$m,string$e):string{$v=is_string($v)?trim($v):'';if($v===''||mb_strlen($v)>$m)throw new MunicipalReadinessException($e);return$v;}
    private function date(mixed$v):string{if(!is_string($v))throw new MunicipalReadinessException('invalid_effective_date');$d=DateTimeImmutable::createFromFormat('!Y-m-d',$v);if(!$d||$d->format('Y-m-d')!==$v)throw new MunicipalReadinessException('invalid_effective_date');return$v;}
    /** @param array<mixed> $v */private function json(array$v):string{return json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}

    /** @return list<array{category:string,items:list<array{id:string,label:string,owner:string}>}> */
    private function seedRequirements():array{return[
        ['category'=>'property_and_municipal_facts','items'=>[['id'=>'municipality','label'=>'Municipality captured','owner'=>'municipal_coordinator'],['id'=>'property_reference','label'=>'Erf or property reference captured','owner'=>'client']]],
        ['category'=>'land_use_and_zoning','items'=>[['id'=>'zoning_confirmed','label'=>'Zoning and land-use confirmed','owner'=>'town_planner'],['id'=>'coverage_parking_resolved','label'=>'Coverage, FAR and parking risks resolved','owner'=>'town_planner']]],
        ['category'=>'professional_team','items'=>[['id'=>'required_team_routed','label'=>'Every triggered discipline is appointed or routed','owner'=>'lead_professional']]],
        ['category'=>'nbr_sans_advisory_precheck','items'=>[['id'=>'nbr_precheck','label'=>'NBR/SANS advisory pre-check reviewed','owner'=>'lead_professional'],['id'=>'energy_precheck','label'=>'SANS 10400-XA evidence reviewed when triggered','owner'=>'energy_consultant'],['id'=>'refuse_precheck','label'=>'Municipal refuse storage evidence reviewed','owner'=>'architect_or_civil_engineer']]],
        ['category'=>'drawing_register','items'=>[['id'=>'site_plan','label'=>'Issued municipal site plan','owner'=>'architect'],['id'=>'floor_plan','label'=>'Issued municipal floor plans','owner'=>'architect'],['id'=>'elevation','label'=>'Issued municipal elevations','owner'=>'architect'],['id'=>'section','label'=>'Issued municipal sections','owner'=>'architect']]],
        ['category'=>'supporting_documents','items'=>[['id'=>'title_deed','label'=>'Title deed available','owner'=>'client'],['id'=>'sg_diagram','label'=>'SG diagram or boundary evidence available','owner'=>'land_surveyor'],['id'=>'municipal_form','label'=>'Municipal application form approved or issued','owner'=>'municipal_coordinator']]],
        ['category'=>'professional_signoffs','items'=>[['id'=>'lead_signoff','label'=>'Lead professional review recorded','owner'=>'lead_professional'],['id'=>'discipline_signoffs','label'=>'Triggered discipline reviews recorded','owner'=>'lead_professional']]],
        ['category'=>'client_authority','items'=>[['id'=>'client_authority','label'=>'Owner consent or client authority available','owner'=>'client']]],
    ];}
}
