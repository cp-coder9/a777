<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\DedicatedApps\DedicatedAppException;
use Architex\Platform\DedicatedApps\DedicatedAppService;
use Architex\Platform\Identity\AuthenticatedIdentity;
use JsonException;

final readonly class DedicatedAppController
{
    public function __construct(private DedicatedAppService$service){}
    public function catalogue(Request$r):Response{return$this->read($r,fn($s)=>$this->service->catalogue($s[0],$s[1],$s[2]));}
    public function installations(Request$r):Response{return$this->read($r,fn($s)=>$this->service->installations($s[0],$s[1],$s[2]));}
    public function events(Request$r):Response{return$this->read($r,fn($s)=>$this->service->recentEvents($s[0],$s[1],$s[2]));}
    public function conflicts(Request$r):Response{return$this->read($r,fn($s)=>$this->service->syncConflicts($s[0],$s[1],$s[2]));}
    public function register(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->registerInstallation($s[0],$s[1],$s[2],$b),201);}
    public function ingest(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->ingestEvent($s[0],$s[1],$s[2],$b),201);}
    public function sync(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->submitSyncBatch($s[0],$s[1],$s[2],$b),201);}
    public function confirm(Request$r):Response{return$this->write($r,function($b,$s)use($r){$id=$this->pathId($r,'eventId');return$this->service->confirmEvent($s[0],$s[1],$s[2],$id,($b['humanConfirmed']??false)===true,is_string($b['reason']??null)?$b['reason']:'');});}
    public function clientHeartbeat(Request$r):Response{return$this->client($r,fn($token,$b)=>$this->service->heartbeatClient($token,$b));}
    public function clientEvent(Request$r):Response{return$this->client($r,fn($token,$b)=>$this->service->ingestClientEvent($token,$b),201);}
    public function clientSync(Request$r):Response{return$this->client($r,fn($token,$b)=>$this->service->submitClientSync($token,$b),201);}
    private function read(Request$r,callable$op):Response{try{$s=$this->scope($r);return$s instanceof Response?$s:Response::success($op($s));}catch(DedicatedAppException$e){return$this->error($e);}}
    private function write(Request$r,callable$op,int$status=200):Response{try{$s=$this->scope($r);if($s instanceof Response)return$s;return Response::success($op($r->jsonBody(),$s),status:$status);}catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(DedicatedAppException$e){return$this->error($e);}}
    private function client(Request$r,callable$op,int$status=200):Response{try{$token=$r->header('X-Dedicated-App-Token');if(!is_string($token))throw new DedicatedAppException('dedicated_app_client_unauthenticated');return Response::success($op($token,$r->jsonBody()),status:$status);}catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(DedicatedAppException$e){return$this->error($e);}}
    /** @return array{string,string,string}|Response */private function scope(Request$r):array|Response{$o=$r->attribute('organisationId');$i=$r->attribute('identity');$role=$r->attribute('role');return is_string($o)&&$i instanceof AuthenticatedIdentity&&is_string($role)?[$o,$i->userId,$role]:Response::error(401,'authentication_required','Authentication is required.');}
    private function pathId(Request$r,string$key):string{$id=$r->attribute($key);if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new DedicatedAppException('dedicated_app_event_not_found');return strtolower($id);}
    private function error(DedicatedAppException$e):Response{$status=match(true){str_ends_with($e->reason,'_not_found')=>404,str_ends_with($e->reason,'_unauthenticated')=>401,str_ends_with($e->reason,'_forbidden')||str_ends_with($e->reason,'_membership_required')=>403,str_contains($e->reason,'_conflict')||str_ends_with($e->reason,'_confirmation_required')||str_ends_with($e->reason,'_not_awaiting_human')||str_ends_with($e->reason,'_disabled')=>409,str_ends_with($e->reason,'_integration_required')=>503,default=>422};return Response::error($status,$e->reason,'The dedicated-app request could not be completed.');}
}
