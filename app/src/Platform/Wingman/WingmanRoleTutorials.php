<?php
declare(strict_types=1);
namespace Architex\Platform\Wingman;

/**
 * Comprehensive per-role Wingman tutorials.
 *
 * Each role gets a full guided tour of the AI Wingman AND the role's own workflow:
 * purpose, project context, the module surfaces the role actually uses, workflow
 * stages, evidence/professional responsibility, collaboration, and the AI's honest
 * boundaries. Steps are generated from this data so they stay consistent.
 */
final class WingmanRoleTutorials
{
    public const VERSION = 3;

    /** role => [label, purpose, stages[], modules[], evidence, boundary, firstRoute] */
    private const ROLES = [
        'architect' => [
            'label' => 'Architect',
            'purpose' => 'You lead design coordination: interpret the brief, translate it into drawings and specifications, coordinate the professional team, and see the design through municipal approval, tender and construction to close-out. Your professional judgement and sign-off are the product — Wingman helps you organise it.',
            'stages' => ['Brief & appointment', 'Concept design', 'Design development', 'Technical documentation', 'Municipal submission', 'Tender', 'Construction', 'Close-out'],
            'modules' => ['Project Passport (project identity & phase)', 'Documents & Drawings (drawing register, revisions, transmittals)', 'SpecForge (specifications, sections, products, issue)', 'Forms (professional forms & certificates)', 'Municipal Approval Readiness + Council Drawing Navigator', 'SANS 10400-XA Energy', 'Town Planning Tracker', 'Fee Proposal Builder', 'Practice Management (programme, tasks, fees)', 'Team Workspace & Professional Directory', 'Governed Meetings', 'Issues / RFIs, NCR Manager, Snag Manager'],
            'evidence' => 'Every issued drawing, specification and certificate must carry your review. AI drafting is never your signature: verify the revision, the source and the professional responsibility before anything is issued to a client or authority.',
            'boundary' => 'Wingman will not sign off design, cannot substitute for your SACAP-registered judgement, will not issue documents on your behalf, and will not claim municipal approval that has not been granted.',
            'firstRoute' => 'tools',
        ],
        'client' => [
            'label' => 'Client / Property Owner',
            'purpose' => 'You own the outcome and the decisions: define the brief, the budget expectation and the property, appoint the professional team, review progress and decisions, and release payments. You do not need to understand the technical detail — you need clear decisions and honest status.',
            'stages' => ['Brief', 'Feasibility', 'Appointment of team', 'Design review', 'Approvals', 'Tender & award', 'Construction', 'Handover & occupation'],
            'modules' => ['Project Space (create/select your project, brief, property)', 'Project Passport (what the project is and what phase it is in)', 'Professional Directory (find & verify professionals)', 'Team Workspace (who is appointed)', 'Approvals Queue & Inbox (decisions waiting on you)', 'Fee Proposal Builder & Finance & Payments (what things cost, what is due)', 'Progress Report Viewer', 'Governed Meetings (decisions recorded)', 'Payments & Escrow (status only — no funds custody)'],
            'evidence' => 'Confirm decisions in writing where the project requires it. Payment and approval actions are yours; Wingman will not approve or pay on your behalf.',
            'boundary' => 'Wingman cannot make commercial or legal decisions for you, cannot release payments, and will not present a professional opinion as fact.',
            'firstRoute' => 'projects',
        ],
        'bep' => [
            'label' => 'Built Environment Professional',
            'purpose' => 'You provide a specialist professional discipline within the team — surveying, planning, energy, accessibility, fire or coordination. You contribute scoped, evidence-backed professional work and coordinate with the lead consultant.',
            'stages' => ['Appointment', 'Discipline scope & inputs', 'Design coordination', 'Compliance evidence', 'Issue & review', 'Construction support', 'Close-out'],
            'modules' => ['Project Passport & Project Explorer', 'Team Workspace (your appointment and role label)', 'Workspace Tools (discipline calculators & forms)', 'Compliance Hub & SANS forms', 'SpecForge / BoM and Tender Builder (as applicable)', 'Governed Meetings', 'Issues / RFIs', 'Feedback Intelligence (platform improvement)'],
            'evidence' => 'Confirm the scope, inputs and assumptions you relied on, and retain your professional review of every issued output. Calculations are advisory until you verify them.',
            'boundary' => 'Wingman will not certify compliance, will not replace your registration, and will not issue professional output in your name.',
            'firstRoute' => 'tools',
        ],
        'engineer' => [
            'label' => 'Engineer',
            'purpose' => 'You design and check the engineering systems and structure, provide engineering evidence for approvals and construction, respond to site queries, and sign off inspections that fall in your discipline.',
            'stages' => ['Brief & appointment', 'Design & calculations', 'Coordination & clashes', 'Documentation & issue', 'Approval evidence', 'Construction inspections', 'Sign-off'],
            'modules' => ['Project Passport & Team Workspace', 'Engineering Calculator (structural, civil, electrical, mechanical, wet services, fire)', 'Inspection Test Plans (ITP)', 'Health & Safety', 'Issues / RFIs & Site Instructions', 'NCR Manager', 'Documents & Drawings', 'Forms (certificates)', 'Governed Meetings'],
            'evidence' => 'Record inputs, formula/version, assumptions and the professional review of every calculation. An inspection is not a statutory certificate unless it is properly issued and signed.',
            'boundary' => 'Wingman will not fabricate engineering results, will not certify structural adequacy, and its calculations are advisory screening aids only.',
            'firstRoute' => 'tools',
        ],
        'quantity_surveyor' => [
            'label' => 'Quantity Surveyor',
            'purpose' => 'You manage cost and commercial control: measurement, cost plans, BoQ, procurement, valuations, claims, variations and final accounts. You give the client and team honest commercial visibility.',
            'stages' => ['Cost planning', 'Measurement & BoQ', 'Tender & procurement', 'Contract award', 'Valuations & certificates', 'Variations & claims', 'Final account'],
            'modules' => ['BoM / BoQ & Tender Builder (takeoff, BoM lines, tender)', 'Fee Proposal Builder & Contract Administration (baselines, variations, claims)', 'Payments & Escrow (internal certificate metadata only)', 'RFQ Marketplace & Supplier Catalogue', 'Documents & Drawings', 'SANS forms', 'Market Insights', 'Quantities in SpecForge'],
            'evidence' => 'Check measurement assumptions, rates and exclusions; every certificate and valuation carries your review. Tender rates are advisory until reviewed.',
            'boundary' => 'Wingman will not certify a valuation, will not execute payment, and will not present an estimate as a fixed price.',
            'firstRoute' => 'tools',
        ],
        'town_planner' => [
            'label' => 'Town Planner',
            'purpose' => 'You manage land-use and statutory planning: zoning checks, applications, public participation, conditions of approval and authority liaison.',
            'stages' => ['Property & zoning enquiry', 'Application preparation', 'Submission', 'Circulation & comment', 'Public participation', 'Decision & conditions', 'Compliance with conditions'],
            'modules' => ['Town Planning Tracker (applications, entries, submit, stage)', 'Municipal Approval Readiness & Council Drawing Navigator', 'Documents & Drawings', 'Compliance Hub', 'SANS forms & Zoning Checker', 'Governed Meetings', 'Inbox & Collaboration'],
            'evidence' => 'Keep property, zoning and authority evidence together; a tracked status is not official approval. Record submission and response dates honestly.',
            'boundary' => 'Wingman will not claim an authority has approved anything, will not submit to a municipality, and does not give legal opinion.',
            'firstRoute' => 'tools',
        ],
        'land_surveyor' => [
            'label' => 'Land Surveyor',
            'purpose' => 'You provide survey and geomatics information: appointments, cadastral information, control points, observations, deliverables and issue.',
            'stages' => ['Appointment', 'Control & reconnaissance', 'Field observations', 'Reduction & computation', 'Deliverables & issue'],
            'modules' => ['Survey & Geomatics (jobs, observations, deliverables, fees, issue)', 'Project Passport & Team Workspace', 'Documents & Drawings', 'Issues / RFIs', 'Governed Meetings'],
            'evidence' => 'Confirm the property, the coordinate reference system, the vertical datum and the survey version before sharing any relied-upon information.',
            'boundary' => 'Wingman will not certify survey accuracy, and issuing a job requires a verified professional registration for the responsible role.',
            'firstRoute' => 'tools',
        ],
        'energy_professional' => [
            'label' => 'Energy Professional',
            'purpose' => 'You demonstrate SANS 10400-XA energy compliance: fenestration, envelope, hot water, lighting and HVAC inputs, and the evidence the authority and team rely on.',
            'stages' => ['Appointment', 'Baseline inputs', 'Fenestration & envelope', 'Services', 'Calculation & report', 'Submission evidence'],
            'modules' => ['SANS 10400-XA Energy (fenestration, walls, roof, floors, hot water, lighting, aircon, results)', 'Fenestration calculator & R-value thermal calculator', 'Compliance Hub', 'Documents & Drawings', 'Forms'],
            'evidence' => 'Check the building inputs and assumptions before issuing; a screening result is not a certified assessment.',
            'boundary' => 'Wingman will not certify XA compliance, and calculator output is advisory until a competent person verifies it.',
            'firstRoute' => 'tools',
        ],
        'fire_engineer' => [
            'label' => 'Fire Engineer',
            'purpose' => 'You establish the fire strategy and life-safety design: occupancy, escape routes, travel distance, compartmentation, fire resistance and active systems.',
            'stages' => ['Appointment & scope', 'Occupancy & risk', 'Escape & travel distance', 'Compartmentation & FRR', 'Active systems', 'Rational design report', 'Review & sign-off'],
            'modules' => ['Fire rational design & fire compliance check (calculator family)', 'Compliance Hub', 'Documents & Drawings', 'ITP & Safety', 'Governed Meetings', 'Issues / RFIs'],
            'evidence' => 'Record scope and evidence; final safety decisions require competent professional review and cannot be delegated to AI.',
            'boundary' => 'Wingman will not certify life safety, will not approve a rational design, and does not replace a competent fire engineer.',
            'firstRoute' => 'tools',
        ],
        'cpm' => [
            'label' => 'Construction Project Manager',
            'purpose' => 'You coordinate delivery: programme, resources, risks, changes, progress and the interfaces between the parties from appointment to close-out.',
            'stages' => ['Establish appointments & responsibilities', 'Programme & resources', 'Procurement support', 'Construction coordination', 'Change & risk management', 'Close-out'],
            'modules' => ['Practice Management (programme, milestones, tasks, resources, fees)', 'Team Workspace', 'Issues / RFIs, Site Instructions', 'NCR Manager, Snag Manager', 'Construction programme & Payment Blockers', 'Governed Meetings', 'Inbox & Collaboration'],
            'evidence' => 'Establish responsibilities and next actions before tracking progress; record changes and blockers with evidence and owners.',
            'boundary' => 'Wingman will not instruct a contractor, will not certify progress, and will not resolve a contractual dispute.',
            'firstRoute' => 'tools',
        ],
        'contractor' => [
            'label' => 'Principal Contractor',
            'purpose' => 'You execute the works: build to the authorised information, manage labour, plant and materials, record site evidence, raise RFIs, and submit claims.',
            'stages' => ['Award & mobilisation', 'Programme & resources', 'Construction', 'Quality & snags', 'Claims & valuations', 'Completion & handover'],
            'modules' => ['Site diary & site evidence', 'Issues / RFIs & Site Instructions', 'NCR Manager & Snag Manager', 'ITP & Health & Safety', 'Materials procurement & Plant register', 'Payment Claim Builder & Valuation certificate', 'Programme', 'Tender / bid workbench'],
            'evidence' => 'Check the authorised drawing set and current revision before building; record site evidence and raise issues through the project workflow. Claims need measured, evidenced support.',
            'boundary' => 'Wingman will not approve a claim, will not certify work, and will not authorise a variation or a payment.',
            'firstRoute' => 'tools',
        ],
        'subcontractor' => [
            'label' => 'Trade Subcontractor',
            'purpose' => 'You deliver your assigned package within the main programme, to the specified quality, with the evidence the main contractor needs.',
            'stages' => ['Package award', 'Shop drawings & samples', 'Installation', 'Inspection & snags', 'Evidence & claim'],
            'modules' => ['Package Scope Viewer (your assigned scope)', 'Shop Drawing & Sample Submission', 'Site diary entry', 'Health & Safety', 'Workforce timesheet', 'Snag evidence upload', 'Warranty upload', 'Payment claim builder'],
            'evidence' => 'Confirm your assigned package, the current information and the required evidence before submitting work for review.',
            'boundary' => 'Wingman will not approve your work, will not certify compliance, and will not vary your package.',
            'firstRoute' => 'tools',
        ],
        'supplier' => [
            'label' => 'Material Supplier',
            'purpose' => 'You supply products and quotations: catalogue data, technical information, pricing, availability, delivery and order fulfilment.',
            'stages' => ['Catalogue & product data', 'RFQ response', 'Quotation', 'Order & delivery', 'Delivery note & payment'],
            'modules' => ['Supplier Catalogue (products, technical data)', 'RFQ Marketplace & Quote Response', 'Delivery Note Builder', 'Payment dashboard (status only)', 'Shop drawing submission'],
            'evidence' => 'Check product information, specifications, availability and delivery assumptions before quoting. Orders require explicit approval.',
            'boundary' => 'Wingman will not accept an order, will not confirm a price, and will not move funds.',
            'firstRoute' => 'tools',
        ],
        'site_manager' => [
            'label' => 'Site Manager / Clerk of Works',
            'purpose' => 'You keep reliable daily site records and quality control: diary, inspections, hold points, materials, snags and contractor observations.',
            'stages' => ['Site establishment', 'Daily records', 'Inspections & hold points', 'Quality & snags', 'Progress reporting', 'Completion'],
            'modules' => ['Site diary & site evidence', 'Inspection Test Plans', 'Snag Manager & NCR Manager', 'Health & Safety', 'Plant & equipment register', 'Issues / RFIs & Site Instructions', 'Programme'],
            'evidence' => 'Use the active project and current drawings; record observations and supporting evidence before closing issues. An inspection record is not a statutory certificate.',
            'boundary' => 'Wingman will not sign off an inspection, will not certify quality, and will not instruct a contractor.',
            'firstRoute' => 'tools',
        ],
        'health_safety' => [
            'label' => 'Health & Safety Officer',
            'purpose' => 'You manage the safety file, permits, HIRA, incidents, inductions and plans, and escalate hazards to the responsible people.',
            'stages' => ['Safety file & appointment', 'Risk assessment (HIRA)', 'Permits & inductions', 'Monitoring & inspections', 'Incidents & corrective action', 'Reporting'],
            'modules' => ['Health & Safety (safety file, permits, HIRA, incidents, inductions, plans, fall protection)', 'ITP', 'Site diary & evidence', 'NCR Manager', 'Plant register', 'Governed Meetings'],
            'evidence' => 'Record inspections, risks and actions with evidence; escalate urgent hazards through the responsible people, not through AI alone.',
            'boundary' => 'Wingman will not certify safety compliance, will not replace your statutory duties, and cannot be the escalation path for an emergency.',
            'firstRoute' => 'tools',
        ],
        'developer' => [
            'label' => 'Property Developer',
            'purpose' => 'You orient the development: establish the property, the brief, the feasibility, the team, approvals and the commercial case across one or many projects.',
            'stages' => ['Site & property', 'Feasibility', 'Brief & appointments', 'Approvals', 'Delivery', 'Sales / occupation'],
            'modules' => ['Project Space (portfolio of projects)', 'Project Passport & Project Explorer', 'Feasibility & soft-cost estimators', 'Professional Directory & Team Workspace', 'Approvals Queue', 'Finance & Payments (status)', 'Market Insights', 'Governed Meetings'],
            'evidence' => 'Review scope, approvals and commercial decisions with the responsible professionals. Portfolio views are not a valuation.',
            'boundary' => 'Wingman will not give investment advice, will not approve expenditure, and will not guarantee approvals or returns.',
            'firstRoute' => 'projects',
        ],
        'freelancer' => [
            'label' => 'Specialist Freelancer',
            'purpose' => 'You complete assigned work packages or tasks to an agreed deliverable, submit the evidence, and get paid for approved work.',
            'stages' => ['Task/package assignment', 'Work', 'Deliverable submission', 'Review & acceptance', 'Time & claim'],
            'modules' => ['Deliverable Submission', 'Freelancer timesheet & claim builder', 'Resource centre & checklists', 'CAD / drawing upload check', 'CPD & Learning', 'Inbox & Collaboration'],
            'evidence' => 'Confirm deliverables, deadlines and acceptance criteria before starting; submit evidence to the responsible reviewer.',
            'boundary' => 'Wingman will not accept your deliverable, will not approve your time, and will not release your payment.',
            'firstRoute' => 'tools',
        ],
        'firm_admin' => [
            'label' => 'Practice Administrator',
            'purpose' => 'You run the practice: people, membership, documents, fees, CPD tracking, and the operational controls that keep the firm compliant and productive.',
            'stages' => ['Organisation setup', 'People & membership', 'Practice documents', 'Fee & rate configuration', 'CPD compliance', 'Reporting'],
            'modules' => ['Settings (organisation, membership, roles)', 'Practice Management (resources, fees, profitability)', 'Firm-wide document register', 'Staff CPD tracker', 'Technical brief editor', 'Admin Review'],
            'evidence' => 'Membership permission determines which changes you may make; keep changes deliberate and auditable.',
            'boundary' => 'Wingman will not change permissions, will not approve a verification, and will not act outside your organisation.',
            'firstRoute' => 'settings',
        ],
        'organisation_admin' => [
            'label' => 'Organisation Administrator',
            'purpose' => 'You govern the organisation: access, roles, verification queue, moderation, exceptions and audit oversight.',
            'stages' => ['Access & roles', 'Verification review', 'Policy & rates', 'Exceptions & disputes', 'Audit & reporting'],
            'modules' => ['Settings', 'Admin Review (verification review queue)', 'User verification console', 'Fee / tariff editor & payment rate config', 'Audit trail viewer', 'Governance console'],
            'evidence' => 'Verify access and responsibilities before changing anything; every privileged change is auditable and attributable to you.',
            'boundary' => 'Wingman will not grant access, will not approve a verification for you, and will not sign off on your behalf.',
            'firstRoute' => 'settings',
        ],
        'admin' => [
            'label' => 'Practice Operations Admin',
            'purpose' => 'You run day-to-day practice operations: setup, registers, rate configuration, disputes handling and staff CPD.',
            'stages' => ['Operations setup', 'Registers & records', 'Rates & configuration', 'Disputes & exceptions', 'Reporting & audit'],
            'modules' => ['Settings', 'Admin Review', 'Audit trail viewer', 'Fee / tariff editor', 'Payment rate config', 'Staff CPD tracker', 'Governance console'],
            'evidence' => 'Keep changes deliberate and auditable; a role label does not grant additional permissions.',
            'boundary' => 'Wingman cannot elevate your permissions, cannot bypass an approval gate, and will not act across organisations.',
            'firstRoute' => 'settings',
        ],
        'platform_admin' => [
            'label' => 'Platform Administrator',
            'purpose' => 'You govern the platform itself: feedback, system health, governance console, AI review queue and cross-organisation oversight within the limits you are actually granted.',
            'stages' => ['Platform health', 'Governance & feedback', 'AI review queue', 'Exceptions & moderation', 'Audit'],
            'modules' => ['Settings (platform controls)', 'Feedback Intelligence & roadmap', 'System health & audit monitor', 'AI review queue', 'Governance console', 'Audit trail viewer'],
            'evidence' => 'Verify which organisation and permissions are active before acting; keep an audit trail. Governance actions are recorded.',
            'boundary' => 'Wingman does not grant cross-organisation superuser access, does not bypass approval gates, and will not take unreviewed platform actions.',
            'firstRoute' => 'settings',
        ],
    ];

    /** Each exercise names the evidence to inspect or prepare, in workflow order. */
    private const EXERCISES = [
        'architect' => ['Match the client brief, property reference and appointment scope', 'Compare the concept revision with the approved brief and list unresolved decisions', 'Check discipline drawing revisions and record coordination conflicts', 'Compare drawing register revisions with specification sections and issue purpose', 'Review readiness blockers and the municipal pack supporting evidence', 'Check tender scope, exclusions and equal information issued to bidders', 'Review open RFIs, instructions, NCRs and snags against the authorised drawing set', 'Reconcile handover documents, outstanding defects and responsible signatories'],
        'client' => ['Identify your property, desired outcome, brief and budget expectation', 'Ask the team to explain feasibility assumptions, exclusions and unanswered risks', 'Compare directory verification evidence with the team appointment and agreed scope', 'Compare the current design revision with your brief and prepare specific comments', 'Review each outstanding decision and distinguish your consent from authority approval', 'Compare tender inclusions, exclusions and the recorded award recommendation', 'Check progress evidence, change requests and payment certificate references', 'Check handover documents, defects, warranties and official occupation evidence'],
        'bep' => ['Confirm your discipline appointment and appointed reviewer', 'List the inputs, revisions and exclusions required for your scope', 'Compare discipline interfaces and prepare an RFI for missing information', 'Check calculation assumptions and compliance evidence sources', 'Match the output revision and issue purpose to the review request', 'Review site queries against your issued discipline information', 'Identify final discipline records and unresolved exceptions for handover'],
        'engineer' => ['Confirm discipline scope, design criteria and responsible professional', 'Check calculation inputs, units, loads, formula version and limitations', 'Compare discipline revisions and identify a clash requiring a response', 'Match calculations and drawing revisions to the proposed issue', 'Identify required authority evidence and unsigned professional declarations', 'Review the inspection test plan, hold points and site evidence', 'Identify outstanding defects and the competent professional who must sign'],
        'quantity_surveyor' => ['Check cost-plan basis, date, area, rates and exclusions', 'Compare quantity units, drawing revision and measurement assumptions', 'Compare tender returnables, quotation inclusions and exclusions', 'Check the awarded scope and commercial baseline references', 'Reconcile claimed quantities with evidence and the certificate revision', 'Compare variation scope, valuation basis and authorisation status', 'Reconcile agreed changes, prior certificates and outstanding claims'],
        'town_planner' => ['Match property identifiers and zoning evidence source dates', 'Check the application type, authority, documents and required appointments', 'Locate the submission receipt and official reference; an internal stage is insufficient', 'Identify authority comments, response owners and due dates', 'Check participation notices, comment evidence and unresolved objections', 'Read the actual decision notice and enumerate conditions', 'Match each condition to its evidence and responsible owner'],
        'land_surveyor' => ['Confirm survey scope, cadastral reference and responsible registrant', 'Check control point sources, coordinate system and vertical datum', 'Inspect observation dates, instruments and field evidence', 'Review reduction assumptions, accuracy checks and exceptions', 'Match deliverable revisions to the appointment and verified issue declaration'],
        'energy_professional' => ['Confirm appointment and required assessment scope', 'Check climate/building inputs, occupancy and source drawing revision', 'Review glazing areas, orientation and envelope assembly evidence', 'Check hot water, lighting and HVAC assumptions against specifications', 'Review all result warnings and trace input units before professional review', 'Reconcile the report revision and competent-person declaration in the submission pack'],
        'fire_engineer' => ['Confirm the appointed fire-design scope and responsible professional', 'Check occupancy, risk assumptions and the source drawing revision', 'Review escape strategy, route dimensions and travel-distance inputs', 'Check compartment boundaries and fire-resistance evidence', 'Compare active-system assumptions and discipline coordination', 'Match the rational-design report to evidence and unresolved departures', 'Record outstanding review issues for the competent signatory'],
        'cpm' => ['Check team responsibilities and missing appointments', 'Compare milestones, dependencies, resources and due dates', 'Review procurement lead times and decision owners', 'Identify delayed actions across RFIs, instructions and site records', 'Check change evidence, risk owners and unresolved approval gates', 'Reconcile handover actions, defects and responsible parties'],
        'contractor' => ['Confirm award scope, authorised drawings and mobilisation requirements', 'Review programme dependencies, workforce and plant requirements', 'Compare site evidence with current instructions and raise unclear scope for review', 'Review inspection hold points, NCR corrective actions and snag evidence', 'Prepare measured claim evidence with period and prior-claim references', 'Reconcile defects, warranties, manuals and handover evidence'],
        'subcontractor' => ['Check the assigned package scope, exclusions and main-contractor contact', 'Match shop drawing and sample revisions to reviewer requirements', 'Compare installation evidence with the authorised detail and safety requirements', 'Identify inspection records, defects and reinspection requirements', 'Assemble package evidence, measured work and the claim period'],
        'supplier' => ['Check catalogue revision, technical data, units and product identifiers', 'Read RFQ scope, quantities, returnables and response deadline', 'Prepare price validity, tax, delivery assumptions and exclusions', 'Match the authorised order to quantities, lead time and delivery destination', 'Reconcile delivery note evidence with invoice and payment status'],
        'site_manager' => ['Check site contacts, current information and inspection responsibilities', 'Review dated diary evidence, labour, plant, weather and work locations', 'Identify hold points, acceptance criteria and required inspection evidence', 'Compare NCR corrective actions and snag reinspection status', 'Reconcile progress statements with dated site records and blockers', 'Identify outstanding defects and completion evidence for the responsible reviewer'],
        'health_safety' => ['Check appointments, safety-file evidence and expiry dates', 'Review hazard, exposure, risk controls and responsible owners', 'Compare permits, induction evidence and activity authorisations', 'Identify overdue inspections and missing corrective-action evidence', 'Check incident facts, notifications and assigned corrective actions', 'Prepare a dated status report with unresolved hazards and accountable owners'],
        'developer' => ['Check the project property identifiers and ownership evidence', 'Review feasibility assumptions, soft costs and sensitivity to exclusions', 'Match the brief to team scopes and appointment evidence', 'Check authority decision evidence and unmet conditions', 'Review programme, change and financial evidence across the selected project', 'Reconcile handover, occupation and sale assumptions with actual records'],
        'freelancer' => ['Confirm assigned deliverables, acceptance criteria, deadline and reviewer', 'Check source revisions and document work against the agreed scope', 'Prepare the deliverable revision and supporting evidence for review', 'Identify reviewer comments and distinguish submission from acceptance', 'Reconcile recorded time and claim evidence with accepted deliverables'],
        'firm_admin' => ['Confirm the active organisation and available settings', 'Review active memberships and least-privilege responsibilities', 'Check document ownership, revision and access scope', 'Verify fee sources, effective dates and proposed configuration changes', 'Check staff learning records and actual professional-body evidence', 'Review operational exceptions and the audit evidence for authorised changes'],
        'organisation_admin' => ['Check organisation identity and effective membership permissions', 'Compare verification evidence, validity dates and review reasons', 'Review policy sources, rate versions and effective dates', 'Identify exception facts, responsible reviewers and missing evidence', 'Trace a privileged action to its actor, reason and recorded audit event'],
        'admin' => ['Confirm organisation context and operational responsibilities', 'Check register references, owners, due dates and evidence gaps', 'Compare proposed rates with their authority, version and effective date', 'Review dispute evidence and identify the authorised decision maker', 'Reconcile operational changes with audit events and outstanding actions'],
        'platform_admin' => ['Check available health evidence and separate observed failures from assumptions', 'Review feedback evidence, affected role and reproducible steps', 'Identify AI output sources, review status and responsible human reviewer', 'Check moderation evidence and the limits of your active organisation access', 'Trace actions to audit evidence without assuming cross-organisation access'],
    ];

    /** V1 module IDs, never invented deep links or endpoint permissions. */
    private const MODULE_LINKS = [
        'Project Passport'=>'project_passport', 'Project Explorer'=>'project_explorer',
        'Documents'=>'documents_drawings', 'SpecForge'=>'specforge', 'Forms'=>'forms',
        'Municipal'=>'municipal', 'Council'=>'council_navigator', 'SANS 10400'=>'xa',
        'Town Planning'=>'planning', 'Fee Proposal'=>'fee_proposal', 'Practice Management'=>'practice',
        'Team Workspace'=>'team_workspace', 'Professional Directory'=>'professional_directory',
        'Governed Meetings'=>'meetings', 'Issues'=>'issues_rfis', 'Engineering Calculator'=>'engineering_calc',
        'Inspection Test'=>'itp', 'Health & Safety'=>'safety', 'NCR Manager'=>'ncr_manager',
        'BoM'=>'bom', 'Payments & Escrow'=>'payments_escrow', 'RFQ'=>'rfq_marketplace',
        'Supplier Catalogue'=>'supplier_catalog', 'Market Insights'=>'market_insights',
        'Survey'=>'survey_geomatics', 'Compliance Hub'=>'compliance_hub', 'ITP'=>'itp',
        'Approvals Queue'=>'approvals_queue', 'Admin Review'=>'admin_review',
    ];

    /** Practical inputs and checks grounded in the dashboard adapter contracts. */
    private const MODULE_PRACTICE = [
        'project_passport'=>'Read project code, property reference, municipality, phase and version. Compare them with the brief; a phase change requires a supported transition and current version.',
        'project_explorer'=>'Locate the relevant record and follow its source module. The returned snapshot is a bounded subset, not a complete archive.',
        'professional_directory'=>'Check profession, registration body and verification grade; confirm scope and current evidence before considering appointment.',
        'team_workspace'=>'Check each appointment role and status. Where invitations are enabled, identify the real candidate, discipline and role label and confirm the resulting appointment status.',
        'documents_drawings'=>'For a permitted draft, prepare title, document type, discipline, issue purpose and revision code. A drawing also requires drawing number and sheet type. Confirm the save, reload the revision, then use the separate review/issue gate; saving does not send an external transmittal.',
        'specforge'=>'Select the project specification, inspect sections and clauses, and check product source evidence. Review draft changes before issue; unavailable Smart Add connectors cannot supply verified product results.',
        'forms'=>'Select the form appropriate to your appointment, check mandatory project and professional details, and review the output. A generated form is not a signed certificate.',
        'municipal'=>'Review the project readiness requirements, supporting evidence and blockers. Prepare the missing evidence with the assigned owner; internal submission packs do not lodge an application with a municipality.',
        'council_navigator'=>'Check drawing types and revision evidence against the submission requirements. Record missing sheets and source requirements; a navigator result is not approval.',
        'planning'=>'Inspect the application property, type, authority, status and deadlines. For an enabled application form, supply the required facts and reload the saved entry; keep the actual authority receipt separately.',
        'xa'=>'Review orientation, areas, thermal properties and services against source drawings. Check all component warnings before using results in a professional assessment.',
        'fee_proposal'=>'Check proposal title, scope, fee basis, project and version. Prepare stage allocations and exclusions; an issued proposal still requires actual client acceptance.',
        'practice'=>'Review programme milestones, tasks, resource allocations and blockers. For an enabled task form, specify the deliverable, owner and due date and verify the saved task after reload.',
        'meetings'=>'Check project, meeting purpose, agenda, attendees and linked evidence. Review minutes and action owners; AI notes remain drafts until the authorised human review.',
        'issues_rfis'=>'Prepare a precise question with location, source drawing revision, due date, responsible role and evidence. Use the enabled RFI form and verify the created record; an RFI is not a site instruction.',
        'engineering_calc'=>'Select the correct discipline calculation and verify input units, assumptions and limits. Save only a supported calculation and reopen its result; advisory screening never establishes engineering certification.',
        'itp'=>'Check inspection type, checklist, findings, follow-ups and whether sign-off is required. Record evidence through the enabled inspection action and verify its status before releasing a hold point.',
        'safety'=>'Review safety-file evidence, permits, risk controls and responsible persons. For enabled inspection records, enter factual findings and follow-ups; escalate immediate danger through the responsible people, not a queued AI request.',
        'ncr_manager'=>'Identify location, non-conformance evidence, severity, owner and corrective action. Verify the review or reinspection evidence before any permitted closure.',
        'bom'=>'Compare line quantities, units, source drawing revision, waste allowance and rate basis. Save an available draft only after checking totals and exclusions; an estimate is not an awarded tender.',
        'payments_escrow'=>'Compare certificate revision, requested release amount and recorded provider-paid metadata. No transfer, escrow custody or settlement is performed by this training or metadata view.',
        'rfq_marketplace'=>'Prepare package scope, procurement method, returnables and evaluation criteria totalling 100 percent where the package form is enabled. Compare quotations with missing returnables and exclusions visible; this adapter does not automatically award or appoint.',
        'supplier_catalog'=>'Check supplier identity, catalogue revision, technical sources and units. After a permitted catalogue import, verify revision and item count; quoted availability still needs supplier confirmation.',
        'market_insights'=>'Check metric formula, unit and as-of date. These are recorded project metrics, not external market benchmarks or forecasts.',
        'survey_geomatics'=>'For an enabled job form, prepare job number, survey type, title, responsible role, coordinate reference system, vertical datum and source references. Add supported observations with point codes, coordinates, observation date and evidence; verify each saved job version. Issuing requires the responsible verified professional and does not lodge a diagram or certify official coordinates.',
        'compliance_hub'=>'Review compliance item category, status, expiry and supporting evidence. This projection is not an automatic certification across standards.',
        'approvals_queue'=>'Read the decision title, priority, human gate and action target. Open its source record and inspect evidence before any authorised action; a missing-record task is not an approval request.',
        'admin_review'=>'Check verification kind, registration body, grade, expiry and source evidence. Use only an available permitted review action with a reason and confirmation; a profile label alone cannot grant review permission.',
    ];

    /** @return array<string,mixed> */
    public static function catalogue(?string $role, string $membership): array
    {
        $key = isset(self::ROLES[$role ?? '']) ? $role : null;
        if ($key === null) return self::generalSteps($membership);
        $r = self::ROLES[$key];
        $steps = [
            self::lesson('welcome', 'Welcome, '.$r['label'], $r['purpose'], 'command', '', 'Confirm your saved profile and active organisation', ['Read the role label and check that it matches your appointment.', 'Use a designated training project; Next saves learning progress only, not real work.']),
            self::lesson('project', 'Know which project you are in', 'Project selection and membership determine the records and actions available to you.', 'projects', '', 'Identify the correct project before reviewing its records', ['Open Projects and select an existing project shared with you.', 'Check its name, property reference and phase. If none is shared, ask the project owner for access.']),
            self::lesson('wingman', 'Use Wingman with evidence', 'AI may be unavailable or wrong. Verify every source and do not treat generated text as a saved record or decision.', 'command', '', 'Ask a bounded question and check its sources', ['Ask which evidence is missing for your current task, if Wingman is available.', 'Compare the answer with actual project records; retain unresolved questions when sources or the provider are unavailable.']),
            self::lesson('workflow', 'Your workflow as '.$r['label'], implode(' → ', $r['stages']), $r['firstRoute'], '', 'Identify the current stage and its next human review gate', ['Compare the displayed phase with the actual appointment and evidence.', 'Identify the responsible reviewer before advancing any real workflow.']),
            self::lesson('modules', 'Your primary workspaces', implode('; ', $r['modules']), 'tools', '', 'Distinguish live records, read-only views and unavailable capabilities', ['Open Workspace Tools and locate the relevant module.', 'Read its availability notice. A prototype, empty list or unavailable action is not a completed workflow.']),
        ];
        foreach ($r['stages'] as $index => $stage) {
            $task = self::EXERCISES[$key][$index];
            $steps[] = self::lesson('stage-'.($index+1), $stage, $task.'. '.$r['evidence'], $r['firstRoute'], '', $task, [
                'In the selected project, locate the records for '.$stage.' using the module lessons that follow.',
                $task.'. Note the exact record reference, revision, owner and any missing evidence.',
                'Prepare the next review or handoff with the responsible team member. Use an enabled authorised action only; if the screen has no action form, keep this as preparation and report the missing capability.',
                'After an authorised save, reopen the record and confirm the saved values and status. Do not infer an external submission, approval or payment from an internal status.',
            ]);
        }
        foreach ($r['modules'] as $index => $module) {
            $tool = '';
            foreach (self::MODULE_LINKS as $prefix => $id) if (str_starts_with($module, $prefix)) { $tool = $id; break; }
            $route = str_starts_with($module, 'Settings') ? 'settings' : (str_starts_with($module, 'Project Space') ? 'projects' : 'tools');
            $steps[] = self::lesson('module-'.($index+1), $module, 'Review this workspace for your '.$r['label'].' responsibilities. Some named capabilities are not exposed as dashboard forms; availability and permissions must be checked on the actual screen.', $route, $tool, 'Find and assess the relevant '.$module.' evidence', [
                $tool !== '' ? 'Open this workspace using Open workspace; confirm the selected project before relying on its records.' : 'Open '.$route.' and locate this capability if offered. If it is absent, record it as unavailable and ask the responsible team member for the evidence.',
                self::MODULE_PRACTICE[$tool] ?? 'Check the reference, revision or date, owner and status against source evidence. An empty list means no returned records, not a completed requirement.',
                'Identify the next action and responsible reviewer. When only a read-only scaffold is available, it cannot create, issue, approve or upload records. An enhanced module may provide authorised actions; check its controls and confirmation requirements.',
                'For a supported edit, check required fields and review the result after saving and reloading. If denied, request the required appointment or permission; changing a role label does not grant access.',
            ]);
        }
        $steps[] = self::lesson('evidence', 'Evidence & professional responsibility', $r['evidence'], 'tools', '', 'Identify evidence that still requires human review', ['Check the relied-upon revision, source and assumptions.', 'Identify the appointed professional or commercial decision maker; learning completion is not professional certification.']);
        $steps[] = self::lesson('collaborate', 'Collaborate and hand over cleanly', 'A handoff needs the exact evidence, recipient and expected decision.', 'inbox', '', 'Prepare a traceable handoff', ['Review the project inbox and note the responsible recipient and due date.', 'Prepare the reference, revision, unresolved question and requested action. Send only through an available authorised communication workflow and check its actual result.']);
        $steps[] = self::lesson('boundaries', 'Authority and integration boundaries', $r['boundary'], 'command', '', 'Recognise actions this workspace cannot perform for you', ['Confirm your membership, project appointment and effective permissions before any action.', 'Payments and escrow views contain internal metadata only. Municipal readiness is not authority approval. Missing providers and controls remain unavailable.']);
        $steps[] = self::lesson('finish', 'Review and resume your training', 'Finishing marks the training as complete; it does not certify competence or complete business records.', 'command', '', 'Explain your next real task and its review gate', ['Name the correct project, next task, required evidence and responsible reviewer.', 'Complete the final lesson to save training progress; reopen training to resume or replay. Recheck unresolved exercises in the actual workspace.']);
        if ($membership === 'viewer') {
            foreach ($steps as &$step) {
                $step['route'] = 'command';
                $step['tool'] = '';
                $step['body'] .= ' Your organisation membership is read-only; this lesson is an observation exercise.';
                $step['instructions'] = ['With read-only access, review only records already shared with you for: '.$step['objective'].'.', 'Identify the evidence and responsible owner. Ask an administrator about missing access; do not attempt an edit, issue, approval or payment.'];
                $step['expectedResult'] = 'You can explain the shared evidence and identify the authorised person who would perform the action; no business record is changed.';
            }
            unset($step);
        }
        return ['version'=>self::VERSION, 'profile'=>$key, 'label'=>$r['label'], 'steps'=>$steps];
    }

    private static function lesson(string $id, string $title, string $body, string $route, string $tool, string $objective, array $instructions): array
    {
        return compact('id', 'title', 'body', 'route', 'tool', 'objective', 'instructions') + [
            'expectedResult'=>'You can demonstrate this outcome: '.$objective.'. Retain the record reference, evidence and next responsible reviewer, or record the missing capability or evidence explicitly.',
            'troubleshooting'=>['Missing records: confirm the organisation and selected project; ask the project owner about sharing.', 'Denied or unavailable action: retain the error and request the required permission or capability. Do not repeatedly submit or treat an error as success.', 'Conflicting or stale data: reload and compare the current revision before retrying an authorised change.'],
            'checkpoint'=>'For "'.$title.'", identify the evidence you checked, one unresolved item or confirmed result, and who owns the next action. Next records lesson progress only.',
        ];
    }

    private static function generalSteps(string $membership): array
    {
        $step = self::lesson('welcome', 'Confirm your role profile', 'Your saved role profile is not configured. Ask your organisation administrator to set it before beginning role-specific training.', 'command', '', 'Identify your active organisation and permitted scope', ['Check the active organisation and your membership; '.($membership === 'viewer' ? 'your access is read-only.' : 'a presentation label does not grant permission.'), 'Ask the administrator to configure your professional profile; only review records shared with you.']);
        $steps = [$step];
        foreach (['projects'=>'Confirm your project', 'role-work'=>'Confirm your permitted work', 'collaborate'=>'Identify your responsible contact', 'finish'=>'Review your next step'] as $id=>$title) {
            $steps[] = self::lesson($id, $title, 'Your role-specific curriculum becomes available after your saved profile is configured. Learning progress does not change access.', 'command', '', $title, ['Review the organisation and project information already shared with you; '.($membership === 'viewer' ? 'access is read-only.' : 'use only your effective permissions.'), 'Identify the responsible administrator or project owner and request missing information before attempting a workflow.']);
        }
        return ['version'=>self::VERSION, 'profile'=>'general', 'label'=>'Workspace member', 'steps'=>$steps];
    }

    /** @return array<string,mixed> */
    public static function profile(string $role): array
    {
        return self::ROLES[$role] ?? [];
    }

    /** @return list<string> */
    public static function roles(): array
    {
        return array_keys(self::ROLES);
    }
}
