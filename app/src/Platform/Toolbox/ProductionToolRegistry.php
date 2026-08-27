<?php
declare(strict_types=1);
namespace Architex\Platform\Toolbox;
use Architex\Platform\Fees\FeeCalculatorService;
final readonly class ProductionToolRegistry
{
    public function __construct(private FeeCalculatorService $fees){}
    public function create():ToolDefinitionRegistry{$registry=new ToolDefinitionRegistry();$registry->register(new ToolDefinition('professional_fee_calculator','Professional Fee Calculator','1.0.0',['owner','admin','member'],'professional-fees','/toolbox/standalone/professional-fee-calculator',['fees','proposal','professional'],['definitionId'=>'id','projectValue'=>'money'],['totalInclVat'=>'money','classification'=>'string'],true,false,true,['ProjectRecord','AuditTrail'],function(array $input,array $context):array{$id=$input['definitionId']??null;if(!is_string($id))throw new ToolException('invalid_tool_input');return$this->fees->calculate($context['organisationId'],$context['userId'],$id,$input);}));return$registry;}
}
