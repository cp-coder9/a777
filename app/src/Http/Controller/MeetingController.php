<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Identity\AuthenticatedIdentity;
use Architex\Platform\Meeting\MeetingException;
use Architex\Platform\Meeting\MeetingService;
use JsonException;

final readonly class MeetingController
{
    public function __construct(private MeetingService $meetings){}
    public function list(Request $r):Response{$scope=$this->scope($r);if($scope instanceof Response)return$scope;$project=$r->query('projectId');if($project!==null&&preg_match('/\A[a-f0-9]{32}\z/i',$project)!==1)return Response::error(422,'meeting_project_invalid','The meeting request could not be completed.');return Response::success($this->meetings->list($scope[0],$scope[1],$project===null?null:strtolower($project)));}
    public function get(Request $r):Response{$scope=$this->scope($r);if($scope instanceof Response)return$scope;try{return Response::success($this->meetings->get($scope[0],$scope[1],$this->id($r)));}catch(MeetingException $e){return$this->error($e);}}
    public function schedule(Request $r):Response{return$this->write($r,fn(array $b,array $s):Response=>Response::success($this->meetings->schedule($s[0],$s[1],$s[2],$b),status:201));}
    public function consent(Request $r):Response{return$this->write($r,fn(array $b,array $s):Response=>Response::success($this->meetings->recordConsent($s[0],$s[1],$s[2],$this->id($r),is_string($b['attendeeIdentity']??null)?$b['attendeeIdentity']:'',($b['consented']??false)===true)));}
    public function start(Request $r):Response{return$this->write($r,fn(array $b,array $s):Response=>Response::success($this->meetings->start($s[0],$s[1],$s[2],$this->id($r))));}
    public function conclude(Request $r):Response{return$this->write($r,fn(array $b,array $s):Response=>Response::success($this->meetings->conclude($s[0],$s[1],$s[2],$this->id($r),$b)));}
    public function reviewOutcome(Request $r):Response{return$this->write($r,function(array $b,array $s)use($r):Response{$index=$r->attribute('outcomeIndex');if(!is_string($index)||preg_match('/\A(?:0|[1-9][0-9]*)\z/',$index)!==1)throw new MeetingException('meeting_outcome_not_found');return Response::success($this->meetings->reviewOutcome($s[0],$s[1],$s[2],$this->id($r),(int)$index,is_string($b['state']??null)?$b['state']:'',is_string($b['reason']??null)?$b['reason']:''));});}
    public function publish(Request $r):Response{return$this->write($r,fn(array $b,array $s):Response=>Response::success($this->meetings->publishMinutes($s[0],$s[1],$s[2],$this->id($r),$b)));}
    public function correct(Request $r):Response{return$this->write($r,fn(array $b,array $s):Response=>Response::success($this->meetings->correctMinutes($s[0],$s[1],$s[2],$this->id($r),is_string($b['reason']??null)?$b['reason']:''),status:201));}
    /** @param callable(array<string,mixed>,array{string,string,string}):Response $operation */
    private function write(Request $r,callable $operation):Response{try{$scope=$this->scope($r);if($scope instanceof Response)return$scope;return$operation($r->jsonBody(),$scope);}catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(MeetingException $e){return$this->error($e);}}
    /** @return array{string,string,string}|Response */
    private function scope(Request $r):array|Response{$org=$r->attribute('organisationId');$identity=$r->attribute('identity');$role=$r->attribute('role');return is_string($org)&&$identity instanceof AuthenticatedIdentity&&is_string($role)?[$org,$identity->userId,$role]:Response::error(401,'authentication_required','Authentication is required.');}
    private function id(Request $r):string{$id=$r->attribute('meetingId');if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new MeetingException('meeting_not_found');return strtolower($id);}
    private function error(MeetingException $e):Response{$status=match($e->reason){'meeting_not_found','meeting_project_not_found','meeting_minutes_not_found','meeting_outcome_not_found'=>404,'meeting_permission_denied'=>403,default=>422};$message=match($e->reason){'meeting_consent_incomplete'=>'Record every attendee consent before starting this recorded or transcribed meeting.','meeting_outcomes_pending'=>'Accept or reject every outcome before publishing the minutes.','meeting_reviewer_confirmation_required'=>'Confirm the human review before publishing the minutes.','meeting_not_scheduled'=>'Only a scheduled meeting can be started.','meeting_not_live'=>'Only a live meeting can be concluded.','meeting_not_in_review'=>'This meeting does not have draft minutes ready for review.','meeting_permission_denied'=>'Your project role cannot change this meeting.','meeting_not_found'=>'Meeting not found.',default=>'The meeting request could not be completed.'};return Response::error($status,$e->reason,$message);}
}
