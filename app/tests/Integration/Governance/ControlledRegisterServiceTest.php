<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Governance;

use Architex\Platform\Governance\ControlledRegisterException;
use Architex\Platform\Governance\ControlledRegisterPolicy;
use Architex\Platform\Governance\ControlledRegisterService;
use Architex\Platform\Identity\Identifier;
use Architex\Tests\Support\MariaDbTestCase;

final class ControlledRegisterServiceTest extends MariaDbTestCase
{
    public function testFourRegistersPersistHumanGovernedProjectRecords(): void
    {
        [$organisation, $user, $project] = $this->tenant();
        $service = new ControlledRegisterService($this->pdo, new ControlledRegisterPolicy());

        foreach (['dispute_resolution', 'insurance_register', 'nhbrc_enrolment', 'contractor_compliance'] as $key) {
            $created = $service->create($organisation, $user, 'owner', $project, $key, [
                'title' => str_replace('_', ' ', $key), 'reference' => strtoupper(substr($key, 0, 8)) . '-001',
                'ownerRole' => 'owner', 'dueOn' => '2026-12-01', 'details' => ['source' => 'human-entered'],
                'evidenceFileIds' => [], 'humanConfirmed' => true,
            ]);
            self::assertSame($service->workspace($organisation, $user, $project, $key)['definition']['initialStatus'], $created['status']);
            self::assertSame(1, $created['version']);
        }

        self::assertSame(4, (int) $this->pdo->query('SELECT COUNT(*) FROM controlled_register_entries')->fetchColumn());
        self::assertSame(4, (int) $this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE action='controlled_register.created'")->fetchColumn());
    }

    public function testTransitionsRequireConfirmationAndOptimisticVersion(): void
    {
        [$organisation, $user, $project] = $this->tenant();
        $service = new ControlledRegisterService($this->pdo, new ControlledRegisterPolicy());
        $entry = $service->create($organisation, $user, 'owner', $project, 'insurance_register', [
            'title' => 'Professional indemnity', 'reference' => 'PI-001', 'ownerRole' => 'architect',
            'dueOn' => '2027-01-01', 'details' => ['policyNumber' => 'HUMAN-ENTERED'],
            'evidenceFileIds' => [], 'humanConfirmed' => true,
        ]);

        $active = $service->transition($organisation, $user, 'owner', $entry['id'], [
            'status' => 'active', 'expectedVersion' => 1, 'reason' => 'Authorised person reviewed policy evidence.', 'humanConfirmed' => true,
        ]);
        self::assertSame('active', $active['status']);
        self::assertSame(2, $active['version']);

        $this->expectException(ControlledRegisterException::class);
        $this->expectExceptionMessage('controlled_register_version_conflict');
        $service->transition($organisation, $user, 'owner', $entry['id'], [
            'status' => 'expired', 'expectedVersion' => 1, 'reason' => 'Stale transition.', 'humanConfirmed' => true,
        ]);
    }

    /** @return array{string,string,string} */
    private function tenant(): array
    {
        $organisation = Identifier::generate(); $user = Identifier::generate(); $project = Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'register-owner@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$user]);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Register Org',:slug,'active')")->execute(['id'=>$organisation,'slug'=>'register-'.substr($organisation,0,8)]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'owner','active')")->execute(['id'=>Identifier::generate(),'o'=>$organisation,'u'=>$user]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:p),UNHEX(:o),'REG-1','Register Project','Client','Tshwane','Erf 1','Residential','','architect','construction_execution',UNHEX(:u))")->execute(['p'=>$project,'o'=>$organisation,'u'=>$user]);
        return [$organisation,$user,$project];
    }
}
