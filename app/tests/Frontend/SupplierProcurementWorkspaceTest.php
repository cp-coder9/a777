<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class SupplierProcurementWorkspaceTest extends TestCase
{
    public function testV1ShellContainsResponsiveSupplierLifecycleWorkspace():void
    {
        $shell=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/js/shell.js');$css=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/css/architex-v1.css');
        foreach(['supplierProcurementWorkspace','Supplier Procurement','Approved baseline','Catalogue & supplier capability','Advisory matching','RFQ & quote revisions','Award & purchase order','Delivery & site acceptance','Payment recommendation','AI recommends; humans approve','integration_required','/supplier-procurement','public-supplier-rfq','purchase-order-acceptance','revokeSupplierRfq','enforceSupplierLifecycleActionVisibility']as$token)self::assertStringContainsString($token,$shell);
        foreach(['.sp-workspace','.sp-lifecycle','.sp-summary','.sp-workspace [hidden]','@media(max-width:760px)']as$token)self::assertStringContainsString($token,$css);
    }
}
