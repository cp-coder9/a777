<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Analytics\AnalyticsReportingException;
use Architex\Platform\Analytics\AnalyticsReportingService;
use Architex\Platform\Identity\AuthenticatedIdentity;
use JsonException;

final readonly class AnalyticsReportingController
{
    public function __construct(private AnalyticsReportingService$service){}
    public function workspace(Request$r):Response{return$this->read($r,fn($s)=>$this->service->workspace($s[0],$s[1],$s[2],$this->id($r,'projectId')));}
    public function baseline(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->installBaselineFormulas($s[0],$s[1],$s[2]),201);}
    public function formula(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->publishFormulaVersion($s[0],$s[1],$s[2],$b),201);}
    public function refresh(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->calculateProjectKpis($s[0],$s[1],$this->id($r,'projectId'),$b),201);}
    public function dashboard(Request$r):Response{return$this->read($r,function($s)use($r){$role=$r->attribute('dashboardRole');if(!is_string($role))throw new AnalyticsReportingException('analytics_dashboard_role_invalid');return$this->service->dashboard($s[0],$s[1],$s[2],$this->id($r,'projectId'),$role);});}
    public function layout(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->saveDashboardLayout($s[0],$s[1],$s[2],$this->id($r,'projectId'),$b),201);}
    public function rule(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->createAlertRule($s[0],$s[1],$s[2],$this->id($r,'projectId'),$b),201);}
    public function evaluate(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->evaluateAlerts($s[0],$s[1],$this->id($r,'projectId'),$b),201);}
    public function acknowledge(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->acknowledgeAlert($s[0],$s[1],$this->id($r,'alertId'),$b));}
    public function observability(Request$r):Response{return$this->write($r,fn($b,$s)=>$this->service->recordObservability($s[0],$s[1],$s[2],$b),201);}
    public function export(Request$r):Response{try{$s=$this->scope($r);if($s instanceof Response)return$s;$type=$r->attribute('exportType');$format=$r->attribute('format');if(!is_string($type)||!is_string($format))throw new AnalyticsReportingException('analytics_export_type_invalid');$f=$this->service->export($s[0],$s[1],$this->id($r,'projectId'),$type,$format,$r->query('from'),$r->query('to'));return Response::binary($f['content'],$f['mimeType'],['Content-Disposition'=>'attachment; filename="'.addcslashes($f['filename'],'"\\').'"','Content-Length'=>(string)strlen($f['content']),'X-Content-Sha256'=>$f['sha256'],'Cache-Control'=>'private, no-store']);}catch(AnalyticsReportingException$e){return$this->error($e);}}
    private function read(Request$r,callable$f):Response{try{$s=$this->scope($r);return$s instanceof Response?$s:Response::success($f($s));}catch(AnalyticsReportingException$e){return$this->error($e);}}
    private function write(Request$r,callable$f,int$status=200):Response{try{$s=$this->scope($r);if($s instanceof Response)return$s;return Response::success($f($r->jsonBody(),$s),status:$status);}catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}catch(AnalyticsReportingException$e){return$this->error($e);}}
    /** @return array{string,string,string}|Response */ private function scope(Request$r):array|Response{$o=$r->attribute('organisationId');$i=$r->attribute('identity');$role=$r->attribute('role');return is_string($o)&&$i instanceof AuthenticatedIdentity&&is_string($role)?[$o,$i->userId,$role]:Response::error(401,'authentication_required','Authentication is required.');}
    private function id(Request$r,string$name):string{$id=$r->attribute($name);if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new AnalyticsReportingException('analytics_record_not_found');return strtolower($id);}
    private function error(AnalyticsReportingException$e):Response{$status=match(true){str_ends_with($e->reason,'_not_found')=>404,str_ends_with($e->reason,'_permission_denied')=>403,str_ends_with($e->reason,'_immutable')=>409,default=>422};return Response::error($status,$e->reason,'The analytics request could not be completed.');}
}
