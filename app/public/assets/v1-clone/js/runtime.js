(function (global) {
  'use strict';
  const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
  function projectModel(record) {
    const stages = { brief: 'Brief', onboarding: 'Brief', feasibility: 'Brief', appoint: 'Appoint', appointment: 'Appoint', design: 'Design', concept_design: 'Design', design_development: 'Design', comply: 'Comply', compliance: 'Comply', municipal_submission: 'Comply', procure: 'Procure', procurement: 'Procure', tender_procurement: 'Procure', build: 'Build', construction: 'Build', construction_execution: 'Build', pay: 'Pay', payment: 'Pay', closeout: 'Close-out', 'close-out': 'Close-out' };
    return {
      name: escape(record?.name || 'Select a project'),
      location: escape(record?.location || 'Location not recorded'),
      stage: stages[String(record?.lifecycleStage || record?.phase || record?.stage || '').toLowerCase()] || 'Brief',
      client: escape(record?.clientName || 'Not recorded'),
      professional: escape(record?.professionalName || 'Not recorded'),
      municipality: escape(record?.municipality || 'Not recorded'),
      revision: escape(record?.revision || '—'),
      progress: 0,
    };
  }
  function authenticatedRole(current) {
    const member = current.session?.memberships?.find(item => item.organisationId === current.organisationId);
    // Profile labels and presentation lenses never confer access. Keep read-only
    // and unknown memberships out of administrative presentation.
    if (!['owner','admin','member'].includes(member?.role)) return 'viewer';
    const role = member.profileRole || member.role;
    const administrative=['firm_admin','organisation_admin','admin','platform_admin'];
    if (administrative.includes(role) && !['owner','admin'].includes(member.role)) return 'member';
    return role === 'organisation_admin' ? 'admin' : role;
  }
  async function bootstrap({ context, fixtureRequested = false, fixtureAllowed = false, onReady }) {
    if (!context) throw Error('Production context adapter is unavailable.');
    await context.bootstrap();
    const result = { context, state: context.getState(), fixtureMode: fixtureRequested === true && fixtureAllowed === true };
    await onReady?.(result);
    return result;
  }
  function installNavigation({context,viewState,notify}) {
    const modeFor = {
      setMode: args => args[0], openStandalone: () => 'standalone', openProjectSpace: () => 'project',
      attachProject: () => 'project', selectProjectStage: () => 'project', godOpenStage: () => 'project',
      openTool: args => args[1] || context.getState().mode, openMeetings: args => args[0] || context.getState().mode,
      // Tools, inbox and feedback are views of the active workspace. Detaching a
      // project requires an explicit standalone/orientation action.
      openGlobal: args => args[0] === 'projects' ? 'project' : args[0] === 'god' ? 'standalone' : context.getState().mode,
      toggleGodMode: args => (typeof args[0] === 'boolean' ? args[0] : !viewState.godMode) ? 'standalone' : viewState.global === 'god' ? 'project' : context.getState().mode,
    };
    Object.entries(modeFor).forEach(([name, getMode]) => {
      const original = global[name]; if (typeof original !== 'function') return;
      global[name] = function (...args) {
        context.cancelNavigation?.();
        const mode = getMode(args);
        if (mode === context.getState().mode) return original.apply(this, args);
        return context.setMode(mode).then(accepted => accepted === false ? false : original.apply(this, args)).catch(error => { notify(error.message); return false; });
      };
    });
    const render = global.renderAll;
    global.renderAll = function(...args) { viewState.mode=context.getState().mode;return render.apply(this,args); };
    const registry = global.renderRegistry;
    if(typeof registry==='function')global.renderRegistry=function(){return registry.call(this,context.getState().mode);};
  }
  // These native workspaces require an authorised project in their current adapters.
  // Organisation tools and standalone-capable tools must not be added implicitly.
  const PROJECT_REQUIRED_TOOLS = Object.freeze({
    bom:'BoQ takeoff runs and procurement actions use the selected project.',
    safety:'Safety evidence and draft inspections belong to a selected project.',
    council_navigator:'Council submission-readiness evidence belongs to a selected project.',
    municipal_tracker:'Municipal readiness assessments belong to a selected project.',
    project_passport:'Project facts belong to a selected project.',
    project_explorer:'Documents and project records belong to a selected project.',
    team_workspace:'Team appointments and responsibilities belong to a selected project.',
    issues_rfis:'Questions and responses must stay linked to their project.',
    documents_drawings:'Document revisions, reviews and issues belong to a selected project.',
    fee_proposal:'Fee proposals and appointments use the selected project scope.',
    compliance_hub:'Compliance evidence and decisions belong to a selected project.',
    environmental_heritage:'Environmental and heritage case records belong to a selected project.',
    eia_workspace:'Environmental case records belong to a selected project.',
    nhbrc_enrolment:'Enrolment evidence belongs to a selected project.',
    insurance_register:'Insurance register records belong to a selected project.',
    bim_ifc:'Model sources and linked evidence belong to a selected project.',
    supplier_catalog:'Supplier catalogues and procurement records use the selected project scope.',
    market_insights:'This workspace shows recorded project analytics.',
    contractor_compliance:'Contractor compliance records belong to a selected project.',
    approvals_queue:'Approval decisions and their evidence belong to a selected project.',
    payments_escrow:'Payment records and internal release requests belong to a selected project.',
    dispute_resolution:'Dispute records and evidence belong to a selected project.',
    site_instructions:'Site instructions and acknowledgements belong to a selected project.',
    ncr_manager:'Non-conformance records and corrective actions belong to a selected project.',
    snag_manager:'Snags and reinspection records belong to a selected project.',
    fm_bridge:'Handover and close-out evidence belong to a selected project.',
  });
  function installProjectRequirement({context,viewState}) {
    const required=()=>Object.hasOwn(PROJECT_REQUIRED_TOOLS,viewState?.tool||'')&&(context.getState().mode==='standalone'||!context.getState().selectedProjectId);
    function render(){
      if(!required())return false;
      const main=document.querySelector('#main');if(!main)return false;
      const toolId=viewState.tool;
      const available=Boolean(context.getState().projects?.length);
      const existing=main.querySelector('[data-v1-project-required-tool]');
      if(existing?.dataset.v1ProjectRequiredTool===toolId&&existing.dataset.v1ProjectAvailable===String(available))return true;
      const registry=typeof TOOLS==='object'?TOOLS:global.TOOLS||{};
      const section=document.createElement('section');section.className='panel panel-pad';section.dataset.v1ProjectRequiredTool=toolId;section.dataset.v1ProjectAvailable=String(available);
      const title=document.createElement('h1');title.textContent=registry[toolId]?.name||toolId;
      const explanation=document.createElement('p');explanation.textContent=PROJECT_REQUIRED_TOOLS[toolId]+' Attach a project to open this workspace.';
      const status=document.createElement('p');status.setAttribute('role','status');status.textContent='No project is attached. No project records have been loaded.';
      const button=document.createElement('button');button.type='button';button.className='btn';button.textContent='Attach project';button.disabled=!context.getState().projects?.length;
      if(button.disabled)status.textContent='No shared project is available. A project owner can grant access.';
      button.onclick=async()=>{
        if(viewState.tool!==toolId||!required())return;
        const before=context.getState(),owner=[before.organisationId,before.session?.userId].join('|');button.disabled=true;
        try{
          if(await context.setMode('project')===false)return;
          const after=context.getState();
          if(viewState.tool!==toolId||owner!==[after.organisationId,after.session?.userId].join('|'))return;
          if(!after.selectedProjectId){status.textContent='No shared project is available. A project owner can grant access.';return;}
          global.openTool?.(toolId,'project');
        }catch(error){if(viewState.tool===toolId&&section.isConnected)status.textContent=error.message;}
        finally{if(section.isConnected)button.disabled=!context.getState().projects?.length;}
      };
      section.append(title,explanation,status,button);main.replaceChildren(section);return true;
    }
    const original=global.renderMain;
    global.renderMain=function(...args){if(render())return;return original?.apply(this,args);};
    return {render,required};
  }
  // Operating snapshots use some historical destination keys. Only known local
  // native workspaces may be followed; arbitrary URLs and deployment actions stay closed.
  function sourceWorkspace(target){
    const aliases={closeout:'fm_bridge',cpd:'cpd_learning',municipal:'municipal_tracker',site_diary:'itp',inbox:'inbox_action',documents_drawings:'documents_drawings',payments_escrow:'payments_escrow',project_passport:'project_passport'};
    return Object.hasOwn(aliases,target)?aliases[target]:'';
  }
  function installWorkspaceDiscovery({context,viewState,notify=()=>{}}) {
    let catalogue=new Map(),loadedScope=null,generation=0,disposed=false;
    const scope=()=>{const s=context.getState();return JSON.stringify([s.organisationId,s.session?.userId,s.session?.memberships]);};
    const membership=()=>{const s=context.getState();return s.session?.memberships?.find(m=>m.organisationId===s.organisationId)?.role;};
    const allowed=id=>!disposed&&loadedScope===scope()&&['owner','admin','member','viewer'].includes(membership())&&catalogue.get(id==='command'?'practice':id)?.access?.read===true;
    const open=global.openTool;
    if(typeof open==='function')global.openTool=function(id,...args){if(!allowed(id)){notify('This tool is not available with your current membership.');return false;}return open.call(this,id,...args);};
    function decorateSources(){
      if(viewState?.tool!=='approvals_queue'||!allowed('approvals_queue'))return;
      const project=context.getState().selectedProjectId,key=scope();
      for(const cell of document.querySelectorAll('#main .approvals-workspace section:first-of-type tbody tr td:last-child')){
        if(cell.querySelector('[data-open-native-source]'))continue;
        const target=sourceWorkspace(cell.textContent.trim());if(!target||!allowed(target))continue;
        const button=document.createElement('button');button.type='button';button.className='btn ghost';button.dataset.openNativeSource=target;button.textContent='Open source workspace';
        button.onclick=()=>{if(key!==scope()||project!==context.getState().selectedProjectId||!allowed(target))return false;return global.openTool(target,'project');};cell.append(button);
      }
    }
    function reconcile(){
      if(disposed)return;
      const s=context.getState();
      const current=loadedScope===scope();
      // Annotate original navigation without changing its visual hierarchy.
      for(const button of document.querySelectorAll('[onclick*="openTool("]')){
        const id=/openTool\(['"]([a-z0-9_]+)['"]/.exec(button.getAttribute('onclick')||'')?.[1];
        if(!id||id==='command')continue;
        const permitted=allowed(id);
        button.disabled=!permitted;button.setAttribute('aria-disabled',String(!permitted));
        button.title=permitted?(catalogue.get(id)?.recommended?'Suggested for your saved profile. Source operations still verify authority.':'Available in your current organisation scope.'):'Requires an authorised membership for this workspace.';
        button.dataset.workspaceRecommended=String(permitted&&catalogue.get(id)?.recommended===true);
      }
      const active=catalogue.get(viewState?.tool),inspector=document.querySelector('#inspectBody');
      if(inspector&&allowed(viewState?.tool)&&active?.nativeActions?.length&&['context','activity'].includes(viewState?.inspector)){
        const panel=document.createElement('div');panel.className='inspect-note';panel.dataset.workspaceContext=active.id;
        const title=document.createElement('b');title.textContent=active.name;panel.append(title);
        const description=document.createElement('p');description.textContent=viewState.inspector==='activity'?'Activity is not loaded in this inspector. Review persisted records in the native workspace; no completion or external action is inferred.':(active.nativeBoundary||active.evidence);panel.append(description);
        const access=document.createElement('p');access.textContent='Membership: '+membership()+'. Stage: '+active.stage+'. Source operations verify project scope, evidence and professional authority.';panel.append(access);
        const gates=document.createElement('p');gates.textContent='Next action gates: '+[...new Set(active.nativeActions.map(a=>a.humanGate))].join(', ')+'. An internal record is not certification, authority approval or payment.';panel.append(gates);
        inspector.replaceChildren(panel);
      }
      const count=[...catalogue.values()].filter(t=>allowed(t.id)).length;
      for(const button of document.querySelectorAll('#globalMenu .os-item'))if(/openGlobal\(['"]tools['"]/.test(button.getAttribute('onclick')||'')){const meta=button.querySelector('.os-meta');if(meta)meta.textContent=current?String(count):'Unavailable';}
      const nav=document.querySelector('#navigator');
      if(nav){
        nav.querySelector('[data-role-suggested-tools]')?.remove();
        if(!viewState?.tool){
          const suggested=[...catalogue.values()].filter(t=>t.recommended&&allowed(t.id));
          if(suggested.length){const panel=document.createElement('div');panel.className='nav-section';panel.dataset.roleSuggestedTools='';const heading=document.createElement('div');heading.className='nav-heading';heading.textContent='Suggested for your saved profile';panel.append(heading);
            for(const tool of suggested){const button=document.createElement('button');button.type='button';button.className='nav-row';button.textContent=tool.name;button.onclick=()=>allowed(tool.id)?global.openTool(tool.id,context.getState().mode):false;panel.append(button);}nav.prepend(panel);}
        }
        nav.querySelector('[data-native-next-actions]')?.remove();
        const tool=catalogue.get(viewState?.tool);
        if(allowed(viewState?.tool)&&tool?.nativeActions?.length){
          const panel=document.createElement('div');panel.className='nav-section';panel.dataset.nativeNextActions='';
          const heading=document.createElement('div');heading.className='nav-heading';heading.textContent='Native next actions';panel.append(heading);
          for(const action of tool.nativeActions){
            const button=document.createElement('button');button.type='button';button.className='nav-row';button.textContent=action.label;
            button.disabled=action.allowed!==true||!allowed(action.target);
            button.title='Gate: '+action.humanGate+'. The source workflow checks evidence and professional authority.';
            button.onclick=()=>{if(loadedScope!==scope()||action.allowed!==true||!allowed(action.target))return false;
              if(action.target!==viewState.tool)return global.openTool(action.target,s.mode);
              const selectors={'save-takeoff':'[data-boq-form] input[name="title"]','download-advisory-output':'[data-boq-history]','draft-safety-inspection':'[data-site-create]','submit-feedback':'#v1-feedback-new','compliance-evidence':'[data-compliance-create]','readiness':'[data-mun-create]','review-verification':'[data-review-select]'};
              const main=document.querySelector('#main');const selector=selectors[action.id];
              const target=selector&&(main?.querySelector(selector)||main?.querySelector('iframe')?.contentDocument?.querySelector(selector));
              if(target){target.scrollIntoView?.({block:'center'});target.focus?.();if(target.tagName==='BUTTON')target.click();}else{main?.scrollIntoView?.({block:'start'});notify('Use the native workspace controls below; source decisions retain their human gate.');}
              return true;
            };panel.append(button);
          }
          nav.append(panel);
        }
      }
    }
    async function load(){const token=++generation,key=scope();loadedScope=null;catalogue=new Map();reconcile();
      try{const rows=await context.read('/api/v1/tools');if(disposed||token!==generation||key!==scope())return false;
        if(!Array.isArray(rows)||rows.some(t=>!t||typeof t.id!=='string'||typeof t.access?.read!=='boolean'))throw Error('Invalid workspace capability response.');
        catalogue=new Map(rows.map(t=>[t.id,t]));loadedScope=key;reconcile();return true;
      }catch(error){if(!disposed&&token===generation){notify('Workspace capability discovery is unavailable. '+error.message);reconcile();}return false;}
    }
    const unsubscribe=context.subscribe?.(()=>{if(loadedScope!==scope())void load();else reconcile();});
    const originalMain=global.renderMain;
    if(typeof originalMain==='function')global.renderMain=function(...args){
      if(viewState?.tool&&viewState.tool!=='command'&&!allowed(viewState.tool)){
        const main=document.querySelector('#main');if(main){const panel=document.createElement('section');panel.className='panel panel-pad';const message=document.createElement('p');message.textContent='This workspace is unavailable with your current membership. No records have been loaded.';message.setAttribute('role','status');panel.append(message);main.replaceChildren(panel);}return;
      }
      return originalMain.apply(this,args);
    };
    const main=document.querySelector('#main');
    const observer=main&&global.MutationObserver?new global.MutationObserver(decorateSources):null;
    observer?.observe(main,{childList:true,subtree:true});
    return {ready:load(),allowed,reconcile(){reconcile();decorateSources();},getCatalogue:()=>[...catalogue.values()],dispose(){disposed=true;++generation;observer?.disconnect();unsubscribe?.();catalogue.clear();}};
  }
  async function openRequestedWorkspace({context,discovery,parameters,notify=()=>{}}){
    const id=parameters?.get('workspace');if(!id)return false;
    if(!discovery.allowed(id)){notify('The requested native workspace is not permitted.');return false;}
    const project=parameters.get('project'),orientation=parameters.get('orientation')||'project';
    if(!['project','standalone'].includes(orientation)){notify('Unknown workspace orientation.');return false;}
    if(project&&(!/^[a-f0-9]{32}$/i.test(project)||!context.getState().projects.some(p=>p.id===project))){notify('The requested project is not authorised.');return false;}
    if(project&&orientation==='project'&&await context.selectProject(project)===false)return false;
    if(await context.setMode(orientation)===false||!discovery.allowed(id))return false;
    const result=await global.openTool(id,orientation);return result!==false;
  }
  function installRenderLifecycle({originalRender,reconcile,shell,training,afterRender}){
    let depth=0;
    for(const name of ['renderMain','renderNavigator','renderTopbar','renderInspector','renderGlobalMenu']){
      const original=global[name];if(typeof original!=='function')continue;
      global[name]=function(...args){const result=original.apply(this,args);shell?.reconcile();training?.reconcile();afterRender?.();if(name==='renderMain'&&depth===0)reconcile();return result;};
    }
    global.renderAll=function(...args){
      const lens=document.activeElement;
      const restore=depth===0&&lens?.id==='productionRoleLens'&&lens.isConnected&&document.querySelector('#roleSwitch select')===lens;
      const value=restore?lens.value:null;
      depth++;try{return originalRender.apply(this,args);}finally{
        depth--;if(depth===0){reconcile();const replacement=document.querySelector('#productionRoleLens');
          if(restore&&!lens.isConnected&&replacement?.isConnected&&replacement.value===value&&document.activeElement===document.body)replacement.focus({preventScroll:true});
        }
      }
    };
  }
  async function installProduction(context) {
    // Production metadata belongs in the adapter layer, never in immutable reference scripts.
    if (typeof TOOLS === 'object' && TOOLS.refuse_calculator) Object.assign(TOOLS.refuse_calculator, {status:'live',source:'Recorded workflow integrated (internal records only)'});
    if (typeof state === 'object') state.role = authenticatedRole(context.getState());
    if (typeof state === 'object' && state.mode === 'standalone') await context.setMode('standalone');
    global.V1CloneRuntime.controllers.scaffolds = global.V1CloneScaffolds.install({ context });
    global.V1CloneRuntime.controllers.compliance = global.V1CloneComplianceTools.install({ context });
    global.V1CloneRuntime.controllers.documents = global.V1CloneDocuments.install({ context });
    global.V1CloneRuntime.controllers.documentTools = global.V1CloneDocumentTools.install({ context });
    global.V1CloneRuntime.controllers.site = global.V1CloneSiteTools.install({ context });
    global.V1CloneRuntime.controllers.feedback = await global.V1CloneFeedback.install({ context });
    global.V1CloneRuntime.controllers.projects = await global.V1CloneProjects.install({ context });
    global.V1CloneRuntime.controllers.practice = global.V1ClonePractice.install({ context });
    global.V1CloneRuntime.controllers.meetings = global.V1CloneMeetings.install({ context });
    global.V1CloneRuntime.controllers.wingman = global.V1CloneWingman.install({ context });
    global.V1CloneRuntime.controllers.governance = global.V1CloneGovernance.install({ context });
    global.V1CloneRuntime.controllers.iconography = global.V1CloneIconography.install({ context });
    // refuse_calculator (the one module with no write path at all) + the two scaffold controls
    // that had no persistence contract (A006/A007), on the shared module-record store.
    global.V1CloneRuntime.controllers.scaffoldRecords = global.V1CloneScaffoldRecords.install({ context });
    // refuse_calculator — the municipal refuse area calculator (its own domain table + rule packs).
    global.V1CloneRuntime.controllers.refuse = global.V1CloneRefuseCalculator.install({ context });
    // inbox_action — the decision inbox (list -> decide -> read back).
    global.V1CloneRuntime.controllers.inbox = global.V1CloneInbox.install({ context });
    // professional_directory — search + invitation (owned here, not by the shared governance module).
    global.V1CloneRuntime.controllers.directory = global.V1CloneDirectory.install({ context });
    global.V1CloneRuntime.controllers.calcWorkspace = global.V1CloneCalcWorkspace.install({ context });
    global.V1CloneRuntime.controllers.explorer = global.V1CloneExplorer.install({ context });
    global.V1CloneRuntime.controllers.models = global.V1CloneModels.install({ context });
    global.V1CloneRuntime.controllers.survey = global.V1CloneSurvey.install({ context });
    global.V1CloneRuntime.controllers.regulatory = global.V1CloneRegulatory.install({ context });
    global.V1CloneRuntime.controllers.registers = global.V1CloneRegisters.install({ context });
    global.V1CloneRuntime.controllers.rfi = global.V1CloneRfi.install({ context });
    global.V1CloneRuntime.controllers.siteField = global.V1CloneSiteField.install({ context });
    global.V1CloneRuntime.controllers.municipal = global.V1CloneMunicipal.install({ context });
    global.V1CloneRuntime.controllers.proposals = global.V1CloneProposals.install({ context });
    global.V1CloneRuntime.controllers.cpd = global.V1CloneCpd.install({ context });
    // rfq_marketplace — procurement packages + RFQ marketplace controller (Tier-1).
    global.V1CloneRuntime.controllers.procurement = global.V1CloneProcurement.install({ context });
    // fm_bridge — close-out / handover / occupation-readiness controller (task47).
    global.V1CloneRuntime.controllers.closeout = global.V1CloneCloseout.install({ context });
    // approvals_queue — decision queue + release-gate controller (task47).
    global.V1CloneRuntime.controllers.approvals = global.V1CloneApprovals.install({ context });
    // supplier_catalog — supplier register, catalogue revisions, baseline approval (Tier-1, task47).
    global.V1CloneRuntime.controllers.supplier = global.V1CloneSupplier.install({ context });
    // compliance_hub / contractor_compliance / insurance_register — trust compliance items,
    // drawing-compliance screen and controlled-register controller (task47).
    global.V1CloneRuntime.controllers.complianceHub = global.V1CloneComplianceHub.install({ context });
    // contract_admin + payments_escrow — commercial-control controller (task47): baselines,
    // payment schedules, variations, claims, certificates and retention. Payments/escrow stay
    // INTERNAL metadata only: no provider execution, no funds custody, no release claim.
    global.V1CloneRuntime.controllers.commercial = global.V1CloneCommercial.install({ context });
    await Promise.all([global.V1CloneRuntime.controllers.practice.ready, global.V1CloneRuntime.controllers.meetings.ready]);
    const originalDecode = global.decode64;
    const originalAdapt = global.adaptFrame;
    let mountedFrame;
    let mountGeneration = 0;
    global.decode64 = function (encoded) {
      const html = originalDecode(encoded);
      const id = typeof state === 'object' ? state.tool : null;
      const controller = Object.values(global.V1CloneRuntime.controllers).find(item => item.supports?.(id));
      if (typeof SOURCES !== 'object' || SOURCES[id] !== encoded) return html;
      const prepared = controller ? controller.prepareSource(id, html) : html;
      // Parse inertly: literal </head> in script text is not an insertion point.
      // This is the source assigned to the real frame before native mountFrame.
      const doc = new global.DOMParser().parseFromString(prepared, 'text/html');
      doc.body.setAttribute('data-architex-m3-embedded', '');
      if (!doc.head.querySelector('link[data-architex-m3-theme]')) {
        const link = doc.createElement('link');
        link.rel = 'stylesheet';
        link.setAttribute('data-architex-m3-theme', '');
        link.href = new URL('/assets/v1-clone/css/material3-embedded-workspaces.css', global.location.origin).href;
        doc.head.append(link);
      }
      const doctype = prepared.match(/^\s*(<!doctype[^>]*>)/i)?.[1] || '';
      return doctype + '\n' + doc.documentElement.outerHTML;
    };
    global.adaptFrame = function (id, frame) {
      const result = originalAdapt(id, frame);
      // The original skin appends a style at load time; keep component geometry
      // last without changing that skin, its palette or its native handlers.
      const theme = frame.contentDocument?.head.querySelector('link[data-architex-m3-theme]');
      if (theme) frame.contentDocument.head.append(theme);
      mountedFrame?.dispose?.();
      const controller = Object.values(global.V1CloneRuntime.controllers).find(item => item.supports?.(id));
      const token = ++mountGeneration;
      frame.dataset.v1CloneAdapter = 'loading';
      Promise.resolve().then(() => controller?.mountFrame(id, frame)).then(handle => {
        if (token !== mountGeneration || !frame.isConnected) { handle?.dispose?.(); return; }
        mountedFrame = handle;
        frame.dataset.v1CloneAdapter = controller && handle !== false ? 'ready' : 'unavailable';
      }).catch(error => {
        if (token !== mountGeneration || !frame.isConnected) return;
        frame.dataset.v1CloneAdapter = 'error';
        global.showToast?.(error.message);
      });
      return result;
    };
    const originalProject = typeof project === 'object' ? project : null;
    const viewState = typeof state === 'object' ? state : null;
    const notify = message => { if (typeof global.showToast === 'function') global.showToast(message); };
    installNavigation({context,viewState,notify});
    const originalRender = global.renderAll;
    const refreshProject = () => {
      const current = context.getState();
      const selected = current.projects.find(item => item.id === (current.selectedProjectId || current.rememberedProjectId));
      if (viewState) viewState.mode = current.mode;
      if (originalProject) Object.assign(originalProject, projectModel(selected));
    };
    const projectRequirement=installProjectRequirement({context,viewState});
    const discovery=installWorkspaceDiscovery({context,viewState,notify});
    global.V1CloneRuntime.controllers.discovery=discovery;
    await discovery.ready;
    const reconcile = () => {
      global.V1CloneRuntime.controllers.shell.reconcile();
      global.V1CloneRuntime.controllers.training?.reconcile();
      discovery.reconcile();
      if(viewState?.tool&&viewState.tool!=='command'&&!discovery.allowed(viewState.tool))return;
      document.querySelectorAll('.nav-note').forEach(note => {
        if (/Functional sample preserved|functional sample is integrated/.test(note.textContent)) note.textContent = 'Original V1 workspace · production adapter verification in progress.';
      });
      if(projectRequirement.render())return;
      if ((viewState?.tool === 'issues_rfis') && global.V1CloneRuntime.controllers?.rfi) {
        global.V1CloneRuntime.controllers.rfi.apply?.('issues_rfis');
      } else if ((viewState?.tool === 'ncr_manager' || viewState?.tool === 'snag_manager' || viewState?.tool === 'site_instructions') && global.V1CloneRuntime.controllers?.siteField) {
        global.V1CloneRuntime.controllers.siteField.apply?.(viewState?.tool);
      } else if (viewState?.tool === 'documents_drawings' && global.V1CloneRuntime.controllers?.documents) {
        global.V1CloneRuntime.controllers.documents.apply?.('documents_drawings');
      } else if (viewState?.tool === 'cpd_learning' && global.V1CloneRuntime.controllers?.cpd) {
        global.V1CloneRuntime.controllers.cpd.apply?.('cpd_learning');
      } else if (viewState?.tool === 'fee_proposal' && global.V1CloneRuntime.controllers?.proposals) {
        global.V1CloneRuntime.controllers.proposals.apply?.('fee_proposal');
      } else if (viewState?.tool === 'fm_bridge' && global.V1CloneRuntime.controllers?.closeout) {
        global.V1CloneRuntime.controllers.closeout.apply?.('fm_bridge');
      } else if (viewState?.tool === 'approvals_queue' && global.V1CloneRuntime.controllers?.approvals) {
        global.V1CloneRuntime.controllers.approvals.apply?.('approvals_queue');
      } else if ((viewState?.tool === 'municipal_tracker' || viewState?.tool === 'council_navigator') && global.V1CloneRuntime.controllers?.municipal) {
        global.V1CloneRuntime.controllers.municipal.apply?.(viewState?.tool);
      } else if (viewState?.tool === 'rfq_marketplace' && global.V1CloneRuntime.controllers?.procurement) {
        global.V1CloneRuntime.controllers.procurement.apply?.('rfq_marketplace');
      } else if (viewState?.tool === 'supplier_catalog' && global.V1CloneRuntime.controllers?.supplier) {
        global.V1CloneRuntime.controllers.supplier.apply?.('supplier_catalog');
      } else if ((viewState?.tool === 'compliance_hub' || viewState?.tool === 'contractor_compliance' || viewState?.tool === 'insurance_register') && global.V1CloneRuntime.controllers?.complianceHub) {
        global.V1CloneRuntime.controllers.complianceHub.apply?.(viewState?.tool);
      } else if ((viewState?.tool === 'contract_admin' || viewState?.tool === 'payments_escrow') && global.V1CloneRuntime.controllers?.commercial) {
        global.V1CloneRuntime.controllers.commercial.apply?.(viewState?.tool);
      } else {
      const v1RouteMap = {
        'feedback': 'feedback',
        'project_passport': 'governance',
        'professional_directory': 'directory',
        'team_workspace': 'governance',
        'admin_review': 'governance',
        'market_insights': 'governance',
        'iconography_registry': 'iconography',
        'refuse_calculator': 'refuse',
        'inbox_action': 'inbox',
        'project_explorer': 'explorer',
        'bim_ifc': 'models',
        'survey_geomatics': 'survey',
        'eia_workspace': 'regulatory',
        'environmental_heritage': 'regulatory',
        'dispute_resolution': 'registers',
        'nhbrc_enrolment': 'registers'
      };
      const v1ExtraController = global.V1CloneRuntime.controllers?.[v1RouteMap[viewState?.tool]];
      if (v1ExtraController && typeof v1ExtraController.apply === 'function') {
        v1ExtraController.apply(viewState?.tool);
      } else {
        global.V1CloneRuntime.controllers?.scaffolds?.apply?.(viewState?.tool);
      }
      }
    };
    global.V1CloneRuntime.controllers.shell = global.V1CloneShellData.install({ context });
    global.V1CloneRuntime.controllers.training = global.V1CloneTraining.install({ context });
    global.V1CloneRuntime.controllers.permissions = global.V1ClonePermissionControls?.install({ context });
    installRenderLifecycle({originalRender,reconcile,shell:global.V1CloneRuntime.controllers.shell,training:global.V1CloneRuntime.controllers.training,afterRender:discovery.reconcile});
    refreshProject();
    context.subscribe(() => { refreshProject(); global.renderAll(); });
    global.renderAll();
    if(global.location?.search)await openRequestedWorkspace({context,discovery,parameters:new URLSearchParams(global.location.search),notify});
  }
  global.V1CloneRuntime = { authenticatedRole, projectModel, bootstrap, installNavigation, installProjectRequirement, installWorkspaceDiscovery, sourceWorkspace, openRequestedWorkspace, installRenderLifecycle, installProduction, fixtureMode: false, controllers: {} };
  if (!global.__V1_CLONE_NO_AUTOSTART__) {
    bootstrap({
      context: global.V1CloneContext?.create({ api: new global.ArchitexApi.ApiClient(), confirm: text => global.confirm(text) }),
      fixtureRequested: global.__V1_CLONE_VISUAL_TEST__ === true,
      fixtureAllowed: document.querySelector('meta[name="v1-clone-visual-fixtures"]')?.content === 'allowed',
      onReady: async result => {
        global.V1CloneRuntime.context = result.context;
        global.V1CloneRuntime.fixtureMode = result.fixtureMode;
        if (!result.fixtureMode) await installProduction(result.context);
        document.body.dataset.v1CloneState = 'ready';
        document.body.dataset.v1CloneData = result.fixtureMode ? 'visual-fixture-only' : 'production-candidate';
      },
    }).catch(error => {
      document.body.dataset.v1CloneState = 'error';
      const panel = document.createElement('main');
      const title = document.createElement('h1'); title.textContent = 'Candidate could not start';
      const message = document.createElement('p'); message.textContent = error.message;
      const retry = document.createElement('button'); retry.textContent = 'Retry'; retry.onclick = () => global.location.reload();
      panel.append(title, message, retry); document.body.replaceChildren(panel);
    });
  }
})(window);
