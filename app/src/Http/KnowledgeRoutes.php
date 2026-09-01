<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\KnowledgeController;

final readonly class KnowledgeRoutes
{
    public function __construct(private KnowledgeController $controller,private Middleware $read,private Middleware $write){}
    public function register(Router $router):void
    {
        $router->get('/api/v1/knowledge',fn(Request$r):Response=>$this->read->process($r,$this->controller->workspace(...)));
        $router->post('/api/v1/knowledge/sources',fn(Request$r):Response=>$this->write->process($r,$this->controller->create(...)));
        $router->post('/api/v1/knowledge/sources/{sourceId}/review',fn(Request$r):Response=>$this->write->process($r,$this->controller->review(...)));
    }
}
