<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Identity\AuthenticatedIdentity;
use Architex\Platform\SpecForge\SpecForgeException;
use Architex\Platform\SpecForge\SpecForgeService;
use JsonException;

final readonly class SpecForgeController
{
    public function __construct(private SpecForgeService $service){}
    public function index(Request$r):Response{return$this->read($r,fn(array$s):array=>$this->service->list($s[0],$s[1],$this->id($r,'projectId','specforge_project_not_found')));}
    public function show(Request$r):Response{return$this->read($r,fn(array$s):array=>$this->service->get($s[0],$s[1],$this->id($r,'workspaceId','specforge_workspace_not_found')));}
    public function create(Request$r):Response{return$this->write($r,function(array$b,array$s)use($r):Response{$b['projectId']=$this->id($r,'projectId','specforge_project_not_found');return Response::success($this->service->create($s[0],$s[1],$s[2],$b),status:201);});}
    public function section(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->service->addSection($s[0],$s[1],$s[2],$this->id($r,'workspaceId','specforge_workspace_not_found'),$this->version($b),$b),status:201));}
    public function item(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->service->addItem($s[0],$s[1],$s[2],$this->id($r,'workspaceId','specforge_workspace_not_found'),$this->bodyId($b,'sectionId','specforge_section_not_found'),$this->version($b),$b),status:201));}
    public function decision(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->service->decideItem($s[0],$s[1],$s[2],$this->id($r,'itemId','specforge_item_not_found'),$b)));}
    public function issue(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->service->issue($s[0],$s[1],$s[2],$this->id($r,'workspaceId','specforge_workspace_not_found'),$this->version($b),($b['humanConfirmed']??false)===true,is_string($b['professionalRole']??null)?$b['professionalRole']:'',is_string($b['issueReason']??null)?$b['issueReason']:'')));}
    /** @param callable(array{string,string,string}):array<mixed> $operation */private function read(Request$r,callable$operation):Response{try{$scope=$this->scope($r);return$scope instanceof Response?$scope:Response::success($operation($scope));}catch(SpecForgeException$e){return$this->error($e);}}
    /** @param callable(array<string,mixed>,array{string,string,string}):Response $operation */private function write(Request$r,callable$operation):Response{try{$scope=$this->scope($r);if($scope instanceof Response)return$scope;return$operation($r->jsonBody(),$scope);}catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(SpecForgeException$e){return$this->error($e);}}
    /** @return array{string,string,string}|Response */private function scope(Request$r):array|Response{$o=$r->attribute('organisationId');$i=$r->attribute('identity');$role=$r->attribute('role');return is_string($o)&&$i instanceof AuthenticatedIdentity&&is_string($role)?[$o,$i->userId,$role]:Response::error(401,'authentication_required','Authentication is required.');}
    private function id(Request$r,string$name,string$reason):string{$id=$r->attribute($name);if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new SpecForgeException($reason);return strtolower($id);}private function bodyId(array$b,string$name,string$reason):string{$id=$b[$name]??null;if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new SpecForgeException($reason);return strtolower($id);}private function version(array$b):int{$version=$b['expectedVersion']??null;if(!is_int($version)||$version<1)throw new SpecForgeException('specforge_version_required');return$version;}
    private function error(SpecForgeException$e):Response{$status=match($e->reason){'specforge_project_not_found','specforge_workspace_not_found','specforge_section_not_found','specforge_item_not_found'=>404,'specforge_permission_denied','specforge_decision_role_denied','specforge_professional_role_denied'=>403,'specforge_version_conflict','specforge_workspace_not_draft','specforge_workspace_revision_exists','specforge_section_code_exists','specforge_item_code_exists'=>409,default=>422};return Response::error($status,$e->reason,'The SpecForge request could not be completed.');}
}
