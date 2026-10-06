<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Finance;

use Architex\Platform\Appointment\AppointmentService;
use Architex\Platform\Finance\CommercialControlException;
use Architex\Platform\Finance\CommercialControlService;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Proposal\ProposalService;
use Architex\Tests\Support\IntegrationBoundaryTestCase;
use Architex\Infrastructure\Integration\BridgeConfig;
use Architex\Infrastructure\Integration\BridgeTransport;
use Architex\Infrastructure\Integration\IntegrationException;

final class CommercialControlServiceTest extends IntegrationBoundaryTestCase
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
        $beforeDispatch=$certificate;
        $undispatched=$service->createReleaseRequest($organisationId,$ownerId,'owner',$beforeDispatch['revisionId'],['providerId'=>$provider['id'],'humanConfirmed'=>true,'reason'=>'Synthetic pre-dispatch request.']);
        $certificate=$service->certifyClaim($organisationId,$qsId,'member',$claim['id'],['grossCertifiedToDateCents'=>9_000_000,'otherDeductionsCents'=>0,'humanConfirmed'=>true,'reason'=>'Synthetic pre-dispatch correction.']);
        self::assertSame($beforeDispatch['id'],$certificate['id']);
        self::assertNotSame($beforeDispatch['revisionId'],$certificate['revisionId']);
        self::assertSame(8_550_000,$certificate['netPayableCents']);
        try { $service->submitReleaseRequest($organisationId,$ownerId,'owner',$undispatched['id'],['humanConfirmed'=>true,'expectedVersion'=>$undispatched['version']]); self::fail('Superseded pre-dispatch release cannot launch.'); }
        catch (CommercialControlException $e) { self::assertSame('provider_release_not_submittable',$e->reason); }
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM provider_release_dispatches')->fetchColumn());
        $release = $service->createReleaseRequest($organisationId, $ownerId, 'owner', $certificate['revisionId'], [
            'providerId' => $provider['id'], 'humanConfirmed' => true, 'reason' => 'Prepare provider-mediated request only.',
        ]);
        self::assertSame(8_550_000, $release['requestedAmountCents']);
        self::assertSame('provider_configuration_required', $release['status']);
        self::assertSame(0, $release['providerPaidCents']);
        self::assertFalse($release['fundsHeldByArchitex']);
        self::assertContains('escrow_release_request', $this->recordTypes($organisationId, $projectId));

        $service->approveReleaseRequest($organisationId, $leadId, 'member', $release['id'], [
            'approvalType' => 'lead_professional', 'decision' => 'approved', 'humanConfirmed' => true, 'reason' => 'Professional approved exact certificate.',
        ]);
        try { $service->submitReleaseRequest($organisationId,$ownerId,'owner',$release['id'],['humanConfirmed'=>true,'expectedVersion'=>2]); self::fail('One approval is insufficient.'); }
        catch (CommercialControlException $e) { self::assertSame('provider_release_approvals_required',$e->reason); }
        $approved = $service->approveReleaseRequest($organisationId, $ownerId, 'owner', $release['id'], [
            'approvalType' => 'client', 'decision' => 'approved', 'humanConfirmed' => true, 'reason' => 'Client approved exact release amount.',
        ]);
        self::assertSame('provider_configuration_required', $approved['status']);
        self::assertCount(2, $approved['approvals']);
        self::assertNull($approved['providerReference']);
        $workspace = $service->workspace($organisationId, $ownerId, $projectId);
        self::assertSame($baseline['id'], $workspace['baseline']['id']);
        self::assertCount(1, $workspace['providers']);
        self::assertCount(2, $workspace['releaseRequests']);
        self::assertFalse($workspace['providerBoundary']['fundsHeldByArchitex']);
        self::assertFalse($workspace['providerBoundary']['livePaymentClaimed']);

        $retentionRelease = $service->createRetentionReleaseRequest($organisationId, $ownerId, 'owner', $baseline['id'], [
            'providerId' => $provider['id'], 'requestedAmountCents' => 450_000,
            'defectsLiabilityCompleted' => true, 'completionCertificateReference' => 'record:practical-completion-001',
            'humanConfirmed' => true, 'reason' => 'Client recorded due retention for separate approvals.',
        ]);
        self::assertSame('provider_configuration_required', $retentionRelease['status']);
        self::assertSame(450_000, $retentionRelease['requestedAmountCents']);
        $service->approveRetentionRelease($organisationId, $leadId, 'member', $retentionRelease['id'], [
            'approvalType' => 'lead_professional', 'decision' => 'approved', 'humanConfirmed' => true, 'reason' => 'Lead professional verified completion evidence.',
        ]);
        $retentionApproved = $service->approveRetentionRelease($organisationId, $ownerId, 'owner', $retentionRelease['id'], [
            'approvalType' => 'client', 'decision' => 'approved', 'humanConfirmed' => true, 'reason' => 'Client approved the exact retention amount.',
        ]);
        self::assertSame('provider_configuration_required', $retentionApproved['status']);
        self::assertCount(2, $retentionApproved['approvals']);
        self::assertContains('retention_release', $this->recordTypes($organisationId, $projectId));
        self::assertContains('finance_retention_release_due', $this->inboxEventTypes($organisationId, $projectId));

        try { $service->submitReleaseRequest($organisationId,$ownerId,'owner',$release['id'],['humanConfirmed'=>true,'expectedVersion'=>$approved['version']]); self::fail('Missing transport configuration must not release.'); }
        catch (IntegrationException $e) { self::assertSame('integration_not_configured',$e->reason); }
        $secret = 'test-provider-webhook-secret-with-32-bytes';
        $transport=new class implements BridgeTransport {
            public int $submissions=0; public int $requests=0; public bool $unknown=true; public array $bodies=[]; public array $keys=[];
            public function request(string $method,string $url,array $headers,string $body): array {
                $this->requests++;
                if ($method==='GET') { parse_str((string)parse_url($url,PHP_URL_QUERY),$scope); return ['status'=>200,'body'=>json_encode($scope+['contract'=>'architex.integration.v1','capabilities'=>['release','webhook_status'],'idempotentRelease'=>true,'signedCallbacks'=>true],JSON_THROW_ON_ERROR)]; }
                $this->submissions++; $this->bodies[]=$body; $this->keys[]=$headers['Idempotency-Key'];
                if ($this->unknown) throw new IntegrationException('integration_transport_unknown',502);
                $payload=json_decode($body,true,flags:JSON_THROW_ON_ERROR);
                return ['status'=>202,'body'=>json_encode($payload+['status'=>'accepted','providerReference'=>'PROV-REL-001'],JSON_THROW_ON_ERROR)];
            }
        };
        $config=BridgeConfig::fromValues(['URL'=>'https://fixture.example','TOKEN'=>str_repeat('test-token-',4),'WEBHOOK_SECRET'=>$secret]);
        $service=new CommercialControlService($this->pdo,$transport,static fn(string $id): BridgeConfig=>$config);
        foreach ([['humanConfirmed'=>false,'expectedVersion'=>$approved['version']],['humanConfirmed'=>true,'expectedVersion'=>1]] as $invalid) {
            try { $service->submitReleaseRequest($organisationId,$ownerId,'owner',$release['id'],$invalid); self::fail('Invalid authorization/version must fail.'); }
            catch (CommercialControlException $e) { self::assertContains($e->reason,['provider_release_confirmation_required','provider_release_version_conflict']); }
        }
        self::assertSame(0,$transport->submissions);
        // Active membership/team cannot authorize an inactive identity.
        $auditBefore = (int)$this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn();
        foreach ([['users', $leadId, 'suspended'], ['users', $ownerId, 'suspended'], ['organisations', $organisationId, 'suspended']] as [$table, $actor, $status]) {
            $this->pdo->prepare("UPDATE $table SET status=:status WHERE id=UNHEX(:id)")->execute(['status'=>$status,'id'=>$actor]);
            try {
                $service->submitReleaseRequest($organisationId,$ownerId,'owner',$release['id'],['humanConfirmed'=>true,'expectedVersion'=>$approved['version']]);
                self::fail('Inactive identity cannot authorize release.');
            } catch (CommercialControlException $e) { self::assertSame('commercial_permission_denied',$e->reason); }
            self::assertSame(0,$transport->submissions);
            self::assertSame(0,$transport->requests);
            self::assertSame([], $transport->bodies);
            self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM provider_release_dispatches')->fetchColumn());
            self::assertSame($auditBefore,(int)$this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
            self::assertSame(2,(int)$this->pdo->query("SELECT COUNT(*) FROM provider_release_approvals WHERE decision='approved'")->fetchColumn());
            $s=$this->pdo->prepare("SELECT status FROM organisation_memberships WHERE organisation_id=UNHEX(:o) AND user_id=UNHEX(:u)");
            $s->execute(['o'=>$organisationId,'u'=>$leadId]); self::assertSame('active',$s->fetchColumn());
            $s=$this->pdo->prepare('SELECT status FROM project_team_members WHERE organisation_id=UNHEX(:o) AND user_id=UNHEX(:u)');
            $s->execute(['o'=>$organisationId,'u'=>$leadId]); self::assertSame('active',$s->fetchColumn());
            $this->pdo->prepare("UPDATE $table SET status='active' WHERE id=UNHEX(:id)")->execute(['id'=>$actor]);
        }
        $earlyBody=json_encode(['organisationId'=>$organisationId,'providerId'=>$provider['id'],'eventId'=>'evt-paid-001','eventType'=>'payment_paid','providerReference'=>'PROV-REL-001','amountCents'=>8_550_000,'currency'=>'ZAR'],JSON_THROW_ON_ERROR);
        $earlyTime=(string)time();
        try { $service->recordProviderWebhook($provider['id'],$earlyBody,$earlyTime,hash_hmac('sha256',$earlyTime.'.'.$earlyBody,$secret)); self::fail('Early callback must request retry, not consume event.'); }
        catch (CommercialControlException $e) { self::assertSame('financial_provider_webhook_reference_pending',$e->reason); }
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM provider_webhook_events')->fetchColumn());
        try { $service->submitReleaseRequest($organisationId,$ownerId,'owner',$release['id'],['humanConfirmed'=>true,'expectedVersion'=>$approved['version']]); self::fail('Unknown outcome cannot represent acceptance.'); }
        catch (IntegrationException $e) { self::assertSame('integration_transport_unknown',$e->reason); }
        self::assertSame('unknown',$this->pdo->query('SELECT status FROM provider_release_dispatches')->fetchColumn());
        $rejectReplacement=function () use ($service,$organisationId,$qsId,$claim,$ownerId,$certificate,$provider): void {
            foreach ([fn()=> $service->certifyClaim($organisationId,$qsId,'member',$claim['id'],['grossCertifiedToDateCents'=>10_000_000,'otherDeductionsCents'=>0,'humanConfirmed'=>true,'reason'=>'Synthetic replacement attempt.']),fn()=> $service->createReleaseRequest($organisationId,$ownerId,'owner',$certificate['revisionId'],['providerId'=>$provider['id'],'humanConfirmed'=>true,'reason'=>'Synthetic replacement attempt.'])] as $operation) {
                try { $operation(); self::fail('Dispatched obligation cannot be re-certified or replaced.'); }
                catch (CommercialControlException $e) { self::assertSame('commercial_obligation_reconciliation_required',$e->reason); }
            }
            self::assertSame(2,(int)$this->pdo->query('SELECT COUNT(*) FROM provider_release_requests')->fetchColumn());
            self::assertSame(2,(int)$this->pdo->query('SELECT COUNT(*) FROM commercial_payment_certificate_revisions')->fetchColumn());
        };
        $rejectReplacement();
        $transport->unknown=false;
        $submitted=$service->submitReleaseRequest($organisationId,$ownerId,'owner',$release['id'],['humanConfirmed'=>true,'expectedVersion'=>$approved['version']]);
        self::assertSame('submitted_to_provider',$submitted['status']); self::assertSame(0,$submitted['providerPaidCents']);
        $rejectReplacement();
        $service->submitReleaseRequest($organisationId,$ownerId,'owner',$release['id'],['humanConfirmed'=>true,'expectedVersion'=>$approved['version']]);
        self::assertSame(2,$transport->submissions);
        self::assertSame($transport->bodies[0],$transport->bodies[1]); self::assertSame($transport->keys[0],$transport->keys[1]);
        self::assertSame($transport->bodies[0],$this->pdo->query('SELECT request_payload FROM provider_release_dispatches')->fetchColumn());
        self::assertSame(hash('sha256',$transport->bodies[0]),$this->pdo->query('SELECT request_sha256 FROM provider_release_dispatches')->fetchColumn());
        $timestamp = time();
        $body = json_encode(['organisationId'=>$organisationId,'providerId'=>$provider['id'],'eventId' => 'evt-paid-001', 'eventType' => 'payment_paid', 'providerReference' => 'PROV-REL-001', 'amountCents' => 8_550_000, 'currency' => 'ZAR'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        try {
            $service->recordProviderWebhook($provider['id'], $body, (string)($timestamp - 301), $signature);
            self::fail('Stale provider events must fail closed.');
        } catch (CommercialControlException $exception) {
            self::assertSame('financial_provider_webhook_timestamp_invalid', $exception->reason);
        }
        try {
            $service->recordProviderWebhook($provider['id'], $body, (string)$timestamp, str_repeat('0', 64));
            self::fail('Incorrect provider signatures must fail closed.');
        } catch (CommercialControlException $exception) {
            self::assertSame('financial_provider_webhook_signature_invalid', $exception->reason);
        }

        $mismatchBody = json_encode(['organisationId'=>$organisationId,'providerId'=>$provider['id'],'eventId' => 'evt-mismatch-001', 'eventType' => 'payment_paid', 'providerReference' => 'PROV-REL-001', 'amountCents' => 1, 'currency' => 'ZAR'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $mismatchSignature = hash_hmac('sha256', $timestamp.'.'.$mismatchBody, $secret);
        $mismatch = $service->recordProviderWebhook($provider['id'], $mismatchBody, (string)$timestamp, $mismatchSignature);
        self::assertSame('rejected', $mismatch['outcome']);
        self::assertSame('provider_release_amount_currency_mismatch', $mismatch['rejectionReason']);
        self::assertSame('submitted_to_provider', $mismatch['releaseStatus']);
        self::assertSame(0, $mismatch['providerPaidCents']);

        $event = $service->recordProviderWebhook($provider['id'], $body, (string)$timestamp, $signature);
        self::assertSame('applied', $event['outcome']);
        self::assertFalse($event['duplicate']);
        self::assertSame('provider_confirmed_paid', $event['releaseStatus']);
        self::assertSame(8_550_000, $event['providerPaidCents']);
        $rejectReplacement();
        $duplicate = $service->recordProviderWebhook($provider['id'], $body, (string)$timestamp, $signature);
        self::assertTrue($duplicate['duplicate']);
        $regressionBody=json_encode(['organisationId'=>$organisationId,'providerId'=>$provider['id'],'eventId'=>'evt-late-processing','eventType'=>'payment_processing','providerReference'=>'PROV-REL-001','amountCents'=>8_550_000,'currency'=>'ZAR'],JSON_THROW_ON_ERROR);
        $regression=$service->recordProviderWebhook($provider['id'],$regressionBody,(string)$timestamp,hash_hmac('sha256',$timestamp.'.'.$regressionBody,$secret));
        self::assertSame('rejected',$regression['outcome']); self::assertSame('provider_confirmed_paid',$regression['releaseStatus']);
        self::assertSame(8_550_000,$regression['providerPaidCents']);
        $changedBody=str_replace('8550000','8550001',$body);
        try { $service->recordProviderWebhook($provider['id'],$changedBody,(string)$timestamp,hash_hmac('sha256',$timestamp.'.'.$changedBody,$secret)); self::fail('Event id reuse with changed body must fail.'); }
        catch (CommercialControlException $e) { self::assertSame('financial_provider_webhook_event_conflict',$e->reason); }

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
        try { $service->submitReleaseRequest($organisationId,$ownerId,'owner',$locked['id'],['humanConfirmed'=>true,'expectedVersion'=>$locked['version']]); self::fail('Dispute lock must prevent transport.'); }
        catch (CommercialControlException $e) { self::assertSame('provider_release_not_submittable',$e->reason); }
        self::assertSame(2,$transport->submissions);
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
        $forecast = $service->createCashflowSnapshot($organisationId, $ownerId, 'owner', $baseline['id'], true, 'Project team reviewed the current schedule and certificate facts.');
        self::assertSame(100_000_000, $forecast['scheduledTotalCents']);
        self::assertSame(28_000_000, $forecast['grossCertifiedToDateCents']);
        self::assertSame(2_800_000, $forecast['retentionHeldToDateCents']);
        self::assertSame(0, $forecast['providerPaidToDateCents']);
        self::assertSame(72_000_000, $forecast['remainingContractValueCents']);
        self::assertTrue($forecast['advisoryOnly']);
        self::assertEqualsCanonicalizing(
            ['commercial_baseline', 'payment_schedule', 'claim_submission', 'payment_certificate', 'cashflow_forecast'],
            array_values(array_unique(array_intersect(
                $this->recordTypes($organisationId, $projectId),
                ['commercial_baseline', 'payment_schedule', 'claim_submission', 'payment_certificate', 'cashflow_forecast'],
            ))),
        );
        self::assertContains('finance_payment_certificate_issued', $this->inboxEventTypes($organisationId, $projectId));

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
        self::assertContains('variation_order', $this->recordTypes($organisationId, $projectId));
        self::assertContains('finance_variation_requires_approval', $this->inboxEventTypes($organisationId, $projectId));

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

    /** @return list<string> */
    private function recordTypes(string $organisationId, string $projectId): array
    {
        $statement = $this->pdo->prepare('SELECT record_type FROM project_records WHERE organisation_id=UNHEX(:organisation) AND project_id=UNHEX(:project) ORDER BY created_at,id');
        $statement->execute(['organisation' => $organisationId, 'project' => $projectId]);
        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** @return list<string> */
    private function inboxEventTypes(string $organisationId, string $projectId): array
    {
        $statement = $this->pdo->prepare('SELECT event_type FROM inbox_items WHERE organisation_id=UNHEX(:organisation) AND project_id=UNHEX(:project) ORDER BY created_at,id');
        $statement->execute(['organisation' => $organisationId, 'project' => $projectId]);
        return $statement->fetchAll(\PDO::FETCH_COLUMN);
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
