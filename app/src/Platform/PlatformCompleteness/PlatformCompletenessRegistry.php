<?php

declare(strict_types=1);

namespace Architex\Platform\PlatformCompleteness;

final class PlatformCompletenessRegistry
{
    /** @return array<string,mixed> */
    public function catalogue(): array
    {
        return [
            'workstreams'=>$this->workstreams(),
            'qualityCriteria'=>$this->lines(self::QUALITY_CRITERIA),
            'comparisonQuestions'=>$this->lines(self::COMPARISON_QUESTIONS),
            'sections'=>$this->sections(),
            'priorities'=>['critical','high','medium','low'],
            'implementationStatuses'=>['not_started','in_progress','implemented','in_review','changes_requested','accepted','deferred'],
            'qcStatuses'=>['not_reviewed','pass','fail','partial','blocked'],
            'platformRoles'=>['platform_super_admin','product_owner','developer','qa_reviewer','viewer'],
            'evidenceCategories'=>['brief','screenshot','test_report','regulatory_reference','cpd_accreditation','source_document','user_guide','client_example','contract_template','implementation_note','qc_report'],
        ];
    }

    /** @return list<array<string,string>> */
    private function workstreams(): array
    {
        return [
            $this->workstream('formal_navigation','Formal Architex Navigation Framework','Simple workflow-zone navigation; messaging-first but not WhatsApp-dependent; clear areas for projects, CPD, tools, marketplace/resources, admin and user workflows.','App shell, routes, dashboards, sidebar/top navigation, contextual entry points.','PR13 noted: feat: implement formal Architex Navigation Framework','Confirm no over-complicated menus; must support cellphone-first use.'),
            $this->workstream('commercial_workflows','B2 Platform Fee, Proposal Builder and Cashflow Workflows','Platform commercial layer for project proposals, fee calculations, payment/cashflow logic and human approval gates.','Proposal builder, fee/cashflow services, payment state, admin approvals.','PR14 noted: feat: implement arc-b2 platform fee, proposal builder, cashflow workflows','Confirm SA-friendly payment paths and admin override/audit controls.'),
            $this->workstream('cpd_assessment','CPD Assessment Platform Pack','Separate CPD/Learning workflow for content intake, AI draft assessments, human review, accreditation tracking, learner attempts and certificates.','CPD hub, assessment runner, CPD services, certificates, partner/admin dashboards.','PR15 noted: feat: integrate CPD Assessment Platform from arc-cpd feature pack','Historical blockers must be re-tested and never treated as current proof.'),
            $this->workstream('platform_spine','Architex Platform Spine Pack','Core platform architecture/spine tying together auth, roles, dashboards, project workflows, messaging, admin controls and shared services.','Data model, route structure, services, roles, workflow orchestration.','PR16 noted: feat: implement Architex Platform Spine from spine pack','Check main flows and reusable services.'),
            $this->workstream('toolset','Amy/Greg Toolset Implementation Pack','Built-environment calculator/toolbox layer with role/phase filtering, tool run history and dashboard integrations.','Tool registry, calculators, BEP toolbox, contractor bid calculator, export/history.','PR17 noted: feat: Amy/Greg Toolset Implementation','Historical pass claims require current regression evidence.'),
            $this->workstream('dedicated_apps','Architex Master Product Expansion / Unified Dedicated-App Pack','Unified architecture for dedicated apps/services within the Architex platform, including remote desktop, resources, files, CPD and project tools.','Dedicated app registry, app modules, admin controls, shared services.','PR18 noted: Integrate Architex Master Product Expansion pack','Keep one coherent platform, not scattered apps.'),
            $this->workstream('remote_desktop','Secure Remote Desktop Resource Rental Pack','Windows-first host agent and renter browser viewer for controlled workstation/app rental.','Host Agent, viewer, broker, session tokens, allowlist, input controls, audit.','Separate pack referenced historically','Selected-app control, process binding, workspace files and human gates are mandatory.'),
            $this->workstream('resource_sharing','ResourceSharing / Workstation and Resource Booking','Booking and rental workflow for rentable resources such as workstations, GPU/render capacity and tools.','Resource listings, availability, booking, payments, session release.','Not yet assigned in supplied historical register','Integrate broker and payment/release approvals.'),
            $this->workstream('file_manager','FileManager / Workspace File Handoff','Platform-managed workspace files, secure uploads/downloads and post-session handoff.','File manager, document storage, permissions, session outputs.','Not yet assigned in supplied historical register','Workspace-only access and admin upload support required.'),
            $this->workstream('messaging','Messaging Centre + Contextual Messaging','Persistent platform messaging attached to projects, CPD, tasks, proposals and tools.','Messaging centre, contextual side panels, notifications, audit trail.','Not yet assigned in supplied historical register','Platform-native messaging is core; WhatsApp is optional.'),
            $this->workstream('cpd_central','CPD Central / CPD Facilitation Pack','Partner facilitation for lecturer upload, AI draft assessment, human review, accreditation and certificate evidence.','CPD partner/admin roles, accreditation packs, professional-body connector modes.','Not yet assigned in supplied historical register','Never self-award CPD credits.'),
            $this->workstream('admin_content','Admin Content and Document Upload Capability','Admin-managed upload of templates, regulatory documents, CPD source material, evidence, contracts, help and platform resources.','Admin upload UI, storage, metadata, versioning, permissions, audit.','Shared platform service required','Use the shared Admin Content Library / FileManager.'),
        ];
    }

    /** @return array<string,string> */
    private function workstream(string$key,string$title,string$goal,string$buildArea,string$historicalReference,string$notes):array
    {
        return compact('key','title','goal','buildArea','historicalReference','notes');
    }

    /** @return list<array{key:string,title:string,items:list<string>}> */
    private function sections(): array
    {
        $definitions=[
            ['account_identity_roles','Account, identity and roles',self::S1],
            ['navigation_platform_spine','Navigation and platform spine',self::S2],
            ['projects_professional_workflows','Projects and professional workflows',self::S3],
            ['messaging_centre','Messaging centre',self::S4],
            ['file_manager_document_library','FileManager and document library',self::S5],
            ['admin_content_uploads','Admin content/resource uploads',self::S6],
            ['cpd_learning','CPD / learning platform',self::S7],
            ['toolboxes_calculators','Toolboxes and calculators',self::S8],
            ['proposals_fees_cashflow_payments','Proposals, fees, cashflow and payments',self::S9],
            ['resource_sharing_rental','Resource sharing and workstation rental',self::S10],
            ['secure_remote_desktop','Secure remote desktop',self::S11],
            ['marketplace_commercial','Marketplace / commercial expansion',self::S12],
            ['admin_governance','Admin operations and governance',self::S13],
            ['testing_deployment_acceptance','Testing, deployment and acceptance',self::S14],
        ];
        return array_map(fn(array$d):array=>['key'=>$d[0],'title'=>$d[1],'items'=>$this->lines($d[2])],$definitions);
    }

    /** @return list<string> */
    private function lines(string$value): array
    {
        return array_values(array_filter(array_map('trim',explode("\n",trim($value))),static fn(string$x):bool=>$x!==''));
    }

    private const QUALITY_CRITERIA=<<<'TXT'
Pack goal is clearly stated in the product brief.
Implementation matches the goal; no unrelated scope creep.
Correct route/menu entry exists and is reachable by intended roles.
Role permissions are enforced server-side / rules-side, not only in UI.
Data model supports the actual workflow, not only a demo screen.
User can start, save/resume where needed, complete and export/download where needed.
Admin can view/manage/approve/reject where human approval is required.
Messaging/notifications/audit events are integrated where relevant.
Upload/document evidence is supported where the workflow needs external content.
Payment, release, certificate, or approval gates cannot be bypassed client-side.
POPIA/data privacy implications checked.
Cellphone-first usability checked.
TypeScript/lint passes.
Unit/service tests pass.
UI smoke test passes.
No unrelated files deleted or modified.
Build passes.
PR/branch linked in this document.
Greg/Amy implementation note added.
Hymie QC note added.
Leor sign-off recorded.
TXT;
    private const COMPARISON_QUESTIONS=<<<'TXT'
Does the built feature do what the pack promised?
Is it reachable from the correct platform workflow zone?
Does it work for the correct user roles?
Are documents/uploads/evidence handled properly?
Are admin approvals and audit trails present where required?
Does the feature survive refresh/re-login and store data correctly?
Does it remain simple enough for cellphone-first use?
Did the PR avoid unrelated deletions or regressions?
Are tests meaningful, and do lint/build pass?
Is the item ready for Leor sign-off?
TXT;
    private const S1="User signup/login works.\nRole-based onboarding captures built-environment role, professional body and required profile fields.\nRole permissions are enforced consistently.\nAdmin can manage users, roles and access restrictions.\nOrganisation/team/project membership is supported.\nAudit trail records role and permission changes.";
    private const S2="Main navigation is simple and divided into clear workflow zones.\nWorkflow zones include projects, messaging, CPD/learning, toolboxes, marketplace/resources, files and admin.\nMobile/cellphone-first navigation is usable.\nUser dashboards show relevant actions by role.\nEmpty states guide users to the next action.\nOld routes do not conflict with new routes.";
    private const S3="Project creation and project profiles work.\nProject stages/phases are represented.\nTeam members can be invited/assigned.\nTasks, deliverables and status tracking exist.\nBEP/team workflow support exists where required.\nProject documents and evidence attach to the right project/task.\nContextual project messaging exists.";
    private const S4="Platform-native messaging centre exists.\nMessages can attach to projects, tasks, CPD items, tool runs and resource bookings.\nNotifications/reminders exist.\nAdmin/moderation/audit view exists.\nWhatsApp is not required for core platform operation.";
    private const S5="Users can upload/download permitted files.\nAdmin can upload required documents/resources/content.\nFiles have metadata, category, version, owner and permissions.\nFiles can be linked to projects, CPD courses, resources, remote desktop sessions and help content.\nVersion history and archive/retire status exist.\nVirus/content safety and file size rules are planned.\nPOPIA and access restrictions are enforced.";
    private const S6="Admin can upload regulatory documents, templates, examples, CPD source material, contracts, onboarding material and help content.\nAdmin can tag documents by profession, project phase, province/municipality, CPD body, resource type and visibility.\nAdmin can replace/update a document without breaking historical references.\nAdmin can require human approval before publishing.\nPlatform records who uploaded, approved, changed or retired each document.";
    private const S7="CPD is a separate workflow/menu, not mixed into calculators.\nCPD learner profile fields are captured.\nLecturer/content creator upload flow exists.\nAI assessment generation is draft-only.\nHuman lecturer/reviewer/accreditation approval exists.\nAccreditation reference, category, credits/points/hours and validity are tracked.\nLearner assessment runner exists.\nServer-side scoring exists.\nCertificates are verifiable.\nCPD Central/partner facilitation can be supported.";
    private const S8="Calculator registry exists.\nTools are filtered by role and project phase.\nTool inputs/outputs are stored as tool runs.\nTool run history is available.\nExports/reports can be generated where needed.\nCalculators include disclaimers/verification prompts where professional judgement is required.";
    private const S9="Proposal builder exists.\nPlatform fee logic exists.\nCashflow/payment states exist.\nAdmin approval/release gates exist.\nPayment gateway/manual EFT paths are planned.\nPayment events are audited.";
    private const S10="Resource listings exist.\nAvailability/booking exists.\nPricing/payment/release gates exist.\nRemote desktop sessions can link to bookings.\nAdmin can approve, suspend or release sessions/resources.\nResource usage and audit events are tracked.";
    private const S11="Windows Host Agent exists.\nBrowser viewer exists.\nSession broker exists.\nShort-lived session tokens exist.\nApp allowlist and selected-app-only control exist.\nWindow/process binding exists.\nInput filtering exists.\nWorkspace-only file access exists.\nFileManager handoff after session exists.\nHuman approval gates for payment/release exist.\nAudit events exist.";
    private const S12="Marketplace/resource directory placeholder exists.\nListings have owner, price, status and terms.\nApproval workflow exists.\nDispute/support workflow is planned.\nReporting and payout placeholders exist.";
    private const S13="Admin dashboard exists.\nFeature flags/config controls exist.\nAudit logs exist.\nError/reporting dashboard exists.\nData retention/export/delete policy is planned.\nPOPIA compliance review is tracked.";
    private const S14="TypeScript/lint clean.\nUnit tests pass.\nIntegration tests pass for core workflows.\nUI smoke tests pass.\nBuild passes.\nNo unrelated file changes.\nDeployment environment variables documented.\nRollback path documented.\nLeor sign-off recorded.";
}
