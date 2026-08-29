<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\AnalyticsReportingController;

final readonly class AnalyticsReportingRoutes
{
    public function __construct(private AnalyticsReportingController$c,private Middleware$read,private Middleware$write,private Middleware$govern){}
    public function register(Router$r):void
    {
        $r->get('/api/v1/projects/{projectId}/analytics-workspace',fn(Request$q):Response=>$this->read->process($q,$this->c->workspace(...)));
        $r->post('/api/v1/analytics/formulas/baseline',fn(Request$q):Response=>$this->govern->process($q,$this->c->baseline(...)));
        $r->post('/api/v1/analytics/formulas',fn(Request$q):Response=>$this->govern->process($q,$this->c->formula(...)));
        $r->post('/api/v1/projects/{projectId}/analytics/kpis/refresh',fn(Request$q):Response=>$this->write->process($q,$this->c->refresh(...)));
        $r->get('/api/v1/projects/{projectId}/analytics/dashboard/{dashboardRole}',fn(Request$q):Response=>$this->read->process($q,$this->c->dashboard(...)));
        $r->post('/api/v1/projects/{projectId}/analytics/dashboard-layouts',fn(Request$q):Response=>$this->govern->process($q,$this->c->layout(...)));
        $r->post('/api/v1/projects/{projectId}/analytics/alert-rules',fn(Request$q):Response=>$this->govern->process($q,$this->c->rule(...)));
        $r->post('/api/v1/projects/{projectId}/analytics/alerts/evaluate',fn(Request$q):Response=>$this->write->process($q,$this->c->evaluate(...)));
        $r->post('/api/v1/analytics/alerts/{alertId}/acknowledge',fn(Request$q):Response=>$this->write->process($q,$this->c->acknowledge(...)));
        $r->post('/api/v1/analytics/observability',fn(Request$q):Response=>$this->govern->process($q,$this->c->observability(...)));
        $r->get('/api/v1/projects/{projectId}/analytics/exports/{exportType}/{format}',fn(Request$q):Response=>$this->read->process($q,$this->c->export(...)));
    }
}
