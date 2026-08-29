<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class AnalyticsReportingWorkspaceTest extends TestCase
{
    public function testMarketInsightsOwnsTheProductionAnalyticsWorkspace(): void
    {
        $shell=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/js/shell.js');
        foreach(['Analytics & Reporting','Five governed KPIs','Role dashboard','Alert rules','Alert acknowledgements','Scoped exports','Platform health','schedule_variance_days','cost_to_complete_cents','defect_liability_remaining_days','retention_release_readiness_percent','compliance_gap_count','analytics-reporting-workspace','market_insights','advisoryOnly','formalApprovalChanged']as$text)self::assertStringContainsString($text,$shell);
        foreach(['/analytics/formulas/baseline','/analytics/kpis/refresh','/analytics/dashboard-layouts','/analytics/alert-rules','/analytics/alerts/evaluate','/analytics/alerts/','/analytics/observability','/analytics/exports/']as$route)self::assertStringContainsString($route,$shell);
        self::assertStringContainsString("state.tool==='market_insights'",$shell);
        self::assertStringNotContainsString('firebase',$shell);
    }
}
