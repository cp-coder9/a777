(function(root){
  'use strict';
  // Survey & Geomatics (survey_geomatics). v1.html ships this as a read-only
  // scaffold; this controller renders a real, project-scoped survey workspace into
  // #main and drives the shared SurveyJobService backend through the adapter
  // (list jobs -> create job -> observation / deliverable / fee -> issue), with
  // every write read-back verified. Honest boundary: no Surveyor-General approval,
  // beacon certificate or official coordinate certification is ever claimed.
  const TOOL='survey_geomatics';
  const TYPE_LABEL={topographical:'Topographical',boundary:'Boundary',beacon_relocation:'Beacon relocation',subdivision:'Subdivision',consolidation:'Consolidation',sectional_title:'Sectional title',servitude:'Servitude',lease_diagram:'Lease diagram',general_plan:'General plan',as_built:'As-built',other:'Other'};
  const OBS_LABEL={control_point:'Control point',benchmark:'Benchmark',beacon:'Beacon',boundary_marker:'Boundary marker',detail_point:'Detail point',other:'Other'};
  const DEL_LABEL={topographical_plan:'Topographical plan',cadastral_diagram:'Cadastral diagram',general_plan:'General plan',beacon_certificate:'Beacon certificate',control_report:'Control report',servitude_diagram:'Servitude diagram',sectional_title_plan:'Sectional title plan',as_built_plan:'As-built plan',coordinate_schedule:'Coordinate schedule',other:'Other'};
  const STATUS_LABEL={draft:'Draft',issued:'Issued'};
  function install({context}){
    // Async work may finish after the shared shell has navigated to another tool.
    const ownsRoute = toolId => { const view = typeof state === 'object' ? state : root.state; return !view || view.tool === undefined || view.tool === toolId; };
    if(!context||typeof context.getState!=='function')throw Error('Survey controller requires the shared context.');
    const adapter=root.V1CloneSurveyAdapter.create(context);
    const esc=v=>String(v==null?'':v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const humanize=(map,key)=>map[key]||String(key||'').replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase());
    let generation=0,disposed=false,active=false,jobs=[],selectedId=null,selected=null;
    function notice(message){if (!ownsRoute(TOOL)) return;
      root.showToast?.(message);
      const el=root.document?.querySelector?.('#main [data-survey-status]');
      if(el){el.textContent=message;el.setAttribute?.('role','status');}
    }
    function cents(v){return v==null?'—':'R'+(Number(v)/100).toFixed(2);}
    function today(){return new Date().toISOString().slice(0,10);}
    function num(v){const n=Number(v);return Number.isFinite(n)?n:NaN;}
    function options(map){return Object.keys(map).map(k=>`<option value="${esc(k)}">${esc(map[k])}</option>`).join('');}

    function jobRows(){
      if(!jobs.length)return '<tr><td colspan="7">No survey jobs recorded for this project.</td></tr>';
      return jobs.map(job=>`<tr data-survey-row="${esc(job.id)}">
        <td><strong>${esc(job.jobNumber)}</strong><br><span class="muted">${esc(String(job.id).slice(0,8))}</span></td>
        <td>${esc(humanize(TYPE_LABEL,job.surveyType))}</td>
        <td>${esc(job.title)}</td>
        <td>${esc(job.responsibleRole)}</td>
        <td><span class="pill">${esc(STATUS_LABEL[job.status]||job.status)}</span></td>
        <td>${esc(job.version)}</td>
        <td><span class="muted">${esc(cents(job.totalFeeCents))}</span> <button class="btn" data-survey-open="${esc(job.id)}">Open</button></td>
      </tr>`).join('');
    }
    function observationRows(){
      const rows=(selected?.observations||[]);
      if(!rows.length)return '<tr><td colspan="6">No observations recorded on this job.</td></tr>';
      return rows.map(o=>`<tr data-survey-observation-row="${esc(o.id)}"><td>${esc(o.pointCode)}</td><td>${esc(humanize(OBS_LABEL,o.observationType))}</td><td>${esc(o.easting)}</td><td>${esc(o.northing)}</td><td>${esc(o.elevation)}</td><td><span class="pill">${esc(o.status)}</span></td></tr>`).join('');
    }
    function deliverableRows(){
      const rows=(selected?.deliverables||[]);
      if(!rows.length)return '<tr><td colspan="4">No deliverables recorded on this job.</td></tr>';
      return rows.map(d=>`<tr data-survey-deliverable-row="${esc(d.id)}"><td>${esc(humanize(DEL_LABEL,d.deliverableType))}</td><td>${esc(d.title)}</td><td><span class="pill">${esc(d.status)}</span></td><td>${esc(d.externalReference||'—')}</td></tr>`).join('');
    }
    function detailPanel(){
      if(!selected)return '<section class="panel" data-survey-detail><h2>Survey job</h2><p>Select a survey job to record control points, deliverables and fees.</p></section>';
      const fee=selected.feePayload;
      const verified=(selected.observations||[]).filter(o=>o.status==='verified').length;
      const complete=(selected.deliverables||[]).filter(d=>d.status==='complete').length;
      return `<section class="panel" data-survey-detail data-survey-job="${esc(selected.id)}">
        <h2>${esc(selected.jobNumber)} — ${esc(selected.title)}</h2>
        <p class="muted">${esc(humanize(TYPE_LABEL,selected.surveyType))} · ${esc(selected.coordinateReferenceSystem)} · ${esc(selected.verticalDatum)} · version ${esc(selected.version)} · <span class="pill">${esc(STATUS_LABEL[selected.status]||selected.status)}</span></p>
        <div class="survey-actions">
          <button class="btn" data-survey-observation ${selected.status==='issued'?'disabled':''}>+ Observation</button>
          <button class="btn" data-survey-deliverable ${selected.status==='issued'?'disabled':''}>+ Deliverable</button>
          <button class="btn" data-survey-fee ${selected.status==='issued'?'disabled':''}>Estimate fees</button>
          <button class="btn" data-survey-issue ${selected.status==='issued'?'disabled':''}>Issue job</button>
        </div>
        <h3>Observations (${(selected.observations||[]).length}) — ${verified} verified</h3>
        <table class="table"><thead><tr><th>Point</th><th>Type</th><th>Easting</th><th>Northing</th><th>Elevation</th><th>Status</th></tr></thead><tbody>${observationRows()}</tbody></table>
        <h3>Deliverables (${(selected.deliverables||[]).length}) — ${complete} complete</h3>
        <table class="table"><thead><tr><th>Type</th><th>Title</th><th>Status</th><th>External reference</th></tr></thead><tbody>${deliverableRows()}</tbody></table>
        <h3>Fee estimate</h3>
        <p class="muted">${fee?`Private professional fee ${esc(cents(selected.privateProfessionalFeeCents))} · statutory ${esc(cents(selected.statutoryFeeCents))} · total ${esc(cents(selected.totalFeeCents))}`:'No fee estimate recorded on this job.'}</p>
        ${selected.snapshotSha256?`<p class="muted">Issue snapshot SHA-256 ${esc(selected.snapshotSha256)}</p>`:''}
        <p class="muted">Surveyor-General approval claimed: no. Official coordinates certified: no.</p>
      </section>`;
    }
    function formPanel(){
      return `<section class="panel" data-survey-form hidden><h2>New survey job</h2>
        <label>Job number <input type="text" maxlength="80" data-survey-field="jobNumber" placeholder="SURV-2026-001"></label>
        <label>Survey type <select data-survey-field="surveyType">${options(TYPE_LABEL)}</select></label>
        <label>Title <input type="text" maxlength="220" data-survey-field="title"></label>
        <label>Responsible professional role <input type="text" maxlength="80" data-survey-field="responsibleRole" value="land_surveyor"></label>
        <label>Coordinate reference system <input type="text" maxlength="180" data-survey-field="coordinateReferenceSystem"></label>
        <label>Vertical datum <input type="text" maxlength="180" data-survey-field="verticalDatum"></label>
        <fieldset><legend>Source basis (at least one)</legend>
          <label>Source title <input type="text" maxlength="220" data-survey-field="sourceTitle"></label>
          <label>Source URL <input type="text" maxlength="1000" data-survey-field="sourceUrl" placeholder="https://"></label>
          <label>Source date <input type="text" maxlength="10" data-survey-field="sourceRetrievedAt" value="${esc(today())}"></label>
        </fieldset>
        <div class="survey-actions"><button class="btn" data-survey-submit>Create survey job</button><button class="btn" data-survey-cancel>Cancel</button></div>
      </section>`;
    }
    function render(){
      if (!ownsRoute(TOOL)) return false;
      const main=root.document.querySelector('#main');if(!main)return null;
      const s=context.getState();
      const project=(s.projects||[]).find(p=>p.id===s.selectedProjectId)?.name||'Selected project';
      main.innerHTML=`<div class="survey-workspace" data-survey-tool="${esc(TOOL)}">
        <div class="survey-hero">
          <div><h1>Survey &amp; Geomatics</h1><p>Survey appointments, control observations, deliverables and geomatics coordination for the selected project.</p><p>Project: <strong>${esc(project)}</strong> · ${jobs.length} survey job(s)</p></div>
          <button class="btn" data-survey-create>+ New survey job</button>
        </div>
        <p class="pill" data-survey-status role="status">${esc(adapter.boundary)}</p>
        <section class="panel"><h2>Survey jobs</h2>
          <table class="table"><thead><tr><th>Job number</th><th>Type</th><th>Title</th><th>Responsible role</th><th>Status</th><th>Version</th><th>Total fee</th></tr></thead>
          <tbody>${jobRows()}</tbody></table>
        </section>
        ${formPanel()}
        ${detailPanel()}
      </div>`;
      bind(main);
      return main;
    }
    function value(main,name){return main.querySelector('[data-survey-field="'+name+'"]')?.value;}
    function gather(){
      const main=root.document.querySelector('#main');if(!main)return null;
      return {jobNumber:String(value(main,'jobNumber')||'').trim(),surveyType:value(main,'surveyType'),title:String(value(main,'title')||'').trim(),responsibleRole:String(value(main,'responsibleRole')||'').trim(),coordinateReferenceSystem:String(value(main,'coordinateReferenceSystem')||'').trim(),verticalDatum:String(value(main,'verticalDatum')||'').trim(),sourceBasis:[{title:String(value(main,'sourceTitle')||'').trim(),url:String(value(main,'sourceUrl')||'').trim(),retrievedAt:String(value(main,'sourceRetrievedAt')||'').trim()}]};
    }
    function observInput(){
      const pointCode=root.prompt?.('Point code');if(!pointCode?.trim())return null;
      const observationType=root.prompt?.('Observation type (control_point, benchmark, beacon, boundary_marker, detail_point, other)','control_point');if(!adapter.observationTypes.includes(String(observationType).trim())){notice('Invalid observation type. Nothing was recorded.');return null;}
      const easting=root.prompt?.('Easting (-29999.9999 to 99999.9999 style decimal)','0');if(!easting){return null;}
      const northing=root.prompt?.('Northing','0');if(!northing){return null;}
      const elevation=root.prompt?.('Elevation','0');if(!elevation){return null;}
      const status=root.prompt?.('Status (observed, verified, rejected, superseded)','observed');if(!adapter.observationStatuses.includes(String(status).trim())){notice('Invalid observation status. Nothing was recorded.');return null;}
      const sourceReference=root.prompt?.('Source reference');if(!sourceReference?.trim())return null;
      const sourceUrl=root.prompt?.('Source URL (http/https)','https://');if(!sourceUrl?.trim())return null;
      const observedAt=root.prompt?.('Observed on (YYYY-MM-DD)',today());if(!observedAt?.trim())return null;
      return {payload:{recordedFrom:'survey_geomatics_workspace'},pointCode:pointCode.trim(),observationType:String(observationType).trim(),easting:num(easting),northing:num(northing),elevation:num(elevation),status:String(status).trim(),sourceReference:sourceReference.trim(),sourceUrl:sourceUrl.trim(),observedAt:observedAt.trim()};
    }
    function delivInput(){
      const deliverableType=root.prompt?.('Deliverable type ('+adapter.deliverableTypes.join(', ')+')','topographical_plan');if(!adapter.deliverableTypes.includes(String(deliverableType).trim())){notice('Invalid deliverable type. Nothing was recorded.');return null;}
      const title=root.prompt?.('Deliverable title');if(!title?.trim())return null;
      const status=root.prompt?.('Deliverable status (draft, pending_review, complete, submitted_externally, accepted, rejected, superseded)','draft');if(!adapter.deliverableStatuses.includes(String(status).trim())){notice('Invalid deliverable status. Nothing was recorded.');return null;}
      const externalReference=root.prompt?.('External reference');if(!externalReference?.trim())return null;
      const sourceUrl=root.prompt?.('Source URL (http/https)','https://');if(!sourceUrl?.trim())return null;
      return {payload:{recordedFrom:'survey_geomatics_workspace'},deliverableType:String(deliverableType).trim(),title:title.trim(),status:String(status).trim(),externalReference:externalReference.trim(),sourceUrl:sourceUrl.trim()};
    }
    function feeInput(){
      const ids=[['fieldDays','Field days'],['officeDays','Office days'],['travelDistanceKm','Travel distance (km)'],['fieldDayRateCents','Field day rate (cents)'],['officeDayRateCents','Office day rate (cents)'],['travelRateCentsPerKm','Travel rate (cents per km)']];
      const out={};
      for(const [key,label] of ids){
        const raw=root.prompt?.(label);if(raw==null||String(raw).trim()===''){notice(label+' is required. Nothing was estimated.');return null;}
        out[key]=num(raw);
      }
      out.complexityAllowanceCents=num(root.prompt?.('Complexity allowance (cents)','0')||0);
      out.statutoryFeeCents=num(root.prompt?.('Statutory fee (cents)','0')||0);
      const statutorySourceTitle=root.prompt?.('Statutory source title');if(!statutorySourceTitle?.trim())return null;
      const statutorySourceUrl=root.prompt?.('Statutory source URL (http/https)','https://');if(!statutorySourceUrl?.trim())return null;
      const statutorySourceRetrievedAt=root.prompt?.('Statutory source retrieved on (YYYY-MM-DD)',today());if(!statutorySourceRetrievedAt?.trim())return null;
      out.statutorySourceTitle=statutorySourceTitle.trim();out.statutorySourceUrl=statutorySourceUrl.trim();out.statutorySourceRetrievedAt=statutorySourceRetrievedAt.trim();
      return out;
    }
    function bind(main){
      const form=main.querySelector('[data-survey-form]');
      const create=main.querySelector('[data-survey-create]');
      if(create)create.addEventListener('click',()=>{if(form)form.hidden=false;});
      const cancel=main.querySelector('[data-survey-cancel]');
      if(cancel)cancel.addEventListener('click',()=>{if(form)form.hidden=true;});
      const submit=main.querySelector('[data-survey-submit]');
      if(submit)submit.addEventListener('click',async()=>{
        const input=gather();
        if(!input||!input.jobNumber||!input.title||!input.responsibleRole||!input.coordinateReferenceSystem||!input.verticalDatum||!input.sourceBasis[0].title||!input.sourceBasis[0].url||!input.sourceBasis[0].retrievedAt){notice('Complete the job number, title, responsible role, CRS, vertical datum and one source basis. Nothing was submitted.');return;}
        if(!root.confirm?.('Create this survey job on the selected project?')){notice('Survey job not created.');return;}
        submit.disabled=true;
        try{const job=await adapter.createJob(input);selectedId=job.id;await refresh();notice('Survey job '+job.jobNumber+' verified in project records. Internal coordination record only.');}
        catch(error){notice(error.message+' No success claimed.');}
        finally{submit.disabled=false;}
      });
      main.querySelectorAll('[data-survey-open]').forEach(btn=>btn.addEventListener('click',async()=>{
        selectedId=btn.getAttribute('data-survey-open');
        try{selected=await adapter.getJob(selectedId);render();}
        catch(error){notice(error.message+' No job loaded.');}
      }));
      const obs=main.querySelector('[data-survey-observation]');
      if(obs)obs.addEventListener('click',async()=>{
        const input=observInput();if(!input)return;
        if(!root.confirm?.('Record this observation on '+selected.jobNumber+'?')){notice('Observation not recorded.');return;}
        obs.disabled=true;
        try{const rec=await adapter.addObservation(selected.id,input);selected=await adapter.getJob(selected.id);await refresh();notice('Observation '+rec.pointCode+' verified on '+selected.jobNumber+'.');}
        catch(error){notice(error.message+' No success claimed.');}
        finally{obs.disabled=false;}
      });
      const del=main.querySelector('[data-survey-deliverable]');
      if(del)del.addEventListener('click',async()=>{
        const input=delivInput();if(!input)return;
        if(!root.confirm?.('Record this deliverable on '+selected.jobNumber+'?')){notice('Deliverable not recorded.');return;}
        del.disabled=true;
        try{const rec=await adapter.addDeliverable(selected.id,input);selected=await adapter.getJob(selected.id);await refresh();notice('Deliverable '+rec.title+' verified on '+selected.jobNumber+'. Internal record — not a Surveyor-General submission.');}
        catch(error){notice(error.message+' No success claimed.');}
        finally{del.disabled=false;}
      });
      const fee=main.querySelector('[data-survey-fee]');
      if(fee)fee.addEventListener('click',async()=>{
        const input=feeInput();if(!input)return;
        if(!root.confirm?.('Record this fee estimate on '+selected.jobNumber+'? It is an internal indicative estimate, not a tariff.')){notice('Fee estimate not recorded.');return;}
        fee.disabled=true;
        try{const result=await adapter.addFee(selected.id,input);selected=await adapter.getJob(selected.id);await refresh();notice('Fee estimate '+cents(result.totalFeeCents)+' verified on '+selected.jobNumber+'. Indicative only.');}
        catch(error){notice(error.message+' No success claimed.');}
        finally{fee.disabled=false;}
      });
      const issue=main.querySelector('[data-survey-issue]');
      if(issue)issue.addEventListener('click',async()=>{
        const professionalRole=root.prompt?.('Responsible professional role for issue',selected.responsibleRole);if(!professionalRole?.trim())return;
        const reason=root.prompt?.('Issue reason');if(!reason?.trim())return;
        const reference=root.prompt?.('Issue reference');if(!reference?.trim())return;
        if(!root.confirm?.('Issue '+selected.jobNumber+'? This records an internal professional issue event, not a Surveyor-General approval or coordinate certification.')){notice('Survey job not issued.');return;}
        issue.disabled=true;
        try{const job=await adapter.issue(selected.id,{confirmed:true,professionalRole:professionalRole.trim(),reason:reason.trim(),reference:reference.trim()});selected=job;await refresh();notice('Survey job '+job.jobNumber+' issued and verified in project records.');}
        catch(error){notice(error.message+' No success claimed.');}
        finally{issue.disabled=false;}
      });
    }
    async function refresh(){
      if (!ownsRoute(TOOL)) return false;
      const token=++generation;
      try{
        const loadedJobs=await adapter.listJobs();
        if(disposed || !ownsRoute(TOOL) || token !== generation)return false;
        const loadedId=selectedId||loadedJobs[0]?.id||null;
        const loadedSelected=loadedId?(loadedJobs.find(j=>j.id===loadedId)||await adapter.getJob(loadedId)):null;
        if(disposed || !ownsRoute(TOOL) || token !== generation)return false;
        jobs=loadedJobs;selectedId=loadedId;selected=loadedSelected;
        render();return true;
      }catch(error){ if (disposed || !ownsRoute(TOOL) || token !== generation) return false;notice(error.message+' No survey records loaded.');render();return false;}
    }
    async function apply(toolId){
      if(toolId!==TOOL)return {applied:false,reason:'not-survey'};
      active=true;
      const ok=await refresh();
      return {applied:ok,tool:toolId};
    }
    const unsubscribe=typeof context.subscribe==='function'?context.subscribe(()=>{if(active&&!disposed)void refresh();}):null;
    function dispose(){disposed=true;++generation;active=false;if(unsubscribe)unsubscribe();}
    return {apply,dispose,spec:{tools:[TOOL],name:'Survey & Geomatics',functional:true}};
  }
  root.V1CloneSurvey={install};
})(window);
