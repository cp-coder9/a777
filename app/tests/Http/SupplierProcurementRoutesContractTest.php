<?php

declare(strict_types=1);

namespace Architex\Tests\Http;

use PHPUnit\Framework\TestCase;

final class SupplierProcurementRoutesContractTest extends TestCase
{
    public function testGovernedAndSupplierPortalRoutesAreRegistered():void
    {
        $routes=(string)file_get_contents(dirname(__DIR__,2).'/src/Http/SupplierProcurementRoutes.php');
        foreach(["get('/api/v1/projects/{projectId}/supplier-procurement'","post('/api/v1/projects/{projectId}/supplier-procurement/suppliers'","post('/api/v1/supplier-procurement/suppliers/{supplierId}/catalogues'","post('/api/v1/projects/{projectId}/supplier-procurement/baselines'","post('/api/v1/supplier-procurement/baselines/{baselineId}/approve'","post('/api/v1/supplier-procurement/baselines/{baselineId}/packages'","post('/api/v1/supplier-procurement/packages/{packageId}/matches'","post('/api/v1/supplier-procurement/packages/{packageId}/rfqs'","post('/api/v1/supplier-procurement/rfqs/{rfqId}/revoke'","post('/api/v1/supplier-procurement/matches/{matchId}/substitution-decision'","post('/api/v1/supplier-procurement/packages/{packageId}/award'","post('/api/v1/supplier-procurement/packages/{packageId}/purchase-orders'","post('/api/v1/supplier-procurement/purchase-orders/{purchaseOrderId}/deliveries'","post('/api/v1/supplier-procurement/deliveries/{deliveryId}/site-acceptance'","post('/api/v1/supplier-procurement/purchase-orders/{purchaseOrderId}/invoices'","post('/api/v1/supplier-procurement/invoices/{invoiceId}/payment-recommendation'","post('/api/v1/supplier-rfqs/{token}/quotes'","post('/api/v1/supplier-rfqs/{token}/purchase-order-acceptance'"]as$route)self::assertStringContainsString($route,$routes);
        $factory=(string)file_get_contents(dirname(__DIR__,2).'/src/Http/AuthenticationKernelFactory.php');self::assertStringContainsString('new SupplierProcurementRoutes',$factory);
    }
}
