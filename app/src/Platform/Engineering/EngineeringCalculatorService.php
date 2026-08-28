<?php

declare(strict_types=1);

namespace Architex\Platform\Engineering;

final class EngineeringCalculatorService
{
    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function calculate(string $calculatorId, array $input): array
    {
        $calculation = match ($calculatorId) {
            'beam_quick_check' => $this->beam($input),
            'rational_runoff_calculator' => $this->runoff($input),
            'voltage_drop_calculator' => $this->voltageDrop($input),
            'concrete_quantity_calculator' => $this->concrete($input),
            'masonry_quantity_calculator' => $this->masonry($input),
            'finishes_quantity_calculator' => $this->finishes($input),
            'tender_rate_build_up_calculator' => $this->rateBuildUp($input),
            'labour_productivity_calculator' => $this->productivity($input),
            default => throw new EngineeringCalculatorException('engineering_calculator_not_found'),
        };

        return [
            'calculatorId' => $calculatorId,
            'calculatorVersion' => '1.0.0',
            'formulaVersion' => $calculation['formulaVersion'],
            'calculationClass' => $calculation['calculationClass'],
            'discipline' => $calculation['discipline'],
            'sourceReference' => $calculation['sourceReference'],
            'assumptions' => $calculation['assumptions'],
            'results' => $calculation['results'],
            'riskFlags' => $calculation['riskFlags'],
            'nextAction' => $calculation['nextAction'],
            'professionalRoleRequired' => $calculation['professionalRoleRequired'],
            'issueEligible' => true,
            'professionalSignoffRequired' => true,
            'advisoryOnly' => true,
            'certifiesCompliance' => false,
            'limitations' => 'A calculator result is a controlled working record only. It does not certify compliance, select a design solution, or replace project-specific review by the responsible competent professional.',
        ];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function beam(array $input): array
    {
        $spanM=$this->positive($input,'spanM');$udl=$this->nonNegative($input,'udlKnPerM');$point=$this->nonNegative($input,'pointLoadKn');
        $elasticModulus=$this->positive($input,'elasticModulusMpa');$inertia=$this->positive($input,'inertiaMm4');$sectionModulus=$this->positive($input,'sectionModulusMm3');
        $allowableStress=$this->positive($input,'allowableStressMpa');$limitRatio=$this->positive($input,'deflectionLimitRatio');
        if($udl===0.0&&$point===0.0)throw new EngineeringCalculatorException('engineering_input_invalid');
        $spanMm=$spanM*1000;$maximumMoment=$udl*$spanM*$spanM/8+$point*$spanM/4;
        $stress=$maximumMoment*1_000_000/$sectionModulus;
        $deflection=5*$udl*($spanMm**4)/(384*$elasticModulus*$inertia)+$point*1000*($spanMm**3)/(48*$elasticModulus*$inertia);
        $deflectionLimit=$spanMm/$limitRatio;$stressUtilisation=$stress/$allowableStress;$deflectionUtilisation=$deflection/$deflectionLimit;
        return $this->definition(
            'beam-static-udl-point-v1','engineering_screening','structural',
            'NASA Technical Reports Server 19880009564; simply-supported beam elastic formula. Section properties, loads, limits and material properties remain user-supplied.',
            ['Simply supported prismatic beam.','Uniform load acts over the full span and point load acts at midspan.','Linear-elastic, small-deflection behavior; stability, shear, lateral restraint, connections, combinations and code factors are outside this quick check.'],
            ['maximumMomentKnM'=>$this->round($maximumMoment),'bendingStressMpa'=>$this->round($stress),'midspanDeflectionMm'=>$this->round($deflection,4),'deflectionLimitMm'=>$this->round($deflectionLimit,4),'stressUtilisation'=>$this->round($stressUtilisation,4),'deflectionUtilisation'=>$this->round($deflectionUtilisation,4),'screeningOutcome'=>max($stressUtilisation,$deflectionUtilisation)<=1?'within_user_limits':'exceeds_user_limit'],
            max($stressUtilisation,$deflectionUtilisation)>1?['A user-entered screening limit is exceeded.']:[],
            'Verify loads, combinations, section properties, stability, material design values and applicable standards before relying on the member design.','structural_engineer'
        );
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function runoff(array $input): array
    {
        $area=$this->positive($input,'catchmentAreaM2');$coefficient=$this->number($input,'runoffCoefficient');$intensity=$this->positive($input,'rainfallIntensityMmPerHour');
        if($coefficient<=0||$coefficient>1)throw new EngineeringCalculatorException('engineering_input_invalid');
        $litresPerSecond=$coefficient*$intensity*$area/3600;
        return $this->definition('rational-method-si-v1','engineering_screening','civil',
            'FHWA Hydraulic Design Series 4, section 2.5 Rational Method; Q = C i A / 360 for hectares and m3/s, converted here from m2 to L/s.',
            ['Rainfall intensity is uniform for a duration equal to the time of concentration.','The entire catchment contributes at peak flow.','The user selects an appropriate runoff coefficient and rainfall intensity for the site and recurrence interval.','Storage, routing, inlet capacity, climate-change allowance and downstream capacity are excluded.'],
            ['peakFlowLitresPerSecond'=>$this->round($litresPerSecond,4),'peakFlowM3PerSecond'=>$this->round($litresPerSecond/1000,6)],
            $area>800000?['The catchment exceeds the FHWA small-catchment use boundary of 80 hectares.']:[],
            'A civil professional must verify catchment delineation, time of concentration, design rainfall, routing and authority requirements.','civil_engineer');
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function voltageDrop(array $input): array
    {
        $phase=$input['phase']??null;if(!in_array($phase,['single_phase','three_phase'],true))throw new EngineeringCalculatorException('engineering_input_invalid');
        $voltage=$this->positive($input,'nominalVoltageV');$current=$this->positive($input,'currentA');$lengthKm=$this->positive($input,'routeLengthM')/1000;
        $resistance=$this->nonNegative($input,'resistanceOhmPerKm');$reactance=$this->nonNegative($input,'reactanceOhmPerKm');$powerFactor=$this->number($input,'powerFactor');$limit=$this->positive($input,'maximumDropPercent');
        if($powerFactor<=0||$powerFactor>1||$limit>100)throw new EngineeringCalculatorException('engineering_input_invalid');
        $reactiveFactor=sqrt(max(0,1-$powerFactor*$powerFactor));$phaseFactor=$phase==='three_phase'?sqrt(3):2;
        $drop=$phaseFactor*$current*($resistance*$powerFactor+$reactance*$reactiveFactor)*$lengthKm;$percent=$drop/$voltage*100;
        return $this->definition('ac-voltage-drop-rx-v1','engineering_screening','electrical',
            'First-principles AC circuit voltage-drop equation using user-supplied conductor resistance and reactance per kilometre; no cable table or protective-device selection is embedded.',
            ['Balanced steady-state circuit.','Route length is one-way; the phase multiplier accounts for the return path.','Resistance/reactance values, operating temperature, grouping, harmonics, starting current and installation conditions remain the user’s responsibility.'],
            ['voltageDropV'=>$this->round($drop,4),'voltageDropPercent'=>$this->round($percent,4),'receivingVoltageV'=>$this->round($voltage-$drop,4),'screeningOutcome'=>$percent<=$limit?'within_user_limit':'exceeds_user_limit'],
            $percent>$limit?['The calculated drop exceeds the user-entered limit.']:[],
            'An electrical professional must verify cable data, current-carrying capacity, protection, fault conditions and applicable wiring rules.','electrical_engineer');
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function concrete(array $input): array
    {
        $elements=$input['elements']??null;if(!is_array($elements)||$elements===[]||count($elements)>200)throw new EngineeringCalculatorException('engineering_input_invalid');
        $rows=[];$net=0.0;foreach($elements as$element){if(!is_array($element))throw new EngineeringCalculatorException('engineering_input_invalid');$reference=$this->text($element,'reference');$volume=$this->positive($element,'lengthM')*$this->positive($element,'widthM')*$this->positive($element,'depthM')*$this->positive($element,'quantity');$net+=$volume;$rows[]=['reference'=>$reference,'volumeM3'=>$this->round($volume,4)];}
        $waste=$this->percentage($input,'wastePercent');$order=$net*(1+$waste/100);
        return $this->definition('rectangular-volume-v1','quantity_estimate','construction','Geometric volume from user-entered rectangular dimensions; no mix design or supplier allowance table is embedded.',['Elements are rectangular and dimensions are in metres.','Waste allowance is user-entered.','Excavation tolerance, overbreak, reinforcement displacement, construction joints and pump losses are excluded.'],['elements'=>$rows,'netVolumeM3'=>$this->round($net,4),'wasteVolumeM3'=>$this->round($order-$net,4),'orderVolumeM3'=>$this->round($order,4)],[],'Verify geometry against current coordinated drawings and agree ordering allowance with the contractor and supplier.','quantity_surveyor_or_responsible_contractor');
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function masonry(array $input): array
    {
        $gross=$this->positive($input,'grossWallAreaM2');$openings=$this->nonNegative($input,'openingsAreaM2');$length=$this->positive($input,'unitLengthM');$height=$this->positive($input,'unitHeightM');$waste=$this->percentage($input,'wastePercent');
        if($openings>=$gross)throw new EngineeringCalculatorException('engineering_input_invalid');$net=$gross-$openings;$raw=$net/($length*$height);$order=(int)ceil($raw*(1+$waste/100));
        return $this->definition('masonry-face-area-v1','quantity_estimate','construction','Net wall face area divided by user-entered nominal unit face area; no proprietary product coverage is embedded.',['Unit dimensions include the intended nominal joint module.','Returns, piers, cuts, bonds, lintels and breakage are represented only by the user-entered waste allowance.'],['netWallAreaM2'=>$this->round($net),'netUnits'=>$this->round($raw,2),'unitsToOrder'=>$order],[],'Verify unit module, bond, wall thickness, openings and current drawings before procurement.','quantity_surveyor_or_responsible_contractor');
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function finishes(array $input): array
    {
        $mode=$input['mode']??null;if(!in_array($mode,['paint','tile'],true))throw new EngineeringCalculatorException('engineering_input_invalid');$area=$this->positive($input,'surfaceAreaM2');$waste=$this->percentage($input,'wastePercent');
        if($mode==='paint'){$coats=$this->positive($input,'coats');$coverage=$this->positive($input,'coverageM2PerLitre');$net=$area*$coats/$coverage;$unit='litres';$basis='surface area × coats ÷ user-entered coverage';}
        else{$packCoverage=$this->positive($input,'packCoverageM2');$net=$area/$packCoverage;$unit='packs';$basis='surface area ÷ user-entered pack coverage';}
        $order=$net*(1+$waste/100);if($mode==='tile')$order=ceil($order);
        return $this->definition('finishes-coverage-v1','quantity_estimate','construction','First-principles coverage arithmetic using user-entered product coverage; no manufacturer performance claim is embedded.',['Substrate, preparation, pattern, cuts and product-specific application rates must be checked.','Waste allowance is user-entered.'],['mode'=>$mode,'basis'=>$basis,'netQuantity'=>$this->round($net,4),'quantityToOrder'=>$this->round($order,4),'unit'=>$unit],[],'Confirm current surface schedule, product data sheet, pack size and installer allowance before procurement.','quantity_surveyor_or_responsible_contractor');
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function rateBuildUp(array $input): array
    {
        $quantity=$this->positive($input,'quantity');$unit=$this->text($input,'unit');$materials=$this->nonNegative($input,'materialsPerUnit');$labour=$this->nonNegative($input,'labourPerUnit');$plant=$this->nonNegative($input,'plantPerUnit');$subcontract=$this->nonNegative($input,'subcontractPerUnit');
        $overheads=$this->percentage($input,'overheadsPercent');$profit=$this->percentage($input,'profitPercent');$risk=$this->percentage($input,'riskPercent');$direct=$materials+$labour+$plant+$subcontract;$withOverheads=$direct*(1+$overheads/100);$withProfit=$withOverheads*(1+$profit/100);$rate=$withProfit*(1+$risk/100);
        return $this->definition('sequential-rate-build-up-v1','commercial_estimate','commercial','Transparent sequential rate build-up from user-entered direct costs, overhead, profit and risk percentages.',['Overhead is applied to direct cost, profit to the overhead-inclusive subtotal, then risk to the profit-inclusive subtotal.','Currency, tax, escalation, measurement rules and market validity remain user-controlled.'],['unit'=>$unit,'directCostPerUnit'=>$this->round($direct),'overheadsPerUnit'=>$this->round($withOverheads-$direct),'profitPerUnit'=>$this->round($withProfit-$withOverheads),'riskPerUnit'=>$this->round($rate-$withProfit),'ratePerUnit'=>$this->round($rate),'total'=>$this->round($rate*$quantity)],[],'A responsible commercial professional must verify quotations, measurement basis, currency, tax, escalation and tender conditions.','quantity_surveyor');
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function productivity(array $input): array
    {
        $quantity=$this->positive($input,'quantity');$unit=$this->text($input,'unit');$productivity=$this->positive($input,'productivityPerCrewDay');$crews=$this->positive($input,'parallelCrews');$hours=$this->positive($input,'hoursPerDay');$crewDays=$quantity/$productivity;$elapsed=$crewDays/$crews;
        return $this->definition('crew-productivity-v1','programme_estimate','construction','Quantity divided by user-entered productivity per crew-day, adjusted by parallel crew count.',['Parallel crews are equally productive and can work without spatial or resource interference.','Weather, access, learning curve, rework, inspections and supply constraints are excluded unless reflected in the input rate.'],['unit'=>$unit,'crewDays'=>$this->round($crewDays,4),'elapsedWorkingDays'=>$this->round($elapsed,4),'elapsedWorkingHours'=>$this->round($elapsed*$hours,4)],[],'Validate productivity against current site conditions, crew composition, method, programme constraints and recent measured performance.','responsible_contractor');
    }

    /** @param list<string> $assumptions @param array<string,mixed> $results @param list<string> $riskFlags @return array<string,mixed> */
    private function definition(string $formulaVersion,string $calculationClass,string $discipline,string $sourceReference,array $assumptions,array $results,array $riskFlags,string $nextAction,string $professionalRoleRequired):array{return compact('formulaVersion','calculationClass','discipline','sourceReference','assumptions','results','riskFlags','nextAction','professionalRoleRequired');}
    /** @param array<string,mixed> $input */
    private function number(array $input,string $key):float{$value=$input[$key]??null;if(!is_int($value)&&!is_float($value)&&!(is_string($value)&&is_numeric($value)))throw new EngineeringCalculatorException('engineering_input_invalid');$number=(float)$value;if(!is_finite($number))throw new EngineeringCalculatorException('engineering_input_invalid');return$number;}
    /** @param array<string,mixed> $input */private function positive(array$input,string$key):float{$value=$this->number($input,$key);if($value<=0)throw new EngineeringCalculatorException('engineering_input_invalid');return$value;}
    /** @param array<string,mixed> $input */private function nonNegative(array$input,string$key):float{$value=$this->number($input,$key);if($value<0)throw new EngineeringCalculatorException('engineering_input_invalid');return$value;}
    /** @param array<string,mixed> $input */private function percentage(array$input,string$key):float{$value=$this->nonNegative($input,$key);if($value>100)throw new EngineeringCalculatorException('engineering_input_invalid');return$value;}
    /** @param array<string,mixed> $input */private function text(array$input,string$key):string{$value=$input[$key]??null;if(!is_string($value)||trim($value)===''||mb_strlen(trim($value))>180)throw new EngineeringCalculatorException('engineering_input_invalid');return trim($value);}
    private function round(float $value,int $precision=2):float{return round($value,$precision);}
}
