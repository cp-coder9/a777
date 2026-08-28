<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Finance;

use Architex\Platform\Appointment\AppointmentService;
use Architex\Platform\Finance\CommercialControlException;
use Architex\Platform\Finance\CommercialControlService;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Proposal\ProposalService;
use Architex\Tests\Support\MariaDbTestCase;

final class CommercialControlServiceTest extends MariaDbTestCase
{
    public function testReleaseRequestsNeverPretendToMoveMoneyWithoutProviderConfiguration(): void
    {
        [$organisationId, $ownerId, $projectId, $appointmentId, $qsId, $leadId] = $this->formallyIssuedAppointment();
        $service = new CommercialControlService($this->pdo);
        $baseline = $service->createBaseline($organisationId, $ownerId, 'owner', $projectId, [
            'appointmentId' => $appointmentId, 'contractSumCents' => 50_000_000, 'currency' => 'ZAR', 'vatIncluded' => true,
            'contingencyCents' => 0, 'retentionBasisPoints' => 500, 'humanConfirmed' => true, 'reason' => 'Reviewed source.',
        ]);
        $schedule = $service->createSchedule($organisationId, $ownerId, 'owner', $baseline['id'], [
            'title' => 'Release schedule', 'humanConfirmed' => true, 'reason' => 'Reviewed schedule.',
            'milestones' => [['label' => 'Works', 'amountCents' => 50_000_000, 'dueDate' => '2026-11-01']],
        ]);
        $provider = $service->registerProvider($organisationId, $ownerId, 'owner', [
            'providerKey' => 'registered-provider-pending-contract', 'name' => 'Registered provider candidate',
            'providerType' => 'escrow_provider', 'registered' => true,
            'capabilities' => ['escrow_hold', 'release', 'payout', 'webhook_status'],
            'configurationReference' => null,
        ]);
        self::assertFalse($provider['liveConfigured']);
        self::assertArrayNotHasKey('secret', $provider);
        self::assertArrayNotHasKey('webhookSecret', $provider);

        $claim = $service->submitClaim($organisationId, $ownerId, 'owner', $baseline['id'], [
            'claimNumber' => 'REL-001', 'claimedToDateCents' => 10_000_000, 'milestoneId' => $schedule['milestones'][0]['id'],
            'evidenceReferences' => ['record:release-evidence-001'], 'disputed' => false, 'humanConfirmed' => true, 'reason' => 'Reviewed claim.',
        ]);
        $certificate = $service->certifyClaim($organisationId, $qsId, 'member', $claim['id'], [
            'grossCertifiedToDateCents' => 9_000_000, 'otherDeductionsCents' => 0, 'humanConfirmed' => true, 'reason' => 'QS certified.',
        ]);
        $release = $service->createReleaseRequest($organisationId, $ownerId, 'owner', $certificate['revisionId'], [
            'providerId' => $provider['id'], 'humanConfirmed' => true, 'reason' => 'Prepare provider-mediated request only.',
        ]);
        self::assertSame(8_550_000, $release['requestedAmountCents']);
        self::assertSame('provider_configuration_required', $release['status']);
        self::assertSame(0, $release['providerPaidCents']);
        self::assertFalse($release['fundsHeldByArchitex']);

        $service->approveReleaseRequest($organisationId, $leadId, 'member', $release['id'], [
            'approvalType' => 'lead_professional', 'decision' => 'approved', 'humanConfirmed' => true, 'reason' => 'Professional approved exact certificate.',
        ]);
        $approved = $service->approveReleaseRequest($organisationId, $ownerId, 'owner', $release['id'], [
            'approvalType' => 'client', 'decision' => 'approved', 'humanConfirmed' => true, 'reason' => 'Client approved exact release amount.',
        ]);
        self::assertSame('provider_configuration_required', $approved['status']);
        self::assertCount(2, $approved['approvals']);
        self::assertNull($approved['providerReference']);
        $workspace = $service->workspace($organisationId, $ownerId, $projectId);
        self::assertSame($baseline['id'], $workspace['baseline']['id']);
        self::assertCount(1, $workspace['providers']);
        self::assertCount(1, $workspace['releaseRequests']);
        self::assertFalse($workspace['providerBoundary']['fundsHeldByArchitex']);
        self::assertFalse($workspace['providerBoundary']['livePaymentClaimed']);

        $disputedClaim = $service->submitClaim($organisationId, $ownerId, 'owner', $baseline['id'], [
            'claimNumber' => 'REL-002', 'claimedToDateCents' => 12_000_000, 'milestoneId' => $schedule['milestones'][0]['id'],
            'evidenceReferences' => ['record:release-evidence-002'], 'disputed' => true, 'humanConfirmed' => true, 'reason' => 'Disputed claim recorded.',
        ]);
        $disputedCertificate = $service->certifyClaim($organisationId, $qsId, 'member', $disputedClaim['id'], [
            'grossCertifiedToDateCents' => 10_000_000, 'otherDeductionsCents' => 0, 'humanConfirmed' => true, 'reason' => 'Measured amount recorded without clearing dispute.',
        ]);
        $locked = $service->createReleaseRequest($organisationId, $ownerId, 'owner', $disputedCertificate['revisionId'], [
            'providerId' => $provider['id'], 'humanConfirmed' => true, 'reason' => 'Record blocked request for audit.',
        ]);
        self::assertSame('disputed_locked', $locked['status']);
    }

    public function testClaimsCertificatesRetentionPreviousPaymentsAndDisputesStaySeparate(): void
    {
        [$organisationId, $ownerId, $projectId, $appointmentId, $qsId] = $this->formallyIssuedAppointment();
        $service = new CommercialControlService($this->pdo);
        $baseline = $service->createBaseline($organisationId, $ownerId, 'owner', $projectId, [
            'appointmentId' => $appointmentId, 'contractSumCents' => 100_000_000, 'currency' => 'ZAR',
            'vatIncluded' => true, 'contingencyCents' => 0, 'retentionBasisPoints' => 1000,
            'humanConfirmed' => true, 'reason' => 'Reviewed signed source and amount.',
        ]);
        $schedule = $service->createSchedule($organisationId, $ownerId, 'owner', $baseline['id'], [
            'title' => 'Progress schedule', 'humanConfirmed' => true, 'reason' => 'Reviewed milestones.',
            'milestones' => [
                ['label' => 'Structure', 'amountCents' => 40_000_000, 'dueDate' => '2026-10-01'],
                ['label' => 'Completion', 'amountCents' => 60_000_000, 'dueDate' => '2027-03-01'],
            ],
        ]);
        $claimOne = $service->submitClaim($organisationId, $ownerId, 'owner', $baseline['id'], [
            'claimNumber' => 'PC-001', 'claimedToDateCents' => 20_000_000, 'milestoneId' => $schedule['milestones'][0]['id'],
            'evidenceReferences' => ['record:site-progress-001', 'document:valuation-001'], 'disputed' => false,
            'humanConfirmed' => true, 'reason' => 'Claimant checked cumulative amount and evidence.',
        ]);
        $certificateOne = $service->certifyClaim($organisationId, $qsId, 'member', $claimOne['id'], [
            'grossCertifiedToDateCents' => 19_000_000, 'otherDeductionsCents' => 0,
            'humanConfirmed' => true, 'reason' => 'QS measured the evidenced work.',
        ]);
        self::assertSame(20_000_000, $certificateOne['claimedToDateCents']);
        self::assertSame(19_000_000, $certificateOne['grossCertifiedToDateCents']);
        self::assertSame(1_900_000, $certificateOne['retentionHeldToDateCents']);
        self::assertSame(0, $certificateOne['previousCertifiedPaymentsCents']);
        self::assertSame(17_100_000, $certificateOne['netPayableCents']);
        self::assertSame('approval_required', $certificateOne['status']);

        $claimTwo = $service->submitClaim($organisationId, $ownerId, 'owner', $baseline['id'], [
            'claimNumber' => 'PC-002', 'claimedToDateCents' => 30_000_000, 'milestoneId' => $schedule['milestones'][0]['id'],
            'evidenceReferences' => ['record:site-progress-002'], 'disputed' => false,
            'humanConfirmed' => true, 'reason' => 'Second cumulative claim checked.',
        ]);
        $certificateTwo = $service->certifyClaim($organisationId, $qsId, 'member', $claimTwo['id'], [
            'grossCertifiedToDateCents' => 28_000_000, 'otherDeductionsCents' => 0,
            'humanConfirmed' => true, 'reason' => 'QS measured cumulative work and prior certificate.',
        ]);
        self::assertSame(2_800_000, $certificateTwo['retentionHeldToDateCents']);
        self::assertSame(17_100_000, $certificateTwo['previousCertifiedPaymentsCents']);
        self::assertSame(8_100_000, $certificateTwo['netPayableCents']);
        self::assertSame(2_800_000, $service->retentionBalance($organisationId, $ownerId, $baseline['id'])['heldToDateCents']);

        $disputed = $service->submitClaim($organisationId, $ownerId, 'owner', $baseline['id'], [
            'claimNumber' => 'PC-003', 'claimedToDateCents' => 35_000_000, 'milestoneId' => $schedule['milestones'][1]['id'],
            'evidenceReferences' => ['record:site-progress-003'], 'disputed' => true,
            'humanConfirmed' => true, 'reason' => 'Cumulative claim recorded with an unresolved scope dispute.',
        ]);
        $locked = $service->certifyClaim($organisationId, $qsId, 'member', $disputed['id'], [
            'grossCertifiedToDateCents' => 30_000_000, 'otherDeductionsCents' => 0,
            'humanConfirmed' => true, 'reason' => 'Undisputed measured work recorded; release remains locked.',
        ]);
        self::assertSame(5_000_000, $locked['disputedAmountCents']);
        self::assertSame('disputed_locked', $locked['status']);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $locked['snapshotSha256']);
    }

    public function testBaselineScheduleAndApprovedVariationRemainGovernedAndTenantScoped(): void
    {
        [$organisationId, $ownerId, $projectId, $appointmentId, $qsId, $leadId] = $this->formallyIssuedAppointment();
        $service = new CommercialControlService($this->pdo);

        $baseline = $service->createBaseline($organisationId, $ownerId, 'owner', $projectId, [
            'appointmentId' => $appointmentId,
            'contractSumCents' => 125_000_000,
            'currency' => 'ZAR',
            'vatIncluded' => true,
            'contingencyCents' => 5_000_000,
            'retentionBasisPoints' => 1000,
            'humanConfirmed' => true,
            'reason' => 'Client reviewed the signed source and commercial amount.',
        ]);
        self::assertSame(125_000_000, $baseline['originalContractSumCents']);
        self::assertSame(125_000_000, $baseline['currentContractSumCents']);
        self::assertSame('ZAR', $baseline['currency']);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $baseline['snapshotSha256']);

        $schedule = $service->createSchedule($organisationId, $ownerId, 'owner', $baseline['id'], [
            'title' => 'Construction payment schedule',
            'humanConfirmed' => true,
            'reason' => 'QS and client reviewed milestone values.',
            'milestones' => [
                ['label' => 'Mobilisation', 'amountCents' => 25_000_000, 'dueDate' => '2026-10-01'],
                ['label' => 'Structure complete', 'amountCents' => 100_000_000, 'dueDate' => '2027-02-28'],
            ],
        ]);
        self::assertSame(125_000_000, $schedule['scheduledTotalCents']);
        self::assertCount(2, $schedule['milestones']);

        $variation = $service->createVariation($organisationId, $ownerId, 'owner', $baseline['id'], [
            'reference' => 'VO-001', 'title' => 'Revised retaining wall',
            'description' => 'Additional reinforced retaining wall scope.',
            'amountCents' => 7_500_000, 'timeImpactDays' => 14,
        ]);
        $variation = $service->submitVariation($organisationId, $ownerId, 'owner', $variation['id'], $variation['version'], true, 'Exact scope and amount submitted for review.');
        self::assertSame('under_review', $variation['status']);
        self::assertSame(125_000_000, $service->getBaseline($organisationId, $ownerId, $baseline['id'])['currentContractSumCents']);

        $service->decideVariation($organisationId, $qsId, 'member', $variation['id'], [
            'approvalType' => 'quantity_surveyor', 'decision' => 'approved',
            'reason' => 'QS checked measurement and price.', 'humanConfirmed' => true,
        ]);
        $service->decideVariation($organisationId, $leadId, 'member', $variation['id'], [
            'approvalType' => 'lead_professional', 'decision' => 'approved',
            'reason' => 'Lead professional checked scope and design responsibility.', 'humanConfirmed' => true,
        ]);
        self::assertSame(125_000_000, $service->getBaseline($organisationId, $ownerId, $baseline['id'])['currentContractSumCents']);
        $approved = $service->decideVariation($organisationId, $ownerId, 'owner', $variation['id'], [
            'approvalType' => 'client', 'decision' => 'approved',
            'reason' => 'Client authorised the exact cost and time impact.', 'humanConfirmed' => true,
        ]);
        self::assertSame('approved', $approved['status']);
        self::assertSame(132_500_000, $service->getBaseline($organisationId, $ownerId, $baseline['id'])['currentContractSumCents']);
        self::assertSame(2, $service->getBaseline($organisationId, $ownerId, $baseline['id'])['revision']);

        [$otherOrganisation, $otherUser] = $this->tenant('Other practice', 'other-finance');
        $this->expectException(CommercialControlException::class);
        $service->getBaseline($otherOrganisation, $otherUser, $baseline['id']);
    }

    /** @return array{string,string,string,string,string,string} */
    private function formallyIssuedAppointment(): array
    {
        [$organisationId, $ownerId, $projectId] = $this->tenant('Finance practice', 'finance');
        $feeRunId = Identifier::generate();
        $this->pdo->prepare("INSERT INTO tool_runs(id,organisation_id,user_id,tool_id,tool_version,executed_role,assignment_mode,project_id,status,input_payload,output_payload,audit_hash,audit_reason,issued_at) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'professional_fee_calculator','1.0.0','owner','internal_project',UNHEX(:p),'issued','{}','{\"totalInclVat\":57900}',:hash,'Reviewed fee',UTC_TIMESTAMP(6))")->execute(['id' => $feeRunId, 'o' => $organisationId, 'u' => $ownerId, 'p' => $projectId, 'hash' => hash('sha256', 'finance-fee')]);
        $proposals = new ProposalService($this->pdo);
        $draft = $proposals->createDraft($organisationId, $ownerId, 'owner', [
            'projectId' => $projectId, 'feeRunId' => $feeRunId, 'clientName' => 'Finance Client', 'clientEmail' => 'finance-client@example.test',
            'validityDays' => 30, 'assumptions' => ['Existing information is accurate.'], 'exclusions' => ['Statutory fees.'], 'notes' => [],
            'selectedTemplateKeys' => ['standard_sa_professional', 'architectural_services'], 'customTerms' => [],
        ]);
        $issued = $proposals->issue($organisationId, $ownerId, 'owner', $draft['id'], ['professionalConfirmed' => true, 'legalReviewConfirmed' => true, 'issueReason' => 'Reviewed exact proposal.']);
        $proposals->accept($issued['acceptanceToken'], 'Finance Client', true, '127.0.0.1', 'PHPUnit');
        $appointments = new AppointmentService($this->pdo);
        $appointment = $appointments->createFromAcceptedProposal($organisationId, $ownerId, 'owner', $draft['id'], [
            'municipality' => 'City of Johannesburg', 'province' => 'Gauteng', 'propertyDescription' => 'Erf 20', 'erfNumber' => '20',
            'landUseOrZoningKnown' => true, 'professionalBody' => 'SACAP', 'professionalRegistrationNumber' => 'PrArch FIN-1',
        ]);
        $appointments->confirmProfessional($organisationId, $ownerId, 'owner', $appointment['id'], ['responsibilityConfirmed' => true, 'confirmationReason' => 'Exact responsibility accepted.']);
        $appointments->issueFormalAppointment($organisationId, $ownerId, 'owner', $appointment['id'], ['humanApprovalConfirmed' => true, 'issueReason' => 'Signed source formally issued.']);
        $qsId = $this->projectProfessional($organisationId, $projectId, $ownerId, 'finance-qs@example.test', 'quantity_surveyor');
        $leadId = $this->projectProfessional($organisationId, $projectId, $ownerId, 'finance-lead@example.test', 'architect');
        return [$organisationId, $ownerId, $projectId, $appointment['id'], $qsId, $leadId];
    }

    private function projectProfessional(string $organisationId, string $projectId, string $ownerId, string $email, string $discipline): string
    {
        $userId = Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id' => $userId, 'email' => $email]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'member','active')")->execute(['id' => Identifier::generate(), 'o' => $organisationId, 'u' => $userId]);
        $this->pdo->prepare("INSERT INTO project_team_members(id,organisation_id,project_id,user_id,discipline,role_label,verification_grade,status,invited_by,accepted_at) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:p),UNHEX(:u),:discipline,:label,'manually_reviewed','active',UNHEX(:owner),UTC_TIMESTAMP(6))")->execute(['id' => Identifier::generate(), 'o' => $organisationId, 'p' => $projectId, 'u' => $userId, 'discipline' => $discipline, 'label' => $discipline, 'owner' => $ownerId]);
        return $userId;
    }

    /** @return array{string,string,string} */
    private function tenant(string $name, string $prefix): array
    {
        $organisation = Identifier::generate(); $user = Identifier::generate(); $project = Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id' => $user, 'email' => $prefix.'@example.test']);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id' => $organisation, 'name' => $name, 'slug' => $prefix.'-'.substr($organisation, 0, 8)]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'owner','active')")->execute(['id' => Identifier::generate(), 'o' => $organisation, 'u' => $user]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:p),UNHEX(:o),:code,:name,'Finance Client','Johannesburg','Erf 20','Residential','','architect','appointment',UNHEX(:u))")->execute(['p' => $project, 'o' => $organisation, 'u' => $user, 'code' => strtoupper(substr($prefix, 0, 8)), 'name' => $name.' Project']);
        return [$organisation, $user, $project];
    }
}
