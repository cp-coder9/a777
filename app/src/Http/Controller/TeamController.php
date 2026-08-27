<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Identity\AuthenticatedIdentity;
use Architex\Platform\Team\ProjectTeamService;
use Architex\Platform\Team\TeamException;
use JsonException;

final readonly class TeamController
{
    public function __construct(private ProjectTeamService $service){}

    public function project(Request $request):Response
    {
        try{$scope=$this->projectScope($request);return $scope instanceof Response?$scope:Response::success($this->service->list($scope[0],$scope[2],$scope[1]));}
        catch(TeamException $exception){return $this->failure($exception);}
    }

    public function invite(Request $request):Response
    {
        try{
            $scope=$this->projectScope($request);if($scope instanceof Response)return$scope;$body=$request->jsonBody();
            return Response::success($this->service->invite($scope[0],$scope[2],$scope[1],$this->bodyId($body,'candidateId','candidate_not_found'),is_string($body['discipline']??null)?$body['discipline']:'',is_string($body['roleLabel']??null)?$body['roleLabel']:''),status:201);
        }catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(TeamException $exception){return$this->failure($exception);}
    }

    public function pending(Request $request):Response
    {
        $identity=$request->attribute('identity');
        return $identity instanceof AuthenticatedIdentity?Response::success($this->service->pendingForUser($identity->userId)):Response::error(401,'authentication_required','Authentication is required.');
    }

    public function accept(Request $request):Response
    {
        try{$identity=$request->attribute('identity');if(!$identity instanceof AuthenticatedIdentity)return Response::error(401,'authentication_required','Authentication is required.');return Response::success($this->service->accept($identity->userId,$this->routeId($request,'teamMemberId','invitation_not_found')));}
        catch(TeamException $exception){return$this->failure($exception);}
    }

    public function remove(Request $request):Response
    {
        try{$scope=$this->projectScope($request);if($scope instanceof Response)return$scope;$body=$request->jsonBody();return Response::success($this->service->remove($scope[0],$scope[2],$scope[1],$this->routeId($request,'teamMemberId','team_member_not_found'),is_string($body['reason']??null)?$body['reason']:''));}
        catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(TeamException $exception){return$this->failure($exception);}
    }

    /** @return array{string,string,string}|Response */
    private function projectScope(Request $request):array|Response
    {
        $organisationId=$request->attribute('organisationId');$identity=$request->attribute('identity');
        if(!is_string($organisationId)||!$identity instanceof AuthenticatedIdentity)return Response::error(401,'authentication_required','Authentication and an organisation context are required.');
        return[$organisationId,$identity->userId,$this->routeId($request,'projectId','project_not_found')];
    }

    /** @param array<string,mixed> $body */
    private function bodyId(array $body,string $key,string $reason):string{$id=$body[$key]??null;if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new TeamException($reason);return strtolower($id);}
    private function routeId(Request $request,string $key,string $reason):string{$id=$request->attribute($key);if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new TeamException($reason);return strtolower($id);}
    private function failure(TeamException $exception):Response{$status=match($exception->reason){'project_not_found','candidate_not_found','invitation_not_found','team_member_not_found'=>404,'team_member_already_present'=>409,'candidate_verification_required'=>422,default=>422};return Response::error($status,$exception->reason,'The project team request could not be completed.');}
}
