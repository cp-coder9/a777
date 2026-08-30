<?php

declare(strict_types=1);

namespace Architex\Platform\Municipal;

final class MunicipalReadinessRefinementPolicy
{
    public const APPLICATION_TYPES=[
        'building_plan','minor_works','consent_use','departure_relaxation','rezoning','subdivision',
        'consolidation','removal_restrictive_title_condition','occupancy_certificate',
    ];

    public const ADVISORY_STATEMENT='This Architex-backed submission pack is a professional-side readiness record. An Architex rules or AI-assisted check is advisory and does not constitute municipal approval or professional certification. Formal lodgement and statutory decisions remain with the municipality and appointed professionals.';

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function normaliseProfile(array $input):array
    {
        $application=$input['applicationType']??null;
        if(!is_string($application)||!in_array($application,self::APPLICATION_TYPES,true))throw new MunicipalReadinessException('invalid_municipal_profile_application_type');
        $municipality=$this->text($input['municipality']??null,180,'invalid_municipal_profile_municipality');
        $address=$this->text($input['address']??null,500,'invalid_municipal_profile_address');
        $erf=$this->optionalText($input['erf']??null,120);$farm=$this->optionalText($input['farm']??null,180);$portion=$this->optionalText($input['portion']??null,120);$sectional=$this->optionalText($input['sectionalTitle']??null,180);
        if($erf===null&&$farm===null&&$sectional===null)throw new MunicipalReadinessException('invalid_municipal_profile_property_identity');
        $fee=$input['municipalFeeStatus']??'not_applicable';
        if(!is_string($fee)||!in_array($fee,['not_applicable','placeholder','quoted','paid','verified'],true))throw new MunicipalReadinessException('invalid_municipal_profile_fee_status');
        $out=['applicationType'=>$application,'erf'=>$erf,'farm'=>$farm,'portion'=>$portion,'sectionalTitle'=>$sectional,'address'=>$address,'municipality'=>$municipality,'zoning'=>$this->optionalText($input['zoning']??null,180),'landUseAssumptions'=>$this->optionalText($input['landUseAssumptions']??null,2000),'overlays'=>$this->strings($input['overlays']??[]),'servitudes'=>$this->strings($input['servitudes']??[]),'municipalFeeStatus'=>$fee];
        foreach(['heritageFlag','environmentalFlag','ownershipConfirmed','agentAuthorised','publicParticipationRequired','noticeRequired']as$key){if(!is_bool($input[$key]??null))throw new MunicipalReadinessException('invalid_municipal_profile_boolean');$out[$key]=$input[$key];}
        return$out;
    }

    /** @param list<array<string,mixed>> $findings @param list<array<string,mixed>> $routes @return array<string,mixed> */
    public function workflow(array $findings,array $routes,bool $reviewApproved,bool $issued):array
    {
        $counts=['blockers'=>0,'warnings'=>0,'advisories'=>0];
        foreach($findings as$finding)if(($finding['status']??null)==='open'){$severity=$finding['severity']??null;if($severity==='blocker')$counts['blockers']++;elseif($severity==='warning')$counts['warnings']++;elseif($severity==='advisory')$counts['advisories']++;}
        $unsigned=0;foreach($routes as$route)if(($route['requiredBeforeIssue']??false)===true&&(($route['status']??null)!=='signed_off'||(($route['supervisingProfessionalRequired']??false)===true&&($route['supervisorSignedOff']??false)!==true)))$unsigned++;
        $status=$issued?'issued':($counts['blockers']>0?'blocked':($reviewApproved&&$unsigned===0?'ready_for_signoff':'in_review'));
        return['status'=>$status,'openFindings'=>$counts,'unsignedRequiredRoutes'=>$unsigned];
    }

    /** @param array<string,mixed> $facts @param list<array<string,mixed>> $findings @param list<array<string,mixed>> $routes @return array{eligible:bool,failures:list<string>} */
    public function issueGate(array $facts,array $findings,array $routes):array
    {
        $failures=[];
        if(($facts['matrixVerified']??false)!==true)$failures[]='verified_municipal_matrix_required';
        if(($facts['professionalReviewApproved']??false)!==true)$failures[]='professional_review_required';
        if(($facts['authorityConfirmed']??false)!==true)$failures[]='owner_or_agent_authority_required';
        if(array_filter($findings,static fn(array$f):bool=>($f['severity']??null)==='blocker'&&($f['status']??null)==='open'))$failures[]='open_blocker_findings';
        if(array_filter($routes,static fn(array$r):bool=>($r['requiredBeforeIssue']??false)===true&&($r['status']??null)!=='signed_off'))$failures[]='unsigned_professional_routes';
        if(array_filter($routes,static fn(array$r):bool=>($r['requiredBeforeIssue']??false)===true&&($r['supervisingProfessionalRequired']??false)===true&&($r['supervisorSignedOff']??false)!==true))$failures[]='candidate_supervision_required';
        return['eligible'=>$failures===[],'failures'=>$failures];
    }

    /** @param array<string,mixed> $input @return array{manifest:array<string,mixed>,canonicalJson:string,sha256:string} */
    public function buildManifest(array $input):array
    {
        $manifest=['standard'=>'architex-backed-submission-pack','standardVersion'=>'1.0.0','packId'=>$input['packId']??null,'packVersion'=>$input['packVersion']??null,'issuedAt'=>$input['issuedAt']??null,'cover'=>['project'=>$input['project']??[],'property'=>$input['profile']??[],'principalProfessional'=>$input['principalProfessional']??[]],'readinessStatement'=>self::ADVISORY_STATEMENT,'readiness'=>$input['readiness']??[],'documentIndex'=>$input['documents']??[],'drawingIndex'=>$input['drawings']??[],'checklist'=>$input['checklist']??[],'resolutionLog'=>$input['resolutionLog']??[],'signoffRegister'=>$input['signoffRegister']??[],'sourceRegister'=>$input['sources']??[],'audit'=>['assessmentSha256'=>$input['assessmentSha256']??null,'projectRecordId'=>$input['projectRecordId']??null]];
        $canonical=$this->canonicalJson($manifest);return['manifest'=>$manifest,'canonicalJson'=>$canonical,'sha256'=>hash('sha256',$canonical)];
    }

    private function text(mixed$value,int$max,string$error):string{$value=is_string($value)?trim($value):'';if($value===''||mb_strlen($value)>$max)throw new MunicipalReadinessException($error);return$value;}
    private function optionalText(mixed$value,int$max):?string{if($value===null||$value==='')return null;if(!is_string($value)||mb_strlen(trim($value))>$max)throw new MunicipalReadinessException('invalid_municipal_profile_text');$value=trim($value);return$value===''?null:$value;}
    /** @return list<string> */private function strings(mixed$value):array{if(!is_array($value)||count($value)>50)throw new MunicipalReadinessException('invalid_municipal_profile_list');$out=[];foreach($value as$item){$item=$this->text($item,180,'invalid_municipal_profile_list');$out[$item]=true;}$out=array_keys($out);sort($out,SORT_STRING);return$out;}
    private function canonicalJson(mixed$value):string{return json_encode($this->canonical($value),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION);}
    private function canonical(mixed$value):mixed{if(!is_array($value))return$value;if(!array_is_list($value))ksort($value,SORT_STRING);foreach($value as$key=>$item)$value[$key]=$this->canonical($item);return$value;}
}
