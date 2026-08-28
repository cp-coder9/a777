<?php
declare(strict_types=1);
namespace Architex\Platform\Toolbox;
use Architex\Platform\Compliance\ComplianceCalculatorService;
use Architex\Platform\Fees\FeeCalculatorService;
final readonly class ProductionToolRegistry
{
    public function __construct(private FeeCalculatorService $fees,private ?ComplianceCalculatorService $compliance=null){}
    public function create():ToolDefinitionRegistry{$registry=new ToolDefinitionRegistry();$registry->register(new ToolDefinition('professional_fee_calculator','Professional Fee Calculator','1.0.0',['owner','admin','member'],'professional-fees','/toolbox/standalone/professional-fee-calculator',['fees','proposal','professional'],['definitionId'=>'id','projectValue'=>'money'],['totalInclVat'=>'money','classification'=>'string'],true,false,true,['ProjectRecord','AuditTrail'],function(array $input,array $context):array{$id=$input['definitionId']??null;if(!is_string($id))throw new ToolException('invalid_tool_input');return$this->fees->calculate($context['organisationId'],$context['userId'],$id,$input);}));
        if($this->compliance!==null){foreach([
            ['fenestration_xa_calculator','Fenestration XA Calculator','/toolbox/standalone/fenestration-xa-calculator',['fenestration','xa','compliance'],['project'=>'object','elements'=>'array']],
            ['r_value_thermal_calculator','R-Value Thermal Calculator','/toolbox/standalone/r-value-thermal-calculator',['thermal','r-value','compliance'],['project'=>'object','assemblies'=>'array']],
            ['sans_forms_checklist_builder','SANS Forms Checklist Builder','/toolbox/standalone/sans-forms-checklist-builder',['sans','forms','compliance'],['project'=>'object','parts'=>'array']],
            ['drawing_compliance_precheck','Drawing Compliance Pre-check','/toolbox/standalone/drawing-compliance-precheck',['drawings','evidence','compliance'],['project'=>'object','sheets'=>'array']],
        ]as[$id,$name,$route,$tags,$schema])$registry->register(new ToolDefinition($id,$name,'1.0.0',['owner','admin','member'],'compliance',$route,$tags,['ruleVersionId'=>'id']+$schema,['verdict'=>'string','actionCards'=>'array','sourceVersion'=>'string'],true,false,true,['ProjectRecord','Inbox','MunicipalReadiness','AuditTrail'],function(array$input,array$context)use($id):array{$source=$input['ruleVersionId']??null;if(!is_string($source))throw new ToolException('invalid_tool_input');try{return$this->compliance->calculate($context['organisationId'],$source,$id,$input);}catch(\Architex\Platform\Compliance\ComplianceException$exception){throw new ToolException($exception->reason);}}));}
        return$registry;}
}
