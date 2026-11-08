<?php

declare(strict_types=1);

namespace Architex\Platform\Wingman;

/** Professional capability eligibility; membership permissions are checked separately. */
final class WingmanCapabilityPolicy
{
    private const PROFESSIONAL_ROLES = [
        'architect', 'bep', 'engineer', 'contractor', 'quantity_surveyor',
        'town_planner', 'energy_professional', 'fire_engineer', 'site_manager',
        'developer', 'freelancer', 'subcontractor', 'supplier', 'client', 'firm_admin',
        // Additional professional profiles offered by this PHP application.
        'land_surveyor', 'cpm', 'health_safety',
    ];
    private const ACCESS = [
        'summarise_status' => null,
        'flag_risk' => null,
        'explain_clause' => null,
        'draft_rfi' => ['architect', 'bep', 'engineer', 'site_manager', 'contractor', 'quantity_surveyor'],
        'draft_site_instruction' => ['architect', 'bep', 'engineer', 'site_manager', 'contractor', 'quantity_surveyor'],
        'flag_compliance' => ['architect', 'bep', 'engineer', 'energy_professional', 'fire_engineer', 'town_planner'],
        'generate_narrative' => ['architect', 'bep', 'engineer', 'quantity_surveyor', 'town_planner'],
        'summarise_financials' => ['architect', 'bep', 'quantity_surveyor', 'contractor', 'client', 'firm_admin'],
    ];

    public static function assertAllowed(mixed $capability, ?string $professionalRole, bool $hasProject): void
    {
        if (!is_string($capability) || !array_key_exists($capability, self::ACCESS)) {
            throw new WingmanException('capability_unrecognized');
        }
        if ($professionalRole === null || !in_array($professionalRole, self::PROFESSIONAL_ROLES, true)) {
            throw new WingmanException('professional_role_required');
        }
        $roles = self::ACCESS[$capability];
        if ($roles !== null && !in_array($professionalRole, $roles, true)) {
            throw new WingmanException('capability_forbidden');
        }
        if (!$hasProject && $capability !== 'explain_clause') {
            throw new WingmanException('capability_project_required');
        }
    }
}
