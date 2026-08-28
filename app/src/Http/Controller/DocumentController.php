<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Documents\DocumentException;
use Architex\Platform\Documents\DocumentService;
use Architex\Platform\Identity\AuthenticatedIdentity;
use JsonException;

final readonly class DocumentController
{
    public function __construct(private DocumentService $documents){}
    public function index(Request$r):Response{$s=$this->scope($r);if($s instanceof Response)return$s;try{return Response::success($this->documents->list($s[0],$s[1],$this->queryId($r,'projectId','document_project_required')));}catch(DocumentException$e){return$this->error($e);}}
    public function show(Request$r):Response{$s=$this->scope($r);if($s instanceof Response)return$s;try{return Response::success($this->documents->get($s[0],$s[1],$this->routeId($r,'revisionId','document_not_found')));}catch(DocumentException$e){return$this->error($e);}}
    public function readiness(Request$r):Response{$s=$this->scope($r);if($s instanceof Response)return$s;try{return Response::success($this->documents->readiness($s[0],$s[1],$this->routeId($r,'projectId','document_project_not_found')));}catch(DocumentException$e){return$this->error($e);}}
    public function create(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->documents->create($s[0],$s[1],$s[2],$b),status:201));}
    public function updateDraft(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->documents->updateDraft($s[0],$s[1],$s[2],$this->routeId($r,'revisionId','document_not_found'),$b)));}
    public function revise(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->documents->revise($s[0],$s[1],$s[2],$this->routeId($r,'revisionId','document_not_found'),$b),status:201));}
    public function submitReview(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->documents->submitForReview($s[0],$s[1],$s[2],$this->routeId($r,'revisionId','document_not_found'),is_string($b['reason']??null)?$b['reason']:'')));}
    public function approve(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->documents->approve($s[0],$s[1],$s[2],$this->routeId($r,'revisionId','document_not_found'),($b['humanConfirmed']??false)===true,is_string($b['reason']??null)?$b['reason']:'')));}
    public function issue(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->documents->issue($s[0],$s[1],$s[2],$this->routeId($r,'revisionId','document_not_found'),($b['humanConfirmed']??false)===true,is_string($b['reason']??null)?$b['reason']:'',is_string($b['issuePurpose']??null)?$b['issuePurpose']:null)));}
    /** @param callable(array<string,mixed>,array{string,string,string}):Response $f */private function write(Request$r,callable$f):Response{try{$s=$this->scope($r);if($s instanceof Response)return$s;return$f($r->jsonBody(),$s);}catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(DocumentException$e){return$this->error($e);}}
    /** @return array{string,string,string}|Response */private function scope(Request$r):array|Response{$o=$r->attribute('organisationId');$i=$r->attribute('identity');$role=$r->attribute('role');return is_string($o)&&$i instanceof AuthenticatedIdentity&&is_string($role)?[$o,$i->userId,$role]:Response::error(401,'authentication_required','Authentication is required.');}
    private function routeId(Request$r,string$name,string$reason):string{$id=$r->attribute($name);return$this->id($id,$reason);}
    private function queryId(Request$r,string$name,string$reason):string{return$this->id($r->query($name),$reason);}
    private function id(mixed$id,string$reason):string{if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new DocumentException($reason);return strtolower($id);}
    private function error(DocumentException$e):Response{$status=match($e->reason){'document_not_found','document_project_not_found','document_file_not_found'=>404,'document_permission_denied'=>403,'document_not_draft','document_not_reviewable','document_not_approved','document_state_invalid','document_revision_source_invalid'=>409,default=>422};$message=match($e->reason){'document_not_found'=>'Document revision not found.','document_project_not_found'=>'Project not found.','document_approval_confirmation_required'=>'Confirm that you personally reviewed this exact document revision.','document_issue_confirmation_required'=>'Confirm formal issue of this exact approved revision.','document_not_draft'=>'Only a draft revision may be edited.',default=>'The controlled document request could not be completed.'};return Response::error($status,$e->reason,$message);}
}
