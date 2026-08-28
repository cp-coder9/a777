<?php

declare(strict_types=1);

namespace Architex\Platform\Documents;

use Architex\Platform\Audit\AuditService;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Project\ProjectContext;
use Architex\Platform\Project\ProjectPhase;
use Architex\Platform\Records\ProjectRecordService;
use PDO;
use Throwable;

final readonly class DrawingIntelligenceService
{
    private const PROVENANCE = ['human_entered', 'provider_result'];
    private const DECISIONS = ['accepted', 'rejected'];
    private const DISCIPLINES = ['architectural', 'structural', 'civil', 'electrical', 'mechanical', 'fire', 'quantity_surveying', 'general'];
    private const PURPOSES = ['for_review', 'for_information', 'for_municipal_submission', 'for_tender', 'for_construction', 'as_built', 'closeout'];

    public function __construct(private PDO $pdo) {}

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function create(string $organisationId, string $userId, string $role, array $input): array
    {
        $this->writer($organisationId, $userId, $role);
        $revisionId = $this->id($input['revisionId'] ?? null, 'drawing_intelligence_revision_required');
        $revision = $this->revision($organisationId, $revisionId);
        $provenance = $this->choice($input['provenance'] ?? null, self::PROVENANCE, 'drawing_intelligence_provenance_invalid');
        $providerName = $this->nullableText($input['providerName'] ?? null, 120, 'drawing_intelligence_provider_invalid');
        $providerReference = $this->nullableText($input['providerReference'] ?? null, 180, 'drawing_intelligence_provider_invalid');
        $confidence = $this->confidence($input['confidence'] ?? null);

        if ($provenance === 'human_entered') {
            if ($providerName !== null || $providerReference !== null || $confidence !== null) {
                throw new DocumentException('drawing_intelligence_human_provenance_invalid');
            }
        } elseif ($providerName === null || $providerReference === null || $confidence === null) {
            throw new DocumentException('drawing_intelligence_provider_evidence_required');
        }

        $candidate = $this->candidate($input['candidate'] ?? null);
        $candidateJson = $this->json($candidate);
        $candidateSha256 = hash('sha256', $candidateJson);
        $id = Identifier::generate();

        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare("INSERT INTO drawing_intelligence_candidates(id,organisation_id,project_id,revision_id,provenance,provider_name,provider_reference,confidence,candidate_payload,candidate_sha256,status,created_by) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:project_id),UNHEX(:revision_id),:provenance,:provider_name,:provider_reference,:confidence,:candidate_payload,:candidate_sha256,'pending_review',UNHEX(:created_by))");
            $statement->execute([
                'id' => $id,
                'organisation_id' => $organisationId,
                'project_id' => $revision['projectId'],
                'revision_id' => $revisionId,
                'provenance' => $provenance,
                'provider_name' => $providerName,
                'provider_reference' => $providerReference,
                'confidence' => $confidence,
                'candidate_payload' => $candidateJson,
                'candidate_sha256' => $candidateSha256,
                'created_by' => $userId,
            ]);
            (new AuditService($this->pdo))->append($organisationId, $userId, 'drawing_intelligence.created', 'drawing_intelligence_candidate', $id, [
                'projectId' => $revision['projectId'],
                'revisionId' => $revisionId,
                'provenance' => $provenance,
                'providerName' => $providerName,
                'providerReference' => $providerReference,
                'confidence' => $confidence,
                'candidateSha256' => $candidateSha256,
                'metadataApplied' => false,
            ]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }

        return $this->get($organisationId, $userId, $id);
    }

    /** @return array<string,mixed> */
    public function review(string $organisationId, string $userId, string $role, string $id, string $decision, bool $humanConfirmed, string $reason): array
    {
        $this->writer($organisationId, $userId, $role);
        if (!$humanConfirmed) throw new DocumentException('drawing_intelligence_review_confirmation_required');
        $decision = $this->choice($decision, self::DECISIONS, 'drawing_intelligence_review_decision_invalid');
        $reason = $this->text($reason, 500, 'drawing_intelligence_review_reason_required');
        $current = $this->get($organisationId, $userId, $id);
        if ($current['status'] !== 'pending_review') throw new DocumentException('drawing_intelligence_not_reviewable');

        $recordStatus = $decision === 'accepted' ? 'approved' : 'rejected';
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare('UPDATE drawing_intelligence_candidates SET status=:decision,review_reason=:reason,reviewed_by=UNHEX(:reviewed_by),reviewed_at=UTC_TIMESTAMP(6) WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation_id) AND status=\'pending_review\'');
            $statement->execute(['decision' => $decision, 'reason' => $reason, 'reviewed_by' => $userId, 'id' => $current['id'], 'organisation_id' => $organisationId]);
            if ($statement->rowCount() !== 1) throw new DocumentException('drawing_intelligence_not_reviewable');

            $recordId = (new ProjectRecordService($this->pdo))->issue(
                new ProjectContext($organisationId, $userId, $current['projectId'], $role),
                ProjectPhase::from($current['phase']),
                'documents',
                'drawing_intelligence_review',
                'Drawing metadata candidate ' . substr($current['id'], 0, 8),
                $recordStatus,
                [
                    'candidateId' => $current['id'],
                    'revisionId' => $current['revisionId'],
                    'decision' => $decision,
                    'reason' => $reason,
                    'candidateSha256' => $current['candidateSha256'],
                    'provenance' => $current['provenance'],
                    'metadataApplied' => false,
                ],
                ['required' => true, 'approvedBy' => $userId, 'humanApprovalConfirmed' => true],
            );
            $this->pdo->prepare('UPDATE drawing_intelligence_candidates SET project_record_id=UNHEX(:record_id) WHERE id=UNHEX(:id)')->execute(['record_id' => $recordId, 'id' => $current['id']]);
            (new AuditService($this->pdo))->append($organisationId, $userId, 'drawing_intelligence.' . $decision, 'drawing_intelligence_candidate', $current['id'], [
                'projectId' => $current['projectId'],
                'revisionId' => $current['revisionId'],
                'decision' => $decision,
                'reason' => $reason,
                'candidateSha256' => $current['candidateSha256'],
                'metadataApplied' => false,
            ]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }

        return $this->get($organisationId, $userId, $current['id']);
    }

    /** @return array<string,mixed> */
    public function get(string $organisationId, string $userId, string $id): array
    {
        $this->member($organisationId, $userId);
        $id = $this->id($id, 'drawing_intelligence_not_found');
        $statement = $this->pdo->prepare("SELECT LOWER(HEX(c.id)) id,LOWER(HEX(c.project_id)) project_id,LOWER(HEX(c.revision_id)) revision_id,c.provenance,c.provider_name,c.provider_reference,c.confidence,c.candidate_payload,c.candidate_sha256,c.status,c.review_reason,LOWER(HEX(c.reviewed_by)) reviewed_by,DATE_FORMAT(c.reviewed_at,'%Y-%m-%dT%H:%i:%s.%fZ') reviewed_at,LOWER(HEX(c.project_record_id)) project_record_id,LOWER(HEX(c.created_by)) created_by,DATE_FORMAT(c.created_at,'%Y-%m-%dT%H:%i:%s.%fZ') created_at,p.current_phase phase FROM drawing_intelligence_candidates c JOIN projects p ON p.id=c.project_id AND p.organisation_id=c.organisation_id WHERE c.id=UNHEX(:id) AND c.organisation_id=UNHEX(:organisation_id)");
        $statement->execute(['id' => $id, 'organisation_id' => $organisationId]);
        $row = $statement->fetch();
        if (!is_array($row)) throw new DocumentException('drawing_intelligence_not_found');
        $candidate = json_decode((string) $row['candidate_payload'], true, 64, JSON_THROW_ON_ERROR);
        if (!hash_equals((string) $row['candidate_sha256'], hash('sha256', $this->json($candidate)))) {
            throw new DocumentException('drawing_intelligence_integrity_failed');
        }
        return [
            'id' => $row['id'], 'projectId' => $row['project_id'], 'revisionId' => $row['revision_id'],
            'provenance' => $row['provenance'], 'providerName' => $row['provider_name'],
            'providerReference' => $row['provider_reference'],
            'confidence' => $row['confidence'] === null ? null : (float) $row['confidence'],
            'candidate' => $candidate, 'candidateSha256' => $row['candidate_sha256'],
            'status' => $row['status'], 'reviewReason' => $row['review_reason'],
            'reviewedBy' => $row['reviewed_by'], 'reviewedAt' => $row['reviewed_at'],
            'projectRecordId' => $row['project_record_id'], 'createdBy' => $row['created_by'],
            'createdAt' => $row['created_at'], 'phase' => $row['phase'], 'metadataApplied' => false,
        ];
    }

    /** @return list<array<string,mixed>> */
    public function list(string $organisationId, string $userId, string $projectId): array
    {
        $this->member($organisationId, $userId);
        $this->project($organisationId, $projectId);
        $statement = $this->pdo->prepare('SELECT LOWER(HEX(id)) FROM drawing_intelligence_candidates WHERE organisation_id=UNHEX(:organisation_id) AND project_id=UNHEX(:project_id) ORDER BY created_at DESC,id DESC');
        $statement->execute(['organisation_id' => $organisationId, 'project_id' => $projectId]);
        return array_map(fn (string $id): array => $this->get($organisationId, $userId, $id), $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return array<string,mixed> */
    private function revision(string $organisationId, string $revisionId): array
    {
        $statement = $this->pdo->prepare('SELECT LOWER(HEX(r.project_id)) projectId FROM document_revisions r JOIN controlled_documents d ON d.id=r.document_id AND d.organisation_id=r.organisation_id WHERE r.id=UNHEX(:revision_id) AND r.organisation_id=UNHEX(:organisation_id)');
        $statement->execute(['revision_id' => $revisionId, 'organisation_id' => $organisationId]);
        $row = $statement->fetch();
        if (!is_array($row)) throw new DocumentException('drawing_intelligence_revision_not_found');
        return $row;
    }

    /** @return array<string,mixed> */
    private function candidate(mixed $value): array
    {
        if (!is_array($value)) throw new DocumentException('drawing_intelligence_candidate_required');
        $titleBlock = $value['titleBlock'] ?? null;
        if (!is_array($titleBlock)) throw new DocumentException('drawing_intelligence_title_block_required');
        $findings = $value['findings'] ?? null;
        if (!is_array($findings) || count($findings) > 100) throw new DocumentException('drawing_intelligence_findings_invalid');
        $cleanFindings = [];
        foreach ($findings as $finding) $cleanFindings[] = $this->text($finding, 500, 'drawing_intelligence_findings_invalid');
        return [
            'classification' => $this->text($value['classification'] ?? null, 120, 'drawing_intelligence_classification_required'),
            'detectedDiscipline' => $this->choice($value['detectedDiscipline'] ?? null, self::DISCIPLINES, 'drawing_intelligence_discipline_invalid'),
            'titleBlock' => [
                'drawingNumber' => $this->text($titleBlock['drawingNumber'] ?? null, 100, 'drawing_intelligence_title_block_invalid'),
                'title' => $this->text($titleBlock['title'] ?? null, 220, 'drawing_intelligence_title_block_invalid'),
                'scale' => $this->text($titleBlock['scale'] ?? null, 40, 'drawing_intelligence_title_block_invalid'),
                'date' => $this->text($titleBlock['date'] ?? null, 40, 'drawing_intelligence_title_block_invalid'),
                'author' => $this->text($titleBlock['author'] ?? null, 160, 'drawing_intelligence_title_block_invalid'),
            ],
            'revision' => $this->text($value['revision'] ?? null, 40, 'drawing_intelligence_revision_label_required'),
            'issuePurpose' => $this->choice($value['issuePurpose'] ?? null, self::PURPOSES, 'drawing_intelligence_issue_purpose_invalid'),
            'findings' => $cleanFindings,
            'suggestedNextAction' => $this->text($value['suggestedNextAction'] ?? null, 500, 'drawing_intelligence_next_action_required'),
        ];
    }

    private function confidence(mixed $value): ?float
    {
        if ($value === null || $value === '') return null;
        if (!is_numeric($value)) throw new DocumentException('drawing_intelligence_confidence_invalid');
        $confidence = (float) $value;
        if ($confidence < 0 || $confidence > 1) throw new DocumentException('drawing_intelligence_confidence_invalid');
        return $confidence;
    }

    private function project(string $organisationId, string $projectId): void
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM projects WHERE id=UNHEX(:project_id) AND organisation_id=UNHEX(:organisation_id)');
        $statement->execute(['project_id' => $this->id($projectId, 'drawing_intelligence_project_not_found'), 'organisation_id' => $organisationId]);
        if ($statement->fetchColumn() === false) throw new DocumentException('drawing_intelligence_project_not_found');
    }

    private function writer(string $organisationId, string $userId, string $role): void
    {
        if (!in_array($role, ['owner', 'admin', 'member'], true)) throw new DocumentException('drawing_intelligence_permission_denied');
        $this->member($organisationId, $userId);
    }

    private function member(string $organisationId, string $userId): void
    {
        $statement = $this->pdo->prepare("SELECT 1 FROM organisation_memberships WHERE organisation_id=UNHEX(:organisation_id) AND user_id=UNHEX(:user_id) AND status='active'");
        $statement->execute(['organisation_id' => $organisationId, 'user_id' => $userId]);
        if ($statement->fetchColumn() === false) throw new DocumentException('drawing_intelligence_permission_denied');
    }

    private function id(mixed $value, string $error): string
    {
        if (!is_string($value) || preg_match('/\A[a-f0-9]{32}\z/i', $value) !== 1) throw new DocumentException($error);
        return strtolower($value);
    }

    private function text(mixed $value, int $maximum, string $error): string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '' || mb_strlen($value) > $maximum) throw new DocumentException($error);
        return $value;
    }

    private function nullableText(mixed $value, int $maximum, string $error): ?string
    {
        if ($value === null || $value === '') return null;
        return $this->text($value, $maximum, $error);
    }

    /** @param list<string> $allowed */
    private function choice(mixed $value, array $allowed, string $error): string
    {
        if (!is_string($value) || !in_array($value, $allowed, true)) throw new DocumentException($error);
        return $value;
    }

    /** @param array<mixed> $value */
    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
