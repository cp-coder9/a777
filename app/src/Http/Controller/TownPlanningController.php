<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Identity\AuthenticatedIdentity;
use Architex\Platform\Planning\TownPlanningException;
use Architex\Platform\Planning\TownPlanningService;
use JsonException;

final readonly class TownPlanningController
{
    public function __construct(private TownPlanningService$service){}
    public function portfolio(Request$r):Response{return$this->read($r,fn(array$s):array=>$this->service->list($s[0],$s[1],null));}
    public function index(Request$r):Response{return$this->read($r,fn(array$s):array=>$this->service->list($s[0],$s[1],$this->id($r,'projectId','planning_project_not_found')));}
    public function show(Request$r):Response{return$this->read($r,fn(array$s):array=>$this->service->get($s[0],$s[1],$this->id($r,'applicationId','planning_application_not_found')));}
    public function create(Request$r):Response{return$this->write($r,function(array$b,array$s)use($r):Response{$b['projectId']=$this->id($r,'projectId','planning_project_not_found');return Response::success($this->service->create($s[0],$s[1],$s[2],$b),status:201);});}
    public function createStandalone(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->service->create($s[0],$s[1],$s[2],$b),status:201));}
    public function entry(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->service->addEntry($s[0],$s[1],$s[2],$this->id($r,'applicationId','planning_application_not_found'),$this->version($b),$b),status:201));}
    public function submit(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->service->submit($s[0],$s[1],$s[2],$this->id($r,'applicationId','planning_application_not_found'),$this->version($b),($b['humanConfirmed']??false)===true,is_string($b['professionalRole']??null)?$b['professionalRole']:'',is_string($b['reason']??null)?$b['reason']:'',is_string($b['externalReference']??null)?$b['externalReference']:'')));}
    public function advance(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->service->advance($s[0],$s[1],$s[2],$this->id($r,'applicationId','planning_application_not_found'),$this->version($b),($b['humanConfirmed']??false)===true,is_string($b['toStage']??null)?$b['toStage']:'',is_string($b['reason']??null)?$b['reason']:'',is_string($b['externalReference']??null)?$b['externalReference']:'')));}
    /** @param callable(array{string,string,string}):array<mixed> $operation */private function read(Request$r,callable$operation):Response{try{$s=$this->scope($r);return$s instanceof Response?$s:Response::success($operation($s));}catch(TownPlanningException$e){return$this->error($e);}}
    /** @param callable(array<string,mixed>,array{string,string,string}):Response $operation */private function write(Request$r,callable$operation):Response{try{$s=$this->scope($r);if($s instanceof Response)return$s;return$operation($r->jsonBody(),$s);}catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(TownPlanningException$e){return$this->error($e);}}
    /** @return array{string,string,string}|Response */private function scope(Request$r):array|Response{$o=$r->attribute('organisationId');$i=$r->attribute('identity');$role=$r->attribute('role');return is_string($o)&&$i instanceof AuthenticatedIdentity&&is_string($role)?[$o,$i->userId,$role]:Response::error(401,'authentication_required','Authentication is required.');}
    private function id(Request$r,string$n,string$e):string{$id=$r->attribute($n);if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new TownPlanningException($e);return strtolower($id);}private function version(array$b):int{$v=$b['expectedVersion']??null;if(!is_int($v)||$v<1)throw new TownPlanningException('planning_version_required');return$v;}
    private function error(TownPlanningException$e):Response{$status=match($e->reason){'planning_project_not_found','planning_application_not_found','planning_entry_not_found'=>404,'planning_permission_denied','planning_submission_role_denied'=>403,'planning_application_number_exists','planning_version_conflict','planning_application_submitted','planning_application_closed'=>409,'planning_integrity_failed'=>503,default=>422};return Response::error($status,$e->reason,'The town planning request could not be completed.');}
}
