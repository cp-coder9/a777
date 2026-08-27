<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\AppointmentController;
use Architex\Http\Middleware\PermissionMiddleware;

final readonly class AppointmentRoutes
{
    public function __construct(private AppointmentController $controller,private PermissionMiddleware $read,private PermissionMiddleware $write){}
    public function register(Router $router):void
    {
        $router->get('/api/v1/appointments',fn(Request $r):Response=>$this->read->process($r,$this->controller->list(...)));
        $router->post('/api/v1/appointments',fn(Request $r):Response=>$this->write->process($r,$this->controller->create(...)));
        $router->get('/api/v1/appointments/{appointmentId}',fn(Request $r):Response=>$this->read->process($r,$this->controller->get(...)));
        $router->post('/api/v1/appointments/{appointmentId}/professional-confirmation',fn(Request $r):Response=>$this->write->process($r,$this->controller->confirm(...)));
        $router->post('/api/v1/appointments/{appointmentId}/issue',fn(Request $r):Response=>$this->write->process($r,$this->controller->issue(...)));
        $router->post('/api/v1/appointments/{appointmentId}/revisions',fn(Request $r):Response=>$this->write->process($r,$this->controller->revise(...)));
        $router->get('/api/v1/projects/{projectId}/kickoff-checklist',fn(Request $r):Response=>$this->read->process($r,$this->controller->projectKickoff(...)));
    }
}
