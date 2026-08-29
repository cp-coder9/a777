<?php

declare(strict_types=1);

namespace Architex\Platform\Demo;

final class DemoRoleLensRegistry
{
    private const ALL_PROJECTS = ['parkview', 'sandton', 'seapoint', 'umhlanga_medical', 'fourways', 'greenpoint', 'rosebank', 'springs', 'bokaap', 'stellenbosch', 'lenasia', 'umhlanga_tower'];
    private const DESIGN_PROJECTS = ['parkview', 'sandton', 'seapoint', 'fourways', 'greenpoint', 'rosebank', 'springs', 'bokaap', 'lenasia', 'umhlanga_tower'];
    private const BEP_PROJECTS = ['parkview', 'sandton', 'fourways', 'rosebank', 'bokaap', 'stellenbosch', 'umhlanga_tower'];

    /** @var array<string,array{key:string,label:string,group:string,canonicalRole:string,focus:string,toolCategories:list<string>,projectKeys:list<string>}> */
    private const ROLES = [
        'client' => ['key'=>'client','label'=>'Client / Developer','group'=>'Client-side','canonicalRole'=>'client','focus'=>'Briefs, decisions, approvals, progress and payments','toolCategories'=>['projects','documents','finance','communication'],'projectKeys'=>self::ALL_PROJECTS],
        'project_manager' => ['key'=>'project_manager','label'=>'Project Manager','group'=>'Client-side','canonicalRole'=>'cpm','focus'=>'Programme, appointments, coordination, actions and delivery risk','toolCategories'=>['projects','programme','documents','communication'],'projectKeys'=>self::ALL_PROJECTS],
        'architect' => ['key'=>'architect','label'=>'Architect (PrArch)','group'=>'Design Team','canonicalRole'=>'architect','focus'=>'Design leadership, coordination, submissions and professional boundaries','toolCategories'=>['design','documents','compliance','communication'],'projectKeys'=>self::DESIGN_PROJECTS],
        'architectural_technologist' => ['key'=>'architectural_technologist','label'=>'Architectural Technologist','group'=>'Design Team','canonicalRole'=>'architect','focus'=>'Technical documentation, model coordination and drawing control','toolCategories'=>['design','documents','bim','compliance'],'projectKeys'=>self::DESIGN_PROJECTS],
        'candidate_architect' => ['key'=>'candidate_architect','label'=>'Candidate Architect','group'=>'Design Team','canonicalRole'=>'architect','focus'=>'Supervised design delivery, learning evidence and coordinated documentation','toolCategories'=>['design','documents','learning','communication'],'projectKeys'=>self::DESIGN_PROJECTS],
        'engineer_structural' => ['key'=>'engineer_structural','label'=>'Structural Engineer (PrEng)','group'=>'Engineering','canonicalRole'=>'engineer','focus'=>'Structural analysis, coordinated evidence and sign-off boundaries','toolCategories'=>['engineering','documents','compliance'],'projectKeys'=>self::DESIGN_PROJECTS],
        'engineer_civil' => ['key'=>'engineer_civil','label'=>'Civil Engineer (PrEng)','group'=>'Engineering','canonicalRole'=>'engineer','focus'=>'Civil services, stormwater, earthworks and coordinated evidence','toolCategories'=>['engineering','documents','site'],'projectKeys'=>['sandton','fourways','greenpoint','rosebank','springs','umhlanga_tower']],
        'engineer_electrical' => ['key'=>'engineer_electrical','label'=>'Electrical Engineer (PrEng)','group'=>'Engineering','canonicalRole'=>'engineer','focus'=>'Electrical reticulation, demand, coordination and sign-off boundaries','toolCategories'=>['engineering','documents','compliance'],'projectKeys'=>self::DESIGN_PROJECTS],
        'engineer_mechanical' => ['key'=>'engineer_mechanical','label'=>'Mechanical Engineer (PrEng)','group'=>'Engineering','canonicalRole'=>'engineer','focus'=>'HVAC, wet services, energy coordination and technical evidence','toolCategories'=>['engineering','documents','energy'],'projectKeys'=>['parkview','sandton','seapoint','fourways','greenpoint','rosebank','lenasia','umhlanga_tower']],
        'engineer_fire' => ['key'=>'engineer_fire','label'=>'Fire Engineer','group'=>'Engineering','canonicalRole'=>'fire_engineer','focus'=>'Fire strategy, life-safety coordination and regulatory review','toolCategories'=>['engineering','fire','compliance','documents'],'projectKeys'=>['sandton','seapoint','fourways','greenpoint','rosebank','springs','bokaap','lenasia','umhlanga_tower']],
        'quantity_surveyor' => ['key'=>'quantity_surveyor','label'=>'Quantity Surveyor (PrQS)','group'=>'Quantity Surveying','canonicalRole'=>'quantity_surveyor','focus'=>'Cost plans, quantities, procurement, valuations and commercial controls','toolCategories'=>['finance','procurement','documents','projects'],'projectKeys'=>self::DESIGN_PROJECTS],
        'contractor' => ['key'=>'contractor','label'=>'Contractor (Main)','group'=>'Construction','canonicalRole'=>'contractor','focus'=>'Site delivery, quality, safety, instructions and claims','toolCategories'=>['site','quality','safety','commercial'],'projectKeys'=>['sandton','seapoint','greenpoint','bokaap','lenasia']],
        'subcontractor' => ['key'=>'subcontractor','label'=>'Subcontractor','group'=>'Construction','canonicalRole'=>'subcontractor','focus'=>'Assigned work packages, RFIs, progress and installation evidence','toolCategories'=>['site','quality','documents','communication'],'projectKeys'=>['sandton','seapoint','greenpoint','bokaap','lenasia']],
        'supplier' => ['key'=>'supplier','label'=>'Supplier','group'=>'Construction','canonicalRole'=>'supplier','focus'=>'Products, quotes, orders, lead times and delivery evidence','toolCategories'=>['procurement','products','documents','communication'],'projectKeys'=>['sandton','seapoint','fourways','springs','umhlanga_tower']],
        'energy_consultant' => ['key'=>'energy_consultant','label'=>'Energy Consultant','group'=>'Design Team','canonicalRole'=>'energy_professional','focus'=>'Energy modelling, Part XA evidence and efficiency coordination','toolCategories'=>['energy','engineering','compliance','documents'],'projectKeys'=>self::BEP_PROJECTS],
        'town_planner' => ['key'=>'town_planner','label'=>'Town Planner','group'=>'Design Team','canonicalRole'=>'town_planner','focus'=>'Land use, authority processes, zoning and approval conditions','toolCategories'=>['planning','submissions','compliance','documents'],'projectKeys'=>self::ALL_PROJECTS],
        'environmental_consultant' => ['key'=>'environmental_consultant','label'=>'Environmental Consultant','group'=>'Design Team','canonicalRole'=>'bep','focus'=>'Environmental screening, evidence, conditions and referral boundaries','toolCategories'=>['environment','compliance','documents','projects'],'projectKeys'=>['parkview','sandton','umhlanga_medical','fourways','greenpoint','rosebank','stellenbosch','umhlanga_tower']],
        'accessibility_consultant' => ['key'=>'accessibility_consultant','label'=>'Accessibility Consultant','group'=>'Design Team','canonicalRole'=>'bep','focus'=>'Inclusive-access review, evidence and coordinated design actions','toolCategories'=>['accessibility','compliance','documents','design'],'projectKeys'=>['sandton','seapoint','umhlanga_medical','fourways','greenpoint','rosebank','bokaap','lenasia','umhlanga_tower']],
        'bep' => ['key'=>'bep','label'=>'BEP / Professional','group'=>'Platform','canonicalRole'=>'bep','focus'=>'Multidisciplinary technical coordination and professional evidence','toolCategories'=>['engineering','compliance','documents','projects'],'projectKeys'=>self::BEP_PROJECTS],
        'freelancer' => ['key'=>'freelancer','label'=>'Freelancer / Draughtsperson','group'=>'Platform','canonicalRole'=>'freelancer','focus'=>'Assigned drafting tasks, deliverables, time and collaboration','toolCategories'=>['design','documents','tasks','communication'],'projectKeys'=>['parkview','fourways','rosebank','umhlanga_tower']],
        'cpd_officer' => ['key'=>'cpd_officer','label'=>'CPD Officer / Administrator','group'=>'CPD & Learning','canonicalRole'=>'firm_admin','focus'=>'Synthetic learning administration, assessment records and non-accredited evidence','toolCategories'=>['learning','reports','compliance'],'projectKeys'=>['parkview','rosebank','bokaap','umhlanga_tower']],
        'admin' => ['key'=>'admin','label'=>'Administrator','group'=>'Platform','canonicalRole'=>'admin','focus'=>'Demo organisation operations, moderation, users and audit visibility','toolCategories'=>['administration','reports','projects','compliance'],'projectKeys'=>self::ALL_PROJECTS],
    ];

    /** @return array<string,array{key:string,label:string,group:string,canonicalRole:string,focus:string,toolCategories:list<string>,projectKeys:list<string>}> */
    public function all(): array
    {
        return self::ROLES;
    }

    /** @return array{key:string,label:string,group:string,canonicalRole:string,focus:string,toolCategories:list<string>,projectKeys:list<string>} */
    public function get(string $role): array
    {
        return self::ROLES[$role] ?? throw new DemoException('demo_role_invalid');
    }
}
