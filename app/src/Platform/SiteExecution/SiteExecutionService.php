<?php

declare(strict_types=1);

namespace Architex\Platform\SiteExecution;

use Architex\Platform\Audit\AuditService;
use Architex\Platform\Identity\Identifier;
use DateTimeImmutable;
use PDO;
use PDOException;
use Throwable;

final readonly class SiteExecutionService
{
    private const MODULE = 'site_execution_field_control';
    private const SEVERITIES = ['low','medium','high','critical'];

    public function __construct(private PDO $pdo) {}

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createDailyLog(string $organisationId, string $userId, string $role, string $projectId, array $input): array
    {
        $this->assertProject($organisationId, $userId, $projectId);
        $id = Identifier::generate();
        $date = $this->date($input['logDate'] ?? null, 'site_daily_log_date_invalid');
        $status = ($input['humanConfirmed'] ?? false) === true ? 'submitted' : 'draft';
        $payload = [
            'weather' => $this->text($input['weather'] ?? null, 1000, 'site_daily_log_weather_required'),
            'labour' => $this->labour($input['labour'] ?? null),
            'plant' => $this->strings($input['plant'] ?? [], 100, 180, 'site_daily_log_plant_invalid'),
            'deliveries' => $this->strings($input['deliveries'] ?? [], 100, 500, 'site_daily_log_deliveries_invalid'),
            'visitors' => $this->strings($input['visitors'] ?? [], 100, 220, 'site_daily_log_visitors_invalid'),
            'delays' => $this->strings($input['delays'] ?? [], 100, 1000, 'site_daily_log_delays_invalid'),
            'safetyNotes' => $this->strings($input['safetyNotes'] ?? [], 100, 1000, 'site_daily_log_safety_invalid'),
        ];
        // One log per project per date: report a repeat as the handled conflict the controller maps
        // to 409 (a reason ending in _exists) instead of letting the unique key surface as a 500.
        $this->assertDailyLogAbsent($organisationId, $projectId, $date);
        return $this->transaction(function () use ($organisationId, $userId, $projectId, $id, $date, $status, $payload): array {
            $this->pdo->prepare('INSERT INTO site_daily_logs(id,organisation_id,project_id,log_date,weather,labour,plant,deliveries,visitors,delays,safety_notes,status,created_by) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:project),:log_date,:weather,:labour,:plant,:deliveries,:visitors,:delays,:safety_notes,:status,UNHEX(:actor))')->execute([
                'id'=>$id,'organisation'=>$organisationId,'project'=>$projectId,'log_date'=>$date,'weather'=>$payload['weather'],'labour'=>$this->json($payload['labour']),'plant'=>$this->json($payload['plant']),'deliveries'=>$this->json($payload['deliveries']),'visitors'=>$this->json($payload['visitors']),'delays'=>$this->json($payload['delays']),'safety_notes'=>$this->json($payload['safetyNotes']),'status'=>$status,'actor'=>$userId,
            ]);
            $this->record($organisationId,$projectId,$userId,'daily_log','Daily log '.$date,$status==='submitted'?'issued':'draft',['dailyLogId'=>$id,'logDate'=>$date]+$payload,[]);
            $this->audit($organisationId,$userId,'site_execution.daily_log_created','site_daily_log',$id,['status'=>$status,'logDate'=>$date]);
            return $this->dailyLog($organisationId,$projectId,$id);
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function captureEvidence(string $organisationId, string $userId, string $role, string $projectId, array $input): array
    {
        $this->assertProject($organisationId,$userId,$projectId);
        if (($input['humanConfirmed'] ?? false) !== true) throw new SiteExecutionException('site_evidence_confirmation_required');
        $id=Identifier::generate();$log=$this->nullableId($input['dailyLogId']??null,'site_daily_log_invalid');$file=$this->nullableId($input['fileId']??null,'site_evidence_file_invalid');
        if($log!==null)$this->assertOwned('site_daily_logs',$organisationId,$projectId,$log,'site_daily_log_not_found');
        if($file!==null)$this->assertOwned('stored_files',$organisationId,$projectId,$file,'site_evidence_file_not_found');
        $type=$this->choice($input['evidenceType']??null,['photo','drawing','specification','delivery_note','weather_report','file'],'site_evidence_type_invalid');
        $title=$this->text($input['title']??null,220,'site_evidence_title_required');$captured=$this->dateTime($input['capturedAt']??null,'site_evidence_captured_at_invalid');
        $classification=$this->choice($input['popiaClassification']??null,['project_team','restricted','personal_information'],'site_evidence_classification_invalid');
        [$latitude,$longitude]=$this->coordinates($input['latitude']??null,$input['longitude']??null);
        return $this->transaction(function()use($organisationId,$userId,$projectId,$id,$log,$file,$type,$title,$captured,$classification,$latitude,$longitude,$input):array{
            $location=$this->nullableText($input['locationLabel']??null,500,'site_evidence_location_invalid');
            $this->pdo->prepare('INSERT INTO site_field_evidence(id,organisation_id,project_id,daily_log_id,file_id,evidence_type,title,location_label,latitude,longitude,captured_at,popia_classification,metadata,captured_by) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:project),UNHEX(:daily_log),UNHEX(:file),:type,:title,:location,:latitude,:longitude,:captured_at,:classification,:metadata,UNHEX(:actor))')->execute(['id'=>$id,'organisation'=>$organisationId,'project'=>$projectId,'daily_log'=>$log,'file'=>$file,'type'=>$type,'title'=>$title,'location'=>$location,'latitude'=>$latitude,'longitude'=>$longitude,'captured_at'=>$captured,'classification'=>$classification,'metadata'=>$this->json(['locationLabel'=>$location,'gps'=>$latitude===null?null:['latitude'=>$latitude,'longitude'=>$longitude]]),'actor'=>$userId]);
            $links=array_values(array_filter([$log,$file]));$this->record($organisationId,$projectId,$userId,'field_evidence',$title,'issued',['evidenceId'=>$id,'type'=>$type,'popiaClassification'=>$classification,'capturedAt'=>$captured],$links);
            $this->audit($organisationId,$userId,'site_execution.evidence_captured','site_field_evidence',$id,['type'=>$type,'classification'=>$classification]);
            return $this->evidence($organisationId,$projectId,$id);
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createRfi(string $organisationId,string $userId,string $role,string $projectId,array $input):array
    {
        $this->assertProject($organisationId,$userId,$projectId);if(($input['humanConfirmed']??false)!==true)throw new SiteExecutionException('site_rfi_confirmation_required');
        $id=Identifier::generate();$subject=$this->text($input['subject']??null,220,'site_rfi_subject_required');$question=$this->text($input['question']??null,12000,'site_rfi_question_required');
        $discipline=$this->choice($input['discipline']??null,['architectural','structural','civil','electrical','mechanical','quantity_surveying'],'site_rfi_discipline_invalid');$documents=$this->strings($input['linkedDocumentIds']??[],100,180,'site_rfi_documents_invalid');$due=$this->dateTime($input['dueAt']??null,'site_rfi_due_at_invalid');
        return $this->transaction(function()use($organisationId,$userId,$projectId,$id,$subject,$question,$discipline,$documents,$due):array{
            $this->pdo->prepare("INSERT INTO site_rfis(id,organisation_id,project_id,subject,question,discipline,linked_document_ids,due_at,status,raised_by) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:project),:subject,:question,:discipline,:documents,:due_at,'open',UNHEX(:actor))")->execute(['id'=>$id,'organisation'=>$organisationId,'project'=>$projectId,'subject'=>$subject,'question'=>$question,'discipline'=>$discipline,'documents'=>$this->json($documents),'due_at'=>$due,'actor'=>$userId]);
            $this->record($organisationId,$projectId,$userId,'rfi',$subject,'pending_review',['rfiId'=>$id,'question'=>$question,'discipline'=>$discipline,'dueAt'=>$due],[]);$this->inbox($organisationId,$projectId,'site_execution.rfi_response_required','RFI response required',$subject,'high',['rfiId'=>$id,'dueAt'=>$due]);$this->recommend($organisationId,$projectId,$id,'field_rfi_monitor','Respond to RFI before its SLA deadline','An open field query requires a professional response.','high');$this->audit($organisationId,$userId,'site_execution.rfi_created','site_rfi',$id,['dueAt'=>$due,'discipline'=>$discipline]);
            return $this->rfi($organisationId,$projectId,$id);
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function respondRfi(string $organisationId,string $userId,string $role,string $rfiId,array $input):array
    {
        if(($input['humanConfirmed']??false)!==true)throw new SiteExecutionException('site_rfi_response_confirmation_required');$row=$this->scopeItem('site_rfis',$organisationId,$userId,$rfiId,'site_rfi_not_found');if(!in_array($row['status'],['open','overdue'],true))throw new SiteExecutionException('site_rfi_transition_invalid');
        $answer=$this->text($input['answer']??null,12000,'site_rfi_answer_required');$requires=($input['requiresInstruction']??false)===true;$responseId=Identifier::generate();
        return $this->transaction(function()use($organisationId,$userId,$rfiId,$row,$answer,$requires,$responseId):array{
            $this->pdo->prepare('INSERT INTO site_rfi_responses(id,organisation_id,rfi_id,answer,requires_instruction,responded_by) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:rfi),:answer,:requires,UNHEX(:actor))')->execute(['id'=>$responseId,'organisation'=>$organisationId,'rfi'=>$rfiId,'answer'=>$answer,'requires'=>$requires?1:0,'actor'=>$userId]);$this->pdo->prepare("UPDATE site_rfis SET status='responded' WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation)")->execute(['id'=>$rfiId,'organisation'=>$organisationId]);
            $this->record($organisationId,$row['project_id'],$userId,'rfi_response','Response: '.$row['subject'],'issued',['rfiId'=>$rfiId,'responseId'=>$responseId,'answer'=>$answer,'requiresInstruction'=>$requires],[$rfiId]);if($requires)$this->inbox($organisationId,$row['project_id'],'site_execution.instruction_required','Formal instruction review required',$row['subject'],'high',['rfiId'=>$rfiId]);$this->audit($organisationId,$userId,'site_execution.rfi_responded','site_rfi',$rfiId,['responseId'=>$responseId,'requiresInstruction'=>$requires]);
            return $this->rfi($organisationId,$row['project_id'],$rfiId);
        });
    }

    public function markOverdueRfis(string $organisationId,string $userId,string $projectId,string $asAt):int
    {
        $this->assertProject($organisationId,$userId,$projectId);$timestamp=$this->dateTime($asAt,'site_rfi_overdue_time_invalid');
        return $this->transaction(function()use($organisationId,$userId,$projectId,$timestamp):int{$select=$this->pdo->prepare("SELECT LOWER(HEX(id)) id,subject,due_at FROM site_rfis WHERE organisation_id=UNHEX(:organisation) AND project_id=UNHEX(:project) AND status='open' AND due_at<:as_at FOR UPDATE");$select->execute(['organisation'=>$organisationId,'project'=>$projectId,'as_at'=>$timestamp]);$rows=$select->fetchAll();if($rows===[])return 0;$this->pdo->prepare("UPDATE site_rfis SET status='overdue' WHERE organisation_id=UNHEX(:organisation) AND project_id=UNHEX(:project) AND status='open' AND due_at<:as_at")->execute(['organisation'=>$organisationId,'project'=>$projectId,'as_at'=>$timestamp]);foreach($rows as$row){$this->inbox($organisationId,$projectId,'site_execution.rfi_overdue','RFI overdue',(string)$row['subject'],'critical',['rfiId'=>$row['id'],'dueAt'=>$row['due_at']]);$this->recommend($organisationId,$projectId,(string)$row['id'],'field_rfi_monitor','Escalate overdue RFI','The professional response SLA has elapsed.','critical');$this->audit($organisationId,$userId,'site_execution.rfi_marked_overdue','site_rfi',(string)$row['id'],['asAt'=>$timestamp]);}return count($rows);});
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function closeRfi(string $organisationId,string $userId,string $role,string $rfiId,array $input):array
    {
        $this->confirmed($input,'site_rfi_close_confirmation_required');$reason=$this->text($input['reason']??null,1000,'site_rfi_close_reason_required');$row=$this->scopeItem('site_rfis',$organisationId,$userId,$rfiId,'site_rfi_not_found');if($row['status']!=='responded')throw new SiteExecutionException('site_rfi_transition_invalid');
        return $this->transaction(function()use($organisationId,$userId,$rfiId,$row,$reason):array{$this->pdo->prepare("UPDATE site_rfis SET status='closed',closed_by=UNHEX(:actor),closed_at=UTC_TIMESTAMP(6) WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation) AND status='responded'")->execute(['actor'=>$userId,'id'=>$rfiId,'organisation'=>$organisationId]);$this->record($organisationId,$row['project_id'],$userId,'rfi_closeout','Closed: '.$row['subject'],'approved',['rfiId'=>$rfiId,'reason'=>$reason],[$rfiId]);$this->audit($organisationId,$userId,'site_execution.rfi_closed','site_rfi',$rfiId,['reason'=>$reason]);return$this->rfi($organisationId,$row['project_id'],$rfiId);});
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createSiteInstruction(string $organisationId,string $userId,string $role,string $projectId,array $input):array
    {
        $this->assertProject($organisationId,$userId,$projectId);$id=Identifier::generate();$linked=$this->nullableId($input['linkedRfiId']??null,'site_instruction_rfi_invalid');if($linked!==null)$this->assertOwned('site_rfis',$organisationId,$projectId,$linked,'site_rfi_not_found');
        $title=$this->text($input['title']??null,220,'site_instruction_title_required');$instruction=$this->text($input['instruction']??null,12000,'site_instruction_body_required');$cost=$this->choice($input['costImpact']??null,['none','possible','confirmed'],'site_instruction_cost_impact_invalid');$time=$this->choice($input['timeImpact']??null,['none','possible','confirmed'],'site_instruction_time_impact_invalid');$variation=$cost!=='none'||$time!=='none';
        return $this->transaction(function()use($organisationId,$userId,$projectId,$id,$linked,$title,$instruction,$cost,$time,$variation):array{
            $this->pdo->prepare("INSERT INTO site_instructions(id,organisation_id,project_id,linked_rfi_id,title,instruction,cost_impact,time_impact,variation_flag,status,created_by) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:project),UNHEX(:rfi),:title,:instruction,:cost,:time,:variation,'draft',UNHEX(:actor))")->execute(['id'=>$id,'organisation'=>$organisationId,'project'=>$projectId,'rfi'=>$linked,'title'=>$title,'instruction'=>$instruction,'cost'=>$cost,'time'=>$time,'variation'=>$variation?1:0,'actor'=>$userId]);$this->record($organisationId,$projectId,$userId,'site_instruction',$title,'draft',['instructionId'=>$id,'instruction'=>$instruction,'costImpact'=>$cost,'timeImpact'=>$time,'variationFlag'=>$variation],array_values(array_filter([$linked])));$this->audit($organisationId,$userId,'site_execution.site_instruction_drafted','site_instruction',$id,['variationFlag'=>$variation]);return $this->instruction($organisationId,$projectId,$id);
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function issueSiteInstruction(string $organisationId,string $userId,string $role,string $instructionId,array $input):array
    {
        if(($input['humanConfirmed']??false)!==true)throw new SiteExecutionException('site_instruction_confirmation_required');$reason=$this->text($input['reason']??null,1000,'site_instruction_reason_required');$row=$this->scopeItem('site_instructions',$organisationId,$userId,$instructionId,'site_instruction_not_found');if($row['status']!=='draft')throw new SiteExecutionException('site_instruction_transition_invalid');$this->assertProfessionalAuthority($organisationId,$userId,$role,$row['project_id']);
        return $this->transaction(function()use($organisationId,$userId,$instructionId,$row,$reason):array{
            $this->pdo->prepare("UPDATE site_instructions SET status='issued',issued_by=UNHEX(:actor),issued_at=UTC_TIMESTAMP(6) WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation) AND status='draft'")->execute(['actor'=>$userId,'id'=>$instructionId,'organisation'=>$organisationId]);$this->record($organisationId,$row['project_id'],$userId,'site_instruction',$row['title'],'issued',['instructionId'=>$instructionId,'instruction'=>$row['instruction'],'costImpact'=>$row['cost_impact'],'timeImpact'=>$row['time_impact'],'variationFlag'=>(bool)$row['variation_flag'],'issueReason'=>$reason],array_values(array_filter([$row['linked_rfi_id']])));$this->inbox($organisationId,$row['project_id'],'site_execution.instruction_acknowledgement_required','Site instruction acknowledgement required',$row['title'],'high',['instructionId'=>$instructionId]);if((bool)$row['variation_flag']){$this->inbox($organisationId,$row['project_id'],'site_execution.variation_review_required','Review possible cost/time impact',$row['title'],'high',['instructionId'=>$instructionId]);$this->recommend($organisationId,$row['project_id'],$instructionId,'field_commercial_monitor','Review site instruction commercial impact','A human-issued instruction has a possible or confirmed cost/time impact.','high');}$this->audit($organisationId,$userId,'site_execution.site_instruction_issued','site_instruction',$instructionId,['reason'=>$reason,'variationFlag'=>(bool)$row['variation_flag']]);return $this->instruction($organisationId,$row['project_id'],$instructionId);
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function acknowledgeSiteInstruction(string $organisationId,string $userId,string $role,string $instructionId,array $input):array
    {
        if(($input['humanConfirmed']??false)!==true)throw new SiteExecutionException('site_instruction_acknowledgement_required');$reason=$this->text($input['reason']??null,1000,'site_instruction_acknowledgement_reason_required');$row=$this->scopeItem('site_instructions',$organisationId,$userId,$instructionId,'site_instruction_not_found');if($row['status']!=='issued')throw new SiteExecutionException('site_instruction_transition_invalid');
        return $this->transaction(function()use($organisationId,$userId,$instructionId,$row,$reason):array{$this->pdo->prepare("UPDATE site_instructions SET status='acknowledged',acknowledged_by=UNHEX(:actor),acknowledged_at=UTC_TIMESTAMP(6) WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation) AND status='issued'")->execute(['actor'=>$userId,'id'=>$instructionId,'organisation'=>$organisationId]);$this->record($organisationId,$row['project_id'],$userId,'site_instruction_acknowledgement','Acknowledged: '.$row['title'],'issued',['instructionId'=>$instructionId,'reason'=>$reason],[$instructionId]);$this->audit($organisationId,$userId,'site_execution.site_instruction_acknowledged','site_instruction',$instructionId,['reason'=>$reason]);return $this->instruction($organisationId,$row['project_id'],$instructionId);});
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function supersedeSiteInstruction(string $organisationId,string $userId,string $role,string $instructionId,array $input):array
    {
        $this->confirmed($input,'site_instruction_supersession_confirmation_required');$reason=$this->text($input['reason']??null,1000,'site_instruction_supersession_reason_required');$row=$this->scopeItem('site_instructions',$organisationId,$userId,$instructionId,'site_instruction_not_found');if(!in_array($row['status'],['issued','acknowledged'],true))throw new SiteExecutionException('site_instruction_transition_invalid');$this->assertProfessionalAuthority($organisationId,$userId,$role,$row['project_id']);$newId=Identifier::generate();$title=$this->text($input['title']??null,220,'site_instruction_title_required');$instruction=$this->text($input['instruction']??null,12000,'site_instruction_body_required');$cost=$this->choice($input['costImpact']??null,['none','possible','confirmed'],'site_instruction_cost_impact_invalid');$time=$this->choice($input['timeImpact']??null,['none','possible','confirmed'],'site_instruction_time_impact_invalid');$variation=$cost!=='none'||$time!=='none';
        return $this->transaction(function()use($organisationId,$userId,$instructionId,$row,$newId,$title,$instruction,$cost,$time,$variation,$reason):array{$this->pdo->prepare("UPDATE site_instructions SET status='superseded' WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation) AND status IN ('issued','acknowledged')")->execute(['id'=>$instructionId,'organisation'=>$organisationId]);$this->pdo->prepare("INSERT INTO site_instructions(id,organisation_id,project_id,linked_rfi_id,supersedes_id,title,instruction,cost_impact,time_impact,variation_flag,status,created_by,issued_by,issued_at) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:project),UNHEX(:rfi),UNHEX(:supersedes),:title,:instruction,:cost,:time,:variation,'issued',UNHEX(:created_by),UNHEX(:issued_by),UTC_TIMESTAMP(6))")->execute(['id'=>$newId,'organisation'=>$organisationId,'project'=>$row['project_id'],'rfi'=>$row['linked_rfi_id'],'supersedes'=>$instructionId,'title'=>$title,'instruction'=>$instruction,'cost'=>$cost,'time'=>$time,'variation'=>$variation?1:0,'created_by'=>$userId,'issued_by'=>$userId]);$this->record($organisationId,$row['project_id'],$userId,'site_instruction',$row['title'],'superseded',['instructionId'=>$instructionId,'supersededBy'=>$newId,'reason'=>$reason],[$instructionId,$newId]);$this->record($organisationId,$row['project_id'],$userId,'site_instruction',$title,'issued',['instructionId'=>$newId,'supersedesId'=>$instructionId,'instruction'=>$instruction,'costImpact'=>$cost,'timeImpact'=>$time,'variationFlag'=>$variation,'issueReason'=>$reason],array_values(array_filter([$instructionId,$row['linked_rfi_id']])));$this->inbox($organisationId,$row['project_id'],'site_execution.instruction_acknowledgement_required','Revised site instruction acknowledgement required',$title,'high',['instructionId'=>$newId,'supersedesId'=>$instructionId]);$this->audit($organisationId,$userId,'site_execution.site_instruction_superseded','site_instruction',$instructionId,['supersededBy'=>$newId,'reason'=>$reason]);return$this->instruction($organisationId,$row['project_id'],$newId);});
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createNcr(string $organisationId,string $userId,string $role,string $projectId,array $input):array
    {
        $this->assertProject($organisationId,$userId,$projectId);$this->confirmed($input,'site_ncr_confirmation_required');$id=Identifier::generate();$title=$this->text($input['title']??null,220,'site_ncr_title_required');$description=$this->text($input['description']??null,12000,'site_ncr_description_required');$severity=$this->choice($input['severity']??null,self::SEVERITIES,'site_ncr_severity_invalid');$party=$this->text($input['responsibleParty']??null,220,'site_ncr_party_required');$evidence=$this->ids($input['evidenceIds']??[],'site_ncr_evidence_invalid');$this->assertEvidence($organisationId,$projectId,$evidence);$blocks=($input['blocksPayment']??false)===true;
        return $this->transaction(function()use($organisationId,$userId,$projectId,$id,$title,$description,$severity,$party,$evidence,$blocks):array{$this->pdo->prepare("INSERT INTO site_ncrs(id,organisation_id,project_id,title,description,severity,responsible_party,evidence_ids,corrective_evidence_ids,blocks_payment,status,created_by) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:project),:title,:description,:severity,:party,:evidence,'[]',:blocks,'open',UNHEX(:actor))")->execute(['id'=>$id,'organisation'=>$organisationId,'project'=>$projectId,'title'=>$title,'description'=>$description,'severity'=>$severity,'party'=>$party,'evidence'=>$this->json($evidence),'blocks'=>$blocks?1:0,'actor'=>$userId]);$this->fieldItemOutputs($organisationId,$projectId,$userId,'non_conformance_report',$id,$title,$severity,'Open NCR requires corrective action.',$blocks,$evidence);return $this->ncr($organisationId,$projectId,$id);});
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function submitNcrCorrectiveAction(string $organisationId,string $userId,string $role,string $ncrId,array $input):array
    {
        $this->confirmed($input,'site_ncr_corrective_confirmation_required');$row=$this->scopeItem('site_ncrs',$organisationId,$userId,$ncrId,'site_ncr_not_found');if(!in_array($row['status'],['open','rejected'],true))throw new SiteExecutionException('site_ncr_transition_invalid');$action=$this->text($input['correctiveAction']??null,12000,'site_ncr_corrective_action_required');$evidence=$this->ids($input['evidenceIds']??[],'site_ncr_evidence_invalid');$this->assertEvidence($organisationId,$row['project_id'],$evidence);
        return $this->transaction(function()use($organisationId,$userId,$ncrId,$row,$action,$evidence):array{$this->pdo->prepare("UPDATE site_ncrs SET corrective_action=:action,corrective_evidence_ids=:evidence,status='corrective_action_submitted',reviewed_by=NULL WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation)")->execute(['action'=>$action,'evidence'=>$this->json($evidence),'id'=>$ncrId,'organisation'=>$organisationId]);$this->record($organisationId,$row['project_id'],$userId,'ncr_corrective_action','Corrective action: '.$row['title'],'pending_review',['ncrId'=>$ncrId,'correctiveAction'=>$action,'evidenceIds'=>$evidence],array_merge([$ncrId],$evidence));$this->inbox($organisationId,$row['project_id'],'site_execution.ncr_verification_required','NCR verification required',$row['title'],'high',['ncrId'=>$ncrId]);$this->audit($organisationId,$userId,'site_execution.ncr_corrective_action_submitted','site_ncr',$ncrId,[]);return $this->ncr($organisationId,$row['project_id'],$ncrId);});
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function decideNcr(string $organisationId,string $userId,string $role,string $ncrId,array $input):array
    {
        $this->confirmed($input,'site_ncr_decision_confirmation_required');$decision=$this->choice($input['decision']??null,['verified_closed','rejected'],'site_ncr_decision_invalid');$reason=$this->text($input['reason']??null,1000,'site_ncr_decision_reason_required');$row=$this->scopeItem('site_ncrs',$organisationId,$userId,$ncrId,'site_ncr_not_found');if($row['status']!=='corrective_action_submitted')throw new SiteExecutionException('site_ncr_transition_invalid');$this->assertProfessionalAuthority($organisationId,$userId,$role,$row['project_id']);
        return $this->transaction(function()use($organisationId,$userId,$ncrId,$row,$decision,$reason):array{$this->pdo->prepare('UPDATE site_ncrs SET status=:decision,reviewed_by=UNHEX(:actor) WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation)')->execute(['decision'=>$decision,'actor'=>$userId,'id'=>$ncrId,'organisation'=>$organisationId]);$this->record($organisationId,$row['project_id'],$userId,'non_conformance_report',$row['title'],$decision==='verified_closed'?'approved':'rejected',['ncrId'=>$ncrId,'decision'=>$decision,'reason'=>$reason],[$ncrId]);if($decision==='rejected')$this->inbox($organisationId,$row['project_id'],'site_execution.ncr_corrective_action_required','NCR corrective action rejected',$reason,'high',['ncrId'=>$ncrId]);$this->audit($organisationId,$userId,'site_execution.ncr_'.$decision,'site_ncr',$ncrId,['reason'=>$reason]);return$this->ncr($organisationId,$row['project_id'],$ncrId);});
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createSnag(string $organisationId,string $userId,string $role,string $projectId,array $input):array
    {
        $this->assertProject($organisationId,$userId,$projectId);$this->confirmed($input,'site_snag_confirmation_required');$id=Identifier::generate();$location=$this->text($input['location']??null,500,'site_snag_location_required');$description=$this->text($input['description']??null,12000,'site_snag_description_required');$priority=$this->choice($input['priority']??null,self::SEVERITIES,'site_snag_priority_invalid');$party=$this->text($input['responsibleParty']??null,220,'site_snag_party_required');$due=$this->dateTime($input['dueAt']??null,'site_snag_due_at_invalid');$evidence=$this->ids($input['evidenceIds']??[],'site_snag_evidence_invalid');$this->assertEvidence($organisationId,$projectId,$evidence);$blocks=($input['blocksPayment']??false)===true;
        return $this->transaction(function()use($organisationId,$userId,$projectId,$id,$location,$description,$priority,$party,$due,$evidence,$blocks):array{$this->pdo->prepare("INSERT INTO site_snags(id,organisation_id,project_id,location_label,description,priority,responsible_party,due_at,evidence_ids,reinspection_evidence_ids,blocks_payment,status,created_by) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:project),:location,:description,:priority,:party,:due_at,:evidence,'[]',:blocks,'open',UNHEX(:actor))")->execute(['id'=>$id,'organisation'=>$organisationId,'project'=>$projectId,'location'=>$location,'description'=>$description,'priority'=>$priority,'party'=>$party,'due_at'=>$due,'evidence'=>$this->json($evidence),'blocks'=>$blocks?1:0,'actor'=>$userId]);$this->fieldItemOutputs($organisationId,$projectId,$userId,'snag_item',$id,$description,$priority,'Snag requires allocation and closeout.',$blocks,$evidence);return $this->snag($organisationId,$projectId,$id);});
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function allocateSnag(string $organisationId,string $userId,string $role,string $snagId,array $input):array
    {
        $this->confirmed($input,'site_snag_allocation_confirmation_required');return $this->snagTransition($organisationId,$userId,$snagId,'open','allocated','site_execution.snag_allocated',[]);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function markSnagReady(string $organisationId,string $userId,string $role,string $snagId,array $input):array
    {
        $this->confirmed($input,'site_snag_reinspection_confirmation_required');$row=$this->scopeItem('site_snags',$organisationId,$userId,$snagId,'site_snag_not_found');if($row['status']!=='allocated')throw new SiteExecutionException('site_snag_transition_invalid');$evidence=$this->ids($input['evidenceIds']??[],'site_snag_evidence_invalid');$this->assertEvidence($organisationId,$row['project_id'],$evidence);
        return $this->transaction(function()use($organisationId,$userId,$snagId,$row,$evidence):array{$this->pdo->prepare("UPDATE site_snags SET status='ready_for_reinspection',reinspection_evidence_ids=:evidence WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation) AND status='allocated'")->execute(['evidence'=>$this->json($evidence),'id'=>$snagId,'organisation'=>$organisationId]);$this->record($organisationId,$row['project_id'],$userId,'snag_reinspection_request','Reinspect: '.$row['description'],'pending_review',['snagId'=>$snagId,'evidenceIds'=>$evidence],array_merge([$snagId],$evidence));$this->inbox($organisationId,$row['project_id'],'site_execution.snag_reinspection_required','Snag ready for reinspection',$row['description'],'high',['snagId'=>$snagId]);$this->audit($organisationId,$userId,'site_execution.snag_ready_for_reinspection','site_snag',$snagId,[]);return $this->snag($organisationId,$row['project_id'],$snagId);});
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function decideSnag(string $organisationId,string $userId,string $role,string $snagId,array $input):array
    {
        $this->confirmed($input,'site_snag_decision_confirmation_required');$decision=$this->choice($input['decision']??null,['closed','rejected'],'site_snag_decision_invalid');$reason=$this->text($input['reason']??null,1000,'site_snag_decision_reason_required');$row=$this->scopeItem('site_snags',$organisationId,$userId,$snagId,'site_snag_not_found');if($row['status']!=='ready_for_reinspection')throw new SiteExecutionException('site_snag_transition_invalid');$this->assertProfessionalAuthority($organisationId,$userId,$role,$row['project_id']);
        return $this->transaction(function()use($organisationId,$userId,$snagId,$row,$decision,$reason):array{$this->pdo->prepare('UPDATE site_snags SET status=:decision,reviewed_by=UNHEX(:actor) WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation)')->execute(['decision'=>$decision,'actor'=>$userId,'id'=>$snagId,'organisation'=>$organisationId]);$this->record($organisationId,$row['project_id'],$userId,'snag_item',$row['description'],$decision==='closed'?'approved':'rejected',['snagId'=>$snagId,'decision'=>$decision,'reason'=>$reason],[$snagId]);if($decision==='rejected')$this->inbox($organisationId,$row['project_id'],'site_execution.snag_rework_required','Snag reinspection rejected',$reason,'high',['snagId'=>$snagId]);$this->audit($organisationId,$userId,'site_execution.snag_'.$decision,'site_snag',$snagId,['reason'=>$reason]);return$this->snag($organisationId,$row['project_id'],$snagId);});
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createInspection(string $organisationId,string $userId,string $role,string $projectId,array $input):array
    {
        $this->assertProject($organisationId,$userId,$projectId);$id=Identifier::generate();$type=$this->choice($input['inspectionType']??null,['architectural','structural','quality','safety','municipal','progress'],'site_inspection_type_invalid');$findings=$this->strings($input['findings']??[],200,1000,'site_inspection_findings_invalid');$followUps=$this->strings($input['followUps']??[],200,1000,'site_inspection_followups_invalid');$checklist=$this->checklist($input['checklist']??[]);$signoff=($input['signOffRequired']??false)===true;
        return $this->transaction(function()use($organisationId,$userId,$projectId,$id,$type,$findings,$followUps,$checklist,$signoff):array{$this->pdo->prepare("INSERT INTO site_inspections(id,organisation_id,project_id,inspection_type,findings,checklist,follow_ups,sign_off_required,statutory_approval,status,created_by) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:project),:type,:findings,:checklist,:followups,:signoff,FALSE,'draft',UNHEX(:actor))")->execute(['id'=>$id,'organisation'=>$organisationId,'project'=>$projectId,'type'=>$type,'findings'=>$this->json($findings),'checklist'=>$this->json($checklist),'followups'=>$this->json($followUps),'signoff'=>$signoff?1:0,'actor'=>$userId]);$this->record($organisationId,$projectId,$userId,'inspection_record','Draft '.$type.' inspection','draft',['inspectionId'=>$id,'inspectionType'=>$type,'findings'=>$findings,'checklist'=>$checklist,'followUps'=>$followUps,'signOffRequired'=>$signoff,'statutoryApproval'=>false],[]);$this->audit($organisationId,$userId,'site_execution.inspection_drafted','site_inspection',$id,['type'=>$type]);return $this->inspection($organisationId,$projectId,$id);});
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function issueInspection(string $organisationId,string $userId,string $role,string $inspectionId,array $input):array
    {
        $this->confirmed($input,'site_inspection_confirmation_required');$row=$this->scopeItem('site_inspections',$organisationId,$userId,$inspectionId,'site_inspection_not_found');if($row['status']!=='draft')throw new SiteExecutionException('site_inspection_transition_invalid');$supervisor=$this->nullableId($input['professionalSupervisorId']??null,'site_inspection_supervisor_invalid');if((bool)$row['sign_off_required']&&$supervisor===null)throw new SiteExecutionException('site_inspection_supervisor_required');if($supervisor!==null)$this->assertProject($organisationId,$supervisor,$row['project_id']);$this->assertProfessionalAuthority($organisationId,$userId,$role,$row['project_id']);$checklist=$this->decode($row['checklist']);$followups=$this->decode($row['follow_ups']);$failed=false;foreach($checklist as$item){if(is_array($item)&&($item['outcome']??null)==='fail'){$failed=true;break;}}$needsFollowUp=$followups!==[]||$failed;$status=$needsFollowUp?'requires_follow_up':'issued';
        return $this->transaction(function()use($organisationId,$userId,$inspectionId,$row,$supervisor,$status):array{$this->pdo->prepare('UPDATE site_inspections SET status=:status,professional_supervisor_id=UNHEX(:supervisor),issued_by=UNHEX(:actor),issued_at=UTC_TIMESTAMP(6),statutory_approval=FALSE WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation)')->execute(['status'=>$status,'supervisor'=>$supervisor,'actor'=>$userId,'id'=>$inspectionId,'organisation'=>$organisationId]);$this->record($organisationId,$row['project_id'],$userId,'inspection_record',ucfirst($row['inspection_type']).' inspection',$status==='issued'?'issued':'pending_review',['inspectionId'=>$inspectionId,'status'=>$status,'statutoryApproval'=>false,'professionalSupervisorId'=>$supervisor],[$inspectionId]);if($status==='requires_follow_up'){$this->inbox($organisationId,$row['project_id'],'site_execution.inspection_follow_up_required','Inspection follow-up required',ucfirst($row['inspection_type']).' inspection','high',['inspectionId'=>$inspectionId]);$this->recommend($organisationId,$row['project_id'],$inspectionId,'field_quality_monitor','Resolve failed inspection outcomes','The issued internal inspection has failed items or follow-up actions.','high');}$this->audit($organisationId,$userId,'site_execution.inspection_issued','site_inspection',$inspectionId,['status'=>$status,'statutoryApproval'=>false]);return $this->inspection($organisationId,$row['project_id'],$inspectionId);});
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function closeInspection(string $organisationId,string $userId,string $role,string $inspectionId,array $input):array
    {
        $this->confirmed($input,'site_inspection_close_confirmation_required');$reason=$this->text($input['reason']??null,1000,'site_inspection_close_reason_required');$row=$this->scopeItem('site_inspections',$organisationId,$userId,$inspectionId,'site_inspection_not_found');if(!in_array($row['status'],['issued','requires_follow_up'],true))throw new SiteExecutionException('site_inspection_transition_invalid');$this->assertProfessionalAuthority($organisationId,$userId,$role,$row['project_id']);return$this->transaction(function()use($organisationId,$userId,$inspectionId,$row,$reason):array{$this->pdo->prepare("UPDATE site_inspections SET status='closed' WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation)")->execute(['id'=>$inspectionId,'organisation'=>$organisationId]);$this->record($organisationId,$row['project_id'],$userId,'inspection_record',ucfirst($row['inspection_type']).' inspection closed','approved',['inspectionId'=>$inspectionId,'reason'=>$reason,'statutoryApproval'=>false],[$inspectionId]);$this->audit($organisationId,$userId,'site_execution.inspection_closed','site_inspection',$inspectionId,['reason'=>$reason,'statutoryApproval'=>false]);return$this->inspection($organisationId,$row['project_id'],$inspectionId);});
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createDelayWarning(string $organisationId,string $userId,string $role,string $projectId,array $input):array
    {
        $this->assertProject($organisationId,$userId,$projectId);$this->confirmed($input,'site_delay_warning_confirmation_required');$id=Identifier::generate();$cause=$this->choice($input['cause']??null,['weather','materials','labour','client','professional','contractor','unknown'],'site_delay_warning_cause_invalid');$description=$this->text($input['description']??null,12000,'site_delay_warning_description_required');$deadline=$this->dateTime($input['noticeDeadline']??null,'site_delay_warning_deadline_invalid');$days=$this->integer($input['likelyProgrammeImpactDays']??null,0,3650,'site_delay_warning_days_invalid');$status='notice_required';
        return $this->transaction(function()use($organisationId,$userId,$projectId,$id,$cause,$description,$deadline,$days,$status):array{$this->pdo->prepare("INSERT INTO site_delay_warnings(id,organisation_id,project_id,cause,description,notice_deadline,likely_programme_impact_days,grants_entitlement,status,created_by) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:project),:cause,:description,:deadline,:days,FALSE,:status,UNHEX(:actor))")->execute(['id'=>$id,'organisation'=>$organisationId,'project'=>$projectId,'cause'=>$cause,'description'=>$description,'deadline'=>$deadline,'days'=>$days,'status'=>$status,'actor'=>$userId]);$this->record($organisationId,$projectId,$userId,'delay_early_warning','Delay early warning','pending_review',['warningId'=>$id,'cause'=>$cause,'description'=>$description,'noticeDeadline'=>$deadline,'likelyProgrammeImpactDays'=>$days,'grantsEntitlement'=>false],[]);$this->inbox($organisationId,$projectId,'site_execution.delay_notice_required','Review delay notice deadline',$description,'high',['warningId'=>$id,'noticeDeadline'=>$deadline]);$this->recommend($organisationId,$projectId,$id,'field_delay_monitor','Review delay evidence and notice deadline','This is an early warning only and does not grant EOT entitlement.','high');$this->audit($organisationId,$userId,'site_execution.delay_warning_created','site_delay_warning',$id,['grantsEntitlement'=>false]);return $this->delayWarning($organisationId,$projectId,$id);});
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function transitionDelayWarning(string $organisationId,string $userId,string $role,string $warningId,array $input):array
    {
        $this->confirmed($input,'site_delay_warning_transition_confirmation_required');$to=$this->choice($input['status']??null,['notice_required','under_review','closed'],'site_delay_warning_status_invalid');$reason=$this->text($input['reason']??null,1000,'site_delay_warning_transition_reason_required');$row=$this->scopeItem('site_delay_warnings',$organisationId,$userId,$warningId,'site_delay_warning_not_found');$allowed=['recorded'=>'notice_required','notice_required'=>'under_review','under_review'=>'closed'];if(($allowed[$row['status']]??null)!==$to)throw new SiteExecutionException('site_delay_warning_transition_invalid');
        return$this->transaction(function()use($organisationId,$userId,$warningId,$row,$to,$reason):array{$this->pdo->prepare('UPDATE site_delay_warnings SET status=:status,grants_entitlement=FALSE WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation)')->execute(['status'=>$to,'id'=>$warningId,'organisation'=>$organisationId]);$this->record($organisationId,$row['project_id'],$userId,'delay_early_warning','Delay warning '.$to,$to==='closed'?'approved':'pending_review',['warningId'=>$warningId,'status'=>$to,'reason'=>$reason,'grantsEntitlement'=>false],[$warningId]);$this->audit($organisationId,$userId,'site_execution.delay_warning_'.$to,'site_delay_warning',$warningId,['reason'=>$reason,'grantsEntitlement'=>false]);return$this->delayWarning($organisationId,$row['project_id'],$warningId);});
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function assessProgrammeImpact(string $organisationId,string $userId,string $role,string $projectId,array $input):array
    {
        $this->assertProject($organisationId,$userId,$projectId);$this->confirmed($input,'site_programme_impact_confirmation_required');$id=Identifier::generate();$type=$this->choice($input['sourceType']??null,['delay_warning','site_instruction','ncr'],'site_programme_impact_source_invalid');$source=$this->id($input['sourceObjectId']??null,'site_programme_impact_source_invalid');$this->assertSource($organisationId,$projectId,$type,$source);$days=$this->integer($input['estimatedDays']??null,0,3650,'site_programme_impact_days_invalid');
        return $this->transaction(function()use($organisationId,$userId,$projectId,$id,$type,$source,$days):array{$this->pdo->prepare('INSERT INTO site_programme_impacts(id,organisation_id,project_id,source_type,source_object_id,estimated_days,requires_planner_review,created_by) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:project),:type,UNHEX(:source),:days,TRUE,UNHEX(:actor))')->execute(['id'=>$id,'organisation'=>$organisationId,'project'=>$projectId,'type'=>$type,'source'=>$source,'days'=>$days,'actor'=>$userId]);$this->record($organisationId,$projectId,$userId,'programme_impact','Programme impact assessment','pending_review',['impactId'=>$id,'sourceType'=>$type,'sourceObjectId'=>$source,'estimatedDays'=>$days,'requiresPlannerReview'=>true],[$source]);$this->inbox($organisationId,$projectId,'site_execution.programme_review_required','Planner review required','Field event has a recorded programme impact.','medium',['impactId'=>$id]);$this->audit($organisationId,$userId,'site_execution.programme_impact_created','site_programme_impact',$id,['sourceType'=>$type,'estimatedDays'=>$days]);return $this->programmeImpact($organisationId,$projectId,$id);});
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createPaymentBlocker(string $organisationId,string $userId,string $role,string $projectId,array $input):array
    {
        $this->assertProject($organisationId,$userId,$projectId);$this->confirmed($input,'site_payment_blocker_confirmation_required');$id=Identifier::generate();$type=$this->choice($input['sourceType']??null,['ncr','snag','inspection'],'site_payment_blocker_source_invalid');$source=$this->id($input['sourceObjectId']??null,'site_payment_blocker_source_invalid');$this->assertSource($organisationId,$projectId,$type,$source);$reason=$this->text($input['reason']??null,1000,'site_payment_blocker_reason_required');$severity=$this->choice($input['severity']??null,self::SEVERITIES,'site_payment_blocker_severity_invalid');
        return $this->transaction(function()use($organisationId,$userId,$projectId,$id,$type,$source,$reason,$severity):array{try{$this->pdo->prepare("INSERT INTO site_payment_blockers(id,organisation_id,project_id,source_type,source_object_id,reason,severity,status,moves_money,created_by) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:project),:type,UNHEX(:source),:reason,:severity,'active',FALSE,UNHEX(:actor))")->execute(['id'=>$id,'organisation'=>$organisationId,'project'=>$projectId,'type'=>$type,'source'=>$source,'reason'=>$reason,'severity'=>$severity,'actor'=>$userId]);}catch(PDOException $e){if($e->getCode()==='23000')throw new SiteExecutionException('site_payment_blocker_exists');throw$e;}$this->record($organisationId,$projectId,$userId,'payment_blocker','Payment recommendation blocker','pending_review',['blockerId'=>$id,'sourceType'=>$type,'sourceObjectId'=>$source,'reason'=>$reason,'severity'=>$severity,'movesMoney'=>false],[$source]);$this->inbox($organisationId,$projectId,'site_execution.payment_blocker','Payment recommendation blocker',$reason,$severity,['blockerId'=>$id,'sourceType'=>$type,'sourceObjectId'=>$source,'movesMoney'=>false]);$this->recommend($organisationId,$projectId,$source,'field_commercial_monitor','Resolve field-control payment blocker',$reason,$severity);$this->audit($organisationId,$userId,'site_execution.payment_blocker_created','site_payment_blocker',$id,['sourceType'=>$type,'sourceObjectId'=>$source,'movesMoney'=>false]);return $this->paymentBlocker($organisationId,$projectId,$id);});
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function clearPaymentBlocker(string $organisationId,string $userId,string $role,string $blockerId,array $input):array
    {
        $this->confirmed($input,'site_payment_blocker_clear_confirmation_required');$reason=$this->text($input['reason']??null,1000,'site_payment_blocker_clear_reason_required');$row=$this->scopeItem('site_payment_blockers',$organisationId,$userId,$blockerId,'site_payment_blocker_not_found');if($row['status']!=='active')throw new SiteExecutionException('site_payment_blocker_transition_invalid');$sourceId=bin2hex($row['source_object_id']);$table=match($row['source_type']){'ncr'=>'site_ncrs','snag'=>'site_snags','inspection'=>'site_inspections'};$resolved=match($row['source_type']){'ncr'=>'verified_closed','snag','inspection'=>'closed'};$source=$this->pdo->prepare("SELECT status FROM $table WHERE organisation_id=UNHEX(:organisation) AND project_id=UNHEX(:project) AND id=UNHEX(:id)");$source->execute(['organisation'=>$organisationId,'project'=>$row['project_id'],'id'=>$sourceId]);if($source->fetchColumn()!==$resolved)throw new SiteExecutionException('site_payment_blocker_source_unresolved');
        return$this->transaction(function()use($organisationId,$userId,$blockerId,$row,$reason,$sourceId):array{$this->pdo->prepare("UPDATE site_payment_blockers SET status='cleared',cleared_by=UNHEX(:actor),cleared_at=UTC_TIMESTAMP(6),moves_money=FALSE WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation)")->execute(['actor'=>$userId,'id'=>$blockerId,'organisation'=>$organisationId]);$this->record($organisationId,$row['project_id'],$userId,'payment_blocker','Payment recommendation blocker cleared','approved',['blockerId'=>$blockerId,'sourceObjectId'=>$sourceId,'reason'=>$reason,'movesMoney'=>false],[$sourceId]);$this->audit($organisationId,$userId,'site_execution.payment_blocker_cleared','site_payment_blocker',$blockerId,['reason'=>$reason,'movesMoney'=>false]);return$this->paymentBlocker($organisationId,$row['project_id'],$blockerId);});
    }

    /** @return array<string,mixed> */
    public function workspace(string $organisationId,string $userId,string $projectId):array
    {
        $this->assertProject($organisationId,$userId,$projectId);
        return ['dailyLogs'=>$this->all('site_daily_logs',$organisationId,$projectId),'evidence'=>$this->all('site_field_evidence',$organisationId,$projectId),'rfis'=>$this->workspaceRfis($organisationId,$projectId),'siteInstructions'=>$this->all('site_instructions',$organisationId,$projectId),'ncrs'=>$this->all('site_ncrs',$organisationId,$projectId),'snags'=>$this->all('site_snags',$organisationId,$projectId),'inspections'=>$this->all('site_inspections',$organisationId,$projectId),'delayWarnings'=>$this->all('site_delay_warnings',$organisationId,$projectId),'programmeImpacts'=>$this->all('site_programme_impacts',$organisationId,$projectId),'paymentBlockers'=>$this->all('site_payment_blockers',$organisationId,$projectId),'recommendations'=>$this->all('site_agent_recommendations',$organisationId,$projectId),'boundaries'=>['delayWarningsGrantEntitlement'=>false,'internalInspectionsAreStatutoryApprovals'=>false,'paymentBlockersMoveMoney'=>false]];
    }

    /** @return list<array<string,mixed>> */
    private function workspaceRfis(string $organisationId, string $projectId): array
    {
        $rfis = $this->all('site_rfis', $organisationId, $projectId);
        if ($rfis === []) return [];
        // One response per RFI is enforced by uq_site_rfi_response. Load the
        // project batch so refreshing the register retains saved answers.
        $statement = $this->pdo->prepare('SELECT LOWER(HEX(r.rfi_id)) rfi_id,LOWER(HEX(r.id)) id,r.answer,r.requires_instruction,LOWER(HEX(r.responded_by)) responded_by,r.issued_at FROM site_rfi_responses r JOIN site_rfis f ON f.id=r.rfi_id AND f.organisation_id=r.organisation_id WHERE f.organisation_id=UNHEX(:organisation) AND f.project_id=UNHEX(:project)');
        $statement->execute(['organisation'=>$organisationId,'project'=>$projectId]);
        $responses = [];
        foreach ($statement->fetchAll() as $row) {
            $rfiId = $row['rfi_id'];
            unset($row['rfi_id']);
            $responses[$rfiId] = $this->camel($row);
        }
        foreach ($rfis as &$rfi) $rfi['response'] = $responses[$rfi['id']] ?? null;
        unset($rfi);
        return $rfis;
    }

    /** @param list<string> $evidence */
    private function fieldItemOutputs(string $organisationId,string $projectId,string $userId,string $recordType,string $id,string $title,string $severity,string $detail,bool $blocks,array $evidence):void
    {
        $this->record($organisationId,$projectId,$userId,$recordType,$title,'pending_review',['sourceId'=>$id,'severity'=>$severity,'blocksPayment'=>$blocks,'evidenceIds'=>$evidence],array_merge([$id],$evidence));$event=$recordType==='snag_item'?'site_execution.snag_allocation_required':'site_execution.ncr_corrective_action_required';$this->inbox($organisationId,$projectId,$event,$title,$detail,$severity,['sourceId'=>$id]);$this->recommend($organisationId,$projectId,$id,'field_quality_monitor',$title,$detail,$severity);$subject=$recordType==='snag_item'?'site_snag':'site_ncr';$action=$recordType==='snag_item'?'site_execution.snag_created':'site_execution.ncr_created';$this->audit($organisationId,$userId,$action,$subject,$id,['severity'=>$severity,'blocksPayment'=>$blocks]);
    }

    /** @param array<string,mixed> $metadata */
    private function audit(string $organisationId,string $userId,string $action,string $subject,string $id,array $metadata):void{(new AuditService($this->pdo))->append($organisationId,$userId,$action,$subject,$id,$metadata);}
    /** @param array<string,mixed> $payload @param list<string> $links */
    private function record(string $organisationId,string $projectId,string $userId,string $type,string $title,string $status,array $payload,array $links):void{$this->pdo->prepare("INSERT INTO project_records(id,organisation_id,project_id,phase,module_key,record_type,title,status,payload,approvals,linked_record_ids,created_by) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:project),'construction_execution',:module,:type,:title,:status,:payload,'{\"required\":false}',:links,UNHEX(:actor))")->execute(['id'=>Identifier::generate(),'organisation'=>$organisationId,'project'=>$projectId,'module'=>self::MODULE,'type'=>$type,'title'=>substr($title,0,220),'status'=>$status,'payload'=>$this->json($payload),'links'=>$this->json($links),'actor'=>$userId]);}
    /** @param array<string,mixed> $payload */
    private function inbox(string $organisationId,string $projectId,string $type,string $title,string $detail,string $priority,array $payload):void{$priority=in_array($priority,self::SEVERITIES,true)?$priority:'medium';$this->pdo->prepare('INSERT INTO inbox_items(id,organisation_id,project_id,assigned_role,event_type,title,detail,priority,payload) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:project),:role,:type,:title,:detail,:priority,:payload)')->execute(['id'=>Identifier::generate(),'organisation'=>$organisationId,'project'=>$projectId,'role'=>'member','type'=>$type,'title'=>substr($title,0,220),'detail'=>$detail,'priority'=>$priority,'payload'=>$this->json($payload)]);}
    private function recommend(string $organisationId,string $projectId,string $sourceId,string $agent,string $title,string $rationale,string $severity):void{$this->pdo->prepare("INSERT INTO site_agent_recommendations(id,organisation_id,project_id,source_object_id,agent_key,title,rationale,severity,status) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:project),UNHEX(:source),:agent,:title,:rationale,:severity,'open')")->execute(['id'=>Identifier::generate(),'organisation'=>$organisationId,'project'=>$projectId,'source'=>$sourceId,'agent'=>$agent,'title'=>substr($title,0,220),'rationale'=>substr($rationale,0,1000),'severity'=>$severity]);}

    /** @return array<string,mixed> */ private function dailyLog(string$o,string$p,string$id):array{return $this->one('site_daily_logs',$o,$p,$id,['labour','plant','deliveries','visitors','delays','safety_notes']);}
    /** @return array<string,mixed> */ private function evidence(string$o,string$p,string$id):array{return $this->one('site_field_evidence',$o,$p,$id,['metadata']);}
    /** @return array<string,mixed> */ private function rfi(string$o,string$p,string$id):array{$r=$this->one('site_rfis',$o,$p,$id,['linked_document_ids']);$s=$this->pdo->prepare('SELECT LOWER(HEX(id)) id,answer,requires_instruction,LOWER(HEX(responded_by)) responded_by,issued_at FROM site_rfi_responses WHERE organisation_id=UNHEX(:o) AND rfi_id=UNHEX(:id)');$s->execute(['o'=>$o,'id'=>$id]);$response=$s->fetch();$r['response']=$response===false?null:$this->camel($response);return$r;}
    /** @return array<string,mixed> */ private function instruction(string$o,string$p,string$id):array{return $this->one('site_instructions',$o,$p,$id,[]);}
    /** @return array<string,mixed> */ private function ncr(string$o,string$p,string$id):array{return $this->one('site_ncrs',$o,$p,$id,['evidence_ids','corrective_evidence_ids']);}
    /** @return array<string,mixed> */ private function snag(string$o,string$p,string$id):array{return $this->one('site_snags',$o,$p,$id,['evidence_ids','reinspection_evidence_ids']);}
    /** @return array<string,mixed> */ private function inspection(string$o,string$p,string$id):array{return $this->one('site_inspections',$o,$p,$id,['findings','checklist','follow_ups']);}
    /** @return array<string,mixed> */ private function delayWarning(string$o,string$p,string$id):array{return $this->one('site_delay_warnings',$o,$p,$id,[]);}
    /** @return array<string,mixed> */ private function programmeImpact(string$o,string$p,string$id):array{return $this->one('site_programme_impacts',$o,$p,$id,[]);}
    /** @return array<string,mixed> */ private function paymentBlocker(string$o,string$p,string$id):array{return $this->one('site_payment_blockers',$o,$p,$id,[]);}

    /** @param list<string> $jsonColumns @return array<string,mixed> */
    private function one(string$table,string$o,string$p,string$id,array$jsonColumns):array{$s=$this->pdo->prepare("SELECT *,LOWER(HEX(id)) id FROM $table WHERE organisation_id=UNHEX(:o) AND project_id=UNHEX(:p) AND id=UNHEX(:id)");$s->execute(['o'=>$o,'p'=>$p,'id'=>$id]);$row=$s->fetch();if(!is_array($row))throw new SiteExecutionException('site_execution_record_not_found');foreach($jsonColumns as$c)$row[$c]=$this->decode($row[$c]);return$this->camel($row);}
    /** @return list<array<string,mixed>> */
    private function all(string$table,string$o,string$p):array{$s=$this->pdo->prepare("SELECT *,LOWER(HEX(id)) id FROM $table WHERE organisation_id=UNHEX(:o) AND project_id=UNHEX(:p) ORDER BY created_at,id");$s->execute(['o'=>$o,'p'=>$p]);return array_map(fn(array$r):array=>$this->camelJson($r),$s->fetchAll());}
    /** @return array<string,mixed> */
    private function camelJson(array$row):array{foreach($row as$key=>$value)if(is_string($value)&&in_array($key,['labour','plant','deliveries','visitors','delays','safety_notes','metadata','linked_document_ids','evidence_ids','corrective_evidence_ids','reinspection_evidence_ids','findings','checklist','follow_ups'],true))$row[$key]=$this->decode($value);return$this->camel($row);}
    /** @return array<string,mixed> */
    private function camel(array$row):array{$out=[];foreach($row as$key=>$value){if(in_array($key,['organisation_id','project_id'],true))continue;$name=preg_replace_callback('/_([a-z])/',static fn(array$m):string=>strtoupper($m[1]),(string)$key);if(is_string($value)&&strlen($value)===16&&($key==='id'||str_ends_with((string)$key,'_id')||str_ends_with((string)$key,'_by')))$value=bin2hex($value);if(in_array($key,['requires_instruction','variation_flag','blocks_payment','sign_off_required','statutory_approval','grants_entitlement','requires_planner_review','moves_money'],true))$value=(bool)$value;$out[$name]=$value;}return$out;}

    /** @return array<string,mixed> */
    private function scopeItem(string$table,string$o,string$u,string$id,string$error):array{$linked=$table==='site_instructions'?',LOWER(HEX(t.linked_rfi_id)) linked_rfi_id':'';$s=$this->pdo->prepare("SELECT t.*,LOWER(HEX(t.project_id)) project_id$linked FROM $table t JOIN organisation_memberships m ON m.organisation_id=t.organisation_id AND m.user_id=UNHEX(:u) AND m.status='active' WHERE t.organisation_id=UNHEX(:o) AND t.id=UNHEX(:id)");$s->execute(['u'=>$u,'o'=>$o,'id'=>$id]);$r=$s->fetch();if(!is_array($r))throw new SiteExecutionException($error);return$r;}
    private function assertProject(string$o,string$u,string$p):void{$s=$this->pdo->prepare("SELECT 1 FROM projects p JOIN organisation_memberships m ON m.organisation_id=p.organisation_id AND m.user_id=UNHEX(:u) AND m.status='active' WHERE p.organisation_id=UNHEX(:o) AND p.id=UNHEX(:p)");$s->execute(['u'=>$u,'o'=>$o,'p'=>$p]);if($s->fetchColumn()===false)throw new SiteExecutionException('site_project_not_found');}
    private function assertOwned(string$table,string$o,string$p,string$id,string$error):void{$s=$this->pdo->prepare("SELECT 1 FROM $table WHERE organisation_id=UNHEX(:o) AND project_id=UNHEX(:p) AND id=UNHEX(:id)");$s->execute(['o'=>$o,'p'=>$p,'id'=>$id]);if($s->fetchColumn()===false)throw new SiteExecutionException($error);}
    private function assertProfessionalAuthority(string$o,string$u,string$role,string$p):void{if(in_array($role,['owner','administrator'],true))return;$s=$this->pdo->prepare("SELECT 1 FROM project_team_members WHERE organisation_id=UNHEX(:o) AND project_id=UNHEX(:p) AND user_id=UNHEX(:u) AND status='active' AND discipline IN ('architect','engineer','quantity_surveyor')");$s->execute(['o'=>$o,'p'=>$p,'u'=>$u]);if($s->fetchColumn()===false)throw new SiteExecutionException('site_professional_authority_required');}
    /** @param list<string> $ids */ private function assertEvidence(string$o,string$p,array$ids):void{foreach($ids as$id)$this->assertOwned('site_field_evidence',$o,$p,$id,'site_evidence_not_found');}
    private function assertSource(string$o,string$p,string$type,string$id):void{$table=match($type){'delay_warning'=>'site_delay_warnings','site_instruction'=>'site_instructions','ncr'=>'site_ncrs','snag'=>'site_snags','inspection'=>'site_inspections'};$this->assertOwned($table,$o,$p,$id,'site_source_not_found');}

    /** @return array<string,mixed> */
    private function snagTransition(string$o,string$u,string$id,string$from,string$to,string$action,array$metadata):array{$row=$this->scopeItem('site_snags',$o,$u,$id,'site_snag_not_found');if($row['status']!==$from)throw new SiteExecutionException('site_snag_transition_invalid');return$this->transaction(function()use($o,$u,$id,$row,$from,$to,$action,$metadata):array{$s=$this->pdo->prepare("UPDATE site_snags SET status=:to WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:o) AND status=:from");$s->execute(['to'=>$to,'id'=>$id,'o'=>$o,'from'=>$from]);$this->audit($o,$u,$action,'site_snag',$id,$metadata);return$this->snag($o,$row['project_id'],$id);});}

    /** @template T @param callable():T $operation @return T */
    private function transaction(callable$operation):mixed{$this->pdo->beginTransaction();try{$result=$operation();$this->pdo->commit();return$result;}catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}}
    /** @param array<string,mixed> $input */ private function confirmed(array$input,string$error):void{if(($input['humanConfirmed']??false)!==true)throw new SiteExecutionException($error);}
    private function text(mixed$v,int$max,string$error):string{if(!is_string($v)||trim($v)===''||mb_strlen(trim($v))>$max)throw new SiteExecutionException($error);return trim($v);}
    private function nullableText(mixed$v,int$max,string$error):?string{if($v===null||$v==='')return null;return$this->text($v,$max,$error);}
    private function choice(mixed$v,array$choices,string$error):string{if(!is_string($v)||!in_array($v,$choices,true))throw new SiteExecutionException($error);return$v;}
    private function id(mixed$v,string$error):string{if(!is_string($v)||preg_match('/\A[a-f0-9]{32}\z/i',$v)!==1)throw new SiteExecutionException($error);return strtolower($v);}
    private function nullableId(mixed$v,string$error):?string{return$v===null||$v===''?null:$this->id($v,$error);}
    /** @return list<string> */ private function ids(mixed$v,string$error):array{if(!is_array($v)||count($v)>100)throw new SiteExecutionException($error);$out=[];foreach($v as$id)$out[]=$this->id($id,$error);return array_values(array_unique($out));}
    /** @return list<string> */ private function strings(mixed$v,int$limit,int$max,string$error):array{if(!is_array($v)||count($v)>$limit)throw new SiteExecutionException($error);$out=[];foreach($v as$item)$out[]=$this->text($item,$max,$error);return$out;}
    /** @return array<string,int> */
    /** One daily log per project per date; a repeat is a conflict, not a server error. */
    private function assertDailyLogAbsent(string $organisationId, string $projectId, string $logDate): void
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM site_daily_logs WHERE organisation_id=UNHEX(:organisation) AND project_id=UNHEX(:project) AND log_date=:log_date');
        $statement->execute(['organisation' => $organisationId, 'project' => $projectId, 'log_date' => $logDate]);
        if ($statement->fetchColumn() !== false) {
            throw new SiteExecutionException('site_daily_log_exists');
        }
    }
    private function labour(mixed$v):array{if(!is_array($v)||count($v)>100)throw new SiteExecutionException('site_daily_log_labour_invalid');$out=[];foreach($v as$key=>$count){if(!is_string($key)||trim($key)===''||!is_int($count)||$count<0||$count>100000)throw new SiteExecutionException('site_daily_log_labour_invalid');$out[substr(trim($key),0,100)]=$count;}return$out;}
    /** @return list<array{item:string,outcome:string}> */ private function checklist(mixed$v):array{if(!is_array($v)||count($v)>300)throw new SiteExecutionException('site_inspection_checklist_invalid');$out=[];foreach($v as$item){if(!is_array($item))throw new SiteExecutionException('site_inspection_checklist_invalid');$out[]=['item'=>$this->text($item['item']??null,500,'site_inspection_checklist_invalid'),'outcome'=>$this->choice($item['outcome']??null,['pass','fail','not_applicable'],'site_inspection_checklist_invalid')];}return$out;}
    private function integer(mixed$v,int$min,int$max,string$error):int{if(!is_int($v)||$v<$min||$v>$max)throw new SiteExecutionException($error);return$v;}
    private function date(mixed$v,string$error):string{if(!is_string($v)||DateTimeImmutable::createFromFormat('!Y-m-d',$v)?->format('Y-m-d')!==$v)throw new SiteExecutionException($error);return$v;}
    private function dateTime(mixed$v,string$error):string{if(!is_string($v))throw new SiteExecutionException($error);try{$d=new DateTimeImmutable($v);}catch(Throwable){throw new SiteExecutionException($error);}return$d->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');}
    /** @return array{?string,?string} */ private function coordinates(mixed$lat,mixed$lng):array{if($lat===null&&$lng===null)return[null,null];if(!is_float($lat)&&!is_int($lat)||!is_float($lng)&&!is_int($lng)||$lat< -90||$lat>90||$lng< -180||$lng>180)throw new SiteExecutionException('site_evidence_coordinates_invalid');return[number_format((float)$lat,7,'.',''),number_format((float)$lng,7,'.','')];}
    private function json(mixed$v):string{return json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);}
    private function decode(mixed$v):array{if(!is_string($v))return[];$decoded=json_decode($v,true,512,JSON_THROW_ON_ERROR);return is_array($decoded)?$decoded:[];}
}
