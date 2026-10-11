(function(root){
  'use strict';
  // rfq_marketplace controller — Procurement packages & RFQ marketplace.
  // v1 shipped this as a read-only scaffold; this replaces it with a real,
  // project-scoped workspace over the verified ProcurementService: package list,
  // create, formal issue, bidder invitations, equal-information addenda and an
  // advisory quote comparison.
  // Honest boundaries rendered on screen: no automatic appointment, no signed
  // contract and no statutory-threshold claim; addenda go equally to every active
  // bidder; quotes keep missing returnables and exclusions visible; the comparison
  // assists human review and never selects or appoints a bidder.
  const escape=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const TOOL='rfq_marketplace';
  const METHODS=['open_tender','invited_tender','rfq','direct_appointment'];
  const COMPLEXITIES=['low','medium','high'];
  const URGENCIES=['normal','urgent','emergency'];
  const BOUNDARY='Internal procurement records only — no automatic appointment, no signed contract and no statutory-threshold claim. Addenda are issued equally to every active bidder, quotes keep their missing returnables and exclusions visible, and the comparison is advisory.';
  function install({context}){
    // Async work may finish after the shared shell has navigated to another tool.
    const ownsRoute = toolId => { const view = typeof state === 'object' ? state : root.state; return !view || view.tool === undefined || view.tool === toolId; };
    if(!root.V1CloneProcurementAdapter||typeof root.V1CloneProcurementAdapter.create!=='function')throw Error('Load adapters/procurement.js before the procurement module.');
    const adapter=root.V1CloneProcurementAdapter.create(context);
    let generation=0,disposed=false,active=false,packages=[],selectedId=null,workspace=null;
    function scope(){const s=context.getState();if(!s.ready||!s.session||!s.organisationId||!/^[a-f0-9]{32}$/i.test(s.selectedProjectId||''))throw Error('Select an authenticated project.');return s;}
    function target(){const doc=root.document;if(!doc)return null;return (typeof doc.querySelector==='function'&&doc.querySelector('#main'))||doc.body||null;}
    function label(v){return String(v==null?'—':v).replace(/_/g,' ');}
    function notice(message){if (!ownsRoute(TOOL)) return;root.showToast?.(message);const el=target()?.querySelector?.('[data-proc-status]');if(el)el.textContent=message;}
    function field(main,name){return main.querySelector('[data-proc-field="'+name+'"]')?.value||'';}
    function csv(value){return String(value||'').split(',').map(item=>item.trim()).filter(Boolean);}

    function methodOptions(current){return METHODS.map(t=>'<option value="'+esc(t)+'"'+(t===current?' selected':'')+'>'+esc(label(t))+'</option>').join('');}
    function esc(v){return escape(v);}
    function shell(state){
      const project=state.projects?.find(p=>p.id===state.selectedProjectId)?.name||'Selected project';
      return '<section class="proc-workspace m3-commercial-workspace" data-proc-root>'+
        '<div class="proc-hero"><h1>Procurement packages &amp; RFQ marketplace</h1>'+
        '<p>Project: <strong>'+esc(project)+'</strong> · <span data-proc-status role="status">Loading packages…</span></p></div>'+
        '<p class="pill" data-proc-boundary role="note">'+esc(BOUNDARY)+'</p>'+
        '<section class="panel"><h2>Packages</h2>'+
          '<button type="button" class="btn" data-proc-create-toggle>+ New procurement package</button>'+
          '<div data-proc-create-panel hidden>'+
            '<div class="proc-form">'+
              '<label>Package number <input type="text" data-proc-field="packageNumber" maxlength="80"></label>'+
              '<label>Title <input type="text" data-proc-field="title" maxlength="220"></label>'+
              '<label>Procurement need <input type="text" data-proc-field="procurementNeed" maxlength="500"></label>'+
              '<label>Procurement method <select data-proc-field="procurementMethod">'+methodOptions('rfq')+'</select></label>'+
              '<label>Method reason <input type="text" data-proc-field="methodReason" maxlength="1000"></label>'+
              '<label>Complexity <select data-proc-field="complexity">'+COMPLEXITIES.map(t=>'<option value="'+esc(t)+'">'+esc(label(t))+'</option>').join('')+'</select></label>'+
              '<label>Urgency <select data-proc-field="urgency">'+URGENCIES.map(t=>'<option value="'+esc(t)+'">'+esc(label(t))+'</option>').join('')+'</select></label>'+
              '<label>Estimated value (ZAR) <input type="number" min="0" step="0.01" data-proc-field="estimatedValueZar"></label>'+
              '<label>Municipal readiness score <input type="number" min="0" max="100" step="1" data-proc-field="municipalReadinessScore"></label>'+
              '<label>Province <input type="text" data-proc-field="province" maxlength="120"></label>'+
              '<label>Location <input type="text" data-proc-field="location" maxlength="220"></label>'+
              '<label>Submission deadline <input type="datetime-local" data-proc-field="deadlineAt"></label>'+
              '<label>Scope summary <textarea data-proc-field="scopeSummary" maxlength="10000"></textarea></label>'+
              '<label>Required trades (comma separated) <input type="text" data-proc-field="requiredTrades"></label>'+
              '<label>Required specialists (comma separated) <input type="text" data-proc-field="requiredSpecialists"></label>'+
              '<label>Required returnables (comma separated) <input type="text" data-proc-field="requiredReturnables"></label>'+
              '<label>Issued-for-tender document revision ids (comma separated) <input type="text" data-proc-field="documentRevisionIds"></label>'+
              '<label>Weight · price <input type="number" min="0" max="100" step="1" data-proc-field="criteriaPrice" value="40"></label>'+
              '<label>Weight · returnables <input type="number" min="0" max="100" step="1" data-proc-field="criteriaReturnables" value="20"></label>'+
              '<label>Weight · lead time <input type="number" min="0" max="100" step="1" data-proc-field="criteriaLeadTime" value="20"></label>'+
              '<label>Weight · risk <input type="number" min="0" max="100" step="1" data-proc-field="criteriaRisk" value="10"></label>'+
              '<label>Weight · conflict <input type="number" min="0" max="100" step="1" data-proc-field="criteriaConflict" value="10"></label>'+
              '<div><button type="button" class="btn" data-proc-create-submit>Save draft package</button>'+
              '<button type="button" class="btn" data-proc-create-cancel>Cancel</button></div>'+
            '</div>'+
          '</div>'+
          '<table class="table"><thead><tr><th>Package</th><th>Title</th><th>Method</th><th>Status</th><th>Deadline</th><th>Value</th><th>Action</th></tr></thead>'+
            '<tbody data-proc-packages><tr><td colspan="7">Loading packages…</td></tr></tbody></table>'+
        '</section>'+
        '<section class="panel" data-proc-workspace hidden>'+
          '<h2 data-proc-workspace-title>Package workspace</h2>'+
          '<div data-proc-invitations><h3>Invitations</h3><p>Select a package and open its workspace.</p></div>'+
          '<div data-proc-invite>'+
            '<label>Marketplace listing id to invite <input type="text" data-proc-field="listingId" maxlength="32"></label>'+
            '<button type="button" class="btn" data-proc-invite-submit>Invite bidder</button>'+
            '<button type="button" class="btn" data-proc-matches>Find marketplace matches</button>'+
          '</div>'+
          '<div data-proc-addenda><h3>Equal-information addenda</h3><p>No clarifications loaded.</p></div>'+
          '<div data-proc-addendum-form>'+
            '<label>Addendum title <input type="text" data-proc-field="addendumTitle" maxlength="220"></label>'+
            '<label>Content <textarea data-proc-field="addendumContent" maxlength="20000"></textarea></label>'+
            '<label>Reason <input type="text" data-proc-field="addendumReason" maxlength="1000"></label>'+
            '<label>Material clarification id answered <input type="text" data-proc-field="addendumClarificationId" maxlength="32"></label>'+
            '<button type="button" class="btn" data-proc-addendum-submit>Issue addendum to every active bidder</button>'+
          '</div>'+
          '<div data-proc-quotes><h3>Quotes</h3><p>No quotes loaded.</p></div>'+
          '<button type="button" class="btn" data-proc-compare>Run advisory comparison</button>'+
          '<div data-proc-comparison role="status"></div>'+
        '</section>'+
      '</section>';
    }

    function packageRow(record){
      const canIssue=record.status==='draft';
      return '<tr data-proc-package="'+esc(record.id)+'">'+
        '<td>'+esc(record.packageNumber||'—')+'</td>'+
        '<td>'+esc(record.title||'—')+'</td>'+
        '<td>'+esc(label(record.procurementMethod))+'</td>'+
        '<td><span class="pill">'+esc(label(record.status))+'</span></td>'+
        '<td>'+esc(record.deadlineAt||'—')+'</td>'+
        '<td>'+esc(record.estimatedValueZar??'—')+'</td>'+
        '<td><button type="button" class="btn" data-proc-open="'+esc(record.id)+'">Open workspace</button>'+
          (canIssue?' <button type="button" class="btn" data-proc-issue="'+esc(record.id)+'">Issue package</button>':'')+
        '</td></tr>';
    }
    function renderPackages(main){if (!ownsRoute(TOOL)) return;
      const body=main.querySelector('[data-proc-packages]');
      if(!body)return;
      body.innerHTML=packages.length?packages.map(packageRow).join(''):'<tr><td colspan="7">No procurement packages recorded for this project.</td></tr>';
    }
    function renderInvitations(main){
      const list=main.querySelector('[data-proc-invitations]');
      if(!list)return;
      const rows=workspace?.invitations||[];
      list.innerHTML='<h3>Invitations</h3>'+(rows.length?'<table class="table"><thead><tr><th>Bidder</th><th>Status</th><th>Delivery</th></tr></thead><tbody>'+
        rows.map(i=>'<tr><td>'+esc(i.listingName||i.listingId||'—')+'</td><td>'+esc(label(i.status))+'</td><td>'+esc(label(i.deliveryStatus))+'</td></tr>').join('')+'</tbody></table>'
        :'<p>No bidders invited. Invitations are recorded in-app only; no email delivery is claimed.</p>');
    }
    function renderAddenda(main){
      const list=main.querySelector('[data-proc-addenda]');
      if(!list)return;
      const rows=workspace?.addenda||[];
      list.innerHTML='<h3>Equal-information addenda</h3>'+(rows.length?'<ul>'+
        rows.map(a=>'<li>#'+esc(a.sequenceNumber)+' '+esc(a.title)+' — distributed to '+esc(a.distributionCount??0)+' active bidder(s)'+(a.equalInformationCompliant===true?' (equal information)':'')+'</li>').join('')+'</ul>'
        :'<p>No addenda issued yet. Material clarifications must be answered by an addendum to every active bidder.</p>');
    }
    function renderQuotes(main){
      const list=main.querySelector('[data-proc-quotes]');
      if(!list)return;
      const rows=workspace?.quotes||[];
      list.innerHTML='<h3>Quotes</h3>'+(rows.length?'<table class="table"><thead><tr><th>Bidder</th><th>Price (ZAR)</th><th>Lead (weeks)</th><th>Missing returnables</th><th>Exclusions</th><th>Conflict</th><th>Late</th></tr></thead><tbody>'+
        rows.map(q=>'<tr><td>'+esc(q.listingName||'—')+'</td><td>'+esc(q.priceZar)+'</td><td>'+esc(q.leadTimeWeeks)+'</td><td>'+esc((q.missingReturnables||[]).join(', ')||'none')+'</td><td>'+esc((q.exclusions||[]).join('; ')||'none')+'</td><td>'+esc(q.conflictDeclared===true?'declared':'none')+'</td><td>'+esc(q.lateSubmission===true?'late':'on time')+'</td></tr>').join('')+'</tbody></table>'
        :'<p>No bidder quotes submitted yet.</p>');
    }
    function renderWorkspace(main){if (!ownsRoute(TOOL)) return;
      const section=main.querySelector('[data-proc-workspace]');
      if(!section)return;
      section.hidden=!workspace;
      if(!workspace)return;
      const title=main.querySelector('[data-proc-workspace-title]');
      if(title)title.textContent='Package '+(workspace.package?.packageNumber||'')+' · '+label(workspace.package?.status);
      renderInvitations(main);renderAddenda(main);renderQuotes(main);
    }
    function bind(main){
      const panel=main.querySelector('[data-proc-create-panel]');
      const toggle=main.querySelector('[data-proc-create-toggle]');
      if(toggle&&panel)toggle.addEventListener('click',()=>{panel.hidden=!panel.hidden;});
      const cancel=main.querySelector('[data-proc-create-cancel]');
      if(cancel&&panel)cancel.addEventListener('click',()=>{panel.hidden=true;});
      const submit=main.querySelector('[data-proc-create-submit]');
      if(submit)submit.addEventListener('click',()=>createPackage(main,submit));
      main.querySelectorAll('[data-proc-open],[data-proc-issue],[data-proc-invite-submit],[data-proc-addendum-submit],[data-proc-compare],[data-proc-matches]').forEach(button=>{
        button.addEventListener('click',()=>{
          if(button.matches('[data-proc-open]'))openWorkspace(main,button.getAttribute('data-proc-open'));
          else if(button.matches('[data-proc-issue]'))issuePackage(main,button);
          else if(button.matches('[data-proc-invite-submit]'))inviteBidder(main,button);
          else if(button.matches('[data-proc-addendum-submit]'))issueAddendum(main,button);
          else if(button.matches('[data-proc-compare]'))runComparison(main,button);
          else if(button.matches('[data-proc-matches]'))runMatches(main,button);
        });
      });
    }
    async function createPackage(main,button){
      try{
        const deadlineRaw=field(main,'deadlineAt');
        const deadline=deadlineRaw&&!Number.isNaN(Date.parse(deadlineRaw))?new Date(deadlineRaw).toISOString():deadlineRaw;
        const input={humanConfirmed:false,packageNumber:field(main,'packageNumber'),title:field(main,'title'),
          procurementNeed:field(main,'procurementNeed'),scopeSummary:field(main,'scopeSummary'),methodReason:field(main,'methodReason'),
          procurementMethod:field(main,'procurementMethod'),complexity:field(main,'complexity'),urgency:field(main,'urgency'),
          province:field(main,'province'),location:field(main,'location'),estimatedValueZar:Number(field(main,'estimatedValueZar')),
          municipalReadinessScore:Number(field(main,'municipalReadinessScore')),deadlineAt:deadline,
          requiredTrades:csv(field(main,'requiredTrades')),requiredSpecialists:csv(field(main,'requiredSpecialists')),
          requiredReturnables:csv(field(main,'requiredReturnables')),documentRevisionIds:csv(field(main,'documentRevisionIds')),
          evaluationCriteria:{price:Number(field(main,'criteriaPrice')),returnables:Number(field(main,'criteriaReturnables')),
            lead_time:Number(field(main,'criteriaLeadTime')),risk:Number(field(main,'criteriaRisk')),conflict:Number(field(main,'criteriaConflict'))}};
        if(!input.title.trim()){notice('Enter a package title. Nothing was saved.');return;}
        if(!root.confirm?.('Create this procurement package as a draft on the selected project?'))return;
        input.humanConfirmed=true;button.disabled=true;notice('Creating package…');
        await adapter.createPackage(input);
        await refresh();
        notice('Package created and verified in the project register.');
      }catch(error){notice(error.message+' No success claimed.');}
      finally{button.disabled=false;}
    }
    async function openWorkspace(main,packageId){
      if(!packageId)return;
      try{workspace=await adapter.workspace(packageId);selectedId=packageId;renderWorkspace(main);notice('Package workspace loaded.');}
      catch(error){workspace=null;renderWorkspace(main);notice(error.message+' No workspace loaded.');}
    }
    async function issuePackage(main,button){
      const packageId=button.getAttribute('data-proc-issue');
      const record=packages.find(p=>p.id===packageId);
      if(!record)return;
      const reason=root.prompt?.('Reason to formally issue this package:');if(!reason||!reason.trim())return;
      if(!root.confirm?.('Issue this exact package version? Fairness rules require material clarifications to go to every active bidder.'))return;
      button.disabled=true;notice('Issuing package…');
      try{await adapter.issuePackage(packageId,{expectedVersion:record.version,reason:reason.trim(),humanConfirmed:true});await refresh();notice('Package issued and read back from the server.');}
      catch(error){notice(error.message+' No success claimed.');}
      finally{button.disabled=false;}
    }
    async function inviteBidder(main,button){
      if(!selectedId){notice('Open a package workspace before inviting a bidder.');return;}
      const listingId=field(main,'listingId').trim();
      if(!listingId){notice('Enter a real marketplace listing id.');return;}
      if(!root.confirm?.('Invite this marketplace listing to the selected package?'))return;
      button.disabled=true;notice('Recording invitation…');
      try{await adapter.invite(selectedId,{listingId,humanConfirmed:true});workspace=await adapter.workspace(selectedId);renderWorkspace(main);notice('Invitation recorded in-app and verified.');}
      catch(error){notice(error.message+' No success claimed.');}
      finally{button.disabled=false;}
    }
    async function issueAddendum(main,button){
      if(!selectedId){notice('Open a package workspace before issuing an addendum.');return;}
      const clarificationId=field(main,'addendumClarificationId').trim();
      if(!clarificationId){notice('An addendum must answer a material clarification id.');return;}
      if(!root.confirm?.('Issue this addendum equally to every active bidder?'))return;
      button.disabled=true;notice('Issuing addendum…');
      try{
        await adapter.addenda(selectedId,{title:field(main,'addendumTitle'),content:field(main,'addendumContent'),reason:field(main,'addendumReason'),clarificationIds:[clarificationId],humanConfirmed:true});
        workspace=await adapter.workspace(selectedId);renderWorkspace(main);notice('Addendum issued to every active bidder and verified.');
      }catch(error){notice(error.message+' No success claimed.');}
      finally{button.disabled=false;}
    }
    async function runComparison(main,button){
      if(!selectedId){notice('Open a package workspace before comparing quotes.');return;}
      button.disabled=true;notice('Building advisory comparison…');
      try{
        const result=await adapter.comparisons(selectedId);
        const box=main.querySelector('[data-proc-comparison]');
        if(box)box.innerHTML='<p>Comparison '+esc(result.comparison.id.slice(0,8))+' · '+esc(label(result.comparison.advisoryOnly?'advisory_only':result.comparison.status))+'</p>'+
          '<table class="table"><thead><tr><th>Bidder</th><th>Score</th><th>Price</th><th>Missing</th><th>Exclusions</th></tr></thead><tbody>'+
          (result.quotes||[]).map(q=>'<tr><td>'+esc(q.listingName||'—')+'</td><td>'+esc(q.normalizedScore??'—')+'</td><td>'+esc(q.priceZar)+'</td><td>'+esc((q.missingReturnables||[]).join(', ')||'none')+'</td><td>'+esc((q.exclusions||[]).join('; ')||'none')+'</td></tr>').join('')+'</tbody></table>'+
          '<p>Advisory only — this comparison preserves submitted facts and does not select or appoint a bidder.</p>';
        notice('Comparison snapshot recorded and verified.');
      }catch(error){notice(error.message+' No comparison claimed.');}
      finally{button.disabled=false;}
    }
    async function runMatches(main,button){
      if(!selectedId){notice('Open a package workspace before matching.');return;}
      const packageId=selectedId;button.disabled=true;notice('Matching marketplace…');
      try{
        const matches=await adapter.matches(packageId);
        const list=main.querySelector('[data-proc-invitations]');
        if(list&&matches.length)list.insertAdjacentHTML('afterbegin','<p>'+matches.length+' advisory marketplace match(es) suggested — no bidder is appointed. Top: '+esc(matches[0]?.listing?.name||'—')+' (score '+esc(matches[0]?.score)+').</p>');
        notice(matches.length?matches.length+' advisory match(es) found. Nothing appointed.':'No marketplace matches. Nothing appointed.');
      }catch(error){notice(error.message+' No matches claimed.');}
      finally{button.disabled=false;}
    }
    async function refresh(token){
      if (!ownsRoute(TOOL)) return false;
      const own=token===undefined?++generation:token;
      try{
        const loadedPackages=await adapter.listPackages();
        if(disposed || !ownsRoute(TOOL) || own !== generation)return false;
        packages=loadedPackages;
        const main=target();if(!main)return false;
        renderPackages(main);renderWorkspace(main);return true;
      }catch(error){ if (disposed || !ownsRoute(TOOL) || own !== generation) return false;notice(error.message+' No packages loaded.');return false;}
    }
    function requiresRfq(toolId){return toolId===TOOL;}
    async function apply(toolId){
      if(!requiresRfq(toolId))return {applied:false,reason:'not-rfq'};
      const main=target();if(!main)return {applied:false,reason:'no-render-target'};
      const token=++generation;active=true;selectedId=null;workspace=null;
      main.innerHTML=shell(context.getState());bind(main);
      try{
        const ok=await refresh(token);
        if(token!==generation)return {applied:false,reason:'stale'};
        notice(ok?(packages.length+' procurement package(s) loaded.'):'No packages loaded.');
        return {applied:true,tool:TOOL,packages:packages.length};
      }catch(error){
        if(token!==generation)return {applied:false,reason:'stale'};
        notice(error.message+' No packages loaded.');
        return {applied:true,tool:TOOL,packages:0,error:error.message};
      }
    }
    const unsubscribe=typeof context.subscribe==='function'?context.subscribe(()=>{if(active&&!disposed)void refresh();}):null;
    function dispose(){disposed=true;++generation;active=false;packages=[];selectedId=null;workspace=null;if(unsubscribe)unsubscribe();}
    return {apply,dispose,spec:{tool:TOOL,name:'Procurement packages & RFQ marketplace',functional:true}};
  }
  root.V1CloneProcurement={install};
})(window);
