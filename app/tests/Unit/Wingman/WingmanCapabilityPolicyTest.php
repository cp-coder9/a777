<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\Wingman;

use Architex\Platform\Wingman\WingmanCapabilityPolicy;
use Architex\Platform\Wingman\WingmanException;
use PHPUnit\Framework\TestCase;

final class WingmanCapabilityPolicyTest extends TestCase
{
    public function testEveryProfileIsCheckedAgainstEveryCapability(): void
    {
        $roles = ['architect','bep','engineer','contractor','quantity_surveyor','town_planner','energy_professional','fire_engineer','site_manager','developer','freelancer','subcontractor','supplier','client','firm_admin','land_surveyor','cpm','health_safety'];
        $rules = [
            'summarise_status' => $roles, 'flag_risk' => $roles, 'explain_clause' => $roles,
            'draft_rfi' => ['architect','bep','engineer','site_manager','contractor','quantity_surveyor'],
            'draft_site_instruction' => ['architect','bep','engineer','site_manager','contractor','quantity_surveyor'],
            'flag_compliance' => ['architect','bep','engineer','energy_professional','fire_engineer','town_planner'],
            'generate_narrative' => ['architect','bep','engineer','quantity_surveyor','town_planner'],
            'summarise_financials' => ['architect','bep','quantity_surveyor','contractor','client','firm_admin'],
        ];
        foreach ($rules as $capability => $allowed) {
            foreach ($roles as $role) {
                try {
                    WingmanCapabilityPolicy::assertAllowed($capability, $role, true);
                    self::assertContains($role, $allowed, "$role should not access $capability");
                } catch (WingmanException $exception) {
                    self::assertSame('capability_forbidden', $exception->reason);
                    self::assertNotContains($role, $allowed, "$role should access $capability");
                }
            }
        }
    }

    public function testMissingAndAdministrativeRolesAndNoProjectFailClosed(): void
    {
        foreach ([null, 'platform_admin', 'organisation_admin', 'admin', 'unknown'] as $role) {
            try {
                WingmanCapabilityPolicy::assertAllowed('explain_clause', $role, false);
                self::fail('Non-professional role was accepted');
            } catch (WingmanException $exception) {
                self::assertSame('professional_role_required', $exception->reason);
            }
        }
        WingmanCapabilityPolicy::assertAllowed('explain_clause', 'architect', false);
        try {
            WingmanCapabilityPolicy::assertAllowed('summarise_status', 'architect', false);
            self::fail('Project capability was allowed without a project');
        } catch (WingmanException $exception) {
            self::assertSame('capability_project_required', $exception->reason);
        }
        try {
            WingmanCapabilityPolicy::assertAllowed('unknown', 'architect', true);
            self::fail('Unknown capability was accepted');
        } catch (WingmanException $exception) {
            self::assertSame('capability_unrecognized', $exception->reason);
        }
    }
}
