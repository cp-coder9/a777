<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Governance\ControlledRegisterException;
use Architex\Platform\Governance\ControlledRegisterService;
use Architex\Platform\Identity\AuthenticatedIdentity;
use JsonException;

final readonly class ControlledRegisterController
{
    public function __construct(private ControlledRegisterService $service) {}

    public function workspace(Request $request): Response
    {
        return $this->handle($request, fn(array $scope):array=>$this->service->workspace($scope[0],$scope[1],$this->id($request,'projectId'),$this->key($request)));
    }

    public function create(Request $request): Response
    {
        return $this->handle($request, fn(array $scope):array=>$this->service->create($scope[0],$scope[1],$scope[2],$this->id($request,'projectId'),$this->key($request),$request->jsonBody()), 201);
    }

    public function transition(Request $request): Response
    {
        return $this->handle($request, fn(array $scope):array=>$this->service->transition($scope[0],$scope[1],$scope[2],$this->id($request,'entryId'),$request->jsonBody()));
    }

    private function handle(Request $request, callable $operation, int $status = 200): Response
    {
        try {
            $scope=$this->scope($request);if($scope instanceof Response)return$scope;
            return Response::success($operation($scope),status:$status);
        } catch (JsonException) {
            return Response::error(400,'invalid_json','Request body must contain valid JSON.');
        } catch (ControlledRegisterException $exception) {
            $code=match(true){str_ends_with($exception->reason,'_not_found')=>404,str_contains($exception->reason,'version_conflict')||str_contains($exception->reason,'transition_invalid')||str_contains($exception->reason,'reference_conflict')=>409,default=>422};
            return Response::error($code,$exception->reason,'The controlled register request could not be completed.');
        }
    }

    /** @return array{string,string,string}|Response */
    private function scope(Request $request): array|Response
    {
        $organisation=$request->attribute('organisationId');$identity=$request->attribute('identity');$role=$request->attribute('role');
        return is_string($organisation)&&$identity instanceof AuthenticatedIdentity&&is_string($role)?[$organisation,$identity->userId,$role]:Response::error(401,'authentication_required','Authentication is required.');
    }

    private function id(Request $request,string $name):string{$value=$request->attribute($name);if(!is_string($value)||preg_match('/\A[a-f0-9]{32}\z/i',$value)!==1)throw new ControlledRegisterException('controlled_register_entry_not_found');return strtolower($value);}
    private function key(Request $request):string{$value=$request->attribute('registerKey');if(!is_string($value)||preg_match('/\A[a-z][a-z0-9_]{1,79}\z/D',$value)!==1)throw new ControlledRegisterException('controlled_register_not_found');return$value;}
}
