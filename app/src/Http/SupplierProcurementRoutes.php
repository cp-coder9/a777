<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\SupplierProcurementController;
use Architex\Http\Middleware\PermissionMiddleware;

final readonly class SupplierProcurementRoutes
{
    public function __construct(private SupplierProcurementController$controller,private PermissionMiddleware$read,private PermissionMiddleware$write,private Middleware$publicLimit){}
    public function register(Router$router):void
    {
        $router->get('/api/v1/projects/{projectId}/supplier-procurement',fn(Request$r):Response=>$this->read->process($r,$this->controller->workspace(...)));
        $router->post('/api/v1/projects/{projectId}/supplier-procurement/suppliers',fn(Request$r):Response=>$this->write->process($r,$this->controller->registerSupplier(...)));
        $router->post('/api/v1/supplier-procurement/suppliers/{supplierId}/catalogues',fn(Request$r):Response=>$this->write->process($r,$this->controller->importCatalogue(...)));
        $router->post('/api/v1/projects/{projectId}/supplier-procurement/baselines',fn(Request$r):Response=>$this->write->process($r,$this->controller->createBaseline(...)));
        $router->post('/api/v1/supplier-procurement/baselines/{baselineId}/approve',fn(Request$r):Response=>$this->write->process($r,$this->controller->approveBaseline(...)));
        $router->post('/api/v1/supplier-procurement/baselines/{baselineId}/packages',fn(Request$r):Response=>$this->write->process($r,$this->controller->buildPackage(...)));
        $router->post('/api/v1/supplier-procurement/packages/{packageId}/matches',fn(Request$r):Response=>$this->write->process($r,$this->controller->matchCatalogue(...)));
        $router->post('/api/v1/supplier-procurement/packages/{packageId}/rfqs',fn(Request$r):Response=>$this->write->process($r,$this->controller->issueRfq(...)));
        $router->post('/api/v1/supplier-procurement/rfqs/{rfqId}/revoke',fn(Request$r):Response=>$this->write->process($r,$this->controller->revokeRfq(...)));
        $router->post('/api/v1/supplier-procurement/matches/{matchId}/substitution-decision',fn(Request$r):Response=>$this->write->process($r,$this->controller->decideSubstitution(...)));
        $router->post('/api/v1/supplier-procurement/packages/{packageId}/award',fn(Request$r):Response=>$this->write->process($r,$this->controller->approveAward(...)));
        $router->post('/api/v1/supplier-procurement/packages/{packageId}/purchase-orders',fn(Request$r):Response=>$this->write->process($r,$this->controller->issuePurchaseOrder(...)));
        $router->post('/api/v1/supplier-procurement/purchase-orders/{purchaseOrderId}/deliveries',fn(Request$r):Response=>$this->write->process($r,$this->controller->recordDelivery(...)));
        $router->post('/api/v1/supplier-procurement/deliveries/{deliveryId}/site-acceptance',fn(Request$r):Response=>$this->write->process($r,$this->controller->recordSiteAcceptance(...)));
        $router->post('/api/v1/supplier-procurement/purchase-orders/{purchaseOrderId}/invoices',fn(Request$r):Response=>$this->write->process($r,$this->controller->captureInvoice(...)));
        $router->post('/api/v1/supplier-procurement/invoices/{invoiceId}/payment-recommendation',fn(Request$r):Response=>$this->write->process($r,$this->controller->recommendPayment(...)));
        $router->get('/api/v1/supplier-rfqs/{token}',fn(Request$r):Response=>$this->publicLimit->process($r,$this->controller->viewRfq(...)));
        $router->post('/api/v1/supplier-rfqs/{token}/quotes',fn(Request$r):Response=>$this->publicLimit->process($r,$this->controller->submitQuote(...)));
        $router->post('/api/v1/supplier-rfqs/{token}/purchase-order-acceptance',fn(Request$r):Response=>$this->publicLimit->process($r,$this->controller->acceptPurchaseOrder(...)));
    }
}
