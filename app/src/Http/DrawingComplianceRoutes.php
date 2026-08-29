<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\DrawingComplianceController;
use Architex\Http\Middleware\PermissionMiddleware;

final readonly class DrawingComplianceRoutes
{
    public function __construct(private DrawingComplianceController$controller,private PermissionMiddleware$read,private PermissionMiddleware$write){}
    public function register(Router$router):void
    {
        $router->get('/api/v1/drawing-compliance/sources',fn(Request$r):Response=>$this->read->process($r,$this->controller->sources(...)));
        $router->post('/api/v1/drawing-compliance/checklist',fn(Request$r):Response=>$this->read->process($r,$this->controller->checklist(...)));
        $router->post('/api/v1/drawing-compliance/reviews',fn(Request$r):Response=>$this->write->process($r,$this->controller->save(...)));
        $router->get('/api/v1/drawing-compliance/reviews/{reviewId}',fn(Request$r):Response=>$this->read->process($r,$this->controller->review(...)));
        $router->get('/api/v1/drawing-compliance/reviews/{reviewId}/export',fn(Request$r):Response=>$this->read->process($r,$this->controller->export(...)));
    }
}
