(function(root){
  'use strict';
  // Municipal Tracker (municipal_tracker) + Council Navigator (council_navigator).
  // v1.html ships both as read-only scaffolds, so this controller renders a real,
  // project-scoped municipal workspace into #main and drives the shared municipal
  // readiness backend through the adapter (list -> create -> transition), all
  // read-back verified. Honest boundary: no external authority submission and no
  // certificate issuance is ever claimed.
  const TOOLS=['municipal_tracker','council_navigator'];
  const BOOL_LABELS={
    changesLoadBearing:'Load-bearing or structural changes',
    changesDrainageOrStormwater:'Drainage / stormwater / services impact',
    publicAccessOrAssembly:'Public access or assembly',
    envelopeEnergyImpact:'Envelope / energy (SANS 10400-XA) impact',
    zoningKnown:'Zoning and land use are known',
    coverageOrParkingRisk:'Coverage / FAR / parking risk is unresolved',
    boundaryOrServitudeUnclear:'Boundary / SG diagram / servitude unclear',
    heritagePotential:'Heritage trigger',
    environmentalSensitivity:'Environmental sensitivity',
    trafficImpact:'Traffic or access impact',
    nbrAdvisoryReviewed:'NBR/SANS advisory pre-check reviewed',
    clientAuthorityConfirmed:'Client authority confirmed'
  };
  const DOC_LABELS={title_deed:'Title deed',sg_diagram:'SG diagram / boundary evidence',zoning_certificate:'Zoning certificate',client_authority:'Client authority',appointment_record:'Appointment record',heritage_comment:'Heritage comment',traffic_comment:'Traffic comment',environmental_comment:'Environmental comment'};
  const DISC_LABELS={client:'Client / developer',lead_professional:'Lead professional',architect:'Architect',municipal_coordinator:'Municipal coordinator',structural_engineer:'Structural engineer',civil_engineer:'Civil engineer',fire_consultant:'Fire consultant',energy_consultant:'Energy consultant',town_planner:'Town planner',land_surveyor:'Land surveyor',quantity_surveyor:'Quantity surveyor',heritage_practitioner:'Heritage practitioner',environmental_practitioner:'Environmental practitioner',traffic_engineer:'Traffic engineer'};
  const CATEGORY_LABELS={property_and_municipal_facts:'Property & municipal facts',land_use_and_zoning:'Land use & zoning',professional_team:'Professional team',nbr_sans_advisory_precheck:'NBR/SANS advisory pre-check',drawing_register:'Drawing register',supporting_documents:'Supporting documents',professional_signoffs:'Professional sign-offs',client_authority:'Client authority'};
  function install({context}){
    const ownsRoute = toolId => { const view = typeof state === 'object' ? state : root.state; return !view || view.tool === undefined || view.tool === toolId; };
    if(!context||typeof context.getState!=='function')throw Error('Municipal controller requires the shared context.');
    const adapter=root.V1CloneMunicipalAdapter.create(context);
    const esc=v=>String(v==null?'':v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const isMunicipal=id=>TOOLS.includes(id);
    let generation=0,disposed=false,active=false,tool=null,submissions=[],readiness=[];
    function notice(message){if(disposed||!ownsRoute(tool))return;
      root.showToast?.(message);
      const el=root.document?.querySelector?.('#main [data-mun-status]');
      if(el){el.textContent=message;el.setAttribute?.('role','status');}
    }
    function humanize(key){return CATEGORY_LABELS[key]||String(key||'').replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase());}
    function checkdot(status){const s=String(status||'');if(s==='complete')return'complete';if(s==='missing')return'missing';if(s==='not_applicable')return'not_applicable';return'review';}
    function categoryPanel(){
      const latest=readiness[0];
      if(!latest)return '<section class="panel" data-mun-readiness><h2>Submission readiness categories</h2><p>No readiness assessment has been recorded for this project yet.</p></section>';
      const cats=latest.categories||{};
      const keys=['property_and_municipal_facts','land_use_and_zoning','professional_team','nbr_sans_advisory_precheck','drawing_register','supporting_documents','professional_signoffs','client_authority'];
      const items=keys.map(key=>{
        const c=cats[key];
        if(!c)return `<li data-mun-cat="${esc(key)}"><b>${esc(humanize(key))}</b> · not returned</li>`;
        const checks=Array.isArray(c.checks)?c.checks.map(ch=>`<li class="mun-check" data-mun-check-status="${esc(checkdot(ch.status))}">${esc(ch.label||ch.id)} <span class="muted">${esc(ch.status)}${ch.owner?' · '+esc(ch.owner):''}</span></li>`).join(''):'';
        return `<li data-mun-cat="${esc(key)}"><b>${esc(humanize(key))}</b> · ${esc(c.score==null?'—':c.score+'%')} <span class="muted">(${esc(c.complete??0)}/${esc(c.applicable??0)})</span><ul>${checks}</ul></li>`;
      }).join('');
      const blockers=(latest.blockers||[]).length?`<p class="muted">Blockers: ${esc(latest.blockers.join('; '))}</p>`:'';
      return `<section class="panel" data-mun-readiness><h2>Submission readiness categories — latest assessment</h2><ul class="mun-categories">${items}</ul>${blockers}</section>`;
    }
    function submissionRows(){
      if(!submissions.length)return '<tr><td colspan="5">No municipal submission readiness records for this project.</td></tr>';
      return submissions.map(sub=>{
        const ready=sub.readyForProfessionalSubmissionReview?'Yes':'No — blockers remain';
        const action=sub.professionallyReviewed?'<span class="muted">Reviewed</span>':`<button class="btn" data-mun-approve="${esc(sub.id)}">Approve for review</button>`;
        return `<tr data-mun-row="${esc(sub.id)}"><td><strong>${esc(sub.id.slice(0,8))}</strong><br><span class="muted">${esc(sub.municipality||'Municipality not recorded')}</span></td><td><span class="pill">${esc(sub.status)}</span></td><td>${sub.overallScore==null?'—':esc(sub.overallScore)+'%'}</td><td>${esc(ready)}</td><td>${action}</td></tr>`;
      }).join('');
    }
    function formPanel(){
      const occ=adapter.occupancy.map(o=>`<option value="${esc(o)}">${esc(humanize(o))}</option>`).join('');
      const bools=adapter.boolFacts.map(f=>`<label class="mun-inline"><input type="checkbox" data-mun-bool="${esc(f)}"> ${esc(BOOL_LABELS[f]||f)}</label>`).join('');
      const docs=adapter.documents.map(d=>`<label class="mun-inline"><input type="checkbox" data-mun-doc="${esc(d)}"> ${esc(DOC_LABELS[d]||d)}</label>`).join('');
      const signs=adapter.disciplines.map(d=>`<label class="mun-inline"><input type="checkbox" data-mun-signoff="${esc(d)}"> ${esc(DISC_LABELS[d]||d)}</label>`).join('');
      return `<section class="panel" data-mun-form hidden><h2>New submission readiness assessment</h2>
        <label>Occupancy type <select data-mun-field="occupancyType">${occ}</select></label>
        <label>Estimated construction value (ZAR) <input type="number" min="1" step="10000" data-mun-field="estimatedConstructionValueZar" value="1000000"></label>
        <fieldset><legend>Scope triggers</legend>${bools}</fieldset>
        <fieldset><legend>Supporting documents available</legend>${docs}</fieldset>
        <fieldset><legend>Professional sign-offs recorded</legend>${signs}</fieldset>
        <div class="mun-actions"><button class="btn" data-mun-submit>Create readiness assessment</button><button class="btn" data-mun-cancel>Cancel</button></div>
      </section>`;
    }
    function render(){if(disposed||!ownsRoute(tool))return;
      const main=root.document.querySelector('#main');if(!main)return null;
      const s=context.getState();
      const project=(s.projects||[]).find(p=>p.id===s.selectedProjectId)?.name||'Selected project';
      const isNav=tool==='council_navigator';
      const title=isNav?'Council Navigator':'Municipal Submission Tracker';
      const intro=isNav?'Navigate the municipal submission-readiness categories and council-facing requirements for the selected project.':'Track municipal submission readiness records and move a ready pack into professional review.';
      main.innerHTML=`<div class="mun-workspace" data-mun-tool="${esc(tool)}">
        <div class="mun-hero"><div><h1>${esc(title)}</h1><p>${esc(intro)}</p><p>Project: <strong>${esc(project)}</strong> · ${submissions.length} submission record(s)</p></div>
          <button class="btn" data-mun-create>+ New submission readiness</button></div>
        <p class="pill" data-mun-status role="status">${esc(adapter.boundary)}</p>
        ${isNav?categoryPanel():''}
        <section class="panel"><h2>Municipal submissions</h2>
          <table class="table"><thead><tr><th>Submission</th><th>Status</th><th>Score</th><th>Ready for review</th><th>Action</th></tr></thead>
          <tbody>${submissionRows()}</tbody></table></section>
        ${formPanel()}
      </div>`;
      bind(main);
      return main;
    }
    function gather(){
      const main=root.document.querySelector('#main');if(!main)return null;
      const sel=main.querySelector('[data-mun-field="occupancyType"]');
      const val=main.querySelector('[data-mun-field="estimatedConstructionValueZar"]');
      const input={occupancyType:sel?.value,estimatedConstructionValueZar:Number(val?.value),availableSupportingDocuments:[],professionalSignoffs:[]};
      main.querySelectorAll('[data-mun-bool]').forEach(el=>{if(el.checked)input[el.getAttribute('data-mun-bool')]=true;});
      main.querySelectorAll('[data-mun-doc]').forEach(el=>{if(el.checked)input.availableSupportingDocuments.push(el.getAttribute('data-mun-doc'));});
      main.querySelectorAll('[data-mun-signoff]').forEach(el=>{if(el.checked)input.professionalSignoffs.push(el.getAttribute('data-mun-signoff'));});
      return input;
    }
    function bind(main){
      const panel=main.querySelector('[data-mun-form]');
      const create=main.querySelector('[data-mun-create]');
      if(create)create.addEventListener('click',()=>{if(panel)panel.hidden=false;});
      const cancel=main.querySelector('[data-mun-cancel]');
      if(cancel)cancel.addEventListener('click',()=>{if(panel)panel.hidden=true;});
      const submit=main.querySelector('[data-mun-submit]');
      if(submit)submit.addEventListener('click',async()=>{
        const input=gather();if(!input)return;
        if(!input.occupancyType||!Number.isFinite(input.estimatedConstructionValueZar)||input.estimatedConstructionValueZar<=0){notice('Enter an occupancy type and a positive construction value. Nothing was submitted.');return;}
        if(!root.confirm?.('Create a municipal submission readiness assessment for the selected project? It is an internal readiness record, not a submission to the municipality.')){notice('Submission readiness not created.');return;}
        submit.disabled=true;
        try{const rec=await adapter.createSubmission(input);await refresh();notice('Submission readiness '+rec.id.slice(0,8)+' verified. Internal advisory record only — not a municipal submission or approval.');}
        catch(error){notice(error.message+' No success claimed.');}
        finally{submit.disabled=false;}
      });
      main.querySelectorAll('[data-mun-approve]').forEach(btn=>btn.addEventListener('click',async()=>{
        const id=btn.getAttribute('data-mun-approve');
        const reason=root.prompt?.('Professional review reason for approving this submission readiness record:');
        if(!reason||!reason.trim()){notice('A professional review reason is required. Nothing was submitted.');return;}
        if(!root.confirm?.('Approve this readiness record for professional submission review? This records an internal professional review decision, not a municipal approval.')){notice('Professional review approval cancelled.');return;}
        btn.disabled=true;
        try{await adapter.transitionSubmission(id,{status:adapter.statuses.approved,reason:reason.trim(),humanConfirmed:true});await refresh();notice('Professional review approval verified in project records.');}
        catch(error){notice(error.message+' No success claimed.');}
        finally{btn.disabled=false;}
      }));
    }
    async function refresh(){
      if(disposed||!ownsRoute(tool))return false;
      const token=++generation;
      try{
        const [subs,reads]=await Promise.all([adapter.listSubmissions(),adapter.listReadiness()]);
        if(disposed||!ownsRoute(tool)||token!==generation)return false;
        submissions=subs;readiness=reads;render();return true;
      }catch(error){if(disposed||!ownsRoute(tool)||token!==generation)return false;notice(error.message+' No municipal records loaded.');render();return false;}
    }
    async function apply(toolId){
      if(!isMunicipal(toolId))return {applied:false,reason:'not-municipal'};
      tool=toolId;active=true;
      const ok=await refresh();
      return {applied:ok,tool:toolId};
    }
    const unsubscribe=typeof context.subscribe==='function'?context.subscribe(()=>{if(active&&!disposed)void refresh();}):null;
    function dispose(){disposed=true;++generation;active=false;if(unsubscribe)unsubscribe();}
    return {apply,dispose,spec:{tools:TOOLS.slice(),name:'Municipal Tracker & Council Navigator',functional:true}};
  }
  root.V1CloneMunicipal={install};
})(window);
