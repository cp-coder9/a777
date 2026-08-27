<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Project;

use Architex\Platform\Identity\Identifier;
use Architex\Platform\Project\CreateProject;
use Architex\Platform\Project\LifecycleService;
use Architex\Platform\Project\ProjectContext;
use Architex\Platform\Project\ProjectPassportService;
use Architex\Platform\Project\ProjectPhase;
use Architex\Platform\Project\ProjectWorkflowException;
use Architex\Platform\Records\ProjectRecordService;
use Architex\Tests\Support\MariaDbTestCase;

final class ProjectWorkflowTest extends MariaDbTestCase
{
    public function testCreatesTenantScopedPassportAndReportsPackRequiredRecords(): void
    {
        [$organisationId, $userId] = $this->seedTenant('alpha');
        $service = new ProjectPassportService($this->pdo);
        $project = $service->create(new CreateProject(
            $organisationId, $userId, 'AX-001', 'Civic Centre', 'City of Example',
            'Cape Town', 'Erf 42', 'Civic', 'Mixed-use consent required', 'architect',
        ));

        self::assertSame(ProjectPhase::Onboarding, $project->currentPhase);
        self::assertSame(1, $project->version);
        self::assertSame(['project_brief', 'property_profile'], array_column($project->missingRecords, 'recordType'));
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
    }

    public function testTransitionsAtomicallyAfterRequiredRecordsAndRejectsStaleVersion(): void
    {
        [$organisationId, $userId] = $this->seedTenant('beta');
        $passport = new ProjectPassportService($this->pdo);
        $created = $passport->create(new CreateProject($organisationId, $userId, 'AX-002', 'Clinic', 'Client', 'Tshwane', 'Erf 7', 'Healthcare', '', 'architect'));
        $context = new ProjectContext($organisationId, $userId, $created->projectId, 'owner');
        $records = new ProjectRecordService($this->pdo);
        $records->issue($context, ProjectPhase::Onboarding, 'project', 'project_brief', 'Approved brief', 'approved', ['summary' => 'Clinic']);
        $records->issue($context, ProjectPhase::Onboarding, 'project', 'property_profile', 'Property profile', 'issued', ['erf' => '7']);

        $advanced = (new LifecycleService($this->pdo))->transition($context, ProjectPhase::Feasibility, 1);
        self::assertSame(ProjectPhase::Feasibility, $advanced->currentPhase);
        self::assertSame(2, $advanced->version);
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM project_records WHERE record_type='project_phase_transition'")->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM workflow_events')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM inbox_items')->fetchColumn());

        $this->expectException(ProjectWorkflowException::class);
        $this->expectExceptionMessage('stale_project_version');
        (new LifecycleService($this->pdo))->transition($context, ProjectPhase::Appointment, 1);
    }

    public function testBlocksUnsafeAdvanceAndCannotReadAnotherTenantProject(): void
    {
        [$organisationId, $userId] = $this->seedTenant('gamma');
        [$otherOrganisationId, $otherUserId] = $this->seedTenant('delta');
        $service = new ProjectPassportService($this->pdo);
        $created = $service->create(new CreateProject($organisationId, $userId, 'AX-003', 'School', 'Client', 'eThekwini', 'Lot 1', 'Education', '', 'architect'));

        try {
            (new LifecycleService($this->pdo))->transition(new ProjectContext($organisationId, $userId, $created->projectId, 'owner'), ProjectPhase::Feasibility, 1);
            self::fail('Missing records must block the transition.');
        } catch (ProjectWorkflowException $exception) {
            self::assertSame('phase_requirements_missing', $exception->reason);
        }
        self::assertNull($service->find(new ProjectContext($otherOrganisationId, $otherUserId, $created->projectId, 'owner')));
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM workflow_events')->fetchColumn());
    }

    /** @return array{string,string} */
    private function seedTenant(string $slug): array
    {
        $organisationId = Identifier::generate();
        $userId = Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")
            ->execute(['id' => $userId, 'email' => $slug . '@example.test']);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")
            ->execute(['id' => $organisationId, 'name' => ucfirst($slug), 'slug' => $slug]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'owner','active')")
            ->execute(['id' => Identifier::generate(), 'organisation_id' => $organisationId, 'user_id' => $userId]);
        return [$organisationId, $userId];
    }
}
