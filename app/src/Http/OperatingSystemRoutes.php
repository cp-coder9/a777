<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\OperatingSystemController;

final readonly class OperatingSystemRoutes
{
    public function __construct(private OperatingSystemController$controller,private Middleware$read,private Middleware$write,private Middleware$govern){}

    public function register(Router$router):void
    {
        $router->get('/api/v1/projects/{projectId}/operating-snapshot',fn(Request$r):Response=>$this->read->process($r,$this->controller->snapshot(...)));
        $router->get('/api/v1/mobile-decision-inbox',fn(Request$r):Response=>$this->read->process($r,$this->controller->mobileInbox(...)));
        $router->post('/api/v1/inbox/{inboxItemId}/transition',fn(Request$r):Response=>$this->write->process($r,$this->controller->transitionInbox(...)));
        $router->get('/api/v1/api-capabilities',fn(Request$r):Response=>$this->read->process($r,$this->controller->capabilityReport(...)));
        $router->get('/api/v1/release-gates',fn(Request$r):Response=>$this->read->process($r,$this->controller->releaseGateHistory(...)));
        $router->post('/api/v1/release-gates',fn(Request$r):Response=>$this->govern->process($r,$this->controller->recordReleaseGate(...)));
    }
}
