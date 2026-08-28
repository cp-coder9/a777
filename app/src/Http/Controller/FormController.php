<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Forms\FormException;
use Architex\Platform\Forms\FormService;
use Architex\Platform\Identity\AuthenticatedIdentity;
use JsonException;

final readonly class FormController
{
    public function __construct(private FormService$forms){}
    public function templates(Request$r):Response{$s=$this->scope($r);if($s instanceof Response)return$s;try{return Response::success($this->forms->listTemplates($s[0],$s[1]));}catch(FormException$e){return$this->error($e);}}
    public function drafts(Request$r):Response{$s=$this->scope($r);if($s instanceof Response)return$s;try{return Response::success($this->forms->listDrafts($s[0],$s[1],$r->query('projectId')));}catch(FormException$e){return$this->error($e);}}
    public function draft(Request$r):Response{$s=$this->scope($r);if($s instanceof Response)return$s;try{return Response::success($this->forms->getDraft($s[0],$s[1],$this->id($r,'draftId','form_not_found')));}catch(FormException$e){return$this->error($e);}}
    public function create(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->forms->createDraft($s[0],$s[1],$s[2],$b),status:201));}
    public function revise(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->forms->revise($s[0],$s[1],$s[2],$this->id($r,'draftId','form_not_found'),$b),status:201));}
    public function submit(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->forms->submitForReview($s[0],$s[1],$s[2],$this->id($r,'draftId','form_not_found'),is_string($b['reason']??null)?$b['reason']:'')));}
    public function approve(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->forms->approve($s[0],$s[1],$s[2],$this->id($r,'draftId','form_not_found'),($b['humanConfirmed']??false)===true,is_string($b['reason']??null)?$b['reason']:'')));}
    public function issue(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->forms->issue($s[0],$s[1],$s[2],$this->id($r,'draftId','form_not_found'),($b['humanConfirmed']??false)===true,is_string($b['reason']??null)?$b['reason']:'')));}
    public function exports(Request$r):Response{$s=$this->scope($r);if($s instanceof Response)return$s;try{return Response::success($this->forms->listExports($s[0],$s[1],$this->id($r,'draftId','form_not_found')));}catch(FormException$e){return$this->error($e);}}
    public function export(Request$r):Response{$s=$this->scope($r);if($s instanceof Response)return$s;try{return Response::success($this->forms->export($s[0],$s[1],$this->id($r,'exportId','form_export_not_found')));}catch(FormException$e){return$this->error($e);}}
    /** @param callable(array<string,mixed>,array{string,string,string}):Response $f */private function write(Request$r,callable$f):Response{try{$s=$this->scope($r);if($s instanceof Response)return$s;return$f($r->jsonBody(),$s);}catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(FormException$e){return$this->error($e);}}
    /** @return array{string,string,string}|Response */private function scope(Request$r):array|Response{$o=$r->attribute('organisationId');$i=$r->attribute('identity');$role=$r->attribute('role');return is_string($o)&&$i instanceof AuthenticatedIdentity&&is_string($role)?[$o,$i->userId,$role]:Response::error(401,'authentication_required','Authentication is required.');}
    private function id(Request$r,string$n,string$reason):string{$id=$r->attribute($n);if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new FormException($reason);return strtolower($id);}
    private function error(FormException$e):Response{$status=match($e->reason){'form_not_found','form_template_not_found','form_export_not_found','form_project_not_found'=>404,'form_permission_denied'=>403,'form_state_invalid','form_not_reviewable','form_not_approved','form_revision_source_invalid'=>409,default=>422};$message=match($e->reason){'form_approval_confirmation_required'=>'Confirm that you personally reviewed the exact form revision.','form_issue_confirmation_required'=>'Confirm formal issue of the exact approved revision.','form_not_found'=>'Form revision not found.','form_template_not_found'=>'Form template not found.','form_export_not_found'=>'Form export not found.',default=>'The versioned form request could not be completed.'};return Response::error($status,$e->reason,$message);}
}
