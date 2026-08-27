<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Brief\BriefException;
use Architex\Platform\Brief\BriefEvidenceService;
use Architex\Platform\Files\FileException;
use Architex\Platform\Brief\GuidedBriefService;
use Architex\Platform\Identity\AuthenticatedIdentity;
use JsonException;

final readonly class BriefController
{
    public function __construct(private GuidedBriefService $service,private BriefEvidenceService $evidence){}
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
    public function files(Request $request):Response
    {
        try{$scope=$this->scope($request);return $scope instanceof Response?$scope:Response::success($this->evidence->list($scope[0],$scope[1],$this->id($request)));}
        catch(BriefException $e){return $this->briefError($e);}
    }
    public function uploadFile(Request $request):Response
    {
        try{
            $scope=$this->scope($request);if($scope instanceof Response)return$scope;$upload=$request->uploadedFile('file');
            if($upload===null)return Response::error(422,'invalid_upload','A guided brief evidence file is required.');
            return Response::success($this->evidence->upload($scope[0],$scope[1],$this->id($request),$upload,$request->form('category')??'',$request->form('processingPurpose')??'',$request->form('retentionUntil')??'',filter_var($request->form('consentGiven'),FILTER_VALIDATE_BOOL)),status:201);
        }catch(BriefException $e){return $this->briefError($e);}catch(FileException $e){return $this->fileError($e);}
    }
    public function downloadFile(Request $request):Response
    {
        try{
            $scope=$this->scope($request);if($scope instanceof Response)return$scope;$fileId=$request->attribute('fileId');if(!is_string($fileId)||preg_match('/\A[a-f0-9]{32}\z/i',$fileId)!==1)throw new BriefException('brief_file_not_found');$file=$this->evidence->download($scope[0],$scope[1],strtolower($fileId));$ascii=preg_replace('/[^A-Za-z0-9._ -]/','_',$file->originalName)?:'download';
            return Response::binary($file->contents,$file->mimeType,['Content-Length'=>(string)$file->byteSize,'Content-Disposition'=>'attachment; filename="'.addcslashes($ascii,'"\\').'"; filename*=UTF-8\'\''.rawurlencode($file->originalName),'X-Content-Sha256'=>$file->sha256,'Cache-Control'=>'private, no-store']);
        }catch(BriefException $e){return $this->briefError($e);}catch(FileException $e){return $this->fileError($e);}
    }
    /** @param callable(array<string,mixed>,string,string):Response $operation */
    private function write(Request $request,callable $operation):Response{try{$scope=$this->scope($request);if($scope instanceof Response)return $scope;return $operation($request->jsonBody(),$scope[0],$scope[1]);}catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(BriefException $e){return $this->briefError($e);}}
    /** @return array{string,string}|Response */
    private function scope(Request $request):array|Response{$org=$request->attribute('organisationId');$identity=$request->attribute('identity');return is_string($org)&&$identity instanceof AuthenticatedIdentity?[$org,$identity->userId]:Response::error(401,'authentication_required','Authentication is required.');}
    private function id(Request $request):string{$id=$request->attribute('briefId');if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new BriefException('brief_not_found');return strtolower($id);}
    private function briefError(BriefException $e):Response{$status=match($e->reason){'brief_not_found','brief_file_not_found','project_not_found','membership_not_found'=>404,'stale_brief_version'=>409,default=>422};return Response::error($status,$e->reason,'The guided brief request could not be completed.');}
    private function fileError(FileException $e):Response{$status=match($e->reason){'unsafe_file','invalid_upload'=>422,'file_too_large'=>413,'storage_unavailable'=>503,default=>404};return Response::error($status,$e->reason,$status===404?'File not found.':$e->getMessage());}
}
