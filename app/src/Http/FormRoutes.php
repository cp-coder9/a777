<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\FormController;
use Architex\Http\Middleware\PermissionMiddleware;

final readonly class FormRoutes
{
    public function __construct(private FormController$controller,private PermissionMiddleware$read,private PermissionMiddleware$write){}
    public function register(Router$router):void
    {
        $router->get('/api/v1/form-templates',fn(Request$r):Response=>$this->read->process($r,$this->controller->templates(...)));
        $router->get('/api/v1/form-drafts',fn(Request$r):Response=>$this->read->process($r,$this->controller->drafts(...)));
        $router->post('/api/v1/form-drafts',fn(Request$r):Response=>$this->write->process($r,$this->controller->create(...)));
        $router->get('/api/v1/form-drafts/{draftId}',fn(Request$r):Response=>$this->read->process($r,$this->controller->draft(...)));
        $router->post('/api/v1/form-drafts/{draftId}/revisions',fn(Request$r):Response=>$this->write->process($r,$this->controller->revise(...)));
        $router->post('/api/v1/form-drafts/{draftId}/submit-review',fn(Request$r):Response=>$this->write->process($r,$this->controller->submit(...)));
        $router->post('/api/v1/form-drafts/{draftId}/approve',fn(Request$r):Response=>$this->write->process($r,$this->controller->approve(...)));
        $router->post('/api/v1/form-drafts/{draftId}/issue',fn(Request$r):Response=>$this->write->process($r,$this->controller->issue(...)));
        $router->get('/api/v1/form-drafts/{draftId}/exports',fn(Request$r):Response=>$this->read->process($r,$this->controller->exports(...)));
        $router->get('/api/v1/form-exports/{exportId}',fn(Request$r):Response=>$this->read->process($r,$this->controller->export(...)));
    }
}
