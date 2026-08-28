<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\SiteExecutionController;

final readonly class SiteExecutionRoutes
{
    public function __construct(private SiteExecutionController $controller,private Middleware $read,private Middleware $write){}
    public function register(Router$r):void
    {
        $read=fn(Request$q):Response=>$this->read->process($q,$this->controller->workspace(...));
        $r->get('/api/v1/projects/{projectId}/site-execution-workspace',$read);
        $this->post($r,'/api/v1/projects/{projectId}/site-diary','createDailyLog');
        $this->post($r,'/api/v1/projects/{projectId}/site-evidence','captureEvidence');
        $this->post($r,'/api/v1/projects/{projectId}/rfis','createRfi');
        $this->post($r,'/api/v1/rfis/{rfiId}/responses','respondRfi');
        $this->post($r,'/api/v1/rfis/{rfiId}/close','closeRfi');
        $this->post($r,'/api/v1/projects/{projectId}/rfis/mark-overdue','markOverdueRfis');
        $this->post($r,'/api/v1/projects/{projectId}/site-instructions','createInstruction');
        $this->post($r,'/api/v1/site-instructions/{instructionId}/issue','issueInstruction');
        $this->post($r,'/api/v1/site-instructions/{instructionId}/acknowledge','acknowledgeInstruction');
        $this->post($r,'/api/v1/site-instructions/{instructionId}/supersede','supersedeInstruction');
        $this->post($r,'/api/v1/projects/{projectId}/ncrs','createNcr');
        $this->post($r,'/api/v1/ncrs/{ncrId}/corrective-actions','submitNcrCorrectiveAction');
        $this->post($r,'/api/v1/ncrs/{ncrId}/decisions','decideNcr');
        $this->post($r,'/api/v1/projects/{projectId}/snags','createSnag');
        $this->post($r,'/api/v1/snags/{snagId}/allocate','allocateSnag');
        $this->post($r,'/api/v1/snags/{snagId}/ready-for-reinspection','markSnagReady');
        $this->post($r,'/api/v1/snags/{snagId}/decisions','decideSnag');
        $this->post($r,'/api/v1/projects/{projectId}/inspections','createInspection');
        $this->post($r,'/api/v1/inspections/{inspectionId}/issue','issueInspection');
        $this->post($r,'/api/v1/inspections/{inspectionId}/close','closeInspection');
        $this->post($r,'/api/v1/projects/{projectId}/delay-warnings','createDelayWarning');
        $this->post($r,'/api/v1/delay-warnings/{warningId}/transitions','transitionDelayWarning');
        $this->post($r,'/api/v1/projects/{projectId}/programme-impacts','assessProgrammeImpact');
        $this->post($r,'/api/v1/projects/{projectId}/payment-blockers','createPaymentBlocker');
        $this->post($r,'/api/v1/payment-blockers/{blockerId}/clear','clearPaymentBlocker');
    }
    private function post(Router$r,string$path,string$method):void{$r->post($path,fn(Request$q):Response=>$this->write->process($q,$this->controller->{$method}(...)));}
}
