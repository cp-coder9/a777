<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\CommunicationController;
use Architex\Http\Middleware\PermissionMiddleware;

final readonly class CommunicationRoutes
{
    public function __construct(private CommunicationController$controller,private PermissionMiddleware$read,private PermissionMiddleware$write){}
    public function register(Router$router):void
    {
        $router->get('/api/v1/communication-threads',fn(Request$r):Response=>$this->read->process($r,$this->controller->threads(...)));
        $router->post('/api/v1/communication-threads',fn(Request$r):Response=>$this->write->process($r,$this->controller->createThread(...)));
        $router->get('/api/v1/communication-threads/{threadId}',fn(Request$r):Response=>$this->read->process($r,$this->controller->getThread(...)));
        $router->post('/api/v1/communication-threads/{threadId}/drafts',fn(Request$r):Response=>$this->write->process($r,$this->controller->draft(...)));
        $router->get('/api/v1/communication-threads/{threadId}/messages',fn(Request$r):Response=>$this->read->process($r,$this->controller->messages(...)));
        $router->post('/api/v1/communication-threads/{threadId}/read',fn(Request$r):Response=>$this->read->process($r,$this->controller->markRead(...)));
        $router->post('/api/v1/communications/messages/{messageId}/send',fn(Request$r):Response=>$this->write->process($r,$this->controller->send(...)));
        $router->post('/api/v1/communications/messages/{messageId}/convert',fn(Request$r):Response=>$this->write->process($r,$this->controller->convert(...)));
        $router->get('/api/v1/projects/{projectId}/communication-assistant',fn(Request$r):Response=>$this->read->process($r,$this->controller->assistant(...)));
        $router->get('/api/v1/projects/{projectId}/communication-actions',fn(Request$r):Response=>$this->read->process($r,$this->controller->actions(...)));
    }
}
