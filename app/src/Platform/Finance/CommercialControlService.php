<?php

declare(strict_types=1);

namespace Architex\Platform\Finance;

use Architex\Platform\Audit\AuditService;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Inbox\InboxService;
use Architex\Platform\Project\ProjectContext;
use Architex\Platform\Project\ProjectPhase;
use Architex\Platform\Records\ProjectRecordService;
use DateTimeImmutable;
use PDO;
use Throwable;

final readonly class CommercialControlService
{
    private const APPROVAL_TYPES = ['quantity_surveyor', 'lead_professional', 'client'];

    public function __construct(private PDO $pdo) {}

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createBaseline(string $organisationId, string $userId, string $role, string $projectId, array $input): array
    {
        $this->assertMember($organisationId, $userId, $role, ['owner', 'admin']);
        if (($input['humanConfirmed'] ?? false) !== true) throw new CommercialControlException('commercial_baseline_confirmation_required');
        $appointmentId = $this->id($input['appointmentId'] ?? null, 'commercial_source_appointment_required');
        $sum = $this->unsignedCents($input['contractSumCents'] ?? null, false, 'commercial_contract_sum_invalid');
        $contingency = $this->unsignedCents($input['contingencyCents'] ?? 0, true, 'commercial_contingency_invalid');
        $retention = $input['retentionBasisPoints'] ?? null;
        if (!is_int($retention) || $retention < 0 || $retention > 2000) throw new CommercialControlException('commercial_retention_invalid');
        $currency = strtoupper($this->text($input['currency'] ?? null, 3, 'commercial_currency_invalid'));
        if (!preg_match('/\A[A-Z]{3}\z/', $currency)) throw new CommercialControlException('commercial_currency_invalid');
        if (!is_bool($input['vatIncluded'] ?? null)) throw new CommercialControlException('commercial_vat_basis_required');
        $reason = $this->text($input['reason'] ?? null, 1000, 'commercial_baseline_reason_required');
        $source = $this->sourceAppointment($organisationId, $projectId, $appointmentId);

        return $this->transaction(function () use ($organisationId, $userId, $role, $projectId, $appointmentId, $sum, $currency, $input, $contingency, $retention, $reason, $source): array {
            $id = Identifier::generate();
            $this->pdo->prepare("INSERT INTO commercial_baselines(id,organisation_id,project_id,source_appointment_id,original_contract_sum_cents,current_contract_sum_cents,currency,vat_included,contingency_cents,retention_basis_points,status,revision,created_by) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:p),UNHEX(:appointment),:original,:current,:currency,:vat,:contingency,:retention,'active',1,UNHEX(:user))")->execute([
                'id' => $id, 'o' => $organisationId, 'p' => $projectId, 'appointment' => $appointmentId,
                'original' => $sum, 'current' => $sum, 'currency' => $currency, 'vat' => $input['vatIncluded'] ? 1 : 0,
                'contingency' => $contingency, 'retention' => $retention, 'user' => $userId,
            ]);
            $snapshot = ['baselineId' => $id, 'projectId' => $projectId, 'sourceAppointmentId' => $appointmentId,
                'sourceAppointmentHash' => $source['proposal_hash'], 'sourceFormallyIssuedAt' => $source['formally_issued_at'],
                'revision' => 1, 'originalContractSumCents' => $sum, 'currentContractSumCents' => $sum,
                'currency' => $currency, 'vatIncluded' => $input['vatIncluded'], 'contingencyCents' => $contingency,
                'retentionBasisPoints' => $retention, 'humanConfirmed' => true, 'createdBy' => $userId, 'createdAt' => $this->now()];
            $revision = $this->insertBaselineRevision($organisationId, $id, 1, 'baseline_created', null, $sum, $snapshot, $reason, $userId);
            $recordId = (new ProjectRecordService($this->pdo))->issue(new ProjectContext($organisationId, $userId, $projectId, $role), ProjectPhase::ConstructionExecution, 'finance_commercial_control', 'commercial_baseline', 'Commercial baseline', 'issued', $snapshot + ['snapshotSha256' => $revision['snapshotSha256']], ['required' => true, 'humanApprovalConfirmed' => true, 'approvedBy' => $userId]);
            (new AuditService($this->pdo))->append($organisationId, $userId, 'finance.commercial_baseline_created', 'commercial_baseline', $id, ['projectId' => $projectId, 'revision' => 1, 'snapshotSha256' => $revision['snapshotSha256'], 'recordId' => $recordId]);
            return $this->getBaseline($organisationId, $userId, $id);
        });
    }

    /** @return array<string,mixed> */
    public function getBaseline(string $organisationId, string $userId, string $baselineId): array
    {
        $this->assertMember($organisationId, $userId, null, ['owner', 'admin', 'member', 'viewer']);
        $statement = $this->pdo->prepare("SELECT LOWER(HEX(b.id)) id,LOWER(HEX(b.project_id)) project_id,LOWER(HEX(b.source_appointment_id)) source_appointment_id,b.original_contract_sum_cents,b.current_contract_sum_cents,b.currency,b.vat_included,b.contingency_cents,b.retention_basis_points,b.status,b.revision,r.snapshot_sha256 FROM commercial_baselines b JOIN commercial_baseline_revisions r ON r.baseline_id=b.id AND r.revision=b.revision WHERE b.id=UNHEX(:id) AND b.organisation_id=UNHEX(:o)");
        $statement->execute(['id' => $baselineId, 'o' => $organisationId]);
        $row = $statement->fetch();
        if (!is_array($row)) throw new CommercialControlException('commercial_baseline_not_found');
        return ['id' => $row['id'], 'projectId' => $row['project_id'], 'sourceAppointmentId' => $row['source_appointment_id'],
            'originalContractSumCents' => (int)$row['original_contract_sum_cents'], 'currentContractSumCents' => (int)$row['current_contract_sum_cents'],
            'currency' => $row['currency'], 'vatIncluded' => (bool)$row['vat_included'], 'contingencyCents' => (int)$row['contingency_cents'],
            'retentionBasisPoints' => (int)$row['retention_basis_points'], 'status' => $row['status'], 'revision' => (int)$row['revision'], 'snapshotSha256' => $row['snapshot_sha256']];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createSchedule(string $organisationId, string $userId, string $role, string $baselineId, array $input): array
    {
        $this->assertMember($organisationId, $userId, $role, ['owner', 'admin', 'member']);
        if (($input['humanConfirmed'] ?? false) !== true) throw new CommercialControlException('commercial_schedule_confirmation_required');
        $baseline = $this->baselineRow($organisationId, $baselineId, false);
        $title = $this->text($input['title'] ?? null, 220, 'commercial_schedule_title_required');
        $reason = $this->text($input['reason'] ?? null, 1000, 'commercial_schedule_reason_required');
        $raw = $input['milestones'] ?? null;
        if (!is_array($raw) || $raw === [] || count($raw) > 100) throw new CommercialControlException('commercial_schedule_milestones_invalid');
        $milestones = []; $total = 0; $previousDate = null;
        foreach (array_values($raw) as $index => $item) {
            if (!is_array($item)) throw new CommercialControlException('commercial_schedule_milestones_invalid');
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string)($item['dueDate'] ?? ''));
            if (!$date || $date->format('Y-m-d') !== ($item['dueDate'] ?? null) || ($previousDate !== null && $date < $previousDate)) throw new CommercialControlException('commercial_schedule_due_date_invalid');
            $amount = $this->unsignedCents($item['amountCents'] ?? null, false, 'commercial_schedule_amount_invalid');
            $total += $amount; if ($total > PHP_INT_MAX) throw new CommercialControlException('commercial_schedule_amount_invalid');
            $milestones[] = ['id' => Identifier::generate(), 'sequenceNumber' => $index + 1, 'label' => $this->text($item['label'] ?? null, 220, 'commercial_schedule_label_required'), 'amountCents' => $amount, 'dueDate' => $date->format('Y-m-d')];
            $previousDate = $date;
        }
        if ($total !== (int)$baseline['current_contract_sum_cents']) throw new CommercialControlException('commercial_schedule_total_mismatch');
        return $this->transaction(function () use ($organisationId, $userId, $baselineId, $title, $reason, $milestones, $total): array {
            $id = Identifier::generate(); $snapshot = ['scheduleId' => $id, 'baselineId' => $baselineId, 'title' => $title, 'scheduledTotalCents' => $total, 'milestones' => $milestones, 'humanConfirmed' => true, 'createdBy' => $userId, 'createdAt' => $this->now()];
            $json = $this->json($snapshot); $sha = hash('sha256', $json);
            $this->pdo->prepare("UPDATE commercial_payment_schedules SET status='superseded' WHERE organisation_id=UNHEX(:o) AND baseline_id=UNHEX(:baseline) AND status='issued'")->execute(['o' => $organisationId, 'baseline' => $baselineId]);
            $this->pdo->prepare("INSERT INTO commercial_payment_schedules(id,organisation_id,baseline_id,title,scheduled_total_cents,status,snapshot_payload,snapshot_sha256,reason,created_by) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:baseline),:title,:total,'issued',:snapshot,:sha,:reason,UNHEX(:user))")->execute(['id' => $id, 'o' => $organisationId, 'baseline' => $baselineId, 'title' => $title, 'total' => $total, 'snapshot' => $json, 'sha' => $sha, 'reason' => $reason, 'user' => $userId]);
            $insert = $this->pdo->prepare('INSERT INTO commercial_payment_milestones(id,organisation_id,schedule_id,sequence_number,label,amount_cents,due_date) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:schedule),:sequence,:label,:amount,:due)');
            foreach ($milestones as $milestone) $insert->execute(['id' => $milestone['id'], 'o' => $organisationId, 'schedule' => $id, 'sequence' => $milestone['sequenceNumber'], 'label' => $milestone['label'], 'amount' => $milestone['amountCents'], 'due' => $milestone['dueDate']]);
            (new AuditService($this->pdo))->append($organisationId, $userId, 'finance.payment_schedule_issued', 'commercial_payment_schedule', $id, ['baselineId' => $baselineId, 'scheduledTotalCents' => $total, 'snapshotSha256' => $sha]);
            return ['id' => $id, 'baselineId' => $baselineId, 'title' => $title, 'scheduledTotalCents' => $total, 'milestones' => $milestones, 'status' => 'issued', 'snapshotSha256' => $sha];
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function submitClaim(string $organisationId, string $userId, string $role, string $baselineId, array $input): array
    {
        $this->assertMember($organisationId, $userId, $role, ['owner', 'admin', 'member']);
        if (($input['humanConfirmed'] ?? false) !== true) throw new CommercialControlException('commercial_claim_confirmation_required');
        $baseline = $this->baselineRow($organisationId, $baselineId, false);
        $claimNumber = $this->text($input['claimNumber'] ?? null, 80, 'commercial_claim_number_required');
        $claimed = $this->unsignedCents($input['claimedToDateCents'] ?? null, false, 'commercial_claim_amount_invalid');
        if ($claimed > (int)$baseline['current_contract_sum_cents']) throw new CommercialControlException('commercial_claim_exceeds_contract_sum');
        $milestoneId = $this->id($input['milestoneId'] ?? null, 'commercial_claim_milestone_required');
        $milestone = $this->pdo->prepare('SELECT COUNT(*) FROM commercial_payment_milestones m JOIN commercial_payment_schedules s ON s.id=m.schedule_id WHERE m.id=UNHEX(:id) AND m.organisation_id=UNHEX(:o) AND s.baseline_id=UNHEX(:baseline)');
        $milestone->execute(['id' => $milestoneId, 'o' => $organisationId, 'baseline' => $baselineId]); if ((int)$milestone->fetchColumn() !== 1) throw new CommercialControlException('commercial_claim_milestone_not_found');
        $evidence = $this->references($input['evidenceReferences'] ?? null); $disputed = $input['disputed'] ?? null;
        if (!is_bool($disputed)) throw new CommercialControlException('commercial_claim_dispute_state_required');
        $reason = $this->text($input['reason'] ?? null, 1000, 'commercial_claim_reason_required');
        return $this->transaction(function () use ($organisationId, $userId, $role, $baselineId, $baseline, $claimNumber, $claimed, $milestoneId, $evidence, $disputed, $reason): array {
            $id = Identifier::generate(); $revisionId = Identifier::generate(); $status = $disputed ? 'disputed_locked' : 'submitted';
            $snapshot = ['claimId' => $id, 'baselineId' => $baselineId, 'projectId' => $baseline['project_id'], 'claimNumber' => $claimNumber, 'revision' => 1, 'claimedToDateCents' => $claimed, 'currency' => $baseline['currency'], 'milestoneId' => $milestoneId, 'evidenceReferences' => $evidence, 'disputed' => $disputed, 'status' => $status, 'humanConfirmed' => true, 'submittedBy' => $userId, 'submittedAt' => $this->now()];
            $json = $this->json($snapshot); $sha = hash('sha256', $json);
            $this->pdo->prepare("INSERT INTO commercial_payment_claims(id,organisation_id,baseline_id,milestone_id,claim_number,status,current_revision_id,version,submitted_by) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:baseline),UNHEX(:milestone),:number,:status,UNHEX(:revision),1,UNHEX(:user))")->execute(['id' => $id, 'o' => $organisationId, 'baseline' => $baselineId, 'milestone' => $milestoneId, 'number' => $claimNumber, 'status' => $status, 'revision' => $revisionId, 'user' => $userId]);
            $this->pdo->prepare('INSERT INTO commercial_payment_claim_revisions(id,organisation_id,claim_id,revision,claimed_to_date_cents,evidence_references,disputed,snapshot_payload,snapshot_sha256,reason,created_by) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:claim),1,:claimed,:evidence,:disputed,:snapshot,:sha,:reason,UNHEX(:user))')->execute(['id' => $revisionId, 'o' => $organisationId, 'claim' => $id, 'claimed' => $claimed, 'evidence' => $this->json($evidence), 'disputed' => $disputed ? 1 : 0, 'snapshot' => $json, 'sha' => $sha, 'reason' => $reason, 'user' => $userId]);
            $recordId = (new ProjectRecordService($this->pdo))->issue(new ProjectContext($organisationId, $userId, (string)$baseline['project_id'], $role), ProjectPhase::ConstructionExecution, 'finance_commercial_control', 'claim_submission', 'Payment claim '.$claimNumber, $disputed ? 'pending_review' : 'issued', $snapshot + ['snapshotSha256' => $sha], ['required' => true, 'quantitySurveyorReviewed' => false]);
            (new InboxService($this->pdo))->create(new ProjectContext($organisationId, $userId, (string)$baseline['project_id'], $role), 'finance_claim_review_required', 'Payment claim '.$claimNumber.' needs QS review', $disputed ? 'This claim includes an unresolved dispute and remains release-locked.' : 'Review the exact cumulative claim and linked evidence.', $disputed ? 'critical' : 'high', ['baselineId' => $baselineId, 'claimId' => $id, 'snapshotSha256' => $sha, 'projectRecordId' => $recordId]);
            (new AuditService($this->pdo))->append($organisationId, $userId, 'finance.claim_submitted', 'commercial_payment_claim', $id, ['baselineId' => $baselineId, 'claimedToDateCents' => $claimed, 'disputed' => $disputed, 'snapshotSha256' => $sha, 'projectRecordId' => $recordId]);
            return ['id' => $id, 'baselineId' => $baselineId, 'claimNumber' => $claimNumber, 'revision' => 1, 'claimedToDateCents' => $claimed, 'currency' => $baseline['currency'], 'milestoneId' => $milestoneId, 'evidenceReferences' => $evidence, 'disputed' => $disputed, 'status' => $status, 'snapshotSha256' => $sha];
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function certifyClaim(string $organisationId, string $userId, string $role, string $claimId, array $input): array
    {
        $actualRole = $this->assertMember($organisationId, $userId, $role, ['owner', 'admin', 'member']);
        if (($input['humanConfirmed'] ?? false) !== true) throw new CommercialControlException('commercial_certificate_confirmation_required');
        $gross = $this->unsignedCents($input['grossCertifiedToDateCents'] ?? null, true, 'commercial_certificate_gross_invalid');
        $deductions = $this->unsignedCents($input['otherDeductionsCents'] ?? 0, true, 'commercial_certificate_deductions_invalid');
        $reason = $this->text($input['reason'] ?? null, 1000, 'commercial_certificate_reason_required');
        return $this->transaction(function () use ($organisationId, $userId, $actualRole, $claimId, $gross, $deductions, $reason): array {
            $claim = $this->claimRow($organisationId, $claimId, true); $this->assertApprovalAuthority($organisationId, $userId, $actualRole, (string)$claim['project_id'], 'quantity_surveyor');
            if ($claim['status'] === 'withdrawn') throw new CommercialControlException('commercial_claim_not_certifiable');
            $claimed = (int)$claim['claimed_to_date_cents']; if ($gross > $claimed) throw new CommercialControlException('commercial_certificate_exceeds_claim');
            $prior = $this->pdo->prepare("SELECT COALESCE(SUM(r.net_payable_cents),0) previous_payments,COALESCE(MAX(r.gross_certified_to_date_cents),0) prior_gross,COALESCE(MAX(r.retention_held_to_date_cents),0) prior_retention FROM commercial_payment_certificates c JOIN commercial_payment_certificate_revisions r ON r.id=c.current_revision_id WHERE c.organisation_id=UNHEX(:o) AND c.baseline_id=UNHEX(:baseline) AND c.claim_id<>UNHEX(:claim) AND c.status<>'voided'");
            $prior->execute(['o' => $organisationId, 'baseline' => $claim['baseline_id'], 'claim' => $claimId]); $totals = $prior->fetch();
            $previous = (int)$totals['previous_payments']; $priorGross = (int)$totals['prior_gross']; $priorRetention = (int)$totals['prior_retention'];
            if ($gross < $priorGross) throw new CommercialControlException('commercial_certificate_cumulative_regression');
            $basisPoints = (int)$claim['retention_basis_points'];
            $retention = intdiv($gross, 10000) * $basisPoints + intdiv(($gross % 10000) * $basisPoints + 5000, 10000); $net = $gross - $retention - $deductions - $previous;
            if ($net < 0) throw new CommercialControlException('commercial_certificate_net_negative');
            $disputedAmount = max(0, $claimed - $gross); $locked = (bool)$claim['disputed']; $status = $locked ? 'disputed_locked' : 'approval_required';
            $certificate = $this->pdo->prepare('SELECT LOWER(HEX(id)) id,version FROM commercial_payment_certificates WHERE claim_id=UNHEX(:claim) FOR UPDATE'); $certificate->execute(['claim' => $claimId]); $existing = $certificate->fetch();
            if (is_array($existing)) { $certificateId = (string)$existing['id']; $revisionNumber = (int)$existing['version'] + 1; $this->pdo->prepare("UPDATE commercial_payment_certificate_revisions SET status='superseded' WHERE certificate_id=UNHEX(:certificate) AND status='current'")->execute(['certificate' => $certificateId]); }
            else { $certificateId = Identifier::generate(); $revisionNumber = 1; $this->pdo->prepare('INSERT INTO commercial_payment_certificates(id,organisation_id,baseline_id,claim_id,status,version,created_by) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:baseline),UNHEX(:claim),:status,1,UNHEX(:user))')->execute(['id' => $certificateId, 'o' => $organisationId, 'baseline' => $claim['baseline_id'], 'claim' => $claimId, 'status' => $status, 'user' => $userId]); }
            $revisionId = Identifier::generate(); $snapshot = ['certificateId' => $certificateId, 'certificateRevisionId' => $revisionId, 'revision' => $revisionNumber, 'claimId' => $claimId, 'claimRevisionId' => $claim['claim_revision_id'], 'baselineId' => $claim['baseline_id'], 'claimedToDateCents' => $claimed, 'grossCertifiedToDateCents' => $gross, 'retentionHeldToDateCents' => $retention, 'otherDeductionsCents' => $deductions, 'previousCertifiedPaymentsCents' => $previous, 'disputedAmountCents' => $disputedAmount, 'netPayableCents' => $net, 'currency' => $claim['currency'], 'status' => $status, 'automaticCertification' => false, 'certifiedBy' => $userId, 'certifiedAt' => $this->now()];
            $json = $this->json($snapshot); $sha = hash('sha256', $json);
            $this->pdo->prepare("INSERT INTO commercial_payment_certificate_revisions(id,organisation_id,certificate_id,revision,status,claimed_to_date_cents,gross_certified_to_date_cents,retention_held_to_date_cents,other_deductions_cents,previous_certified_payments_cents,disputed_amount_cents,net_payable_cents,snapshot_payload,snapshot_sha256,reason,certified_by) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:certificate),:revision,'current',:claimed,:gross,:retention,:deductions,:previous,:disputed,:net,:snapshot,:sha,:reason,UNHEX(:user))")->execute(['id' => $revisionId, 'o' => $organisationId, 'certificate' => $certificateId, 'revision' => $revisionNumber, 'claimed' => $claimed, 'gross' => $gross, 'retention' => $retention, 'deductions' => $deductions, 'previous' => $previous, 'disputed' => $disputedAmount, 'net' => $net, 'snapshot' => $json, 'sha' => $sha, 'reason' => $reason, 'user' => $userId]);
            $this->pdo->prepare('UPDATE commercial_payment_certificates SET current_revision_id=UNHEX(:revision),status=:status,version=:version WHERE id=UNHEX(:id)')->execute(['revision' => $revisionId, 'status' => $status, 'version' => $revisionNumber, 'id' => $certificateId]);
            $this->pdo->prepare('UPDATE commercial_payment_claims SET status=:status,version=version+1 WHERE id=UNHEX(:id)')->execute(['status' => $locked ? 'disputed_locked' : 'certified', 'id' => $claimId]);
            $retentionDelta = $retention - $priorRetention; $retentionSnapshot = ['baselineId' => $claim['baseline_id'], 'certificateRevisionId' => $revisionId, 'eventType' => $retentionDelta >= 0 ? 'accrual' : 'adjustment', 'amountCents' => $retentionDelta, 'balanceCents' => $retention, 'createdAt' => $this->now()]; $retentionJson = $this->json($retentionSnapshot);
            $this->pdo->prepare('INSERT INTO commercial_retention_ledger(id,organisation_id,baseline_id,certificate_revision_id,event_type,amount_cents,balance_cents,snapshot_payload,snapshot_sha256,created_by) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:baseline),UNHEX(:revision),:type,:amount,:balance,:snapshot,:sha,UNHEX(:user))')->execute(['id' => Identifier::generate(), 'o' => $organisationId, 'baseline' => $claim['baseline_id'], 'revision' => $revisionId, 'type' => $retentionDelta >= 0 ? 'accrual' : 'adjustment', 'amount' => $retentionDelta, 'balance' => $retention, 'snapshot' => $retentionJson, 'sha' => hash('sha256', $retentionJson), 'user' => $userId]);
            $recordId = (new ProjectRecordService($this->pdo))->issue(new ProjectContext($organisationId, $userId, (string)$claim['project_id'], $actualRole), ProjectPhase::ConstructionExecution, 'finance_commercial_control', 'payment_certificate', 'Payment certificate '.$claim['claim_number'], $locked ? 'pending_review' : 'issued', $snapshot + ['snapshotSha256' => $sha], ['required' => true, 'quantitySurveyorCertified' => true, 'releaseApproved' => false]);
            (new AuditService($this->pdo))->append($organisationId, $userId, 'finance.payment_certificate_issued', 'commercial_payment_certificate', $certificateId, ['claimId' => $claimId, 'certificateRevisionId' => $revisionId, 'snapshotSha256' => $sha, 'status' => $status, 'netPayableCents' => $net, 'providerPaidCents' => 0, 'projectRecordId' => $recordId]);
            return $snapshot + ['id' => $certificateId, 'revisionId' => $revisionId, 'snapshotSha256' => $sha];
        });
    }

    /** @return array{baselineId:string,heldToDateCents:int,currency:string} */
    public function retentionBalance(string $organisationId, string $userId, string $baselineId): array
    {
        $this->assertMember($organisationId, $userId, null, ['owner', 'admin', 'member', 'viewer']); $baseline = $this->baselineRow($organisationId, $baselineId, false);
        $statement = $this->pdo->prepare('SELECT balance_cents FROM commercial_retention_ledger WHERE organisation_id=UNHEX(:o) AND baseline_id=UNHEX(:baseline) ORDER BY created_at DESC,id DESC LIMIT 1'); $statement->execute(['o' => $organisationId, 'baseline' => $baselineId]); $balance = $statement->fetchColumn();
        return ['baselineId' => $baselineId, 'heldToDateCents' => $balance === false ? 0 : (int)$balance, 'currency' => (string)$baseline['currency']];
    }

    /** @return array<string,mixed> */
    public function workspace(string $organisationId, string $userId, string $projectId): array
    {
        $this->assertMember($organisationId, $userId, null, ['owner', 'admin', 'member', 'viewer']);
        $project = $this->pdo->prepare('SELECT project_name,current_phase FROM projects WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:o)'); $project->execute(['id' => $projectId, 'o' => $organisationId]); $projectRow = $project->fetch();
        if (!is_array($projectRow)) throw new CommercialControlException('commercial_project_not_found');
        $baselineStatement = $this->pdo->prepare("SELECT LOWER(HEX(id)) id FROM commercial_baselines WHERE organisation_id=UNHEX(:o) AND project_id=UNHEX(:p) AND status='active' ORDER BY created_at DESC LIMIT 1"); $baselineStatement->execute(['o' => $organisationId, 'p' => $projectId]); $baselineId = $baselineStatement->fetchColumn();
        $providers = $this->pdo->prepare("SELECT LOWER(HEX(id)) id FROM financial_providers WHERE organisation_id=UNHEX(:o) AND status='active' ORDER BY name,id"); $providers->execute(['o' => $organisationId]); $providerRows = array_map(fn(string $id): array => $this->provider($organisationId, $id), $providers->fetchAll(PDO::FETCH_COLUMN));
        if (!is_string($baselineId)) return ['projectId' => $projectId, 'projectName' => $projectRow['project_name'], 'projectPhase' => $projectRow['current_phase'], 'baseline' => null, 'schedules' => [], 'variations' => [], 'claims' => [], 'certificates' => [], 'retention' => null, 'providers' => $providerRows, 'releaseRequests' => [], 'providerBoundary' => $this->providerBoundary()];
        $baseline = $this->getBaseline($organisationId, $userId, $baselineId);
        $schedules = $this->snapshotRows("SELECT snapshot_payload FROM commercial_payment_schedules WHERE organisation_id=UNHEX(:o) AND baseline_id=UNHEX(:baseline) ORDER BY created_at DESC", ['o' => $organisationId, 'baseline' => $baselineId]);
        $variationIds = $this->ids("SELECT LOWER(HEX(id)) FROM commercial_variations WHERE organisation_id=UNHEX(:o) AND baseline_id=UNHEX(:baseline) ORDER BY created_at DESC", ['o' => $organisationId, 'baseline' => $baselineId]); $variations = array_map(fn(string $id): array => $this->variation($organisationId, $id), $variationIds);
        $claims = $this->snapshotRows('SELECT r.snapshot_payload FROM commercial_payment_claims c JOIN commercial_payment_claim_revisions r ON r.id=c.current_revision_id WHERE c.organisation_id=UNHEX(:o) AND c.baseline_id=UNHEX(:baseline) ORDER BY c.submitted_at DESC', ['o' => $organisationId, 'baseline' => $baselineId]);
        $certificates = $this->snapshotRows("SELECT r.snapshot_payload FROM commercial_payment_certificates c JOIN commercial_payment_certificate_revisions r ON r.id=c.current_revision_id WHERE c.organisation_id=UNHEX(:o) AND c.baseline_id=UNHEX(:baseline) AND c.status<>'voided' ORDER BY c.created_at DESC", ['o' => $organisationId, 'baseline' => $baselineId]);
        $releaseIds = $this->ids('SELECT LOWER(HEX(id)) FROM provider_release_requests WHERE organisation_id=UNHEX(:o) AND baseline_id=UNHEX(:baseline) ORDER BY created_at DESC', ['o' => $organisationId, 'baseline' => $baselineId]); $releases = array_map(fn(string $id): array => $this->releaseRequest($organisationId, $id), $releaseIds);
        return ['projectId' => $projectId, 'projectName' => $projectRow['project_name'], 'projectPhase' => $projectRow['current_phase'], 'baseline' => $baseline, 'schedules' => $schedules, 'variations' => $variations, 'claims' => $claims, 'certificates' => $certificates, 'retention' => $this->retentionBalance($organisationId, $userId, $baselineId), 'providers' => $providerRows, 'releaseRequests' => $releases, 'providerBoundary' => $this->providerBoundary()];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function registerProvider(string $organisationId, string $userId, string $role, array $input): array
    {
        $this->assertMember($organisationId, $userId, $role, ['owner', 'admin']);
        $key = $this->text($input['providerKey'] ?? null, 100, 'financial_provider_key_required');
        if (preg_match('/\A[a-z0-9][a-z0-9_-]*\z/', $key) !== 1) throw new CommercialControlException('financial_provider_key_invalid');
        $name = $this->text($input['name'] ?? null, 220, 'financial_provider_name_required');
        $type = $input['providerType'] ?? null; if (!is_string($type) || !in_array($type, ['payment_provider', 'escrow_provider', 'trust_account_provider'], true)) throw new CommercialControlException('financial_provider_type_invalid');
        $registered = $input['registered'] ?? null; if (!is_bool($registered)) throw new CommercialControlException('financial_provider_registration_state_required');
        $capabilities = $this->providerCapabilities($input['capabilities'] ?? null); $reference = $input['configurationReference'] ?? null;
        if ($reference !== null) $reference = $this->text($reference, 220, 'financial_provider_configuration_reference_invalid');
        $id = Identifier::generate();
        $this->pdo->prepare("INSERT INTO financial_providers(id,organisation_id,provider_key,name,provider_type,registered,capabilities,live_configured,configuration_reference,status,created_by) VALUES(UNHEX(:id),UNHEX(:o),:key,:name,:type,:registered,:capabilities,0,:reference,'active',UNHEX(:user))")->execute(['id' => $id, 'o' => $organisationId, 'key' => $key, 'name' => $name, 'type' => $type, 'registered' => $registered ? 1 : 0, 'capabilities' => $this->json($capabilities), 'reference' => $reference, 'user' => $userId]);
        (new AuditService($this->pdo))->append($organisationId, $userId, 'finance.provider_registered', 'financial_provider', $id, ['providerKey' => $key, 'providerType' => $type, 'registered' => $registered, 'capabilities' => $capabilities, 'liveConfigured' => false, 'secretsStoredInResponse' => false]);
        return $this->provider($organisationId, $id);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createReleaseRequest(string $organisationId, string $userId, string $role, string $certificateRevisionId, array $input): array
    {
        $this->assertMember($organisationId, $userId, $role, ['owner', 'admin']);
        if (($input['humanConfirmed'] ?? false) !== true) throw new CommercialControlException('provider_release_confirmation_required');
        $providerId = $this->id($input['providerId'] ?? null, 'financial_provider_not_found'); $provider = $this->providerRow($organisationId, $providerId, false);
        if (!(bool)$provider['registered'] || $provider['status'] !== 'active' || !in_array('release', $this->decodeList((string)$provider['capabilities']), true)) throw new CommercialControlException('financial_provider_not_eligible');
        $reason = $this->text($input['reason'] ?? null, 1000, 'provider_release_reason_required');
        $statement = $this->pdo->prepare("SELECT LOWER(HEX(r.id)) revision_id,LOWER(HEX(c.id)) certificate_id,LOWER(HEX(c.baseline_id)) baseline_id,LOWER(HEX(b.project_id)) project_id,c.status,r.net_payable_cents,b.currency,r.snapshot_sha256 FROM commercial_payment_certificate_revisions r JOIN commercial_payment_certificates c ON c.id=r.certificate_id AND c.current_revision_id=r.id JOIN commercial_baselines b ON b.id=c.baseline_id AND b.organisation_id=c.organisation_id WHERE r.id=UNHEX(:revision) AND r.organisation_id=UNHEX(:o) AND r.status='current'");
        $statement->execute(['revision' => $certificateRevisionId, 'o' => $organisationId]); $certificate = $statement->fetch(); if (!is_array($certificate)) throw new CommercialControlException('commercial_certificate_revision_not_found');
        $amount = (int)$certificate['net_payable_cents']; if ($amount <= 0) throw new CommercialControlException('provider_release_amount_invalid');
        $status = $certificate['status'] === 'disputed_locked' ? 'disputed_locked' : ((bool)$provider['live_configured'] ? 'approval_required' : 'provider_configuration_required');
        return $this->transaction(function () use ($organisationId, $userId, $role, $providerId, $provider, $reason, $certificateRevisionId, $certificate, $amount, $status): array {
            $id = Identifier::generate();
            $this->pdo->prepare('INSERT INTO provider_release_requests(id,organisation_id,baseline_id,certificate_revision_id,provider_id,requested_amount_cents,currency,provider_paid_cents,status,reason,version,created_by) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:baseline),UNHEX(:revision),UNHEX(:provider),:amount,:currency,0,:status,:reason,1,UNHEX(:user))')->execute(['id' => $id, 'o' => $organisationId, 'baseline' => $certificate['baseline_id'], 'revision' => $certificateRevisionId, 'provider' => $providerId, 'amount' => $amount, 'currency' => $certificate['currency'], 'status' => $status, 'reason' => $reason, 'user' => $userId]);
            $payload = ['releaseRequestId' => $id, 'certificateId' => $certificate['certificate_id'], 'certificateRevisionId' => $certificateRevisionId, 'certificateSnapshotSha256' => $certificate['snapshot_sha256'], 'providerId' => $providerId, 'providerKey' => $provider['provider_key'], 'requestedAmountCents' => $amount, 'providerPaidCents' => 0, 'currency' => $certificate['currency'], 'status' => $status, 'fundsHeldByArchitex' => false, 'automaticRelease' => false];
            $recordId = (new ProjectRecordService($this->pdo))->issue(new ProjectContext($organisationId, $userId, (string)$certificate['project_id'], $role), ProjectPhase::ConstructionExecution, 'finance_commercial_control', 'provider_release_request', 'Third-party provider release request', 'pending_review', $payload, ['required' => true, 'clientApproved' => false, 'leadProfessionalApproved' => false, 'providerConfigured' => (bool)$provider['live_configured']]);
            (new InboxService($this->pdo))->create(new ProjectContext($organisationId, $userId, (string)$certificate['project_id'], $role), $status === 'disputed_locked' ? 'finance_release_dispute_locked' : 'finance_provider_configuration_required', $status === 'disputed_locked' ? 'Dispute lock prevents provider release' : 'Configure a registered financial provider', $status === 'disputed_locked' ? 'Resolve the claim dispute before any release workflow can continue.' : 'Approvals may be recorded, but no live release can be represented until a real provider connector is configured and verified.', 'critical', $payload + ['projectRecordId' => $recordId]);
            (new AuditService($this->pdo))->append($organisationId, $userId, 'finance.provider_release_request_created', 'provider_release_request', $id, $payload + ['projectRecordId' => $recordId]);
            return $this->releaseRequest($organisationId, $id);
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function approveReleaseRequest(string $organisationId, string $userId, string $role, string $releaseRequestId, array $input): array
    {
        $actualRole = $this->assertMember($organisationId, $userId, $role, ['owner', 'admin', 'member']);
        if (($input['humanConfirmed'] ?? false) !== true) throw new CommercialControlException('provider_release_approval_confirmation_required');
        $type = $input['approvalType'] ?? null; if (!is_string($type) || !in_array($type, ['lead_professional', 'client'], true)) throw new CommercialControlException('provider_release_approval_type_invalid');
        $decision = $input['decision'] ?? null; if (!is_string($decision) || !in_array($decision, ['approved', 'rejected'], true)) throw new CommercialControlException('provider_release_decision_invalid');
        $reason = $this->text($input['reason'] ?? null, 1000, 'provider_release_decision_reason_required');
        return $this->transaction(function () use ($organisationId, $userId, $actualRole, $releaseRequestId, $type, $decision, $reason): array {
            $release = $this->releaseRow($organisationId, $releaseRequestId, true); if (in_array($release['status'], ['submitted_to_provider', 'provider_confirmed_paid', 'failed', 'refunded', 'rejected'], true)) throw new CommercialControlException('provider_release_not_approvable');
            $this->assertApprovalAuthority($organisationId, $userId, $actualRole, (string)$release['project_id'], $type);
            try { $this->pdo->prepare('INSERT INTO provider_release_approvals(id,organisation_id,release_request_id,approval_type,decision,reason,decided_by) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:release),:type,:decision,:reason,UNHEX(:user))')->execute(['id' => Identifier::generate(), 'o' => $organisationId, 'release' => $releaseRequestId, 'type' => $type, 'decision' => $decision, 'reason' => $reason, 'user' => $userId]); }
            catch (Throwable $exception) { throw new CommercialControlException('provider_release_approval_conflict'); }
            if ($decision === 'rejected') $status = 'rejected';
            elseif ($release['certificate_status'] === 'disputed_locked') $status = 'disputed_locked';
            elseif (!(bool)$release['live_configured']) $status = 'provider_configuration_required';
            else { $count = $this->pdo->prepare("SELECT COUNT(DISTINCT approval_type) FROM provider_release_approvals WHERE release_request_id=UNHEX(:release) AND decision='approved'"); $count->execute(['release' => $releaseRequestId]); $status = (int)$count->fetchColumn() === 2 ? 'approved_for_provider_request' : 'approval_required'; }
            $this->pdo->prepare('UPDATE provider_release_requests SET status=:status,version=version+1 WHERE id=UNHEX(:id)')->execute(['status' => $status, 'id' => $releaseRequestId]);
            (new AuditService($this->pdo))->append($organisationId, $userId, 'finance.provider_release_decided', 'provider_release_request', $releaseRequestId, ['approvalType' => $type, 'decision' => $decision, 'status' => $status, 'automaticRelease' => false, 'fundsHeldByArchitex' => false]);
            return $this->releaseRequest($organisationId, $releaseRequestId);
        });
    }

    /** @return array<string,mixed> */
    public function recordProviderWebhook(string $providerId, string $rawBody, string $timestampHeader, string $signatureHeader): array
    {
        $providerId = $this->id(strtolower($providerId), 'financial_provider_not_found');
        $provider = $this->providerForWebhook($providerId);
        if (!(bool)$provider['registered'] || !(bool)$provider['live_configured'] || $provider['status'] !== 'active' || !in_array('webhook_status', $this->decodeList((string)$provider['capabilities']), true)) throw new CommercialControlException('financial_provider_webhook_unavailable');
        if (preg_match('/\A[0-9]{10}\z/', $timestampHeader) !== 1 || abs(time() - (int)$timestampHeader) > 300) throw new CommercialControlException('financial_provider_webhook_timestamp_invalid');
        if (preg_match('/\A[a-f0-9]{64}\z/i', $signatureHeader) !== 1) throw new CommercialControlException('financial_provider_webhook_signature_invalid');
        $environmentKey = 'FINANCE_PROVIDER_'.preg_replace('/[^A-Z0-9]+/', '_', strtoupper((string)$provider['provider_key'])).'_WEBHOOK_SECRET';
        $secret = getenv($environmentKey); if (!is_string($secret) || strlen($secret) < 32) throw new CommercialControlException('financial_provider_webhook_unavailable');
        $expected = hash_hmac('sha256', $timestampHeader.'.'.$rawBody, $secret); if (!hash_equals($expected, strtolower($signatureHeader))) throw new CommercialControlException('financial_provider_webhook_signature_invalid');
        try { $payload = json_decode($rawBody, true, 32, JSON_THROW_ON_ERROR); } catch (Throwable) { throw new CommercialControlException('financial_provider_webhook_payload_invalid'); }
        if (!is_array($payload)) throw new CommercialControlException('financial_provider_webhook_payload_invalid');
        $eventId = $this->text($payload['eventId'] ?? null, 180, 'financial_provider_webhook_event_id_required');
        $eventType = $payload['eventType'] ?? null; if (!is_string($eventType) || !in_array($eventType, ['payment_processing', 'payment_paid', 'payment_failed', 'payment_refunded'], true)) throw new CommercialControlException('financial_provider_webhook_event_type_invalid');
        $providerReference = $this->text($payload['providerReference'] ?? null, 220, 'financial_provider_webhook_reference_required');
        $amount = $this->unsignedCents($payload['amountCents'] ?? null, true, 'financial_provider_webhook_amount_invalid');
        $currency = strtoupper($this->text($payload['currency'] ?? null, 3, 'financial_provider_webhook_currency_invalid')); if (preg_match('/\A[A-Z]{3}\z/', $currency) !== 1) throw new CommercialControlException('financial_provider_webhook_currency_invalid');
        return $this->transaction(function () use ($provider, $providerId, $eventId, $eventType, $providerReference, $amount, $currency, $rawBody, $signatureHeader): array {
            $existing = $this->webhookEvent($providerId, $eventId); if ($existing !== null) return $existing + ['duplicate' => true];
            $release = $this->pdo->prepare('SELECT LOWER(HEX(id)) id,requested_amount_cents,currency,status,provider_paid_cents FROM provider_release_requests WHERE provider_id=UNHEX(:provider) AND provider_reference=:reference FOR UPDATE'); $release->execute(['provider' => $providerId, 'reference' => $providerReference]); $row = $release->fetch();
            $rejection = null;
            if (!is_array($row)) $rejection = 'provider_release_reference_not_found';
            elseif ((int)$row['requested_amount_cents'] !== $amount || !hash_equals((string)$row['currency'], $currency)) $rejection = 'provider_release_amount_currency_mismatch';
            elseif (!in_array($row['status'], ['submitted_to_provider', 'provider_confirmed_paid', 'failed', 'refunded'], true)) $rejection = 'provider_release_status_invalid';
            $outcome = $rejection === null ? 'applied' : 'rejected'; $releaseId = is_array($row) ? (string)$row['id'] : null;
            $payloadSha = hash('sha256', $rawBody); $signatureSha = hash('sha256', strtolower($signatureHeader));
            $this->pdo->prepare('INSERT INTO provider_webhook_events(id,organisation_id,provider_id,release_request_id,external_event_id,event_type,provider_reference,amount_cents,currency,signature_sha256,payload_sha256,payload,outcome,rejection_reason) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:provider),UNHEX(:release),:event,:type,:reference,:amount,:currency,:signature_sha,:payload_sha,:payload,:outcome,:rejection)')->execute(['id' => Identifier::generate(), 'o' => $provider['organisation_id'], 'provider' => $providerId, 'release' => $releaseId, 'event' => $eventId, 'type' => $eventType, 'reference' => $providerReference, 'amount' => $amount, 'currency' => $currency, 'signature_sha' => $signatureSha, 'payload_sha' => $payloadSha, 'payload' => $rawBody, 'outcome' => $outcome, 'rejection' => $rejection]);
            if ($rejection === null && is_array($row)) {
                $status = match ($eventType) { 'payment_processing' => 'submitted_to_provider', 'payment_paid' => 'provider_confirmed_paid', 'payment_failed' => 'failed', 'payment_refunded' => 'refunded' };
                $paid = $eventType === 'payment_paid' ? $amount : ($eventType === 'payment_processing' ? (int)$row['provider_paid_cents'] : 0);
                $this->pdo->prepare('UPDATE provider_release_requests SET status=:status,provider_paid_cents=:paid,version=version+1 WHERE id=UNHEX(:id)')->execute(['status' => $status, 'paid' => $paid, 'id' => $row['id']]);
                (new AuditService($this->pdo))->append((string)$provider['organisation_id'], null, 'finance.provider_webhook_applied', 'provider_release_request', (string)$row['id'], ['providerId' => $providerId, 'externalEventId' => $eventId, 'eventType' => $eventType, 'providerReference' => $providerReference, 'amountCents' => $amount, 'currency' => $currency, 'payloadSha256' => $payloadSha, 'providerPaidCents' => $paid]);
            }
            return ['externalEventId' => $eventId, 'eventType' => $eventType, 'providerReference' => $providerReference, 'outcome' => $outcome, 'rejectionReason' => $rejection, 'releaseRequestId' => $releaseId, 'releaseStatus' => $rejection === null ? $status : (is_array($row) ? $row['status'] : null), 'providerPaidCents' => $rejection === null ? $paid : (is_array($row) ? (int)$row['provider_paid_cents'] : 0), 'payloadSha256' => $payloadSha, 'duplicate' => false];
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createVariation(string $organisationId, string $userId, string $role, string $baselineId, array $input): array
    {
        $this->assertMember($organisationId, $userId, $role, ['owner', 'admin', 'member']); $this->baselineRow($organisationId, $baselineId, false);
        $id = Identifier::generate(); $reference = $this->text($input['reference'] ?? null, 80, 'commercial_variation_reference_required');
        $title = $this->text($input['title'] ?? null, 220, 'commercial_variation_title_required'); $description = $this->text($input['description'] ?? null, 10000, 'commercial_variation_description_required');
        $amount = $input['amountCents'] ?? null; $days = $input['timeImpactDays'] ?? null;
        if (!is_int($amount) || $amount === 0 || abs($amount) > 9_000_000_000_000_000) throw new CommercialControlException('commercial_variation_amount_invalid');
        if (!is_int($days) || abs($days) > 3650) throw new CommercialControlException('commercial_variation_time_invalid');
        $this->pdo->prepare("INSERT INTO commercial_variations(id,organisation_id,baseline_id,reference,title,description,amount_cents,time_impact_days,status,version,created_by) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:baseline),:reference,:title,:description,:amount,:days,'draft',1,UNHEX(:user))")->execute(['id' => $id, 'o' => $organisationId, 'baseline' => $baselineId, 'reference' => $reference, 'title' => $title, 'description' => $description, 'amount' => $amount, 'days' => $days, 'user' => $userId]);
        return $this->variation($organisationId, $id);
    }

    /** @return array<string,mixed> */
    public function submitVariation(string $organisationId, string $userId, string $role, string $variationId, int $expectedVersion, bool $humanConfirmed, string $reason): array
    {
        $this->assertMember($organisationId, $userId, $role, ['owner', 'admin', 'member']); if (!$humanConfirmed) throw new CommercialControlException('commercial_variation_confirmation_required');
        $reason = $this->text($reason, 1000, 'commercial_variation_reason_required'); $variation = $this->variationRow($organisationId, $variationId, false);
        if ($variation['status'] !== 'draft' || (int)$variation['version'] !== $expectedVersion) throw new CommercialControlException('commercial_variation_version_conflict');
        return $this->transaction(function () use ($organisationId, $userId, $variationId, $expectedVersion, $reason): array {
            $updated = $this->pdo->prepare("UPDATE commercial_variations SET status='under_review',version=version+1,submitted_by=UNHEX(:user),submitted_at=UTC_TIMESTAMP(6) WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:o) AND status='draft' AND version=:version");
            $updated->execute(['user' => $userId, 'id' => $variationId, 'o' => $organisationId, 'version' => $expectedVersion]); if ($updated->rowCount() !== 1) throw new CommercialControlException('commercial_variation_version_conflict');
            $variation = $this->variationRow($organisationId, $variationId, false); $snapshot = $this->variationSnapshot($variation, $userId); $json = $this->json($snapshot); $sha = hash('sha256', $json);
            $this->pdo->prepare('INSERT INTO commercial_variation_revisions(id,organisation_id,variation_id,revision,snapshot_payload,snapshot_sha256,reason,created_by) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:variation),1,:snapshot,:sha,:reason,UNHEX(:user))')->execute(['id' => Identifier::generate(), 'o' => $organisationId, 'variation' => $variationId, 'snapshot' => $json, 'sha' => $sha, 'reason' => $reason, 'user' => $userId]);
            (new AuditService($this->pdo))->append($organisationId, $userId, 'finance.variation_submitted', 'commercial_variation', $variationId, ['baselineId' => $variation['baseline_id'], 'snapshotSha256' => $sha, 'automaticApproval' => false]);
            return $this->variation($organisationId, $variationId);
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function decideVariation(string $organisationId, string $userId, string $role, string $variationId, array $input): array
    {
        $actualRole = $this->assertMember($organisationId, $userId, $role, ['owner', 'admin', 'member']);
        if (($input['humanConfirmed'] ?? false) !== true) throw new CommercialControlException('commercial_variation_approval_confirmation_required');
        $type = $input['approvalType'] ?? null; $decision = $input['decision'] ?? null;
        if (!is_string($type) || !in_array($type, self::APPROVAL_TYPES, true)) throw new CommercialControlException('commercial_variation_approval_type_invalid');
        if (!is_string($decision) || !in_array($decision, ['approved', 'rejected'], true)) throw new CommercialControlException('commercial_variation_decision_invalid');
        $reason = $this->text($input['reason'] ?? null, 1000, 'commercial_variation_decision_reason_required');
        return $this->transaction(function () use ($organisationId, $userId, $actualRole, $variationId, $type, $decision, $reason): array {
            $variation = $this->variationRow($organisationId, $variationId, true); if ($variation['status'] !== 'under_review') throw new CommercialControlException('commercial_variation_not_under_review');
            $this->assertApprovalAuthority($organisationId, $userId, $actualRole, (string)$variation['project_id'], $type);
            try {
                $this->pdo->prepare('INSERT INTO commercial_variation_approvals(id,organisation_id,variation_id,approval_type,decision,reason,decided_by) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:variation),:type,:decision,:reason,UNHEX(:user))')->execute(['id' => Identifier::generate(), 'o' => $organisationId, 'variation' => $variationId, 'type' => $type, 'decision' => $decision, 'reason' => $reason, 'user' => $userId]);
            } catch (Throwable $exception) { throw new CommercialControlException('commercial_variation_approval_conflict'); }
            if ($decision === 'rejected') {
                $this->pdo->prepare("UPDATE commercial_variations SET status='rejected',version=version+1 WHERE id=UNHEX(:id)")->execute(['id' => $variationId]);
            } else {
                $count = $this->pdo->prepare("SELECT COUNT(DISTINCT approval_type) FROM commercial_variation_approvals WHERE variation_id=UNHEX(:variation) AND decision='approved'"); $count->execute(['variation' => $variationId]);
                if ((int)$count->fetchColumn() === 3) $this->incorporateVariation($organisationId, $userId, $variation);
            }
            (new AuditService($this->pdo))->append($organisationId, $userId, 'finance.variation_decided', 'commercial_variation', $variationId, ['approvalType' => $type, 'decision' => $decision, 'automaticApproval' => false]);
            return $this->variation($organisationId, $variationId);
        });
    }

    /** @param array<string,mixed> $variation */
    private function incorporateVariation(string $organisationId, string $userId, array $variation): void
    {
        $baseline = $this->baselineRow($organisationId, (string)$variation['baseline_id'], true); $newSum = (int)$baseline['current_contract_sum_cents'] + (int)$variation['amount_cents'];
        if ($newSum <= 0) throw new CommercialControlException('commercial_contract_sum_invalid'); $revision = (int)$baseline['revision'] + 1;
        $this->pdo->prepare("UPDATE commercial_baselines SET current_contract_sum_cents=:sum,revision=:revision WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:o)")->execute(['sum' => $newSum, 'revision' => $revision, 'id' => $baseline['id'], 'o' => $organisationId]);
        $this->pdo->prepare("UPDATE commercial_variations SET status='approved',version=version+1,approved_at=UTC_TIMESTAMP(6) WHERE id=UNHEX(:id)")->execute(['id' => $variation['id']]);
        $snapshot = ['baselineId' => $baseline['id'], 'projectId' => $baseline['project_id'], 'revision' => $revision, 'previousContractSumCents' => (int)$baseline['current_contract_sum_cents'], 'approvedVariationId' => $variation['id'], 'variationAmountCents' => (int)$variation['amount_cents'], 'currentContractSumCents' => $newSum, 'currency' => $baseline['currency'], 'automaticApproval' => false, 'approvedAt' => $this->now()];
        $this->insertBaselineRevision($organisationId, (string)$baseline['id'], $revision, 'approved_variation', (string)$variation['id'], $newSum, $snapshot, 'Three distinct authorised human approvals recorded.', $userId);
    }

    /** @param array<string,mixed> $snapshot @return array{snapshotSha256:string} */
    private function insertBaselineRevision(string $organisationId, string $baselineId, int $revision, string $changeType, ?string $sourceId, int $sum, array $snapshot, string $reason, string $userId): array
    {
        $json = $this->json($snapshot); $sha = hash('sha256', $json);
        $this->pdo->prepare('INSERT INTO commercial_baseline_revisions(id,organisation_id,baseline_id,revision,change_type,source_id,contract_sum_cents,snapshot_payload,snapshot_sha256,reason,created_by) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:baseline),:revision,:type,UNHEX(:source),:sum,:snapshot,:sha,:reason,UNHEX(:user))')->execute(['id' => Identifier::generate(), 'o' => $organisationId, 'baseline' => $baselineId, 'revision' => $revision, 'type' => $changeType, 'source' => $sourceId, 'sum' => $sum, 'snapshot' => $json, 'sha' => $sha, 'reason' => $reason, 'user' => $userId]);
        return ['snapshotSha256' => $sha];
    }

    /** @return array<string,mixed> */
    private function sourceAppointment(string $organisationId, string $projectId, string $appointmentId): array
    {
        $statement = $this->pdo->prepare("SELECT proposal_hash,DATE_FORMAT(formally_issued_at,'%Y-%m-%dT%H:%i:%s.%fZ') formally_issued_at FROM professional_appointments WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:o) AND project_id=UNHEX(:p) AND formally_issued_at IS NOT NULL AND status='confirmed'");
        $statement->execute(['id' => $appointmentId, 'o' => $organisationId, 'p' => $projectId]); $row = $statement->fetch();
        if (!is_array($row)) throw new CommercialControlException('commercial_source_appointment_not_formally_issued'); return $row;
    }

    /** @return array<string,mixed> */
    private function baselineRow(string $organisationId, string $baselineId, bool $lock): array
    {
        $statement = $this->pdo->prepare('SELECT LOWER(HEX(id)) id,LOWER(HEX(project_id)) project_id,current_contract_sum_cents,currency,revision,status FROM commercial_baselines WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:o)'.($lock ? ' FOR UPDATE' : ''));
        $statement->execute(['id' => $baselineId, 'o' => $organisationId]); $row = $statement->fetch(); if (!is_array($row) || $row['status'] !== 'active') throw new CommercialControlException('commercial_baseline_not_found'); return $row;
    }

    /** @return array<string,mixed> */
    private function claimRow(string $organisationId, string $claimId, bool $lock): array
    {
        $statement = $this->pdo->prepare('SELECT LOWER(HEX(c.id)) id,LOWER(HEX(c.baseline_id)) baseline_id,LOWER(HEX(b.project_id)) project_id,c.claim_number,c.status,LOWER(HEX(c.current_revision_id)) claim_revision_id,r.claimed_to_date_cents,r.disputed,b.currency,b.retention_basis_points FROM commercial_payment_claims c JOIN commercial_payment_claim_revisions r ON r.id=c.current_revision_id JOIN commercial_baselines b ON b.id=c.baseline_id AND b.organisation_id=c.organisation_id WHERE c.id=UNHEX(:id) AND c.organisation_id=UNHEX(:o)'.($lock ? ' FOR UPDATE' : ''));
        $statement->execute(['id' => $claimId, 'o' => $organisationId]); $row = $statement->fetch(); if (!is_array($row)) throw new CommercialControlException('commercial_claim_not_found'); return $row;
    }

    /** @return array<string,mixed> */
    private function providerRow(string $organisationId, string $providerId, bool $lock): array
    {
        $statement = $this->pdo->prepare('SELECT LOWER(HEX(id)) id,provider_key,name,provider_type,registered,capabilities,live_configured,configuration_reference,status FROM financial_providers WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:o)'.($lock ? ' FOR UPDATE' : ''));
        $statement->execute(['id' => $providerId, 'o' => $organisationId]); $row = $statement->fetch(); if (!is_array($row)) throw new CommercialControlException('financial_provider_not_found'); return $row;
    }

    /** @return array<string,mixed> */
    private function providerForWebhook(string $providerId): array
    {
        $statement = $this->pdo->prepare('SELECT LOWER(HEX(id)) id,LOWER(HEX(organisation_id)) organisation_id,provider_key,registered,capabilities,live_configured,status FROM financial_providers WHERE id=UNHEX(:id)'); $statement->execute(['id' => $providerId]); $row = $statement->fetch(); if (!is_array($row)) throw new CommercialControlException('financial_provider_not_found'); return $row;
    }

    /** @return array<string,mixed>|null */
    private function webhookEvent(string $providerId, string $eventId): ?array
    {
        $statement = $this->pdo->prepare('SELECT e.external_event_id,e.event_type,e.provider_reference,e.outcome,e.rejection_reason,LOWER(HEX(e.release_request_id)) release_request_id,e.payload_sha256,q.status release_status,q.provider_paid_cents FROM provider_webhook_events e LEFT JOIN provider_release_requests q ON q.id=e.release_request_id WHERE e.provider_id=UNHEX(:provider) AND e.external_event_id=:event'); $statement->execute(['provider' => $providerId, 'event' => $eventId]); $row = $statement->fetch();
        if (!is_array($row)) return null; return ['externalEventId' => $row['external_event_id'], 'eventType' => $row['event_type'], 'providerReference' => $row['provider_reference'], 'outcome' => $row['outcome'], 'rejectionReason' => $row['rejection_reason'], 'releaseRequestId' => $row['release_request_id'], 'releaseStatus' => $row['release_status'], 'providerPaidCents' => $row['provider_paid_cents'] === null ? 0 : (int)$row['provider_paid_cents'], 'payloadSha256' => $row['payload_sha256']];
    }

    /** @return array<string,mixed> */
    private function provider(string $organisationId, string $providerId): array
    {
        $row = $this->providerRow($organisationId, $providerId, false);
        return ['id' => $row['id'], 'providerKey' => $row['provider_key'], 'name' => $row['name'], 'providerType' => $row['provider_type'], 'registered' => (bool)$row['registered'], 'capabilities' => $this->decodeList((string)$row['capabilities']), 'liveConfigured' => (bool)$row['live_configured'], 'configurationReference' => $row['configuration_reference'], 'status' => $row['status'], 'fundsHeldByArchitex' => false];
    }

    /** @return array<string,mixed> */
    private function releaseRow(string $organisationId, string $releaseRequestId, bool $lock): array
    {
        $statement = $this->pdo->prepare('SELECT LOWER(HEX(q.id)) id,LOWER(HEX(q.baseline_id)) baseline_id,LOWER(HEX(q.certificate_revision_id)) certificate_revision_id,LOWER(HEX(q.provider_id)) provider_id,q.requested_amount_cents,q.currency,q.provider_paid_cents,q.status,q.provider_reference,q.version,LOWER(HEX(b.project_id)) project_id,p.live_configured,c.status certificate_status FROM provider_release_requests q JOIN financial_providers p ON p.id=q.provider_id AND p.organisation_id=q.organisation_id JOIN commercial_baselines b ON b.id=q.baseline_id AND b.organisation_id=q.organisation_id JOIN commercial_payment_certificate_revisions r ON r.id=q.certificate_revision_id JOIN commercial_payment_certificates c ON c.id=r.certificate_id WHERE q.id=UNHEX(:id) AND q.organisation_id=UNHEX(:o)'.($lock ? ' FOR UPDATE' : ''));
        $statement->execute(['id' => $releaseRequestId, 'o' => $organisationId]); $row = $statement->fetch(); if (!is_array($row)) throw new CommercialControlException('provider_release_request_not_found'); return $row;
    }

    /** @return array<string,mixed> */
    private function releaseRequest(string $organisationId, string $releaseRequestId): array
    {
        $row = $this->releaseRow($organisationId, $releaseRequestId, false); $statement = $this->pdo->prepare('SELECT approval_type,decision,reason,LOWER(HEX(decided_by)) decided_by,DATE_FORMAT(decided_at,\'%Y-%m-%dT%H:%i:%s.%fZ\') decided_at FROM provider_release_approvals WHERE release_request_id=UNHEX(:id) ORDER BY decided_at,id'); $statement->execute(['id' => $releaseRequestId]);
        $approvals = array_map(static fn(array $a): array => ['approvalType' => $a['approval_type'], 'decision' => $a['decision'], 'reason' => $a['reason'], 'decidedBy' => $a['decided_by'], 'decidedAt' => $a['decided_at']], $statement->fetchAll());
        return ['id' => $row['id'], 'baselineId' => $row['baseline_id'], 'certificateRevisionId' => $row['certificate_revision_id'], 'providerId' => $row['provider_id'], 'requestedAmountCents' => (int)$row['requested_amount_cents'], 'currency' => $row['currency'], 'providerPaidCents' => (int)$row['provider_paid_cents'], 'status' => $row['status'], 'providerReference' => $row['provider_reference'], 'version' => (int)$row['version'], 'approvals' => $approvals, 'fundsHeldByArchitex' => false, 'automaticRelease' => false];
    }

    /** @return array<string,mixed> */
    private function variationRow(string $organisationId, string $variationId, bool $lock): array
    {
        $statement = $this->pdo->prepare('SELECT LOWER(HEX(v.id)) id,LOWER(HEX(v.baseline_id)) baseline_id,LOWER(HEX(b.project_id)) project_id,v.reference,v.title,v.description,v.amount_cents,v.time_impact_days,v.status,v.version FROM commercial_variations v JOIN commercial_baselines b ON b.id=v.baseline_id AND b.organisation_id=v.organisation_id WHERE v.id=UNHEX(:id) AND v.organisation_id=UNHEX(:o)'.($lock ? ' FOR UPDATE' : ''));
        $statement->execute(['id' => $variationId, 'o' => $organisationId]); $row = $statement->fetch(); if (!is_array($row)) throw new CommercialControlException('commercial_variation_not_found'); return $row;
    }

    /** @return array<string,mixed> */
    private function variation(string $organisationId, string $variationId): array
    {
        $row = $this->variationRow($organisationId, $variationId, false); $statement = $this->pdo->prepare('SELECT approval_type,decision,reason,LOWER(HEX(decided_by)) decided_by,DATE_FORMAT(decided_at,\'%Y-%m-%dT%H:%i:%s.%fZ\') decided_at FROM commercial_variation_approvals WHERE variation_id=UNHEX(:id) ORDER BY decided_at,id'); $statement->execute(['id' => $variationId]);
        $approvals = array_map(static fn(array $a): array => ['approvalType' => $a['approval_type'], 'decision' => $a['decision'], 'reason' => $a['reason'], 'decidedBy' => $a['decided_by'], 'decidedAt' => $a['decided_at']], $statement->fetchAll());
        return ['id' => $row['id'], 'baselineId' => $row['baseline_id'], 'projectId' => $row['project_id'], 'reference' => $row['reference'], 'title' => $row['title'], 'description' => $row['description'], 'amountCents' => (int)$row['amount_cents'], 'timeImpactDays' => (int)$row['time_impact_days'], 'status' => $row['status'], 'version' => (int)$row['version'], 'approvals' => $approvals, 'automaticApproval' => false];
    }

    /** @param array<string,mixed> $variation @return array<string,mixed> */
    private function variationSnapshot(array $variation, string $userId): array { return ['variationId' => $variation['id'], 'baselineId' => $variation['baseline_id'], 'reference' => $variation['reference'], 'title' => $variation['title'], 'description' => $variation['description'], 'amountCents' => (int)$variation['amount_cents'], 'timeImpactDays' => (int)$variation['time_impact_days'], 'status' => $variation['status'], 'version' => (int)$variation['version'], 'automaticApproval' => false, 'submittedBy' => $userId, 'submittedAt' => $this->now()]; }

    private function assertApprovalAuthority(string $organisationId, string $userId, string $role, string $projectId, string $type): void
    {
        if ($type === 'client') { if (!in_array($role, ['owner', 'admin'], true)) throw new CommercialControlException('commercial_variation_approval_permission_denied'); return; }
        $discipline = $type === 'quantity_surveyor' ? 'quantity_surveyor' : null;
        $sql = 'SELECT COUNT(*) FROM project_team_members t JOIN projects p ON p.id=t.project_id AND p.organisation_id=t.organisation_id WHERE t.organisation_id=UNHEX(:o) AND t.project_id=UNHEX(:p) AND t.user_id=UNHEX(:u) AND t.status=\'active\'';
        if ($discipline !== null) $sql .= ' AND t.discipline=:discipline'; else $sql .= ' AND t.discipline=p.lead_professional_role';
        $statement = $this->pdo->prepare($sql); $values = ['o' => $organisationId, 'p' => $projectId, 'u' => $userId]; if ($discipline !== null) $values['discipline'] = $discipline; $statement->execute($values);
        if ((int)$statement->fetchColumn() !== 1) throw new CommercialControlException('commercial_variation_approval_permission_denied');
    }

    /** @param list<string> $allowed */
    private function assertMember(string $organisationId, string $userId, ?string $claimedRole, array $allowed): string
    {
        $statement = $this->pdo->prepare("SELECT role FROM organisation_memberships WHERE organisation_id=UNHEX(:o) AND user_id=UNHEX(:u) AND status='active'"); $statement->execute(['o' => $organisationId, 'u' => $userId]); $actual = $statement->fetchColumn();
        if (!is_string($actual) || !in_array($actual, $allowed, true) || ($claimedRole !== null && !hash_equals($actual, $claimedRole))) throw new CommercialControlException('commercial_permission_denied'); return $actual;
    }

    private function id(mixed $value, string $reason): string { if (!is_string($value) || preg_match('/\A[a-f0-9]{32}\z/', $value) !== 1) throw new CommercialControlException($reason); return $value; }
    private function text(mixed $value, int $max, string $reason): string { if (!is_string($value) || ($value = trim($value)) === '' || mb_strlen($value) > $max) throw new CommercialControlException($reason); return $value; }
    private function unsignedCents(mixed $value, bool $zeroAllowed, string $reason): int { if (!is_int($value) || $value < ($zeroAllowed ? 0 : 1) || $value > 9_000_000_000_000_000) throw new CommercialControlException($reason); return $value; }
    /** @return list<string> */ private function providerCapabilities(mixed $value): array { $allowed = ['collect', 'escrow_hold', 'release', 'payout', 'webhook_status']; if (!is_array($value) || $value === [] || count($value) > count($allowed)) throw new CommercialControlException('financial_provider_capabilities_invalid'); $result = []; foreach ($value as $capability) { if (!is_string($capability) || !in_array($capability, $allowed, true)) throw new CommercialControlException('financial_provider_capabilities_invalid'); $result[] = $capability; } return array_values(array_unique($result)); }
    /** @return list<string> */ private function decodeList(string $json): array { $value = json_decode($json, true, 512, JSON_THROW_ON_ERROR); if (!is_array($value)) return []; return array_values(array_filter($value, 'is_string')); }
    /** @return list<string> */ private function references(mixed $value): array { if (!is_array($value) || $value === [] || count($value) > 100) throw new CommercialControlException('commercial_claim_evidence_required'); $result = []; foreach ($value as $reference) { if (!is_string($reference) || ($reference = trim($reference)) === '' || mb_strlen($reference) > 500 || !preg_match('/\A[a-z][a-z0-9_-]*:[^\s]+\z/i', $reference)) throw new CommercialControlException('commercial_claim_evidence_invalid'); $result[] = $reference; } return array_values(array_unique($result)); }
    /** @param array<string,string> $bindings @return list<array<string,mixed>> */ private function snapshotRows(string $sql, array $bindings): array { $statement = $this->pdo->prepare($sql); $statement->execute($bindings); $result = []; foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $json) { $value = json_decode((string)$json, true, 512, JSON_THROW_ON_ERROR); if (is_array($value)) $result[] = $value; } return $result; }
    /** @param array<string,string> $bindings @return list<string> */ private function ids(string $sql, array $bindings): array { $statement = $this->pdo->prepare($sql); $statement->execute($bindings); return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN))); }
    /** @return array<string,mixed> */ private function providerBoundary(): array { return ['fundsHeldByArchitex' => false, 'moneyMovementRequiresRegisteredThirdParty' => true, 'automaticRelease' => false, 'credentialsConfigured' => false, 'livePaymentClaimed' => false, 'unconfiguredStatus' => 'provider_configuration_required']; }
    /** @param array<string,mixed> $value */ private function json(array $value): string { return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); }
    private function now(): string { return gmdate('Y-m-d\TH:i:s.u\Z'); }
    private function transaction(callable $operation): mixed { $this->pdo->beginTransaction(); try { $result = $operation(); $this->pdo->commit(); return $result; } catch (Throwable $exception) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $exception; } }
}
