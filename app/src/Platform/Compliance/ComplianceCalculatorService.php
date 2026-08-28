<?php

declare(strict_types=1);

namespace Architex\Platform\Compliance;

final readonly class ComplianceCalculatorService
{
    private const ORIENTATIONS=['N','NE','E','SE','S','SW','W','NW','HORIZONTAL'];
    private const FRAME_TYPES=['alu','thermal','timber'];

    public function __construct(private ComplianceRuleRegistry $rules) {}

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function calculate(string $organisationId,string $sourceVersionId,string $toolId,array $input): array
    {
        $source=$this->rules->get($organisationId,$sourceVersionId);$project=$this->project($input['project']??null);$rules=$source['rules'];
        $result=match($toolId){
            'fenestration_xa_calculator'=>$this->fenestration($project,$input['elements']??null,$rules),
            'r_value_thermal_calculator'=>$this->assemblies($project,$input['assemblies']??null,$rules),
            'sans_forms_checklist_builder'=>$this->forms($project,$input,$rules),
            'drawing_compliance_precheck'=>$this->drawings($project,$input['sheets']??null),
            default=>throw new ComplianceException('unknown_compliance_tool'),
        };
        return$result+['sourceRuleVersionId'=>$source['id'],'sourceVersion'=>$source['version'],'sourceStatus'=>$source['status'],'sourceReference'=>$source['sourceReference'],'sourceEffectiveDate'=>$source['effectiveDate'],'sourcePayloadSha256'=>$source['payloadSha256'],'issueEligible'=>$source['issueEligible'],'professionalSignoffRequired'=>true,'municipalApproval'=>false,'advisoryOnly'=>true];
    }

    /** @param array<string,mixed> $project @param mixed $rawElements @param array<string,mixed> $rules @return array<string,mixed> */
    private function fenestration(array $project,mixed $rawElements,array $rules): array
    {
        $elements=$this->items($rawElements,'invalid_fenestration_elements');$zone=$this->zone($rules,$project['climateZone']);$results=[];$byStorey=[];
        foreach($elements as$element){
            $id=$this->text($element['id']??null,60,'invalid_fenestration_element');$storey=$this->integer($element['storey']??null,1,$project['storeys'],'invalid_fenestration_element');$orientation=$element['orientation']??null;$frame=$element['frameType']??null;
            if(!is_string($orientation)||!in_array($orientation,self::ORIENTATIONS,true)||!is_string($frame)||!in_array($frame,self::FRAME_TYPES,true))throw new ComplianceException('invalid_fenestration_element');
            $glass=$this->positive($element['glassArea']??null,'invalid_fenestration_element');$wall=$this->positive($element['wallArea']??null,'invalid_fenestration_element');$uValue=$this->nonNegative($element['uValue']??null,'invalid_fenestration_element');$shgc=$this->range($element['shgc']??null,0,1,'invalid_fenestration_element');$shading=$this->range($element['shadingFactor']??null,0,1,'invalid_fenestration_element');
            $band=in_array($orientation,['N','NE','NW','HORIZONTAL'],true)?'n':(in_array($orientation,['S','SE','SW'],true)?'s':'ew');$uLimit=(float)$zone['uLimit'][$frame];$shgcLimit=(float)$zone['shgc'][$band];$maxGlazing=(float)$zone['maxGlazingPercent'];$effective=$this->round($shgc*(1-$shading));$glazing=$this->round($glass/$wall);$messages=[];
            if($uValue>$uLimit)$messages[]="U-value {$uValue} exceeds {$uLimit}";if($effective>$shgcLimit)$messages[]="Effective SHGC {$effective} exceeds {$shgcLimit}";if($glazing>$maxGlazing)$messages[]='Glazing '.$this->round($glazing*100).'% exceeds '.$this->round($maxGlazing*100).'% guide';
            $verdict=$messages===[]?'pass':(count($messages)>1?'fail':'watch');$results[]=['id'=>$id,'storey'=>$storey,'room'=>$this->text($element['room']??null,120,'invalid_fenestration_element'),'description'=>$this->text($element['description']??null,220,'invalid_fenestration_element'),'orientation'=>$orientation,'frameType'=>$frame,'verdict'=>$verdict,'messages'=>$messages===[]?['Element is within the checked seed limits']:$messages,'effectiveShgc'=>$effective,'glazingPercent'=>$glazing,'limits'=>['uLimit'=>$uLimit,'shgcLimit'=>$shgcLimit,'maxGlazingPercent'=>$maxGlazing]];
            $byStorey[$storey]??=['total'=>0,'pass'=>0,'watch'=>0,'fail'=>0];$byStorey[$storey]['total']++;$byStorey[$storey][$verdict]++;
        }
        $verdict=$this->verdict($results);$actions=[];foreach($results as$result)if($result['verdict']!=='pass')foreach($result['messages']as$message)$actions[]=$result['id'].': '.$message;
        return['tool'=>'fenestration_xa_calculator','title'=>'Fenestration XA Compliance Evidence Report','project'=>$project,'verdict'=>$verdict,'summary'=>[count($results).' fenestration elements checked',count(array_filter($results,static fn(array$r):bool=>$r['verdict']==='pass')).' pass',count($actions).' action findings','Climate zone '.$project['climateZone'].': '.$zone['name']],'actionCards'=>$actions,'results'=>$results,'byStorey'=>$byStorey,'limitations'=>'Pre-check against the selected rule source only. Shading factors are human-entered; no geometry is inferred.'];
    }

    /** @param array<string,mixed> $project @param mixed $rawAssemblies @param array<string,mixed> $rules @return array<string,mixed> */
    private function assemblies(array $project,mixed $rawAssemblies,array $rules): array
    {
        $assemblies=$this->items($rawAssemblies,'invalid_thermal_assemblies');$results=[];
        foreach($assemblies as$assembly){$type=$assembly['type']??null;if(!in_array($type,['roof','wall','floor'],true))throw new ComplianceException('invalid_thermal_assembly');$climate=$this->integer($assembly['climateZone']??null,1,6,'invalid_thermal_assembly');$zone=$this->zone($rules,$climate);$layers=$this->items($assembly['layers']??null,'invalid_thermal_layers');$layerResults=[];
            foreach($layers as$layer){$material=strtolower($this->text($layer['material']??null,100,'invalid_thermal_layer'));$library=$rules['materials'][$material]??null;$explicitR=$layer['rValue']??null;$explicitConductivity=$layer['conductivity']??null;$thickness=$this->nonNegative($layer['thicknessMm']??null,'invalid_thermal_layer');
                if($explicitR!==null)$r=$this->positive($explicitR,'invalid_thermal_layer');else{$conductivity=$explicitConductivity!==null?$this->positive($explicitConductivity,'invalid_thermal_layer'):(is_array($library)&&isset($library['conductivity'])?(float)$library['conductivity']:null);if($conductivity!==null)$r=($thickness/1000)/$conductivity;elseif(is_array($library)&&isset($library['rValue']))$r=(float)$library['rValue'];else throw new ComplianceException('unknown_thermal_material');}
                $layerResults[]=['material'=>$material,'thicknessMm'=>$thickness,'rValue'=>$this->round($r)];
            }
            $totalR=$this->round(array_sum(array_column($layerResults,'rValue')));$minR=(float)$zone['minR'][$type];$verdict=$totalR>=$minR?'pass':($totalR>=$minR*.85?'watch':'fail');$message=$verdict==='pass'?'Assembly meets the checked R-value target':"R {$totalR} below target {$minR}";$results[]=['id'=>$this->text($assembly['id']??null,60,'invalid_thermal_assembly'),'type'=>$type,'description'=>$this->text($assembly['description']??null,220,'invalid_thermal_assembly'),'climateZone'=>$climate,'area'=>$this->positive($assembly['area']??null,'invalid_thermal_assembly'),'verdict'=>$verdict,'totalR'=>$totalR,'minR'=>$minR,'uValue'=>$this->round(1/max($totalR,.01)),'layerResults'=>$layerResults,'message'=>$message];
        }
        $verdict=$this->verdict($results);$actions=array_values(array_map(static fn(array$r):string=>$r['id'].': '.$r['message'],array_filter($results,static fn(array$r):bool=>$r['verdict']!=='pass')));
        return['tool'=>'r_value_thermal_calculator','title'=>'R-Value / Thermal Assembly Evidence Report','project'=>$project,'verdict'=>$verdict,'summary'=>[count($results).' assemblies checked',count(array_filter($results,static fn(array$r):bool=>$r['verdict']==='pass')).' pass',count($actions).' require action'],'actionCards'=>$actions,'results'=>$results,'limitations'=>'Layer properties and thicknesses are human-entered. Results are pre-check evidence, not a competent-person declaration.'];
    }

    /** @param array<string,mixed> $project @param array<string,mixed> $input @param array<string,mixed> $rules @return array<string,mixed> */
    private function forms(array $project,array $input,array $rules): array
    {
        $occupancy=$this->text($input['occupancyClass']??null,40,'invalid_occupancy_class');$competent=$input['includesCompetentPersons']??null;if(!is_bool($competent))throw new ComplianceException('invalid_competent_person_flag');$parts=$input['parts']??null;if(!is_array($parts)||$parts===[]||array_filter($parts,static fn(mixed$v):bool=>!is_string($v)||trim($v)==='')!==[])throw new ComplianceException('invalid_sans_parts');
        $selected=[];foreach($rules['forms']as$form){if(in_array($form['id'],$parts,true)||array_intersect($form['parts'],$parts)!==[])$selected[]=$form;}
        if($selected===[])throw new ComplianceException('unknown_sans_parts');$evidence=[];foreach($selected as$form)foreach($form['evidence']as$item)$evidence[$item]=true;$warnings=[];if(!$competent&&in_array('A19',array_column($selected,'id'),true))$warnings[]='Competent person form selected but no competent persons are recorded.';
        return['tool'=>'sans_forms_checklist_builder','title'=>'SANS / NBR Forms and Evidence Checklist','project'=>$project,'occupancyClass'=>$occupancy,'verdict'=>$warnings===[]?'pass':'watch','summary'=>[count($selected).' forms selected',count($evidence).' evidence items required'],'actionCards'=>$warnings,'results'=>$selected,'requiredEvidence'=>array_keys($evidence),'limitations'=>'Checklist selection does not create, sign or submit a statutory form. Human review and current municipal requirements remain required.'];
    }

    /** @param array<string,mixed> $project @param mixed $rawSheets @return array<string,mixed> */
    private function drawings(array $project,mixed $rawSheets): array
    {
        $sheets=$this->items($rawSheets,'invalid_drawing_sheets');$numbers=[];foreach($sheets as$sheet)$numbers[]=$this->text($sheet['number']??null,100,'invalid_drawing_sheet');$results=[];$actions=[];
        foreach($sheets as$index=>$sheet){$number=$numbers[$index];$title=$this->text($sheet['title']??null,220,'invalid_drawing_sheet');$issues=[];$scale=$this->optionalText($sheet['scale']??null,40,'invalid_drawing_sheet');$revision=$this->optionalText($sheet['revision']??null,40,'invalid_drawing_sheet');if($scale===null)$issues[]='missing scale';if($revision===null)$issues[]='missing revision';$north=$this->boolean($sheet['hasNorthPoint']??null);$professional=$this->boolean($sheet['hasProfessionalBlock']??null);$dimensions=$this->boolean($sheet['hasDimensions']??null);$rooms=$this->boolean($sheet['hasRoomLabels']??null);if(!$north&&preg_match('/site|layout/i',$title)===1)$issues[]='site/layout sheet missing north point';if(!$professional)$issues[]='missing professional/signature block';if(!$dimensions)$issues[]='missing dimensions flag';if(!$rooms&&preg_match('/plan/i',$title)===1)$issues[]='plan lacks room labels flag';$references=$sheet['referencedSheets']??[];if(!is_array($references))throw new ComplianceException('invalid_drawing_sheet');foreach($references as$reference){$reference=$this->text($reference,100,'invalid_drawing_sheet');if(!in_array($reference,$numbers,true))$issues[]='unresolved sheet reference '.$reference;}$verdict=count($issues)>2?'fail':($issues===[]?'pass':'watch');$results[]=['sheet'=>$number,'title'=>$title,'verdict'=>$verdict,'issues'=>$issues];foreach($issues as$issue)$actions[]=$number.': '.$issue;}
        $verdict=in_array('fail',array_column($results,'verdict'),true)?'fail':(in_array('watch',array_column($results,'verdict'),true)?'watch':'pass');
        return['tool'=>'drawing_compliance_precheck','title'=>'Drawing Compliance Metadata Pre-check','project'=>$project,'verdict'=>$verdict,'summary'=>[count($results).' drawing sheets checked',count($actions).' human-review findings'],'actionCards'=>$actions,'results'=>$results,'limitations'=>'Pre-check only. No dimensions inferred, no drawing content parsed, and no missing evidence invented. Professional review required.'];
    }

    /** @return array<string,mixed> */
    private function project(mixed$raw): array{if(!is_array($raw))throw new ComplianceException('invalid_compliance_project');$type=$raw['buildingType']??null;if(!in_array($type,['residential','commercial','public'],true))throw new ComplianceException('invalid_compliance_project');return['projectName'=>$this->text($raw['projectName']??null,180,'invalid_compliance_project'),'reference'=>$this->text($raw['reference']??null,100,'invalid_compliance_project'),'climateZone'=>$this->integer($raw['climateZone']??null,1,6,'invalid_compliance_project'),'buildingType'=>$type,'storeys'=>$this->integer($raw['storeys']??null,1,100,'invalid_compliance_project'),'floorArea'=>$this->positive($raw['floorArea']??null,'invalid_compliance_project')];}
    /** @param array<string,mixed> $rules @return array<string,mixed> */
    private function zone(array$rules,int$zone): array{$item=$rules['climateZones'][(string)$zone]??$rules['climateZones'][$zone]??null;if(!is_array($item))throw new ComplianceException('invalid_rule_payload');return$item;}
    /** @return list<array<string,mixed>> */
    private function items(mixed$value,string$error): array{if(!is_array($value)||$value===[]||array_filter($value,static fn(mixed$item):bool=>!is_array($item))!==[])throw new ComplianceException($error);return array_values($value);}
    /** @param list<array<string,mixed>> $results */
    private function verdict(array$results): string{$verdicts=array_column($results,'verdict');return in_array('fail',$verdicts,true)?'fail':(in_array('watch',$verdicts,true)?'watch':'pass');}
    private function text(mixed$value,int$max,string$error): string{if(!is_string($value)||trim($value)===''||mb_strlen(trim($value))>$max)throw new ComplianceException($error);return trim($value);}
    private function optionalText(mixed$value,int$max,string$error): ?string{if($value===null||$value==='')return null;return$this->text($value,$max,$error);}
    private function positive(mixed$value,string$error): float{if(!is_numeric($value)||(float)$value<=0||!is_finite((float)$value))throw new ComplianceException($error);return(float)$value;}
    private function nonNegative(mixed$value,string$error): float{if(!is_numeric($value)||(float)$value<0||!is_finite((float)$value))throw new ComplianceException($error);return(float)$value;}
    private function range(mixed$value,float$min,float$max,string$error): float{$number=$this->nonNegative($value,$error);if($number<$min||$number>$max)throw new ComplianceException($error);return$number;}
    private function integer(mixed$value,int$min,int$max,string$error): int{if(filter_var($value,FILTER_VALIDATE_INT)===false||(int)$value<$min||(int)$value>$max)throw new ComplianceException($error);return(int)$value;}
    private function boolean(mixed$value): bool{if(!is_bool($value))throw new ComplianceException('invalid_drawing_sheet');return$value;}
    private function round(float$value): float{return round($value,2);}
}
