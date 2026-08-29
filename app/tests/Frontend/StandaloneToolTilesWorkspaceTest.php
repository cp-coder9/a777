<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class StandaloneToolTilesWorkspaceTest extends TestCase
{
    public function testToolRegistryHasProductionStandaloneTilesSearchAssignmentAndHistory(): void
    {
        $shell=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/js/shell.js');
        foreach(['AI-guided','All tools','Search standalone tools','Category','Use standalone','Assign to project','External / offline project','Recent standalone runs','boq_takeoff','site_diary_entry','professional_fee_calculator','fenestration_xa_calculator','r_value_thermal_calculator','/toolbox/definitions','/toolbox/runs','/assignment','Evidence exports','No project context required']as$text)self::assertStringContainsString($text,$shell);
        foreach(['fee_calculator','compliance','estimating','site_management','document_control','admin_governance']as$category)self::assertStringContainsString($category,$shell);
        self::assertStringContainsString("state.global==='tools'",$shell);
        self::assertStringContainsString('type="button" onclick="standaloneAssignRun', $shell);
        self::assertStringContainsString('type="button" onclick="loadComplianceExports', $shell);
    }
}
