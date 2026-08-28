<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Identity\AuthenticatedIdentity;
use Architex\Platform\SiteExecution\SiteExecutionException;
use Architex\Platform\SiteExecution\SiteExecutionService;
use JsonException;

final readonly class SiteExecutionController
{
    public function __construct(private SiteExecutionService $service) {}

    public function workspace(Request $r):Response{return$this->read($r,fn(array$s):array=>$this->service->workspace($s[0],$s[1],$this->id($r,'projectId','site_project_not_found')));}
    public function createDailyLog(Request $r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->createDailyLog($s[0],$s[1],$s[2],$this->id($r,'projectId','site_project_not_found'),$b),201);}
    public function captureEvidence(Request $r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->captureEvidence($s[0],$s[1],$s[2],$this->id($r,'projectId','site_project_not_found'),$b),201);}
    public function createRfi(Request $r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->createRfi($s[0],$s[1],$s[2],$this->id($r,'projectId','site_project_not_found'),$b),201);}
    public function respondRfi(Request $r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->respondRfi($s[0],$s[1],$s[2],$this->id($r,'rfiId','site_rfi_not_found'),$b),201);}
    public function closeRfi(Request $r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->closeRfi($s[0],$s[1],$s[2],$this->id($r,'rfiId','site_rfi_not_found'),$b));}
    public function markOverdueRfis(Request $r):Response{return$this->write($r,fn(array$b,array$s):array=>['updated'=>$this->service->markOverdueRfis($s[0],$s[1],$this->id($r,'projectId','site_project_not_found'),is_string($b['asAt']??null)?$b['asAt']:'')]);}
    public function createInstruction(Request $r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->createSiteInstruction($s[0],$s[1],$s[2],$this->id($r,'projectId','site_project_not_found'),$b),201);}
    public function issueInstruction(Request $r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->issueSiteInstruction($s[0],$s[1],$s[2],$this->id($r,'instructionId','site_instruction_not_found'),$b));}
    public function acknowledgeInstruction(Request $r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->acknowledgeSiteInstruction($s[0],$s[1],$s[2],$this->id($r,'instructionId','site_instruction_not_found'),$b));}
    public function supersedeInstruction(Request $r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->supersedeSiteInstruction($s[0],$s[1],$s[2],$this->id($r,'instructionId','site_instruction_not_found'),$b),201);}
    public function createNcr(Request $r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->createNcr($s[0],$s[1],$s[2],$this->id($r,'projectId','site_project_not_found'),$b),201);}
    public function submitNcrCorrectiveAction(Request $r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->submitNcrCorrectiveAction($s[0],$s[1],$s[2],$this->id($r,'ncrId','site_ncr_not_found'),$b),201);}
    public function decideNcr(Request $r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->decideNcr($s[0],$s[1],$s[2],$this->id($r,'ncrId','site_ncr_not_found'),$b));}
    public function createSnag(Request $r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->createSnag($s[0],$s[1],$s[2],$this->id($r,'projectId','site_project_not_found'),$b),201);}
    public function allocateSnag(Request $r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->allocateSnag($s[0],$s[1],$s[2],$this->id($r,'snagId','site_snag_not_found'),$b));}
    public function markSnagReady(Request $r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->markSnagReady($s[0],$s[1],$s[2],$this->id($r,'snagId','site_snag_not_found'),$b));}
    public function decideSnag(Request $r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->decideSnag($s[0],$s[1],$s[2],$this->id($r,'snagId','site_snag_not_found'),$b));}
    public function createInspection(Request $r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->createInspection($s[0],$s[1],$s[2],$this->id($r,'projectId','site_project_not_found'),$b),201);}
    public function issueInspection(Request $r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->issueInspection($s[0],$s[1],$s[2],$this->id($r,'inspectionId','site_inspection_not_found'),$b));}
    public function closeInspection(Request $r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->closeInspection($s[0],$s[1],$s[2],$this->id($r,'inspectionId','site_inspection_not_found'),$b));}
    public function createDelayWarning(Request $r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->createDelayWarning($s[0],$s[1],$s[2],$this->id($r,'projectId','site_project_not_found'),$b),201);}
    public function transitionDelayWarning(Request $r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->transitionDelayWarning($s[0],$s[1],$s[2],$this->id($r,'warningId','site_delay_warning_not_found'),$b));}
    public function assessProgrammeImpact(Request $r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->assessProgrammeImpact($s[0],$s[1],$s[2],$this->id($r,'projectId','site_project_not_found'),$b),201);}
    public function createPaymentBlocker(Request $r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->createPaymentBlocker($s[0],$s[1],$s[2],$this->id($r,'projectId','site_project_not_found'),$b),201);}
    public function clearPaymentBlocker(Request $r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->clearPaymentBlocker($s[0],$s[1],$s[2],$this->id($r,'blockerId','site_payment_blocker_not_found'),$b));}

    /** @param callable(array{string,string,string}):array<string,mixed> $operation */
    private function read(Request$r,callable$operation):Response{try{$s=$this->scope($r);return$s instanceof Response?$s:Response::success($operation($s));}catch(SiteExecutionException$e){return$this->error($e);}}
    /** @param callable(array<string,mixed>,array{string,string,string}):array<string,mixed> $operation */
    private function write(Request$r,callable$operation,int$status=200):Response{try{$s=$this->scope($r);if($s instanceof Response)return$s;return Response::success($operation($r->jsonBody(),$s),status:$status);}catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(SiteExecutionException$e){return$this->error($e);}}
    /** @return array{string,string,string}|Response */
    private function scope(Request$r):array|Response{$o=$r->attribute('organisationId');$i=$r->attribute('identity');$role=$r->attribute('role');return is_string($o)&&$i instanceof AuthenticatedIdentity&&is_string($role)?[$o,$i->userId,$role]:Response::error(401,'authentication_required','Authentication is required.');}
    private function id(Request$r,string$name,string$error):string{$id=$r->attribute($name);if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new SiteExecutionException($error);return strtolower($id);}
    private function error(SiteExecutionException$e):Response{$status=match(true){str_ends_with($e->reason,'_not_found')||$e->reason==='site_project_not_found'||$e->reason==='site_source_not_found'=>404,$e->reason==='site_professional_authority_required'=>403,str_contains($e->reason,'transition_invalid')||str_ends_with($e->reason,'_exists')||$e->reason==='site_payment_blocker_source_unresolved'=>409,default=>422};return Response::error($status,$e->reason,'The site-execution request could not be completed.');}
}
