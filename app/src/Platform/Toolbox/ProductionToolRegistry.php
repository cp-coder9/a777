<?php
declare(strict_types=1);
namespace Architex\Platform\Toolbox;
use Architex\Platform\Compliance\ComplianceCalculatorService;
use Architex\Platform\Engineering\EngineeringCalculatorException;
use Architex\Platform\Engineering\EngineeringCalculatorService;
use Architex\Platform\DrawingCompliance\DrawingComplianceService;
use Architex\Platform\Fees\FeeCalculatorService;
final readonly class ProductionToolRegistry
{
    /** Discovery describes implemented entry points; it never bypasses endpoint or professional gates. */
    private const WORKSPACE_PERMISSIONS = [
        'bom'=>'tools.use','xa'=>'tools.use','engineering_calc'=>'tools.use',
        'drawing_compliance_navigator'=>'tools.use','admin_review'=>'verification.review',
    ];
    private const NATIVE_BOUNDARIES = [
        'bom'=>'Advisory BoQ inputs, saved runs and checksum-verified JSON/CSV/HTML artifacts are connected. Procurement packages open in their own governed workspace. QS certification and legacy tender/PDF generation are not provided here.',
        'safety'=>'Safety inspection drafts and recorded plan evidence use the selected project. Permits, incidents, inductions and hazard registers remain unavailable; these records are not certification or permission to work.',
        'feedback'=>'Contextual feedback submission and user-scoped history persist. Roadmap clustering and product prioritisation are not authoritative workflows.',
        'project_explorer'=>'Search the bounded project-record projection and document metadata. Controlled document drafts use the document workflow; this is not an asset-wide archive or content search.',
        'council_navigator'=>'Prepare and inspect municipal submission-readiness categories. The governed drawing navigator is a separate native entry. No council comment feed or authority approval is inferred.',
        'compliance_hub'=>'Record and independently review compliance evidence. Drawing advisory checks and governed drawing workspaces do not certify compliance; source versions and professional responsibility remain mandatory.',
        'municipal_tracker'=>'Prepare internal submission-readiness assessments and accountable professional review. This does not lodge an authority submission, record an authority decision automatically or issue a certificate.',
        'approvals_queue'=>'Inspect mixed source decisions and missing-record tasks, then open the source workflow. Resolving an inbox item is not approving its source record or deploying a release.',
        'admin_review'=>'Review independent registration evidence with a reason and explicit confirmation, and inspect your own verification status separately. Membership scope is tenant-bound; self-review and unsupported external verification remain protected.',
    ];
    private const NATIVE_ACTIONS = [
        'bom'=>[['id'=>'save-takeoff','label'=>'Save advisory BoQ takeoff','permission'=>'tools.use','humanGate'=>'review','target'=>'bom'],['id'=>'download-advisory-output','label'=>'Download saved advisory output','permission'=>'tools.use','humanGate'=>'review','target'=>'bom'],['id'=>'procure','label'=>'Open procurement packages','permission'=>'projects.read','humanGate'=>'client_and_professional_approval','target'=>'rfq_marketplace']],
        'safety'=>[['id'=>'draft-safety-inspection','label'=>'Draft safety inspection','permission'=>'projects.write','humanGate'=>'professional_review','target'=>'safety'],['id'=>'evidence','label'=>'Open compliance evidence','permission'=>'projects.read','humanGate'=>'professional_review','target'=>'compliance_hub']],
        'feedback'=>[['id'=>'submit-feedback','label'=>'Submit contextual feedback','permission'=>'projects.read','humanGate'=>'none','target'=>'feedback']],
        'project_explorer'=>[['id'=>'draft-document','label'=>'Create controlled document draft','permission'=>'projects.write','humanGate'=>'document_review','target'=>'documents_drawings']],
        'council_navigator'=>[['id'=>'readiness','label'=>'Prepare submission readiness','permission'=>'projects.write','humanGate'=>'professional_review','target'=>'municipal_tracker'],['id'=>'drawing-precheck','label'=>'Open governed drawing navigator','permission'=>'tools.use','humanGate'=>'professional_review','target'=>'drawing_compliance_navigator']],
        'compliance_hub'=>[['id'=>'compliance-evidence','label'=>'Record compliance evidence','permission'=>'projects.write','humanGate'=>'professional_review','target'=>'compliance_hub'],['id'=>'drawing-precheck','label'=>'Open governed drawing navigator','permission'=>'tools.use','humanGate'=>'professional_review','target'=>'drawing_compliance_navigator']],
        'municipal_tracker'=>[['id'=>'readiness','label'=>'Prepare submission readiness','permission'=>'projects.write','humanGate'=>'professional_review','target'=>'municipal_tracker']],
        'approvals_queue'=>[['id'=>'inspect-decisions','label'=>'Inspect source decisions','permission'=>'projects.read','humanGate'=>'source_record_review','target'=>'approvals_queue']],
        'admin_review'=>[['id'=>'review-verification','label'=>'Review independent registration evidence','permission'=>'verification.review','humanGate'=>'independent_human_review','target'=>'admin_review']],
    ];

    /** @return array{read:bool,write:bool,review:bool,permission:string} */
    public static function workspaceAccess(string $id,string $role):array
    {
        $permissions=new \Architex\Platform\Authorization\PermissionService();
        $path=dirname(__DIR__,3).'/public/assets/tool-registry.json';
        $registry=json_decode((string)file_get_contents($path),true);
        $known=in_array($id,array_column($registry['tools']??[],'id'),true);
        $permission=self::WORKSPACE_PERMISSIONS[$id]??'projects.read';
        $read=$known&&$permissions->allows($role,$permission);
        return ['read'=>$read,'write'=>$read&&$permissions->allows($role,isset(self::WORKSPACE_PERMISSIONS[$id])&&$permission==='tools.use'?'tools.use':'projects.write'),'review'=>$read&&$permissions->allows($role,'verification.review'),'permission'=>$permission];
    }

    /** @return list<array<string,mixed>> */
    public static function workspaceCatalogue(string $role,?string $profileRole=null):array
    {
        $root=dirname(__DIR__,3).'/public/assets/';
        $registry=json_decode((string)file_get_contents($root.'tool-registry.json'),true,flags:JSON_THROW_ON_ERROR);
        $placement=json_decode((string)file_get_contents($root.'route-placement.json'),true,flags:JSON_THROW_ON_ERROR);
        $placements=array_column($placement['tools'],'id');
        $byId=array_combine($placements,$placement['tools']);
        $recommended=[];
        if(in_array($profileRole,\Architex\Platform\Wingman\WingmanRoleTutorials::roles(),true)) {
            $lessons=\Architex\Platform\Wingman\WingmanRoleTutorials::catalogue($profileRole,'member');
            $recommended=array_merge(array_column($lessons['steps'],'tool'),['project_passport','project_explorer','documents_drawings','feedback','inbox_action','meetings','cpd_learning']);
            // General collaboration and evidence workspaces exist for every saved
            // profile, including freelancers whose tutorial names specialist tools.
            // Administrative discovery still depends exclusively on server scope.
            if((new \Architex\Platform\Authorization\PermissionService())->allows($role,'verification.review'))$recommended[]='admin_review';
        }
        $permissions=new \Architex\Platform\Authorization\PermissionService();
        return array_map(static function(array $tool)use($role,$recommended,$byId,$permissions):array {
            $id=$tool['id'];$access=self::workspaceAccess($id,$role);
            $actions=[];
            foreach(self::NATIVE_ACTIONS[$id]??[] as $action) {
                $action['allowed']=$access['read']&&$permissions->allows($role,$action['permission'])&&self::workspaceAccess($action['target'],$role)['read'];
                $actions[]=$action;
            }
            return array_replace($tool,['access'=>$access,'placement'=>$byId[$id]??null,'recommended'=>in_array($id,$recommended,true),'nativeActions'=>$actions,'nativeBoundary'=>self::NATIVE_BOUNDARIES[$id]??null,'authorizationSource'=>'server_membership','professionalAuthority'=>'verified_by_source_operation']);
        },$registry['tools']);
    }

    public function __construct(private FeeCalculatorService $fees,private ?ComplianceCalculatorService $compliance=null,private ?EngineeringCalculatorService $engineering=null,private ?StandaloneUtilityService $standalone=null,private ?StandaloneRecordToolService $records=null,private ?DrawingComplianceService $drawingCompliance=null){}
    public function create():ToolDefinitionRegistry{$registry=new ToolDefinitionRegistry();$registry->register(new ToolDefinition('professional_fee_calculator','Professional Fee Calculator','1.0.0',['owner','admin','member'],'professional-fees','/toolbox/standalone/professional-fee-calculator',['fees','proposal','professional'],['definitionId'=>'id','projectValue'=>'money'],['totalInclVat'=>'money','classification'=>'string'],true,false,true,['ProjectRecord','AuditTrail'],function(array $input,array $context):array{$id=$input['definitionId']??null;if(!is_string($id))throw new ToolException('invalid_tool_input');return$this->fees->calculate($context['organisationId'],$context['userId'],$id,$input);}));
        if($this->compliance!==null){foreach([
            ['fenestration_xa_calculator','Fenestration XA Calculator','/toolbox/standalone/fenestration-xa-calculator',['fenestration','xa','compliance'],['project'=>'object','elements'=>'array']],
            ['r_value_thermal_calculator','R-Value Thermal Calculator','/toolbox/standalone/r-value-thermal-calculator',['thermal','r-value','compliance'],['project'=>'object','assemblies'=>'array']],
            ['sans_forms_checklist_builder','SANS Forms Checklist Builder','/toolbox/standalone/sans-forms-checklist-builder',['sans','forms','compliance'],['project'=>'object','parts'=>'array']],
            ['drawing_compliance_precheck','Drawing Compliance Pre-check','/toolbox/standalone/drawing-compliance-precheck',['drawings','evidence','compliance'],['project'=>'object','sheets'=>'array']],
            ['refuse_area_calculator','Refuse Area Calculator','/toolbox/standalone/refuse-area-calculator',['refuse','waste','municipal'],['project'=>'object','occupancyUse'=>'enum','quantity'=>'positive_number','collectionIntervalDays'=>'integer','contingencyPercent'=>'percentage','binType'=>'enum','proposedStorageAreaM2'=>'positive_number','facilities'=>'object','assumptions'=>'string']],
        ]as[$id,$name,$route,$tags,$schema])$registry->register(new ToolDefinition($id,$name,'1.0.0',['owner','admin','member'],'compliance',$route,$tags,['ruleVersionId'=>'id']+$schema,['verdict'=>'string','actionCards'=>'array','sourceVersion'=>'string'],true,false,true,['ProjectRecord','Inbox','MunicipalReadiness','AuditTrail'],function(array$input,array$context)use($id):array{$source=$input['ruleVersionId']??null;if(!is_string($source))throw new ToolException('invalid_tool_input');try{return$this->compliance->calculate($context['organisationId'],$source,$id,$input);}catch(\Architex\Platform\Compliance\ComplianceException$exception){throw new ToolException($exception->reason);}}));}
        if($this->drawingCompliance!==null)$registry->register(new ToolDefinition('sa_council_drawing_compliance_navigator','SA Council Drawing Compliance Navigator','1.6.0',['owner','admin','member'],'compliance','/toolbox/standalone/sa-council-drawing-compliance-navigator',['drawings','sans','nbr','municipal','submission'],['buildingDescription'=>'string','boundaryWall'=>'object','availableDrawingTypes'=>'array','drawingRegister'=>'array','references'=>'array','siteContext'=>'object'],['overallStatus'=>'string','source'=>'object','professionalReviewRequired'=>'boolean'],true,false,true,['ProjectRecord','Inbox','MunicipalReadiness','AuditTrail'],function(array$input):array{return$this->drawingCompliance->evaluate($input)+['issueEligible'=>false,'advisoryOnly'=>true];}));
        if($this->engineering!==null){foreach([
            ['beam_quick_check','Beam Quick Check','/toolbox/standalone/beam-quick-check',['structural','beam','screening'],['spanM'=>'positive_number','udlKnPerM'=>'non_negative_number','pointLoadKn'=>'non_negative_number','elasticModulusMpa'=>'positive_number','inertiaMm4'=>'positive_number','sectionModulusMm3'=>'positive_number','allowableStressMpa'=>'positive_number','deflectionLimitRatio'=>'positive_number']],
            ['rational_runoff_calculator','Rational Runoff Calculator','/toolbox/standalone/rational-runoff-calculator',['civil','stormwater','runoff'],['catchmentAreaM2'=>'positive_number','runoffCoefficient'=>'number_0_to_1','rainfallIntensityMmPerHour'=>'positive_number']],
            ['voltage_drop_calculator','Voltage Drop Calculator','/toolbox/standalone/voltage-drop-calculator',['electrical','cable','voltage-drop'],['phase'=>'enum','nominalVoltageV'=>'positive_number','currentA'=>'positive_number','routeLengthM'=>'positive_number','resistanceOhmPerKm'=>'non_negative_number','reactanceOhmPerKm'=>'non_negative_number','powerFactor'=>'number_0_to_1','maximumDropPercent'=>'positive_number']],
            ['concrete_quantity_calculator','Concrete Quantity Calculator','/toolbox/standalone/concrete-quantity-calculator',['concrete','quantity','construction'],['elements'=>'array','wastePercent'=>'percentage']],
            ['masonry_quantity_calculator','Masonry Quantity Calculator','/toolbox/standalone/masonry-quantity-calculator',['masonry','quantity','construction'],['grossWallAreaM2'=>'positive_number','openingsAreaM2'=>'non_negative_number','unitLengthM'=>'positive_number','unitHeightM'=>'positive_number','wastePercent'=>'percentage']],
            ['finishes_quantity_calculator','Paint and Tile Quantity Calculator','/toolbox/standalone/finishes-quantity-calculator',['finishes','quantity','construction'],['mode'=>'enum','surfaceAreaM2'=>'positive_number','wastePercent'=>'percentage']],
            ['tender_rate_build_up_calculator','Tender Rate Build-up Calculator','/toolbox/standalone/tender-rate-build-up-calculator',['tender','rate','commercial'],['quantity'=>'positive_number','unit'=>'string','materialsPerUnit'=>'non_negative_number','labourPerUnit'=>'non_negative_number','plantPerUnit'=>'non_negative_number','subcontractPerUnit'=>'non_negative_number','overheadsPercent'=>'percentage','profitPercent'=>'percentage','riskPercent'=>'percentage']],
            ['labour_productivity_calculator','Labour Productivity Calculator','/toolbox/standalone/labour-productivity-calculator',['labour','productivity','programme'],['quantity'=>'positive_number','unit'=>'string','productivityPerCrewDay'=>'positive_number','parallelCrews'=>'positive_number','hoursPerDay'=>'positive_number']],
        ]as[$id,$name,$route,$tags,$schema])$registry->register(new ToolDefinition($id,$name,'1.0.0',['owner','admin','member'],'engineering',$route,$tags,$schema,['formulaVersion'=>'string','results'=>'object','professionalRoleRequired'=>'string','issueEligible'=>'boolean'],true,false,true,['ProjectRecord','AuditTrail'],function(array$input)use($id):array{try{return$this->engineering->calculate($id,$input);}catch(EngineeringCalculatorException$exception){throw new ToolException($exception->reason);}}));}
        if($this->standalone!==null){foreach([
            ['boq_takeoff','BoQ / BoM Takeoff Tool','estimating','/toolbox/standalone/boq-takeoff',['boq','bom','takeoff'],['title'=>'string','currency'=>'enum','contingencyPercent'=>'percentage','items'=>'array'],['subtotalCents'=>'money','totalCents'=>'money','items'=>'array']],
            ['site_diary_entry','Site Diary Standalone Entry','site_management','/toolbox/standalone/site-diary-entry',['site','diary','field'],['date'=>'date','location'=>'string','weather'=>'string','activities'=>'array','labourCount'=>'integer','plantCount'=>'integer','deliveries'=>'array','delays'=>'array'],['date'=>'date','activityCount'=>'integer','deliveryCount'=>'integer','delayCount'=>'integer']],
        ]as[$id,$name,$category,$route,$tags,$input,$output])$registry->register(new ToolDefinition($id,$name,'1.0.0',['owner','admin','member'],$category,$route,$tags,$input,$output,false,false,true,['AuditTrail'],fn(array$data):array=>$this->standalone->calculate($id,$data)));}
        if($this->records!==null)foreach($this->records->definitions()as$id=>$definition)$registry->register(new ToolDefinition($id,$definition['name'],'1.0.0',['owner','admin','member'],$definition['category'],$definition['route'],$definition['tags'],$definition['inputSchema'],$definition['outputSchema'],false,false,true,['AuditTrail'],fn(array$data):array=>$this->records->calculate($id,$data)));
        return$registry;}
}
