<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class EngineeringWorkspaceTest extends TestCase
{
    public function testCanonicalEngineeringHubUsesGovernedPersistedCalculationFlow():void
    {
        $shell=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/js/shell.js');$styles=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/css/architex-v1.css');
        foreach([
            'id="engineeringEvidenceWorkspace"','Controlled calculation record','Professional responsibility confirmation','current independently reviewed authority','Engineering run history','/toolbox/runs','/revisions','professionalConfirmation','responsibilityStatement','beam_quick_check','rational_runoff_calculator','voltage_drop_calculator','concrete_quantity_calculator','masonry_quantity_calculator','finishes_quantity_calculator','tender_rate_build_up_calculator','labour_productivity_calculator','TOOLS.engineering_calc.status = \'live\'',
        ]as$expected)self::assertStringContainsString($expected,$shell);
        self::assertStringContainsString('.engineering-evidence-workspace',$styles);
        self::assertStringContainsString('@media(max-width:760px)',$styles);
        $canonical=(string)file_get_contents(dirname(__DIR__,2).'/public/app.php');self::assertStringContainsString('TOOLS.engineering_calc={',$canonical);
        self::assertStringContainsString("if(state.tool==='engineering_calc'){q('#main').innerHTML=renderEngineering()",$canonical);
        self::assertStringNotContainsString("state.engineeringSaved=true;showToast(review?'Calculation record sent to professional review.'",$shell);
    }
}
