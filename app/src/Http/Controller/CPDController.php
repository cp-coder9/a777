<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\CPD\CPDAssessmentException;
use Architex\Platform\CPD\CPDAssessmentService;
use Architex\Platform\Identity\AuthenticatedIdentity;
use JsonException;

final readonly class CPDController
{
    public function __construct(private CPDAssessmentService $service,private string $verificationBaseUrl='') {}

    public function workspace(Request $request):Response{return$this->read($request,fn($scope)=>$this->service->workspace($scope[0],$scope[1],$scope[2]));}
    public function professionalBodies(Request $request):Response{return$this->read($request,fn($scope)=>$this->service->professionalBodyRules());}
    public function saveProfile(Request $request):Response{return$this->write($request,fn($body,$scope)=>$this->service->saveProfile($scope[0],$scope[1],$scope[2],$body));}
    public function createContent(Request $request):Response{return$this->write($request,fn($body,$scope)=>$this->service->createContent($scope[0],$scope[1],$scope[2],$body),201);}
    public function createAssessment(Request $request):Response{return$this->write($request,fn($body,$scope)=>$this->service->createAssessmentDraft($scope[0],$scope[1],$scope[2],$this->bodyId($body,'contentId','cpd_content_not_found'),$body),201);}
    public function reviewAssessment(Request $request):Response{return$this->write($request,fn($body,$scope)=>$this->service->reviewAssessment($scope[0],$scope[1],$scope[2],$this->pathId($request,'assessmentId','cpd_assessment_not_found'),$body));}
    public function createAccreditation(Request $request):Response{return$this->write($request,fn($body,$scope)=>$this->service->createAccreditationApplication($scope[0],$scope[1],$scope[2],$this->bodyId($body,'assessmentId','cpd_assessment_not_found'),$body),201);}
    public function decideAccreditation(Request $request):Response{return$this->write($request,fn($body,$scope)=>$this->service->decideAccreditation($scope[0],$scope[1],$scope[2],$this->pathId($request,'applicationId','cpd_accreditation_not_found'),$body));}
    public function publishCourse(Request $request):Response{return$this->write($request,fn($body,$scope)=>$this->service->publishCourse($scope[0],$scope[1],$scope[2],$this->bodyId($body,'applicationId','cpd_accreditation_not_found'),$body),201);}
    public function createManualEntitlement(Request $request):Response{return$this->write($request,fn($body,$scope)=>$this->service->createManualEntitlement($scope[0],$scope[1],$scope[2],$this->bodyId($body,'courseId','cpd_course_not_found'),$body),201);}
    public function reviewEntitlement(Request $request):Response{return$this->write($request,fn($body,$scope)=>$this->service->reviewManualEntitlement($scope[0],$scope[1],$scope[2],$this->pathId($request,'entitlementId','cpd_entitlement_not_found'),$body));}
    public function configureCommercialSettings(Request $request):Response{return$this->write($request,fn($body,$scope)=>$this->service->configureCommercialSettings($scope[0],$scope[1],$scope[2],$body));}
    public function captureReferral(Request $request):Response{return$this->write($request,fn($body,$scope)=>$this->service->captureReferral($scope[0],$scope[1],$body),201);}
    public function startAttempt(Request $request):Response{return$this->write($request,fn($body,$scope)=>$this->service->startAttempt($scope[0],$scope[1],$scope[2],$this->pathId($request,'courseId','cpd_course_not_found')),201);}
    public function submitAttempt(Request $request):Response{return$this->write($request,fn($body,$scope)=>$this->service->submitAttempt($scope[0],$scope[1],$scope[2],$this->pathId($request,'attemptId','cpd_attempt_not_found'),$body));}
    public function reviewAttempt(Request $request):Response{return$this->write($request,fn($body,$scope)=>$this->service->reviewAttempt($scope[0],$scope[1],$scope[2],$this->pathId($request,'attemptId','cpd_attempt_not_found'),$body));}
    public function issueCertificate(Request $request):Response{return$this->write($request,fn($body,$scope)=>$this->service->issueCertificate($scope[0],$scope[1],$scope[2],$this->pathId($request,'attemptId','cpd_attempt_not_found'),$this->verificationBaseUrl),201);}
    public function downloadCertificate(Request $request):Response{try{$scope=$this->scope($request);if($scope instanceof Response)return$scope;$file=$this->service->certificatePdf($scope[0],$scope[1],$scope[2],$this->pathId($request,'certificateId','cpd_certificate_not_found'));return Response::binary($file['content'],$file['mimeType'],['Content-Disposition'=>'attachment; filename="'.addcslashes($file['filename'],'"\\').'"','Content-Length'=>(string)strlen($file['content']),'X-Content-Sha256'=>$file['sha256'],'Cache-Control'=>'private, no-store']);}catch(CPDAssessmentException$exception){return$this->error($exception);}}
    public function verifyCertificate(Request $request):Response{try{$code=$request->attribute('verificationCode');if(!is_string($code))throw new CPDAssessmentException('cpd_certificate_not_found');return Response::success($this->service->verifyCertificate(strtoupper($code)));}catch(CPDAssessmentException$exception){return$this->error($exception);}}

    private function read(Request$request,callable$callback):Response{try{$scope=$this->scope($request);return$scope instanceof Response?$scope:Response::success($callback($scope));}catch(CPDAssessmentException$exception){return$this->error($exception);}}
    private function write(Request$request,callable$callback,int$status=200):Response{try{$scope=$this->scope($request);if($scope instanceof Response)return$scope;return Response::success($callback($request->jsonBody(),$scope),status:$status);}catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(CPDAssessmentException$exception){return$this->error($exception);}}
    /** @return array{string,string,string}|Response */ private function scope(Request$request):array|Response{$organisation=$request->attribute('organisationId');$identity=$request->attribute('identity');$role=$request->attribute('role');return is_string($organisation)&&$identity instanceof AuthenticatedIdentity&&is_string($role)?[$organisation,$identity->userId,$role]:Response::error(401,'authentication_required','Authentication is required.');}
    /** @param array<string,mixed> $body */ private function bodyId(array$body,string$key,string$error):string{$id=$body[$key]??null;if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new CPDAssessmentException($error);return strtolower($id);}
    private function pathId(Request$request,string$key,string$error):string{$id=$request->attribute($key);if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new CPDAssessmentException($error);return strtolower($id);}
    private function error(CPDAssessmentException$exception):Response{$status=match(true){str_ends_with($exception->reason,'_not_found')=>404,str_ends_with($exception->reason,'_forbidden')||str_ends_with($exception->reason,'_membership_required')=>403,$exception->reason==='cpd_entitlement_required'=>402,str_ends_with($exception->reason,'_integration_required')=>503,str_contains($exception->reason,'_state_invalid')||str_ends_with($exception->reason,'_immutable')||str_ends_with($exception->reason,'_already_exists')||str_ends_with($exception->reason,'_already_published')=>409,default=>422};return Response::error($status,$exception->reason,'The CPD request could not be completed.');}
}
