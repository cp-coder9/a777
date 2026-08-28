<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Identity\AuthenticatedIdentity;
use Architex\Platform\Municipal\MunicipalReadinessException;
use Architex\Platform\Municipal\MunicipalReadinessService;
use Architex\Platform\Municipal\MunicipalRequirementRegistry;
use JsonException;

final readonly class MunicipalReadinessController
{
    public function __construct(private MunicipalRequirementRegistry$matrices,private MunicipalReadinessService$readiness){}

    public function matrices(Request$r):Response{return$this->read($r,fn(array$s):array=>$this->matrices->list($s[0],is_string($r->query('municipality'))?$r->query('municipality'):null));}
    public function seed(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->matrices->ensureImplementationSeed($s[0],$s[1],$s[2],is_string($b['municipality']??null)?$b['municipality']:''),status:201));}
    public function createMatrix(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->matrices->createVersion($s[0],$s[1],$s[2],$b),status:201));}
    public function verifyMatrix(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->matrices->verify($s[0],$s[1],$s[2],$this->id($r,'matrixVersionId','municipal_matrix_not_found'),($b['humanConfirmed']??false)===true,is_string($b['verificationReason']??null)?$b['verificationReason']:'')));}
    public function history(Request$r):Response{return$this->read($r,fn(array$s):array=>$this->readiness->list($s[0],$s[1],$this->id($r,'projectId','municipal_project_not_found')));}
    public function show(Request$r):Response{return$this->read($r,fn(array$s):array=>$this->readiness->get($s[0],$s[1],$this->id($r,'assessmentId','municipal_readiness_not_found')));}
    public function assess(Request$r):Response{return$this->write($r,function(array$b,array$s)use($r):Response{$matrix=$b['matrixVersionId']??null;if(!is_string($matrix)||preg_match('/\A[a-f0-9]{32}\z/i',$matrix)!==1)throw new MunicipalReadinessException('municipal_matrix_not_found');unset($b['matrixVersionId']);return Response::success($this->readiness->assess($s[0],$s[1],$s[2],$this->id($r,'projectId','municipal_project_not_found'),strtolower($matrix),$b),status:201);});}
    public function approve(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->readiness->approveForProfessionalReview($s[0],$s[1],$s[2],$this->id($r,'assessmentId','municipal_readiness_not_found'),($b['humanConfirmed']??false)===true,is_string($b['reviewReason']??null)?$b['reviewReason']:'')));}

    /** @param callable(array{string,string,string}):array<mixed> $operation */private function read(Request$r,callable$operation):Response{try{$s=$this->scope($r);return$s instanceof Response?$s:Response::success($operation($s));}catch(MunicipalReadinessException$e){return$this->error($e);}}
    /** @param callable(array<string,mixed>,array{string,string,string}):Response $operation */private function write(Request$r,callable$operation):Response{try{$s=$this->scope($r);if($s instanceof Response)return$s;return$operation($r->jsonBody(),$s);}catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(MunicipalReadinessException$e){return$this->error($e);}}
    /** @return array{string,string,string}|Response */private function scope(Request$r):array|Response{$o=$r->attribute('organisationId');$i=$r->attribute('identity');$role=$r->attribute('role');return is_string($o)&&$i instanceof AuthenticatedIdentity&&is_string($role)?[$o,$i->userId,$role]:Response::error(401,'authentication_required','Authentication is required.');}
    private function id(Request$r,string$name,string$reason):string{$id=$r->attribute($name);if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new MunicipalReadinessException($reason);return strtolower($id);}
    private function error(MunicipalReadinessException$e):Response{$status=match($e->reason){'municipal_matrix_not_found','municipal_project_not_found','municipal_readiness_not_found'=>404,'municipal_matrix_role_denied','municipal_readiness_permission_denied','municipal_readiness_role_denied','membership_not_found'=>403,'duplicate_municipal_matrix_version','municipal_matrix_not_reviewable','municipal_readiness_already_reviewed'=>409,'municipal_matrix_integrity_failed','municipal_readiness_integrity_failed'=>503,default=>422};return Response::error($status,$e->reason,'The municipal readiness request could not be completed.');}
}
