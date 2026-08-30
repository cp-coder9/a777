<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Identity\AuthenticatedIdentity;
use Architex\Platform\PlatformCompleteness\PlatformCompletenessException;
use Architex\Platform\PlatformCompleteness\PlatformCompletenessService;
use JsonException;

final readonly class PlatformCompletenessController
{
    public function __construct(private PlatformCompletenessService$service){}
    public function workspace(Request$r):Response{return$this->read($r,fn($s)=>$this->service->workspace($s[0],$s[1],$s[2]));}
    public function updateItem(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->updateItem($s[0],$s[1],$s[2],$this->pathId($r,'itemId'),$b));}
    public function updateWorkstream(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->updateWorkstream($s[0],$s[1],$s[2],$this->pathId($r,'workstreamId'),$b));}
    public function review(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->reviewItem($s[0],$s[1],$s[2],$this->pathId($r,'itemId'),$b),201);}
    public function evidence(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->addEvidence($s[0],$s[1],$s[2],$this->pathId($r,'itemId'),$b),201);}
    public function upload(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->linkUpload($s[0],$s[1],$s[2],$this->pathId($r,'itemId'),$b),201);}
    public function signoff(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->signOff($s[0],$s[1],$s[2],$this->pathId($r,'itemId'),$b),201);}
    public function signoffWorkstream(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->signOffWorkstream($s[0],$s[1],$s[2],$this->pathId($r,'workstreamId'),$b),201);}
    public function assignRole(Request$r):Response{return$this->write($r,function($b,$s)use($r){$role=$b['platformRole']??null;if(!is_string($role))throw new PlatformCompletenessException('platform_completeness_role_invalid');return$this->service->assignRole($s[0],$s[1],$s[2],$this->pathId($r,'userId'),$role);});}
    private function read(Request$r,callable$op):Response{try{$s=$this->scope($r);return$s instanceof Response?$s:Response::success($op($s));}catch(PlatformCompletenessException$e){return$this->error($e);}}
    private function write(Request$r,callable$op,int$status=200):Response{try{$s=$this->scope($r);if($s instanceof Response)return$s;return Response::success($op($r->jsonBody(),$s),status:$status);}catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(PlatformCompletenessException$e){return$this->error($e);}}
    /** @return array{string,string,string}|Response */private function scope(Request$r):array|Response{$o=$r->attribute('organisationId');$i=$r->attribute('identity');$role=$r->attribute('role');return is_string($o)&&$i instanceof AuthenticatedIdentity&&is_string($role)?[$o,$i->userId,$role]:Response::error(401,'authentication_required','Authentication is required.');}
    private function pathId(Request$r,string$key):string{$id=$r->attribute($key);if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new PlatformCompletenessException('platform_completeness_item_not_found');return strtolower($id);}
    private function error(PlatformCompletenessException$e):Response{$status=match(true){str_ends_with($e->reason,'_not_found')=>404,str_ends_with($e->reason,'_forbidden')||str_ends_with($e->reason,'_membership_required')=>403,str_contains($e->reason,'_conflict')||str_ends_with($e->reason,'_confirmation_required')||str_ends_with($e->reason,'_signoff_required')||str_ends_with($e->reason,'_qc_required')=>409,default=>422};return Response::error($status,$e->reason,'The platform completeness request could not be completed.');}
}
