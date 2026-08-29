<?php

declare(strict_types=1);

namespace Architex\Platform\Toolbox;

use DateTimeImmutable;

final readonly class StandaloneUtilityService
{
    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function calculate(string $toolId,array $input):array
    {
        return match($toolId){'boq_takeoff'=>$this->boq($input),'site_diary_entry'=>$this->siteDiary($input),default=>throw new ToolException('unknown_tool')};
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function boq(array $input):array
    {
        $title=$this->text($input['title']??null,220);$currency=$input['currency']??'ZAR';if($currency!=='ZAR')throw new ToolException('invalid_tool_input');$items=$input['items']??null;if(!is_array($items)||$items===[]||count($items)>200)throw new ToolException('invalid_tool_input');$rows=[];$subtotal=0;
        foreach($items as$index=>$item){if(!is_array($item))throw new ToolException('invalid_tool_input');$quantity=$this->number($item['quantity']??null,true);$rate=$this->number($item['rate']??null,false);$amount=(int)round($quantity*$rate*100);if($amount<0||$amount>9_000_000_000_000)throw new ToolException('invalid_tool_input');$subtotal+=$amount;$rows[]=['line'=>$index+1,'description'=>$this->text($item['description']??null,300),'quantity'=>$quantity,'unit'=>$this->text($item['unit']??null,30),'rateCents'=>(int)round($rate*100),'amountCents'=>$amount];}
        $percent=$this->number($input['contingencyPercent']??0,false);if($percent>100)throw new ToolException('invalid_tool_input');$contingency=(int)round($subtotal*$percent/100);return['tool'=>'boq_takeoff','title'=>$title,'currency'=>$currency,'items'=>$rows,'lineCount'=>count($rows),'subtotalCents'=>$subtotal,'contingencyPercent'=>$percent,'contingencyCents'=>$contingency,'totalCents'=>$subtotal+$contingency,'humanEntered'=>true,'issueEligible'=>false,'advisoryOnly'=>true,'limitations'=>'Human-entered draft takeoff. Quantities, units, rates, tax, scope and procurement suitability require responsible-person review.'];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function siteDiary(array $input):array
    {
        $date=$input['date']??null;if(!is_string($date))throw new ToolException('invalid_tool_input');$parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$parsed instanceof DateTimeImmutable||$parsed->format('Y-m-d')!==$date)throw new ToolException('invalid_tool_input');$activities=$this->texts($input['activities']??null,100,1000);if($activities===[])throw new ToolException('invalid_tool_input');$deliveries=$this->texts($input['deliveries']??[],100,1000);$delays=$this->texts($input['delays']??[],100,1000);$labour=$this->integer($input['labourCount']??0);$plant=$this->integer($input['plantCount']??0);
        return['tool'=>'site_diary_entry','title'=>'Standalone site diary entry','date'=>$date,'location'=>$this->text($input['location']??null,300),'weather'=>$this->text($input['weather']??null,300),'activities'=>$activities,'activityCount'=>count($activities),'labourCount'=>$labour,'plantCount'=>$plant,'deliveries'=>$deliveries,'deliveryCount'=>count($deliveries),'delays'=>$delays,'delayCount'=>count($delays),'notes'=>$this->optional($input['notes']??null,4000),'humanEntered'=>true,'issueEligible'=>false,'advisoryOnly'=>true,'limitations'=>'Human-entered standalone diary record. Weather, attendance, plant, deliveries, progress, instructions and delay entitlement are not independently verified.'];
    }

    private function text(mixed$value,int$max):string{if(!is_string($value)||trim($value)===''||mb_strlen(trim($value))>$max)throw new ToolException('invalid_tool_input');return trim($value);}
    private function optional(mixed$value,int$max):?string{if($value===null||$value==='')return null;return$this->text($value,$max);}
    private function number(mixed$value,bool$positive):float{if(!is_int($value)&&!is_float($value)&&!is_string($value))throw new ToolException('invalid_tool_input');if(!is_numeric($value))throw new ToolException('invalid_tool_input');$number=(float)$value;if(!is_finite($number)||($positive?$number<=0:$number<0)||$number>1_000_000_000)throw new ToolException('invalid_tool_input');return round($number,4);}
    private function integer(mixed$value):int{if(!is_int($value)&&!(is_string($value)&&preg_match('/\A\d+\z/',$value)===1))throw new ToolException('invalid_tool_input');$number=(int)$value;if($number<0||$number>100000)throw new ToolException('invalid_tool_input');return$number;}
    /** @return list<string> */private function texts(mixed$value,int$limit,int$max):array{if(!is_array($value)||count($value)>$limit)throw new ToolException('invalid_tool_input');$out=[];foreach($value as$item)$out[]=$this->text($item,$max);return$out;}
}
