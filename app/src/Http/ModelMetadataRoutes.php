<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\ModelMetadataController;
use Architex\Http\Middleware\PermissionMiddleware;

final readonly class ModelMetadataRoutes
{
    public function __construct(private ModelMetadataController$controller,private PermissionMiddleware$read,private PermissionMiddleware$write){}
    public function register(Router$router):void
    {
        $router->get('/api/v1/projects/{projectId}/model-sources',fn(Request$r):Response=>$this->read->process($r,$this->controller->index(...)));
        $router->post('/api/v1/projects/{projectId}/model-sources',fn(Request$r):Response=>$this->write->process($r,$this->controller->create(...)));
        $router->get('/api/v1/model-sources/{sourceId}',fn(Request$r):Response=>$this->read->process($r,$this->controller->show(...)));
        $router->post('/api/v1/model-sources/{sourceId}/revisions',fn(Request$r):Response=>$this->write->process($r,$this->controller->revision(...)));
        $router->post('/api/v1/model-metadata-revisions/{revisionId}/decisions',fn(Request$r):Response=>$this->write->process($r,$this->controller->decision(...)));
        $router->post('/api/v1/model-sources/{sourceId}/issue',fn(Request$r):Response=>$this->write->process($r,$this->controller->issue(...)));
    }
}
