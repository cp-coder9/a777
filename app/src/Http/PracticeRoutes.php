<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\PracticeController;

final readonly class PracticeRoutes
{
    public function __construct(private PracticeController$controller,private Middleware$read,private Middleware$write){}
    public function register(Router$r):void
    {
        $r->get('/api/v1/projects/{projectId}/practice-workspace',fn(Request$q):Response=>$this->read->process($q,$this->controller->workspace(...)));
        foreach([
            ['/api/v1/projects/{projectId}/practice/pipeline','createPipeline'],['/api/v1/practice/pipeline/{pipelineId}/transitions','transitionPipeline'],
            ['/api/v1/projects/{projectId}/practice/tasks','createTask'],['/api/v1/practice/tasks/{taskId}/transitions','transitionTask'],['/api/v1/projects/{projectId}/practice/resource-allocations','allocateResource'],
            ['/api/v1/projects/{projectId}/practice/timesheets','captureTimesheet'],['/api/v1/practice/timesheets/{timesheetId}/submit','submitTimesheet'],['/api/v1/practice/timesheets/{timesheetId}/review','reviewTimesheet'],
            ['/api/v1/projects/{projectId}/practice/supervision-reviews','createSupervision'],['/api/v1/practice/supervision-reviews/{reviewId}/sign-off','signOffSupervision'],
            ['/api/v1/projects/{projectId}/practice/invoice-readiness','assessInvoice'],['/api/v1/practice/registration-renewals','recordRegistration'],
            ['/api/v1/practice/templates','createTemplate'],['/api/v1/practice/templates/{templateId}/revisions','reviseTemplate'],
            ['/api/v1/projects/{projectId}/practice/programme','createProgramme'],['/api/v1/practice/programme/{programmeId}','updateProgramme'],
            ['/api/v1/projects/{projectId}/practice/milestones','createMilestone'],['/api/v1/projects/{projectId}/practice/milestones/{milestoneId}/completion','setMilestoneCompletion'],
            ['/api/v1/projects/{projectId}/practice/fees','saveFee'],
        ]as[$path,$method])$r->post($path,fn(Request$q):Response=>$this->write->process($q,$this->controller->{$method}(...)));
    }
}
