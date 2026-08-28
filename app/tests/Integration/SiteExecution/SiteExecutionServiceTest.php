<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\SiteExecution;

use Architex\Platform\Identity\Identifier;
use Architex\Platform\SiteExecution\SiteExecutionException;
use Architex\Platform\SiteExecution\SiteExecutionService;
use Architex\Tests\Support\MariaDbTestCase;

final class SiteExecutionServiceTest extends MariaDbTestCase
{
    public function testConnectedFieldControlScenarioPersistsControlledOutputs(): void
    {
        [$organisationId, $ownerId, $projectId] = $this->tenant('Field Controls', 'field-controls');
        $service = new SiteExecutionService($this->pdo);

        $log = $service->createDailyLog($organisationId, $ownerId, 'owner', $projectId, [
            'logDate' => '2026-08-29', 'weather' => 'Clear morning, rain after 14:00',
            'labour' => ['main_contractor' => 12, 'electrical_subcontractor' => 3],
            'plant' => ['mobile crane'], 'deliveries' => ['electrical conduit'],
            'visitors' => ['architect'], 'delays' => ['Rain stopped brickwork'],
            'safetyNotes' => ['Toolbox talk completed'], 'humanConfirmed' => true,
        ]);
        self::assertSame('submitted', $log['status']);

        $evidence = $service->captureEvidence($organisationId, $ownerId, 'owner', $projectId, [
            'dailyLogId' => $log['id'], 'evidenceType' => 'photo', 'title' => 'Slab conflict',
            'fileId' => null, 'locationLabel' => 'Level 1 grid B3',
            'latitude' => -26.204103, 'longitude' => 28.047305, 'capturedAt' => '2026-08-29T12:30:00+00:00',
            'popiaClassification' => 'project_team', 'humanConfirmed' => true,
        ]);
        self::assertSame($log['id'], $evidence['dailyLogId']);

        $rfi = $service->createRfi($organisationId, $ownerId, 'owner', $projectId, [
            'subject' => 'Slab penetration conflict', 'question' => 'Confirm the revised electrical route.',
            'discipline' => 'architectural', 'linkedDocumentIds' => ['A-210-REV-C'],
            'dueAt' => '2026-08-30T12:00:00+00:00', 'humanConfirmed' => true,
        ]);
        self::assertSame('open', $rfi['status']);
        $responded = $service->respondRfi($organisationId, $ownerId, 'owner', $rfi['id'], [
            'answer' => 'Reroute along grid B after structural coordination.',
            'requiresInstruction' => true, 'humanConfirmed' => true,
        ]);
        self::assertSame('responded', $responded['status']);

        $instruction = $service->createSiteInstruction($organisationId, $ownerId, 'owner', $projectId, [
            'title' => 'Reroute conduit', 'instruction' => 'Proceed only after structural confirmation.',
            'linkedRfiId' => $rfi['id'], 'costImpact' => 'possible', 'timeImpact' => 'possible',
        ]);
        $issued = $service->issueSiteInstruction($organisationId, $ownerId, 'owner', $instruction['id'], [
            'humanConfirmed' => true, 'reason' => 'Principal agent reviewed the exact instruction.',
        ]);
        self::assertSame('issued', $issued['status']);
        self::assertTrue($issued['variationFlag']);
        $acknowledged = $service->acknowledgeSiteInstruction($organisationId, $ownerId, 'owner', $instruction['id'], [
            'humanConfirmed' => true, 'reason' => 'Contractor acknowledges receipt only.',
        ]);
        self::assertSame('acknowledged', $acknowledged['status']);

        $ncr = $service->createNcr($organisationId, $ownerId, 'owner', $projectId, [
            'title' => 'Unapproved beam chase', 'severity' => 'high', 'responsibleParty' => 'Electrical subcontractor',
            'description' => 'Work occurred without written approval.', 'evidenceIds' => [$evidence['id']],
            'blocksPayment' => true, 'humanConfirmed' => true,
        ]);
        $corrective = $service->submitNcrCorrectiveAction($organisationId, $ownerId, 'owner', $ncr['id'], [
            'correctiveAction' => 'Stop work, make good and submit method statement.',
            'evidenceIds' => [$evidence['id']], 'humanConfirmed' => true,
        ]);
        self::assertSame('corrective_action_submitted', $corrective['status']);

        $snag = $service->createSnag($organisationId, $ownerId, 'owner', $projectId, [
            'location' => 'Level 1 passage', 'description' => 'Patch finish incomplete', 'priority' => 'critical',
            'responsibleParty' => 'Electrical subcontractor', 'dueAt' => '2026-09-02T12:00:00+00:00',
            'evidenceIds' => [$evidence['id']], 'blocksPayment' => true, 'humanConfirmed' => true,
        ]);
        $allocated = $service->allocateSnag($organisationId, $ownerId, 'owner', $snag['id'], ['humanConfirmed' => true]);
        self::assertSame('allocated', $allocated['status']);
        $ready = $service->markSnagReady($organisationId, $ownerId, 'owner', $snag['id'], [
            'evidenceIds' => [$evidence['id']], 'humanConfirmed' => true,
        ]);
        self::assertSame('ready_for_reinspection', $ready['status']);

        $inspection = $service->createInspection($organisationId, $ownerId, 'owner', $projectId, [
            'inspectionType' => 'structural', 'findings' => ['No further chasing without method statement.'],
            'checklist' => [['item' => 'Made-good area inspected', 'outcome' => 'fail']],
            'followUps' => ['Contractor to submit method statement'], 'signOffRequired' => true,
        ]);
        $inspection = $service->issueInspection($organisationId, $ownerId, 'owner', $inspection['id'], [
            'professionalSupervisorId' => $ownerId, 'humanConfirmed' => true,
        ]);
        self::assertSame('requires_follow_up', $inspection['status']);
        self::assertFalse($inspection['statutoryApproval']);

        $warning = $service->createDelayWarning($organisationId, $ownerId, 'owner', $projectId, [
            'cause' => 'weather', 'description' => 'Rain and coordination hold may affect sequencing.',
            'noticeDeadline' => '2026-08-30T16:00:00+00:00', 'likelyProgrammeImpactDays' => 2,
            'humanConfirmed' => true,
        ]);
        self::assertSame('notice_required', $warning['status']);
        self::assertFalse($warning['grantsEntitlement']);
        $impact = $service->assessProgrammeImpact($organisationId, $ownerId, 'owner', $projectId, [
            'sourceType' => 'delay_warning', 'sourceObjectId' => $warning['id'],
            'estimatedDays' => 2, 'humanConfirmed' => true,
        ]);
        self::assertTrue($impact['requiresPlannerReview']);

        $blocker = $service->createPaymentBlocker($organisationId, $ownerId, 'owner', $projectId, [
            'sourceType' => 'ncr', 'sourceObjectId' => $ncr['id'],
            'reason' => 'High-severity NCR requires verification.', 'severity' => 'high', 'humanConfirmed' => true,
        ]);
        self::assertSame('active', $blocker['status']);
        self::assertFalse($blocker['movesMoney']);

        $workspace = $service->workspace($organisationId, $ownerId, $projectId);
        self::assertCount(1, $workspace['dailyLogs']);
        self::assertCount(1, $workspace['evidence']);
        self::assertCount(1, $workspace['rfis']);
        self::assertCount(1, $workspace['siteInstructions']);
        self::assertCount(1, $workspace['ncrs']);
        self::assertCount(1, $workspace['snags']);
        self::assertCount(1, $workspace['inspections']);
        self::assertCount(1, $workspace['delayWarnings']);
        self::assertCount(1, $workspace['programmeImpacts']);
        self::assertCount(1, $workspace['paymentBlockers']);
        self::assertNotEmpty($workspace['recommendations']);
        self::assertContains('site_instruction', $this->values('project_records', 'record_type', $organisationId, $projectId));
        self::assertContains('site_execution.payment_blocker', $this->values('inbox_items', 'event_type', $organisationId, $projectId));
        self::assertContains('site_execution.payment_blocker_created', $this->values('audit_events', 'action', $organisationId, null));
    }

    public function testStateMachinesAuthorityAndTenantBoundariesFailClosed(): void
    {
        [$organisationId, $ownerId, $projectId] = $this->tenant('Field State', 'field-state');
        [$otherOrganisationId, $otherOwnerId, $otherProjectId] = $this->tenant('Other Field State', 'other-field-state');
        $memberId = $this->member($organisationId, 'unverified-contractor@example.test');
        $service = new SiteExecutionService($this->pdo);

        $rfi = $service->createRfi($organisationId, $ownerId, 'owner', $projectId, [
            'subject' => 'Past due RFI', 'question' => 'Confirm detail.', 'discipline' => 'structural',
            'linkedDocumentIds' => [], 'dueAt' => '2026-08-01T12:00:00+00:00', 'humanConfirmed' => true,
        ]);
        self::assertSame(1, $service->markOverdueRfis($organisationId, $ownerId, $projectId, '2026-08-29T12:00:00+00:00'));
        self::assertSame('overdue', $service->workspace($organisationId, $ownerId, $projectId)['rfis'][0]['status']);
        $service->respondRfi($organisationId, $ownerId, 'owner', $rfi['id'], ['answer' => 'Use detail S-12.', 'requiresInstruction' => false, 'humanConfirmed' => true]);
        self::assertSame('closed', $service->closeRfi($organisationId, $ownerId, 'owner', $rfi['id'], ['humanConfirmed' => true, 'reason' => 'Originator confirmed answer.'])['status']);

        $instruction = $service->createSiteInstruction($organisationId, $ownerId, 'owner', $projectId, [
            'title' => 'Controlled instruction', 'instruction' => 'Install per S-12.', 'costImpact' => 'none', 'timeImpact' => 'none',
        ]);
        try {
            $service->issueSiteInstruction($organisationId, $memberId, 'member', $instruction['id'], ['humanConfirmed' => true, 'reason' => 'Unauthorised issue attempt.']);
            self::fail('A general member must not issue a formal instruction.');
        } catch (SiteExecutionException $exception) {
            self::assertSame('site_professional_authority_required', $exception->reason);
        }
        $service->issueSiteInstruction($organisationId, $ownerId, 'owner', $instruction['id'], ['humanConfirmed' => true, 'reason' => 'Principal agent reviewed exact instruction.']);
        $replacement = $service->supersedeSiteInstruction($organisationId, $ownerId, 'owner', $instruction['id'], [
            'title' => 'Controlled instruction Rev 2', 'instruction' => 'Install per S-12 Rev 2.',
            'costImpact' => 'possible', 'timeImpact' => 'none', 'humanConfirmed' => true,
            'reason' => 'Revision issued instead of mutating the original.',
        ]);
        $instructions = $service->workspace($organisationId, $ownerId, $projectId)['siteInstructions'];
        self::assertSame('superseded', $instructions[0]['status']);
        self::assertSame('issued', $replacement['status']);
        self::assertSame($instruction['id'], $replacement['supersedesId']);

        $evidenceLog = $service->createDailyLog($organisationId, $ownerId, 'owner', $projectId, [
            'logDate' => '2026-08-28', 'weather' => 'Clear', 'labour' => [], 'plant' => [], 'deliveries' => [],
            'visitors' => [], 'delays' => [], 'safetyNotes' => [], 'humanConfirmed' => true,
        ]);
        $evidence = $service->captureEvidence($organisationId, $ownerId, 'owner', $projectId, [
            'dailyLogId' => $evidenceLog['id'], 'evidenceType' => 'photo', 'title' => 'Quality evidence',
            'capturedAt' => '2026-08-28T12:00:00+00:00', 'popiaClassification' => 'restricted', 'humanConfirmed' => true,
        ]);
        $ncr = $service->createNcr($organisationId, $ownerId, 'owner', $projectId, [
            'title' => 'NCR verification', 'description' => 'Non-conforming work.', 'severity' => 'critical',
            'responsibleParty' => 'Contractor', 'evidenceIds' => [$evidence['id']], 'blocksPayment' => true, 'humanConfirmed' => true,
        ]);
        $blocker = $service->createPaymentBlocker($organisationId, $ownerId, 'owner', $projectId, [
            'sourceType' => 'ncr', 'sourceObjectId' => $ncr['id'], 'reason' => 'NCR remains open.',
            'severity' => 'critical', 'humanConfirmed' => true,
        ]);
        $service->submitNcrCorrectiveAction($organisationId, $ownerId, 'owner', $ncr['id'], [
            'correctiveAction' => 'Remove and replace.', 'evidenceIds' => [$evidence['id']], 'humanConfirmed' => true,
        ]);
        self::assertSame('verified_closed', $service->decideNcr($organisationId, $ownerId, 'owner', $ncr['id'], ['decision' => 'verified_closed', 'humanConfirmed' => true, 'reason' => 'Professional verified replacement.'])['status']);
        self::assertSame('cleared', $service->clearPaymentBlocker($organisationId, $ownerId, 'owner', $blocker['id'], ['humanConfirmed' => true, 'reason' => 'Source NCR verified closed.'])['status']);

        $snag = $service->createSnag($organisationId, $ownerId, 'owner', $projectId, [
            'location' => 'Room 1', 'description' => 'Paint defect', 'priority' => 'medium', 'responsibleParty' => 'Contractor',
            'dueAt' => '2026-09-02T12:00:00+00:00', 'evidenceIds' => [$evidence['id']], 'blocksPayment' => false, 'humanConfirmed' => true,
        ]);
        $service->allocateSnag($organisationId, $ownerId, 'owner', $snag['id'], ['humanConfirmed' => true]);
        $service->markSnagReady($organisationId, $ownerId, 'owner', $snag['id'], ['evidenceIds' => [$evidence['id']], 'humanConfirmed' => true]);
        self::assertSame('closed', $service->decideSnag($organisationId, $ownerId, 'owner', $snag['id'], ['decision' => 'closed', 'humanConfirmed' => true, 'reason' => 'Reinspection passed.'])['status']);

        $inspection = $service->createInspection($organisationId, $ownerId, 'owner', $projectId, [
            'inspectionType' => 'quality', 'findings' => [], 'checklist' => [['item' => 'Finish', 'outcome' => 'pass']],
            'followUps' => [], 'signOffRequired' => true,
        ]);
        self::assertSame('issued', $service->issueInspection($organisationId, $ownerId, 'owner', $inspection['id'], ['professionalSupervisorId' => $ownerId, 'humanConfirmed' => true])['status']);
        self::assertSame('closed', $service->closeInspection($organisationId, $ownerId, 'owner', $inspection['id'], ['humanConfirmed' => true, 'reason' => 'All internal checks completed.'])['status']);

        $warning = $service->createDelayWarning($organisationId, $ownerId, 'owner', $projectId, [
            'cause' => 'materials', 'description' => 'Late delivery.', 'noticeDeadline' => '2026-08-30T12:00:00+00:00',
            'likelyProgrammeImpactDays' => 1, 'humanConfirmed' => true,
        ]);
        self::assertSame('under_review', $service->transitionDelayWarning($organisationId, $ownerId, 'owner', $warning['id'], ['status' => 'under_review', 'humanConfirmed' => true, 'reason' => 'Notice evidence under review.'])['status']);
        self::assertSame('closed', $service->transitionDelayWarning($organisationId, $ownerId, 'owner', $warning['id'], ['status' => 'closed', 'humanConfirmed' => true, 'reason' => 'Warning reviewed without entitlement decision.'])['status']);

        try {
            $service->workspace($otherOrganisationId, $otherOwnerId, $projectId);
            self::fail('Cross-tenant project access must fail closed.');
        } catch (SiteExecutionException $exception) {
            self::assertSame('site_project_not_found', $exception->reason);
        }
        self::assertNotSame($projectId, $otherProjectId);
    }

    /** @return array{string,string,string} */
    private function tenant(string $name, string $prefix): array
    {
        $organisation = Identifier::generate(); $user = Identifier::generate(); $project = Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id' => $user, 'email' => $prefix.'@example.test']);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id' => $organisation, 'name' => $name, 'slug' => $prefix.'-'.substr($organisation, 0, 8)]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'owner','active')")->execute(['id' => Identifier::generate(), 'o' => $organisation, 'u' => $user]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:p),UNHEX(:o),:code,:name,'Client','Johannesburg','Erf 1','Residential','','architect','construction_execution',UNHEX(:u))")->execute(['p' => $project, 'o' => $organisation, 'u' => $user, 'code' => strtoupper(substr($prefix, 0, 8)), 'name' => $name.' Project']);
        return [$organisation, $user, $project];
    }

    private function member(string $organisationId, string $email): string
    {
        $user = Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id' => $user, 'email' => $email]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'member','active')")->execute(['id' => Identifier::generate(), 'o' => $organisationId, 'u' => $user]);
        return $user;
    }

    /** @return list<string> */
    private function values(string $table, string $column, string $organisationId, ?string $projectId): array
    {
        $project = $projectId === null ? '' : ' AND project_id=UNHEX(:project)';
        $statement = $this->pdo->prepare("SELECT $column FROM $table WHERE organisation_id=UNHEX(:organisation)$project ORDER BY created_at,id");
        $parameters = ['organisation' => $organisationId]; if ($projectId !== null) $parameters['project'] = $projectId;
        $statement->execute($parameters);
        return array_values(array_map('strval', $statement->fetchAll(\PDO::FETCH_COLUMN)));
    }
}
