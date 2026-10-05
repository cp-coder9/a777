<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Identity\AuthenticatedIdentity;
use Architex\Platform\OperatingSystem\ApiCapabilityService;
use Architex\Platform\OperatingSystem\DeploymentReleaseGateService;
use Architex\Platform\OperatingSystem\ProductOperatingSystemException;
use Architex\Platform\OperatingSystem\ProductOperatingSystemService;
use JsonException;

final readonly class OperatingSystemController
{
    public function __construct(private ProductOperatingSystemService$operating,private ApiCapabilityService$capabilities,private DeploymentReleaseGateService$releaseGates){}

    public function snapshot(Request$r):Response{return$this->read($r,function($s)use($r){$project=$this->pathId($r,'projectId','operating_project_not_found');return$this->operating->projectSnapshot($s[0],$s[1],$s[2],$project);});}
    public function mobileInbox(Request$r):Response{return$this->read($r,fn($s)=>$this->operating->mobileInbox($s[0],$s[1],$s[2]));}
    public function capabilityReport(Request$r):Response{return$this->read($r,function($s)use($r){$profile=null;$identity=$r->attribute('identity');foreach($identity->memberships as $membership)if(($membership['organisationId']??null)===$s[0]&&($membership['role']??null)===$s[2]){$profile=is_string($membership['profileRole']??null)?$membership['profileRole']:null;return$this->capabilities->report($s[2],$profile);}return$this->capabilities->report();});}
    public function releaseGateHistory(Request$r):Response{return$this->read($r,fn($s)=>$this->releaseGates->history($s[0],$s[1],$s[2]));}
    public function transitionInbox(Request$r):Response{return$this->write($r,function($b,$s)use($r){$id=$this->pathId($r,'inboxItemId','operating_inbox_item_not_found');$status=$b['status']??null;if(!is_string($status))throw new ProductOperatingSystemException('operating_inbox_status_invalid');return$this->operating->transitionInboxItem($s[0],$s[1],$s[2],$id,$status,$b);});}
    public function recordReleaseGate(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->releaseGates->record($s[0],$s[1],$s[2],$b),201);}

    private function read(Request$r,callable$callback):Response{try{$s=$this->scope($r);return$s instanceof Response?$s:Response::success($callback($s));}catch(ProductOperatingSystemException$e){return$this->error($e);}}
    private function write(Request$r,callable$callback,int$status=200):Response{try{$s=$this->scope($r);if($s instanceof Response)return$s;return Response::success($callback($r->jsonBody(),$s),status:$status);}catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(ProductOperatingSystemException$e){return$this->error($e);}}
    /** @return array{string,string,string}|Response */ private function scope(Request$r):array|Response{$o=$r->attribute('organisationId');$i=$r->attribute('identity');$role=$r->attribute('role');return is_string($o)&&$i instanceof AuthenticatedIdentity&&is_string($role)?[$o,$i->userId,$role]:Response::error(401,'authentication_required','Authentication is required.');}
    private function pathId(Request$r,string$key,string$error):string{$id=$r->attribute($key);if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new ProductOperatingSystemException($error);return strtolower($id);}
    private function error(ProductOperatingSystemException$e):Response{$status=match(true){str_ends_with($e->reason,'_not_found')=>404,str_ends_with($e->reason,'_forbidden')||str_ends_with($e->reason,'_membership_required')=>403,str_ends_with($e->reason,'_confirmation_required')=>409,default=>422};return Response::error($status,$e->reason,'The product operating system request could not be completed.');}
}
