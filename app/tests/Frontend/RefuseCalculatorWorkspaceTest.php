<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class RefuseCalculatorWorkspaceTest extends TestCase
{
    public function testRefuseScaffoldIsConnectedToTheGovernedCalculatorWorkflow(): void
    {
        $shell=file_get_contents(dirname(__DIR__,2).'/public/assets/js/shell.js');
        self::assertIsString($shell);
        foreach(['id="refuseWorkspace"','Municipal Refuse Storage Pre-check','municipal_refuse','refuse_area_calculator','collectionIntervalDays','proposedStorageAreaM2','collectionVehicleAccess','professionalConfirmation','/compliance-rule-versions/seed','/toolbox/runs','No municipal approval']as$expected)self::assertStringContainsString($expected,$shell);
        self::assertStringContainsString("TOOLS.refuse_calculator.status = 'live'",$shell);
        self::assertStringContainsString("state.tool==='refuse_calculator'",$shell);

        $app=file_get_contents(dirname(__DIR__,2).'/public/app.php');
        self::assertIsString($app);
        self::assertMatchesRegularExpression("/engineer:\\[[^\\]]*'refuse_calculator'/",$app);
        self::assertMatchesRegularExpression("/Design:\\[[^\\]]*'refuse_calculator'/",$app);
        self::assertMatchesRegularExpression("/Comply:\\[[^\\]]*'refuse_calculator'/",$app);
    }
}
