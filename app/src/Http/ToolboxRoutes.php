<?php
declare(strict_types=1);
namespace Architex\Http;
use Architex\Http\Controller\ToolboxController;
use Closure;
final readonly class ToolboxRoutes
{
    public function __construct(private ToolboxController $controller,private Middleware $useTools){}
    public function register(Router $router):void{$routes=[['GET','/api/v1/toolbox/definitions',$this->controller->definitions(...)],['GET','/api/v1/toolbox/runs',$this->controller->runs(...)],['POST','/api/v1/toolbox/runs',$this->controller->run(...)],['POST','/api/v1/toolbox/runs/{runId}/revisions',$this->controller->revise(...)],['GET','/api/v1/toolbox/runs/{runId}/exports',$this->controller->exports(...)],['GET','/api/v1/toolbox/exports/{exportId}',$this->controller->export(...)],['GET','/api/v1/fee-definitions',$this->controller->feeDefinitions(...)],['POST','/api/v1/fee-sources',$this->controller->createFeeSource(...)],['POST','/api/v1/fee-sources/{sourceId}/verify',$this->controller->verifyFeeSource(...)],['POST','/api/v1/fee-definitions',$this->controller->createFeeDefinition(...)],['POST','/api/v1/fee-definitions/{definitionId}/verify',$this->controller->verifyFeeDefinition(...)]];foreach($routes as[$method,$path,$handler])$router->{strtolower($method)}($path,$this->through($handler));}
    /** @param Closure(Request):Response $handler @return Closure(Request):Response */private function through(Closure $handler):Closure{return fn(Request $request):Response=>$this->useTools->process($request,$handler);}
}
