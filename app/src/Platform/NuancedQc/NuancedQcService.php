<?php

declare(strict_types=1);

namespace Architex\Platform\NuancedQc;

use Architex\Platform\Audit\AuditService;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Inbox\InboxService;
use Architex\Platform\Project\ProjectContext;
use Architex\Platform\Project\ProjectPhase;
use Architex\Platform\Records\ProjectRecordService;
use PDO;
use Throwable;

final readonly class NuancedQcService
{
    private const MODULE = 'nuanced_qc';
    private const REVIEW_ROLES = ['platform_super_admin', 'product_owner', 'qc_reviewer', 'professional_reviewer', 'site_auditor'];
    private const MUTATION_ROLES = ['platform_super_admin', 'product_owner', 'qc_reviewer', 'professional_reviewer', 'site_auditor', 'developer'];

    public function __construct(private PDO $pdo, private NuancedQcRegistry $registry, private NuancedQcEvaluator $evaluator) {}

    /** @return array<string,mixed> */
    public function workspace(string $organisationId, string $userId, string $claimedRole, ?string $projectId = null): array
    {
        $access = $this->accessRole($organisationId, $userId, $claimedRole);
        if ($projectId !== null) $this->project($organisationId, $projectId);
        $this->ensureDefinitions($organisationId, $userId);
        $where = $projectId === null ? '1=1' : 'r.project_id=UNHEX(:project)';
        $statement = $this->pdo->prepare("SELECT LOWER(HEX(r.id)) id,LOWER(HEX(r.project_id)) projectId,r.title,r.scope_type scopeType,r.target,r.recommendation,r.lifecycle_status lifecycleStatus,r.signoff_status signoffStatus,r.expected_version version,DATE_FORMAT(r.updated_at,'%Y-%m-%dT%H:%i:%s.%fZ') updatedAt FROM qc_run_records r WHERE r.organisation_id=UNHEX(:organisation) AND $where ORDER BY r.updated_at DESC");
        $params = ['organisation' => $organisationId]; if ($projectId !== null) $params['project'] = $projectId; $statement->execute($params);
        $definitions = $this->pdo->prepare('SELECT category_key `key`,title,description,sort_order sortOrder FROM qc_control_definitions WHERE organisation_id=UNHEX(:organisation) AND is_active=TRUE ORDER BY sort_order');
        $definitions->execute(['organisation' => $organisationId]);
        $runs = $statement->fetchAll();
        $scopeParams = ['organisation' => $organisationId]; if ($projectId !== null) $scopeParams['project'] = $projectId;
        $categoryResults = $this->workspaceRows("SELECT LOWER(HEX(c.id)) id,LOWER(HEX(c.run_id)) runId,c.category_key category,c.revision,c.status,c.reason FROM qc_category_results c JOIN qc_run_records r ON r.id=c.run_id AND r.organisation_id=c.organisation_id WHERE c.organisation_id=UNHEX(:organisation) AND $where AND c.revision=(SELECT MAX(c2.revision) FROM qc_category_results c2 WHERE c2.run_id=c.run_id AND c2.category_key=c.category_key) ORDER BY c.category_key", $scopeParams);
        $findings = $this->workspaceRows("SELECT LOWER(HEX(f.id)) id,LOWER(HEX(f.run_id)) runId,f.category_key category,f.severity,f.title,f.description,f.evidence_type evidenceType,f.evidence_ref evidenceRef,f.affected_pack affectedPack,f.affected_workflow affectedWorkflow,f.cross_pack_impact crossPackImpact,f.required_fix requiredFix,f.retest_steps retestSteps,f.status,f.expected_version version FROM qc_gap_findings f JOIN qc_run_records r ON r.id=f.run_id AND r.organisation_id=f.organisation_id WHERE f.organisation_id=UNHEX(:organisation) AND $where ORDER BY FIELD(f.severity,'blocker','high','medium','low'),f.created_at DESC", $scopeParams);
        $retests = $this->workspaceRows("SELECT LOWER(HEX(t.id)) id,LOWER(HEX(t.run_id)) runId,LOWER(HEX(t.finding_id)) findingId,t.expected_test expectedTest,t.result_summary resultSummary,t.evidence_ref evidenceRef,t.status,t.expected_version version FROM qc_retest_tasks t JOIN qc_run_records r ON r.id=t.run_id AND r.organisation_id=t.organisation_id WHERE t.organisation_id=UNHEX(:organisation) AND $where ORDER BY t.created_at DESC", $scopeParams);
        $risks = $this->workspaceRows("SELECT LOWER(HEX(q.id)) id,LOWER(HEX(q.run_id)) runId,LOWER(HEX(q.finding_id)) findingId,q.severity,q.title,q.description,q.status,q.risk_acceptance_reason riskAcceptanceReason,q.risk_review_date reviewDate,q.human_confirmed humanConfirmed,q.expected_version version FROM qc_risk_entries q JOIN qc_run_records r ON r.id=q.run_id AND r.organisation_id=q.organisation_id WHERE q.organisation_id=UNHEX(:organisation) AND $where ORDER BY q.created_at DESC", $scopeParams);
        foreach ($risks as &$risk) $risk['humanConfirmed'] = (bool) $risk['humanConfirmed'];
        $signoffs = $this->workspaceRows("SELECT LOWER(HEX(s.id)) id,LOWER(HEX(s.run_id)) runId,s.decision,s.reason,s.snapshot_sha256 snapshotSha256,DATE_FORMAT(s.created_at,'%Y-%m-%dT%H:%i:%s.%fZ') createdAt FROM qc_signoffs s JOIN qc_run_records r ON r.id=s.run_id AND r.organisation_id=s.organisation_id WHERE s.organisation_id=UNHEX(:organisation) AND $where ORDER BY s.created_at DESC", $scopeParams);
        $files = []; if ($projectId !== null) $files = $this->workspaceRows("SELECT LOWER(HEX(id)) id,original_name originalName,mime_type mimeType,byte_size byteSize FROM stored_files WHERE organisation_id=UNHEX(:organisation) AND project_id=UNHEX(:project) ORDER BY created_at DESC LIMIT 100", $scopeParams);
        return ['definitions' => $definitions->fetchAll(), 'runs' => $runs, 'categoryResults' => $categoryResults, 'findings' => $findings, 'retests' => $retests, 'risks' => $risks, 'signoffs' => $signoffs, 'availableFiles' => $files, 'capabilities' => ['accessRole' => $access, 'mayMutate' => in_array($access, self::MUTATION_ROLES, true), 'mayReview' => in_array($access, self::REVIEW_ROLES, true), 'mayAcceptRisk' => in_array($access, ['platform_super_admin', 'product_owner'], true), 'maySignOff' => in_array($access, ['platform_super_admin', 'product_owner'], true), 'aiMayExecute' => false]];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createRun(string $organisationId, string $userId, string $claimedRole, array $input): array
    {
        $this->requireReview($this->accessRole($organisationId, $userId, $claimedRole));
        $key = $this->text($input['idempotencyKey'] ?? null, 120, 'nuanced_qc_idempotency_key_required');
        $existing = $this->pdo->prepare('SELECT LOWER(HEX(id)) FROM qc_run_records WHERE organisation_id=UNHEX(:organisation) AND idempotency_key=:key');
        $existing->execute(['organisation' => $organisationId, 'key' => $key]); $existingId = $existing->fetchColumn();
        if (is_string($existingId)) return ['id' => $existingId, 'idempotentReplay' => true];
        $projectId = $this->optionalId($input['projectId'] ?? null, 'nuanced_qc_project_not_found'); if ($projectId !== null) $this->project($organisationId, $projectId);
        $title = $this->text($input['title'] ?? null, 220, 'nuanced_qc_title_required');
        $scopeType = $this->choice($input['scopeType'] ?? null, ['pr','repo','live_site','feature','implementation_pack','site_audit'], 'nuanced_qc_scope_type_invalid');
        $target = $this->text($input['target'] ?? null, 1000, 'nuanced_qc_target_required');
        $items = $input['sourceItems'] ?? null; if (!is_array($items) || $items === []) throw new NuancedQcException('nuanced_qc_source_items_required');
        return $this->transaction(function () use ($organisationId, $userId, $claimedRole, $projectId, $title, $scopeType, $target, $items, $input, $key): array {
            $this->ensureDefinitions($organisationId, $userId); $id = Identifier::generate();
            $this->pdo->prepare('INSERT INTO qc_run_records(id,organisation_id,project_id,title,scope_type,target,reviewer_user_id,assistance_label,idempotency_key,lifecycle_status) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:project),:title,:scope,:target,UNHEX(:user),:assistance,:key,\'in_review\')')->execute(['id' => $id, 'organisation' => $organisationId, 'project' => $projectId, 'title' => $title, 'scope' => $scopeType, 'target' => $target, 'user' => $userId, 'assistance' => $this->optionalText($input['assistanceLabel'] ?? null, 160), 'key' => $key]);
            foreach ($items as $item) {
                if (!is_array($item)) throw new NuancedQcException('nuanced_qc_source_item_invalid');
                $this->pdo->prepare('INSERT INTO qc_run_scope_items(id,organisation_id,run_id,scope_kind,reference,source_promise,affected_workflow,cross_pack_target,created_by) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:run),:kind,:reference,:promise,:workflow,:cross_pack,UNHEX(:user))')->execute(['id' => Identifier::generate(), 'organisation' => $organisationId, 'run' => $id, 'kind' => $this->choice($item['kind'] ?? null, ['pack','brief','repo','pr','url','route','file','project','workflow'], 'nuanced_qc_source_item_invalid'), 'reference' => $this->text($item['reference'] ?? null, 500, 'nuanced_qc_source_item_invalid'), 'promise' => $this->text($item['sourcePromise'] ?? null, 4000, 'nuanced_qc_source_item_invalid'), 'workflow' => $this->optionalText($item['affectedWorkflow'] ?? null, 220), 'cross_pack' => $this->optionalText($item['crossPackTarget'] ?? null, 220), 'user' => $userId]);
            }
            $this->event($organisationId, $userId, $claimedRole, $projectId, 'run_created', 'Nuanced QC run created', 'pending_review', ['runId' => $id, 'scopeType' => $scopeType, 'target' => $target], $id);
            return ['id' => $id, 'idempotentReplay' => false, 'version' => 1];
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function recordTechnicalSummary(string $organisationId, string $userId, string $claimedRole, string $runId, array $input): array
    {
        $this->requireReview($this->accessRole($organisationId, $userId, $claimedRole)); $run = $this->run($organisationId, $runId); $version = $this->version($input);
        $statement = $this->pdo->prepare('UPDATE qc_run_records SET technical_verification_summary=:technical,pack_goal_match_summary=:goal,nuanced_gap_summary=:gap,expected_version=expected_version+1 WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation) AND expected_version=:version');
        $statement->execute(['technical' => $this->text($input['technicalVerificationSummary'] ?? null, 8000, 'nuanced_qc_summary_required'), 'goal' => $this->text($input['packGoalMatchSummary'] ?? null, 8000, 'nuanced_qc_summary_required'), 'gap' => $this->text($input['nuancedGapSummary'] ?? null, 8000, 'nuanced_qc_summary_required'), 'id' => $runId, 'organisation' => $organisationId, 'version' => $version]);
        if ($statement->rowCount() !== 1) throw new NuancedQcException('nuanced_qc_version_conflict');
        $this->audit($organisationId, $userId, 'summary_recorded', 'qc_run', $runId, ['version' => $version + 1]); return ['id' => $runId, 'version' => $version + 1, 'projectId' => $run['project_id']];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function recordCategoryResult(string $organisationId, string $userId, string $claimedRole, string $runId, array $input): array
    {
        $this->requireReview($this->accessRole($organisationId, $userId, $claimedRole)); $this->run($organisationId, $runId);
        $category = $this->category($input['category'] ?? null); $status = $this->choice($input['status'] ?? null, ['pass','fail','partial','not_applicable','deferred','blocked','manually_satisfied','external_system_handled'], 'nuanced_qc_category_status_invalid');
        $reason = $this->text($input['reason'] ?? null, 8000, 'nuanced_qc_reason_required');
        return $this->transaction(function () use ($organisationId, $userId, $runId, $category, $status, $reason): array {
            $revision = $this->nextRevision('qc_category_results', 'run_id', $runId, 'category_key', $category); $id = Identifier::generate();
            $this->pdo->prepare('INSERT INTO qc_category_results(id,organisation_id,run_id,category_key,revision,status,reason,reviewed_by) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:run),:category,:revision,:status,:reason,UNHEX(:user))')->execute(['id' => $id, 'organisation' => $organisationId, 'run' => $runId, 'category' => $category, 'revision' => $revision, 'status' => $status, 'reason' => $reason, 'user' => $userId]);
            $this->refreshRun($organisationId, $runId); $this->audit($organisationId, $userId, 'category_recorded', 'qc_category_result', $id, ['runId' => $runId, 'category' => $category, 'status' => $status, 'revision' => $revision]);
            return ['id' => $id, 'revision' => $revision, 'category' => $category, 'status' => $status];
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function addFinding(string $organisationId, string $userId, string $claimedRole, string $runId, array $input): array
    {
        $this->requireMutation($this->accessRole($organisationId, $userId, $claimedRole)); $run = $this->run($organisationId, $runId);
        $category = $this->category($input['category'] ?? null); $severity = $this->choice($input['severity'] ?? null, ['blocker','high','medium','low'], 'nuanced_qc_severity_invalid');
        $title = $this->text($input['title'] ?? null, 220, 'nuanced_qc_title_required'); $description = $this->text($input['description'] ?? null, 8000, 'nuanced_qc_description_required');
        $evidenceType = $this->evidenceType($input['evidenceType'] ?? null); $evidenceRef = $this->text($input['evidenceRef'] ?? null, 1000, 'nuanced_qc_evidence_required');
        $requiredFix = $this->text($input['requiredFix'] ?? null, 8000, 'nuanced_qc_required_fix_required'); $retestSteps = $this->text($input['retestSteps'] ?? null, 8000, 'nuanced_qc_retest_steps_required');
        $owner = $this->optionalId($input['ownerUserId'] ?? null, 'nuanced_qc_owner_not_found'); if ($owner !== null) $this->member($organisationId, $owner);
        return $this->transaction(function () use ($organisationId, $userId, $claimedRole, $runId, $run, $category, $severity, $title, $description, $evidenceType, $evidenceRef, $requiredFix, $retestSteps, $owner, $input): array {
            $id = Identifier::generate();
            $this->pdo->prepare('INSERT INTO qc_gap_findings(id,organisation_id,project_id,run_id,category_key,severity,title,description,evidence_type,evidence_ref,affected_pack,affected_workflow,cross_pack_impact,required_fix,retest_steps,owner_user_id,created_by) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:project),UNHEX(:run),:category,:severity,:title,:description,:evidence_type,:evidence_ref,:pack,:workflow,:impact,:fix,:retest,UNHEX(:owner),UNHEX(:user))')->execute(['id' => $id, 'organisation' => $organisationId, 'project' => $run['project_id'], 'run' => $runId, 'category' => $category, 'severity' => $severity, 'title' => $title, 'description' => $description, 'evidence_type' => $evidenceType, 'evidence_ref' => $evidenceRef, 'pack' => $this->optionalText($input['affectedPack'] ?? null, 220), 'workflow' => $this->optionalText($input['affectedWorkflow'] ?? null, 220), 'impact' => $this->optionalText($input['crossPackImpact'] ?? null, 8000), 'fix' => $requiredFix, 'retest' => $retestSteps, 'owner' => $owner, 'user' => $userId]);
            $riskId = null; if (in_array($severity, ['blocker','high'], true)) { $riskId = Identifier::generate(); $this->pdo->prepare('INSERT INTO qc_risk_entries(id,organisation_id,project_id,run_id,finding_id,severity,title,description) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:project),UNHEX(:run),UNHEX(:finding),:severity,:title,:description)')->execute(['id' => $riskId, 'organisation' => $organisationId, 'project' => $run['project_id'], 'run' => $runId, 'finding' => $id, 'severity' => $severity, 'title' => $title, 'description' => $description]); }
            $retestId = $this->insertRetest($organisationId, $userId, $run, $id, $owner, $retestSteps);
            $this->refreshRun($organisationId, $runId); $this->event($organisationId, $userId, $claimedRole, $run['project_id'], 'finding_added', 'Nuanced QC finding: '.$title, in_array($severity, ['blocker','high'], true) ? 'rejected' : 'pending_review', ['runId' => $runId, 'findingId' => $id, 'severity' => $severity, 'crossPackImpact' => $input['crossPackImpact'] ?? null], $id);
            return ['id' => $id, 'riskId' => $riskId, 'retestId' => $retestId, 'status' => 'open'];
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function linkEvidence(string $organisationId, string $userId, string $claimedRole, string $runId, array $input): array
    {
        $this->requireMutation($this->accessRole($organisationId, $userId, $claimedRole)); $run = $this->run($organisationId, $runId); $key = $this->text($input['idempotencyKey'] ?? null, 120, 'nuanced_qc_idempotency_key_required');
        $existing = $this->pdo->prepare('SELECT LOWER(HEX(id)) FROM qc_evidence_links WHERE organisation_id=UNHEX(:organisation) AND idempotency_key=:key'); $existing->execute(['organisation' => $organisationId, 'key' => $key]); $existingId = $existing->fetchColumn(); if (is_string($existingId)) return ['id' => $existingId, 'idempotentReplay' => true];
        $finding = $this->optionalId($input['findingId'] ?? null, 'nuanced_qc_finding_not_found'); if ($finding !== null) $this->finding($organisationId, $runId, $finding);
        $file = $this->optionalId($input['storedFileId'] ?? null, 'nuanced_qc_file_not_found'); if ($file !== null) $this->file($organisationId, $run['project_id'], $file);
        $evidenceType = $this->evidenceType($input['evidenceType'] ?? null); $ref = $this->optionalText($input['evidenceRef'] ?? null, 1000); if ($file === null && $ref === null) throw new NuancedQcException('nuanced_qc_evidence_required');
        $id = Identifier::generate(); $this->pdo->prepare('INSERT INTO qc_evidence_links(id,organisation_id,project_id,run_id,finding_id,stored_file_id,evidence_type,evidence_ref,label,notes,idempotency_key,linked_by) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:project),UNHEX(:run),UNHEX(:finding),UNHEX(:file),:type,:ref,:label,:notes,:key,UNHEX(:user))')->execute(['id' => $id, 'organisation' => $organisationId, 'project' => $run['project_id'], 'run' => $runId, 'finding' => $finding, 'file' => $file, 'type' => $evidenceType, 'ref' => $ref, 'label' => $this->text($input['label'] ?? null, 220, 'nuanced_qc_label_required'), 'notes' => $this->optionalText($input['notes'] ?? null, 8000), 'key' => $key, 'user' => $userId]);
        $this->audit($organisationId, $userId, 'evidence_linked', 'qc_evidence_link', $id, ['runId' => $runId, 'findingId' => $finding, 'storedFileId' => $file]); return ['id' => $id, 'idempotentReplay' => false];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createRetest(string $organisationId, string $userId, string $claimedRole, string $findingId, array $input): array
    {
        $this->requireMutation($this->accessRole($organisationId, $userId, $claimedRole)); $finding = $this->findingById($organisationId, $findingId); $run = $this->run($organisationId, $finding['run_id']);
        $assigned = $this->optionalId($input['assignedUserId'] ?? null, 'nuanced_qc_assignee_not_found'); if ($assigned !== null) $this->member($organisationId, $assigned);
        $id = $this->insertRetest($organisationId, $userId, $run, $findingId, $assigned, $this->text($input['expectedTest'] ?? null, 8000, 'nuanced_qc_retest_steps_required'));
        $this->audit($organisationId, $userId, 'retest_created', 'qc_retest_task', $id, ['findingId' => $findingId]); return ['id' => $id, 'status' => 'open', 'version' => 1];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function completeRetest(string $organisationId, string $userId, string $claimedRole, string $retestId, array $input): array
    {
        $this->requireMutation($this->accessRole($organisationId, $userId, $claimedRole)); $version = $this->version($input); $status = $this->choice($input['status'] ?? null, ['passed','failed'], 'nuanced_qc_retest_status_invalid');
        return $this->transaction(function () use ($organisationId, $userId, $retestId, $input, $version, $status): array {
            $task = $this->one('SELECT LOWER(HEX(run_id)) run_id,LOWER(HEX(finding_id)) finding_id,LOWER(HEX(project_id)) project_id FROM qc_retest_tasks WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation)', $organisationId, $retestId, 'nuanced_qc_retest_not_found');
            $statement = $this->pdo->prepare('UPDATE qc_retest_tasks SET status=:status,result_summary=:summary,evidence_ref=:evidence,completed_by=UNHEX(:user),completed_at=UTC_TIMESTAMP(6),expected_version=expected_version+1 WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation) AND expected_version=:version AND status IN (\'open\',\'in_progress\')');
            $statement->execute(['status' => $status, 'summary' => $this->text($input['resultSummary'] ?? null, 8000, 'nuanced_qc_retest_result_required'), 'evidence' => $this->text($input['evidenceRef'] ?? null, 1000, 'nuanced_qc_evidence_required'), 'user' => $userId, 'id' => $retestId, 'organisation' => $organisationId, 'version' => $version]); if ($statement->rowCount() !== 1) throw new NuancedQcException('nuanced_qc_version_conflict');
            if ($status === 'passed') $this->pdo->prepare("UPDATE qc_gap_findings SET status='retest_passed',expected_version=expected_version+1 WHERE id=UNHEX(:finding) AND organisation_id=UNHEX(:organisation)")->execute(['finding' => $task['finding_id'], 'organisation' => $organisationId]);
            $this->refreshRun($organisationId, $task['run_id']); $this->audit($organisationId, $userId, 'retest_completed', 'qc_retest_task', $retestId, ['status' => $status, 'findingId' => $task['finding_id']]); return ['id' => $retestId, 'status' => $status, 'version' => $version + 1];
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function acceptRisk(string $organisationId, string $userId, string $claimedRole, string $riskId, array $input): array
    {
        $this->requireApprover($this->accessRole($organisationId, $userId, $claimedRole)); $this->confirmed($input); $version = $this->version($input); $reason = $this->text($input['reason'] ?? null, 8000, 'nuanced_qc_reason_required'); $date = $this->date($input['reviewDate'] ?? null);
        return $this->transaction(function () use ($organisationId, $userId, $riskId, $version, $reason, $date): array {
            $risk = $this->one('SELECT LOWER(HEX(run_id)) run_id,LOWER(HEX(finding_id)) finding_id FROM qc_risk_entries WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation)', $organisationId, $riskId, 'nuanced_qc_risk_not_found');
            $statement = $this->pdo->prepare("UPDATE qc_risk_entries SET status='accepted',risk_accepted_by=UNHEX(:user),risk_acceptance_reason=:reason,risk_review_date=:review_date,human_confirmed=TRUE,expected_version=expected_version+1 WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation) AND expected_version=:version AND status IN ('open','mitigating')");
            $statement->execute(['user' => $userId, 'reason' => $reason, 'review_date' => $date, 'id' => $riskId, 'organisation' => $organisationId, 'version' => $version]); if ($statement->rowCount() !== 1) throw new NuancedQcException('nuanced_qc_version_conflict');
            $this->pdo->prepare("UPDATE qc_gap_findings SET status='accepted_risk',expected_version=expected_version+1 WHERE id=UNHEX(:finding) AND organisation_id=UNHEX(:organisation)")->execute(['finding' => $risk['finding_id'], 'organisation' => $organisationId]);
            $this->refreshRun($organisationId, $risk['run_id']); $this->audit($organisationId, $userId, 'risk_accepted', 'qc_risk_entry', $riskId, ['reason' => $reason, 'reviewDate' => $date, 'humanConfirmed' => true]); return ['id' => $riskId, 'status' => 'accepted', 'version' => $version + 1];
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function signOff(string $organisationId, string $userId, string $claimedRole, string $runId, array $input): array
    {
        $this->requireApprover($this->accessRole($organisationId, $userId, $claimedRole)); $this->confirmed($input); $run = $this->run($organisationId, $runId); $decision = $this->choice($input['decision'] ?? null, ['accepted','accepted_with_notes','changes_requested','block_merge','block_deploy'], 'nuanced_qc_signoff_decision_invalid'); $reason = $this->text($input['reason'] ?? null, 8000, 'nuanced_qc_reason_required');
        $summary = $this->summary($organisationId, $runId); if (in_array($decision, ['accepted','accepted_with_notes'], true) && !$summary['readyForSignoff']) throw new NuancedQcException('nuanced_qc_'.($summary['blockReason'] ?? 'signoff_not_ready'));
        return $this->transaction(function () use ($organisationId, $userId, $claimedRole, $runId, $run, $decision, $reason, $summary): array {
            $snapshot = ['runId' => $runId, 'decision' => $decision, 'reason' => $reason, 'summary' => $summary, 'signedOffBy' => $userId]; $sha = hash('sha256', $this->json($snapshot)); $id = Identifier::generate();
            $this->pdo->prepare('INSERT INTO qc_signoffs(id,organisation_id,project_id,run_id,signed_off_by,decision,reason,human_confirmed,snapshot_sha256) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:project),UNHEX(:run),UNHEX(:user),:decision,:reason,TRUE,:sha)')->execute(['id' => $id, 'organisation' => $organisationId, 'project' => $run['project_id'], 'run' => $runId, 'user' => $userId, 'decision' => $decision, 'reason' => $reason, 'sha' => $sha]);
            $lifecycle = match ($decision) { 'accepted' => 'accepted', 'accepted_with_notes' => 'accepted_with_notes', 'changes_requested' => 'changes_requested', default => 'blocked' };
            $this->pdo->prepare('UPDATE qc_run_records SET signoff_status=:decision,lifecycle_status=:lifecycle,completed_at=UTC_TIMESTAMP(6),expected_version=expected_version+1 WHERE id=UNHEX(:run) AND organisation_id=UNHEX(:organisation)')->execute(['decision' => $decision, 'lifecycle' => $lifecycle, 'run' => $runId, 'organisation' => $organisationId]);
            $this->event($organisationId, $userId, $claimedRole, $run['project_id'], 'signoff_recorded', 'Nuanced QC sign-off', in_array($decision, ['accepted','accepted_with_notes'], true) ? 'approved' : 'rejected', ['runId' => $runId, 'signoffId' => $id, 'decision' => $decision, 'reason' => $reason, 'humanConfirmed' => true, 'snapshotSha256' => $sha], $id);
            return ['id' => $id, 'decision' => $decision, 'snapshotSha256' => $sha];
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function assignRole(string $organisationId, string $userId, string $claimedRole, array $input): array
    {
        $this->requireApprover($this->accessRole($organisationId, $userId, $claimedRole)); $target = $this->id($input['userId'] ?? null, 'nuanced_qc_user_not_found'); $this->member($organisationId, $target); $role = $this->choice($input['qcRole'] ?? null, ['qc_reviewer','developer','professional_reviewer','site_auditor','viewer'], 'nuanced_qc_role_invalid'); $id = Identifier::generate();
        $this->pdo->prepare('INSERT INTO qc_role_assignments(id,organisation_id,user_id,qc_role,assigned_by) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:target),:role,UNHEX(:user)) ON DUPLICATE KEY UPDATE qc_role=VALUES(qc_role),assigned_by=VALUES(assigned_by),updated_at=UTC_TIMESTAMP(6)')->execute(['id' => $id, 'organisation' => $organisationId, 'target' => $target, 'role' => $role, 'user' => $userId]);
        $this->audit($organisationId, $userId, 'role_assigned', 'qc_role_assignment', $target, ['qcRole' => $role]); return ['userId' => $target, 'qcRole' => $role];
    }

    private function ensureDefinitions(string $organisationId, string $userId): void { $statement = $this->pdo->prepare('INSERT IGNORE INTO qc_control_definitions(id,organisation_id,category_key,title,sort_order,created_by) VALUES(UNHEX(:id),UNHEX(:organisation),:key,:title,:sort,UNHEX(:user))'); foreach ($this->registry->categories() as $category) $statement->execute(['id' => Identifier::generate(), 'organisation' => $organisationId, 'key' => $category['key'], 'title' => $category['title'], 'sort' => $category['sortOrder'], 'user' => $userId]); }
    /** @return array<string,mixed> */ private function run(string $organisationId, string $runId): array { return $this->one('SELECT LOWER(HEX(id)) id,LOWER(HEX(project_id)) project_id,LOWER(HEX(reviewer_user_id)) reviewer_user_id,title,expected_version FROM qc_run_records WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation)', $organisationId, $runId, 'nuanced_qc_run_not_found'); }
    /** @return array<string,mixed> */ private function finding(string $organisationId, string $runId, string $findingId): array { return $this->one('SELECT LOWER(HEX(id)) id,LOWER(HEX(run_id)) run_id FROM qc_gap_findings WHERE id=UNHEX(:id) AND run_id=UNHEX(:run) AND organisation_id=UNHEX(:organisation)', $organisationId, $findingId, 'nuanced_qc_finding_not_found', ['run' => $runId]); }
    /** @return array<string,mixed> */ private function findingById(string $organisationId, string $findingId): array { return $this->one('SELECT LOWER(HEX(id)) id,LOWER(HEX(run_id)) run_id FROM qc_gap_findings WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation)', $organisationId, $findingId, 'nuanced_qc_finding_not_found'); }
    private function project(string $organisationId, string $projectId): void { $statement = $this->pdo->prepare('SELECT COUNT(*) FROM projects WHERE id=UNHEX(:project) AND organisation_id=UNHEX(:organisation)'); $statement->execute(['project' => $this->id($projectId, 'nuanced_qc_project_not_found'), 'organisation' => $organisationId]); if ((int) $statement->fetchColumn() !== 1) throw new NuancedQcException('nuanced_qc_project_not_found'); }
    private function member(string $organisationId, string $userId): void { $statement = $this->pdo->prepare("SELECT COUNT(*) FROM organisation_memberships WHERE organisation_id=UNHEX(:organisation) AND user_id=UNHEX(:user) AND status='active'"); $statement->execute(['organisation' => $organisationId, 'user' => $userId]); if ((int) $statement->fetchColumn() !== 1) throw new NuancedQcException('nuanced_qc_user_not_found'); }
    private function file(string $organisationId, ?string $projectId, string $fileId): void { $sql = $projectId === null ? 'project_id IS NULL' : 'project_id=UNHEX(:project)'; $statement = $this->pdo->prepare("SELECT COUNT(*) FROM stored_files WHERE id=UNHEX(:file) AND organisation_id=UNHEX(:organisation) AND $sql"); $params = ['file' => $fileId, 'organisation' => $organisationId]; if ($projectId !== null) $params['project'] = $projectId; $statement->execute($params); if ((int) $statement->fetchColumn() !== 1) throw new NuancedQcException('nuanced_qc_file_not_found'); }
    private function accessRole(string $organisationId, string $userId, string $claimedRole): string { $statement = $this->pdo->prepare("SELECT role FROM organisation_memberships WHERE organisation_id=UNHEX(:organisation) AND user_id=UNHEX(:user) AND status='active'"); $statement->execute(['organisation' => $organisationId, 'user' => $userId]); $actual = $statement->fetchColumn(); if (!is_string($actual) || !hash_equals($actual, $claimedRole)) throw new NuancedQcException('nuanced_qc_membership_required'); if ($actual === 'owner') return 'platform_super_admin'; if ($actual === 'admin') return 'product_owner'; $assigned = $this->pdo->prepare('SELECT qc_role FROM qc_role_assignments WHERE organisation_id=UNHEX(:organisation) AND user_id=UNHEX(:user)'); $assigned->execute(['organisation' => $organisationId, 'user' => $userId]); $role = $assigned->fetchColumn(); return is_string($role) ? $role : 'viewer'; }
    private function requireReview(string $role): void { if (!in_array($role, self::REVIEW_ROLES, true)) throw new NuancedQcException('nuanced_qc_review_forbidden'); }
    private function requireMutation(string $role): void { if (!in_array($role, self::MUTATION_ROLES, true)) throw new NuancedQcException('nuanced_qc_mutation_forbidden'); }
    private function requireApprover(string $role): void { if (!in_array($role, ['platform_super_admin','product_owner'], true)) throw new NuancedQcException('nuanced_qc_signoff_forbidden'); }
    /** @return array<string,mixed> */ private function summary(string $organisationId, string $runId): array { $categories = array_map(fn (array $category): array => ['category' => $category['key'], 'status' => $this->latestCategoryStatus($organisationId, $runId, $category['key'])], $this->registry->categories()); $statement = $this->pdo->prepare("SELECT severity,status,EXISTS(SELECT 1 FROM qc_risk_entries r WHERE r.finding_id=f.id AND r.organisation_id=f.organisation_id AND r.status='accepted' AND r.human_confirmed=TRUE) riskAccepted FROM qc_gap_findings f WHERE organisation_id=UNHEX(:organisation) AND run_id=UNHEX(:run)"); $statement->execute(['organisation' => $organisationId, 'run' => $runId]); $findings = $statement->fetchAll(); foreach ($findings as &$finding) $finding['riskAccepted'] = (bool) $finding['riskAccepted']; return $this->evaluator->summarise($categories, $findings); }
    private function latestCategoryStatus(string $organisationId, string $runId, string $category): string { $statement = $this->pdo->prepare('SELECT status FROM qc_category_results WHERE organisation_id=UNHEX(:organisation) AND run_id=UNHEX(:run) AND category_key=:category ORDER BY revision DESC LIMIT 1'); $statement->execute(['organisation' => $organisationId, 'run' => $runId, 'category' => $category]); $status = $statement->fetchColumn(); return is_string($status) ? $status : 'not_reviewed'; }
    private function refreshRun(string $organisationId, string $runId): void { $summary = $this->summary($organisationId, $runId); $lifecycle = match ($summary['recommendation']) { 'block_merge_or_deploy' => 'blocked', 'changes_requested' => 'changes_requested', default => 'in_review' }; $this->pdo->prepare('UPDATE qc_run_records SET recommendation=:recommendation,lifecycle_status=IF(signoff_status=\'not_signed\',:lifecycle,lifecycle_status) WHERE id=UNHEX(:run) AND organisation_id=UNHEX(:organisation)')->execute(['recommendation' => $summary['recommendation'], 'lifecycle' => $lifecycle, 'run' => $runId, 'organisation' => $organisationId]); }
    /** @param array<string,mixed> $run */ private function insertRetest(string $organisationId, string $userId, array $run, string $findingId, ?string $assigned, string $expected): string { $id = Identifier::generate(); $this->pdo->prepare('INSERT INTO qc_retest_tasks(id,organisation_id,project_id,run_id,finding_id,assigned_user_id,expected_test,created_by) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:project),UNHEX(:run),UNHEX(:finding),UNHEX(:assigned),:expected,UNHEX(:user))')->execute(['id' => $id, 'organisation' => $organisationId, 'project' => $run['project_id'], 'run' => $run['id'], 'finding' => $findingId, 'assigned' => $assigned, 'expected' => $expected, 'user' => $userId]); return $id; }
    private function event(string $organisationId, string $userId, string $role, ?string $projectId, string $type, string $title, string $status, array $payload, string $subject): void { if ($projectId !== null) { $context = new ProjectContext($organisationId, $userId, $projectId, $role); (new ProjectRecordService($this->pdo))->issue($context, ProjectPhase::DesignDevelopment, self::MODULE, 'nuanced_qc_'.$type, $title, $status, $payload, ['required' => str_contains($type, 'signoff'), 'humanConfirmed' => $payload['humanConfirmed'] ?? false], [$subject]); (new InboxService($this->pdo))->create($context, self::MODULE.'.'.$type, $title, (string) ($payload['reason'] ?? $title), in_array($status, ['rejected','pending_review'], true) ? 'high' : 'medium', $payload); } $this->audit($organisationId, $userId, $type, 'qc_'.$type, $subject, $payload); }
    private function audit(string $organisationId, string $userId, string $action, string $type, string $subject, array $metadata): void { (new AuditService($this->pdo))->append($organisationId, $userId, self::MODULE.'.'.$action, $type, $subject, $metadata); }
    /** @param array<string,mixed> $extra @return array<string,mixed> */ private function one(string $sql, string $organisationId, string $id, string $error, array $extra = []): array { $statement = $this->pdo->prepare($sql); $statement->execute(['organisation' => $organisationId, 'id' => $this->id($id, $error)] + $extra); $row = $statement->fetch(); if (!is_array($row)) throw new NuancedQcException($error); return $row; }
    private function nextRevision(string $table, string $parentField, string $parentId, string $groupField, string $group): int { $statement = $this->pdo->prepare("SELECT COALESCE(MAX(revision),0)+1 FROM $table WHERE $parentField=UNHEX(:parent) AND $groupField=:grouping"); $statement->execute(['parent' => $parentId, 'grouping' => $group]); return (int) $statement->fetchColumn(); }
    /** @param array<string,string> $params @return list<array<string,mixed>> */ private function workspaceRows(string $sql, array $params): array { $statement = $this->pdo->prepare($sql); $statement->execute($params); return $statement->fetchAll(); }
    private function category(mixed $value): string { $category = $this->text($value, 100, 'nuanced_qc_category_invalid'); foreach ($this->registry->categories() as $definition) if ($definition['key'] === $category) return $category; throw new NuancedQcException('nuanced_qc_category_invalid'); }
    private function evidenceType(mixed $value): string { return $this->choice($value, ['file','route','screenshot','test_output','api_output','assumption','user_report'], 'nuanced_qc_evidence_type_invalid'); }
    /** @param array<string,mixed> $input */ private function confirmed(array $input): void { if (($input['humanConfirmed'] ?? false) !== true) throw new NuancedQcException('nuanced_qc_human_confirmation_required'); }
    /** @param array<string,mixed> $input */ private function version(array $input): int { $version = $input['expectedVersion'] ?? null; if (!is_int($version) || $version < 1) throw new NuancedQcException('nuanced_qc_expected_version_required'); return $version; }
    private function text(mixed $value, int $max, string $error): string { if (!is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > $max) throw new NuancedQcException($error); return trim($value); }
    private function optionalText(mixed $value, int $max): ?string { if ($value === null || $value === '') return null; if (!is_string($value) || mb_strlen(trim($value)) > $max) throw new NuancedQcException('nuanced_qc_value_invalid'); return trim($value); }
    private function choice(mixed $value, array $allowed, string $error): string { if (!is_string($value) || !in_array($value, $allowed, true)) throw new NuancedQcException($error); return $value; }
    private function id(mixed $value, string $error): string { if (!is_string($value) || preg_match('/\A[a-f0-9]{32}\z/i', $value) !== 1) throw new NuancedQcException($error); return strtolower($value); }
    private function optionalId(mixed $value, string $error): ?string { return $value === null || $value === '' ? null : $this->id($value, $error); }
    private function date(mixed $value): string { if (!is_string($value) || preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value) !== 1 || strtotime($value) === false) throw new NuancedQcException('nuanced_qc_review_date_invalid'); return $value; }
    /** @param array<mixed> $value */ private function json(array $value): string { return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION); }
    /** @template T @param callable():T $callback @return T */ private function transaction(callable $callback): mixed { $owned = !$this->pdo->inTransaction(); if ($owned) $this->pdo->beginTransaction(); try { $result = $callback(); if ($owned) $this->pdo->commit(); return $result; } catch (Throwable $error) { if ($owned && $this->pdo->inTransaction()) $this->pdo->rollBack(); throw $error; } }
}
