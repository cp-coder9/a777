<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Freelancer\JobTicketMarketplaceException;
use Architex\Platform\Freelancer\JobTicketMarketplaceService;
use Architex\Platform\Identity\AuthenticatedIdentity;
use JsonException;

final readonly class JobTicketMarketplaceController
{
    public function __construct(private JobTicketMarketplaceService $service) {}

    public function discover(Request $request): Response
    {
        return $this->read($request, fn(array $scope): array => $this->service->discover($scope[0], $scope[1], $scope[2], [
            'location' => $request->query('location'),
            'category' => $request->query('category'),
        ]));
    }

    public function createTicket(Request $request): Response { return $this->write($request, fn(array $body,array $scope): Response => Response::success($this->service->createTicket($scope[0],$scope[1],$scope[2],is_string($body['projectId']??null)?$body['projectId']:null,$body),status:201)); }
    public function ticket(Request $request): Response { return $this->read($request,fn(array $scope):array=>$this->service->getTicket($scope[0],$scope[1],$scope[2],$this->id($request,'ticketId','job_ticket_not_found'))); }
    public function tree(Request $request): Response { return $this->read($request,fn(array $scope):array=>$this->service->ticketTree($scope[0],$scope[1],$scope[2],$this->id($request,'ticketId','job_ticket_not_found'))); }
    public function overrideClassification(Request $request): Response { return $this->write($request,fn(array $body,array $scope):Response=>Response::success($this->service->overrideClassification($scope[0],$scope[1],$scope[2],$this->id($request,'ticketId','job_ticket_not_found'),$body))); }
    public function clarify(Request $request): Response { return $this->write($request,fn(array $body,array $scope):Response=>Response::success($this->service->addClarification($scope[0],$scope[1],$scope[2],$this->id($request,'ticketId','job_ticket_not_found'),$body),status:201)); }
    public function quotations(Request $request): Response { return $this->read($request,fn(array $scope):array=>$this->service->listQuotations($scope[0],$scope[1],$scope[2],$this->id($request,'ticketId','job_ticket_not_found'),$request->query('includeWithdrawn')==='true')); }
    public function quote(Request $request): Response { return $this->write($request,fn(array $body,array $scope):Response=>Response::success($this->service->submitQuotation($scope[0],$scope[1],$scope[2],is_string($body['bidderRole']??null)?$body['bidderRole']:'',$this->id($request,'ticketId','job_ticket_not_found'),$body),status:201)); }
    public function withdrawQuote(Request $request): Response { return $this->write($request,fn(array $body,array $scope):Response=>Response::success($this->service->withdrawQuotation($scope[0],$scope[1],$scope[2],$this->id($request,'ticketId','job_ticket_not_found'),$this->id($request,'quotationId','job_ticket_quotation_not_found'),is_string($body['reason']??null)?$body['reason']:''))); }
    public function acceptQuote(Request $request): Response { return $this->write($request,fn(array $body,array $scope):Response=>Response::success($this->service->acceptQuotation($scope[0],$scope[1],$scope[2],$this->id($request,'ticketId','job_ticket_not_found'),$this->id($request,'quotationId','job_ticket_quotation_not_found'),$this->version($body),($body['humanConfirmed']??false)===true,is_string($body['reason']??null)?$body['reason']:''))); }
    public function acceptFreelancerQuote(Request $request): Response { return $this->write($request,fn(array $body,array $scope):Response=>Response::success($this->service->acceptFreelancerQuotation($scope[0],$scope[1],$scope[2],$this->id($request,'ticketId','job_ticket_not_found'),$this->id($request,'quotationId','job_ticket_quotation_not_found'),$this->version($body),$this->bodyId($body,'supervisorOrganisationId','freelancer_supervisor_required'),$this->bodyId($body,'supervisorUserId','freelancer_supervisor_required'),is_string($body['supervisorDiscipline']??null)?$body['supervisorDiscipline']:'',($body['humanConfirmed']??false)===true,is_string($body['reason']??null)?$body['reason']:''),status:201)); }
    public function expand(Request $request): Response { return $this->write($request,function(array $body,array $scope)use($request):Response{$payload=is_array($body['ticket']??null)?$body['ticket']:[];return Response::success($this->service->expandTicket($scope[0],$scope[1],$scope[2],$this->id($request,'ticketId','job_ticket_not_found'),$this->version($body),is_string($body['roleKey']??null)?$body['roleKey']:'',$payload),status:201);}); }
    public function upsertProfile(Request $request): Response { return $this->write($request,fn(array $body,array $scope):Response=>Response::success($this->service->upsertFreelancerProfile($scope[0],$scope[1],$scope[2],$body))); }
    public function publishProfile(Request $request): Response { return $this->write($request,fn(array $body,array $scope):Response=>Response::success($this->service->publishFreelancerProfile($scope[0],$scope[1],$scope[2],$this->version($body),($body['humanConfirmed']??false)===true,is_string($body['reason']??null)?$body['reason']:''))); }
    public function engagement(Request $request): Response { return $this->read($request,fn(array $scope):array=>$this->service->getEngagement($scope[0],$scope[1],$scope[2],$this->id($request,'engagementId','freelancer_engagement_not_found'))); }
    public function timesheet(Request $request): Response { return $this->write($request,fn(array $body,array $scope):Response=>Response::success($this->service->submitTimesheet($scope[0],$scope[1],$scope[2],$this->id($request,'engagementId','freelancer_engagement_not_found'),$body),status:201)); }
    public function reviewTimesheet(Request $request): Response { return $this->write($request,fn(array $body,array $scope):Response=>Response::success($this->service->reviewTimesheet($scope[0],$scope[1],$scope[2],$this->id($request,'engagementId','freelancer_engagement_not_found'),$this->id($request,'timesheetId','freelancer_timesheet_not_found'),is_string($body['decision']??null)?$body['decision']:'',is_string($body['reason']??null)?$body['reason']:''))); }
    public function claim(Request $request): Response { return $this->write($request,fn(array $body,array $scope):Response=>Response::success($this->service->submitClaim($scope[0],$scope[1],$scope[2],$this->id($request,'engagementId','freelancer_engagement_not_found'),$body),status:201)); }
    public function reviewClaim(Request $request): Response { return $this->write($request,fn(array $body,array $scope):Response=>Response::success($this->service->reviewClaim($scope[0],$scope[1],$scope[2],$this->id($request,'engagementId','freelancer_engagement_not_found'),$this->id($request,'claimId','freelancer_claim_not_found'),is_string($body['decision']??null)?$body['decision']:'',$body['approvedAmount']??null,is_string($body['reason']??null)?$body['reason']:''))); }
    public function paymentEvidence(Request $request): Response { return $this->write($request,fn(array $body,array $scope):Response=>Response::success($this->service->recordClaimPayment($scope[0],$scope[1],$scope[2],$this->id($request,'engagementId','freelancer_engagement_not_found'),$this->id($request,'claimId','freelancer_claim_not_found'),$body))); }
    public function deliverable(Request $request): Response { return $this->write($request,fn(array $body,array $scope):Response=>Response::success($this->service->submitDeliverable($scope[0],$scope[1],$scope[2],$this->id($request,'engagementId','freelancer_engagement_not_found'),$body),status:201)); }
    public function reviseDeliverable(Request $request): Response { return $this->write($request,fn(array $body,array $scope):Response=>Response::success($this->service->reviseDeliverable($scope[0],$scope[1],$scope[2],$this->id($request,'engagementId','freelancer_engagement_not_found'),$this->id($request,'deliverableId','freelancer_deliverable_not_found'),$this->version($body),$body),status:201)); }
    public function signOffDeliverable(Request $request): Response { return $this->write($request,fn(array $body,array $scope):Response=>Response::success($this->service->signOffDeliverable($scope[0],$scope[1],$scope[2],$this->id($request,'engagementId','freelancer_engagement_not_found'),$this->id($request,'deliverableId','freelancer_deliverable_not_found'),$this->id($request,'revisionId','freelancer_deliverable_revision_not_found'),is_string($body['decision']??null)?$body['decision']:'',is_string($body['declaration']??null)?$body['declaration']:''))); }
    public function issueDeliverable(Request $request): Response { return $this->write($request,fn(array $body,array $scope):Response=>Response::success($this->service->issueDeliverable($scope[0],$scope[1],$scope[2],$this->id($request,'engagementId','freelancer_engagement_not_found'),$this->id($request,'deliverableId','freelancer_deliverable_not_found'),($body['humanConfirmed']??false)===true,is_string($body['reason']??null)?$body['reason']:''))); }
    public function complete(Request $request): Response { return $this->write($request,fn(array $body,array $scope):Response=>Response::success($this->service->completeEngagement($scope[0],$scope[1],$scope[2],$this->id($request,'engagementId','freelancer_engagement_not_found'),$this->version($body),($body['humanAccepted']??false)===true,is_string($body['reason']??null)?$body['reason']:''))); }
    public function review(Request $request): Response { return $this->write($request,fn(array $body,array $scope):Response=>Response::success($this->service->createPerformanceReview($scope[0],$scope[1],$scope[2],$this->id($request,'engagementId','freelancer_engagement_not_found'),is_int($body['rating']??null)?$body['rating']:0,is_string($body['reviewText']??null)?$body['reviewText']:''),status:201)); }

    /** @param callable(array{string,string,string}):array<mixed> $operation */
    private function read(Request $request, callable $operation): Response
    {
        try { $scope=$this->scope($request); return $scope instanceof Response?$scope:Response::success($operation($scope)); }
        catch(JobTicketMarketplaceException $exception){ return $this->error($exception); }
    }

    /** @param callable(array<string,mixed>,array{string,string,string}):Response $operation */
    private function write(Request $request, callable $operation): Response
    {
        try { $scope=$this->scope($request); return $scope instanceof Response?$scope:$operation($request->jsonBody(),$scope); }
        catch(JsonException){ return Response::error(400,'invalid_json','Request body must contain valid JSON.'); }
        catch(JobTicketMarketplaceException $exception){ return $this->error($exception); }
    }

    /** @return array{string,string,string}|Response */
    private function scope(Request $request): array|Response
    {
        $organisation=$request->attribute('organisationId');$identity=$request->attribute('identity');$role=$request->attribute('role');
        return is_string($organisation)&&$identity instanceof AuthenticatedIdentity&&is_string($role)?[$organisation,$identity->userId,$role]:Response::error(401,'authentication_required','Authentication is required.');
    }

    private function id(Request $request,string $name,string $error): string { $id=$request->attribute($name); if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new JobTicketMarketplaceException($error); return strtolower($id); }
    /** @param array<string,mixed> $body */ private function bodyId(array $body,string $name,string $error): string { $id=$body[$name]??null;if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new JobTicketMarketplaceException($error);return strtolower($id); }
    /** @param array<string,mixed> $body */ private function version(array $body): int { $version=$body['expectedVersion']??null;if(!is_int($version)||$version<1)throw new JobTicketMarketplaceException('job_ticket_version_required');return $version; }

    private function error(JobTicketMarketplaceException $exception): Response
    {
        $reason=$exception->reason;
        $status=match(true){
            str_ends_with($reason,'_not_found')=>404,
            str_contains($reason,'permission_denied')||str_contains($reason,'_required')&&preg_match('/(poster|holder|authority|reviewer|supervisor|access)/',$reason)===1||str_contains($reason,'access_denied')=>403,
            str_contains($reason,'version_conflict')||str_contains($reason,'_exists')||str_contains($reason,'not_actionable')||str_contains($reason,'not_open')||str_contains($reason,'route_locked')||str_contains($reason,'not_active')=>409,
            str_contains($reason,'integrity_failed')=>503,
            default=>422,
        };
        return Response::error($status,$reason,'The governed job ticket request could not be completed.');
    }
}
