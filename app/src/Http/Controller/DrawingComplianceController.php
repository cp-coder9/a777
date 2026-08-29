<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\DrawingCompliance\DrawingComplianceException;
use Architex\Platform\DrawingCompliance\DrawingComplianceService;
use Architex\Platform\Identity\AuthenticatedIdentity;
use JsonException;

final readonly class DrawingComplianceController
{
    public function __construct(private DrawingComplianceService$service){}
    public function sources(Request$request):Response{return$this->read($request,fn(array$scope):array=>$this->service->sourceRegistry());}
    public function checklist(Request$request):Response{return$this->write($request,fn(array$body,array$scope):array=>$this->service->evaluate($body));}
    public function save(Request$request):Response{return$this->write($request,fn(array$body,array$scope):array=>$this->service->saveReview($scope[0],$scope[1],$body),201);}
    public function review(Request$request):Response{return$this->read($request,fn(array$scope):array=>$this->service->review($scope[0],$scope[1],$this->id($request)));}
    public function export(Request$request):Response{try{$scope=$this->scope($request);if($scope instanceof Response)return$scope;$file=$this->service->export($scope[0],$scope[1],$this->id($request));return Response::binary($file['content'],$file['mimeType'],['Content-Disposition'=>'attachment; filename="'.$file['filename'].'"','X-Content-Sha256'=>$file['sha256'],'Cache-Control'=>'private, no-store']);}catch(DrawingComplianceException$exception){return$this->error($exception);}}
    /** @param callable(array{string,string,string}):mixed$operation */private function read(Request$request,callable$operation):Response{$scope=$this->scope($request);if($scope instanceof Response)return$scope;try{return Response::success($operation($scope));}catch(DrawingComplianceException$exception){return$this->error($exception);}}
    /** @param callable(array<string,mixed>,array{string,string,string}):mixed$operation */private function write(Request$request,callable$operation,int$status=200):Response{$scope=$this->scope($request);if($scope instanceof Response)return$scope;try{return Response::success($operation($request->jsonBody(),$scope),status:$status);}catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(DrawingComplianceException$exception){return$this->error($exception);}}
    /** @return array{string,string,string}|Response */private function scope(Request$request):array|Response{$organisation=$request->attribute('organisationId');$identity=$request->attribute('identity');$role=$request->attribute('role');return is_string($organisation)&&$identity instanceof AuthenticatedIdentity&&is_string($role)?[$organisation,$identity->userId,$role]:Response::error(401,'authentication_required','Authentication is required.');}
    private function id(Request$request):string{$id=$request->attribute('reviewId');if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new DrawingComplianceException('drawing_compliance_review_not_found');return strtolower($id);}
    private function error(DrawingComplianceException$exception):Response{$reason=$exception->getMessage();$status=match($reason){'drawing_compliance_review_not_found','drawing_compliance_project_not_found'=>404,'drawing_compliance_membership_not_found'=>403,default=>422};return Response::error($status,$reason,match($reason){'drawing_compliance_review_not_found'=>'Drawing compliance review not found.','drawing_compliance_project_not_found'=>'Project not found in the active organisation.',default=>'The drawing compliance request could not be completed.'});}
}
