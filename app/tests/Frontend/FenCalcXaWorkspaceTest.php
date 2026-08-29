<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class FenCalcXaWorkspaceTest extends TestCase
{
    public function testFenCalcXaUsesTheNativeGovernedMultiElementWorkspace(): void
    {
        $shell=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/js/shell.js');
        foreach(['FenCalc XA','SANS 10400-XA:2021','SANS 10400-N minimum area checks','Add element','Load eight-element example','Element type','Room use','Openable area','Per-storey analysis','Orientation distribution','Overall glazing','Compliance summary','Print advisory report','fenCalcElements','fenCalcAddElement','fenCalcLoadExample','fenCalcPrintReport']as$text)self::assertStringContainsString($text,$shell);
        foreach(['fenestration','roof_lights','doors','curtain_wall','skylight','alu','thermal','timber','HORIZONTAL']as$value)self::assertStringContainsString($value,$shell);
        self::assertStringContainsString("input.elements=fenCalcElements.map",$shell);
        self::assertStringContainsString('professional sign-off',$shell);
    }
}
