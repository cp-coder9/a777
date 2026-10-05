(function(root){
  'use strict';
  // Contract Administration (contract_admin) + Payments & Escrow (payments_escrow).
  // v1.html ships both as read-only commercial scaffolds, so this controller renders a
  // real, project-scoped commercial workspace into #main and drives the shared
  // commercial-control backend through the adapter (baseline -> payment schedule ->
  // variation -> claim -> certificate -> retention), every write read-back verified.
  //
  // HONEST BOUNDARY: payments_escrow is an approved integration skip. Everything shown
  // is INTERNAL commercial metadata. No funds are held, no payment is executed,
  // settled or released, and no payment/escrow provider is configured. Every release
  // or payout control stays disabled behind a truthful 'provider not configured'
  // notice — no fake payment is ever wired.
  const TOOLS=['contract_admin','payments_escrow'];
  function install({context}){
    // Async work may finish after the shared shell has navigated to another tool.
    const ownsRoute = toolId => { const view = typeof state === 'object' ? state : root.state; return !view || view.tool === undefined || view.tool === toolId; };
    if(!context||typeof context.getState!=='function')throw Error('Commercial controller requires the shared context.');
    const adapter=root.V1CloneCommercialAdapter.create(context);
    const esc=v=>String(v==null?'':v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const isCommercial=id=>TOOLS.includes(id);
    const money=cents=>{const n=Number(cents);return Number.isFinite(n)?'R '+((n/100).toLocaleString('en-ZA',{minimumFractionDigits:2,maximumFractionDigits:2})):'—';};
    const humanize=key=>String(key||'').replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase());
    let generation=0,disposed=false,active=false,tool=null,model=null,errorMessage='';
    function notice(message){if (!ownsRoute(tool)) return;
      root.showToast?.(message);
      const el=root.document?.querySelector?.('#main [data-com-status]');
      if(el){el.textContent=message;el.setAttribute?.('role','status');}
    }
    const centsFromRands=v=>{const n=Number(v);if(!Number.isFinite(n)||n<0)return NaN;return Math.round(n*100);};
    function baselinePanel(){
      const b=model.baseline;
      if(!b)return '<section class="panel" data-com-baseline><h2>Commercial baseline</h2><p>No commercial baseline exists for this project. A baseline is only created from a formally issued professional appointment.</p></section>';
      return `<section class="panel" data-com-baseline>
        <h2>Commercial baseline <span class="pill" data-com-baseline-status>${esc(b.status)}</span></h2>
        <ul class="com-summary">
          <li>Baseline <b>${esc(String(b.id).slice(0,8))}</b> · revision ${esc(b.revision)}</li>
          <li>Original contract sum <b>${esc(money(b.originalContractSumCents))}</b></li>
          <li>Current contract sum <b>${esc(money(b.currentContractSumCents))}</b></li>
          <li>Contingency <b>${esc(money(b.contingencyCents))}</b> · retention <b>${esc((Number(b.retentionBasisPoints)||0)/100)}%</b></li>
          <li>VAT ${b.vatIncluded?'included':'excluded'} · ${esc(b.currency)}</li>
          <li>Snapshot ${esc(String(b.snapshotSha256||'').slice(0,16))}… (immutable revision hash)</li>
        </ul></section>`;
    }
    function variationRows(){
      if(!model.variations.length)return '<tr><td colspan="7">No commercial variations recorded for this project.</td></tr>';
      return model.variations.map(v=>{
        const approvals=(v.approvals||[]).map(a=>a.approvalType+':'+a.decision).join(', ')||'none recorded';
        const actions=[];
        if(v.status==='draft')actions.push(`<button class="btn" data-com-submit-variation="${esc(v.id)}" data-com-version="${esc(v.version)}">Submit for review</button>`);
        if(v.status==='under_review')actions.push(`<button class="btn" data-com-approve-variation="${esc(v.id)}">Record client approval</button>`);
        if(!actions.length)actions.push('<span class="muted">No client action available</span>');
        return `<tr data-com-variation-row="${esc(v.id)}"><td><strong>${esc(v.reference)}</strong><br><span class="muted">${esc(String(v.id).slice(0,8))}</span></td>
          <td>${esc(v.title)}</td><td>${esc(money(v.amountCents))}</td><td>${esc(v.timeImpactDays)} d</td>
          <td><span class="pill" data-com-variation-status>${esc(v.status)}</span><br><span class="muted">v${esc(v.version)}</span></td>
          <td class="muted">${esc(approvals)}</td><td>${actions.join(' ')}</td></tr>`;
      }).join('');
    }
    function scheduleRows(){
      if(!model.schedules.length)return '<tr><td colspan="4">No payment schedule has been issued for this baseline.</td></tr>';
      return model.schedules.map(s=>`<tr data-com-schedule-row="${esc(s.id)}"><td><strong>${esc(s.title)}</strong><br><span class="muted">${esc(String(s.id).slice(0,8))}</span></td>
        <td>${esc(money(s.scheduledTotalCents))}</td><td>${esc((s.milestones||[]).length)} milestone(s)</td>
        <td class="muted">${esc((s.milestones||[]).map(m=>m.label+': '+money(m.amountCents)+' due '+m.dueDate).join(' · '))}</td></tr>`).join('');
    }
    function claimRows(withCertificates){
      if(!model.claims.length)return '<tr><td colspan="'+(withCertificates?6:5)+'">No payment claims recorded for this project.</td></tr>';
      return model.claims.map(c=>{
        const certificate=model.certificates.find(x=>x.claimId===c.claimId)||null;
        const certCell=withCertificates?`<td>${certificate?esc(certificate.status)+'<br><span class="muted">net '+esc(money(certificate.netPayableCents))+'</span>':'<button class="btn" data-com-certify="'+esc(c.claimId)+'">Certify</button>'}</td>`:'';
        return `<tr data-com-claim-row="${esc(c.claimId)}"><td><strong>${esc(c.claimNumber)}</strong><br><span class="muted">${esc(String(c.claimId).slice(0,8))}</span></td>
          <td>${esc(money(c.claimedToDateCents))}</td><td><span class="pill" data-com-claim-status>${esc(c.status)}</span></td>
          <td>${c.disputed?'disputed':'undisputed'}</td><td class="muted">${esc((c.evidenceReferences||[]).join(', '))}</td>${certCell}</tr>`;
      }).join('');
    }
    function certificateRows(){
      if(!model.certificates.length)return '<tr><td colspan="6">No payment certificate revision has been issued (internal metadata only — no payment is implied).</td></tr>';
      return model.certificates.map(c=>`<tr data-com-certificate-row="${esc(c.certificateId)}"><td><strong>${esc(String(c.certificateId).slice(0,8))}</strong><br><span class="muted">rev ${esc(c.revision)}</span></td>
        <td>${esc(money(c.grossCertifiedToDateCents))}</td><td>${esc(money(c.retentionHeldToDateCents))}</td><td>${esc(money(c.otherDeductionsCents))}</td>
        <td>${esc(money(c.netPayableCents))}</td><td><span class="pill">${esc(c.status)}</span></td></tr>`).join('');
    }
    function baselineForm(){
      if(model.baseline)return '';
      return `<section class="panel" data-com-baseline-form hidden><h2>New commercial baseline</h2>
        <p class="muted">A baseline is created against the project's formally issued professional appointment, which is resolved from the live appointments register.</p>
        <label>Contract sum (R) <input type="number" min="1" step="0.01" data-com-field="contractSum" value="250000.00"></label>
        <label>Contingency (R) <input type="number" min="0" step="0.01" data-com-field="contingency" value="0.00"></label>
        <label>Retention (%) <input type="number" min="0" max="20" step="0.1" data-com-field="retentionPercent" value="5"></label>
        <label class="com-inline"><input type="checkbox" data-com-field="vatIncluded" checked> Contract sum includes VAT</label>
        <label>Reason <input type="text" maxlength="1000" data-com-field="baselineReason" value="Baseline created from the issued appointment"></label>
        <div class="com-actions"><button class="btn" data-com-create-baseline>Create baseline</button><button class="btn" data-com-cancel>Cancel</button></div>
        <p class="muted">Internal commercial-control record only — this is not a payment, settlement or certificate of payment.</p></section>`;
    }
    function variationForm(){
      if(!model.baseline)return '';
      return `<section class="panel" data-com-variation-form hidden><h2>New variation</h2>
        <label>Reference <input type="text" maxlength="80" data-com-field="variationReference" value="VO-001"></label>
        <label>Title <input type="text" maxlength="220" data-com-field="variationTitle"></label>
        <label>Description <input type="text" maxlength="10000" data-com-field="variationDescription"></label>
        <label>Amount (R) <input type="number" step="0.01" data-com-field="variationAmount" value="0.00"></label>
        <label>Time impact (days) <input type="number" step="1" data-com-field="variationDays" value="0"></label>
        <div class="com-actions"><button class="btn" data-com-create-variation>Create draft variation</button><button class="btn" data-com-cancel>Cancel</button></div>
        <p class="muted">A draft is only incorporated once a quantity surveyor, the lead professional and the client have each approved it.</p></section>`;
    }
    function scheduleForm(){
      const b=model.baseline;
      if(!b)return '';
      return `<section class="panel" data-com-schedule-form hidden><h2>Issue a payment schedule</h2>
        <p class="muted">Milestones must total the current contract sum (${esc(money(b.currentContractSumCents))}). Nothing is released by issuing a schedule.</p>
        <label>Schedule title <input type="text" maxlength="220" data-com-field="scheduleTitle" value="Payment schedule"></label>
        <label>Reason <input type="text" maxlength="1000" data-com-field="scheduleReason" value="Issued payment schedule"></label>
        <div class="com-milestones" data-com-milestones>
          <div class="com-milestone">Milestone 1 <input type="text" data-com-milestone="label" value="Mobilisation"> <input type="number" step="0.01" data-com-milestone="amount" value="0.00"> <input type="date" data-com-milestone="dueDate" value="2026-12-01"></div>
          <div class="com-milestone">Milestone 2 <input type="text" data-com-milestone="label" value="Completion"> <input type="number" step="0.01" data-com-milestone="amount" value="0.00"> <input type="date" data-com-milestone="dueDate" value="2027-06-01"></div>
        </div>
        <div class="com-actions"><button class="btn" data-com-create-schedule>Issue schedule</button><button class="btn" data-com-cancel>Cancel</button></div></section>`;
    }
    function claimForm(){
      const milestones=model.schedules.flatMap(s=>(s.milestones||[]).map(m=>({id:m.id,label:s.title+' · '+m.label})));
      if(!model.baseline||!milestones.length)return '';
      const options=milestones.map(m=>`<option value="${esc(m.id)}">${esc(m.label)}</option>`).join('');
      return `<section class="panel" data-com-claim-form hidden><h2>Submit a payment claim</h2>
        <label>Claim number <input type="text" maxlength="80" data-com-field="claimNumber" value="PC-001"></label>
        <label>Cumulative claimed (R) <input type="number" min="0" step="0.01" data-com-field="claimAmount" value="0.00"></label>
        <label>Milestone <select data-com-field="milestoneId">${options}</select></label>
        <label>Evidence reference <input type="text" data-com-field="claimEvidence" value="document:site-valuation"></label>
        <label class="com-inline"><input type="checkbox" data-com-field="claimDisputed"> This claim includes an unresolved dispute (release-locked)</label>
        <label>Reason <input type="text" maxlength="1000" data-com-field="claimReason" value="Monthly valuation claim"></label>
        <div class="com-actions"><button class="btn" data-com-create-claim>Submit claim</button><button class="btn" data-com-cancel>Cancel</button></div>
        <p class="muted">Submitting a claim records internal valuation metadata and requests review. It does not request, execute or represent any payment.</p></section>`;
    }
    function escrowPanel(){
      const status=adapter.providerStatus(model);
      const rows=[
        ['Payment / escrow provider configured',status.configured?'Yes':'No — provider not configured'],
        ['Registered provider records (never a live connector)',String(status.registeredProviders)],
        ['Live-configured connectors',String(status.liveProviders)],
        ['Provider credentials configured',status.credentialsConfigured?'Yes':'No'],
        ['Architex holds funds',status.fundsHeldByArchitex?'Yes (must never be true)':'No'],
        ['Architex executes or settles payments',status.livePaymentClaimed?'Yes (must never be true)':'No'],
        ['Automatic release',status.automaticRelease?'Yes (must never be true)':'No'],
        ['Backend release status while unconfigured',status.unconfiguredStatus]
      ];
      return `<section class="panel" data-com-provider-boundary><h2>Third-party provider boundary</h2>
        <p class="muted">${esc(status.message)}</p>
        <table class="table"><thead><tr><th>Provider boundary check</th><th>Reported state</th></tr></thead><tbody>
        ${rows.map(r=>`<tr data-com-boundary-row="${esc(r[0])}"><td>${esc(r[0])}</td><td>${esc(r[1])}</td></tr>`).join('')}</tbody></table>
        <div class="com-actions"><button class="btn" data-com-release disabled title="${esc(adapter.releaseRefused)}">Request provider release</button></div>
        <p class="muted" data-com-release-notice>${esc(adapter.releaseRefused)}</p></section>`;
    }
    function retentionPanel(){
      const retention=model.retention;
      return `<section class="panel" data-com-retention><h2>Retention</h2>
        <p>Held to date <b>${esc(money(retention?.heldToDateCents||0))}</b> ${esc(retention?.currency||model.baseline?.currency||'ZAR')} · internal retention ledger metadata only — no retention is released or paid.</p></section>`;
    }
    function render(){
      if (!ownsRoute(tool)) return false;
      const main=root.document?.querySelector?.('#main');if(!main)return null;
      const s=context.getState();
      const project=(s.projects||[]).find(p=>p.id===s.selectedProjectId)?.name||'Selected project';
      const escrow=tool==='payments_escrow';
      const title=escrow?'Payments & Escrow':'Contract Administration';
      const intro=escrow
        ?'Track payment schedules, claims, certificate revisions and retention as internal commercial metadata for the selected project.'
        :'Administer the commercial baseline, variations, payment claims and certificates for the selected project.';
      main.innerHTML=`<div class="com-workspace" data-com-tool="${esc(tool)}">
        <div class="com-hero"><div><h1>${esc(title)}</h1><p>${esc(intro)}</p>
          <p>Project: <strong>${esc(project)}</strong>${model?` · ${esc(model.variations.length)} variation(s) · ${esc(model.claims.length)} claim(s) · ${esc(model.certificates.length)} certificate(s)`:''}</p></div>
          <div class="com-hero-actions">${!escrow&&!model?.baseline?'<button class="btn" data-com-new-baseline>+ New baseline</button>':''}${model?.baseline&&!escrow?'<button class="btn" data-com-new-variation>+ New variation</button>':''}${model?.baseline?'<button class="btn" data-com-new-schedule>+ Payment schedule</button>':''}${model?.schedules?.some(s=>(s.milestones||[]).length)?'<button class="btn" data-com-new-claim>+ Submit claim</button>':''}<button class="btn" data-com-refresh>Refresh</button></div></div>
        ${errorMessage?`<p class="pill" data-com-error>${esc(errorMessage)}</p>`:''}
        <p class="pill" data-com-status role="status">${esc(adapter.boundary)}</p>
        ${escrow?escrowPanel():''}
        ${baselinePanel()}
        ${model?.baseline?`<section class="panel"><h2>${escrow?'Payment schedules':'Commercial baseline & variations'}</h2>
          <table class="table" data-com-schedules><thead><tr><th>Schedule</th><th>Total</th><th>Milestones</th><th>Milestone detail</th></tr></thead><tbody>${scheduleRows()}</tbody></table>
          <table class="table" data-com-variations><thead><tr><th>Variation</th><th>Title</th><th>Amount</th><th>Time</th><th>Status</th><th>Approvals</th><th>Action</th></tr></thead><tbody>${variationRows()}</tbody></table></section>`:''}
        ${model?.baseline?`<section class="panel"><h2>Payment claims${escrow?' & certificates':''}</h2>
          <table class="table" data-com-claims><thead><tr><th>Claim</th><th>Cumulative claimed</th><th>Status</th><th>Dispute</th><th>Evidence</th>${escrow?'<th>Certificate</th>':''}</tr></thead><tbody>${claimRows(escrow)}</tbody></table>
          ${escrow?`<h2>Certificate revisions</h2><table class="table" data-com-certificates><thead><tr><th>Certificate</th><th>Gross certified</th><th>Retention held</th><th>Deductions</th><th>Net payable (internal)</th><th>Status</th></tr></thead><tbody>${certificateRows()}</tbody></table>`:''}</section>`:''}
        ${model?.baseline?retentionPanel():''}
        ${escrow?'':baselineForm()}${escrow?'':variationForm()}${escrow?scheduleForm():''}${escrow?claimForm():''}
      </div>`;
      bind(main);
      return main;
    }
    function field(name){const el=root.document?.querySelector?.('#main [data-com-field="'+name+'"]');return el?el.value:undefined;}
    function token(name){const el=root.document?.querySelector?.('#main [data-com-field="'+name+'"]');return el&&el.checked===true;}
    function milestones(){
      const out=[];
      const nodes=root.document?.querySelectorAll?.('#main .com-milestone')||[];
      for(const node of nodes){
        const value=kind=>{const el=node.querySelector?.(`[data-com-milestone="${kind}"]`);return el?el.value:'';};
        out.push({label:String(value('label')||'').trim(),amountCents:centsFromRands(value('amount')),dueDate:String(value('dueDate')||'').trim()});
      }
      return out;
    }
    function panel(name){const el=root.document?.querySelector?.('#main [data-com-'+name+']');return el||null;}
    function show(name){const el=panel(name+'-form');if(el)el.hidden=false;}
    function bind(main){
      const createBaseline=main.querySelector('[data-com-create-baseline]');
      if(createBaseline)createBaseline.addEventListener('click',async()=>{
        const sum=centsFromRands(field('contractSum'));
        const input={contractSumCents:sum,contingencyCents:centsFromRands(field('contingency')||0),retentionBasisPoints:Math.round(Number(field('retentionPercent')||0)*100),currency:'ZAR',vatIncluded:token('vatIncluded'),reason:String(field('baselineReason')||'').trim(),humanConfirmed:true};
        if(!root.confirm?.('Create the commercial baseline for the selected project from its formally issued appointment? This records internal commercial-control metadata; it is not a payment or settlement.')){notice('Baseline not created.');return;}
        createBaseline.disabled=true;
        try{const record=await adapter.createBaseline(input);await refresh();notice('Baseline '+String(record.id).slice(0,8)+' verified ('+record.status+', revision '+record.revision+'). Internal record only — no payment is implied.');}
        catch(error){notice(error.message+' No success claimed.');}
        finally{createBaseline.disabled=false;}
      });
      const createVariation=main.querySelector('[data-com-create-variation]');
      if(createVariation)createVariation.addEventListener('click',async()=>{
        const input={reference:String(field('variationReference')||'').trim(),title:String(field('variationTitle')||'').trim(),description:String(field('variationDescription')||'').trim(),amountCents:centsFromRands(field('variationAmount')),timeImpactDays:Math.round(Number(field('variationDays')||0))};
        if(!root.confirm?.('Create this draft variation for the selected project? It is incorporated only after quantity-surveyor, lead-professional and client approvals.')){notice('Variation not created.');return;}
        createVariation.disabled=true;
        try{const record=await adapter.addVariation(input);await refresh();notice('Variation '+record.reference+' verified ('+record.status+', v'+record.version+'). It is not approved.');}
        catch(error){notice(error.message+' No success claimed.');}
        finally{createVariation.disabled=false;}
      });
      const createSchedule=main.querySelector('[data-com-create-schedule]');
      if(createSchedule)createSchedule.addEventListener('click',async()=>{
        const input={title:String(field('scheduleTitle')||'').trim(),reason:String(field('scheduleReason')||'').trim(),milestones:milestones(),humanConfirmed:true};
        if(!root.confirm?.('Issue this payment schedule as internal commercial metadata? Issuing a schedule releases no funds and requests no payment.')){notice('Payment schedule not issued.');return;}
        createSchedule.disabled=true;
        try{const record=await adapter.addPaymentSchedule(input);await refresh();notice('Payment schedule '+String(record.id).slice(0,8)+' verified. No payment or release is implied.');}
        catch(error){notice(error.message+' No success claimed.');}
        finally{createSchedule.disabled=false;}
      });
      const createClaim=main.querySelector('[data-com-create-claim]');
      if(createClaim)createClaim.addEventListener('click',async()=>{
        const input={claimNumber:String(field('claimNumber')||'').trim(),claimedToDateCents:centsFromRands(field('claimAmount')),milestoneId:String(field('milestoneId')||''),evidenceReferences:[String(field('claimEvidence')||'').trim()].filter(Boolean),disputed:token('claimDisputed'),reason:String(field('claimReason')||'').trim(),humanConfirmed:true};
        if(!root.confirm?.('Submit this internal payment claim for review? It records valuation metadata only; it does not request, execute or settle a payment.')){notice('Payment claim not submitted.');return;}
        createClaim.disabled=true;
        try{const record=await adapter.addClaim(input);await refresh();notice('Payment claim '+record.claimNumber+' verified ('+record.status+'). No payment is implied.');}
        catch(error){notice(error.message+' No success claimed.');}
        finally{createClaim.disabled=false;}
      });
      main.querySelectorAll('[data-com-submit-variation]').forEach(button=>button.addEventListener('click',async()=>{
        const id=button.getAttribute('data-com-submit-variation');
        const version=Number(button.getAttribute('data-com-version'));
        const reason=root.prompt?.('Reason for submitting this variation for review:');
        if(!reason?.trim()){notice('A recorded reason is required. Nothing was submitted.');return;}
        if(!root.confirm?.('Submit this variation for governed review? Submission is not approval.')){notice('Variation not submitted.');return;}
        button.disabled=true;
        try{await adapter.submitVariation(id,{expectedVersion:version,reason:reason.trim(),humanConfirmed:true});await refresh();notice('Variation submitted for review and verified. Approvals remain outstanding.');}
        catch(error){notice(error.message+' No success claimed.');}
        finally{button.disabled=false;}
      }));
      main.querySelectorAll('[data-com-approve-variation]').forEach(button=>button.addEventListener('click',async()=>{
        const id=button.getAttribute('data-com-approve-variation');
        const reason=root.prompt?.('Client approval reason for this variation:');
        if(!reason?.trim()){notice('A recorded reason is required. Nothing was submitted.');return;}
        if(!root.confirm?.('Record the CLIENT approval decision for this variation? Three separate approvals are required before incorporation; this is one of them.')){notice('Variation approval cancelled.');return;}
        button.disabled=true;
        try{await adapter.approveVariation(id,{approvalType:'client',decision:'approved',reason:reason.trim(),humanConfirmed:true});await refresh();notice('Client approval recorded and verified. The variation is incorporated only after all three approvals.');}
        catch(error){notice(error.message+' No success claimed.');}
        finally{button.disabled=false;}
      }));
      main.querySelectorAll('[data-com-certify]').forEach(button=>button.addEventListener('click',async()=>{
        const id=button.getAttribute('data-com-certify');
        const gross=root.prompt?.('Gross certified-to-date amount in Rands (must not exceed the cumulative claim):');
        if(gross==null||gross===''){notice('A certified amount is required. Nothing was submitted.');return;}
        const reason=root.prompt?.('Certification reason:');
        if(!reason?.trim()){notice('A recorded certification reason is required. Nothing was submitted.');return;}
        if(!root.confirm?.('Record this payment certificate revision? It records internal certification metadata only — it is not a payment, and no provider release is requested.')){notice('Certificate not issued.');return;}
        button.disabled=true;
        try{const record=await adapter.issueCertificate(id,{grossCertifiedToDateCents:centsFromRands(gross),otherDeductionsCents:0,reason:reason.trim(),humanConfirmed:true});await refresh();notice('Certificate revision '+record.revision+' verified (net payable '+record.netPayableCents+' cents). Internal metadata — no payment or release occurred.');}
        catch(error){notice(error.message+' No success claimed.');}
        finally{button.disabled=false;}
      }));
      main.querySelectorAll('[data-com-release]').forEach(button=>button.addEventListener('click',()=>{
        button.disabled=true;
        notice(adapter.releaseRefused);
      }));
      const refreshButton=main.querySelector('[data-com-refresh]');
      if(refreshButton)refreshButton.addEventListener('click',()=>void refresh());
      [['new-baseline','baseline'],['new-variation','variation'],['new-schedule','schedule'],['new-claim','claim']].forEach(([trigger,target])=>{
        const button=main.querySelector('[data-com-'+trigger+']');
        if(button)button.addEventListener('click',()=>show(target));
      });
      main.querySelectorAll('[data-com-cancel]').forEach(button=>button.addEventListener('click',()=>{const holder=button.closest?.('[data-com-baseline-form],[data-com-variation-form],[data-com-schedule-form],[data-com-claim-form]');if(holder)holder.hidden=true;}));
    }
    async function refresh(){
      if (!ownsRoute(tool)) return false;
      const token=++generation;
      try{
        const data=await adapter.listFinanceWorkspace();
        if(disposed || !ownsRoute(tool) || token !== generation)return false;
        model=data;errorMessage='';render();return true;
      }catch(error){ if (disposed || !ownsRoute(tool) || token !== generation) return false;
        if(disposed || !ownsRoute(tool) || token !== generation)return false;
        errorMessage=error.message+' No commercial records loaded.';model=model||null;notice(errorMessage);render();return false;
      }
    }
    async function apply(toolId){
      if(!isCommercial(toolId))return {applied:false,reason:'not-commercial'};
      tool=toolId;active=true;
      const ok=await refresh();
      return {applied:ok,tool:toolId};
    }
    const unsubscribe=typeof context.subscribe==='function'?context.subscribe(()=>{if(active&&!disposed)void refresh();}):null;
    function dispose(){disposed=true;++generation;active=false;if(unsubscribe)unsubscribe();}
    return {apply,dispose,spec:{tools:TOOLS.slice(),name:'Contract Administration & Payments/Escrow',functional:true,escrowProviderBoundary:true}};
  }
  root.V1CloneCommercial={install};
})(window);
