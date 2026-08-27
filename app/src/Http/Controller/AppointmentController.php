<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Appointment\AppointmentException;
use Architex\Platform\Appointment\AppointmentService;
use Architex\Platform\Identity\AuthenticatedIdentity;
use JsonException;

final readonly class AppointmentController
{
    public function __construct(private AppointmentService $appointments) {}

    public function list(Request $request):Response{$scope=$this->scope($request);return $scope instanceof Response?$scope:Response::success($this->appointments->list($scope[0],$scope[1]));}
    public function get(Request $request):Response{$scope=$this->scope($request);if($scope instanceof Response)return $scope;try{return Response::success($this->appointments->get($scope[0],$scope[1],$this->id($request,'appointmentId')));}catch(AppointmentException $e){return $this->error($e);}}
    public function projectKickoff(Request $request):Response{$scope=$this->scope($request);if($scope instanceof Response)return $scope;try{return Response::success($this->appointments->forProject($scope[0],$scope[1],$this->id($request,'projectId')));}catch(AppointmentException $e){return $this->error($e);}}
    public function create(Request $request):Response{return $this->write($request,function(array $body,array $scope):Response{$proposalId=is_string($body['proposalId']??null)?$body['proposalId']:'';$facts=is_array($body['projectFacts']??null)?$body['projectFacts']:[];return Response::success($this->appointments->createFromAcceptedProposal($scope[0],$scope[1],$scope[2],$proposalId,$facts),status:201);});}
    public function confirm(Request $request):Response{return $this->write($request,fn(array $body,array $scope):Response=>Response::success($this->appointments->confirmProfessional($scope[0],$scope[1],$scope[2],$this->id($request,'appointmentId'),$body)));}
    public function issue(Request $request):Response{return $this->write($request,fn(array $body,array $scope):Response=>Response::success($this->appointments->issueFormalAppointment($scope[0],$scope[1],$scope[2],$this->id($request,'appointmentId'),$body)));}
    public function revise(Request $request):Response{return $this->write($request,function(array $body,array $scope):Response{$proposalId=is_string($body['proposalId']??null)?$body['proposalId']:'';$facts=is_array($body['projectFacts']??null)?$body['projectFacts']:[];$reason=is_string($body['reason']??null)?$body['reason']:'';return Response::success($this->appointments->reviseFromAcceptedProposal($scope[0],$scope[1],$scope[2],$this->id($request,'appointmentId'),$proposalId,$facts,$reason),status:201);});}

    /** @param callable(array<string,mixed>,array{string,string,string}):Response $operation */
    private function write(Request $request,callable $operation):Response{try{$scope=$this->scope($request);if($scope instanceof Response)return $scope;return $operation($request->jsonBody(),$scope);}catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(AppointmentException $e){return $this->error($e);}}
    /** @return array{string,string,string}|Response */
    private function scope(Request $request):array|Response{$organisationId=$request->attribute('organisationId');$identity=$request->attribute('identity');$role=$request->attribute('role');return is_string($organisationId)&&$identity instanceof AuthenticatedIdentity&&is_string($role)?[$organisationId,$identity->userId,$role]:Response::error(401,'authentication_required','Authentication is required.');}
    private function id(Request $request,string $name):string{$id=$request->attribute($name);if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new AppointmentException('appointment_not_found');return strtolower($id);}
    private function error(AppointmentException $e):Response{$status=match($e->reason){'appointment_not_found','accepted_proposal_required'=>404,'appointment_permission_denied'=>403,'appointment_already_exists'=>409,default=>422};return Response::error($status,$e->reason,'The appointment request could not be completed.');}
}
