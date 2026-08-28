<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\ProcurementController;
use Architex\Http\Middleware\PermissionMiddleware;

final readonly class ProcurementRoutes
{
    public function __construct(private ProcurementController$controller,private PermissionMiddleware$read,private PermissionMiddleware$write,private Middleware$publicLimit){}
    public function register(Router$router):void
    {
        $router->get('/api/v1/marketplace/listings',$this->controller->marketplace(...));
        $router->get('/api/v1/marketplace/listings/mine',fn(Request$r):Response=>$this->read->process($r,$this->controller->myListings(...)));
        $router->post('/api/v1/marketplace/listings',fn(Request$r):Response=>$this->write->process($r,$this->controller->createListing(...)));
        $router->post('/api/v1/marketplace/listings/{listingId}/publish',fn(Request$r):Response=>$this->write->process($r,$this->controller->publishListing(...)));
        $router->post('/api/v1/projects/{projectId}/procurement-packages',fn(Request$r):Response=>$this->write->process($r,$this->controller->createPackage(...)));
        $router->get('/api/v1/projects/{projectId}/procurement-packages',fn(Request$r):Response=>$this->read->process($r,$this->controller->packages(...)));
        $router->get('/api/v1/procurement-packages/{packageId}/workspace',fn(Request$r):Response=>$this->read->process($r,$this->controller->workspace(...)));
        $router->get('/api/v1/procurement-packages/{packageId}',fn(Request$r):Response=>$this->read->process($r,$this->controller->showPackage(...)));
        $router->post('/api/v1/procurement-packages/{packageId}/issue',fn(Request$r):Response=>$this->write->process($r,$this->controller->issuePackage(...)));
        $router->post('/api/v1/procurement-packages/{packageId}/matches',fn(Request$r):Response=>$this->write->process($r,$this->controller->matches(...)));
        $router->post('/api/v1/procurement-packages/{packageId}/invitations',fn(Request$r):Response=>$this->write->process($r,$this->controller->invite(...)));
        $router->post('/api/v1/procurement-packages/{packageId}/addenda',fn(Request$r):Response=>$this->write->process($r,$this->controller->addendum(...)));
        $router->post('/api/v1/procurement-packages/{packageId}/comparisons',fn(Request$r):Response=>$this->write->process($r,$this->controller->compare(...)));
        $router->post('/api/v1/procurement-packages/{packageId}/award-recommendations',fn(Request$r):Response=>$this->write->process($r,$this->controller->recommend(...)));
        $router->post('/api/v1/procurement-award-recommendations/{recommendationId}/approvals',fn(Request$r):Response=>$this->write->process($r,$this->controller->approve(...)));
        $router->post('/api/v1/procurement-award-recommendations/{recommendationId}/appointment-placeholder',fn(Request$r):Response=>$this->write->process($r,$this->controller->finalise(...)));
        $router->get('/api/v1/procurement-invitations/{token}',fn(Request$r):Response=>$this->publicLimit->process($r,$this->controller->invitation(...)));
        $router->post('/api/v1/procurement-invitations/{token}/accept',fn(Request$r):Response=>$this->publicLimit->process($r,$this->controller->acceptInvitation(...)));
        $router->post('/api/v1/procurement-invitations/{token}/clarifications',fn(Request$r):Response=>$this->publicLimit->process($r,$this->controller->clarification(...)));
        $router->post('/api/v1/procurement-invitations/{token}/quotes',fn(Request$r):Response=>$this->publicLimit->process($r,$this->controller->quote(...)));
    }
}
