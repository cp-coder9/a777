(function (root) {
  'use strict';
  // Contracts refer to the ORIGINAL renderScaffold: four informational cards,
  // four orientation tabs, and no domain form or domain action buttons.
  const project = suffix => '/api/v1/projects/{projectId}' + suffix;
  const read = (path, rows, fields, label, extra = {}) => ({path, rows, fields:fields.split(' '), label, ...extra});
  const list = (path, fields, label, extra) => read(path, '', fields, label, extra);
  const records = (key, label) => read(project('/site-execution-workspace'),key,'id title status dueOn severity priority',label);
  const controlled = key => read(project('/controlled-registers/'+key),'entries','id title reference status ownerRole dueOn version','Register entries');
  const finance = key => read(project('/finance-workspace'),key,{
    variations:'id reference title amountCents timeImpactDays status version',
    claims:'claimId claimNumber claimedToDateCents currency status revision',
    certificates:'certificateId certificateRevisionId netPayableCents currency status revision',
    releaseRequests:'id certificateRevisionId requestedAmountCents currency providerPaidCents status version'
  }[key],key);
  const tools = id => list('/api/v1/tools','id name status evidence','Backend capability metadata',{filter:{field:'id',value:id}});
  const registry = Object.create(null);
  function define(id, reads, gap, extra = {}) {
    registry[id] = {id, ui:'original-prototype', reads, writes:[], gap, ...extra};
  }
  define('project_passport',[read(project(''),'$object','id projectCode name clientName municipality propertyReference propertyUse phase version effectiveRole','Project Passport')], 'No project editing or transition form exists in the original. Phase changes require targetPhase and expectedVersion.');
  define('project_explorer',[read(project('/operating-snapshot'),'records','id title sourceModule recordType status createdAt','Project records (latest returned subset)')], 'Original has no search, record editor or asset upload fields. Snapshot is a bounded record projection, not a complete archive.');
  define('professional_directory',[list('/api/v1/directory/search','profileId displayName profession verificationGrade registrationBody','Professional directory')], 'Original has no directory filters or invitation fields. Inviting requires candidateId, discipline and roleLabel in Team API.');
  define('team_workspace',[list(project('/team'),'id displayName discipline roleLabel status','Project team')], 'Original has no candidateId, discipline or roleLabel fields for invitations, or removal reason.');
  define('inbox_action',[list('/api/v1/inbox','id title detail priority status projectId','Inbox',{projectFilter:true})], 'Original has no selected inbox item or transition/reason/confirmation controls.');
  define('issues_rfis',[records('rfis','RFIs')], 'Original has no RFI title, question, due date, responsible role, evidence or response form.');
  define('documents_drawings',[list('/api/v1/documents?projectId={projectId}','id title documentType revisionCode status issuePurpose','Controlled document revisions')], 'Original has no document metadata, drawing fields, file chooser, reason or human-confirmation controls.');
  define('fee_proposal',[list('/api/v1/proposals','id title status projectId version','Proposals',{projectFilter:true})], 'Original has no fee scope, source calculation, client, stage allocation or issue confirmation form.');
  define('council_navigator',[list(project('/municipal-submission-packs'),'id status createdAt','Municipal submission packs')], 'Original has no drawing viewer, selected revision, comparison or municipal response fields. Internal packs do not claim external submission.');
  define('compliance_hub',[read(project('/trust-compliance-workspace'),'complianceItems','id title status category expiresOn','Trust compliance items')], 'Original has no compliance-item evidence or human review controls. This projection is trust compliance, not an automated cross-standard certification.');
  define('municipal_tracker',[list(project('/submission-readiness'),'id status createdAt','Submission readiness assessments')], 'Original has no municipal profile, versioned requirement matrix, supporting evidence or professional sign-off fields. Readiness is not municipal approval.');
  define('environmental_heritage',[list(project('/regulatory-cases'),'id title caseNumber caseType status version','Environmental and heritage cases')], 'Original has no case type, authority, property facts, evidence or professional determination fields.');
  define('eia_workspace',[list(project('/regulatory-cases')+'?type=environmental','id title caseNumber caseType status version','Environmental cases')], 'Original has no screening facts, studies, participation, EMPr or route determination form.');
  define('refuse_calculator',[list('/api/v1/toolbox/runs','id toolId status createdAt','Refuse calculation history (returned subset)',{filter:{field:'toolId',value:'refuse_area_calculator'},projectFilter:true})], 'Original has no ruleVersionId, occupancyUse, quantity, collection interval, bin/storage/facility inputs or professional declaration. History is user-scoped and bounded.');
  define('survey_geomatics',[list(project('/survey-jobs'),'id title jobNumber status version','Survey jobs')], 'Original has no appointment, cadastral reference, observations, deliverable or issue-declaration fields.');
  define('nhbrc_enrolment',[controlled('nhbrc_enrolment')], 'Original has no title, reference, ownerRole, dueOn, details, evidenceFileIds or humanConfirmed form. This is internal evidence, not NHBRC submission.');
  define('insurance_register',[controlled('insurance_register')], 'Original has no policy title, reference, ownerRole, dueOn, details, evidenceFileIds or humanConfirmed form.');
  define('bim_ifc',[list(project('/model-sources'),'id name title status version','Model sources')], 'Original has no IFC file input, discipline, model source/revision or mapping decisions. No model parsing is simulated.');
  define('rfq_marketplace',[list(project('/procurement-packages'),'id title status version','Procurement packages')], 'Original has no package scope, returnables, invited supplier, quotation, award reason or human-confirmation fields.');
  define('supplier_catalog',[read(project('/supplier-procurement'),'catalogues','id supplierId revision sourceKind itemCount','Catalogue revisions')], 'Original has no supplier, catalogue upload or product editor. API exposes revision metadata here, not individual product detail.');
  define('market_insights',[read(project('/analytics-workspace'),'latestMetrics','id formulaKey value unit asOf','Recorded project metrics')], 'Original has no benchmark source or forecast inputs. Project analytics are not external market intelligence.');
  define('contract_admin',[finance('variations'),finance('claims'),finance('certificates')], 'Original has no baseline, variation, valuation lines, expectedVersion, certification reason or human-confirmation fields.');
  define('contractor_compliance',[controlled('contractor_compliance')], 'Original has no title, reference, ownerRole, dueOn, details, evidenceFileIds or humanConfirmed form.');
  define('approvals_queue',[read(project('/operating-snapshot'),'decisions','id title priority humanGate actionTarget','Project decisions')], 'Original has no selected record, review reason, version or human-confirmation controls. Decisions include missing-record actions; not all are approvals.');
  define('payments_escrow',[finance('certificates'),finance('releaseRequests'),tools('payments_escrow')], 'Payment-provider integration is explicitly skipped. Internal certificate/release metadata remains visible; no transfer, escrow custody or settlement is claimed.',{integration:'approved-skip'});
  define('dispute_resolution',[controlled('dispute_resolution')], 'Original has no title, reference, ownerRole, dueOn, details, evidenceFileIds or humanConfirmed form. Legal resolution is not inferred.');
  define('site_instructions',[records('siteInstructions','Site instructions')], 'Original has no instruction title, detail, recipient, evidence, issue reason or acknowledgement form.');
  define('ncr_manager',[records('ncrs','Non-conformance records')], 'Original has no NCR evidence, severity, corrective-action or review decision form.');
  define('snag_manager',[records('snags','Snag records')], 'Original has no location, evidence, allocation, corrective-action or reinspection decision form.');
  define('fm_bridge',[read(project('/closeout-workspace'),'handoverPacks','id title status createdAt','Handover packs'),read(project('/closeout-workspace'),'handoverDocuments','id title category status','Handover documents')], 'Original has no handover checklist, document association or acceptance form. External FM synchronisation is not provided by the closeout API.');
  define('remote_desktop',[tools('remote_desktop')], 'Remote Desktop integration is explicitly skipped. Host agent, broker, viewer and allowlist remain unconfigured; no remote session is started.',{integration:'approved-skip'});
  define('cpd_learning',[read('/api/v1/cpd/workspace','courses','id title status','CPD courses'),read('/api/v1/cpd/workspace','records','id activityTitle completedOn creditsAwarded professionalBody approvedCategory','Professional learning records')], 'Original has no learner profile, enrolment, assessment answer, accreditation or certificate action controls.');
  define('admin_review',[list('/api/v1/verification-review-queue','id kind grade effectiveGrade registration_body expires_on','Verification review queue')], 'Original has no selected verification, decision, evidence review reason or human-confirmation fields. Permission errors remain visible.');
  define('iconography_registry',[list('/api/v1/tools','id name group stage status','Semantic tool registry metadata')], 'Original has no icon editor or search control. Backend tool metadata is read-only; original icon shapes and tones are preserved.');
  const escape = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const at = (value, path) => path.split('.').reduce((v, k) => v && typeof v === 'object' && Object.prototype.hasOwnProperty.call(v,k) ? v[k] : undefined, value);
  const org = state => state.organisationId || state.org?.id || (typeof state.org === 'string' ? state.org : null);
  const scopeKey = state => JSON.stringify([state.selectedProjectId, org(state), !!state.session, state.session?.userId, state.session?.id, state.ready, state.readOnly, state.role]);
  function projectOf(row) { return row.projectId ?? row.assignment?.projectId; }
  function transform(contract, payload, state) {
    const data = payload && !Array.isArray(payload) && Object.prototype.hasOwnProperty.call(payload,'data') ? payload.data : payload;
    if (contract.rows === '$object' && (!data || data.id !== state.selectedProjectId)) throw Error('Project response does not match the requested project.');
    const responseProject=data?.context?.projectId ?? data?.projectId;
    if (contract.path.includes('{projectId}') && responseProject && responseProject!==state.selectedProjectId) throw Error('API response belongs to a different project.');
    let rows = contract.rows === '$object' ? (data && typeof data === 'object' && !Array.isArray(data) ? [data] : null) : contract.rows ? at(data,contract.rows) : data;
    if (!Array.isArray(rows) || rows.some(r => !r || typeof r !== 'object' || Array.isArray(r))) throw Error('Unexpected API response; no record count is available.');
    if (contract.projectFilter && state.selectedProjectId) rows = rows.filter(r => projectOf(r) === state.selectedProjectId);
    else rows = rows.filter(r => !projectOf(r) || !contract.path.includes('{projectId}') || projectOf(r) === state.selectedProjectId);
    if (contract.filter) rows = rows.filter(r => at(r,contract.filter.field) === contract.filter.value);
    const lines = rows.map(row => contract.fields.flatMap(field => {
      const value=at(row,field);
      return value !== null && value !== undefined && ['string','number','boolean'].includes(typeof value) ? [field+': '+String(value)] : [];
    }).join(' · ') || 'Record returned without mapped display fields.');
    return {status:'ready', count:rows.length, lines};
  }
  async function load(context, id) {
    const contract = registry[id]; if (!contract) return null;
    const state={...context.getState()}, scope=scopeKey(state), requests=new Map();
    const sections=await Promise.all(contract.reads.map(async source => {
      const base={label:source.label,path:source.path,count:null,lines:[]};
      if (!state.session || !org(state) || state.ready === false) return {...base,status:'authentication_required',message:'Sign in and select an organisation to load records.'};
      if (source.path.includes('{projectId}') && !/^[a-f0-9]{32}$/i.test(state.selectedProjectId || '')) return {...base,status:'needs_project',message:'Select a project to load these records.'};
      const path=source.path.replaceAll('{projectId}',encodeURIComponent(state.selectedProjectId || ''));
      try {
        if (!requests.has(path)) requests.set(path,Promise.resolve().then(()=>context.read(path)));
        return {...base,path,...transform(source,await requests.get(path),state)};
      } catch (error) { return {...base,path,status:'error',message:typeof error?.message === 'string' ? error.message : 'Records could not be loaded. Retry after checking access.'}; }
    }));
    return {id,scope,ui:contract.ui,integration:contract.integration || null,gap:contract.gap,writes:[],sections};
  }
  root.V1CloneScaffoldRegistry = {registry,load,transform,escape,scopeKey};
})(window);
