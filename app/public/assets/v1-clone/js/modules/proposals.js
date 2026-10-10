(function(root){
  'use strict';
  // Fee Proposal — functional module controller (Tier-1 Commercial).
  //
  // v1.html ships fee_proposal as a read-only scaffold ("Original has no fee scope,
  // source calculation, client, stage allocation or issue confirmation form"), so this
  // replaces that with a real, project-scoped workspace backed by the real routes:
  //   proposals list  -> GET  /api/v1/proposals
  //   fee basis       -> POST /api/v1/toolbox/runs (professional_fee_calculator, issued)
  //   create + confirm-> POST /api/v1/proposals -> POST /api/v1/proposals/{id}/issue
  // The adapter is write + read-back verified; nothing is fabricated. The calculator
  // output is advisory only (not a fixed tariff or legal opinion) and is shown as such.
  function install({context}){
    // Async work may finish after the shared shell has navigated to another tool.
    const ownsRoute = toolId => { const view = typeof state === 'object' ? state : root.state; return !view || view.tool === undefined || view.tool === toolId; };
    const adapter=root.V1CloneProposalsAdapter.create(context);
    const esc=v=>String(v==null?'':v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const TOOL='fee_proposal';
    const unwrap=r=>r?.data??r;
    const TEMPLATES=[['standard_sa_professional','Standard South African professional services terms'],['architectural_services','Architectural services terms']];
    let generation=0,disposed=false,active=false,definitions=[],runs=[],proposals=[],lastRun=null;
    const readOnly = () => { const s=context.getState();return s.readOnly===true || !['owner','admin','member'].includes(s.session?.memberships?.find(m=>m.organisationId===s.organisationId)?.role); };

    function scope(){
      const s=context.getState();
      if(!s.ready||!s.session||!s.organisationId||!/^[a-f0-9]{32}$/i.test(s.selectedProjectId||''))throw Error('Select an authenticated project.');
      return s;
    }
    function notice(message){if (!ownsRoute(TOOL)) return;
      root.showToast?.(message);
      const el=root.document.querySelector('#main [data-proposals-status]');
      if(el){el.textContent=message;el.setAttribute('role','status');}
    }
    function num(value,fallback){const n=Number(value);return Number.isFinite(n)?n:fallback;}

    async function load(token=generation){
      const s=scope();
      const loadedProposals=await adapter.listProposals();
      if(readOnly()){
        if(disposed||!ownsRoute(TOOL)||token!==generation)return null;
        proposals=loadedProposals;definitions=[];runs=[];lastRun=null;
        return {s,proposals,definitions,runs};
      }
      const rawDefs=await context.read('/fee-definitions');
      const defs=Array.isArray(rawDefs)?rawDefs:(Array.isArray(rawDefs?.data)?rawDefs.data:[]);
      const loadedDefinitions=defs.filter(d=>d&&d.status==='verified'&&d.sourceStatus==='verified'&&d.configuration);
      const loadedRuns=await adapter.listFeeRuns();
      if(disposed||!ownsRoute(TOOL)||token!==generation)return null;
      proposals=loadedProposals;definitions=loadedDefinitions;runs=loadedRuns;
      return {s,proposals,definitions,runs};
    }

    function defById(id){return definitions.find(d=>d.id===id)||null;}

    function defOptions(selectedId){
      if(!definitions.length)return '<option value="">No verified fee definition available</option>';
      return definitions.map(d=>`<option value="${esc(d.id)}"${d.id===selectedId?' selected':''}>${esc(d.name)} · v${esc(d.version)} · ${esc(d.profession)}</option>`).join('');
    }
    function complexityOptions(def,selected){
      const keys=def?.configuration?.complexities?Object.keys(def.configuration.complexities):[];
      if(!keys.length)return '<option value="">—</option>';
      return keys.map(k=>`<option value="${esc(k)}"${k===selected?' selected':''}>${esc(k)} (${esc(def.configuration.complexities[k])})</option>`).join('');
    }
    function categoryFields(def){
      const keys=def?.configuration?.categories?Object.keys(def.configuration.categories):[];
      if(!keys.length)return '<p class="muted">No fee categories configured.</p>';
      const share=(1/keys.length).toFixed(4);
      return keys.map(k=>`<label class="field"><span>${esc(k)}</span><input type="number" step="0.0001" min="0" value="${share}" data-proposals-category="${esc(k)}" aria-label="Category allocation ${esc(k)}"></label>`).join('');
    }
    function stageFields(def){
      const stages=Array.isArray(def?.configuration?.stages)?def.configuration.stages:[];
      if(!stages.length)return '<p class="muted">No stages configured.</p>';
      return stages.map(s=>`<label class="field"><input type="checkbox" data-proposals-stage="${esc(s.id)}" checked aria-label="Include stage ${esc(s.name)}"> <span>${esc(s.name)} (weight ${esc(s.weight)})</span><input type="number" step="0.01" min="0" max="1" value="0" data-proposals-reduction="${esc(s.id)}" aria-label="Reduction ${esc(s.name)}"></label>`).join('');
    }

    function proposalRows(){
      if(!proposals.length)return '<tr><td colspan="6">No proposals recorded for this project.</td></tr>';
      return proposals.map(p=>`<tr data-proposals-row="${esc(p.id)}">
        <td><strong>${esc(p.title)}</strong></td>
        <td>${esc(p.clientName||'—')}</td>
        <td>v${esc(p.version)}</td>
        <td><span class="pill">${esc(p.status)}</span></td>
        <td>${esc(p.issuedAt?String(p.issuedAt).slice(0,10):'—')}</td>
        <td>${p.auditHash?esc(String(p.auditHash).slice(0,12))+'…':'—'}</td>
      </tr>`).join('');
    }
    function runOptions(selectedId){
      if(!runs.length)return '<option value="">No issued fee calculation for this project</option>';
      return runs.map(r=>`<option value="${esc(r.id)}"${r.id===selectedId?' selected':''}>${esc(String(r.id).slice(0,12))}… · ${esc(r.output?.totalInclVat!=null?('R'+Number(r.output.totalInclVat).toLocaleString('en-ZA')):'—')} · ${esc(String(r.createdAt||'').slice(0,10))}</option>`).join('');
    }

    function render(){
      if (!ownsRoute(TOOL)) return false;
      const main=root.document.querySelector('#main');
      if(!main)return;
      const s=context.getState();
      const project=s.projects?.find(p=>p.id===s.selectedProjectId)?.name||'Selected project';
      const def=defById(definitions[0]?.id)||definitions[0]||null;
      main.innerHTML=`
        <div class="proposals-workspace m3-commercial-workspace" data-proposals-tool="${TOOL}">
          <div class="proposals-hero">
            <div><h1>Fee Proposal</h1><p>Project: <strong>${esc(project)}</strong> · ${proposals.length} proposal(s) · ${runs.length} issued fee basis</p></div>
            <button class="btn" type="button" data-proposals-refresh>Refresh</button>
          </div>
          <p class="pill" data-proposals-status role="status">Advisory proposal aid only — not a fixed tariff or legal opinion. Professional review required before issue.</p>

          <section class="panel">
            <h2>Proposals</h2>
            <table class="table">
              <thead><tr><th>Title</th><th>Client</th><th>Version</th><th>Status</th><th>Issued</th><th>Audit</th></tr></thead>
              <tbody>${proposalRows()}</tbody>
            </table>
          </section>

          <section class="panel" data-proposals-calculator>
            <h2>Professional fee calculator <span class="pill">professional_decision_support</span></h2>
            <p class="muted">${definitions.length?'Indicative proposal aid only; not a fixed tariff or legal opinion.':esc('No verified fee definition is available for this organisation; the calculator is disabled until a verified source and definition exist.')}</p>
            <form data-proposals-calc-form>
              <label class="field"><span>Verified fee definition</span><select data-proposals-definition aria-label="Verified fee definition">${defOptions(def?.id)}</select></label>
              <label class="field"><span>Project value (excl. VAT)</span><input type="number" min="0" step="1000" value="1000000" data-proposals-projectvalue aria-label="Project value"></label>
              <label class="field"><span>Complexity</span><select data-proposals-complexity aria-label="Complexity">${complexityOptions(def,null)}</select></label>
              <fieldset data-proposals-categories><legend>Category allocation (must total 1.0)</legend>${categoryFields(def)}</fieldset>
              <fieldset data-proposals-stages><legend>Stages</legend>${stageFields(def)}</fieldset>
              <label class="field"><span>Discount %</span><input type="number" min="0" max="100" step="1" value="0" data-proposals-discount aria-label="Discount percent"></label>
              <label class="field"><span>Discount reason</span><input type="text" value="" data-proposals-discount-reason aria-label="Discount reason"></label>
              <label class="field"><input type="checkbox" data-proposals-vat checked> <span>VAT applicable</span></label>
              <label class="field"><span>Reason to freeze this fee basis</span><input type="text" value="" data-proposals-calc-reason aria-label="Fee basis reason"></label>
              <button class="btn" type="button" data-proposals-calculate ${definitions.length?'':'disabled'}>Calculate &amp; freeze fee basis</button>
            </form>
            <p class="calc-result" data-proposals-calc-result role="status"></p>
          </section>

          <section class="panel" data-proposals-confirm>
            <h2>Create &amp; confirm proposal</h2>
            <p class="muted">The proposal is created from an issued fee calculation, then professionally confirmed and issued with an acceptance token. This is not legal advice.</p>
            <form data-proposals-confirm-form>
              <label class="field"><span>Issued fee basis</span><select data-proposals-feerun aria-label="Issued fee basis">${runOptions(lastRun?.id)}</select></label>
              <label class="field"><span>Client name</span><input type="text" value="" data-proposals-client aria-label="Client name"></label>
              <label class="field"><span>Client email</span><input type="email" value="" data-proposals-client-email aria-label="Client email"></label>
              <label class="field"><span>Validity (days)</span><input type="number" min="1" max="365" step="1" value="30" data-proposals-validity aria-label="Validity days"></label>
              <fieldset data-proposals-templates><legend>Professional services terms</legend>${TEMPLATES.map(([k,label])=>`<label class="field"><input type="checkbox" checked data-proposals-template="${esc(k)}"> <span>${esc(label)}</span></label>`).join('')}</fieldset>
              <label class="field"><span>Issue reason</span><input type="text" value="" data-proposals-issue-reason aria-label="Issue reason"></label>
              <label class="field"><input type="checkbox" data-proposals-human> <span>I confirm this exact proposal and its advisory fee basis.</span></label>
              <button class="btn" type="button" data-proposals-confirm-submit>Create &amp; issue proposal</button>
            </form>
          </section>
        </div>`;
      if(readOnly()){
        main.querySelector('[data-proposals-calculator]')?.remove();
        main.querySelector('[data-proposals-confirm]')?.remove();
        main.querySelector('[data-proposals-status]').textContent='Read-only access: review recorded proposals. Calculating, creating and issuing proposals requires write permission.';
      }
      bind(main);
    }

    function readSelectedDefinition(main){
      const sel=main.querySelector('[data-proposals-definition]');
      return defById(sel?.value)||definitions[0]||null;
    }
    function collectCalc(main){
      const def=readSelectedDefinition(main);
      if(!def)throw Error('No verified fee definition available.');
      const categorySplits={};
      for(const input of main.querySelectorAll('[data-proposals-category]'))categorySplits[input.getAttribute('data-proposals-category')]=num(input.value,0);
      const selectedStages={};
      for(const box of main.querySelectorAll('[data-proposals-stage]')){
        const id=box.getAttribute('data-proposals-stage');
        const red=main.querySelector('[data-proposals-reduction="'+id+'"]');
        selectedStages[id]={applicable:box.checked===true,reduction:num(red?.value,0)};
      }
      const discountPct=num(main.querySelector('[data-proposals-discount]')?.value,0);
      const discountReason=main.querySelector('[data-proposals-discount-reason]')?.value||'';
      return {
        definitionId:def.id,
        projectValue:num(main.querySelector('[data-proposals-projectvalue]')?.value,NaN),
        complexityId:(main.querySelector('[data-proposals-complexity]')?.value)||Object.keys(def.configuration.complexities||{})[0]||'',
        categorySplits,
        selectedStages,
        discount:{percentage:discountPct/100,reason:discountReason},
        vatApplicable:main.querySelector('[data-proposals-vat]')?.checked!==false,
        issueReason:(main.querySelector('[data-proposals-calc-reason]')?.value||'').trim()||'Fee calculation frozen as proposal basis.',
      };
    }
    function collectConfirm(main){
      const templates=[...main.querySelectorAll('[data-proposals-template]')].filter(b=>b.checked).map(b=>b.getAttribute('data-proposals-template'));
      return {
        feeRunId:(main.querySelector('[data-proposals-feerun]')?.value)||(lastRun?.id||''),
        clientName:(main.querySelector('[data-proposals-client]')?.value||'').trim(),
        clientEmail:(main.querySelector('[data-proposals-client-email]')?.value||'').trim(),
        validityDays:num(main.querySelector('[data-proposals-validity]')?.value,30),
        selectedTemplateKeys:templates,
        issueReason:(main.querySelector('[data-proposals-issue-reason]')?.value||'').trim(),
        humanConfirmed:main.querySelector('[data-proposals-human]')?.checked===true,
      };
    }
    function setCalcResult(main,html){if (!ownsRoute(TOOL)) return;const el=main.querySelector('[data-proposals-calc-result]');if(el)el.innerHTML=html;}

    function bind(main){
      const refresh=main.querySelector('[data-proposals-refresh]');
      if(refresh)refresh.addEventListener('click',()=>{void load().then(render).catch(error=>notice(error.message));});
      const defSel=main.querySelector('[data-proposals-definition]');
      if(defSel)defSel.addEventListener('change',()=>{const def=readSelectedDefinition(main);const box=main.querySelector('[data-proposals-categories]');if(box)box.innerHTML='<legend>Category allocation (must total 1.0)</legend>'+categoryFields(def);const stages=main.querySelector('[data-proposals-stages]');if(stages)stages.innerHTML='<legend>Stages</legend>'+stageFields(def);const cx=main.querySelector('[data-proposals-complexity]');if(cx)cx.innerHTML=complexityOptions(def,null);});
      const calc=main.querySelector('[data-proposals-calculate]');
      if(calc)calc.addEventListener('click',async()=>{
        let values;try{values=collectCalc(main);}catch(error){notice(error.message);return;}
        const sum=Object.values(values.categorySplits).reduce((a,b)=>a+b,0);
        if(Math.abs(sum-1)>0.001){notice('Category allocation must total 1.0 (currently '+sum.toFixed(4)+'). Nothing was submitted.');return;}
        if(!root.confirm?.('Freeze this professional fee calculation as the proposal basis? It is advisory (not a fixed tariff) and requires professional review before issue.'))return;
        calc.disabled=true;setCalcResult(main,'Calculating…');
        try{
          const run=await adapter.calculateProfessionalFee(values);lastRun=run;
          const out=run.output||{};
          setCalcResult(main,'Fee basis verified (run '+esc(String(run.id).slice(0,12))+'…): <strong>R'+esc(Number(out.totalInclVat||0).toLocaleString('en-ZA'))+'</strong> incl. VAT · R'+esc(Number(out.professionalFeeAfterDiscount||0).toLocaleString('en-ZA'))+' after discount · advisoryOnly: '+esc(String(out.advisoryOnly===true))+' · '+esc(out.classification||'professional_decision_support'));
          const fe=main.querySelector('[data-proposals-feerun]');if(fe)fe.innerHTML=runOptions(run.id);
          notice('Fee calculation frozen and verified. Advisory only; not a fixed tariff.');
        }catch(error){setCalcResult(main,'');notice(error.message+' No success claimed.');}
        finally{calc.disabled=false;}
      });
      const submit=main.querySelector('[data-proposals-confirm-submit]');
      if(submit)submit.addEventListener('click',async()=>{
        const values=collectConfirm(main);
        if(values.humanConfirmed!==true){notice('Confirm the proposal checkbox before issuing.');return;}
        if(!values.feeRunId){notice('Select an issued fee calculation first.');return;}
        if(!root.confirm?.('Create and issue this fee proposal from the selected fee basis? An acceptance token is generated for the client.'))return;
        submit.disabled=true;
        try{const result=await adapter.confirmProposal(values);notice('Proposal issued and verified (token '+String(result.acceptanceToken||'').slice(0,8)+'…). Not legal advice.');await load();render();}
        catch(error){notice(error.message+' No success claimed.');}
        finally{submit.disabled=false;}
      });
    }

    async function refresh(){
      if (!ownsRoute(TOOL)) return false;
      const token=++generation;
      try{
        await load();
        if(disposed || !ownsRoute(TOOL) || token !== generation)return false;
        render();
        return true;
      }catch(error){
        if (disposed || !ownsRoute(TOOL) || token !== generation) return false;
        const main=root.document.querySelector('#main');
        if(main){
          const message=[403,404].includes(error.status)?'These records are not available with your current access.':'This workspace could not be loaded. Retry when the connection is available.';
          main.innerHTML=`<div class="proposals-workspace m3-commercial-workspace" data-proposals-tool="${TOOL}"><h1>Fee Proposal</h1><section class="panel"><p data-proposals-status role="status">${message} No records loaded.</p><button class="btn ghost" type="button" data-proposals-refresh>Retry loading</button></section></div>`;
          main.querySelector('[data-proposals-refresh]')?.addEventListener('click',()=>{void refresh();});
        }
        return false;
      }
    }
    async function apply(toolId){
      if(toolId!==TOOL)return {applied:false,reason:'not-fee-proposal'};
      active=true;
      const ok=await refresh();
      return {applied:ok,tool:TOOL};
    }
    const unsubscribe=typeof context.subscribe==='function'?context.subscribe(()=>{if(active&&!disposed)void refresh();}):null;
    function dispose(){disposed=true;++generation;active=false;if(unsubscribe)unsubscribe();}

    return {apply,dispose,spec:{tool:TOOL,name:'Fee Proposal',functional:true}};
  }
  root.V1CloneProposals={install};
})(window);
