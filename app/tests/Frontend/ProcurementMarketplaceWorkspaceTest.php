<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class ProcurementMarketplaceWorkspaceTest extends TestCase
{
    public function testRfqScaffoldIsConnectedToTheGovernedProcurementWorkflow(): void
    {
        $shell=file_get_contents(dirname(__DIR__,2).'/public/assets/js/shell.js');
        self::assertIsString($shell);
        foreach([
            'id="rfqMarketplaceWorkspace"','Fair tendering','No automatic award',
            '/marketplace/listings','/procurement-packages','/procurement-invitations/',
            'requiredReturnables','clarificationIds','conflictDeclared',
            'appointment_pending_formalisation','emailDeliveryClaimed','procurement_token',
        ]as$expected)self::assertStringContainsString($expected,$shell);
        self::assertStringContainsString("TOOLS.rfq_marketplace.status = 'live'",$shell);
        self::assertStringContainsString("state.tool==='rfq_marketplace'",$shell);
        self::assertStringContainsString("document.getElementById('main').innerHTML=`\${pageHead('RFQ Marketplace'",$shell);

        $app=file_get_contents(dirname(__DIR__,2).'/public/app.php');
        self::assertIsString($app);
        foreach(['quantity_surveyor','supplier']as$role){
            self::assertMatchesRegularExpression('/'.$role.":\\[[^\\]]*'rfq_marketplace'/",$app);
        }
        self::assertMatchesRegularExpression("/Procure:\\[[^\\]]*'rfq_marketplace'/",$app);
    }
}
