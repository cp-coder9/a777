<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\Demo;

use Architex\Platform\Demo\DemoException;
use Architex\Platform\Demo\DemoRoleLensRegistry;
use PHPUnit\Framework\TestCase;

final class DemoRoleLensRegistryTest extends TestCase
{
    private const EXPECTED_ROLES = [
        'client', 'project_manager', 'architect', 'architectural_technologist', 'candidate_architect',
        'engineer_structural', 'engineer_civil', 'engineer_electrical', 'engineer_mechanical', 'engineer_fire',
        'quantity_surveyor', 'contractor', 'subcontractor', 'supplier', 'energy_consultant', 'town_planner',
        'environmental_consultant', 'accessibility_consultant', 'bep', 'freelancer', 'cpd_officer', 'admin',
    ];

    public function testRegistryDeclaresAllRoleLensesWithServerOwnedProjections(): void
    {
        $roles = (new DemoRoleLensRegistry())->all();

        self::assertSame(self::EXPECTED_ROLES, array_keys($roles));
        self::assertCount(22, array_unique(array_column($roles, 'key')));
        self::assertCount(7, array_unique(array_column($roles, 'group')));
        $canonicalRoles = [
            'client', 'architect', 'bep', 'engineer', 'quantity_surveyor', 'town_planner', 'energy_professional',
            'fire_engineer', 'cpm', 'contractor', 'subcontractor', 'supplier', 'freelancer', 'firm_admin', 'admin',
        ];
        foreach ($roles as $key => $role) {
            self::assertSame($key, $role['key']);
            self::assertNotSame('', $role['label']);
            self::assertNotSame('', $role['focus']);
            self::assertContains($role['canonicalRole'], $canonicalRoles);
            self::assertNotEmpty($role['toolCategories']);
            self::assertNotEmpty($role['projectKeys']);
            self::assertSame($role['toolCategories'], array_values(array_unique($role['toolCategories'])));
            self::assertSame($role['projectKeys'], array_values(array_unique($role['projectKeys'])));
        }
    }

    public function testUnknownRoleFailsClosed(): void
    {
        $this->expectException(DemoException::class);
        $this->expectExceptionMessage('demo_role_invalid');
        (new DemoRoleLensRegistry())->get('platform_admin');
    }
}
