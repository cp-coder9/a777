<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Identity\AuthenticatedIdentity;
use Architex\Platform\Knowledge\KnowledgeException;
use Architex\Platform\Knowledge\KnowledgeService;
use JsonException;

final readonly class KnowledgeController
{
    public function __construct(private KnowledgeService $service){}
    public function workspace(Request$r):Response{return$this->handle($r,fn($s)=>$this->service->workspace($s[0],$s[1],$s[2],$this->optionalId($r->query('projectId')),$r->query('query')));}
    public function create(Request$r):Response{return$this->handle($r,fn($s)=>$this->service->create($s[0],$s[1],$s[2],$r->jsonBody()),201);}
    public function review(Request$r):Response{return$this->handle($r,fn($s)=>$this->service->review($s[0],$s[1],$s[2],$this->id($r),$r->jsonBody()));}
    private function handle(Request$r,callable$operation,int$status=200):Response{try{$s=$this->scope($r);if($s instanceof Response)return$s;return Response::success($operation($s),status:$status);}catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(KnowledgeException$e){$status=match(true){str_ends_with($e->reason,'_not_found')=>404,str_contains($e->reason,'forbidden')||str_contains($e->reason,'permission_denied')=>403,str_contains($e->reason,'conflict')||str_contains($e->reason,'immutable')=>409,default=>422};return Response::error($status,$e->reason,'The knowledge request could not be completed.');}}
    /** @return array{string,string,string}|Response */private function scope(Request$r):array|Response{$o=$r->attribute('organisationId');$i=$r->attribute('identity');$role=$r->attribute('role');return is_string($o)&&$i instanceof AuthenticatedIdentity&&is_string($role)?[$o,$i->userId,$role]:Response::error(401,'authentication_required','Authentication is required.');}
    private function id(Request$r):string{$v=$r->attribute('sourceId');if(!is_string($v)||preg_match('/\A[a-f0-9]{32}\z/i',$v)!==1)throw new KnowledgeException('knowledge_source_not_found');return strtolower($v);}
    private function optionalId(?string$v):?string{if($v===null||$v==='')return null;if(preg_match('/\A[a-f0-9]{32}\z/i',$v)!==1)throw new KnowledgeException('knowledge_project_not_found');return strtolower($v);}
}
