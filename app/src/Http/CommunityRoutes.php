<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\CommunityController;
use Architex\Http\Middleware\PermissionMiddleware;

final readonly class CommunityRoutes
{
    public function __construct(private CommunityController $controller, private PermissionMiddleware $read, private PermissionMiddleware $write) {}
    public function register(Router $router): void
    {
        $router->get('/api/v1/community/channels',fn(Request$r):Response=>$this->read->process($r,$this->controller->channels(...)));
        $router->post('/api/v1/community/channels',fn(Request$r):Response=>$this->write->process($r,$this->controller->saveChannel(...)));
        $router->put('/api/v1/community/channels/{channelId}',fn(Request$r):Response=>$this->write->process($r,$this->controller->updateChannel(...)));
        $router->put('/api/v1/community/channel-preference',fn(Request$r):Response=>$this->write->process($r,$this->controller->saveChannelPreference(...)));
        $router->get('/api/v1/community/preference',fn(Request$r):Response=>$this->read->process($r,$this->controller->preference(...)));
        $router->get('/api/v1/community/capabilities',fn(Request$r):Response=>$this->read->process($r,$this->controller->capabilities(...)));
        $router->get('/api/v1/community/notifications',fn(Request$r):Response=>$this->read->process($r,$this->controller->notifications(...)));
        $router->put('/api/v1/community/notifications/{notificationId}/read',fn(Request$r):Response=>$this->write->process($r,$this->controller->readNotification(...)));
        $router->put('/api/v1/community/preference',fn(Request$r):Response=>$this->write->process($r,$this->controller->savePreference(...)));
        $router->get('/api/v1/community/feed',fn(Request$r):Response=>$this->read->process($r,$this->controller->feed(...)));
        $router->post('/api/v1/community/threads',fn(Request$r):Response=>$this->write->process($r,$this->controller->create(...)));
        $router->get('/api/v1/community/threads/{threadId}',fn(Request$r):Response=>$this->read->process($r,$this->controller->thread(...)));
        $router->post('/api/v1/community/threads/{threadId}/replies',fn(Request$r):Response=>$this->write->process($r,$this->controller->reply(...)));
        $router->post('/api/v1/community/threads/{threadId}/reports',fn(Request$r):Response=>$this->write->process($r,$this->controller->report(...)));
        $router->put('/api/v1/community/threads/{threadId}/saved',fn(Request$r):Response=>$this->write->process($r,$this->controller->saveThread(...)));
        $router->get('/api/v1/community/moderation',fn(Request$r):Response=>$this->read->process($r,$this->controller->moderationQueue(...)));
        $router->post('/api/v1/community/threads/{threadId}/moderate',fn(Request$r):Response=>$this->write->process($r,$this->controller->moderate(...)));
        $router->post('/api/v1/community/threads/{threadId}/approve',fn(Request$r):Response=>$this->write->process($r,$this->controller->approve(...)));
        $router->post('/api/v1/community/threads/{threadId}/convert',fn(Request$r):Response=>$this->write->process($r,$this->controller->convert(...)));
    }
}
