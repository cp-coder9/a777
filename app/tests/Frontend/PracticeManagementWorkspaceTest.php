<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class PracticeManagementWorkspaceTest extends TestCase
{
    public function testPracticeToolUsesTheMariaDbOfficeOpsWorkspace(): void
    {
        $shell=file_get_contents(dirname(__DIR__,2).'/public/assets/js/shell.js');self::assertIsString($shell);
        foreach(['id="practiceManagementWorkspace"','/practice-workspace','/practice/pipeline','/practice/tasks','/practice/resource-allocations','/practice/timesheets','/practice/supervision-reviews','/practice/invoice-readiness','/practice/registration-renewals','/practice/templates','practiceTransitionPipeline','practiceTransitionTask','practiceSignOffSupervision','practiceReviseTemplate','practiceCanGovern','does not create an invoice or move money','Registration reminders are not verification guarantees']as$text)self::assertStringContainsString($text,$shell);
        self::assertStringContainsString("TOOLS.practice.status = 'live'",$shell);
        self::assertStringContainsString("state.tool==='practice'",$shell);
    }
}
