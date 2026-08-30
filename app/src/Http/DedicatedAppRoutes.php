<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\DedicatedAppController;

final readonly class DedicatedAppRoutes
{
    public function __construct(private DedicatedAppController$controller,private Middleware$read,private Middleware$write,private Middleware$clientLimit){}
    public function register(Router$router):void
    {
        $router->get('/api/v1/dedicated-apps',fn(Request$r):Response=>$this->read->process($r,$this->controller->catalogue(...)));
        $router->get('/api/v1/dedicated-apps/installations',fn(Request$r):Response=>$this->read->process($r,$this->controller->installations(...)));
        $router->get('/api/v1/dedicated-apps/events',fn(Request$r):Response=>$this->read->process($r,$this->controller->events(...)));
        $router->get('/api/v1/dedicated-apps/sync-conflicts',fn(Request$r):Response=>$this->read->process($r,$this->controller->conflicts(...)));
        $router->post('/api/v1/dedicated-apps/installations',fn(Request$r):Response=>$this->write->process($r,$this->controller->register(...)));
        $router->post('/api/v1/dedicated-apps/events',fn(Request$r):Response=>$this->write->process($r,$this->controller->ingest(...)));
        $router->post('/api/v1/dedicated-apps/events/{eventId}/confirm',fn(Request$r):Response=>$this->write->process($r,$this->controller->confirm(...)));
        $router->post('/api/v1/dedicated-apps/sync',fn(Request$r):Response=>$this->write->process($r,$this->controller->sync(...)));
        $router->post('/api/v1/dedicated-app-client/heartbeat',fn(Request$r):Response=>$this->clientLimit->process($r,$this->controller->clientHeartbeat(...)));
        $router->post('/api/v1/dedicated-app-client/events',fn(Request$r):Response=>$this->clientLimit->process($r,$this->controller->clientEvent(...)));
        $router->post('/api/v1/dedicated-app-client/sync',fn(Request$r):Response=>$this->clientLimit->process($r,$this->controller->clientSync(...)));
    }
}
