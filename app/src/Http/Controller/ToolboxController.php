<?php
declare(strict_types=1);
namespace Architex\Http\Controller;
use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Fees\FeeCalculatorService;
use Architex\Platform\Fees\FeeException;
use Architex\Platform\Identity\AuthenticatedIdentity;
use Architex\Platform\Toolbox\ToolDefinition;
use Architex\Platform\Toolbox\ToolDefinitionRegistry;
use Architex\Platform\Toolbox\ToolException;
use Architex\Platform\Toolbox\ToolboxEngine;
use JsonException;
final readonly class ToolboxController
{
    public function __construct(private ToolboxEngine $engine,private ToolDefinitionRegistry $registry,private FeeCalculatorService $fees){}
    public function definitions(Request $request):Response{$scope=$this->scope($request);if($scope instanceof Response)return$scope;return Response::success(array_map(static fn(ToolDefinition $d):array=>['id'=>$d->id,'name'=>$d->name,'version'=>$d->version,'category'=>$d->category,'route'=>$d->route,'tags'=>$d->tags,'inputSchema'=>$d->inputSchema,'outputSchema'=>$d->outputSchema,'governance'=>['requiresProfessionalConfirmation'=>$d->requiresProfessionalConfirmation,'allowsAiDraft'=>$d->allowsAiDraft,'locksOnIssue'=>$d->locksOnIssue,'downstreamWriteBack'=>$d->downstreamWriteBack]],$this->registry->forRole($scope[2])));}
    public function runs(Request $request):Response{$scope=$this->scope($request);return$scope instanceof Response?$scope:Response::success($this->engine->history($scope[0],$scope[1],(int)($request->query('limit')??20)));}
    public function run(Request $request):Response{return$this->write($request,function(array $body,array $scope):Response{return Response::success($this->engine->run($scope[0],$scope[1],$scope[2],is_string($body['toolId']??null)?$body['toolId']:'',is_array($body['input']??null)?$body['input']:[],is_array($body['assignment']??null)?$body['assignment']:[],($body['issueImmediately']??false)===true,is_string($body['issueReason']??null)?$body['issueReason']:null),status:201);});}
    public function revise(Request $request):Response{return$this->write($request,function(array $body,array $scope)use($request):Response{return Response::success($this->engine->revise($scope[0],$scope[1],$scope[2],$this->id($request,'runId','tool_run_not_found'),is_array($body['input']??null)?$body['input']:[],is_array($body['assignment']??null)?$body['assignment']:[],is_string($body['reason']??null)?$body['reason']:''),status:201);});}
    public function exports(Request $request):Response{$scope=$this->scope($request);return$scope instanceof Response?$scope:Response::success($this->engine->exports($scope[0],$scope[1],$this->id($request,'runId','tool_run_not_found')));}
    public function export(Request $request):Response{try{$scope=$this->scope($request);if($scope instanceof Response)return$scope;$file=$this->engine->export($scope[0],$scope[1],$this->id($request,'exportId','tool_export_not_found'));return Response::binary($file['content'],$file['mimeType'],['Content-Length'=>(string)$file['byteSize'],'Content-Disposition'=>'attachment; filename="'.addcslashes($file['filename'],'"\\').'"','X-Content-Sha256'=>$file['sha256'],'Cache-Control'=>'private, no-store']);}catch(ToolException $e){return$this->error($e->reason);}}
    public function feeDefinitions(Request $request):Response{$scope=$this->scope($request);return$scope instanceof Response?$scope:Response::success($this->fees->definitions($scope[0],$scope[1]));}
    public function createFeeSource(Request $request):Response{return$this->write($request,fn(array $body,array $scope):Response=>Response::success($this->fees->createSource($scope[0],$scope[1],$scope[2],$body),status:201));}
    public function verifyFeeSource(Request $request):Response{return$this->write($request,function(array $body,array $scope)use($request):Response{$id=$this->id($request,'sourceId','fee_source_not_found');$this->fees->verifySource($scope[0],$scope[1],$scope[2],$id);return Response::success(['id'=>$id,'status'=>'verified']);});}
    public function createFeeDefinition(Request $request):Response{return$this->write($request,fn(array $body,array $scope):Response=>Response::success($this->fees->createDefinition($scope[0],$scope[1],$scope[2],$body),status:201));}
    public function verifyFeeDefinition(Request $request):Response{return$this->write($request,function(array $body,array $scope)use($request):Response{$id=$this->id($request,'definitionId','fee_definition_not_found');$this->fees->verifyDefinition($scope[0],$scope[1],$scope[2],$id);return Response::success(['id'=>$id,'status'=>'verified']);});}
    /** @param callable(array<string,mixed>,array{string,string,string}):Response $operation */
    private function write(Request $request,callable $operation):Response{try{$scope=$this->scope($request);if($scope instanceof Response)return$scope;return$operation($request->jsonBody(),$scope);}catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(ToolException|FeeException $e){return$this->error($e->reason);}}
    /** @return array{string,string,string}|Response */
    private function scope(Request $request):array|Response{$org=$request->attribute('organisationId');$identity=$request->attribute('identity');$role=$request->attribute('role');return is_string($org)&&$identity instanceof AuthenticatedIdentity&&is_string($role)?[$org,$identity->userId,$role]:Response::error(401,'authentication_required','Authentication is required.');}
    private function id(Request $request,string $attribute,string $reason):string{$id=$request->attribute($attribute);if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new ToolException($reason);return strtolower($id);}
    private function error(string $reason):Response{$status=match($reason){'unknown_tool','tool_run_not_found','tool_export_not_found','fee_definition_not_found','fee_source_not_found','project_not_found'=>404,'tool_role_denied','fee_admin_required'=>403,'tool_export_integrity_failed'=>503,default=>422};$message=match($reason){'professional_authority_not_verified'=>'Issue requires a current independently reviewed professional or contractor verification matching the responsible role.','professional_role_mismatch'=>'The confirmed professional role does not match the calculator responsibility role.','professional_confirmation_required'=>'Explicit professional responsibility confirmation and an issue reason are required.',default=>'The toolbox request could not be completed.'};return Response::error($status,$reason,$message);}
}
