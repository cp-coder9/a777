<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Brief\BriefException;
use Architex\Platform\Brief\GuidedBriefService;
use Architex\Platform\Identity\AuthenticatedIdentity;
use JsonException;

final readonly class BriefController
{
    public function __construct(private GuidedBriefService $service){}
    public function index(Request $request):Response{$scope=$this->scope($request);return $scope instanceof Response?$scope:Response::success($this->service->listForUser($scope[0],$scope[1]));}
    public function create(Request $request):Response{return $this->write($request,fn(array $body,string $org,string $user):Response=>Response::success($this->service->create($org,$user,is_string($body['title']??null)?$body['title']:''),status:201));}
    public function save(Request $request):Response{return $this->write($request,fn(array $body,string $org,string $user):Response=>Response::success($this->service->save($org,$user,$this->id($request),(int)($body['expectedVersion']??0),$body)));}
    public function submit(Request $request):Response{return $this->write($request,fn(array $body,string $org,string $user):Response=>Response::success($this->service->submit($org,$user,$this->id($request),(int)($body['expectedVersion']??0))));}
    public function assign(Request $request):Response
    {
        return $this->write($request,function(array $body,string $org,string $user)use($request):Response{
            $projectId=$body['projectId']??null;$role=$request->attribute('role');if(!is_string($projectId)||!is_string($role))throw new BriefException('project_not_found');
            return Response::success($this->service->assignToProject($org,$user,$role,$this->id($request),$projectId,(int)($body['expectedVersion']??0)));
        });
    }
    /** @param callable(array<string,mixed>,string,string):Response $operation */
    private function write(Request $request,callable $operation):Response{try{$scope=$this->scope($request);if($scope instanceof Response)return $scope;return $operation($request->jsonBody(),$scope[0],$scope[1]);}catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(BriefException $e){$status=match($e->reason){'brief_not_found','project_not_found','membership_not_found'=>404,'stale_brief_version'=>409,default=>422};return Response::error($status,$e->reason,'The guided brief request could not be completed.');}}
    /** @return array{string,string}|Response */
    private function scope(Request $request):array|Response{$org=$request->attribute('organisationId');$identity=$request->attribute('identity');return is_string($org)&&$identity instanceof AuthenticatedIdentity?[$org,$identity->userId]:Response::error(401,'authentication_required','Authentication is required.');}
    private function id(Request $request):string{$id=$request->attribute('briefId');if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new BriefException('brief_not_found');return strtolower($id);}
}
