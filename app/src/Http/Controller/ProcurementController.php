<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Identity\AuthenticatedIdentity;
use Architex\Platform\Procurement\ProcurementException;
use Architex\Platform\Procurement\ProcurementService;
use JsonException;

final readonly class ProcurementController
{
    public function __construct(private ProcurementService$service){}
    public function marketplace(Request$r):Response{try{return Response::success($this->service->searchMarketplace(['listingType'=>$r->query('listingType'),'trade'=>$r->query('trade'),'serviceArea'=>$r->query('serviceArea')]));}catch(ProcurementException$e){return$this->error($e);}}
    public function createListing(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->service->createListing($s[0],$s[1],$s[2],$b),status:201));}
    public function publishListing(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->service->publishListing($s[0],$s[1],$s[2],$this->id($r,'listingId','marketplace_listing_not_found'),$this->version($b),($b['humanConfirmed']??false)===true,is_string($b['reason']??null)?$b['reason']:'')));}
    public function createPackage(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->service->createPackage($s[0],$s[1],$s[2],$this->id($r,'projectId','procurement_project_not_found'),$b),status:201));}
    public function showPackage(Request$r):Response{return$this->read($r,fn(array$s):array=>$this->service->getPackage($s[0],$s[1],$this->id($r,'packageId','procurement_package_not_found')));}
    public function issuePackage(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->service->issuePackage($s[0],$s[1],$s[2],$this->id($r,'packageId','procurement_package_not_found'),$this->version($b),($b['humanConfirmed']??false)===true,is_string($b['reason']??null)?$b['reason']:'')));}
    public function matches(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->service->matchMarketplace($s[0],$s[1],$this->id($r,'packageId','procurement_package_not_found'))));}
    public function invite(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->service->invite($s[0],$s[1],$s[2],$this->id($r,'packageId','procurement_package_not_found'),is_string($b['listingId']??null)?$b['listingId']:'',($b['humanConfirmed']??false)===true),status:201));}
    public function addendum(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->service->issueAddendum($s[0],$s[1],$s[2],$this->id($r,'packageId','procurement_package_not_found'),$b),status:201));}
    public function compare(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->service->compareQuotes($s[0],$s[1],$this->id($r,'packageId','procurement_package_not_found')),status:201));}
    public function recommend(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->service->recommendAward($s[0],$s[1],$s[2],$this->id($r,'packageId','procurement_package_not_found'),$b),status:201));}
    public function approve(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->service->approveAward($s[0],$s[1],$s[2],$this->id($r,'recommendationId','procurement_award_not_found'),$b),status:201));}
    public function finalise(Request$r):Response{return$this->write($r,fn(array$b,array$s):Response=>Response::success($this->service->finaliseAward($s[0],$s[1],$s[2],$this->id($r,'recommendationId','procurement_award_not_found'),($b['humanConfirmed']??false)===true,is_string($b['reason']??null)?$b['reason']:''),status:201));}
    public function invitation(Request$r):Response{return$this->public($r,fn(string$t,array$b):array=>$this->service->invitationView($t));}
    public function acceptInvitation(Request$r):Response{return$this->public($r,fn(string$t,array$b):array=>$this->service->acceptInvitation($t));}
    public function clarification(Request$r):Response{return$this->public($r,fn(string$t,array$b):array=>$this->service->submitClarification($t,is_string($b['question']??null)?$b['question']:'',($b['material']??null)===true),201);}
    public function quote(Request$r):Response{return$this->public($r,fn(string$t,array$b):array=>$this->service->submitQuote($t,$b),201);}
    /** @param callable(array{string,string,string}):array<mixed> $operation */private function read(Request$r,callable$operation):Response{try{$s=$this->scope($r);return$s instanceof Response?$s:Response::success($operation($s));}catch(ProcurementException$e){return$this->error($e);}}
    /** @param callable(array<string,mixed>,array{string,string,string}):Response $operation */private function write(Request$r,callable$operation):Response{try{$s=$this->scope($r);if($s instanceof Response)return$s;return$operation($r->jsonBody(),$s);}catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(ProcurementException$e){return$this->error($e);}}
    /** @param callable(string,array<string,mixed>):array<mixed> $operation */private function public(Request$r,callable$operation,int$status=200):Response{try{$token=$r->attribute('token');if(!is_string($token))throw new ProcurementException('procurement_invitation_not_found');$body=$r->method()==='GET'?[]:$r->jsonBody();return Response::success($operation($token,$body),status:$status);}catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(ProcurementException$e){return$this->error($e);}}
    /** @return array{string,string,string}|Response */private function scope(Request$r):array|Response{$o=$r->attribute('organisationId');$i=$r->attribute('identity');$role=$r->attribute('role');return is_string($o)&&$i instanceof AuthenticatedIdentity&&is_string($role)?[$o,$i->userId,$role]:Response::error(401,'authentication_required','Authentication is required.');}
    private function id(Request$r,string$name,string$error):string{$id=$r->attribute($name);if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new ProcurementException($error);return strtolower($id);}private function version(array$b):int{$v=$b['expectedVersion']??null;if(!is_int($v)||$v<1)throw new ProcurementException('procurement_version_required');return$v;}
    private function error(ProcurementException$e):Response{$status=match($e->reason){'marketplace_listing_not_found','procurement_project_not_found','procurement_package_not_found','procurement_invitation_not_found','procurement_comparison_not_found','procurement_quote_revision_not_found','procurement_award_not_found'=>404,'procurement_permission_denied','procurement_client_authority_not_verified','procurement_professional_authority_not_verified'=>403,'marketplace_listing_version_conflict','procurement_package_version_conflict','procurement_package_number_exists','procurement_listing_already_invited','procurement_award_approval_already_recorded','procurement_award_not_actionable'=>409,'procurement_integrity_failed','procurement_award_integrity_failed'=>503,default=>422};return Response::error($status,$e->reason,'The procurement request could not be completed.');}
}
