<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Agent\AgentOrchestrationException;
use Architex\Platform\Agent\AgentOrchestrationService;
use Architex\Platform\Identity\AuthenticatedIdentity;
use JsonException;

final readonly class AgentOrchestrationController
{
    public function __construct(private AgentOrchestrationService $service) {}

    public function workspace(Request $request): Response { return $this->read($request, fn(array $scope) => $this->service->workspace($scope[0],$scope[1],$this->id($request,'projectId'))); }
    public function bootstrap(Request $request): Response { return $this->write($request, fn(array $body,array $scope) => $this->service->bootstrap($scope[0],$scope[1],$this->id($request,'projectId'),$body)); }
    public function createPolicy(Request $request): Response { return $this->write($request, fn(array $body,array $scope) => $this->service->createPolicy($scope[0],$scope[1],$scope[2],$body),201); }
    public function createRoute(Request $request): Response { return $this->write($request, fn(array $body,array $scope) => $this->service->createRoute($scope[0],$scope[1],$scope[2],$this->id($request,'projectId'),$body),201); }
    public function routeEvent(Request $request): Response { return $this->write($request, fn(array $body,array $scope) => $this->service->routeEvent($scope[0],$scope[1],$this->id($request,'projectId'),$body),201); }
    public function createMessageDraft(Request $request): Response { return $this->write($request, fn(array $body,array $scope) => $this->service->createMessageDraft($scope[0],$scope[1],$this->id($request,'projectId'),$body),201); }
    public function decideApproval(Request $request): Response { return $this->write($request, fn(array $body,array $scope) => $this->service->decideApproval($scope[0],$scope[1],$scope[2],$this->id($request,'gateId'),$body)); }

    private function read(Request$request,callable$callback):Response{try{$scope=$this->scope($request);return$scope instanceof Response?$scope:Response::success($callback($scope));}catch(AgentOrchestrationException$exception){return$this->error($exception);}}
    private function write(Request$request,callable$callback,int$status=200):Response{try{$scope=$this->scope($request);if($scope instanceof Response)return$scope;return Response::success($callback($request->jsonBody(),$scope),status:$status);}catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(AgentOrchestrationException$exception){return$this->error($exception);}}
    /** @return array{string,string,string}|Response */ private function scope(Request$request):array|Response{$organisation=$request->attribute('organisationId');$identity=$request->attribute('identity');$role=$request->attribute('role');return is_string($organisation)&&$identity instanceof AuthenticatedIdentity&&is_string($role)?[$organisation,$identity->userId,$role]:Response::error(401,'authentication_required','Authentication is required.');}
    private function id(Request$request,string$name):string{$id=$request->attribute($name);if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new AgentOrchestrationException('agent_record_not_found');return strtolower($id);}
    private function error(AgentOrchestrationException$exception):Response{$status=match(true){str_ends_with($exception->reason,'_not_found')=>404,str_ends_with($exception->reason,'_permission_denied')||str_ends_with($exception->reason,'_forbidden')=>403,str_ends_with($exception->reason,'_immutable')=>409,default=>422};return Response::error($status,$exception->reason,'The agent orchestration request could not be completed.');}
}
