<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\MeetingController;
use Architex\Http\Middleware\PermissionMiddleware;

final readonly class MeetingRoutes
{
    public function __construct(private MeetingController $controller,private PermissionMiddleware $read,private PermissionMiddleware $write){}
    public function register(Router $router):void
    {
        $router->get('/api/v1/meetings',fn(Request $r):Response=>$this->read->process($r,$this->controller->list(...)));
        $router->post('/api/v1/meetings',fn(Request $r):Response=>$this->write->process($r,$this->controller->schedule(...)));
        $router->get('/api/v1/meetings/{meetingId}',fn(Request $r):Response=>$this->read->process($r,$this->controller->get(...)));
        $router->post('/api/v1/meetings/{meetingId}/consents',fn(Request $r):Response=>$this->write->process($r,$this->controller->consent(...)));
        $router->post('/api/v1/meetings/{meetingId}/start',fn(Request $r):Response=>$this->write->process($r,$this->controller->start(...)));
        $router->post('/api/v1/meetings/{meetingId}/conclude',fn(Request $r):Response=>$this->write->process($r,$this->controller->conclude(...)));
        $router->post('/api/v1/meetings/{meetingId}/outcomes/{outcomeIndex}/review',fn(Request $r):Response=>$this->write->process($r,$this->controller->reviewOutcome(...)));
        $router->post('/api/v1/meetings/{meetingId}/minutes/publish',fn(Request $r):Response=>$this->write->process($r,$this->controller->publish(...)));
        $router->post('/api/v1/meetings/{meetingId}/minutes/corrections',fn(Request $r):Response=>$this->write->process($r,$this->controller->correct(...)));
    }
}
