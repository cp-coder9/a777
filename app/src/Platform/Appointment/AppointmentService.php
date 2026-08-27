<?php

declare(strict_types=1);

namespace Architex\Platform\Appointment;

use Architex\Platform\Audit\AuditService;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Project\ProjectContext;
use Architex\Platform\Project\ProjectPhase;
use Architex\Platform\Records\ProjectRecordService;
use PDO;
use PDOException;
use Throwable;

final readonly class AppointmentService
{
    public function __construct(private PDO $pdo) {}

    /** @param array<string,mixed> $facts @return array<string,mixed> */
    public function createFromAcceptedProposal(string $organisationId, string $userId, string $role, string $proposalId, array $facts): array
    {
        $this->assertManager($organisationId, $userId, $role);
        $facts = $this->facts($facts);
        $missing = $this->missingFacts($facts);
        $this->pdo->beginTransaction();
        try {
            $proposal = $this->acceptedProposal($organisationId, $proposalId, true);
            $id = Identifier::generate();
            $snapshot = $this->appointmentSnapshot($organisationId, $proposal);
            $statement = $this->pdo->prepare("INSERT INTO professional_appointments(id,organisation_id,project_id,proposal_id,proposal_snapshot,proposal_hash,project_facts,missing_facts,created_by) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:project_id),UNHEX(:proposal_id),:proposal_snapshot,:proposal_hash,:project_facts,:missing_facts,UNHEX(:created_by))");
            $statement->execute([
                'id' => $id, 'organisation_id' => $organisationId, 'project_id' => $proposal['projectId'], 'proposal_id' => $proposalId,
                'proposal_snapshot' => $this->json($snapshot), 'proposal_hash' => $proposal['auditHash'],
                'project_facts' => $this->json($facts), 'missing_facts' => $this->json($missing), 'created_by' => $userId,
            ]);
            $this->createChecklist($id, $missing);
            $this->createTasks($id, (string)$proposal['projectId']);
            $this->createDocuments($id, (string)$proposal['projectId'], $snapshot, 1);
            $this->inbox($organisationId, (string)$proposal['projectId'], $userId, 'appointment_awaiting_professional_confirmation', 'Professional confirmation required before formal appointment issue.', 'high', ['appointmentId' => $id]);
            if ($missing !== []) $this->inbox($organisationId, (string)$proposal['projectId'], $userId, 'kickoff_blocked_missing_facts', count($missing) . ' project facts are missing for kickoff readiness.', 'high', ['appointmentId' => $id, 'missingFacts' => $missing]);
            (new ProjectRecordService($this->pdo))->issue(new ProjectContext($organisationId, $userId, (string)$proposal['projectId'], $role), ProjectPhase::Onboarding, 'appointment_kickoff', 'appointment_baseline', 'Appointment baseline revision 1', 'pending_review', ['appointmentId' => $id, 'proposalSnapshot' => $snapshot, 'projectFacts' => $facts, 'missingFacts' => $missing], ['required' => true, 'professionalConfirmed' => false]);
            (new AuditService($this->pdo))->append($organisationId, $userId, 'appointment.created', 'professional_appointment', $id, ['proposalId' => $proposalId, 'proposalHash' => $proposal['auditHash'], 'missingFacts' => $missing]);
            $this->pdo->commit();
            return $this->get($organisationId, $userId, $id);
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if ($exception instanceof PDOException && ($exception->errorInfo[1] ?? null) === 1062) throw new AppointmentException('appointment_already_exists');
            throw $exception;
        }
    }

    /** @param array<string,mixed> $confirmation @return array<string,mixed> */
    public function confirmProfessional(string $organisationId, string $userId, string $role, string $appointmentId, array $confirmation): array
    {
        $this->assertManager($organisationId, $userId, $role);
        if (($confirmation['responsibilityConfirmed'] ?? false) !== true) throw new AppointmentException('appointment_professional_confirmation_required');
        $reason = $this->text($confirmation['confirmationReason'] ?? null, 500, 'appointment_confirmation_reason_required');
        $this->pdo->beginTransaction();
        try {
            $appointment = $this->lock($organisationId, $appointmentId);
            if ($appointment['professional_confirmed_at'] !== null) throw new AppointmentException('appointment_already_confirmed');
            $missing = json_decode((string)$appointment['missing_facts'], true, 64, JSON_THROW_ON_ERROR);
            $status = $missing === [] ? 'confirmed' : 'pending_professional_confirmation';
            $update = $this->pdo->prepare('UPDATE professional_appointments SET status=:status,professional_confirmed_by=UNHEX(:user_id),professional_confirmation_reason=:reason,professional_confirmed_at=UTC_TIMESTAMP(6) WHERE id=UNHEX(:id)');
            $update->execute(['status' => $status, 'user_id' => $userId, 'reason' => $reason, 'id' => $appointmentId]);
            $this->pdo->prepare("UPDATE kickoff_checklist_items SET completed=1,completed_at=UTC_TIMESTAMP(6) WHERE appointment_id=UNHEX(:id) AND item_key='confirm_appointment'")->execute(['id' => $appointmentId]);
            if ($missing === []) {
                $this->pdo->prepare("UPDATE kickoff_checklist_items SET completed=1,completed_at=UTC_TIMESTAMP(6) WHERE appointment_id=UNHEX(:id) AND item_key='verify_property'")->execute(['id' => $appointmentId]);
                $this->inbox($organisationId, (string)$appointment['project_id'], $userId, 'kickoff_ready', 'Project kickoff checklist is ready for team action.', 'medium', ['appointmentId' => $appointmentId]);
            }
            (new AuditService($this->pdo))->append($organisationId, $userId, 'appointment.professional_confirmed', 'professional_appointment', $appointmentId, ['reason' => $reason, 'kickoffReady' => $missing === []]);
            $this->pdo->commit();
            return $this->get($organisationId, $userId, $appointmentId);
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    /** @param array<string,mixed> $facts @return array<string,mixed> */
    public function reviseFromAcceptedProposal(string $organisationId, string $userId, string $role, string $appointmentId, string $proposalId, array $facts, string $reason): array
    {
        $this->assertManager($organisationId, $userId, $role);
        $reason = $this->text($reason, 500, 'appointment_revision_reason_required');
        $facts = $this->facts($facts);
        $missing = $this->missingFacts($facts);
        $this->pdo->beginTransaction();
        try {
            $previous = $this->lock($organisationId, $appointmentId);
            $proposal = $this->acceptedProposal($organisationId, $proposalId, true);
            if ($proposal['projectId'] !== $previous['project_id']) throw new AppointmentException('appointment_revision_project_mismatch');
            $revision = (int)$previous['revision'] + 1;
            $id = Identifier::generate();
            $snapshot = $this->appointmentSnapshot($organisationId, $proposal);
            $statement = $this->pdo->prepare("INSERT INTO professional_appointments(id,organisation_id,project_id,proposal_id,supersedes_appointment_id,revision,proposal_snapshot,proposal_hash,project_facts,missing_facts,created_by) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:project_id),UNHEX(:proposal_id),UNHEX(:supersedes_id),:revision,:proposal_snapshot,:proposal_hash,:project_facts,:missing_facts,UNHEX(:created_by))");
            $statement->execute(['id'=>$id,'organisation_id'=>$organisationId,'project_id'=>$proposal['projectId'],'proposal_id'=>$proposalId,'supersedes_id'=>$appointmentId,'revision'=>$revision,'proposal_snapshot'=>$this->json($snapshot),'proposal_hash'=>$proposal['auditHash'],'project_facts'=>$this->json($facts),'missing_facts'=>$this->json($missing),'created_by'=>$userId]);
            $this->createChecklist($id,$missing);
            $this->createTasks($id,(string)$proposal['projectId']);
            $this->createDocuments($id,(string)$proposal['projectId'],$snapshot,$revision);
            $this->inbox($organisationId,(string)$proposal['projectId'],$userId,'appointment_revision_awaiting_professional_confirmation','Appointment revision ' . $revision . ' requires professional confirmation.','high',['appointmentId'=>$id,'supersedesAppointmentId'=>$appointmentId,'reason'=>$reason]);
            (new ProjectRecordService($this->pdo))->issue(new ProjectContext($organisationId,$userId,(string)$proposal['projectId'],$role),ProjectPhase::Appointment,'appointment_kickoff','appointment_revision','Appointment revision ' . $revision,'pending_review',['appointmentId'=>$id,'supersedesAppointmentId'=>$appointmentId,'proposalSnapshot'=>$snapshot,'projectFacts'=>$facts,'reason'=>$reason],['required'=>true,'professionalConfirmed'=>false]);
            (new AuditService($this->pdo))->append($organisationId,$userId,'appointment.revision_created','professional_appointment',$id,['supersedesAppointmentId'=>$appointmentId,'proposalId'=>$proposalId,'proposalHash'=>$proposal['auditHash'],'revision'=>$revision,'reason'=>$reason]);
            $this->pdo->commit();
            return $this->get($organisationId,$userId,$id);
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if ($exception instanceof PDOException && ($exception->errorInfo[1] ?? null) === 1062) throw new AppointmentException('appointment_already_exists');
            throw $exception;
        }
    }

    /** @param array<string,mixed> $approval @return array<string,mixed> */
    public function issueFormalAppointment(string $organisationId, string $userId, string $role, string $appointmentId, array $approval): array
    {
        $this->assertManager($organisationId, $userId, $role);
        if (($approval['humanApprovalConfirmed'] ?? false) !== true) throw new AppointmentException('appointment_human_approval_required');
        $reason = $this->text($approval['issueReason'] ?? null, 500, 'appointment_issue_reason_required');
        $this->pdo->beginTransaction();
        try {
            $row = $this->lock($organisationId, $appointmentId);
            if ($row['status'] !== 'confirmed' || $row['professional_confirmed_at'] === null || json_decode((string)$row['missing_facts'], true, 64, JSON_THROW_ON_ERROR) !== []) throw new AppointmentException('appointment_not_ready');
            if ($row['formally_issued_at'] !== null) throw new AppointmentException('appointment_already_issued');
            $this->pdo->prepare('UPDATE professional_appointments SET formally_issued_by=UNHEX(:user_id),formal_issue_reason=:reason,formally_issued_at=UTC_TIMESTAMP(6) WHERE id=UNHEX(:id)')->execute(['user_id' => $userId, 'reason' => $reason, 'id' => $appointmentId]);
            $this->pdo->prepare("UPDATE appointment_documents SET status='issued' WHERE appointment_id=UNHEX(:id) AND kind='appointment_letter_draft'")->execute(['id' => $appointmentId]);
            $this->pdo->prepare("UPDATE projects SET current_phase='appointment',version=version+1 WHERE id=UNHEX(:project_id) AND organisation_id=UNHEX(:organisation_id)")->execute(['project_id' => $row['project_id'], 'organisation_id' => $organisationId]);
            $snapshot = json_decode((string)$row['proposal_snapshot'], true, 64, JSON_THROW_ON_ERROR);
            (new ProjectRecordService($this->pdo))->issue(new ProjectContext($organisationId, $userId, (string)$row['project_id'], $role), ProjectPhase::Appointment, 'appointment_kickoff', 'professional_appointment', 'Professional appointment revision ' . $row['revision'], 'issued', ['appointmentId' => $appointmentId, 'revision' => (int)$row['revision'], 'proposalSnapshot' => $snapshot, 'projectFacts' => json_decode((string)$row['project_facts'], true, 64, JSON_THROW_ON_ERROR), 'issueReason' => $reason], ['required' => true, 'approvedBy' => $userId, 'humanApprovalConfirmed' => true]);
            $this->inbox($organisationId, (string)$row['project_id'], $userId, 'appointment_formally_issued', 'Professional appointment issued after explicit human approval.', 'medium', ['appointmentId' => $appointmentId, 'revision' => (int)$row['revision']]);
            (new AuditService($this->pdo))->append($organisationId, $userId, 'appointment.formally_issued', 'professional_appointment', $appointmentId, ['reason' => $reason, 'revision' => (int)$row['revision'], 'proposalHash' => $row['proposal_hash']]);
            $this->pdo->commit();
            return $this->get($organisationId, $userId, $appointmentId);
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    /** @return array<string,mixed> */
    public function get(string $organisationId, string $userId, string $appointmentId): array
    {
        $this->assertMember($organisationId, $userId);
        $statement = $this->pdo->prepare("SELECT LOWER(HEX(id)) id,LOWER(HEX(project_id)) projectId,LOWER(HEX(proposal_id)) proposalId,LOWER(HEX(supersedes_appointment_id)) supersedesAppointmentId,revision,status,proposal_snapshot,proposal_hash proposalHash,project_facts,missing_facts,professional_confirmation_reason professionalConfirmationReason,professional_confirmed_at professionalConfirmedAt,requires_human_approval requiresHumanApproval,formal_issue_reason formalIssueReason,formally_issued_at formallyIssuedAt,created_at createdAt FROM professional_appointments WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation_id)");
        $statement->execute(['id' => $appointmentId, 'organisation_id' => $organisationId]);
        $row = $statement->fetch();
        if (!is_array($row)) throw new AppointmentException('appointment_not_found');
        $row['revision'] = (int)$row['revision'];
        $row['requiresHumanApproval'] = (bool)$row['requiresHumanApproval'];
        $row['proposalSnapshot'] = json_decode((string)$row['proposal_snapshot'], true, 64, JSON_THROW_ON_ERROR);
        $row['projectFacts'] = json_decode((string)$row['project_facts'], true, 64, JSON_THROW_ON_ERROR);
        $row['missingFacts'] = json_decode((string)$row['missing_facts'], true, 64, JSON_THROW_ON_ERROR);
        unset($row['proposal_snapshot'], $row['project_facts'], $row['missing_facts']);
        $row['readinessGates'] = $this->readinessGates($row);
        $ready = true;
        foreach ($row['readinessGates'] as $gate) if (!$gate['passed']) $ready = false;
        $row['kickoffReadiness'] = $ready ? 'ready' : 'blocked';
        $row['checklist'] = $this->checklist($appointmentId);
        $row['initialTasks'] = $this->tasks($appointmentId);
        $row['documents'] = $this->documents($appointmentId);
        $row['recommendations'] = $this->recommendations($row);
        return $row;
    }

    /** @return list<array<string,mixed>> */
    public function list(string $organisationId, string $userId): array
    {
        $this->assertMember($organisationId, $userId);
        $statement = $this->pdo->prepare("SELECT LOWER(HEX(id)) id FROM professional_appointments WHERE organisation_id=UNHEX(:organisation_id) ORDER BY created_at DESC,id");
        $statement->execute(['organisation_id' => $organisationId]);
        return array_map(fn(string $id): array => $this->get($organisationId, $userId, $id), $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return array<string,mixed> */
    public function forProject(string $organisationId,string $userId,string $projectId):array
    {
        $this->assertMember($organisationId,$userId);
        $statement=$this->pdo->prepare('SELECT LOWER(HEX(id)) id FROM professional_appointments WHERE organisation_id=UNHEX(:organisation_id) AND project_id=UNHEX(:project_id) ORDER BY revision DESC,created_at DESC LIMIT 1');
        $statement->execute(['organisation_id'=>$organisationId,'project_id'=>$projectId]);
        $id=$statement->fetchColumn();
        if(!is_string($id))throw new AppointmentException('appointment_not_found');
        return $this->get($organisationId,$userId,$id);
    }

    /** @return array<string,mixed> */
    private function acceptedProposal(string $organisationId, string $proposalId, bool $lock): array
    {
        $suffix = $lock ? ' FOR UPDATE' : '';
        $statement = $this->pdo->prepare("SELECT LOWER(HEX(p.id)) id,LOWER(HEX(p.project_id)) projectId,LOWER(HEX(p.fee_run_id)) feeRunId,p.title,p.client_name clientName,p.client_email clientEmail,p.version,p.status,p.document_snapshot,p.audit_hash auditHash,p.accepted_at acceptedAt,LOWER(HEX(p.created_by)) professionalId,LOWER(HEX(r.id)) clientAcceptanceId,r.accepted_by acceptedBy,r.accepted_at recipientAcceptedAt,pr.project_name projectName,o.name companyName,u.email professionalName FROM professional_proposals p JOIN proposal_acceptance_requests r ON r.proposal_id=p.id AND r.status='accepted' JOIN projects pr ON pr.id=p.project_id AND pr.organisation_id=p.organisation_id JOIN organisations o ON o.id=p.organisation_id JOIN users u ON u.id=p.created_by WHERE p.id=UNHEX(:id) AND p.organisation_id=UNHEX(:organisation_id) AND p.status='accepted'" . $suffix);
        $statement->execute(['id' => $proposalId, 'organisation_id' => $organisationId]);
        $row = $statement->fetch();
        if (!is_array($row)) throw new AppointmentException('accepted_proposal_required');
        return $row;
    }

    /** @param array<string,mixed> $proposal @return array<string,mixed> */
    private function appointmentSnapshot(string $organisationId, array $proposal): array
    {
        $document = json_decode((string)$proposal['document_snapshot'], true, 64, JSON_THROW_ON_ERROR);
        return [
            'proposalId' => $proposal['id'], 'proposalRevisionId' => $proposal['id'], 'proposalVersion' => (int)$proposal['version'],
            'acceptedAtIso' => $proposal['recipientAcceptedAt'], 'clientAcceptanceId' => $proposal['clientAcceptanceId'],
            'clientId' => 'external:' . hash('sha256', strtolower((string)$proposal['clientEmail'])), 'clientName' => $proposal['acceptedBy'],
            'professionalId' => $proposal['professionalId'], 'professionalName' => $proposal['professionalName'], 'companyName' => $proposal['companyName'],
            'projectName' => $proposal['projectName'],
            'scopeSnapshotId' => hash('sha256', $this->json(['assumptions' => $document['assumptions'], 'exclusions' => $document['exclusions']])),
            'termsSnapshotId' => hash('sha256', $this->json($document['terms'])),
            'feeSnapshotId' => $proposal['feeRunId'],
            'acceptedTotal' => ['currency' => 'ZAR', 'amount' => $document['feeSnapshot']['output']['totalInclVat']],
            'sourceCalculatorVersion' => $document['feeSnapshot']['toolVersion'] ?? null,
            'immutabilityHash' => $proposal['auditHash'], 'exactProposalDocument' => $document,
        ];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function facts(array $input): array
    {
        return [
            'municipality' => $this->optional($input['municipality'] ?? null, 180), 'province' => $this->optional($input['province'] ?? null, 100),
            'propertyDescription' => $this->optional($input['propertyDescription'] ?? null, 500), 'erfNumber' => $this->optional($input['erfNumber'] ?? null, 120),
            'landUseOrZoningKnown' => ($input['landUseOrZoningKnown'] ?? false) === true,
            'professionalBody' => $this->optional($input['professionalBody'] ?? null, 120),
            'professionalRegistrationNumber' => $this->optional($input['professionalRegistrationNumber'] ?? null, 120),
        ];
    }

    /** @param array<string,mixed> $facts @return list<string> */
    private function missingFacts(array $facts): array
    {
        $missing = [];
        foreach (['municipality' => 'Municipality is required.', 'province' => 'Province is required.', 'professionalBody' => 'Professional body is required.', 'professionalRegistrationNumber' => 'Professional registration number is required.'] as $key => $label) if ($facts[$key] === null) $missing[] = $label;
        if ($facts['propertyDescription'] === null && $facts['erfNumber'] === null) $missing[] = 'Either a property description or erf number is required.';
        return $missing;
    }

    /** @param list<string> $missing */
    private function createChecklist(string $appointmentId, array $missing): void
    {
        $items = [
            ['confirm_appointment','Professional confirms appointment responsibility','lead_professional',1,0],
            ['review_scope','Review locked accepted scope and exclusions','lead_professional',1,1],
            ['verify_property','Verify property/erf and municipal context','client',1,$missing === [] ? 1 : 0],
            ['prepare_brief','Generate first project brief from accepted proposal','platform_agent',0,0],
            ['setup_documents','Create project document register placeholders','platform_agent',0,1],
        ];
        foreach ($missing as $index => $label) $items[] = ['missing_fact_' . ($index + 1), $label, 'client', 1, 0];
        $statement = $this->pdo->prepare('INSERT INTO kickoff_checklist_items(id,appointment_id,item_key,label,owner_role,required,completed,completed_at) VALUES(UNHEX(:id),UNHEX(:appointment_id),:item_key,:label,:owner_role,:required,:completed,IF(:completed_at=1,UTC_TIMESTAMP(6),NULL))');
        foreach ($items as [$key,$label,$owner,$required,$completed]) $statement->execute(['id'=>Identifier::generate(),'appointment_id'=>$appointmentId,'item_key'=>$key,'label'=>$label,'owner_role'=>$owner,'required'=>$required,'completed'=>$completed,'completed_at'=>$completed]);
    }

    private function createTasks(string $appointmentId, string $projectId): void
    {
        $tasks = [['Confirm client brief and success criteria','inception','lead_professional'],['Request existing drawings, title deed and municipal information','inception','client'],['Assess likely municipal submission readiness path','municipal_submission_readiness','lead_professional']];
        $statement=$this->pdo->prepare('INSERT INTO appointment_initial_tasks(id,appointment_id,project_id,title,phase,owner_role) VALUES(UNHEX(:id),UNHEX(:appointment_id),UNHEX(:project_id),:title,:phase,:owner_role)');
        foreach($tasks as [$title,$phase,$owner])$statement->execute(['id'=>Identifier::generate(),'appointment_id'=>$appointmentId,'project_id'=>$projectId,'title'=>$title,'phase'=>$phase,'owner_role'=>$owner]);
    }

    /** @param array<string,mixed> $snapshot */
    private function createDocuments(string $appointmentId,string $projectId,array $snapshot,int $revision):void
    {
        $revisionId='appointment-rev-' . $revision;
        $documents=[['proposal_pdf','Accepted Proposal PDF','placeholder',$snapshot['proposalRevisionId']],['client_acceptance','Client Acceptance Record','ready_to_generate',$snapshot['clientAcceptanceId']],['terms_snapshot','Accepted Terms Snapshot','ready_to_generate',$snapshot['termsSnapshotId']],['appointment_letter_draft','Appointment Letter Draft','requires_human_approval',$revisionId],['project_brief','Project Brief Draft','placeholder',$revisionId],['kickoff_checklist','Kickoff Checklist','ready_to_generate',$revisionId]];
        $statement=$this->pdo->prepare('INSERT INTO appointment_documents(id,appointment_id,project_id,kind,title,status,source_revision_id) VALUES(UNHEX(:id),UNHEX(:appointment_id),UNHEX(:project_id),:kind,:title,:status,:source_revision_id)');
        foreach($documents as [$kind,$title,$status,$source])$statement->execute(['id'=>Identifier::generate(),'appointment_id'=>$appointmentId,'project_id'=>$projectId,'kind'=>$kind,'title'=>$title,'status'=>$status,'source_revision_id'=>$source]);
    }

    /** @param array<string,mixed> $row @return array<string,array{passed:bool,label:string}> */
    private function readinessGates(array $row):array
    {
        $snapshot=$row['proposalSnapshot'];$facts=$row['projectFacts'];
        return [
            'proposal_accepted'=>['passed'=>isset($snapshot['acceptedAtIso']),'label'=>'Proposal is accepted'],
            'client_acceptance'=>['passed'=>!empty($snapshot['clientAcceptanceId']),'label'=>'Client acceptance exists'],
            'professional_confirmation'=>['passed'=>$row['professionalConfirmedAt']!==null,'label'=>'Professional confirmation exists'],
            'project_name'=>['passed'=>!empty($snapshot['projectName']),'label'=>'Project name exists'],
            'municipality'=>['passed'=>!empty($facts['municipality']),'label'=>'Municipality is confirmed'],
            'property_reference'=>['passed'=>!empty($facts['propertyDescription'])||!empty($facts['erfNumber']),'label'=>'Property description or erf reference exists'],
            'scope_terms_snapshots'=>['passed'=>!empty($snapshot['scopeSnapshotId'])&&!empty($snapshot['termsSnapshotId'])&&!empty($snapshot['feeSnapshotId']),'label'=>'Scope, terms and fee snapshots exist'],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function checklist(string $appointmentId):array{$s=$this->pdo->prepare('SELECT item_key itemKey,label,owner_role ownerRole,required,completed,completed_at completedAt FROM kickoff_checklist_items WHERE appointment_id=UNHEX(:id) ORDER BY id');$s->execute(['id'=>$appointmentId]);$rows=$s->fetchAll();foreach($rows as &$r){$r['required']=(bool)$r['required'];$r['completed']=(bool)$r['completed'];}return $rows;}
    /** @return list<array<string,mixed>> */
    private function tasks(string $appointmentId):array{$s=$this->pdo->prepare('SELECT LOWER(HEX(id)) id,title,phase,owner_role ownerRole,status FROM appointment_initial_tasks WHERE appointment_id=UNHEX(:id) ORDER BY created_at,id');$s->execute(['id'=>$appointmentId]);return $s->fetchAll();}
    /** @return list<array<string,mixed>> */
    private function documents(string $appointmentId):array{$s=$this->pdo->prepare("SELECT LOWER(HEX(id)) id,title,kind,status,source_revision_id sourceRevisionId FROM appointment_documents WHERE appointment_id=UNHEX(:id) ORDER BY FIELD(kind,'proposal_pdf','client_acceptance','terms_snapshot','appointment_letter_draft','project_brief','kickoff_checklist')");$s->execute(['id'=>$appointmentId]);return $s->fetchAll();}
    /** @param array<string,mixed> $appointment @return list<array<string,mixed>> */
    private function recommendations(array $appointment):array{$items=[['review_appointment_letter','Review and approve appointment letter before issue','Formal appointment documents require human approval.',true],['generate_project_brief','Generate first project brief from accepted scope','The brief becomes the operational inception baseline.',false],['assign_inception_tasks','Assign initial inception and municipal-readiness tasks',count($appointment['initialTasks']).' starter tasks are available.',false]];if($appointment['missingFacts']!==[])array_unshift($items,['request_missing_facts','Request missing project facts from client',implode(' ',$appointment['missingFacts']),false]);if(($appointment['projectFacts']['landUseOrZoningKnown']??false)!==true)$items[]=['check_zoning','Check land-use/zoning','Municipal readiness depends on verified land-use context.',true];return array_map(static fn(array $i):array=>['id'=>$i[0],'title'=>$i[1],'rationale'=>$i[2],'requiresHumanApproval'=>$i[3]],$items);}

    /** @return array<string,mixed> */
    private function lock(string $organisationId,string $appointmentId):array{$s=$this->pdo->prepare('SELECT LOWER(HEX(project_id)) project_id,revision,status,proposal_snapshot,proposal_hash,project_facts,missing_facts,professional_confirmed_at,formally_issued_at FROM professional_appointments WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation_id) FOR UPDATE');$s->execute(['id'=>$appointmentId,'organisation_id'=>$organisationId]);$row=$s->fetch();if(!is_array($row))throw new AppointmentException('appointment_not_found');return $row;}
    private function inbox(string $organisationId,string $projectId,string $userId,string $type,string $title,string $priority,array $payload):void{$s=$this->pdo->prepare("INSERT INTO inbox_items(id,organisation_id,project_id,assigned_user_id,assigned_role,event_type,title,detail,priority,payload) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:project_id),UNHEX(:user_id),'lead_professional',:event_type,:title,:detail,:priority,:payload)");$s->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'project_id'=>$projectId,'user_id'=>$userId,'event_type'=>$type,'title'=>$title,'detail'=>$title,'priority'=>$priority,'payload'=>$this->json($payload)]);}
    private function assertManager(string $organisationId,string $userId,string $role):void{if(!in_array($role,['owner','admin'],true))throw new AppointmentException('appointment_permission_denied');$this->assertMember($organisationId,$userId);}
    private function assertMember(string $organisationId,string $userId):void{$s=$this->pdo->prepare("SELECT 1 FROM organisation_memberships WHERE organisation_id=UNHEX(:organisation_id) AND user_id=UNHEX(:user_id) AND status='active'");$s->execute(['organisation_id'=>$organisationId,'user_id'=>$userId]);if($s->fetchColumn()===false)throw new AppointmentException('appointment_not_found');}
    private function optional(mixed $value,int $max):?string{if($value===null||trim((string)$value)==='')return null;$value=trim((string)$value);if(mb_strlen($value)>$max)throw new AppointmentException('appointment_fact_invalid');return $value;}
    private function text(mixed $value,int $max,string $reason):string{$value=is_string($value)?trim($value):'';if($value===''||mb_strlen($value)>$max)throw new AppointmentException($reason);return $value;}
    /** @param array<string,mixed>|list<mixed> $value */
    private function json(array $value):string{return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
}
