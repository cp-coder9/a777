<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Identity\AuthenticatedIdentity;
use Architex\Platform\Practice\PracticeException;
use Architex\Platform\Practice\PracticeManagementService;
use JsonException;

final readonly class PracticeController
{
    public function __construct(private PracticeManagementService$service){}
    public function workspace(Request$r):Response{return$this->read($r,fn($s)=>$this->service->workspace($s[0],$s[1],$this->id($r,'projectId')));}
    public function createPipeline(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->createPipelineItem($s[0],$s[1],$this->id($r,'projectId'),$b),201);}
    public function transitionPipeline(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->transitionPipelineItem($s[0],$s[1],$this->id($r,'pipelineId'),$b));}
    public function createTask(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->createTask($s[0],$s[1],$this->id($r,'projectId'),$b),201);}
    public function transitionTask(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->transitionTask($s[0],$s[1],$s[2],$this->id($r,'taskId'),$b));}
    public function allocateResource(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->allocateResource($s[0],$s[1],$this->id($r,'projectId'),$b),201);}
    public function captureTimesheet(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->captureTimesheet($s[0],$s[1],$this->id($r,'projectId'),$b),201);}
    public function submitTimesheet(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->submitTimesheet($s[0],$s[1],$this->id($r,'timesheetId'),$b));}
    public function reviewTimesheet(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->reviewTimesheet($s[0],$s[1],$s[2],$this->id($r,'timesheetId'),$b));}
    public function createSupervision(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->createSupervisionReview($s[0],$s[1],$this->id($r,'projectId'),$b),201);}
    public function signOffSupervision(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->signOffSupervision($s[0],$s[1],$s[2],$this->id($r,'reviewId'),$b));}
    public function assessInvoice(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->assessInvoiceReadiness($s[0],$s[1],$this->id($r,'projectId'),$b),201);}
    public function recordRegistration(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->recordRegistrationRenewal($s[0],$s[1],$s[2],$b),201);}
    public function createTemplate(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->createTemplate($s[0],$s[1],$s[2],$b),201);}
    public function reviseTemplate(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->reviseTemplate($s[0],$s[1],$s[2],$this->id($r,'templateId'),$b),201);}
    private function read(Request$r,callable$f):Response{try{$s=$this->scope($r);return$s instanceof Response?$s:Response::success($f($s));}catch(PracticeException$e){return$this->error($e);}}
    private function write(Request$r,callable$f,int$status=200):Response{try{$s=$this->scope($r);if($s instanceof Response)return$s;return Response::success($f($r->jsonBody(),$s),status:$status);}catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(PracticeException$e){return$this->error($e);}}
    /** @return array{string,string,string}|Response */ private function scope(Request$r):array|Response{$o=$r->attribute('organisationId');$i=$r->attribute('identity');$role=$r->attribute('role');return is_string($o)&&$i instanceof AuthenticatedIdentity&&is_string($role)?[$o,$i->userId,$role]:Response::error(401,'authentication_required','Authentication is required.');}
    private function id(Request$r,string$name):string{$id=$r->attribute($name);if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new PracticeException('practice_record_not_found');return strtolower($id);}
    private function error(PracticeException$e):Response{$status=match(true){str_ends_with($e->reason,'_not_found')||$e->reason==='practice_project_not_found'=>404,str_ends_with($e->reason,'_forbidden')||$e->reason==='practice_membership_required'||$e->reason==='practice_registered_professional_required'=>403,str_contains($e->reason,'transition_invalid')||str_contains($e->reason,'immutable')=>409,default=>422};return Response::error($status,$e->reason,'The practice-management request could not be completed.');}
}
