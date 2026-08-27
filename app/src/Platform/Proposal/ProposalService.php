<?php

declare(strict_types=1);

namespace Architex\Platform\Proposal;

use Architex\Platform\Audit\AuditService;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Project\ProjectContext;
use Architex\Platform\Project\ProjectPhase;
use Architex\Platform\Records\ProjectRecordService;
use DateTimeImmutable;
use PDO;

final readonly class ProposalService
{
    private const TEMPLATES = [
        'standard_sa_professional' => [
            'name' => 'Standard South African professional services terms',
            'profession' => 'all',
            'version' => '0.2.0',
            'clauses' => [
                'The professional services are limited to the scope, stages and deliverables recorded in this proposal.',
                'The client remains responsible for accurate instructions, site access, ownership information and timeous decisions.',
                'Fees exclude statutory or municipal charges, specialist investigations and reimbursable expenses unless expressly included.',
                'This draft template is not legal advice and requires professional and legal review before issue.',
            ],
        ],
        'architectural_services' => [
            'name' => 'Architectural services terms',
            'profession' => 'architect',
            'version' => '0.2.0',
            'clauses' => [
                'Architectural services are limited to the agreed stages and selected deliverables.',
                'Municipal approval is not guaranteed; submissions and coordination are performed only as scoped.',
                'Copyright and permitted use of drawings remain subject to the final appointment terms and applicable law.',
            ],
        ],
    ];

    public function __construct(private PDO $pdo) {}

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createDraft(string $organisationId, string $userId, string $role, array $input): array
    {
        $this->assertRole($organisationId, $userId, $role);
        $projectId = $this->id($input['projectId'] ?? null, 'proposal_project_required');
        $feeRunId = $this->id($input['feeRunId'] ?? null, 'proposal_fee_run_required');
        $clientName = $this->text($input['clientName'] ?? null, 180, 'proposal_client_required');
        $clientEmail = filter_var($input['clientEmail'] ?? null, FILTER_VALIDATE_EMAIL);
        if (!is_string($clientEmail)) throw new ProposalException('proposal_client_email_invalid');
        $validityDays = (int)($input['validityDays'] ?? 0);
        if ($validityDays < 1 || $validityDays > 365) throw new ProposalException('proposal_validity_invalid');
        $project = $this->project($organisationId, $projectId);
        $fee = $this->feeRun($organisationId, $projectId, $feeRunId);
        $terms = $this->terms($organisationId, $userId, $project['profession'], $input);
        $id = Identifier::generate();
        $document = [
            'title' => 'Professional Fee Proposal - ' . $project['name'],
            'project' => $project,
            'professional' => ['organisationId' => $organisationId, 'responsibleUserId' => $userId, 'role' => $role],
            'feeSnapshot' => $fee,
            'assumptions' => $this->lines($input['assumptions'] ?? []),
            'exclusions' => $this->lines($input['exclusions'] ?? []),
            'notes' => $this->lines($input['notes'] ?? []),
            'terms' => $terms,
            'validityDays' => $validityDays,
            'acceptance' => [
                'clientConfirmation' => 'I reviewed this exact proposal version and accept its stated scope, fee basis, exclusions and terms.',
                'professionalConfirmationRequiredBeforeIssue' => true,
                'externalSignatureMayBeRequired' => true,
            ],
            'legalStatus' => 'Draft terms for legal review; not legal advice.',
        ];
        $statement = $this->pdo->prepare('INSERT INTO professional_proposals(id,organisation_id,project_id,fee_run_id,created_by,title,client_name,client_email,validity_days,legal_review_required,document_snapshot) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:project_id),UNHEX(:fee_run_id),UNHEX(:created_by),:title,:client_name,:client_email,:validity_days,1,:snapshot)');
        $statement->execute([
            'id' => $id, 'organisation_id' => $organisationId, 'project_id' => $projectId,
            'fee_run_id' => $feeRunId, 'created_by' => $userId, 'title' => $document['title'],
            'client_name' => $clientName, 'client_email' => $clientEmail, 'validity_days' => $validityDays,
            'snapshot' => $this->json($document),
        ]);
        (new AuditService($this->pdo))->append($organisationId, $userId, 'proposal.draft_created', 'professional_proposal', $id, ['projectId' => $projectId, 'feeRunId' => $feeRunId, 'version' => 1]);
        return $this->get($organisationId, $userId, $id);
    }

    /** @param array<string,mixed> $confirmation @return array{proposal:array<string,mixed>,acceptanceToken:string} */
    public function issue(string $organisationId, string $userId, string $role, string $proposalId, array $confirmation): array
    {
        $this->assertRole($organisationId, $userId, $role);
        if (($confirmation['professionalConfirmed'] ?? false) !== true) throw new ProposalException('proposal_professional_confirmation_required');
        if (($confirmation['legalReviewConfirmed'] ?? false) !== true) throw new ProposalException('proposal_legal_review_required');
        $reason = $this->text($confirmation['issueReason'] ?? null, 500, 'proposal_issue_reason_required');
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare("SELECT LOWER(HEX(id)) id,LOWER(HEX(project_id)) project_id,LOWER(HEX(supersedes_proposal_id)) supersedes_proposal_id,status,client_email,validity_days,document_snapshot FROM professional_proposals WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation_id) FOR UPDATE");
            $statement->execute(['id' => $proposalId, 'organisation_id' => $organisationId]);
            $row = $statement->fetch();
            if (!is_array($row)) throw new ProposalException('proposal_not_found');
            if ($row['status'] !== 'draft') throw new ProposalException('proposal_not_draft');
            $issuedAt = new DateTimeImmutable();
            $expiresAt = $issuedAt->modify('+' . (int)$row['validity_days'] . ' days');
            $document = json_decode((string)$row['document_snapshot'], true, 64, JSON_THROW_ON_ERROR);
            $document['issue'] = ['issuedAt' => $issuedAt->format('Y-m-d\TH:i:s.u\Z'), 'issuedBy' => $userId, 'reason' => $reason, 'professionalConfirmed' => true, 'legalReviewConfirmed' => true];
            $snapshot = $this->json($document);
            $hash = hash('sha256', $snapshot);
            $update = $this->pdo->prepare("UPDATE professional_proposals SET status='issued',document_snapshot=:snapshot,audit_hash=:hash,issue_reason=:reason,professional_confirmed_at=UTC_TIMESTAMP(6),legal_review_confirmed_at=UTC_TIMESTAMP(6),issued_at=UTC_TIMESTAMP(6),expires_at=:expires_at WHERE id=UNHEX(:id)");
            $update->execute(['snapshot' => $snapshot, 'hash' => $hash, 'reason' => $reason, 'expires_at' => $expiresAt->format('Y-m-d H:i:s.u'), 'id' => $proposalId]);
            $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $requestId = Identifier::generate();
            $invite = $this->pdo->prepare('INSERT INTO proposal_acceptance_requests(id,organisation_id,proposal_id,recipient_email,token_hash,expires_at) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:proposal_id),:email,:token_hash,:expires_at)');
            $invite->execute(['id' => $requestId, 'organisation_id' => $organisationId, 'proposal_id' => $proposalId, 'email' => $row['client_email'], 'token_hash' => hash('sha256', $token), 'expires_at' => $expiresAt->format('Y-m-d H:i:s.u')]);
            if (is_string($row['supersedes_proposal_id'])) {
                $this->pdo->prepare("UPDATE professional_proposals SET status='superseded' WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation_id) AND status IN ('issued','accepted')")->execute(['id' => $row['supersedes_proposal_id'], 'organisation_id' => $organisationId]);
                $this->pdo->prepare("UPDATE proposal_acceptance_requests SET status='revoked' WHERE proposal_id=UNHEX(:id) AND status='pending'")->execute(['id' => $row['supersedes_proposal_id']]);
            }
            (new AuditService($this->pdo))->append($organisationId, $userId, 'proposal.issued', 'professional_proposal', $proposalId, ['auditHash' => $hash, 'reason' => $reason, 'acceptanceRequestId' => $requestId]);
            (new ProjectRecordService($this->pdo))->issue(
                new ProjectContext($organisationId, $userId, (string)$row['project_id'], $role),
                ProjectPhase::Onboarding,
                'professional_proposal',
                'fee_proposal',
                (string)$document['title'],
                'issued',
                ['proposalId' => $proposalId, 'auditHash' => $hash, 'document' => $document],
                ['required' => true, 'approvedBy' => $userId],
            );
            $this->pdo->commit();
            return ['proposal' => $this->get($organisationId, $userId, $proposalId), 'acceptanceToken' => $token];
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    /** @param array<string,mixed> $changes @return array<string,mixed> */
    public function revise(string $organisationId, string $userId, string $role, string $proposalId, array $changes): array
    {
        $this->assertRole($organisationId, $userId, $role);
        $statement = $this->pdo->prepare("SELECT LOWER(HEX(project_id)) project_id,client_name,client_email,validity_days,version,status,document_snapshot FROM professional_proposals WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation_id)");
        $statement->execute(['id' => $proposalId, 'organisation_id' => $organisationId]);
        $previous = $statement->fetch();
        if (!is_array($previous)) throw new ProposalException('proposal_not_found');
        if (!in_array($previous['status'], ['issued', 'accepted'], true)) throw new ProposalException('proposal_revision_source_invalid');
        $feeRunId = $this->id($changes['feeRunId'] ?? null, 'proposal_fee_run_required');
        $project = $this->project($organisationId, (string)$previous['project_id']);
        $document = json_decode((string)$previous['document_snapshot'], true, 64, JSON_THROW_ON_ERROR);
        unset($document['issue']);
        $document['feeSnapshot'] = $this->feeRun($organisationId, (string)$previous['project_id'], $feeRunId);
        $document['assumptions'] = $this->lines($changes['assumptions'] ?? []);
        $document['exclusions'] = $this->lines($changes['exclusions'] ?? []);
        $document['notes'] = $this->lines($changes['notes'] ?? []);
        $document['terms'] = $this->terms($organisationId, $userId, $project['profession'], $changes);
        $document['legalStatus'] = 'Draft terms for legal review; not legal advice.';
        $id = Identifier::generate();
        $insert = $this->pdo->prepare('INSERT INTO professional_proposals(id,organisation_id,project_id,fee_run_id,created_by,title,client_name,client_email,version,status,validity_days,legal_review_required,document_snapshot,supersedes_proposal_id) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:project_id),UNHEX(:fee_run_id),UNHEX(:created_by),:title,:client_name,:client_email,:version,\'draft\',:validity_days,1,:snapshot,UNHEX(:supersedes_id))');
        $insert->execute([
            'id' => $id, 'organisation_id' => $organisationId, 'project_id' => $previous['project_id'],
            'fee_run_id' => $feeRunId, 'created_by' => $userId, 'title' => $document['title'],
            'client_name' => $previous['client_name'], 'client_email' => $previous['client_email'],
            'version' => (int)$previous['version'] + 1, 'validity_days' => $previous['validity_days'],
            'snapshot' => $this->json($document), 'supersedes_id' => $proposalId,
        ]);
        (new AuditService($this->pdo))->append($organisationId, $userId, 'proposal.revision_created', 'professional_proposal', $id, ['supersedesProposalId' => $proposalId, 'version' => (int)$previous['version'] + 1]);
        return $this->get($organisationId, $userId, $id);
    }

    /** @return array<string,mixed> */
    public function accept(string $token, string $acceptedBy, bool $responsibilityConfirmed, string $ipAddress, string $userAgent): array
    {
        if (preg_match('/\A[A-Za-z0-9_-]{43}\z/', $token) !== 1) throw new ProposalException('proposal_acceptance_invalid');
        $acceptedBy = $this->text($acceptedBy, 180, 'proposal_acceptance_name_required');
        if (!$responsibilityConfirmed) throw new ProposalException('proposal_acceptance_confirmation_required');
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare("SELECT LOWER(HEX(r.id)) request_id,LOWER(HEX(r.organisation_id)) organisation_id,LOWER(HEX(r.proposal_id)) proposal_id,p.status,p.audit_hash,r.expires_at FROM proposal_acceptance_requests r JOIN professional_proposals p ON p.id=r.proposal_id AND p.organisation_id=r.organisation_id WHERE r.token_hash=:token_hash AND r.status='pending' FOR UPDATE");
            $statement->execute(['token_hash' => hash('sha256', $token)]);
            $row = $statement->fetch();
            if (!is_array($row)) throw new ProposalException('proposal_acceptance_invalid');
            if ($row['status'] !== 'issued') throw new ProposalException('proposal_not_issuable');
            if (strtotime((string)$row['expires_at']) <= time()) throw new ProposalException('proposal_acceptance_expired');
            $update = $this->pdo->prepare("UPDATE proposal_acceptance_requests SET status='accepted',accepted_by=:accepted_by,responsibility_confirmed=1,accepted_ip=:ip,accepted_user_agent=:user_agent,accepted_at=UTC_TIMESTAMP(6) WHERE id=UNHEX(:id)");
            $update->execute(['accepted_by' => $acceptedBy, 'ip' => substr($ipAddress, 0, 64), 'user_agent' => substr($userAgent, 0, 500), 'id' => $row['request_id']]);
            $this->pdo->prepare("UPDATE professional_proposals SET status='accepted',accepted_at=UTC_TIMESTAMP(6) WHERE id=UNHEX(:id)")->execute(['id' => $row['proposal_id']]);
            (new AuditService($this->pdo))->append((string)$row['organisation_id'], null, 'proposal.accepted', 'professional_proposal', (string)$row['proposal_id'], ['acceptedBy' => $acceptedBy, 'auditHash' => $row['audit_hash'], 'ipAddress' => $ipAddress]);
            $this->pdo->commit();
            return ['id' => $row['proposal_id'], 'status' => 'accepted', 'acceptedBy' => $acceptedBy, 'auditHash' => $row['audit_hash']];
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    /** @return array<string,mixed> */
    public function get(string $organisationId, string $userId, string $proposalId): array
    {
        $this->assertMember($organisationId, $userId);
        $statement = $this->pdo->prepare("SELECT LOWER(HEX(p.id)) id,LOWER(HEX(p.project_id)) projectId,LOWER(HEX(p.fee_run_id)) feeRunId,LOWER(HEX(p.supersedes_proposal_id)) supersedesProposalId,p.title,p.client_name clientName,p.client_email clientEmail,p.version,p.status,p.validity_days validityDays,p.legal_review_required legalReviewRequired,p.document_snapshot,p.audit_hash auditHash,p.issued_at issuedAt,p.expires_at expiresAt,p.accepted_at acceptedAt,r.accepted_by acceptedBy,r.accepted_at recipientAcceptedAt FROM professional_proposals p LEFT JOIN proposal_acceptance_requests r ON r.proposal_id=p.id AND r.status='accepted' WHERE p.id=UNHEX(:id) AND p.organisation_id=UNHEX(:organisation_id)");
        $statement->execute(['id' => $proposalId, 'organisation_id' => $organisationId]);
        $row = $statement->fetch();
        if (!is_array($row)) throw new ProposalException('proposal_not_found');
        $document = json_decode((string)$row['document_snapshot'], true, 64, JSON_THROW_ON_ERROR);
        unset($row['document_snapshot']);
        $row['version'] = (int)$row['version'];
        $row['validityDays'] = (int)$row['validityDays'];
        $row['legalReviewRequired'] = (bool)$row['legalReviewRequired'];
        $row['feeSnapshot'] = $document['feeSnapshot'];
        $row['terms'] = $document['terms'];
        $row['document'] = $document;
        $row['acceptance'] = is_string($row['acceptedBy']) ? ['acceptedBy' => $row['acceptedBy'], 'acceptedAt' => $row['recipientAcceptedAt']] : null;
        unset($row['acceptedBy'], $row['recipientAcceptedAt']);
        return $row;
    }

    /** @return list<array<string,mixed>> */
    public function list(string $organisationId, string $userId): array
    {
        $this->assertMember($organisationId, $userId);
        $statement = $this->pdo->prepare("SELECT LOWER(HEX(id)) id,title,client_name clientName,version,status,audit_hash auditHash,issued_at issuedAt,expires_at expiresAt,accepted_at acceptedAt,created_at createdAt FROM professional_proposals WHERE organisation_id=UNHEX(:organisation_id) ORDER BY created_at DESC,id");
        $statement->execute(['organisation_id' => $organisationId]);
        $rows = $statement->fetchAll();
        foreach ($rows as &$row) $row['version'] = (int)$row['version'];
        return $rows;
    }

    /** @return array<string,mixed> */
    public function publicView(string $token): array
    {
        if (preg_match('/\A[A-Za-z0-9_-]{43}\z/', $token) !== 1) throw new ProposalException('proposal_acceptance_invalid');
        $statement = $this->pdo->prepare("SELECT LOWER(HEX(p.id)) id,p.title,p.version,p.status,p.document_snapshot,p.audit_hash auditHash,p.issued_at issuedAt,p.expires_at expiresAt,r.status acceptanceStatus FROM proposal_acceptance_requests r JOIN professional_proposals p ON p.id=r.proposal_id AND p.organisation_id=r.organisation_id WHERE r.token_hash=:token_hash AND r.status IN ('pending','accepted')");
        $statement->execute(['token_hash' => hash('sha256', $token)]);
        $row = $statement->fetch();
        if (!is_array($row)) throw new ProposalException('proposal_acceptance_invalid');
        if (strtotime((string)$row['expiresAt']) <= time() && $row['acceptanceStatus'] === 'pending') throw new ProposalException('proposal_acceptance_expired');
        $document = json_decode((string)$row['document_snapshot'], true, 64, JSON_THROW_ON_ERROR);
        unset($row['document_snapshot'], $row['acceptanceStatus']);
        $row['version'] = (int)$row['version'];
        $row['document'] = $document;
        return $row;
    }

    /** @param array<string,mixed> $input @return list<array<string,mixed>> */
    private function terms(string $organisationId, string $userId, string $profession, array $input): array
    {
        $selected = $input['selectedTemplateKeys'] ?? [];
        if (!is_array($selected) || $selected === []) throw new ProposalException('proposal_terms_required');
        $terms = [];
        foreach ($selected as $key) {
            if (!is_string($key) || !isset(self::TEMPLATES[$key])) throw new ProposalException('proposal_term_unknown');
            $template = self::TEMPLATES[$key];
            if (!in_array($template['profession'], ['all', $profession], true)) throw new ProposalException('proposal_term_profession_mismatch');
            $this->seedTemplate($organisationId, $userId, $key, $template);
            $terms[] = ['key' => $key] + $template + ['legalReviewRequired' => true];
        }
        foreach ($this->lines($input['customTerms'] ?? []) as $line) {
            $terms[] = ['key' => 'custom_' . Identifier::generate(), 'name' => 'Custom project term', 'profession' => $profession, 'version' => 'draft', 'clauses' => [$line], 'legalReviewRequired' => true];
        }
        return $terms;
    }

    /** @param array{name:string,profession:string,version:string,clauses:list<string>} $template */
    private function seedTemplate(string $organisationId, string $userId, string $key, array $template): void
    {
        $statement = $this->pdo->prepare("INSERT IGNORE INTO proposal_term_templates(id,organisation_id,template_key,name,profession,version,clauses,status,created_by) VALUES(UNHEX(:id),UNHEX(:organisation_id),:template_key,:name,:profession,:version,:clauses,'draft',UNHEX(:created_by))");
        $statement->execute(['id' => Identifier::generate(), 'organisation_id' => $organisationId, 'template_key' => $key, 'name' => $template['name'], 'profession' => $template['profession'], 'version' => $template['version'], 'clauses' => $this->json($template['clauses']), 'created_by' => $userId]);
    }

    /** @return array<string,mixed> */
    private function project(string $organisationId, string $projectId): array
    {
        $statement = $this->pdo->prepare("SELECT project_name name,client_name clientName,municipality location,property_reference propertyReference,lead_professional_role profession FROM projects WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation_id)");
        $statement->execute(['id' => $projectId, 'organisation_id' => $organisationId]);
        $row = $statement->fetch();
        if (!is_array($row)) throw new ProposalException('proposal_project_not_found');
        return $row;
    }

    /** @return array<string,mixed> */
    private function feeRun(string $organisationId, string $projectId, string $feeRunId): array
    {
        $statement = $this->pdo->prepare("SELECT tool_version toolVersion,input_payload,output_payload,audit_hash auditHash,issued_at issuedAt FROM tool_runs WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation_id) AND project_id=UNHEX(:project_id) AND tool_id='professional_fee_calculator' AND status='issued'");
        $statement->execute(['id' => $feeRunId, 'organisation_id' => $organisationId, 'project_id' => $projectId]);
        $row = $statement->fetch();
        if (!is_array($row)) throw new ProposalException('proposal_fee_run_not_issued');
        return ['toolVersion' => $row['toolVersion'], 'input' => json_decode((string)$row['input_payload'], true, 64, JSON_THROW_ON_ERROR), 'output' => json_decode((string)$row['output_payload'], true, 64, JSON_THROW_ON_ERROR), 'auditHash' => $row['auditHash'], 'issuedAt' => $row['issuedAt']];
    }

    private function assertRole(string $organisationId, string $userId, string $role): void
    {
        if (!in_array($role, ['owner', 'admin', 'member'], true)) throw new ProposalException('proposal_role_denied');
        $this->assertMember($organisationId, $userId);
    }

    private function assertMember(string $organisationId, string $userId): void
    {
        $statement = $this->pdo->prepare("SELECT 1 FROM organisation_memberships WHERE organisation_id=UNHEX(:organisation_id) AND user_id=UNHEX(:user_id) AND status='active'");
        $statement->execute(['organisation_id' => $organisationId, 'user_id' => $userId]);
        if ($statement->fetchColumn() === false) throw new ProposalException('proposal_membership_required');
    }

    private function id(mixed $value, string $reason): string
    {
        if (!is_string($value) || preg_match('/\A[a-f0-9]{32}\z/i', $value) !== 1) throw new ProposalException($reason);
        return strtolower($value);
    }

    private function text(mixed $value, int $maximum, string $reason): string
    {
        if (!is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > $maximum) throw new ProposalException($reason);
        return trim($value);
    }

    /** @return list<string> */
    private function lines(mixed $value): array
    {
        if (!is_array($value)) throw new ProposalException('proposal_lines_invalid');
        $lines = [];
        foreach ($value as $line) $lines[] = $this->text($line, 1000, 'proposal_line_invalid');
        return $lines;
    }

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
