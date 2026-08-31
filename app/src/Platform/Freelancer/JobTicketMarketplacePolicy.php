<?php

declare(strict_types=1);

namespace Architex\Platform\Freelancer;

use DateTimeImmutable;
use DateTimeZone;

final class JobTicketMarketplacePolicy
{
    private const ROLES=[
        'client'=>['post'=>true,'accept'=>false,'expand'=>false,'supervise'=>false],
        'registered_bep'=>['post'=>true,'accept'=>true,'expand'=>true,'supervise'=>true],
        'freelancer_candidate'=>['post'=>false,'accept'=>true,'expand'=>false,'supervise'=>false],
        'freelancer_specialist'=>['post'=>false,'accept'=>true,'expand'=>false,'supervise'=>false],
        'contractor'=>['post'=>true,'accept'=>true,'expand'=>true,'supervise'=>false],
        'subcontractor'=>['post'=>true,'accept'=>true,'expand'=>false,'supervise'=>false],
    ];

    private const CATEGORIES=[
        'architectural_design'=>['keywords'=>['architect','architectural','building plan','floor plan','elevation','section','site plan','municipal submission','design'],'registrations'=>['SACAP'],'competence'=>'registered_professional','supervisor'=>null],
        'structural_engineering'=>['keywords'=>['structural','foundation','concrete','steel','loading','retaining wall','reinforcement'],'registrations'=>['ECSA'],'competence'=>'registered_professional','supervisor'=>null],
        'quantity_surveying'=>['keywords'=>['quantity surveyor','bills of quantities','boq','cost estimate','cost planning','valuation','final account'],'registrations'=>['SACQSP'],'competence'=>'registered_professional','supervisor'=>null],
        'town_planning'=>['keywords'=>['town planner','town planning','land use','zoning','rezoning','consent use','development application'],'registrations'=>['SACPLAN'],'competence'=>'registered_professional','supervisor'=>null],
        'production_drawings'=>['keywords'=>['production drawing','working drawing','drafting','draughting','cad','autocad','revit','bim','as-built drawing','shop drawing','drawing register'],'registrations'=>[],'competence'=>'candidate','supervisor'=>'registered_architect_or_engineer'],
        'site_inspection'=>['keywords'=>['site inspector','clerk of works','site supervision','inspection','quality control','snagging','defects','site monitoring'],'registrations'=>[],'competence'=>'specialist','supervisor'=>'registered_architect_engineer_cpm'],
        'contract_administration'=>['keywords'=>['contract admin','contract administrator','jbcc','nec','payment certificate','site instruction'],'registrations'=>[],'competence'=>'specialist','supervisor'=>'registered_qs_or_architect'],
        'document_control'=>['keywords'=>['document control','document controller','drawing register','transmittal','rfi tracker','submittal','filing'],'registrations'=>[],'competence'=>'specialist','supervisor'=>'any_registered_bep'],
        'energy_modelling'=>['keywords'=>['energy model','sans 10400','sans 10400-xa','energy compliance','thermal simulation','green star'],'registrations'=>[],'competence'=>'specialist','supervisor'=>'registered_architect_or_engineer'],
        'contracting'=>['keywords'=>['contractor','construction','building works','site works','renovation','alteration','new build'],'registrations'=>[],'competence'=>'any','supervisor'=>null],
        'subcontracting'=>['keywords'=>['subcontractor','plumbing','electrical','tiling','roofing','painting','plastering','waterproofing','carpentry','steel fixing'],'registrations'=>[],'competence'=>'any','supervisor'=>null],
    ];

    private const SUPERVISION=[
        'candidate_architect'=>['supervisorDiscipline'=>'registered_architect','compatible'=>['registered_architect'],'description'=>'Pr Arch - project or external supervisor'],
        'candidate_engineer'=>['supervisorDiscipline'=>'registered_engineer','compatible'=>['registered_engineer','registered_technologist_engineer'],'description'=>'Pr Eng or Pr Tech Eng - project or external supervisor'],
        'candidate_quantity_surveyor'=>['supervisorDiscipline'=>'registered_quantity_surveyor','compatible'=>['registered_quantity_surveyor'],'description'=>'Pr QS - project or external supervisor'],
        'candidate_planner'=>['supervisorDiscipline'=>'registered_planner','compatible'=>['registered_planner'],'description'=>'Pr Pln - project or external supervisor'],
        'candidate_landscape_architect'=>['supervisorDiscipline'=>'registered_landscape_architect','compatible'=>['registered_landscape_architect'],'description'=>'Pr LArch - project or external supervisor'],
        'candidate_interior_designer'=>['supervisorDiscipline'=>'registered_architect_or_interior_designer','compatible'=>['registered_architect','registered_interior_designer'],'description'=>'Discipline-aligned registered design professional'],
        'candidate_cpm'=>['supervisorDiscipline'=>'registered_cpm','compatible'=>['registered_cpm'],'description'=>'Pr CPM - project or external supervisor'],
        'candidate_land_surveyor'=>['supervisorDiscipline'=>'registered_land_surveyor','compatible'=>['registered_land_surveyor'],'description'=>'Registered land surveyor - project or external supervisor'],
        'draughtsperson_bim_modeller'=>['supervisorDiscipline'=>'registered_architect_or_engineer','compatible'=>['registered_architect','registered_engineer','registered_technologist_engineer'],'description'=>'Senior registered project professional'],
        'document_controller'=>['supervisorDiscipline'=>'any_registered_bep','compatible'=>['registered_architect','registered_engineer','registered_technologist_engineer','registered_quantity_surveyor','registered_planner','registered_landscape_architect','registered_cpm','registered_land_surveyor'],'description'=>'Lead consultant or project manager'],
        'energy_modeller'=>['supervisorDiscipline'=>'registered_architect_or_engineer','compatible'=>['registered_architect','registered_engineer','registered_technologist_engineer'],'description'=>'Discipline-aligned Pr Arch, Pr Eng or Pr Tech Eng'],
        'site_inspector'=>['supervisorDiscipline'=>'registered_architect_engineer_cpm','compatible'=>['registered_architect','registered_engineer','registered_technologist_engineer','registered_cpm'],'description'=>'Client or contractor registered professional'],
        'contract_admin'=>['supervisorDiscipline'=>'registered_qs_or_architect','compatible'=>['registered_quantity_surveyor','registered_architect'],'description'=>'Project QS or lead consultant'],
        'visualiser'=>['supervisorDiscipline'=>'registered_architect_or_technologist','compatible'=>['registered_architect','registered_architectural_technologist'],'description'=>'Registered design lead'],
        'site_assistant'=>['supervisorDiscipline'=>'any_registered_bep','compatible'=>['registered_architect','registered_engineer','registered_technologist_engineer','registered_quantity_surveyor','registered_cpm'],'description'=>'Registered project lead or site agent'],
        'graduate_trainee'=>['supervisorDiscipline'=>'any_registered_bep','compatible'=>['registered_architect','registered_engineer','registered_technologist_engineer','registered_quantity_surveyor','registered_planner','registered_landscape_architect','registered_cpm','registered_land_surveyor'],'description'=>'Mentor or registered project professional'],
    ];

    /** @return array{post:bool,accept:bool,expand:bool,supervise:bool} */
    public function roleCapabilities(string $role):array
    {
        if(!isset(self::ROLES[$role]))throw new JobTicketMarketplaceException('job_ticket_role_invalid');
        return self::ROLES[$role];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function normaliseTicket(array $input):array
    {
        $poster=$this->choice($input['posterType']??null,array_keys(self::ROLES),'job_ticket_poster_type_invalid');
        if(!$this->roleCapabilities($poster)['post'])throw new JobTicketMarketplaceException('job_ticket_poster_not_permitted');
        $min=$this->money($input['budgetMin']??null,'job_ticket_budget_invalid');$max=$this->money($input['budgetMax']??null,'job_ticket_budget_invalid');
        if($min>$max)throw new JobTicketMarketplaceException('job_ticket_budget_invalid');
        $currency=$this->choice($input['currency']??null,['ZAR','USD','EUR','GBP'],'job_ticket_currency_invalid');
        $deadline=$this->date($input['deadline']??null,'job_ticket_deadline_invalid');
        $invitation=$this->choice($input['invitationType']??null,['public','direct'],'job_ticket_invitation_type_invalid');
        $invited=$this->ids($input['invitedUserIds']??[],'job_ticket_invitees_invalid');
        if($invitation==='direct'&&$invited===[])throw new JobTicketMarketplaceException('job_ticket_direct_invitees_required');
        if($invitation==='public'&&$invited!==[])throw new JobTicketMarketplaceException('job_ticket_public_invitees_prohibited');
        $fee=$input['platformFeePercent']??null;if((!is_int($fee)&&!is_float($fee))||!is_finite((float)$fee)||(float)$fee<0||(float)$fee>100)throw new JobTicketMarketplaceException('job_ticket_platform_fee_invalid');
        $disclosure=$this->text($input['platformFeeDisclosure']??null,500,'job_ticket_platform_fee_disclosure_required');
        if(!str_contains(mb_strtolower($disclosure),'platform fee'))throw new JobTicketMarketplaceException('job_ticket_platform_fee_disclosure_required');
        return[
            'posterType'=>$poster,'title'=>$this->text($input['title']??null,220,'job_ticket_title_required'),
            'description'=>$this->text($input['description']??null,5000,'job_ticket_description_required'),
            'deliverables'=>$this->strings($input['deliverables']??[],100,220,'job_ticket_deliverables_invalid'),
            'budget'=>['min'=>$min,'max'=>$max,'currency'=>$currency],'deadline'=>$deadline,
            'location'=>$this->text($input['location']??null,220,'job_ticket_location_required'),
            'phase'=>$this->slug($input['phase']??null,80,'job_ticket_phase_invalid'),
            'requiredRegistrations'=>$this->strings($input['requiredRegistrations']??[],30,80,'job_ticket_registrations_invalid'),
            'invitationType'=>$invitation,'invitedUserIds'=>$invited,
            'platformFee'=>['percent'=>(float)$fee,'disclosure'=>$disclosure,'paymentExecutionClaimed'=>false],
        ];
    }

    /** @param array<string,mixed> $signals @return array<string,mixed> */
    public function classify(array $signals):array
    {
        $title=$this->text($signals['title']??null,220,'job_ticket_title_required');
        $description=$this->text($signals['description']??null,5000,'job_ticket_description_required');
        $deliverables=$this->strings($signals['deliverables']??[],100,220,'job_ticket_deliverables_invalid');
        $required=$this->strings($signals['requiredRegistrations']??[],30,80,'job_ticket_registrations_invalid');
        $parts=[mb_strtolower($title),mb_strtolower($description),mb_strtolower(implode(' ',$deliverables))];$scores=[];
        foreach(self::CATEGORIES as$category=>$definition){$score=0;$reasons=[];foreach($definition['keywords']as$keyword){foreach($parts as$index=>$text)if(str_contains($text,$keyword)){$score+=[5,2,4][$index];$reasons[$keyword]=true;}}$registrationHits=array_values(array_intersect($required,$definition['registrations']));if($registrationHits!==[]){$score+=8*count($registrationHits);foreach($registrationHits as$hit)$reasons['registration '.$hit]=true;}if($score>0)$scores[]=['category'=>$category,'score'=>$score,'reasons'=>array_keys($reasons)];}
        usort($scores,static fn(array$a,array$b):int=>$b['score']<=>$a['score']?:strcmp($a['category'],$b['category']));
        $top=$scores[0]??null;$second=$scores[1]??null;$category=$top['category']??'unclassified';$definition=self::CATEGORIES[$category]??['registrations'=>[],'competence'=>'any','supervisor'=>null];
        $registrations=$required!==[]?$required:$definition['registrations'];sort($registrations,SORT_STRING);
        $payload=['taxonomyVersion'=>'architex-job-ticket-v1','primaryCategory'=>$category,'subCategory'=>$second['category']??'general','requiredCompetence'=>$required!==[]?'registered_professional':$definition['competence'],'requiredRegistrations'=>$registrations,'suggestedSupervisorDiscipline'=>$definition['supervisor'],'confidence'=>$top===null?0:min(99,40+($top['score']*3)),'reasons'=>$top['reasons']??[],'classifiedBy'=>'deterministic_advisory','professionalDecision'=>false,'automaticAward'=>false];
        return[...$payload,'sha256'=>$this->canonicalEvidence('classification',$payload)['sha256']];
    }

    /** @param array<string,mixed> $previous @param array<string,mixed> $input @return array<string,mixed> */
    public function normaliseClassificationOverride(array $previous,array $input):array
    {
        $category=$this->choice($input['primaryCategory']??null,array_keys(self::CATEGORIES),'job_ticket_classification_category_invalid');
        $payload=['taxonomyVersion'=>'architex-job-ticket-v1','primaryCategory'=>$category,'subCategory'=>$this->slug($input['subCategory']??null,100,'job_ticket_classification_subcategory_invalid'),'requiredCompetence'=>$this->choice($input['requiredCompetence']??null,['registered_professional','candidate','specialist','any'],'job_ticket_competence_invalid'),'requiredRegistrations'=>$this->strings($input['requiredRegistrations']??[],30,80,'job_ticket_registrations_invalid'),'suggestedSupervisorDiscipline'=>$input['suggestedSupervisorDiscipline']===null?null:$this->slug($input['suggestedSupervisorDiscipline']??null,100,'job_ticket_supervisor_discipline_invalid'),'confidence'=>null,'reasons'=>[],'classifiedBy'=>'human_override_after_advisory','professionalDecision'=>true,'automaticAward'=>false,'reason'=>$this->text($input['reason']??null,1000,'job_ticket_classification_override_reason_required'),'replacesSha256'=>$this->hash($previous['sha256']??null,'job_ticket_classification_previous_hash_invalid')];
        return[...$payload,'sha256'=>$this->canonicalEvidence('classification_override',$payload)['sha256']];
    }

    /** @param list<string> $invited */
    public function canViewTicket(string $invitationType,array $invited,?string $userId):bool
    {
        if($invitationType==='public')return true;if($invitationType!=='direct')throw new JobTicketMarketplaceException('job_ticket_invitation_type_invalid');
        return$userId!==null&&in_array($userId,$invited,true);
    }

    /** @param array<string,mixed> $classification @param list<array<string,mixed>> $profiles @return list<array<string,mixed>> */
    public function matchRecipients(array $classification,string $location,array $profiles):array
    {
        $category=$this->choice($classification['primaryCategory']??null,[...array_keys(self::CATEGORIES),'unclassified'],'job_ticket_classification_category_invalid');$location=mb_strtolower(trim($location));$matches=[];
        foreach($profiles as$profile){$role=$profile['role']??null;if(!is_string($role)||!isset(self::ROLES[$role])||!self::ROLES[$role]['accept'])continue;$score=0;$reasons=[];$disciplines=is_array($profile['disciplines']??null)?$profile['disciplines']:[];$categoryWords=explode('_',$category);foreach($disciplines as$discipline)if(is_string($discipline)&&($discipline===$category||array_filter($categoryWords,static fn(string$word):bool=>str_contains(mb_strtolower($discipline),$word)))){$score+=40;$reasons[]='discipline match';break;}$competence=$classification['requiredCompetence']??'any';if($competence==='registered_professional'&&$role==='registered_bep'){$score+=25;$reasons[]='registered role match';}elseif($competence==='candidate'&&$role==='freelancer_candidate'){$score+=25;$reasons[]='candidate role match';}elseif($competence==='specialist'&&in_array($role,['freelancer_specialist','registered_bep'],true)){$score+=20;$reasons[]='specialist role match';}elseif($competence==='any'){$score+=10;$reasons[]='open competence';}$areas=is_array($profile['serviceAreas']??null)?$profile['serviceAreas']:[];if(array_filter($areas,static fn(mixed$area):bool=>is_string($area)&&str_contains($location,mb_strtolower($area)))){$score+=20;$reasons[]='service area match';}if(($profile['verificationStatus']??null)==='verified'){$score+=10;$reasons[]='verified public evidence';}if(($profile['capacityStatus']??null)==='available'){$score+=5;$reasons[]='available capacity';}if($score<25)continue;$matches[]=['userId'=>(string)($profile['userId']??''),'role'=>$role,'score'=>$score,'reasons'=>$reasons,'advisoryOnly'=>true,'automaticAward'=>false];}
        usort($matches,static fn(array$a,array$b):int=>$b['score']<=>$a['score']?:strcmp($a['userId'],$b['userId']));return array_slice($matches,0,5);
    }

    /** @return array<string,mixed> */
    public function supervisionRule(string $discipline):array
    {
        if(!isset(self::SUPERVISION[$discipline]))throw new JobTicketMarketplaceException('freelancer_discipline_invalid');
        return[...self::SUPERVISION[$discipline],'gateRequired'=>true,'independentAppointmentPermitted'=>false];
    }

    public function supervisorCompatible(string $freelancerDiscipline,string $supervisorDiscipline):bool
    {
        return in_array($supervisorDiscipline,$this->supervisionRule($freelancerDiscipline)['compatible'],true);
    }

    /** @param array<string,mixed> $payload @return array{canonicalJson:string,sha256:string} */
    public function canonicalEvidence(string $kind,array $payload):array
    {
        if(!in_array($kind,['classification','classification_override','quotation','timesheet','claim','deliverable_revision','supervisor_signoff','payment_evidence'],true))throw new JobTicketMarketplaceException('job_ticket_evidence_kind_invalid');
        $canonical=$this->canonicalJson(['kind'=>$kind,'schemaVersion'=>'1.0.0','payload'=>$payload]);return['canonicalJson'=>$canonical,'sha256'=>hash('sha256',$canonical)];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function normalisePaymentEvidence(array $input):array
    {
        $reference=$this->text($input['reference']??null,180,'freelancer_payment_reference_required');$date=$this->date($input['paidAt']??null,'freelancer_payment_date_invalid');$note=$this->text($input['note']??null,1000,'freelancer_payment_note_required');$combined=mb_strtolower($reference.' '.$note);
        foreach(['bank confirmed','guaranteed','paid automatically','ai paid','escrow released']as$claim)if(str_contains($combined,$claim))throw new JobTicketMarketplaceException('freelancer_payment_claim_prohibited');
        $payload=['reference'=>$reference,'paidAt'=>$date,'note'=>$note,'verificationBoundary'=>'recorded_evidence_not_bank_confirmation','paymentExecutionClaimed'=>false];return[...$payload,'sha256'=>$this->canonicalEvidence('payment_evidence',$payload)['sha256']];
    }

    /** @param array<string,mixed> $facts @return array{eligible:bool,failures:list<string>} */
    public function externalIssueGate(array $facts):array
    {
        $failures=[];if(($facts['engagementActive']??false)!==true)$failures[]='active_engagement_required';if(($facts['routeLocked']??false)!==true)$failures[]='locked_supervision_route_required';if(($facts['currentRevisionSignedOff']??false)!==true)$failures[]='current_revision_supervisor_signoff_required';return['eligible'=>$failures===[],'failures'=>$failures];
    }

    /** @param array<string,mixed> $facts @return array{eligible:true,failures:list<string>} */
    public function completionGate(array $facts):array
    {
        $failures=[];if(($facts['deliverablesSignedOff']??false)!==true)$failures[]='deliverable_signoff_required';if(($facts['claimsResolved']??false)!==true)$failures[]='claims_must_be_resolved';if(($facts['humanAccepted']??false)!==true)$failures[]='human_completion_acceptance_required';if($failures!==[])throw new JobTicketMarketplaceException($failures[0]);return['eligible'=>true,'failures'=>[]];
    }

    private function text(mixed$value,int$max,string$error):string{if(!is_string($value)||($value=trim($value))===''||mb_strlen($value)>$max)throw new JobTicketMarketplaceException($error);return$value;}
    /** @param list<string> $allowed */private function choice(mixed$value,array$allowed,string$error):string{if(!is_string($value)||!in_array($value,$allowed,true))throw new JobTicketMarketplaceException($error);return$value;}
    private function slug(mixed$value,int$max,string$error):string{$value=$this->text($value,$max,$error);if(preg_match('/\A[a-z0-9][a-z0-9_-]*\z/',$value)!==1)throw new JobTicketMarketplaceException($error);return$value;}
    private function money(mixed$value,string$error):float{if((!is_int($value)&&!is_float($value))||!is_finite((float)$value)||(float)$value<0||(float)$value>999999999999.99)throw new JobTicketMarketplaceException($error);return round((float)$value,2);}
    private function date(mixed$value,string$error):string{if(!is_string($value)||preg_match('/\A\d{4}-\d{2}-\d{2}\z/',$value)!==1)throw new JobTicketMarketplaceException($error);$date=DateTimeImmutable::createFromFormat('!Y-m-d',$value,new DateTimeZone('UTC'));if(!$date||$date->format('Y-m-d')!==$value)throw new JobTicketMarketplaceException($error);return$value;}
    /** @return list<string> */private function strings(mixed$value,int$limit,int$max,string$error):array{if(!is_array($value)||count($value)>$limit)throw new JobTicketMarketplaceException($error);$out=[];foreach($value as$item){$item=$this->text($item,$max,$error);$out[$item]=true;}$out=array_keys($out);sort($out,SORT_STRING);return$out;}
    /** @return list<string> */private function ids(mixed$value,string$error):array{if(!is_array($value)||count($value)>100)throw new JobTicketMarketplaceException($error);$out=[];foreach($value as$id){if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/',$id)!==1)throw new JobTicketMarketplaceException($error);$out[$id]=true;}$out=array_keys($out);sort($out,SORT_STRING);return$out;}
    private function hash(mixed$value,string$error):string{if(!is_string($value)||preg_match('/\A[a-f0-9]{64}\z/',$value)!==1)throw new JobTicketMarketplaceException($error);return$value;}
    private function canonicalJson(mixed$value):string{return json_encode($this->canonical($value),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION);}
    private function canonical(mixed$value):mixed{if(!is_array($value))return$value;if(!array_is_list($value))ksort($value,SORT_STRING);foreach($value as$key=>$item)$value[$key]=$this->canonical($item);return$value;}
}
