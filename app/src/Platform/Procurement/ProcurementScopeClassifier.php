<?php

declare(strict_types=1);

namespace Architex\Platform\Procurement;

final class ProcurementScopeClassifier
{
    private const TYPES=['main_contractor','trade_package','supplier','specialist_consultant','freelancer_support'];
    private const COMPLEXITIES=['low','medium','high'];
    private const URGENCIES=['normal','urgent','emergency'];
    private const HIGH_VALUE_ZAR=1_500_000.0;

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function classify(array $input):array
    {
        $need=$this->text($input['procurementNeed']??null,500,'procurement_need_required');
        $value=$input['estimatedValueZar']??null;
        if((!is_int($value)&&!is_float($value))||!is_finite((float)$value)||(float)$value<0)throw new ProcurementException('procurement_value_invalid');
        $complexity=$this->choice($input['complexity']??null,self::COMPLEXITIES,'procurement_complexity_invalid');
        $urgency=$this->choice($input['urgency']??null,self::URGENCIES,'procurement_urgency_invalid');
        $trades=$this->values($input['requiredTrades']??null,'procurement_trades_invalid');
        $specialists=$this->values($input['requiredSpecialists']??null,'procurement_specialists_invalid');
        $score=$input['municipalReadinessScore']??null;
        if((!is_int($score)&&!is_float($score))||!is_finite((float)$score)||(float)$score<0||(float)$score>100)throw new ProcurementException('procurement_readiness_score_invalid');

        $requested=$input['procurementType']??null;
        if($requested!==null)$type=$this->choice($requested,self::TYPES,'procurement_type_invalid');
        elseif(in_array('general_building',$trades,true))$type='main_contractor';
        elseif($trades!==[])$type='trade_package';
        elseif($specialists!==[])$type='specialist_consultant';
        else throw new ProcurementException('procurement_scope_required');

        $method=$urgency==='emergency'?'direct_appointment':(($complexity==='high'||(float)$value>=self::HIGH_VALUE_ZAR)?'open_tender':(count($trades)>1?'invited_tender':'rfq'));
        $participants=['client','lead_professional','quantity_surveyor','marketplace_admin'];
        $participants[]=match($type){'main_contractor'=>'contractor','trade_package'=>'subcontractor','supplier'=>'supplier','specialist_consultant'=>'specialist_consultant','freelancer_support'=>'freelancer_candidate_professional'};
        if(count($trades)>1)$participants[]='subcontractor';
        if($specialists!==[])$participants[]='specialist_consultant';
        $risks=[];
        if((float)$value>=self::HIGH_VALUE_ZAR)$risks[]='Value exceeds the Architex organisational default comparison threshold; this is not a statutory procurement threshold.';
        if((float)$score<80)$risks[]='Municipal readiness is below 80; package assumptions and approval dependencies must remain visible.';
        if($complexity==='high')$risks[]='High project complexity requires documented evaluation criteria and professional review.';
        if($urgency==='emergency')$risks[]='Emergency urgency does not itself authorise a direct appointment; record the human rationale and applicable procurement policy.';

        return[
            'procurementNeed'=>$need,
            'procurementType'=>$type,
            'recommendedMethod'=>$method,
            'requiredParticipants'=>array_values(array_unique($participants)),
            'riskFlags'=>$risks,
            'humanMethodSelectionRequired'=>true,
            'directAppointmentRationaleRequired'=>$method==='direct_appointment',
            'statutoryThresholdClaimed'=>false,
            'classificationPolicy'=>[
                'version'=>'architex-organisational-default-v1',
                'highValueComparisonThresholdZar'=>self::HIGH_VALUE_ZAR,
                'source'=>'Architex workflow default; replace or approve under the organisation procurement policy.',
            ],
        ];
    }

    private function text(mixed$value,int$max,string$error):string
    {
        if(!is_string($value)||($value=trim($value))===''||mb_strlen($value)>$max)throw new ProcurementException($error);
        return$value;
    }

    /** @param list<string> $allowed */
    private function choice(mixed$value,array$allowed,string$error):string
    {
        if(!is_string($value)||!in_array($value,$allowed,true))throw new ProcurementException($error);
        return$value;
    }

    /** @return list<string> */
    private function values(mixed$value,string$error):array
    {
        if(!is_array($value)||count($value)>50)throw new ProcurementException($error);
        $result=[];
        foreach($value as$item){if(!is_string($item)||preg_match('/\A[a-z0-9][a-z0-9_ -]{0,79}\z/',$item)!==1)throw new ProcurementException($error);$result[]=$item;}
        return array_values(array_unique($result));
    }
}
