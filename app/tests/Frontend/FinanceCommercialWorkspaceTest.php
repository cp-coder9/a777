<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class FinanceCommercialWorkspaceTest extends TestCase
{
    public function testFinanceScaffoldsUseTheGovernedMariaDbWorkspace(): void
    {
        $shell = file_get_contents(dirname(__DIR__, 2).'/public/assets/js/shell.js');
        self::assertIsString($shell);
        foreach ([
            'id="financeCommercialWorkspace"', 'Third-party provider boundary', 'fundsHeldByArchitex=false',
            '/finance-workspace', '/commercial-baselines', '/payment-schedules', '/variations', '/claims',
            '/certificates', '/financial-providers', '/release-requests', 'provider_configuration_required',
            'grossCertifiedToDateCents', 'retentionHeldToDateCents', 'previousCertifiedPaymentsCents',
            'disputedAmountCents', 'netPayableCents', 'No automatic release',
            '/cashflow-snapshots', '/retention-release-requests', 'certifiedAwaitingProviderPaymentCents',
            'defectsLiabilityCompleted', 'completionCertificateReference',
            'installProductionRenderLifecycle', 'productionRenderAll', 'syncProjectSelector(projects, session, inbox)',
            'name="name" autocomplete="organization"',
        ] as $expected) self::assertStringContainsString($expected, $shell);
        foreach (['valuations', 'contract_admin'] as $tool) {
            self::assertStringContainsString("TOOLS.$tool.status = 'live'", $shell);
        }
        self::assertStringContainsString("TOOLS.payments_escrow.status = 'integration_required'", $shell);
        self::assertStringContainsString("['payments_escrow','valuations','contract_admin'].includes(state.tool)", $shell);
        foreach (['client','architect','bep','engineer','quantity_surveyor','cpm','contractor','subcontractor','supplier','developer','firm_admin','admin'] as $role) {
            self::assertStringContainsString("'$role'", $shell);
        }
    }

    public function testSharedShellExposesReadableNavigationNamesAndContrastTokens(): void
    {
        $app = file_get_contents(dirname(__DIR__, 2).'/public/app.php');
        $css = file_get_contents(dirname(__DIR__, 2).'/public/assets/css/architex-v1.css');
        self::assertIsString($app);
        self::assertIsString($css);
        self::assertStringContainsString('<meta name="description" content="Architex OS project and standalone built-environment workflows with governed evidence and professional review gates.">', $app);
        $flightpath = file_get_contents(dirname(__DIR__, 2).'/public/assets/js/flightpath-shell.js');
        self::assertIsString($flightpath);
        self::assertStringContainsString('aria-label="${escapeHtml(destination.label)}"', $flightpath);
        self::assertStringContainsString('.kicker{font-size:9px;text-transform:uppercase;letter-spacing:.11em;color:#126965', $css);
        self::assertStringContainsString('.scope-pill{font-size:8px;text-transform:uppercase;letter-spacing:.08em;font-weight:800;padding:5px 8px;border-radius:99px;background:rgba(25,183,176,.08);color:#14756f}', $css);
        self::assertStringContainsString('.project-chip{font-size:9px;padding:6px 9px;border:1px solid rgba(25,183,176,.16);background:var(--aqua);border-radius:9px;color:#14756f', $css);
        self::assertStringContainsString('.inspect-block h4{font-size:8px;letter-spacing:.1em;text-transform:uppercase;color:#96a0ad', $css);
    }
}
