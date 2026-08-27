<?php

declare(strict_types=1);

namespace Architex\Platform\Toolbox;

use Architex\Platform\Audit\AuditService;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Project\ProjectContext;
use Architex\Platform\Project\ProjectPhase;
use Architex\Platform\Records\ProjectRecordService;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

final readonly class ToolboxEngine
{
    public function __construct(private PDO $pdo,private ToolDefinitionRegistry $registry){}

    /** @param array<string,mixed> $input @param array<string,mixed> $assignment @return array<string,mixed> */
    public function run(string $organisationId,string $userId,string $role,string $toolId,array $input,array $assignment,bool $issueImmediately,?string $issueReason=null,?string $supersedesRunId=null):array
    {
        $this->assertMember($organisationId,$userId);$definition=$this->registry->required($toolId);
        if(!in_array($role,$definition->roles,true))throw new ToolException('tool_role_denied');
        $assignment=$this->assignment($organisationId,$assignment);
        if($issueImmediately&&$definition->requiresProfessionalConfirmation&&trim((string)$issueReason)==='')throw new ToolException('professional_confirmation_required');
        $id=Identifier::generate();$createdAt=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z');
        try{$output=($definition->execute)($input,['organisationId'=>$organisationId,'userId'=>$userId,'role'=>$role]);if(!is_array($output))throw new ToolException('invalid_tool_output');}
        catch(Throwable $exception){$this->storeFailure($id,$organisationId,$userId,$role,$definition,$assignment,$input,$exception);throw $exception;}
        $status=$issueImmediately?'issued':'completed';$issuedAt=$issueImmediately?$createdAt:null;
        $snapshot=$issueImmediately?['organisationId'=>$organisationId,'userId'=>$userId,'toolId'=>$definition->id,'toolVersion'=>$definition->version,'role'=>$role,'assignment'=>$assignment,'input'=>$input,'output'=>$output,'issuedAt'=>$issuedAt,'reason'=>trim((string)$issueReason)]:null;
        $snapshotJson=$snapshot===null?null:$this->json($snapshot);$hash=$snapshotJson===null?null:hash('sha256',$snapshotJson);
        $this->pdo->beginTransaction();
        try{
            $this->pdo->prepare('INSERT INTO tool_runs(id,organisation_id,user_id,tool_id,tool_version,executed_role,assignment_mode,project_id,project_name,external_reference,assignment_notes,status,input_payload,output_payload,snapshot_payload,audit_hash,audit_reason,issued_at,supersedes_run_id) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),:tool_id,:tool_version,:role,:assignment_mode,UNHEX(:project_id),:project_name,:external_reference,:notes,:status,:input,:output,:snapshot,:hash,:reason,:issued_at,UNHEX(:supersedes))')->execute(['id'=>$id,'organisation_id'=>$organisationId,'user_id'=>$userId,'tool_id'=>$definition->id,'tool_version'=>$definition->version,'role'=>$role,'assignment_mode'=>$assignment['mode'],'project_id'=>$assignment['projectId']??null,'project_name'=>$assignment['projectName']??null,'external_reference'=>$assignment['externalReference']??null,'notes'=>$assignment['notes']??null,'status'=>$status,'input'=>$this->json($input),'output'=>$this->json($output),'snapshot'=>$snapshotJson,'hash'=>$hash,'reason'=>$issueReason,'issued_at'=>$issuedAt===null?null:str_replace(['T','Z'],[' ',''],$issuedAt),'supersedes'=>$supersedesRunId]);
            $run=['id'=>$id,'toolId'=>$definition->id,'toolName'=>$definition->name,'toolVersion'=>$definition->version,'role'=>$role,'assignment'=>$assignment,'status'=>$status,'input'=>$input,'output'=>$output,'auditHash'=>$hash,'auditReason'=>$issueReason,'createdAt'=>$createdAt,'issuedAt'=>$issuedAt,'supersedesRunId'=>$supersedesRunId];
            $this->storeExports($organisationId,$run);
            (new AuditService($this->pdo))->append($organisationId,$userId,'tool_run.'.$status,'tool_run',$id,['toolId'=>$definition->id,'toolVersion'=>$definition->version,'assignment'=>$assignment,'auditHash'=>$hash,'advisoryOnly'=>(bool)($output['advisoryOnly']??false)]);
            if($issueImmediately&&($assignment['mode']??'')==='internal_project'&&in_array('ProjectRecord',$definition->downstreamWriteBack,true)){
                (new ProjectRecordService($this->pdo))->issue(new ProjectContext($organisationId,$userId,$assignment['projectId'],$role),ProjectPhase::Onboarding,'toolbox','tool_run',$definition->name.' issued result','issued',['toolRunId'=>$id,'toolId'=>$definition->id,'toolVersion'=>$definition->version,'auditHash'=>$hash,'output'=>$output],['required'=>true,'approvedBy'=>$userId]);
            }
            $this->pdo->commit();return$run;
        }catch(Throwable $exception){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $exception;}
    }

    /** @param array<string,mixed> $input @param array<string,mixed> $assignment @return array<string,mixed> */
    public function revise(string $organisationId,string $userId,string $role,string $issuedRunId,array $input,array $assignment,string $reason):array
    {
        $existing=$this->get($organisationId,$userId,$issuedRunId);if($existing['status']!=='issued')throw new ToolException('run_not_revisable');
        $revision=$this->run($organisationId,$userId,$role,$existing['toolId'],$input,$assignment,true,$reason,$issuedRunId);
        $updated=$this->pdo->prepare("UPDATE tool_runs SET status='superseded' WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation_id) AND user_id=UNHEX(:user_id) AND status='issued'");$updated->execute(['id'=>$issuedRunId,'organisation_id'=>$organisationId,'user_id'=>$userId]);if($updated->rowCount()!==1)throw new ToolException('run_not_revisable');
        (new AuditService($this->pdo))->append($organisationId,$userId,'tool_run.superseded','tool_run',$issuedRunId,['supersededBy'=>$revision['id']]);return$revision;
    }

    /** @return array<string,mixed> */
    public function get(string $organisationId,string $userId,string $runId):array
    {
        $statement=$this->pdo->prepare('SELECT LOWER(HEX(id)) id,tool_id,tool_version,executed_role,assignment_mode,LOWER(HEX(project_id)) project_id,project_name,external_reference,assignment_notes,status,input_payload,output_payload,audit_hash,audit_reason,DATE_FORMAT(created_at,\'%Y-%m-%dT%H:%i:%s.%fZ\') created_at,DATE_FORMAT(issued_at,\'%Y-%m-%dT%H:%i:%s.%fZ\') issued_at,LOWER(HEX(supersedes_run_id)) supersedes_run_id FROM tool_runs WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation_id) AND user_id=UNHEX(:user_id)');$statement->execute(['id'=>$runId,'organisation_id'=>$organisationId,'user_id'=>$userId]);$row=$statement->fetch();if(!is_array($row))throw new ToolException('tool_run_not_found');return$this->map($row);
    }

    /** @return list<array<string,mixed>> */
    public function history(string $organisationId,string $userId,int $limit=20):array
    {
        $limit=max(1,min(100,$limit));$statement=$this->pdo->prepare("SELECT LOWER(HEX(id)) id,tool_id,tool_version,executed_role,assignment_mode,LOWER(HEX(project_id)) project_id,project_name,external_reference,assignment_notes,status,input_payload,output_payload,audit_hash,audit_reason,DATE_FORMAT(created_at,'%Y-%m-%dT%H:%i:%s.%fZ') created_at,DATE_FORMAT(issued_at,'%Y-%m-%dT%H:%i:%s.%fZ') issued_at,LOWER(HEX(supersedes_run_id)) supersedes_run_id FROM tool_runs WHERE organisation_id=UNHEX(:organisation_id) AND user_id=UNHEX(:user_id) ORDER BY created_at DESC,id DESC LIMIT {$limit}");$statement->execute(['organisation_id'=>$organisationId,'user_id'=>$userId]);$runs=[];foreach($statement as $row)$runs[]=$this->map($row);return$runs;
    }

    public function runCount(string $organisationId,string $userId,string $toolId):int{$statement=$this->pdo->prepare('SELECT COUNT(*) FROM tool_runs WHERE organisation_id=UNHEX(:organisation_id) AND user_id=UNHEX(:user_id) AND tool_id=:tool_id');$statement->execute(['organisation_id'=>$organisationId,'user_id'=>$userId,'tool_id'=>$toolId]);return(int)$statement->fetchColumn();}

    /** @return list<array<string,mixed>> */
    public function exports(string $organisationId,string $userId,string $runId):array
    {
        $this->get($organisationId,$userId,$runId);$statement=$this->pdo->prepare('SELECT LOWER(HEX(e.id)) id,e.format,e.filename,e.mime_type,e.sha256,e.created_at FROM tool_run_exports e WHERE e.organisation_id=UNHEX(:organisation_id) AND e.run_id=UNHEX(:run_id) ORDER BY FIELD(e.format,\'json\',\'csv\',\'html\')');$statement->execute(['organisation_id'=>$organisationId,'run_id'=>$runId]);return$statement->fetchAll();
    }

    /** @return array{filename:string,mimeType:string,byteSize:int,sha256:string,content:string} */
    public function export(string $organisationId,string $userId,string $exportId):array{$statement=$this->pdo->prepare('SELECT e.filename,e.mime_type,e.content,e.sha256 FROM tool_run_exports e JOIN tool_runs r ON r.id=e.run_id AND r.organisation_id=e.organisation_id AND r.user_id=UNHEX(:user_id) WHERE e.id=UNHEX(:id) AND e.organisation_id=UNHEX(:organisation_id)');$statement->execute(['user_id'=>$userId,'id'=>$exportId,'organisation_id'=>$organisationId]);$row=$statement->fetch();if(!is_array($row))throw new ToolException('tool_export_not_found');$content=(string)$row['content'];if(!hash_equals((string)$row['sha256'],hash('sha256',$content)))throw new ToolException('tool_export_integrity_failed');return['filename'=>(string)$row['filename'],'mimeType'=>(string)$row['mime_type'],'byteSize'=>strlen($content),'sha256'=>(string)$row['sha256'],'content'=>$content];}

    /** @param array<string,mixed> $assignment @return array<string,mixed> */
    private function assignment(string $organisationId,array $assignment):array
    {
        $mode=$assignment['mode']??null;if(!in_array($mode,['none','internal_project','external_reference'],true))throw new ToolException('invalid_tool_assignment');
        if($mode==='none')return['mode'=>'none'];
        if($mode==='internal_project'){$projectId=$assignment['projectId']??null;if(!is_string($projectId)||preg_match('/\A[a-f0-9]{32}\z/i',$projectId)!==1)throw new ToolException('invalid_tool_assignment');$statement=$this->pdo->prepare('SELECT project_name FROM projects WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation_id)');$statement->execute(['id'=>$projectId,'organisation_id'=>$organisationId]);$name=$statement->fetchColumn();if(!is_string($name))throw new ToolException('project_not_found');return['mode'=>$mode,'projectId'=>strtolower($projectId),'projectName'=>$name,'notes'=>$this->optional($assignment['notes']??null,1000)];}
        $name=$this->optional($assignment['projectName']??null,180);$reference=$this->optional($assignment['externalReference']??null,180);if($name===null&&$reference===null)throw new ToolException('invalid_tool_assignment');return['mode'=>$mode,'projectName'=>$name,'externalReference'=>$reference,'notes'=>$this->optional($assignment['notes']??null,1000)];
    }

    /** @param array<string,mixed> $run */
    private function storeExports(string $organisationId,array $run):void
    {
        $json=json_encode($run,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$rows=[['json','application/json',$json],['csv','text/csv',$this->csv($run)],['html','text/html',$this->html($run)]];
        $statement=$this->pdo->prepare('INSERT INTO tool_run_exports(id,organisation_id,run_id,format,filename,mime_type,content,sha256) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:run_id),:format,:filename,:mime,:content,:sha256)');foreach($rows as[$format,$mime,$content])$statement->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'run_id'=>$run['id'],'format'=>$format,'filename'=>$run['toolId'].'-'.$run['id'].'.'.$format,'mime'=>$mime,'content'=>$content,'sha256'=>hash('sha256',$content)]);
    }

    /** @param array<string,mixed> $run */
    private function csv(array $run):string{$rows=[['field','value'],['runId',$run['id']],['toolId',$run['toolId']],['toolVersion',$run['toolVersion']],['userRole',$run['role']],['status',$run['status']],['createdAt',$run['createdAt']],['input',$this->json($run['input'])],['output',$this->json($run['output'])],['auditHash',(string)($run['auditHash']??'')]];return implode("\n",array_map(static fn(array $row):string=>implode(',',array_map(static fn(string $cell):string=>'"'.str_replace('"','""',$cell).'"',$row)),$rows));}
    /** @param array<string,mixed> $run */
    private function html(array $run):string{$title=htmlspecialchars($run['toolName'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$body=htmlspecialchars(json_encode(['assignment'=>$run['assignment'],'input'=>$run['input'],'output'=>$run['output']],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');return'<!doctype html><html><head><meta charset="utf-8"><title>'.$title.'</title><style>body{font-family:Arial,sans-serif;margin:32px;color:#102033}.card{border:1px solid #ccd8d6;border-radius:12px;padding:16px;margin:12px 0}pre{white-space:pre-wrap;background:#f5faf9;padding:12px}</style></head><body><h1>'.$title.'</h1><div class="card"><b>Run:</b> '.htmlspecialchars($run['id']).'<br><b>Version:</b> '.htmlspecialchars($run['toolVersion']).'<br><b>User role:</b> '.htmlspecialchars($run['role']).'<br><b>Timestamp:</b> '.htmlspecialchars($run['createdAt']).'<br><b>Status:</b> '.htmlspecialchars($run['status']).'<br><b>SHA-256 snapshot:</b> '.htmlspecialchars((string)($run['auditHash']??'not issued')).'</div><div class="card"><pre>'.$body.'</pre></div></body></html>';}

    /** @return array<string,mixed> */
    private function map(array $row):array{return['id'=>$row['id'],'toolId'=>$row['tool_id'],'toolVersion'=>$row['tool_version'],'role'=>$row['executed_role'],'assignment'=>array_filter(['mode'=>$row['assignment_mode'],'projectId'=>$row['project_id'],'projectName'=>$row['project_name'],'externalReference'=>$row['external_reference'],'notes'=>$row['assignment_notes']],static fn(mixed $value):bool=>$value!==null),'status'=>$row['status'],'input'=>json_decode($row['input_payload'],true,32,JSON_THROW_ON_ERROR),'output'=>is_string($row['output_payload'])?json_decode($row['output_payload'],true,32,JSON_THROW_ON_ERROR):null,'auditHash'=>$row['audit_hash'],'auditReason'=>$row['audit_reason'],'createdAt'=>$row['created_at'],'issuedAt'=>$row['issued_at'],'supersedesRunId'=>$row['supersedes_run_id']];}
    private function assertMember(string $organisationId,string $userId):void{$statement=$this->pdo->prepare("SELECT COUNT(*) FROM organisation_memberships WHERE organisation_id=UNHEX(:organisation_id) AND user_id=UNHEX(:user_id) AND status='active'");$statement->execute(['organisation_id'=>$organisationId,'user_id'=>$userId]);if((int)$statement->fetchColumn()!==1)throw new ToolException('membership_not_found');}
    /** @param array<string,mixed> $assignment @param array<string,mixed> $input */
    private function storeFailure(string $id,string $organisationId,string $userId,string $role,ToolDefinition $definition,array $assignment,array $input,Throwable $exception):void{$this->pdo->prepare('INSERT INTO tool_runs(id,organisation_id,user_id,tool_id,tool_version,executed_role,assignment_mode,project_id,project_name,external_reference,assignment_notes,status,input_payload,error_code) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),:tool_id,:version,:role,:mode,UNHEX(:project_id),:project_name,:external_reference,:notes,\'failed\',:input,:error)')->execute(['id'=>$id,'organisation_id'=>$organisationId,'user_id'=>$userId,'tool_id'=>$definition->id,'version'=>$definition->version,'role'=>$role,'mode'=>$assignment['mode'],'project_id'=>$assignment['projectId']??null,'project_name'=>$assignment['projectName']??null,'external_reference'=>$assignment['externalReference']??null,'notes'=>$assignment['notes']??null,'input'=>$this->json($input),'error'=>$exception instanceof ToolException?$exception->reason:'tool_execution_failed']);}
    /** @param array<string,mixed> $value */
    private function json(array $value):string{return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION);}
    private function optional(mixed $value,int $max):?string{if($value===null||$value==='')return null;if(!is_string($value))throw new ToolException('invalid_tool_assignment');$value=trim($value);if($value===''||mb_strlen($value)>$max)throw new ToolException('invalid_tool_assignment');return$value;}
}
