<?php

declare(strict_types=1);

namespace Architex\Platform\Toolbox;

use DateTimeImmutable;

final readonly class StandaloneRecordToolService
{
    /** @return array<string,array{name:string,category:string,route:string,tags:list<string>,inputSchema:array<string,string>,outputSchema:array<string,string>}> */
    public function definitions(): array
    {
        $rows=[
            ['drawing_register','Drawing Register','document_control','drawing-register',['drawing','revision','register'],['registerTitle'=>'string','drawingNumbers'=>'lines','revision'=>'string','issueStatus'=>'string']],
            ['technical_brief','Technical Brief Editor','briefing','technical-brief',['brief','scope','deliverables'],['title'=>'string','scope'=>'text','assumptions'=>'lines','exclusions'=>'lines','deliverables'=>'lines']],
            ['doc_control_issue','Document Control Issue Sheet','document_control','document-issue',['document','transmittal','tracking'],['issueNumber'=>'string','recipients'=>'lines','documents'=>'lines','purpose'=>'text']],
            ['cpd_standalone','CPD Assessment','cpd','cpd-assessment',['cpd','credits','development'],['activityTitle'=>'string','provider'=>'string','completedOn'=>'date','hours'=>'positive_number','reflection'=>'text']],
            ['soft_cost_estimator','Soft Cost Estimator','fee_calculator','soft-cost-estimator',['cost','professional','municipal'],['constructionValue'=>'money','professionalPercent'=>'percentage','municipalCosts'=>'money','contingencyPercent'=>'percentage']],
            ['brief_wizard','Guided Brief Wizard','briefing','guided-brief',['brief','requirements','outcomes'],['briefTitle'=>'string','outcomes'=>'text','siteConstraints'=>'text','budget'=>'money','timeline'=>'text']],
            ['proposal_comparison','BEP Proposal Comparison','proposal','proposal-comparison',['proposal','comparison','selection'],['proposalAName'=>'string','proposalAFee'=>'money','proposalAScope'=>'text','proposalBName'=>'string','proposalBFee'=>'money','proposalBScope'=>'text']],
            ['progress_viewer','Progress Report Viewer','general','progress-report',['progress','report','status'],['projectReference'=>'string','reportDate'=>'date','progressPercent'=>'percentage','summary'=>'text']],
            ['payment_dashboard','Payment Status Dashboard','payment','payment-status',['payment','claim','tracking'],['reference'=>'string','amount'=>'money','paymentStatus'=>'string','dueDate'=>'date']],
            ['rfi_generator','RFI / Site Instruction Generator','site_management','rfi-generator',['rfi','instruction','query'],['reference'=>'string','subject'=>'string','question'=>'text','requiredBy'=>'date','details'=>'text']],
            ['workforce_timesheet','Workforce Timesheet / Payroll Export','workforce','workforce-timesheet',['timesheet','labour','payroll'],['employee'=>'string','periodStart'=>'date','periodEnd'=>'date','regularHours'=>'non_negative_number','overtimeHours'=>'non_negative_number','hourlyRate'=>'money']],
            ['plant_register','Plant & Equipment Register','plant_equipment','plant-register',['plant','equipment','asset'],['assetReference'=>'string','description'=>'string','hireRate'=>'money','serviceDue'=>'date','utilisationPercent'=>'percentage']],
            ['tender_bid_bench','Tender / Bid Workbench','tendering','tender-bid',['tender','bid','pricing'],['tenderReference'=>'string','client'=>'string','baseCost'=>'money','overheadPercent'=>'percentage','profitPercent'=>'percentage','qualifications'=>'lines']],
            ['snag_creator','Snag List Creator','site_management','snag-list',['snag','defect','inspection'],['reference'=>'string','location'=>'string','description'=>'text','dueDate'=>'date','responsibleParty'=>'string']],
            ['material_procurement','Material Procurement / Order List','procurement','material-procurement',['material','procurement','order'],['listTitle'=>'string','items'=>'lines','budget'=>'money','requiredBy'=>'date']],
            ['payment_claim_builder','Payment Claim Builder','payment','payment-claim',['payment','claim','evidence'],['claimReference'=>'string','grossAmount'=>'money','previousCertified'=>'money','retentionPercent'=>'percentage','evidenceReferences'=>'lines']],
            ['shop_drawing_submission','Shop Drawing & Sample Submission','document_control','shop-drawing-submission',['shop-drawing','sample','submission'],['reference'=>'string','submissionType'=>'string','title'=>'string','documentReferences'=>'lines','submittedOn'=>'date']],
            ['package_scope_viewer','Package Scope Viewer','general','package-scope',['package','scope','deliverables'],['packageReference'=>'string','scope'=>'text','deliverables'=>'lines','paymentTerms'=>'text']],
            ['snag_evidence_upload','Snag / Closeout Evidence Upload','closeout','snag-evidence',['snag','closeout','evidence'],['snagReference'=>'string','evidenceReferences'=>'lines','completedOn'=>'date','completionStatement'=>'text']],
            ['rfi_response','RFI / Site Instruction Response','site_management','rfi-response',['rfi','response','instruction'],['rfiReference'=>'string','responseReference'=>'string','response'=>'text','respondedOn'=>'date']],
            ['catalogue_manager','Catalogue / Product Data Manager','supplier','catalogue-manager',['catalogue','product','supplier'],['productCode'=>'string','productName'=>'string','unitPrice'=>'money','leadTimeDays'=>'positive_number','availability'=>'string']],
            ['quote_response','Quote Response Form','supplier','quote-response',['quote','rfq','pricing'],['rfqReference'=>'string','quoteNumber'=>'string','quantity'=>'positive_number','unitRate'=>'money','validUntil'=>'date','qualifications'=>'lines']],
            ['delivery_note','Delivery Note Builder','supplier','delivery-note',['delivery','goods','pod'],['noteNumber'=>'string','poReference'=>'optional_string','recipient'=>'string','items'=>'lines','deliveredOn'=>'date']],
            ['warranty_upload','Warranty Certificate Uploader','closeout','warranty-certificate',['warranty','certificate','documentation'],['certificateReference'=>'string','product'=>'string','expiresOn'=>'date','evidenceReferences'=>'lines']],
            ['cad_upload_check','CAD / BIM File Upload + Drawing Checker','drawing','cad-bim-check',['cad','bim','drawing'],['fileReferences'=>'lines','discipline'=>'string','revision'=>'string','checkScope'=>'lines']],
            ['freelancer_timesheet','Timesheet / Claim Builder','freelancer','freelancer-timesheet',['timesheet','claim','hours'],['worker'=>'string','periodStart'=>'date','periodEnd'=>'date','hours'=>'non_negative_number','hourlyRate'=>'money','deliverableNotes'=>'text']],
            ['deliverable_submission','Deliverable Submission with Sign-off','freelancer','deliverable-submission',['deliverable','submission','signoff'],['reference'=>'string','title'=>'string','evidenceReferences'=>'lines','submittedOn'=>'date','supervisor'=>'string']],
            ['freelancer_resource_centre','Resource Centre / Checklists','resource_centre','resource-checklist',['resource','checklist','template'],['checklistTitle'=>'string','items'=>'lines','notes'=>'text']],
            ['admin_governance','Governance Console','admin_governance','governance-review',['admin','governance','oversight'],['reviewTitle'=>'string','scope'=>'text','findings'=>'lines','reviewedOn'=>'date']],
            ['audit_trail_viewer','Audit Trail Viewer','admin_governance','audit-review',['audit','history','compliance'],['dateFrom'=>'date','dateTo'=>'date','actionFilter'=>'optional_string','userReference'=>'optional_string','purpose'=>'text']],
            ['ai_review_queue','AI Review Queue','admin_governance','ai-review',['ai','review','governance'],['outputReference'=>'string','decision'=>'string','reviewNote'=>'text','reviewedOn'=>'date']],
            ['payment_rate_config','Payment Rate Configurator','admin_governance','payment-rate-config',['payment','rate','config'],['configName'=>'string','platformFeePercent'=>'percentage','escrowFeePercent'=>'percentage','effectiveDate'=>'date','reason'=>'text']],
            ['user_verification_console','User Verification Console','admin_governance','user-verification',['verification','identity','registration'],['userReference'=>'string','verificationType'=>'string','verificationStatus'=>'string','evidenceReferences'=>'lines','reviewedOn'=>'date']],
            ['fee_tariff_editor','Fee / Tariff Table Editor','admin_governance','fee-tariff-editor',['fee','tariff','editor'],['tariffName'=>'string','profession'=>'string','effectiveDate'=>'date','basePercent'=>'percentage','notes'=>'text']],
        ];
        $out=[];foreach($rows as[$id,$name,$category,$slug,$tags,$input])$out[$id]=['name'=>$name,'category'=>$category,'route'=>'/toolbox/standalone/'.$slug,'tags'=>$tags,'inputSchema'=>$input,'outputSchema'=>['title'=>'string','status'=>'string','recordChecksum'=>'sha256']];return$out;
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function calculate(string $toolId,array $input): array
    {
        $definition=$this->definitions()[$toolId]??null;if($definition===null)throw new ToolException('unknown_tool');$normalized=[];foreach($definition['inputSchema']as$key=>$type)$normalized[$key]=$this->value($input[$key]??null,$type);
        $special=match($toolId){
            'soft_cost_estimator'=>$this->softCosts($normalized),
            'workforce_timesheet'=>$this->timesheet($normalized,true),
            'freelancer_timesheet'=>$this->timesheet($normalized,false),
            'tender_bid_bench'=>$this->tender($normalized),
            'payment_claim_builder'=>$this->claim($normalized),
            'quote_response'=>['subtotalCents'=>$this->cents($normalized['quantity']*$normalized['unitRate'])],
            'catalogue_manager'=>['unitPriceCents'=>$this->cents($normalized['unitPrice'])],
            'payment_rate_config'=>['platformFeeBasisPoints'=>(int)round($normalized['platformFeePercent']*100),'escrowFeeBasisPoints'=>(int)round($normalized['escrowFeePercent']*100)],
            'fee_tariff_editor'=>['baseRateBasisPoints'=>(int)round($normalized['basePercent']*100)],
            default=>[],
        };
        $title=$this->title($definition['name'],$normalized);$recordType=match($toolId){'rfi_generator'=>'rfi','rfi_response'=>'rfi_response','doc_control_issue'=>'document_issue','drawing_register'=>'drawing_register','technical_brief','brief_wizard'=>'brief','payment_claim_builder'=>'payment_claim',default=>$toolId};$canonical=json_encode(['toolId'=>$toolId,'input'=>$normalized],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        return['tool'=>$toolId,'recordType'=>$recordType,'title'=>$title,'reference'=>$this->reference($normalized),'status'=>'draft','input'=>$normalized]+$special+['recordChecksum'=>hash('sha256',$canonical),'humanEntered'=>true,'issueEligible'=>false,'advisoryOnly'=>true,'limitations'=>'Human-entered standalone record. Source facts, contractual effect, compliance, payment, approval and professional responsibility require authorised review.'];
    }

    /** @param array<string,mixed> $values @return array<string,int|float> */
    private function softCosts(array $values):array{$professional=$this->cents($values['constructionValue']*$values['professionalPercent']/100);$municipal=$this->cents($values['municipalCosts']);$base=$professional+$municipal;$contingency=(int)round($base*$values['contingencyPercent']/100);return['constructionValueCents'=>$this->cents($values['constructionValue']),'professionalFeesCents'=>$professional,'municipalCostsCents'=>$municipal,'contingencyCents'=>$contingency,'totalCents'=>$base+$contingency];}
    /** @param array<string,mixed> $values @return array<string,int|float> */
    private function timesheet(array $values,bool $overtime):array{$regular=$overtime?$values['regularHours']:$values['hours'];$extra=$overtime?$values['overtimeHours']:0;$rate=$values['hourlyRate'];return['regularAmountCents'=>$this->cents($regular*$rate),'overtimeAmountCents'=>$this->cents($extra*$rate*1.5),'overtimeMultiplier'=>1.5,'totalCents'=>$this->cents(($regular+$extra*1.5)*$rate)];}
    /** @param array<string,mixed> $values @return array<string,int> */
    private function tender(array $values):array{$base=$this->cents($values['baseCost']);$overhead=(int)round($base*$values['overheadPercent']/100);$profit=(int)round(($base+$overhead)*$values['profitPercent']/100);return['baseCostCents'=>$base,'overheadCents'=>$overhead,'profitCents'=>$profit,'totalCents'=>$base+$overhead+$profit];}
    /** @param array<string,mixed> $values @return array<string,int> */
    private function claim(array $values):array{$gross=$this->cents($values['grossAmount']);$previous=$this->cents($values['previousCertified']);if($previous>$gross)throw new ToolException('invalid_tool_input');$current=$gross-$previous;$retention=(int)round($current*$values['retentionPercent']/100);return['grossAmountCents'=>$gross,'previousCertifiedCents'=>$previous,'currentClaimCents'=>$current,'retentionCents'=>$retention,'netClaimCents'=>$current-$retention];}
    private function cents(float|int $value):int{$cents=(int)round($value*100);if($cents<0||$cents>9_000_000_000_000)throw new ToolException('invalid_tool_input');return$cents;}
    private function title(string $fallback,array $values):string{foreach(['title','registerTitle','briefTitle','activityTitle','reviewTitle','productName','checklistTitle','subject']as$key)if(is_string($values[$key]??null)&&$values[$key]!=='')return$values[$key];return$fallback;}
    private function reference(array $values):?string{foreach(['reference','issueNumber','projectReference','tenderReference','claimReference','packageReference','snagReference','rfiReference','rfqReference','quoteNumber','noteNumber','certificateReference','outputReference','userReference','tariffName','configName','productCode']as$key)if(is_string($values[$key]??null)&&$values[$key]!=='')return$values[$key];return null;}
    private function value(mixed$value,string$type):mixed
    {
        if($type==='optional_string'&&($value===null||$value===''))return null;
        if(in_array($type,['string','text','optional_string'],true)){if(!is_string($value)||trim($value)===''||mb_strlen(trim($value))>($type==='text'?5000:500))throw new ToolException('invalid_tool_input');return trim($value);}
        if($type==='date'){if(!is_string($value))throw new ToolException('invalid_tool_input');$date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);if(!$date instanceof DateTimeImmutable||$date->format('Y-m-d')!==$value)throw new ToolException('invalid_tool_input');return$value;}
        if($type==='lines'){if(is_string($value))$value=preg_split('/\R/u',$value,-1,PREG_SPLIT_NO_EMPTY);if(!is_array($value)||$value===[]||count($value)>200)throw new ToolException('invalid_tool_input');$out=[];foreach($value as$item){if(!is_string($item)||trim($item)===''||mb_strlen(trim($item))>1000)throw new ToolException('invalid_tool_input');$out[]=trim($item);}return$out;}
        if(in_array($type,['positive_number','non_negative_number','money','percentage'],true)){if(!is_int($value)&&!is_float($value)&&!(is_string($value)&&is_numeric($value)))throw new ToolException('invalid_tool_input');$number=round((float)$value,4);$minimum=$type==='positive_number'?0.0001:0;if(!is_finite($number)||$number<$minimum||$number>1_000_000_000||($type==='percentage'&&$number>100))throw new ToolException('invalid_tool_input');return$number;}
        throw new ToolException('invalid_tool_definition');
    }
}
