<?php

declare(strict_types=1);
namespace Architex\Http;

use Architex\Http\Controller\WorkPermitController;

/** Register with existing projects.read/projects.write PermissionMiddleware. */
final readonly class WorkPermitRoutes
{
    public function __construct(private WorkPermitController $controller,private Middleware $read,private Middleware $write) {}
    public function register(Router $r): void
    {
        $base='/api/v1/projects/{projectId}/work-permits';
        $r->get($base,fn(Request $q):Response=>$this->read->process($q,fn(Request $q):Response=>$this->controller->handle($q,'index')));
        $r->get($base.'/{permitId}',fn(Request $q):Response=>$this->read->process($q,fn(Request $q):Response=>$this->controller->handle($q,'show')));
        $r->post($base,fn(Request $q):Response=>$this->write->process($q,fn(Request $q):Response=>$this->controller->handle($q,'create')));
        $r->post($base.'/{permitId}/transition',fn(Request $q):Response=>$this->write->process($q,fn(Request $q):Response=>$this->controller->handle($q,'transition')));
    }
}