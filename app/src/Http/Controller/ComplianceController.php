<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Compliance\ComplianceException;
use Architex\Platform\Compliance\ComplianceRuleRegistry;
use Architex\Platform\Identity\AuthenticatedIdentity;
use JsonException;

final readonly class ComplianceController
{
    public function __construct(private ComplianceRuleRegistry$rules) {}

    public function list(Request$request):Response{$scope=$this->scope($request);return$scope instanceof Response?$scope:Response::success($this->rules->list($scope[0]));}
    public function seed(Request$request):Response{return$this->write($request,function(array$body,array$scope):Response{$ruleKey=$body['ruleKey']??'sans_10400_xa';if(!is_string($ruleKey))throw new ComplianceException('invalid_rule_key');$seed=match($ruleKey){'sans_10400_xa'=>$this->rules->ensureImplementationSeed($scope[0],$scope[1],$scope[2]),'municipal_refuse'=>$this->rules->ensureRefuseImplementationSeed($scope[0],$scope[1],$scope[2]),default=>throw new ComplianceException('invalid_rule_key')};return Response::success($seed,status:201);});}
    public function create(Request$request):Response{return$this->write($request,fn(array$body,array$scope):Response=>Response::success($this->rules->createVersion($scope[0],$scope[1],$scope[2],$body),status:201));}
    public function verify(Request$request):Response{return$this->write($request,fn(array$body,array$scope):Response=>Response::success($this->rules->verify($scope[0],$scope[1],$scope[2],$this->id($request),($body['humanConfirmed']??false)===true,is_string($body['verificationReason']??null)?$body['verificationReason']:'')));}

    /** @param callable(array<string,mixed>,array{string,string,string}):Response $operation */
    private function write(Request$request,callable$operation):Response{try{$scope=$this->scope($request);if($scope instanceof Response)return$scope;return$operation($request->jsonBody(),$scope);}catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(ComplianceException$exception){return$this->error($exception);}}
    /** @return array{string,string,string}|Response */
    private function scope(Request$request):array|Response{$organisation=$request->attribute('organisationId');$identity=$request->attribute('identity');$role=$request->attribute('role');return is_string($organisation)&&$identity instanceof AuthenticatedIdentity&&is_string($role)?[$organisation,$identity->userId,$role]:Response::error(401,'authentication_required','Authentication is required.');}
    private function id(Request$request):string{$id=$request->attribute('ruleVersionId');if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new ComplianceException('compliance_rule_not_found');return strtolower($id);}
    private function error(ComplianceException$exception):Response{$status=match($exception->reason){'compliance_rule_not_found'=>404,'compliance_rule_role_denied','membership_not_found'=>403,'duplicate_rule_version','rule_version_not_reviewable'=>409,'compliance_rule_integrity_failed'=>503,default=>422};return Response::error($status,$exception->reason,'The compliance rule request could not be completed.');}
}
