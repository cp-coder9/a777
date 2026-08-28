<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\SiteExecution;

use Architex\Platform\Identity\Identifier;
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
