<?php

declare(strict_types=1);

namespace Architex\Platform\SupplierProcurement;

use DateTimeImmutable;
use DateTimeZone;

final class SupplierCatalogueNormaliser
{
    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function normalise(array $input):array
    {
        $standards=$this->strings($input['standards']??[]);$regions=array_map('strtolower',$this->strings($input['deliveryRegions']??[]));
        return['sku'=>trim((string)($input['sku']??'')),'name'=>trim((string)($input['name']??'')),'category'=>strtolower(trim((string)($input['category']??''))),'unit'=>strtolower(trim((string)($input['unit']??''))),'priceCents'=>(int)round((float)($input['priceZar']??0)*100),'stockQuantity'=>(float)($input['stockQuantity']??0),'leadTimeDays'=>(int)($input['leadTimeDays']??0),'standards'=>$standards,'deliveryRegions'=>$regions,'datasheetFileId'=>is_string($input['datasheetFileId']??null)?strtolower($input['datasheetFileId']):null,'substitutionEligible'=>($input['substitutionEligible']??false)===true];
    }

    /** @param array<string,mixed> $line @param array<string,mixed> $item @return array{score:int,riskFlags:list<string>,latestOrderDate:string,advisoryOnly:bool} */
    public function score(array $line,array $item,string $projectRegion,string $today):array
    {
        $required=new DateTimeImmutable((string)$line['requiredOnSiteDate'],new DateTimeZone('UTC'));$latest=$required->modify('-'.max(0,(int)$item['leadTimeDays']).' days');$score=0;$risk=[];
        $nameScore=$this->overlap((string)($line['description']??''),(string)($item['name']??''));$score+=(int)round($nameScore*35);
        $score+=strtolower((string)($line['category']??''))===$item['category']?15:0;
        if(strtolower((string)($line['unit']??''))===$item['unit'])$score+=15;else$risk[]='unit_mismatch';
        $requiredStandard=trim((string)($line['requiredStandard']??''));if($requiredStandard==='')$score+=10;elseif(in_array($requiredStandard,$item['standards'],true))$score+=15;else{$risk[]='standard_not_confirmed';$risk[]='substitution_requires_professional_approval';}
        if((float)$item['stockQuantity']>=(float)($line['quantity']??0))$score+=10;else$risk[]='stock_shortfall';
        if(in_array(strtolower(trim($projectRegion)),$item['deliveryRegions'],true))$score+=10;else$risk[]='delivery_region_unconfirmed';
        if(new DateTimeImmutable($today,new DateTimeZone('UTC'))>$latest)$risk[]='programme_date_at_risk';
        return['score'=>max(0,min(100,$score)),'riskFlags'=>array_values(array_unique($risk)),'latestOrderDate'=>$latest->format('Y-m-d'),'advisoryOnly'=>true];
    }

    /** @return list<string> */private function strings(mixed$value):array{if(!is_array($value))return[];return array_values(array_unique(array_filter(array_map(static fn(mixed$v):string=>trim((string)$v),$value),static fn(string$v):bool=>$v!=='')));}
    private function overlap(string$a,string$b):float{$left=array_values(array_unique(array_filter(preg_split('/[^a-z0-9]+/',strtolower($a))?:[])));$right=array_flip(array_values(array_unique(array_filter(preg_split('/[^a-z0-9]+/',strtolower($b))?:[]))));if($left===[])return 0.0;$hits=0;foreach($left as$token)if(isset($right[$token]))$hits++;return$hits/count($left);}
}
