<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class NuancedQcWorkspaceTest extends TestCase
{
    public function testV1ShellContainsResponsiveEvidenceLedQcWorkspace(): void
    {
        $shell = (string) file_get_contents(dirname(__DIR__, 2).'/public/assets/js/shell.js');
        $css = (string) file_get_contents(dirname(__DIR__, 2).'/public/assets/css/architex-v1.css');
        foreach (['nuancedQcWorkspace', 'Nuanced QC Control', '35 nuanced categories', 'Cross-pack speak-back', 'Risk acceptance', 'Retest checklist', 'AI may execute: no', '/nuanced-qc', 'acceptNuancedQcRisk', 'signOffNuancedQc'] as $token) self::assertStringContainsString($token, $shell);
        foreach (['.nq-workspace', '.nq-category-grid', '.nq-findings', '.nq-workspace [hidden]', '@media(max-width:760px)'] as $token) self::assertStringContainsString($token, $css);
    }
}
