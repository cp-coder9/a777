<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Identity\AuthenticatedIdentity;
use Architex\Platform\Trust\TrustComplianceService;
use Architex\Platform\Trust\TrustException;
use JsonException;

final readonly class TrustComplianceController
{
    public function __construct(private TrustComplianceService$service){}
    public function workspace(Request$r):Response{return$this->read($r,fn($s)=>$this->service->workspace($s[0],$s[1],$this->id($r,'projectId')));}
    public function createItem(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->createComplianceItem($s[0],$s[1],$this->id($r,'projectId'),$b),201);}
    public function reviewItem(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->reviewComplianceItem($s[0],$s[1],$this->id($r,'itemId'),$b));}
    public function createProcessingActivity(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->createProcessingActivity($s[0],$s[1],$this->id($r,'projectId'),$b),201);}
    public function createDataSubjectRequest(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->createDataSubjectRequest($s[0],$s[1],$this->id($r,'projectId'),$b),201);}
    public function transitionDataSubjectRequest(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->transitionDataSubjectRequest($s[0],$s[1],$s[2],$this->id($r,'requestId'),$b));}
    public function reportBreach(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->reportBreach($s[0],$s[1],$this->id($r,'projectId'),$b),201);}
    public function transitionBreach(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->transitionBreach($s[0],$s[1],$s[2],$this->id($r,'breachId'),$b));}
    public function resolveRisk(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->resolveRisk($s[0],$s[1],$s[2],$this->id($r,'riskId'),$b));}
    private function read(Request$r,callable$f):Response{try{$s=$this->scope($r);return$s instanceof Response?$s:Response::success($f($s));}catch(TrustException$e){return$this->error($e);}}
    private function write(Request$r,callable$f,int$status=200):Response{try{$s=$this->scope($r);if($s instanceof Response)return$s;return Response::success($f($r->jsonBody(),$s),status:$status);}catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(TrustException$e){return$this->error($e);}}
    /** @return array{string,string,string}|Response */ private function scope(Request$r):array|Response{$o=$r->attribute('organisationId');$i=$r->attribute('identity');$role=$r->attribute('role');return is_string($o)&&$i instanceof AuthenticatedIdentity&&is_string($role)?[$o,$i->userId,$role]:Response::error(401,'authentication_required','Authentication is required.');}
    private function id(Request$r,string$name):string{$id=$r->attribute($name);if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new TrustException('trust_record_not_found');return strtolower($id);}
    private function error(TrustException$e):Response{$status=match(true){str_ends_with($e->reason,'_not_found')||$e->reason==='trust_project_not_found'||$e->reason==='membership_not_found'=>404,str_ends_with($e->reason,'_permission_denied')||str_ends_with($e->reason,'_forbidden')=>403,str_contains($e->reason,'_immutable')||str_contains($e->reason,'transition_invalid')=>409,default=>422};return Response::error($status,$e->reason,'The trust/compliance request could not be completed.');}
}
