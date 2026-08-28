<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Communication\CommunicationException;
use Architex\Platform\Communication\ProjectCommunicationService;
use Architex\Platform\Identity\AuthenticatedIdentity;
use JsonException;

final readonly class CommunicationController
{
    public function __construct(private ProjectCommunicationService $communications){}
    public function threads(Request $r):Response{$s=$this->scope($r);if($s instanceof Response)return$s;$project=$r->query('projectId');if($project!==null&&preg_match('/\A[a-f0-9]{32}\z/i',$project)!==1)return Response::error(422,'communication_project_invalid','Select a valid project.');try{return Response::success($this->communications->listThreads($s[0],$s[1],$project));}catch(CommunicationException $e){return$this->error($e);}}
    public function createThread(Request $r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->communications->createThread($s[0],$s[1],$s[2],$b),status:201));}
    public function getThread(Request $r):Response{$s=$this->scope($r);if($s instanceof Response)return$s;try{return Response::success($this->communications->getThread($s[0],$s[1],$this->id($r,'threadId','communication_thread_not_found')));}catch(CommunicationException $e){return$this->error($e);}}
    public function draft(Request $r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->communications->draftContextualMessage($s[0],$s[1],$s[2],$this->id($r,'threadId','communication_thread_not_found'),$b),status:201));}
    public function messages(Request $r):Response{$s=$this->scope($r);if($s instanceof Response)return$s;try{$filters=[];foreach(['phase','captureType','messageKind','search']as$key)if($r->query($key)!==null)$filters[$key]=$r->query($key);return Response::success($this->communications->listMessages($s[0],$s[1],$s[2],$this->id($r,'threadId','communication_thread_not_found'),$filters));}catch(CommunicationException $e){return$this->error($e);}}
    public function send(Request $r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->communications->sendDraft($s[0],$s[1],$s[2],$this->id($r,'messageId','communication_message_not_found'),($b['humanConfirmed']??false)===true)));}
    public function convert(Request $r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->communications->convertMessage($s[0],$s[1],$s[2],$this->id($r,'messageId','communication_message_not_found'),$b),status:201));}
    public function markRead(Request $r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->communications->markRead($s[0],$s[1],$this->id($r,'threadId','communication_thread_not_found'),is_string($b['messageId']??null)?$b['messageId']:'')));}
    public function assistant(Request $r):Response{$s=$this->scope($r);if($s instanceof Response)return$s;try{return Response::success($this->communications->phaseAssistant($s[0],$s[1],$this->id($r,'projectId','communication_project_not_found'),$r->query('phase')??''));}catch(CommunicationException $e){return$this->error($e);}}
    public function actions(Request $r):Response{$s=$this->scope($r);if($s instanceof Response)return$s;try{return Response::success($this->communications->listActions($s[0],$s[1],$this->id($r,'projectId','communication_project_not_found')));}catch(CommunicationException $e){return$this->error($e);}}
    /** @param callable(array<string,mixed>,array{string,string,string}):Response $operation */private function write(Request$r,callable$operation):Response{try{$s=$this->scope($r);if($s instanceof Response)return$s;return$operation($r->jsonBody(),$s);}catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(CommunicationException$e){return$this->error($e);}}
    /** @return array{string,string,string}|Response */private function scope(Request$r):array|Response{$org=$r->attribute('organisationId');$identity=$r->attribute('identity');$role=$r->attribute('role');return is_string($org)&&$identity instanceof AuthenticatedIdentity&&is_string($role)?[$org,$identity->userId,$role]:Response::error(401,'authentication_required','Authentication is required.');}
    private function id(Request$r,string$name,string$reason):string{$id=$r->attribute($name);if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new CommunicationException($reason);return strtolower($id);}
    private function error(CommunicationException$e):Response{$status=match($e->reason){'communication_thread_not_found','communication_message_not_found','communication_project_not_found','communication_attachment_not_found','communication_action_not_found'=>404,'communication_permission_denied'=>403,'communication_conversion_exists'=>409,default=>422};$message=match($e->reason){'communication_send_confirmation_required'=>'Confirm the exact contextual draft before sending it.','communication_conversion_confirmation_required'=>'Confirm the message conversion before creating a project action or record.','communication_thread_not_found'=>'Conversation not found.','communication_message_not_found'=>'Message not found.','communication_conversion_exists'=>'This message already has that conversion.',default=>'The project communication request could not be completed.'};return Response::error($status,$e->reason,$message);}
}
