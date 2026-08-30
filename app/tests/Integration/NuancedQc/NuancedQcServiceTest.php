<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\NuancedQc;

use Architex\Platform\Identity\Identifier;
use Architex\Platform\NuancedQc\NuancedQcEvaluator;
use Architex\Platform\NuancedQc\NuancedQcException;
use Architex\Platform\NuancedQc\NuancedQcRegistry;
use Architex\Platform\NuancedQc\NuancedQcService;
use Architex\Tests\Support\MariaDbTestCase;

final class NuancedQcServiceTest extends MariaDbTestCase
{
    public function testGovernedQcLifecycleIsTenantScopedAndSpeaksBack(): void
    {
        [$organisation, $owner, $reviewer, $developer, $viewer, $project] = $this->tenant('qc-a');
        [$other, $otherOwner, , , , $otherProject] = $this->tenant('qc-b');
        $file = $this->file($organisation, $project, $owner);
        $foreignFile = $this->file($other, $otherProject, $otherOwner);
        $service = new NuancedQcService($this->pdo, new NuancedQcRegistry(), new NuancedQcEvaluator());

        $service->assignRole($organisation, $owner, 'owner', ['userId' => $reviewer, 'qcRole' => 'qc_reviewer']);
        $service->assignRole($organisation, $owner, 'owner', ['userId' => $developer, 'qcRole' => 'developer']);
        $run = $service->createRun($organisation, $reviewer, 'member', [
            'title' => 'Pack 27 acceptance', 'scopeType' => 'implementation_pack', 'target' => 'Pack 27',
            'projectId' => $project, 'assistanceLabel' => 'Amy assisted; reviewer accountable',
            'sourceItems' => [['kind' => 'pack', 'reference' => '27. architex-nuanced-qc-control-pack', 'sourcePromise' => 'Production QC control']],
            'idempotencyKey' => 'release23-pack27-run',
        ]);
        self::assertFalse($run['idempotentReplay']);
        self::assertTrue($service->createRun($organisation, $reviewer, 'member', ['title' => 'ignored', 'scopeType' => 'repo', 'target' => 'ignored', 'sourceItems' => [['kind' => 'repo', 'reference' => 'ignored', 'sourcePromise' => 'ignored']], 'idempotencyKey' => 'release23-pack27-run'])['idempotentReplay']);
        $this->expectReason(fn () => $service->createRun($organisation, $reviewer, 'member', ['title' => 'Foreign project', 'scopeType' => 'repo', 'target' => 'repo', 'projectId' => $otherProject, 'sourceItems' => [['kind' => 'project', 'reference' => $otherProject, 'sourcePromise' => 'Foreign']], 'idempotencyKey' => 'foreign-project']), 'nuanced_qc_project_not_found');

        $service->recordTechnicalSummary($organisation, $reviewer, 'member', $run['id'], ['technicalVerificationSummary' => 'PHP, API and MariaDB verified.', 'packGoalMatchSummary' => 'Matches all pack goals.', 'nuancedGapSummary' => 'Blocker retained until retest.', 'expectedVersion' => 1]);
        foreach ((new NuancedQcRegistry())->categories() as $category) {
            $service->recordCategoryResult($organisation, $reviewer, 'member', $run['id'], ['category' => $category['key'], 'status' => 'pass', 'reason' => 'Evidence reviewed for this category.']);
        }
        $this->expectReason(fn () => $service->recordCategoryResult($organisation, $reviewer, 'member', $run['id'], ['category' => 'invented', 'status' => 'pass', 'reason' => 'No']), 'nuanced_qc_category_invalid');

        $finding = $service->addFinding($organisation, $reviewer, 'member', $run['id'], [
            'category' => 'tenant_project_isolation', 'severity' => 'blocker', 'title' => 'Cross-tenant quote visible',
            'description' => 'A scoped read returned another tenant record.', 'evidenceType' => 'test_output', 'evidenceRef' => 'NuancedQcServiceTest::tenantIsolation',
            'affectedPack' => 'Supplier procurement', 'affectedWorkflow' => 'Quote comparison', 'crossPackImpact' => 'Procurement records must remain isolated.',
            'requiredFix' => 'Scope the query by organisation and project.', 'retestSteps' => 'Repeat as tenant B and expect not found.', 'ownerUserId' => $developer,
        ]);
        self::assertNotEmpty($finding['riskId']);
        self::assertNotEmpty($finding['retestId']);
        $this->expectReason(fn () => $service->linkEvidence($organisation, $reviewer, 'member', $run['id'], ['findingId' => $finding['id'], 'storedFileId' => $foreignFile, 'evidenceType' => 'file', 'label' => 'Foreign', 'idempotencyKey' => 'foreign-file']), 'nuanced_qc_file_not_found');
        $evidence = $service->linkEvidence($organisation, $reviewer, 'member', $run['id'], ['findingId' => $finding['id'], 'storedFileId' => $file, 'evidenceType' => 'file', 'label' => 'Isolation failure output', 'notes' => 'Retained in File Manager.', 'idempotencyKey' => 'qc-evidence-1']);
        self::assertFalse($evidence['idempotentReplay']);

        $this->expectReason(fn () => $service->signOff($organisation, $owner, 'owner', $run['id'], ['decision' => 'accepted', 'humanConfirmed' => true, 'reason' => 'Premature']), 'nuanced_qc_open_blocker_findings');
        $this->expectReason(fn () => $service->acceptRisk($organisation, $owner, 'owner', $finding['riskId'], ['expectedVersion' => 1, 'humanConfirmed' => false, 'reason' => 'No']), 'nuanced_qc_human_confirmation_required');
        $risk = $service->acceptRisk($organisation, $owner, 'owner', $finding['riskId'], ['expectedVersion' => 1, 'humanConfirmed' => true, 'reason' => 'Accountable owner accepts the time-bounded residual risk.', 'reviewDate' => '2026-09-30']);
        self::assertSame('accepted', $risk['status']);
        $this->expectReason(fn () => $service->acceptRisk($organisation, $owner, 'owner', $finding['riskId'], ['expectedVersion' => 1, 'humanConfirmed' => true, 'reason' => 'Stale', 'reviewDate' => '2026-09-30']), 'nuanced_qc_version_conflict');

        $retest = $service->completeRetest($organisation, $developer, 'member', $finding['retestId'], ['expectedVersion' => 1, 'status' => 'passed', 'resultSummary' => 'Tenant B now receives not found.', 'evidenceRef' => 'NuancedQcServiceTest::tenantIsolation']);
        self::assertSame('passed', $retest['status']);
        $this->expectReason(fn () => $service->signOff($organisation, $developer, 'member', $run['id'], ['decision' => 'accepted', 'humanConfirmed' => true, 'reason' => 'Developer self sign-off']), 'nuanced_qc_signoff_forbidden');
        $signoff = $service->signOff($organisation, $owner, 'owner', $run['id'], ['decision' => 'accepted_with_notes', 'humanConfirmed' => true, 'reason' => 'All categories reviewed; residual accepted risk remains visible.']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $signoff['snapshotSha256']);

        $workspace = $service->workspace($organisation, $viewer, 'viewer', $project);
        self::assertCount(35, $workspace['definitions']);
        self::assertCount(1, $workspace['runs']);
        self::assertSame('accepted_with_notes', $workspace['runs'][0]['signoffStatus']);
        self::assertFalse($workspace['capabilities']['mayMutate']);
        $this->expectReason(fn () => $service->workspace($other, $otherOwner, 'owner', $project), 'nuanced_qc_project_not_found');
        self::assertGreaterThanOrEqual(3, (int) $this->pdo->query("SELECT COUNT(*) FROM project_records WHERE organisation_id=UNHEX('$organisation') AND module_key='nuanced_qc'")->fetchColumn());
        self::assertGreaterThanOrEqual(3, (int) $this->pdo->query("SELECT COUNT(*) FROM inbox_items WHERE organisation_id=UNHEX('$organisation') AND event_type LIKE 'nuanced_qc.%'")->fetchColumn());
        self::assertGreaterThanOrEqual(8, (int) $this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE organisation_id=UNHEX('$organisation') AND action LIKE 'nuanced_qc.%'")->fetchColumn());
    }

    private function expectReason(callable $callback, string $reason): void
    {
        try { $callback(); self::fail('Expected '.$reason); } catch (NuancedQcException $error) { self::assertSame($reason, $error->reason); }
    }

    /** @return array{string,string,string,string,string,string} */
    private function tenant(string $slug): array
    {
        $organisation = Identifier::generate(); $owner = Identifier::generate(); $reviewer = Identifier::generate(); $developer = Identifier::generate(); $viewer = Identifier::generate();
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id' => $organisation, 'name' => $slug, 'slug' => $slug]);
        foreach ([[$owner, 'owner'], [$reviewer, 'member'], [$developer, 'member'], [$viewer, 'viewer']] as $index => [$user, $role]) {
            $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id' => $user, 'email' => $slug.$index.'@example.test']);
            $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),:role,'active')")->execute(['id' => Identifier::generate(), 'o' => $organisation, 'u' => $user, 'role' => $role]);
        }
        $project = Identifier::generate();
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,created_by) VALUES(UNHEX(:id),UNHEX(:o),:code,:name,'Client','City','Erf 1','Office','','architect',UNHEX(:u))")->execute(['id' => $project, 'o' => $organisation, 'code' => strtoupper(substr(hash('sha256', $slug), 0, 8)), 'name' => $slug, 'u' => $owner]);
        return [$organisation, $owner, $reviewer, $developer, $viewer, $project];
    }

    private function file(string $organisation, string $project, string $user): string
    {
        $id = Identifier::generate();
        $this->pdo->prepare('INSERT INTO stored_files(id,organisation_id,project_id,uploaded_by,original_name,storage_name,mime_type,byte_size,sha256) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:p),UNHEX(:u),\'qc.txt\',:storage,\'text/plain\',4,:sha)')->execute(['id' => $id, 'o' => $organisation, 'p' => $project, 'u' => $user, 'storage' => substr(hash('sha256', $id), 0, 48), 'sha' => hash('sha256', $id)]);
        return $id;
    }
}
