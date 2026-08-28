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
        ] as $expected) self::assertStringContainsString($expected, $shell);
        foreach (['payments_escrow', 'valuations', 'contract_admin'] as $tool) {
            self::assertStringContainsString("TOOLS.$tool.status = 'live'", $shell);
        }
        self::assertStringContainsString("['payments_escrow','valuations','contract_admin'].includes(state.tool)", $shell);
        foreach (['client','architect','bep','engineer','quantity_surveyor','cpm','contractor','subcontractor','supplier','developer','firm_admin','admin'] as $role) {
            self::assertStringContainsString("'$role'", $shell);
        }
    }
}
