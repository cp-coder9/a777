<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Closeout\CloseoutException;
use Architex\Platform\Closeout\CloseoutService;
use Architex\Platform\Identity\AuthenticatedIdentity;
use JsonException;

final readonly class CloseoutController
{
    public function __construct(private CloseoutService $service) {}
    public function workspace(Request$r):Response{return$this->read($r,fn($s)=>$this->service->workspace($s[0],$s[1],$this->id($r,'projectId')));}
    public function assessPracticalCompletion(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->assessPracticalCompletion($s[0],$s[1],$s[2],$this->id($r,'projectId'),$b),201);}
    public function issuePracticalCompletion(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->issuePracticalCompletion($s[0],$s[1],$s[2],$this->id($r,'completionId'),$b));}
    public function acceptPracticalCompletion(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->acceptPracticalCompletion($s[0],$s[1],$this->id($r,'completionId'),$b));}
    public function createHandoverPack(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->createHandoverPack($s[0],$s[1],$this->id($r,'projectId')),201);}
    public function addHandoverDocument(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->addHandoverDocument($s[0],$s[1],$this->id($r,'packId'),$b),201);}
    public function refreshHandoverPack(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->refreshHandoverPack($s[0],$s[1],$this->id($r,'packId')));}
    public function issueHandoverPack(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->issueHandoverPack($s[0],$s[1],$s[2],$this->id($r,'packId'),$b));}
    public function assessOccupation(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->assessOccupationReadiness($s[0],$s[1],$this->id($r,'projectId'),$b),201);}
    public function reviewOccupation(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->reviewOccupationReadiness($s[0],$s[1],$s[2],$this->id($r,'readinessId'),$b));}
    public function assessFinalAccount(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->assessFinalAccount($s[0],$s[1],$this->id($r,'projectId'),$b),201);}
    public function createLiabilityPeriod(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->createDefectsLiabilityPeriod($s[0],$s[1],$this->id($r,'projectId'),$b),201);}
    public function reportDefect(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->reportDefect($s[0],$s[1],$this->id($r,'projectId'),$b),201);}
    public function recallContractor(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->recallContractor($s[0],$s[1],$this->id($r,'defectId'),$b));}
    public function submitDefectCorrection(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->submitDefectCorrection($s[0],$s[1],$this->id($r,'defectId'),$b));}
    public function verifyDefect(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->verifyDefect($s[0],$s[1],$s[2],$this->id($r,'defectId'),$b));}
    public function closeLiabilityPeriod(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->closeDefectsLiabilityPeriod($s[0],$s[1],$s[2],$this->id($r,'periodId'),$b));}
    private function read(Request$r,callable$f):Response{try{$s=$this->scope($r);return$s instanceof Response?$s:Response::success($f($s));}catch(CloseoutException$e){return$this->error($e);}}
    private function write(Request$r,callable$f,int$status=200):Response{try{$s=$this->scope($r);if($s instanceof Response)return$s;return Response::success($f($r->jsonBody(),$s),status:$status);}catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(CloseoutException$e){return$this->error($e);}}
    private function scope(Request$r):array|Response{$o=$r->attribute('organisationId');$i=$r->attribute('identity');$role=$r->attribute('role');return is_string($o)&&$i instanceof AuthenticatedIdentity&&is_string($role)?[$o,$i->userId,$role]:Response::error(401,'authentication_required','Authentication is required.');}
    private function id(Request$r,string$name):string{$id=$r->attribute($name);if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new CloseoutException('closeout_record_not_found');return strtolower($id);}
    private function error(CloseoutException$e):Response{$status=match(true){str_ends_with($e->reason,'_not_found')||$e->reason==='closeout_project_not_found'=>404,$e->reason==='closeout_professional_authority_required'||$e->reason==='closeout_write_forbidden'=>403,str_contains($e->reason,'transition_invalid')||str_contains($e->reason,'not_ready')||str_contains($e->reason,'not_issued')||str_contains($e->reason,'not_elapsed')||str_contains($e->reason,'immutable')||str_contains($e->reason,'defects_open')=>409,default=>422};return Response::error($status,$e->reason,'The closeout request could not be completed.');}
}
