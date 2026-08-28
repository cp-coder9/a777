<?php

declare(strict_types=1);

namespace Architex\Platform\Finance;

use Architex\Platform\Audit\AuditService;
use Architex\Platform\Identity\Identifier;
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
            $milestones[] = ['sequenceNumber' => $index + 1, 'label' => $this->text($item['label'] ?? null, 220, 'commercial_schedule_label_required'), 'amountCents' => $amount, 'dueDate' => $date->format('Y-m-d')];
            $previousDate = $date;
        }
        if ($total !== (int)$baseline['current_contract_sum_cents']) throw new CommercialControlException('commercial_schedule_total_mismatch');
        return $this->transaction(function () use ($organisationId, $userId, $baselineId, $title, $reason, $milestones, $total): array {
            $id = Identifier::generate(); $snapshot = ['scheduleId' => $id, 'baselineId' => $baselineId, 'title' => $title, 'scheduledTotalCents' => $total, 'milestones' => $milestones, 'humanConfirmed' => true, 'createdBy' => $userId, 'createdAt' => $this->now()];
            $json = $this->json($snapshot); $sha = hash('sha256', $json);
            $this->pdo->prepare("UPDATE commercial_payment_schedules SET status='superseded' WHERE organisation_id=UNHEX(:o) AND baseline_id=UNHEX(:baseline) AND status='issued'")->execute(['o' => $organisationId, 'baseline' => $baselineId]);
            $this->pdo->prepare("INSERT INTO commercial_payment_schedules(id,organisation_id,baseline_id,title,scheduled_total_cents,status,snapshot_payload,snapshot_sha256,reason,created_by) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:baseline),:title,:total,'issued',:snapshot,:sha,:reason,UNHEX(:user))")->execute(['id' => $id, 'o' => $organisationId, 'baseline' => $baselineId, 'title' => $title, 'total' => $total, 'snapshot' => $json, 'sha' => $sha, 'reason' => $reason, 'user' => $userId]);
            $insert = $this->pdo->prepare('INSERT INTO commercial_payment_milestones(id,organisation_id,schedule_id,sequence_number,label,amount_cents,due_date) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:schedule),:sequence,:label,:amount,:due)');
            foreach ($milestones as $milestone) $insert->execute(['id' => Identifier::generate(), 'o' => $organisationId, 'schedule' => $id, 'sequence' => $milestone['sequenceNumber'], 'label' => $milestone['label'], 'amount' => $milestone['amountCents'], 'due' => $milestone['dueDate']]);
            (new AuditService($this->pdo))->append($organisationId, $userId, 'finance.payment_schedule_issued', 'commercial_payment_schedule', $id, ['baselineId' => $baselineId, 'scheduledTotalCents' => $total, 'snapshotSha256' => $sha]);
            return ['id' => $id, 'baselineId' => $baselineId, 'title' => $title, 'scheduledTotalCents' => $total, 'milestones' => $milestones, 'status' => 'issued', 'snapshotSha256' => $sha];
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
    /** @param array<string,mixed> $value */ private function json(array $value): string { return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); }
    private function now(): string { return gmdate('Y-m-d\TH:i:s.u\Z'); }
    private function transaction(callable $operation): mixed { $this->pdo->beginTransaction(); try { $result = $operation(); $this->pdo->commit(); return $result; } catch (Throwable $exception) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $exception; } }
}
