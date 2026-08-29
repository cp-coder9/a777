<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\CloseoutController;

final readonly class CloseoutRoutes
{
    public function __construct(private CloseoutController$controller,private Middleware$read,private Middleware$write){}
    public function register(Router$r):void
    {
        $r->get('/api/v1/projects/{projectId}/closeout-workspace',fn(Request$q):Response=>$this->read->process($q,$this->controller->workspace(...)));
        foreach([
            ['/api/v1/projects/{projectId}/practical-completions','assessPracticalCompletion'],['/api/v1/practical-completions/{completionId}/issue','issuePracticalCompletion'],['/api/v1/practical-completions/{completionId}/accept','acceptPracticalCompletion'],
            ['/api/v1/projects/{projectId}/handover-packs','createHandoverPack'],['/api/v1/handover-packs/{packId}/documents','addHandoverDocument'],['/api/v1/handover-packs/{packId}/refresh','refreshHandoverPack'],['/api/v1/handover-packs/{packId}/issue','issueHandoverPack'],
            ['/api/v1/projects/{projectId}/occupation-readiness','assessOccupation'],['/api/v1/occupation-readiness/{readinessId}/review','reviewOccupation'],['/api/v1/projects/{projectId}/final-account-readiness','assessFinalAccount'],
            ['/api/v1/projects/{projectId}/defects-liability-periods','createLiabilityPeriod'],['/api/v1/projects/{projectId}/closeout-defects','reportDefect'],['/api/v1/closeout-defects/{defectId}/recall','recallContractor'],['/api/v1/closeout-defects/{defectId}/corrective-actions','submitDefectCorrection'],['/api/v1/closeout-defects/{defectId}/verify','verifyDefect'],['/api/v1/defects-liability-periods/{periodId}/close','closeLiabilityPeriod'],
        ]as[$path,$method])$r->post($path,fn(Request$q):Response=>$this->write->process($q,$this->controller->{$method}(...)));
    }
}
