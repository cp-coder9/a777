<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Community\CommunityException;
use Architex\Platform\Community\CommunityMessagingService;
use Architex\Platform\Identity\AuthenticatedIdentity;
use JsonException;

final readonly class CommunityController
{
    public function __construct(private CommunityMessagingService $community) {}

    public function channels(Request $request): Response { return $this->read($request, fn(array $scope): array => $this->community->channels($scope[0], $scope[1])); }
    public function saveChannelPreference(Request$request):Response{return$this->write($request,fn(array$body,array$scope):array=>$this->community->saveChannelPreference($scope[0],$scope[1],(string)($body['channelId']??''),($body['watching']??false)===true,($body['muted']??false)===true));}
    public function saveChannel(Request$request):Response{return$this->write($request,fn(array$body,array$scope):array=>$this->community->saveChannel($scope[0],$scope[1],$scope[2],$body),201);}
    public function updateChannel(Request$request):Response{return$this->write($request,function(array$body,array$scope)use($request):array{$body['id']=(string)$request->attribute('channelId');return$this->community->saveChannel($scope[0],$scope[1],$scope[2],$body);});}
    public function preference(Request $request): Response { return $this->read($request, fn(array $scope): array => $this->community->currentPreference($scope[0], $scope[1])); }
    public function capabilities(Request$request):Response{return$this->read($request,fn(array$scope):array=>$this->community->capabilities($scope[0],$scope[1]));}
    public function notifications(Request$request):Response{return$this->read($request,fn(array$scope):array=>$this->community->notifications($scope[0],$scope[1]));}
    public function readNotification(Request$request):Response{return$this->write($request,fn(array$body,array$scope):array=>$this->community->readNotification($scope[0],$scope[1],$this->id($request,'notificationId')));}
    public function savePreference(Request $request): Response { return $this->write($request, fn(array $body,array $scope): array => $this->community->savePreference($scope[0],$scope[1],$body)); }
    public function feed(Request $request): Response { return $this->read($request, function(array $scope) use ($request): array { $filters=[]; foreach(['channelId','category','search'] as $key) if($request->query($key)!==null)$filters[$key]=$request->query($key);if($request->query('saved')==='true')$filters['saved']=true; return$this->community->feed($scope[0],$scope[1],$filters); }); }
    public function thread(Request $request): Response { return $this->read($request, fn(array $scope): array => $this->community->thread($scope[0],$scope[1],$this->id($request,'threadId'))); }
    public function create(Request $request): Response { return $this->write($request, fn(array $body,array $scope): array => $this->community->createThread($scope[0],$scope[1],$scope[2],$body), 201); }
    public function reply(Request $request): Response { return $this->write($request, fn(array $body,array $scope): array => $this->community->reply($scope[0],$scope[1],$scope[2],$this->id($request,'threadId'),is_string($body['body']??null)?$body['body']:'',is_array($body['attachmentIds']??null)?$body['attachmentIds']:[],is_array($body['mentionUserIds']??null)?$body['mentionUserIds']:[]), 201); }
    public function report(Request $request): Response { return $this->write($request, fn(array $body,array $scope): array => $this->community->report($scope[0],$scope[1],$this->id($request,'threadId'),is_string($body['reason']??null)?$body['reason']:'',is_string($body['detail']??null)?$body['detail']:''), 201); }
    public function approve(Request $request): Response { return $this->write($request, fn(array $body,array $scope): array => $this->community->approve($scope[0],$scope[1],$scope[2],$this->id($request,'threadId'),is_string($body['note']??null)?$body['note']:'')); }
    public function moderationQueue(Request$request):Response{return$this->read($request,fn(array$scope):array=>$this->community->moderationQueue($scope[0],$scope[1]));}
    public function moderate(Request$request):Response{return$this->write($request,fn(array$body,array$scope):array=>$this->community->moderate($scope[0],$scope[1],$scope[2],$this->id($request,'threadId'),(string)($body['action']??''),(string)($body['note']??'')));}
    public function saveThread(Request$request):Response{return$this->write($request,fn(array$body,array$scope):array=>$this->community->saveThread($scope[0],$scope[1],$this->id($request,'threadId'),($body['saved']??false)===true));}
    public function convert(Request $request): Response { return $this->write($request, fn(array $body,array $scope): array => $this->community->convert($scope[0],$scope[1],$scope[2],$this->id($request,'threadId'),$body), 201); }

    /** @param callable(array{string,string,string}):mixed $operation */
    private function read(Request $request, callable $operation): Response
    {
        $scope=$this->scope($request); if($scope instanceof Response)return$scope;
        try{return Response::success($operation($scope));}catch(CommunityException $e){return$this->error($e);}
    }
    /** @param callable(array<string,mixed>,array{string,string,string}):mixed $operation */
    private function write(Request $request, callable $operation, int $status=200): Response
    {
        $scope=$this->scope($request); if($scope instanceof Response)return$scope;
        try{return Response::success($operation($request->jsonBody(),$scope),status:$status);}catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(CommunityException $e){return$this->error($e);}
    }
    /** @return array{string,string,string}|Response */
    private function scope(Request$request):array|Response{$organisation=$request->attribute('organisationId');$identity=$request->attribute('identity');$role=$request->attribute('role');return is_string($organisation)&&$identity instanceof AuthenticatedIdentity&&is_string($role)?[$organisation,$identity->userId,$role]:Response::error(401,'authentication_required','Authentication is required.');}
    private function id(Request$request,string$name):string{$id=$request->attribute($name);if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new CommunityException('community_thread_not_found');return strtolower($id);}
    private function error(CommunityException$exception):Response{$status=match($exception->reason){'community_thread_not_found','community_channel_not_found','community_preference_not_found','community_project_not_found','community_attachment_not_found'=>404,'community_permission_denied','community_moderation_permission_denied','community_channel_forbidden'=>403,'community_report_exists','community_conversion_exists','community_channel_preference_conflict'=>409,'community_rate_limit_exceeded'=>429,default=>422};$message=match($exception->reason){'community_thread_not_found'=>'Community thread not found.','community_preference_not_found'=>'Community preferences have not been configured.','community_moderation_permission_denied'=>'Community moderation permission is required.','community_channel_forbidden'=>'Your verified community role cannot access this channel.','community_conversion_confirmation_required'=>'Confirm the community conversion before creating a formal workflow draft.','community_rate_limit_exceeded'=>'Please wait before creating another community thread.',default=>'The community request could not be completed.'};return Response::error($status,$exception->reason,$message);}
}
