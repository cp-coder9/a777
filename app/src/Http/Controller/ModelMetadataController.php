<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Identity\AuthenticatedIdentity;
use Architex\Platform\Models\ModelMetadataException;
use Architex\Platform\Models\ModelMetadataService;
use JsonException;

final readonly class ModelMetadataController
{
    public function __construct(private ModelMetadataService$service){}
    public function index(Request$r):Response{return$this->read($r,fn(array$s):array=>$this->service->list($s[0],$s[1],$this->id($r,'projectId','model_project_not_found')));}
    public function show(Request$r):Response{return$this->read($r,fn(array$s):array=>$this->service->get($s[0],$s[1],$this->id($r,'sourceId','model_source_not_found')));}
    public function create(Request$r):Response{return$this->write($r,function(array$b,array$s)use($r):Response{$b['projectId']=$this->id($r,'projectId','model_project_not_found');return Response::success($this->service->createSource($s[0],$s[1],$s[2],$b),status:201);});}
    public function revision(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->service->addRevision($s[0],$s[1],$s[2],$this->id($r,'sourceId','model_source_not_found'),$this->version($b),$b),status:201));}
    public function decision(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->service->decide($s[0],$s[1],$s[2],$this->id($r,'revisionId','model_revision_not_found'),$b)));}
    public function issue(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->service->issue($s[0],$s[1],$s[2],$this->id($r,'sourceId','model_source_not_found'),$this->bodyId($b,'revisionId','model_revision_not_found'),$this->version($b),($b['humanConfirmed']??false)===true,is_string($b['professionalRole']??null)?$b['professionalRole']:'',is_string($b['issueReason']??null)?$b['issueReason']:'')));}
    /** @param callable(array{string,string,string}):array<mixed> $operation */private function read(Request$r,callable$operation):Response{try{$scope=$this->scope($r);return$scope instanceof Response?$scope:Response::success($operation($scope));}catch(ModelMetadataException$e){return$this->error($e);}}
    /** @param callable(array<string,mixed>,array{string,string,string}):Response $operation */private function write(Request$r,callable$operation):Response{try{$scope=$this->scope($r);if($scope instanceof Response)return$scope;return$operation($r->jsonBody(),$scope);}catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(ModelMetadataException$e){return$this->error($e);}}
    /** @return array{string,string,string}|Response */private function scope(Request$r):array|Response{$o=$r->attribute('organisationId');$i=$r->attribute('identity');$role=$r->attribute('role');return is_string($o)&&$i instanceof AuthenticatedIdentity&&is_string($role)?[$o,$i->userId,$role]:Response::error(401,'authentication_required','Authentication is required.');}
    private function id(Request$r,string$n,string$e):string{$id=$r->attribute($n);if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new ModelMetadataException($e);return strtolower($id);}private function bodyId(array$b,string$n,string$e):string{$id=$b[$n]??null;if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new ModelMetadataException($e);return strtolower($id);}private function version(array$b):int{$v=$b['expectedVersion']??null;if(!is_int($v)||$v<1)throw new ModelMetadataException('model_source_version_required');return$v;}
    private function error(ModelMetadataException$e):Response{$status=match($e->reason){'model_project_not_found','model_file_not_found','model_source_not_found','model_revision_not_found'=>404,'model_permission_denied','model_decision_role_denied','model_issue_role_denied'=>403,'model_source_version_conflict','model_source_issued','model_revision_issued','model_source_revision_exists'=>409,default=>422};return Response::error($status,$e->reason,'The model metadata request could not be completed.');}
}
