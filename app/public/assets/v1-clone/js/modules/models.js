(function(root){
  'use strict';
  // BIM / IFC — functional module controller (Tier-2, Design & Documentation).
  //
  // v1.html ships bim_ifc as a scaffold only: "No IFC input/model metadata/
  // revision/mapping decisions; no simulated parser". This replaces that with a
  // real, project-scoped workspace backed by the real model-metadata routes:
  //   model sources -> GET  /api/v1/projects/{projectId}/model-sources
  //   create source -> POST /api/v1/projects/{projectId}/model-sources
  //   add revision  -> POST /api/v1/model-sources/{sourceId}/revisions
  //   decide        -> POST /api/v1/model-metadata-revisions/{revisionId}/decisions
  //   issue         -> POST /api/v1/model-sources/{sourceId}/issue
  // Every write goes through the adapter (read-back verified). The honest
  // boundary is stated in the workspace: there is no IFC parser and none is
  // simulated — only model metadata, hand-entered facts and mapping decisions.
  const TOOL='bim_ifc';
  const unwrap=r=>r?.data??r;
  function install({context}){
    // Async work may finish after the shared shell has navigated to another tool.
    const ownsRoute = toolId => { const view = typeof state === 'object' ? state : root.state; return !view || view.tool === undefined || view.tool === toolId; };
    const adapter=root.V1CloneModelsAdapter.create(context);
    const esc=v=>String(v==null?'':v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    let generation=0,disposed=false,active=false,sources=[];
    let selectedSourceId=null,selectedRevisionId=null;

    function scope(){
      const s=context.getState();
      if(!s.ready||!s.session||!s.organisationId||!/^[a-f0-9]{32}$/i.test(s.selectedProjectId||''))throw Error('Select an authenticated project.');
      return s;
    }
    function notice(message){if (!ownsRoute(TOOL)) return;
      root.showToast?.(message);
      const el=root.document?.querySelector?.('#main [data-models-status]');
      if(el){el.textContent=message;el.setAttribute('role','status');}
    }
    function num(v,fallback){const n=Number(v);return Number.isFinite(n)?n:fallback;}
    async function load(token=generation){const value=await adapter.listSources();if(disposed||!ownsRoute(TOOL)||token!==generation)return null;sources=value;return sources;}
    function sourceById(id){return sources.find(s=>s.id===id)||null;}
    function revisionsOf(id){const s=sourceById(id);return Array.isArray(s?.revisions)?s.revisions:[];}
    function sourceOptions(selected){
      if(!sources.length)return '<option value="">No model source registered for this project</option>';
      return sources.map(s=>`<option value="${esc(s.id)}"${s.id===selected?' selected':''}>${esc(s.title)} · ${esc(s.format)} · ${esc(s.sourceRevision)} · ${esc(s.status)} v${esc(s.version)}</option>`).join('');
    }
    function revisionOptions(sourceId,selected,onlyApproved){
      const revs=revisionsOf(sourceId).filter(r=>!onlyApproved||r.status==='approved');
      if(!revs.length)return '<option value="">No '+(onlyApproved?'approved ':'')+'revision on this source</option>';
      return revs.map(r=>`<option value="${esc(r.id)}"${r.id===selected?' selected':''}>#${esc(r.revisionNumber)} ${esc(r.revisionLabel)} · ${esc(r.status)}${r.validation?.errors?.length?' · '+esc(r.validation.errors.length)+' validation error(s)':''}</option>`).join('');
    }
    function sourceRows(){
      if(!sources.length)return '<tr><td colspan="7">No model source recorded for this project.</td></tr>';
      return sources.map(s=>{
        const revs=Array.isArray(s.revisions)?s.revisions:[];
        const decisions=Array.isArray(s.decisions)?s.decisions:[];
        const approved=revs.filter(r=>r.status==='approved').length;
        return `<tr data-models-row="${esc(s.id)}">
          <td><strong>${esc(s.title)}</strong><br><span class="muted">${esc(s.responsibleRole)}</span></td>
          <td>${esc(s.format)}</td>
          <td>${esc(s.schemaVersion)}</td>
          <td>${esc(s.sourceRevision)}</td>
          <td><span class="pill">${esc(s.status)}</span></td>
          <td>${revs.length} rev · ${approved} approved · ${decisions.length} decision(s)</td>
          <td>${esc(s.nativeParsingAvailable===true?'parser':'metadata only')}</td>
        </tr>`;
      }).join('');
    }
    function revisionRows(){
      const rows=[];
      for(const s of sources)for(const r of (Array.isArray(s.revisions)?s.revisions:[])){
        rows.push(`<tr>
          <td>${esc(s.title)}</td>
          <td>#${esc(r.revisionNumber)} ${esc(r.revisionLabel)}</td>
          <td>${esc((r.facts||[]).length)}</td>
          <td><span class="pill">${esc(r.status)}</span></td>
          <td>${esc((r.validation?.errors||[]).join('; ')||'—')}</td>
        </tr>`);
      }
      if(!rows.length)return '<tr><td colspan="5">No metadata revision recorded.</td></tr>';
      return rows.join('');
    }
    function decisionRows(){
      const rows=[];
      for(const s of sources)for(const d of (Array.isArray(s.decisions)?s.decisions:[])){
        rows.push(`<tr><td>${esc(s.title)}</td><td>${esc(d.actingRole)}</td><td><span class="pill">${esc(d.action)}</span></td><td>${esc(d.reason)}</td></tr>`);
      }
      if(!rows.length)return '<tr><td colspan="4">No mapping decision recorded.</td></tr>';
      return rows.join('');
    }

    function render(){
      if (!ownsRoute(TOOL)) return false;
      const main=root.document?.querySelector?.('#main');
      if(!main)return false;
      const s=context.getState();
      const project=s.projects?.find(p=>p.id===s.selectedProjectId)?.name||'Selected project';
      selectedSourceId=selectedSourceId&&sourceById(selectedSourceId)?selectedSourceId:(sources[0]?.id||null);
      main.innerHTML=`
        <div class="models-workspace" data-models-workspace="${TOOL}">
          <div class="rfi-hero">
            <div><h1>BIM / IFC Metadata</h1><p>Project: <strong>${esc(project)}</strong> · ${sources.length} model source(s)</p></div>
            <button class="btn" type="button" data-models-refresh>Refresh</button>
          </div>
          <p class="pill" data-models-status role="status">Model metadata &amp; mapping decisions only — no native IFC parsing is performed or simulated. Not a certification or approval.</p>

          <section class="panel">
            <h2>Model sources</h2>
            <table class="table"><thead><tr><th>Source</th><th>Format</th><th>Schema</th><th>Revision</th><th>Status</th><th>Revisions</th><th>Capability</th></tr></thead>
            <tbody>${sourceRows()}</tbody></table>
          </section>

          <section class="panel">
            <h2>Register a model source</h2>
            <p class="muted">Records source metadata and assumptions only. IFC geometry is never parsed here.</p>
            <form data-models-create-form>
              <label class="field"><span>Title</span><input type="text" data-models-title placeholder="Ground floor coordination model"></label>
              <label class="field"><span>Format</span><select data-models-format>${adapter.formats.map(f=>`<option value="${esc(f)}">${esc(f)}</option>`).join('')}</select></label>
              <label class="field"><span>Schema version</span><input type="text" data-models-schema placeholder="IFC4"></label>
              <label class="field"><span>Source revision</span><input type="text" data-models-revision placeholder="REV-C01"></label>
              <label class="field"><span>Extraction mode</span><select data-models-mode>${adapter.extractionModes.map(m=>`<option value="${esc(m)}">${esc(m)}</option>`).join('')}</select></label>
              <label class="field"><span>Provider name (optional)</span><input type="text" data-models-provider placeholder="Authoring tool / consultant"></label>
              <label class="field"><span>Responsible role</span><input type="text" data-models-role placeholder="architect"></label>
              <label class="field"><span>Assumptions (one per line)</span><textarea data-models-assumptions rows="2" placeholder="Metadata compiled from a consultant export; no geometry parsed."></textarea></label>
              <button class="btn" type="button" data-models-create>Register source</button>
            </form>
          </section>

          <section class="panel">
            <h2>Add metadata revision</h2>
            <p class="muted">Facts are recorded mappings (category, label, reference). One JSON object per line; no geometry is derived.</p>
            <form data-models-revision-form>
              <label class="field"><span>Model source</span><select data-models-rev-source>${sourceOptions(selectedSourceId)}</select></label>
              <label class="field"><span>Revision label</span><input type="text" data-models-rev-label placeholder="Rev A - mapped levels"></label>
              <label class="field"><span>Metadata facts (JSON lines)</span><textarea data-models-rev-facts rows="3" placeholder='{"category":"wall","externalId":"W-01","label":"Wall W-01","level":"Level 1","sourceReference":"Schedule row 12","confidence":80}'></textarea></label>
              <label class="field"><span>Notes</span><input type="text" data-models-rev-notes placeholder="Mapped from issued consultant schedule"></label>
              <button class="btn" type="button" data-models-add-revision>Add revision</button>
            </form>
          </section>

          <section class="panel">
            <h2>Mapping decision</h2>
            <p class="muted">Approve only when every fact is correct; validation errors block approval.</p>
            <form data-models-decision-form>
              <label class="field"><span>Model source</span><select data-models-dec-source>${sourceOptions(selectedSourceId)}</select></label>
              <label class="field"><span>Revision</span><select data-models-dec-revision>${revisionOptions(selectedSourceId,null,false)}</select></label>
              <label class="field"><span>Action</span><select data-models-dec-action>${adapter.decisionActions.map(a=>`<option value="${esc(a)}">${esc(a)}</option>`).join('')}</select></label>
              <label class="field"><span>Acting role</span><input type="text" data-models-dec-role placeholder="architect"></label>
              <label class="field"><span>Reason</span><input type="text" data-models-dec-reason placeholder="Mapped levels and walls verified against the issued schedule."></label>
              <label class="field"><input type="checkbox" data-models-dec-human> <span>I confirm this mapping decision on the exact revision.</span></label>
              <button class="btn" type="button" data-models-decide>Record decision</button>
            </form>
          </section>

          <section class="panel">
            <h2>Issue approved metadata</h2>
            <p class="muted">Freezes an immutable snapshot hash of the approved revision. Professional confirmation required.</p>
            <form data-models-issue-form>
              <label class="field"><span>Model source</span><select data-models-iss-source>${sourceOptions(selectedSourceId)}</select></label>
              <label class="field"><span>Approved revision</span><select data-models-iss-revision>${revisionOptions(selectedSourceId,null,true)}</select></label>
              <label class="field"><span>Professional role</span><input type="text" data-models-iss-role placeholder="architect"></label>
              <label class="field"><span>Issue reason</span><input type="text" data-models-iss-reason placeholder="Issued for coordination as the exact mapped metadata."></label>
              <label class="field"><input type="checkbox" data-models-iss-human> <span>I professionally confirm this exact metadata issue.</span></label>
              <button class="btn" type="button" data-models-issue>Issue metadata</button>
            </form>
          </section>

          <section class="panel">
            <h2>Recorded revisions</h2>
            <table class="table"><thead><tr><th>Source</th><th>Revision</th><th>Facts</th><th>Status</th><th>Validation</th></tr></thead>
            <tbody>${revisionRows()}</tbody></table>
          </section>

          <section class="panel">
            <h2>Decision history</h2>
            <table class="table"><thead><tr><th>Source</th><th>Acting role</th><th>Action</th><th>Reason</th></tr></thead>
            <tbody>${decisionRows()}</tbody></table>
          </section>
        </div>`;
      bind(main);
      return true;
    }

    function parseFacts(value){
      const lines=String(value||'').split('\n').map(l=>l.trim()).filter(Boolean);
      if(!lines.length)throw Error('Enter at least one metadata fact (one JSON object per line).');
      return lines.map((line,i)=>{try{return JSON.parse(line);}catch(error){throw Error('Fact line '+(i+1)+' is not valid JSON.');}});
    }
    function setOptions(sel,html){if(sel)sel.innerHTML=html;}

    function bind(main){
      const refresh=main.querySelector('[data-models-refresh]');
      if(refresh)refresh.addEventListener('click',()=>{void load().then(()=>render()).catch(error=>notice(error.message+' No records loaded.'));});

      const create=main.querySelector('[data-models-create]');
      if(create)create.addEventListener('click',async()=>{
        const assumptions=String(main.querySelector('[data-models-assumptions]')?.value||'').split('\n').map(l=>l.trim()).filter(Boolean);
        const input={
          title:main.querySelector('[data-models-title]')?.value,
          format:main.querySelector('[data-models-format]')?.value,
          schemaVersion:main.querySelector('[data-models-schema]')?.value,
          sourceRevision:main.querySelector('[data-models-revision]')?.value,
          extractionMode:main.querySelector('[data-models-mode]')?.value,
          providerName:main.querySelector('[data-models-provider]')?.value,
          responsibleRole:main.querySelector('[data-models-role]')?.value,
          assumptions,
        };
        if(!root.confirm?.('Register this model source metadata on the selected project? No geometry is parsed.'))return;
        create.disabled=true;
        try{const saved=await adapter.createSource(input);selectedSourceId=saved.id;notice('Model source registered and verified (metadata only; no parser).');await load();render();}
        catch(error){notice(error.message+' No success claimed.');}
        finally{create.disabled=false;}
      });

      const revSource=main.querySelector('[data-models-rev-source]');
      if(revSource)revSource.addEventListener('change',()=>{const id=revSource.value;selectedSourceId=id;setOptions(main.querySelector('[data-models-dec-revision]'),revisionOptions(id,null,false));setOptions(main.querySelector('[data-models-iss-revision]'),revisionOptions(id,null,true));});
      const addRev=main.querySelector('[data-models-add-revision]');
      if(addRev)addRev.addEventListener('click',async()=>{
        let factList;
        try{factList=parseFacts(main.querySelector('[data-models-rev-facts]')?.value);}catch(error){notice(error.message);return;}
        const input={
          sourceId:main.querySelector('[data-models-rev-source]')?.value,
          revisionLabel:main.querySelector('[data-models-rev-label]')?.value,
          facts:factList,
          notes:main.querySelector('[data-models-rev-notes]')?.value,
        };
        if(!root.confirm?.('Add this metadata revision to the selected model source?'))return;
        addRev.disabled=true;
        try{const result=await adapter.addRevision(input);selectedRevisionId=result.revision.id;notice('Metadata revision '+result.revision.revisionNumber+' added and verified.');await load();render();}
        catch(error){notice(error.message+' No success claimed.');}
        finally{addRev.disabled=false;}
      });

      const decSource=main.querySelector('[data-models-dec-source]');
      if(decSource)decSource.addEventListener('change',()=>{selectedSourceId=decSource.value;setOptions(main.querySelector('[data-models-dec-revision]'),revisionOptions(selectedSourceId,null,false));});
      const decide=main.querySelector('[data-models-decide]');
      if(decide)decide.addEventListener('click',async()=>{
        const input={
          sourceId:main.querySelector('[data-models-dec-source]')?.value,
          revisionId:main.querySelector('[data-models-dec-revision]')?.value,
          action:main.querySelector('[data-models-dec-action]')?.value,
          actingRole:main.querySelector('[data-models-dec-role]')?.value,
          reason:main.querySelector('[data-models-dec-reason]')?.value,
          humanConfirmed:main.querySelector('[data-models-dec-human]')?.checked===true,
        };
        if(input.humanConfirmed!==true){notice('Confirm the mapping decision checkbox first.');return;}
        if(!root.confirm?.('Record this mapping decision with the system audit trail?'))return;
        decide.disabled=true;
        try{const result=await adapter.decide(input);notice('Decision '+input.action+' recorded; revision is now '+result.revision.status+'.');await load();render();}
        catch(error){notice(error.message+' No success claimed.');}
        finally{decide.disabled=false;}
      });

      const issSource=main.querySelector('[data-models-iss-source]');
      if(issSource)issSource.addEventListener('change',()=>{selectedSourceId=issSource.value;setOptions(main.querySelector('[data-models-iss-revision]'),revisionOptions(selectedSourceId,null,true));});
      const issue=main.querySelector('[data-models-issue]');
      if(issue)issue.addEventListener('click',async()=>{
        const input={
          sourceId:main.querySelector('[data-models-iss-source]')?.value,
          revisionId:main.querySelector('[data-models-iss-revision]')?.value,
          professionalRole:main.querySelector('[data-models-iss-role]')?.value,
          issueReason:main.querySelector('[data-models-iss-reason]')?.value,
          humanConfirmed:main.querySelector('[data-models-iss-human]')?.checked===true,
        };
        if(input.humanConfirmed!==true){notice('Confirm the professional issue checkbox first.');return;}
        if(!root.confirm?.('Issue this approved metadata revision and freeze its snapshot hash?'))return;
        issue.disabled=true;
        try{const issued=await adapter.issue(input);notice('Metadata issued and verified (snapshot '+String(issued.snapshotSha256||'').slice(0,12)+'…). No geometry was parsed.');await load();render();}
        catch(error){notice(error.message+' No success claimed.');}
        finally{issue.disabled=false;}
      });
    }

    async function refresh(){
      if (!ownsRoute(TOOL)) return false;
      const token=++generation;
      try{
        scope();
        await load();
        if(disposed || !ownsRoute(TOOL) || token !== generation)return false;
        return render();
      }catch(error){ if (disposed || !ownsRoute(TOOL) || token !== generation) return false;notice(error.message+' No records loaded.');return false;}
    }
    async function apply(toolId){
      if(toolId!==TOOL)return {applied:false,reason:'not-bim-ifc'};
      active=true;
      const ok=await refresh();
      return ok?{applied:true,tool:TOOL}:{applied:false,tool:TOOL,reason:'workspace unavailable'};
    }
    function supports(toolId){return toolId===TOOL;}
    const unsubscribe=typeof context.subscribe==='function'?context.subscribe(()=>{if(active&&!disposed)void refresh();}):null;
    function dispose(){disposed=true;++generation;active=false;if(unsubscribe)unsubscribe();}

    return {apply,dispose,supports,spec:{tool:TOOL,name:'BIM / IFC Metadata',functional:true}};
  }
  root.V1CloneModels={install};
})(window);
