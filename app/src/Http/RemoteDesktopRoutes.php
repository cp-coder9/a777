<?php
declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\RemoteDesktopController;

final readonly class RemoteDesktopRoutes
{
    public function __construct(private RemoteDesktopController $controller,private Middleware $read,private Middleware $write,private Middleware $callbackLimit) {}
    public function register(Router $router): void
    {
        $router->get('/api/v1/projects/{projectId}/remote-desktop/capabilities',fn(Request $r): Response=>$this->read->process($r,$this->controller->capabilities(...)));
        $router->post('/api/v1/projects/{projectId}/remote-desktop/sessions',fn(Request $r): Response=>$this->write->process($r,$this->controller->create(...)));
        $router->get('/api/v1/remote-desktop/sessions/{sessionId}',fn(Request $r): Response=>$this->read->process($r,$this->controller->show(...)));
        $router->post('/api/v1/remote-desktop/sessions/{sessionId}/viewer-ticket',fn(Request $r): Response=>$this->write->process($r,$this->controller->ticket(...)));
        $router->post('/api/v1/remote-desktop/sessions/{sessionId}/close',fn(Request $r): Response=>$this->write->process($r,$this->controller->close(...)));
        $router->post('/api/v1/remote-desktop/cleanup/{organisationId}/{sessionId}',fn(Request $r): Response=>$this->controller->cleanup($r));
        $router->post('/api/v1/webhooks/remote-desktop/{organisationId}',fn(Request $r): Response=>$this->callbackLimit->process($r,$this->controller->callback(...)));
    }
}
