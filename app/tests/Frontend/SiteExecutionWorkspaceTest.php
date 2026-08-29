<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class SiteExecutionWorkspaceTest extends TestCase
{
    public function testSiteToolsUseOneProjectAwareMariaDbWorkspace(): void
    {
        $shell = file_get_contents(dirname(__DIR__, 2).'/public/assets/js/shell.js');
        self::assertIsString($shell);
        foreach ([
            'id="siteExecutionWorkspace"', '/site-execution-workspace', '/site-diary', '/site-evidence',
            '/rfis', '/site-instructions', '/ncrs', '/snags', '/inspections', '/delay-warnings',
            '/programme-impacts', '/payment-blockers', 'Daily site log', 'RFI register',
            'Site instructions', 'NCR and snag quality control', 'Delay early warnings',
            'No automatic EOT or variation entitlement', 'Internal inspection is not statutory approval',
            'Payment blockers are recommendations only', 'humanConfirmed', 'professionalSupervisorId',
        ] as $expected) self::assertStringContainsString($expected, $shell);
        foreach (['site_instructions','ncr_manager','snag_manager','issues_rfis','itp'] as $tool) {
            self::assertStringContainsString("TOOLS.$tool.status = 'live'", $shell);
        }
        self::assertStringContainsString("['site_diary','rfis','quality','issues_rfis','itp','site_instructions','ncr_manager','snag_manager'].includes(state.tool)", $shell);
        self::assertStringContainsString('localDateInputValue(now)', $shell);
        self::assertStringContainsString('localDateTimeInputValue(tomorrow)', $shell);
        self::assertStringNotContainsString("now.toISOString().slice(0,10)", $shell);
        self::assertStringNotContainsString("tomorrow.toISOString().slice(0,16)", $shell);
    }
}
