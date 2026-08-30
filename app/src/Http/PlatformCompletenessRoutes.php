<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\PlatformCompletenessController;

final readonly class PlatformCompletenessRoutes
{
    public function __construct(private PlatformCompletenessController$controller,private Middleware$read,private Middleware$write){}
    public function register(Router$router):void
    {
        $router->get('/api/v1/platform-completeness',fn(Request$r):Response=>$this->read->process($r,$this->controller->workspace(...)));
        $router->put('/api/v1/platform-completeness/items/{itemId}',fn(Request$r):Response=>$this->write->process($r,$this->controller->updateItem(...)));
        $router->put('/api/v1/platform-completeness/workstreams/{workstreamId}',fn(Request$r):Response=>$this->write->process($r,$this->controller->updateWorkstream(...)));
        $router->post('/api/v1/platform-completeness/items/{itemId}/reviews',fn(Request$r):Response=>$this->write->process($r,$this->controller->review(...)));
        $router->post('/api/v1/platform-completeness/items/{itemId}/evidence',fn(Request$r):Response=>$this->write->process($r,$this->controller->evidence(...)));
        $router->post('/api/v1/platform-completeness/items/{itemId}/uploads',fn(Request$r):Response=>$this->write->process($r,$this->controller->upload(...)));
        $router->post('/api/v1/platform-completeness/items/{itemId}/signoffs',fn(Request$r):Response=>$this->write->process($r,$this->controller->signoff(...)));
        $router->post('/api/v1/platform-completeness/workstreams/{workstreamId}/signoffs',fn(Request$r):Response=>$this->write->process($r,$this->controller->signoffWorkstream(...)));
        $router->put('/api/v1/platform-completeness/roles/{userId}',fn(Request$r):Response=>$this->write->process($r,$this->controller->assignRole(...)));
    }
}
