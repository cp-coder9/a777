<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Identity\AuthenticatedIdentity;
use Architex\Platform\SupplierProcurement\SupplierProcurementException;
use Architex\Platform\SupplierProcurement\SupplierProcurementService;
use JsonException;

final readonly class SupplierProcurementController
{
    public function __construct(private SupplierProcurementService$service){}
    public function workspace(Request$r):Response{return$this->read($r,fn(array$s):array=>$this->service->workspace($s[0],$s[1],$s[2],$this->id($r,'projectId','supplier_project_not_found')));}
    public function registerSupplier(Request$r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->registerSupplier($s[0],$s[1],$s[2],$this->id($r,'projectId','supplier_project_not_found'),$b),201);}
    public function importCatalogue(Request$r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->importCatalogue($s[0],$s[1],$s[2],$this->id($r,'supplierId','supplier_not_found'),$b),201);}
    public function createBaseline(Request$r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->createBaseline($s[0],$s[1],$s[2],$this->id($r,'projectId','supplier_project_not_found'),$b),201);}
    public function approveBaseline(Request$r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->approveBaseline($s[0],$s[1],$s[2],$this->id($r,'baselineId','supplier_baseline_not_found'),$b));}
    public function buildPackage(Request$r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->buildPackage($s[0],$s[1],$s[2],$this->id($r,'baselineId','supplier_baseline_not_found'),$b),201);}
    public function matchCatalogue(Request$r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->matchCatalogue($s[0],$s[1],$s[2],$this->id($r,'packageId','supplier_package_not_found')));}
    public function issueRfq(Request$r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->issueRfq($s[0],$s[1],$s[2],$this->id($r,'packageId','supplier_package_not_found'),$b),201);}
    public function revokeRfq(Request$r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->revokeRfq($s[0],$s[1],$s[2],$this->id($r,'rfqId','supplier_rfq_not_found'),$b));}
    public function decideSubstitution(Request$r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->decideSubstitution($s[0],$s[1],$s[2],$this->id($r,'matchId','supplier_match_not_found'),$b),201);}
    public function approveAward(Request$r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->approveAward($s[0],$s[1],$s[2],$this->id($r,'packageId','supplier_package_not_found'),$b),201);}
    public function issuePurchaseOrder(Request$r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->issuePurchaseOrder($s[0],$s[1],$s[2],$this->id($r,'packageId','supplier_package_not_found'),$b),201);}
    public function recordDelivery(Request$r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->recordDelivery($s[0],$s[1],$s[2],$this->id($r,'purchaseOrderId','supplier_purchase_order_not_found'),$b),201);}
    public function recordSiteAcceptance(Request$r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->recordSiteAcceptance($s[0],$s[1],$s[2],$this->id($r,'deliveryId','supplier_delivery_not_found'),$b),201);}
    public function captureInvoice(Request$r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->captureInvoice($s[0],$s[1],$s[2],$this->id($r,'purchaseOrderId','supplier_purchase_order_not_found'),$b),201);}
    public function recommendPayment(Request$r):Response{return$this->write($r,fn(array$b,array$s):array=>$this->service->recommendPayment($s[0],$s[1],$s[2],$this->id($r,'invoiceId','supplier_invoice_not_found'),$b),201);}
    public function viewRfq(Request$r):Response{try{$token=$r->attribute('token');if(!is_string($token))throw new SupplierProcurementException('supplier_rfq_not_found');return Response::success($this->service->viewRfq($token));}catch(SupplierProcurementException$e){return$this->error($e);}}
    public function submitQuote(Request$r):Response{try{$token=$r->attribute('token');if(!is_string($token))throw new SupplierProcurementException('supplier_rfq_not_found');return Response::success($this->service->submitQuote($token,$r->jsonBody()),status:201);}catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(SupplierProcurementException$e){return$this->error($e);}}
    public function acceptPurchaseOrder(Request$r):Response{try{$token=$r->attribute('token');if(!is_string($token))throw new SupplierProcurementException('supplier_rfq_not_found');return Response::success($this->service->acceptPurchaseOrder($token,$r->jsonBody()));}catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(SupplierProcurementException$e){return$this->error($e);}}
    /** @param callable(array{string,string,string}):array<mixed>$operation */private function read(Request$r,callable$operation):Response{try{$scope=$this->scope($r);return$scope instanceof Response?$scope:Response::success($operation($scope));}catch(SupplierProcurementException$e){return$this->error($e);}}
    /** @param callable(array<string,mixed>,array{string,string,string}):array<mixed>$operation */private function write(Request$r,callable$operation,int$status=200):Response{try{$scope=$this->scope($r);if($scope instanceof Response)return$scope;return Response::success($operation($r->jsonBody(),$scope),status:$status);}catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(SupplierProcurementException$e){return$this->error($e);}}
    /** @return array{string,string,string}|Response */private function scope(Request$r):array|Response{$o=$r->attribute('organisationId');$identity=$r->attribute('identity');$role=$r->attribute('role');return is_string($o)&&$identity instanceof AuthenticatedIdentity&&is_string($role)?[$o,$identity->userId,$role]:Response::error(401,'authentication_required','Authentication is required.');}
    private function id(Request$r,string$name,string$error):string{$id=$r->attribute($name);if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new SupplierProcurementException($error);return strtolower($id);}
    private function error(SupplierProcurementException$e):Response{$status=match($e->reason){'supplier_project_not_found','supplier_not_found','supplier_baseline_not_found','supplier_package_not_found','supplier_package_line_not_found','supplier_match_not_found','supplier_rfq_not_found','supplier_award_approval_required','supplier_purchase_order_not_found','supplier_delivery_not_found','supplier_invoice_not_found','supplier_file_not_found'=>404,'supplier_permission_denied','supplier_approval_forbidden'=>403,'supplier_version_conflict','supplier_package_exists'=>409,'supplier_connector_integration_required'=>501,default=>422};return Response::error($status,$e->reason,'The supplier procurement request could not be completed.');}
}
