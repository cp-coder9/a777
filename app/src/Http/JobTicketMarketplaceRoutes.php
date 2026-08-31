<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\JobTicketMarketplaceController;
use Architex\Http\Middleware\PermissionMiddleware;

final readonly class JobTicketMarketplaceRoutes
{
    public function __construct(private JobTicketMarketplaceController $controller,private PermissionMiddleware $read,private PermissionMiddleware $write,private PermissionMiddleware $govern) {}

    public function register(Router $router): void
    {
        $read=fn(callable $next):callable=>fn(Request $request):Response=>$this->read->process($request,$next);
        $write=fn(callable $next):callable=>fn(Request $request):Response=>$this->write->process($request,$next);
        $govern=fn(callable $next):callable=>fn(Request $request):Response=>$this->govern->process($request,$next);
        $router->get('/api/v1/job-tickets',$read($this->controller->discover(...)));
        $router->post('/api/v1/job-tickets',$write($this->controller->createTicket(...)));
        $router->get('/api/v1/job-tickets/{ticketId}',$read($this->controller->ticket(...)));
        $router->get('/api/v1/job-tickets/{ticketId}/tree',$read($this->controller->tree(...)));
        $router->post('/api/v1/job-tickets/{ticketId}/classification-override',$govern($this->controller->overrideClassification(...)));
        $router->post('/api/v1/job-tickets/{ticketId}/clarifications',$write($this->controller->clarify(...)));
        $router->post('/api/v1/job-tickets/{ticketId}/quotations',$write($this->controller->quote(...)));
        $router->get('/api/v1/job-tickets/{ticketId}/quotations',$read($this->controller->quotations(...)));
        $router->post('/api/v1/job-tickets/{ticketId}/quotations/{quotationId}/withdraw',$write($this->controller->withdrawQuote(...)));
        $router->post('/api/v1/job-tickets/{ticketId}/quotations/{quotationId}/accept',$govern($this->controller->acceptQuote(...)));
        $router->post('/api/v1/job-tickets/{ticketId}/freelancer-quotations/{quotationId}/accept',$govern($this->controller->acceptFreelancerQuote(...)));
        $router->post('/api/v1/job-tickets/{ticketId}/expansions',$write($this->controller->expand(...)));
        $router->put('/api/v1/freelancers/me',$write($this->controller->upsertProfile(...)));
        $router->get('/api/v1/freelancers/me',$read($this->controller->profile(...)));
        $router->post('/api/v1/freelancers/me/publish',$govern($this->controller->publishProfile(...)));
        $router->get('/api/v1/freelancer-engagements',$read($this->controller->engagements(...)));
        $router->get('/api/v1/freelancer-engagements/{engagementId}',$read($this->controller->engagement(...)));
        $router->post('/api/v1/freelancer-engagements/{engagementId}/timesheets',$write($this->controller->timesheet(...)));
        $router->post('/api/v1/freelancer-engagements/{engagementId}/timesheets/{timesheetId}/review',$govern($this->controller->reviewTimesheet(...)));
        $router->post('/api/v1/freelancer-engagements/{engagementId}/claims',$write($this->controller->claim(...)));
        $router->post('/api/v1/freelancer-engagements/{engagementId}/claims/{claimId}/review',$govern($this->controller->reviewClaim(...)));
        $router->post('/api/v1/freelancer-engagements/{engagementId}/claims/{claimId}/payment-evidence',$govern($this->controller->paymentEvidence(...)));
        $router->post('/api/v1/freelancer-engagements/{engagementId}/deliverables',$write($this->controller->deliverable(...)));
        $router->post('/api/v1/freelancer-engagements/{engagementId}/deliverables/{deliverableId}/revisions',$write($this->controller->reviseDeliverable(...)));
        $router->post('/api/v1/freelancer-engagements/{engagementId}/deliverables/{deliverableId}/revisions/{revisionId}/sign-off',$govern($this->controller->signOffDeliverable(...)));
        $router->post('/api/v1/freelancer-engagements/{engagementId}/deliverables/{deliverableId}/issue',$govern($this->controller->issueDeliverable(...)));
        $router->post('/api/v1/freelancer-engagements/{engagementId}/complete',$govern($this->controller->complete(...)));
        $router->post('/api/v1/freelancer-engagements/{engagementId}/reviews',$govern($this->controller->review(...)));
    }
}
