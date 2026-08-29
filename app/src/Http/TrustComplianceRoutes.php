<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\TrustComplianceController;

final readonly class TrustComplianceRoutes
{
    public function __construct(private TrustComplianceController$c,private Middleware$read,private Middleware$write,private Middleware$review){}
    public function register(Router$r):void
    {
        $r->get('/api/v1/projects/{projectId}/trust-compliance-workspace',fn(Request$q):Response=>$this->read->process($q,$this->c->workspace(...)));
        foreach([
            ['/api/v1/projects/{projectId}/trust/compliance-items','createItem'],
            ['/api/v1/projects/{projectId}/trust/processing-activities','createProcessingActivity'],
            ['/api/v1/projects/{projectId}/trust/data-subject-requests','createDataSubjectRequest'],
            ['/api/v1/projects/{projectId}/trust/breaches','reportBreach'],
        ]as[$path,$method])$r->post($path,fn(Request$q):Response=>$this->write->process($q,$this->c->{$method}(...)));
        foreach([
            ['/api/v1/trust/compliance-items/{itemId}/review','reviewItem'],
            ['/api/v1/trust/data-subject-requests/{requestId}/transitions','transitionDataSubjectRequest'],
            ['/api/v1/trust/breaches/{breachId}/transitions','transitionBreach'],
            ['/api/v1/trust/risks/{riskId}/resolve','resolveRisk'],
        ]as[$path,$method])$r->post($path,fn(Request$q):Response=>$this->review->process($q,$this->c->{$method}(...)));
    }
}
