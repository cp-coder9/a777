<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Proposal;

use Architex\Platform\Identity\Identifier;
use Architex\Platform\Proposal\ProposalException;
use Architex\Platform\Proposal\ProposalService;
use Architex\Tests\Support\MariaDbTestCase;

final class ProposalServiceTest extends MariaDbTestCase
{
    public function testIssuedProposalRequiresProfessionalAndLegalReviewThenRecordsRecipientAcceptance(): void
    {
        [$organisationId, $ownerId, $projectId, $feeRunId] = $this->seedIssuedFeeRun();
        $service = new ProposalService($this->pdo);
        $draft = $service->createDraft($organisationId, $ownerId, 'owner', [
            'projectId' => $projectId,
            'feeRunId' => $feeRunId,
            'clientName' => 'Fee Client',
            'clientEmail' => 'client@example.test',
            'validityDays' => 30,
            'assumptions' => ['Existing survey information is accurate.'],
            'exclusions' => ['Municipal application fees.'],
            'notes' => ['Programme subject to client decisions.'],
            'selectedTemplateKeys' => ['standard_sa_professional', 'architectural_services'],
            'customTerms' => ['Two concept revision rounds are included.'],
        ]);

        self::assertSame('draft', $draft['status']);
        self::assertSame(1, $draft['version']);
        self::assertTrue($draft['legalReviewRequired']);
        self::assertSame(3, count($draft['terms']));
        self::assertSame(57_900, $draft['feeSnapshot']['output']['totalInclVat']);

        try {
            $service->issue($organisationId, $ownerId, 'owner', $draft['id'], [
                'professionalConfirmed' => true,
                'legalReviewConfirmed' => false,
                'issueReason' => 'Scope and fee reviewed.',
            ]);
            self::fail('Legal review confirmation must fail closed.');
        } catch (ProposalException $exception) {
            self::assertSame('proposal_legal_review_required', $exception->reason);
        }

        $issued = $service->issue($organisationId, $ownerId, 'owner', $draft['id'], [
            'professionalConfirmed' => true,
            'legalReviewConfirmed' => true,
            'issueReason' => 'Scope, fee, tax, exclusions and draft legal terms reviewed.',
        ]);
        self::assertSame('issued', $issued['proposal']['status']);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $issued['proposal']['auditHash']);
        self::assertMatchesRegularExpression('/\A[A-Za-z0-9_-]{43}\z/', $issued['acceptanceToken']);

        $accepted = $service->accept(
            $issued['acceptanceToken'],
            'Fee Client',
            true,
            '127.0.0.1',
            'PHPUnit',
        );
        self::assertSame('accepted', $accepted['status']);
        self::assertSame('Fee Client', $accepted['acceptedBy']);
        self::assertSame($issued['proposal']['auditHash'], $accepted['auditHash']);

        $reloaded = $service->get($organisationId, $ownerId, $draft['id']);
        self::assertSame('accepted', $reloaded['status']);
        self::assertSame('Fee Client', $reloaded['acceptance']['acceptedBy']);

        $revision = $service->revise($organisationId, $ownerId, 'owner', $draft['id'], [
            'feeRunId' => $feeRunId,
            'assumptions' => ['Updated survey information is accurate.'],
            'exclusions' => ['Municipal fees and specialist investigations.'],
            'notes' => ['Revised after client scope discussion.'],
            'selectedTemplateKeys' => ['standard_sa_professional', 'architectural_services'],
            'customTerms' => ['Three concept revision rounds are included.'],
        ]);
        self::assertSame('draft', $revision['status']);
        self::assertSame(2, $revision['version']);
        self::assertSame($draft['id'], $revision['supersedesProposalId']);
        self::assertSame('accepted', $service->get($organisationId, $ownerId, $draft['id'])['status']);

        $issuedRevision = $service->issue($organisationId, $ownerId, 'owner', $revision['id'], [
            'professionalConfirmed' => true,
            'legalReviewConfirmed' => true,
            'issueReason' => 'Revised scope and terms reviewed.',
        ]);
        self::assertSame('issued', $issuedRevision['proposal']['status']);
        self::assertSame('superseded', $service->get($organisationId, $ownerId, $draft['id'])['status']);
    }

    /** @return array{string,string,string,string} */
    private function seedIssuedFeeRun(): array
    {
        $organisationId = Identifier::generate();
        $ownerId = Identifier::generate();
        $projectId = Identifier::generate();
        $feeRunId = Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'proposal-owner@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id' => $ownerId]);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Proposal Practice','proposal-practice','active')")->execute(['id' => $organisationId]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'owner','active')")->execute(['id' => Identifier::generate(), 'organisation_id' => $organisationId, 'user_id' => $ownerId]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,created_by) VALUES(UNHEX(:id),UNHEX(:organisation_id),'PROP-1','Proposal Project','Fee Client','Tshwane','Erf 13','Residential','','architect',UNHEX(:user_id))")->execute(['id' => $projectId, 'organisation_id' => $organisationId, 'user_id' => $ownerId]);
        $this->pdo->prepare("INSERT INTO tool_runs(id,organisation_id,user_id,tool_id,tool_version,executed_role,assignment_mode,project_id,status,input_payload,output_payload,audit_hash,audit_reason,issued_at) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'professional_fee_calculator','1.0.0','owner','internal_project',UNHEX(:project_id),'issued',:input,:output,:hash,'Reviewed fee',UTC_TIMESTAMP(6))")->execute([
            'id' => $feeRunId,
            'organisation_id' => $organisationId,
            'user_id' => $ownerId,
            'project_id' => $projectId,
            'input' => json_encode(['definitionId' => Identifier::generate(), 'projectValue' => 1_000_000], JSON_THROW_ON_ERROR),
            'output' => json_encode(['guidelineProfessionalFee' => 50_000, 'professionalFeeAfterDiscount' => 45_000, 'disbursementsTotal' => 1_000, 'statutoryFeesTotal' => 5_000, 'vat' => 6_900, 'totalInclVat' => 57_900], JSON_THROW_ON_ERROR),
            'hash' => hash('sha256', 'fee-run-snapshot'),
        ]);
        return [$organisationId, $ownerId, $projectId, $feeRunId];
    }
}
