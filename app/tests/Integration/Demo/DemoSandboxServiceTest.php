<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Demo;

use Architex\Platform\Demo\DemoException;
use Architex\Platform\Demo\DemoRoleLensRegistry;
use Architex\Platform\Demo\DemoSandboxService;
use Architex\Platform\Identity\Identifier;
use Architex\Tests\Support\MariaDbTestCase;

final class DemoSandboxServiceTest extends MariaDbTestCase
{
    public function testPersistentSandboxLifecycleIsolationResetAndRollback(): void
    {
        [$organisationA, $userA] = $this->seedTenant('demo-a');
        [$organisationB, $userB] = $this->seedTenant('demo-b');
        $service = new DemoSandboxService($this->pdo);

        $first = $service->ensure($organisationA, $userA, 'owner');
        self::assertSame(1, $first['generation']);
        self::assertSame(12, $first['counts']['projects']);
        self::assertSame(22, $first['counts']['personas']);
        self::assertSame($first['sandboxId'], $service->ensure($organisationA, $userA, 'owner')['sandboxId']);
        $secondUser = $service->ensure($organisationB, $userB, 'owner');
        self::assertNotSame($first['sandboxId'], $secondUser['sandboxId']);

        $registry = new DemoRoleLensRegistry();
        foreach ($registry->all() as $key => $role) {
            $selected = $service->selectRole($organisationA, $userA, $key);
            self::assertSame($key, $selected['selectedRole']);
            self::assertCount(count($role['projectKeys']), $service->projects($organisationA, $userA));
        }
        $service->selectRole($organisationA, $userA, 'engineer_structural');
        self::assertSame('engineer_structural', (new DemoSandboxService($this->pdo))->state($organisationA, $userA)['selectedRole']);

        $visible = $service->projects($organisationA, $userA);
        self::assertNotEmpty($visible);
        $projectA = $visible[0]['id'];
        self::assertSame($projectA, $service->project($organisationA, $userA, $projectA)['id']);
        $this->assertDemoError(fn () => $service->project($organisationB, $userB, $projectA), 'demo_project_not_visible');

        $this->pdo->prepare("INSERT INTO project_records(id,organisation_id,project_id,phase,module_key,record_type,title,status,payload,approvals,linked_record_ids,created_by) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:p),'concept_design','demo','demo_note','Persistent user note','draft',JSON_OBJECT('persisted',true),JSON_OBJECT(),JSON_ARRAY(),UNHEX(:u))")->execute(['id'=>Identifier::generate(),'o'=>$organisationA,'p'=>$projectA,'u'=>$userA]);
        self::assertGreaterThan(0, (new DemoSandboxService($this->pdo))->project($organisationA, $userA, $projectA)['recordCount']);

        $learning = $service->learning($organisationA, $userA);
        self::assertSame(['articles'=>6,'assessments'=>4,'modules'=>3,'certificates'=>5], $learning['counts']);
        self::assertSame('not_accredited_demo_record', $learning['relianceLabel']);
        $this->assertDemoError(fn () => $service->reset($organisationA, $userA, 'owner', ['confirmation'=>'wrong','reason'=>'A valid reset reason.']), 'demo_reset_confirmation_required');
        $this->assertDemoError(fn () => $service->reset($organisationA, $userA, 'owner', ['confirmation'=>'RESET DEMO SANDBOX','reason'=>'short']), 'demo_reset_reason_required');

        $replacement = $service->reset($organisationA, $userA, 'owner', ['confirmation'=>'RESET DEMO SANDBOX','reason'=>'Restore the deterministic training baseline.']);
        self::assertSame(2, $replacement['generation']);
        self::assertNotSame($organisationA, $replacement['organisationId']);
        self::assertSame('revoked', $this->membershipStatus($organisationA, $userA));
        self::assertSame('closed', $this->organisationStatus($organisationA));
        self::assertSame(['superseded','active'], $this->sandboxStatuses($userA));
        self::assertSame(1, $service->state($organisationB, $userB)['generation']);
        self::assertSame('active', $this->membershipStatus($organisationB, $userB));

        $failing = new DemoSandboxService($this->pdo, seedHook: static function (): void {
            throw new DemoException('forced_seed_failure');
        });
        $this->assertDemoError(fn () => $failing->reset($replacement['organisationId'], $userA, 'owner', ['confirmation'=>'RESET DEMO SANDBOX','reason'=>'Exercise transactional seed rollback safely.']), 'forced_seed_failure');
        self::assertSame(2, $service->state($replacement['organisationId'], $userA)['generation']);
        self::assertSame('active', $this->membershipStatus($replacement['organisationId'], $userA));
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM demo_sandboxes WHERE owner_user_id=UNHEX('$userA') AND status='active'")->fetchColumn());
    }

    /** @return array{string,string} */
    private function seedTenant(string $slug): array
    {
        $organisation = Identifier::generate();
        $user = Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$user,'email'=>$slug.'@example.test']);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id'=>$organisation,'name'=>$slug,'slug'=>$slug.'-'.substr($organisation,0,8)]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'owner','active')")->execute(['id'=>Identifier::generate(),'o'=>$organisation,'u'=>$user]);

        return [$organisation, $user];
    }

    private function assertDemoError(callable $operation, string $reason): void
    {
        try {
            $operation();
            self::fail('Expected demo operation to fail with ' . $reason);
        } catch (DemoException $exception) {
            self::assertSame($reason, $exception->getMessage());
        }
    }

    private function membershipStatus(string $organisation, string $user): string
    {
        $statement = $this->pdo->prepare('SELECT status FROM organisation_memberships WHERE organisation_id=UNHEX(:o) AND user_id=UNHEX(:u)');
        $statement->execute(['o'=>$organisation,'u'=>$user]);
        return (string) $statement->fetchColumn();
    }

    private function organisationStatus(string $organisation): string
    {
        $statement = $this->pdo->prepare('SELECT status FROM organisations WHERE id=UNHEX(:o)');
        $statement->execute(['o'=>$organisation]);
        return (string) $statement->fetchColumn();
    }

    /** @return list<string> */
    private function sandboxStatuses(string $user): array
    {
        $statement = $this->pdo->prepare('SELECT status FROM demo_sandboxes WHERE owner_user_id=UNHEX(:u) ORDER BY generation');
        $statement->execute(['u'=>$user]);
        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }
}
