(function(root){
  'use strict';
  const ids=['forms','specforge','bom'];
  // This function is serialized into the original document, not run in the parent.
  function frameBootstrap(id){
    const w=window,d=document;
    if(w.__v1DocumentFrame)return;
    let data={},tab='library',draft=null,template=null,values=null,renderForms=null,renderBoq=null;
    function captureForm(){if(id!=='forms')return;if(tab==='editor'&&template){const fields=Array.from(d.querySelectorAll('[data-field-key]')).filter(e=>e.dataset.fieldKey);if(fields.length)values=Object.fromEntries(fields.map(e=>[e.dataset.fieldKey,e.value]));}w.__v1RememberForm?.({tab,draft,template,values:values&&{...values}});}
    const q=s=>d.querySelector(s),qa=s=>Array.from(d.querySelectorAll(s));
    const text=(s,v)=>qa(s).forEach(e=>e.textContent=String(v??''));
    function notice(message){d.documentElement.dataset.documentStatus=message;const el=q('#toast,.hero .sub,.ai-body,.topbar-right');if(el){el.textContent=message;el.classList?.add('show');}}
    const unavailable=()=>notice('Unavailable: no configured service for this action. Nothing was saved, signed, sent or exported.');
    function specCanWrite(){return data.readOnly!==true&&data.role!=='viewer'&&!data.error&&!!data.workspace;}
    function run(action,payload){if(id==='specforge'&&action!=='selectWorkspace'&&!specCanWrite()){notice('Read-only: specification changes are unavailable. Nothing was saved or issued.');return Promise.resolve();}if(!w.__v1DocumentCommand){notice('Loading authenticated project context.');return Promise.resolve();}notice('Saving — awaiting server and readback.');return Promise.resolve(w.__v1DocumentCommand(action,payload)).catch(e=>notice(e.message));}
    function blocked(el,reason){el.removeAttribute('onclick');el.onclick=()=>notice(reason||'Unavailable: no configured service for this action.');el.setAttribute('aria-disabled','true');el.title=reason||'Unavailable: no configured service for this action.';}
    function scrubForms(){
      text('.topbar-right','Authenticated project context');
      text('.hero .sub',data.projectName||'Select an authenticated project');
      text('.stat-value,.tn-badge','—');
      text('.collab-avatar','—');text('.collab-text','Live collaboration unavailable');
      qa('.collab-bar > span:last-child').forEach(e=>e.textContent='Not connected');
      text('.field-badge','Not mapped');
      qa('.pill').forEach(e=>{if(/\d|signed|auto|pending|export/i.test(e.textContent))e.textContent='Not verified';});
      qa('.audit-item').forEach(e=>{e.querySelectorAll('.audit-text,.audit-time').forEach(n=>n.textContent='No audit events available');});
      if(tab==='export'){const walker=d.createTreeWalker(q('#main-content'),NodeFilter.SHOW_TEXT);let node;while((node=walker.nextNode())){if(/Riverside|Joshil|Millimetre|PrArch|\d|Signed|signed|Generated|generated|Verified|verified|Ready|ready/.test(node.textContent))node.textContent='Not available';}}
      if(tab==='library'){
        const grid=q('.template-grid'),prototype=grid?.firstElementChild?.cloneNode(true);if(grid){grid.replaceChildren();for(const t of data.templates||[]){const card=prototype.cloneNode(true);card.querySelector('.tc-name').textContent=t.name;card.querySelector('.tc-meta').textContent=t.category+' · '+t.version;card.removeAttribute('onclick');card.onclick=()=>{captureForm();if(template?.id!==t.id||draft){template=t;draft=null;values=null;}renderForms('editor');captureForm();};grid.append(card);}}
        const statValues=qa('.stat-value');if(statValues[0])statValues[0].textContent=(data.templates||[]).length;if(statValues[2])statValues[2].textContent=(data.drafts||[]).filter(x=>x.status==='draft').length;
        qa('.tabs .tab').forEach(b=>b.onclick=()=>{qa('.tabs .tab').forEach(x=>x.classList.toggle('active',x===b));const category=b.textContent.toLowerCase();qa('.template-card').forEach((c,i)=>c.hidden=!/recommended|all/.test(category)&&!(data.templates[i]?.category||'').includes(category.replace(/s$/,'')));});
        const search=q('.search-bar input');if(search)search.oninput=()=>qa('.template-card').forEach(c=>c.hidden=!c.textContent.toLowerCase().includes(search.value.toLowerCase()));
      }
      if(tab==='drafts'){
        const rows=qa('.draft-row'),proto=rows[0]?.cloneNode(true),parent=rows[0]?.parentElement;rows.forEach(e=>e.remove());if(proto&&parent)for(const r of data.drafts||[]){const e=proto.cloneNode(true);e.querySelector('.dr-title').textContent=r.title;e.querySelector('.dr-meta').textContent='Revision '+r.revision;e.querySelector('.dr-status').textContent=r.status;e.removeAttribute('onclick');e.onclick=()=>{captureForm();if(draft?.id!==r.id){draft=r;template=r.template;values={...r.values};}renderForms('editor');captureForm();};parent.append(e);}
      }
      if(tab==='editor'){
        const controls=qa('.field-input');controls.forEach((e,i)=>{e.value='';e.removeAttribute('value');e.readOnly=false;e.dataset.fieldKey='';const field=template?.fields?.[i];const label=e.closest('.field-group')?.querySelector('.field-label');if(field){e.dataset.fieldKey=field.key;if(label)label.textContent=field.label;e.required=field.required===true;e.value=values?.[field.key]??draft?.values?.[field.key]??'';e.title='Verified template field: '+field.key;}else{e.title='Original control retained; no corresponding field in selected template. Not submitted.';}});
        text('.eyebrow',template?.name||'Select a verified template');text('h1',draft?.title||template?.name||'Form Editor');
        qa('.content > div:first-child p').forEach(e=>e.textContent=template?'Template '+template.version+' · '+(draft?.status||'Unsaved'):'No template selected');
        qa('.two-col > div:last-child .panel > div').forEach(e=>{if(!e.querySelector('button')){e.textContent='Not verified — signature and live auto-fill providers unavailable';}});
        qa('.form-section-header .pill').forEach(e=>e.textContent='Verify fields');
        qa('button').forEach(b=>{if(/Save Draft/.test(b.textContent)){b.removeAttribute('onclick');b.onclick=()=>{const values={};controls.forEach(e=>{if(e.dataset.fieldKey)values[e.dataset.fieldKey]=e.value;});run('saveForm',{draft,template,values});};}else if(/Export PDF|Preview|Municipal/.test(b.textContent))blocked(b,'PDF, preview integration and municipal submission are unavailable. Issued forms expose HTML/JSON records, not PDF.');});
        qa('.tabs .tab').forEach((b,i)=>b.onclick=()=>{qa('.tabs .tab').forEach(x=>x.classList.toggle('active',x===b));qa('.two-col > div:first-child > .panel').forEach((p,j)=>p.hidden=i!==0&&j!==i-1);});
      }
      if(tab==='export'){
        text('.hero .eyebrow','ISSUED FORM EXPORTS');text('.hero .sub','HTML and JSON server artifacts only. No PDF or digital signature provider configured.');text('.panel:first-of-type .form-section-title',draft?.title||'Select an issued draft');
        text('.sig-pad','Digital signature unavailable');qa('.sig-pad').forEach(e=>e.classList.remove('signed'));
        qa('.panel').forEach(e=>{const walker=d.createTreeWalker(e,NodeFilter.SHOW_TEXT);let n;while((n=walker.nextNode()))if(/Validation passed|signature applied|Credential|embedded|PDF will/.test(n.textContent))n.textContent='Not verified; no signature or external registration claimed.';});
        qa('button').forEach((b,i)=>{b.textContent=i===0?'Download HTML':'Download JSON';b.removeAttribute('onclick');b.onclick=()=>run('exportForm',{id:draft?.id,format:i===0?'html':'json'});});
      }
      if(tab==='audit')notice('No form audit-event listing route. Audit hashes and reasons remain on the selected server record.');
    }
    function specPost(){
      for(const selector of ['#bomContent','#pipelineContent','#recipientGrid','#issueActionsPreview','#allDrawingSuggestions','#kanbanBoard','#closeoutStatus','#integrationGrid']){const el=q(selector);if(!el)continue;const walker=d.createTreeWalker(el,NodeFilter.SHOW_TEXT);let n;while((n=walker.nextNode())){if(n.textContent.trim()&&!n.parentElement.closest('button,thead,.integration-card-title,.panel-title,.ai-label'))n.textContent='Unavailable';}el.querySelectorAll('button').forEach(b=>blocked(b));}
      text('#summaryStatus',data.workspace?.status||'No workspace');
      if(!data.workspace)text('#readinessFindings','Select a real specification workspace; no operational records loaded.');
      text('#navRoleBadge',data.role||'Unverified role');
      qa('[onclick]').filter(e=>/showToast\(/.test(e.getAttribute('onclick'))).forEach(e=>blocked(e));
      const readOnly=data.readOnly===true||data.role==='viewer'||!!data.error||!data.workspace;
      qa('[onclick="openAddItemForm()"],[onclick="openAddSectionForm()"],[onclick^="approveItem("],[onclick^="rejectItem("],#modalContent button[type="submit"]').forEach(el=>{el.setAttribute('aria-disabled',String(readOnly));if('disabled' in el)el.disabled=readOnly;});
      const issue=q('#issueNowBtn');if(issue){issue.disabled=readOnly||!data.workspace?.riskSummary?.readyToIssue;issue.setAttribute('aria-disabled',String(issue.disabled));}
      const selector=q('#projectSelector');if(selector){selector.replaceChildren();for(const ws of data.workspaces||[]){const o=d.createElement('option');o.value=ws.id;o.textContent=ws.title;selector.append(o);}selector.value=data.workspace?.id||'';selector.removeAttribute('onchange');selector.onchange=()=>run('selectWorkspace',{id:selector.value});}
      qa('select[onchange*="changeRole"]').forEach(e=>{e.removeAttribute('onchange');e.disabled=true;e.title='Role is supplied by authenticated project context, not this selector.';});
    }
    if(id==='forms'){
      for(const name of ['renderLibrary','renderEditor','renderDrafts','renderExport','renderAudit']){const original=w[name];w[name]=function(){original();scrubForms();};}
      const original=w.showTab;renderForms=t=>{tab=t;original(t);};w.showTab=function(t){captureForm();renderForms(t);captureForm();};
      d.addEventListener('input',e=>{if(e.target.matches('.field-input'))captureForm();});
      const nav=qa('.tool-nav-item');if(nav[0])nav[0].onclick=()=>w.showTab('library');
      for(const i of [3,7,8,9])if(nav[i])blocked(nav[i]);
      if(nav[5])nav[5].onclick=()=>run('transitionForm',{id:draft?.id});
    }
    if(id==='specforge'){
      d.addEventListener('click',event=>{if(event.target.closest('[aria-disabled="true"]')){event.preventDefault();event.stopImmediatePropagation();}},true);
      // DOMContentLoaded can resume mount between listeners, before the original
      // init listener builds its dynamic overview targets. Wait for that shell,
      // not parser completion alone; retain strict renderer target checks.
      let shellReady;
      w.__v1SpecShellReady=new Promise(resolve=>{shellReady=resolve;});
      const originalShell=w.buildShell;
      w.buildShell=function(){const result=originalShell();shellReady();return result;};
      const originalBudget=w.budgetSummary;w.budgetSummary=()=>{const b=originalBudget();if(!Number.isFinite(Number(b.deltaPct)))b.deltaPct='—';return b;};
      w.showToast=unavailable;
      const originalModal=w.openModal;w.openModal=function(html){originalModal(html);specPost();if(!specCanWrite())notice('Read-only: specification changes are unavailable. Nothing was saved or issued.');};
      const originalAll=w.renderAll,originalView=w.renderCurrentView;
      w.renderAll=function(){originalAll();specPost();};w.renderCurrentView=function(v){originalView(v);specPost();};
      ['submitNewProject','switchProject','handleProjectChange','changeRole','handleSmartSearch','showUrlAnalysis','useAiResult','useUrlResult','simulateAiScan','simulateAiScanInline','addAllDrawingSuggestions','addAllDrawingsSuggestions','movePipelineItem','createIssueSnapshot','executeIssue'].forEach(n=>w[n]=unavailable);
      w.openNewProjectModal=unavailable;
      w.submitAddSection=e=>{e.preventDefault();const get=k=>q('#'+k)?.value||'';return run('section',{code:get('secCode'),title:get('secTitle'),discipline:get('secDisc'),ownerRole:get('secOwner'),reviewerRole:get('secReviewer'),approverRole:get('secApprover')});};
      w.submitAddItem=e=>{e.preventDefault();const get=k=>q('#'+k)?.value||'',p={};for(const [key,field] of Object.entries({sectionId:'aiSection',code:'aiCode',title:'aiTitle',room:'aiRoom',package:'aiPkg',supplier:'aiSupplier',model:'aiModel',finish:'aiFinish',dimensions:'aiDims',ownerRole:'aiOwner',reviewerRole:'aiReviewer',approverRole:'aiApprover',sustainability:'aiSustain',warranty:'aiWarranty',notes:'aiNotes'}))p[key]=get(field);p.budgetAllowanceCents=Math.round(Number(get('aiBudget'))*100);p.estimatedCostCents=Math.round(Number(get('aiCost'))*100);p.leadTimeDays=Number(get('aiLead'));p.requiresClientDecision=!!q('#aiClientDec')?.checked;p.drawingReferences=get('aiDrawings').split(',').map(x=>x.trim()).filter(Boolean);p.clauseReferences=get('aiClauses').split(',').map(x=>x.trim()).filter(Boolean);return run('item',p);};
      w.approveItem=key=>run('decision',{id:key,action:'approve'});w.rejectItem=key=>run('decision',{id:key,action:'reject'});w.issueNow=()=>run('issue',{});
    }
    if(id==='bom'){
      const clean=()=>{
        qa('tbody').forEach(e=>e.replaceChildren());text('.stat-value,.nav-count,.count,.badge,.chip','—');
        const walker=d.createTreeWalker(d.body,NodeFilter.SHOW_TEXT);let node;while((node=walker.nextNode())){if(node.parentElement.closest('script,style,button,thead,.nav-label'))continue;const value=node.textContent;if(/\d|Sarah Mokoena|Greg Tarb|BuildCo|Magwa Group|Stefanutti|WBHO|Swartland|Fairway|Faerie|Certified|Approved|In range|All items have|blockers remain|unresolved|Assigned:|Sign-Off Actions|Today|Yesterday/.test(value)&&!/^\s*SANS\b/.test(value))node.textContent='Not available';}
        qa('#projectDropdown [onclick]').forEach(e=>blocked(e,'Select project in parent workspace.'));
        text('#activeProjectName','Select project in parent workspace');
        qa('.project-dropdown-item').forEach(e=>blocked(e,'Select project in parent workspace.'));
        qa('button').forEach(e=>{if(!e.closest('nav,.tool-nav')&&!e.getAttribute('onclick')?.includes('switchView'))blocked(e,'QS certification, tender generation and export routes are unavailable. Use the advisory BoQ form to save explicit line items.');});
        text('.ai-body','BoQ takeoffs are advisory Toolbox records. Enter measured quantities and rates explicitly. QS certification and tender generation are unavailable. Saved advisory runs support verified JSON, CSV, HTML and PDF artifacts.');
        const audit=q('#view-audit .panel > div:last-child');if(audit)audit.textContent='No BoM audit records available.';
        qa('#view-agents .panel > div').forEach(e=>{if(!e.classList.contains('panel-title'))e.textContent='No connected BoM agents or verified activity.';});
        const dz=q('#dropZone');if(dz){dz.addEventListener('click',e=>{e.stopImmediatePropagation();e.preventDefault();unavailable();},true);dz.addEventListener('drop',e=>{e.stopImmediatePropagation();e.preventDefault();unavailable();},true);}
      };
      w.selectProject=unavailable;clean();
      const panel=d.createElement('section');panel.className='panel';
      panel.innerHTML=`<h2 class="panel-title">Advisory BoQ takeoff</h2><p>No automatic quantities or rates. Not QS certification or a tender. Saved runs expose checksum-verified JSON, CSV, HTML and PDF advisory artifacts.</p>
        <form data-boq-form><label>Title <input class="form-input" name="title" required></label>
        <label>Currency <input class="form-input" name="currency" required></label>
        <label>Contingency % <input class="form-input" name="contingencyPercent" type="number" min="0" max="100" step="any" required></label>
        <div data-boq-lines></div><button class="btn btn-secondary" type="button" data-boq-add>Add line</button>
        <button class="btn btn-primary" type="submit">Save advisory BoQ</button></form>
        <h3 class="panel-title">Saved advisory takeoffs</h3><div data-boq-history></div>`;
      (q('#view-takeoff')||q('.main-content')).prepend(panel);
      const form=q('[data-boq-form]'),lines=q('[data-boq-lines]');
      function addLine(){const row=d.createElement('fieldset');row.dataset.boqLine='';row.innerHTML=`<legend>Measured line</legend><label>Description <input class="form-input" name="description" required></label><label>Quantity <input class="form-input" name="quantity" type="number" min="0.00000001" step="any" required></label><label>Unit <input class="form-input" name="unit" required></label><label>Rate <input class="form-input" name="rate" type="number" min="0" step="any" required></label><button class="btn btn-secondary" type="button">Remove line</button>`;row.querySelector('button').onclick=()=>row.remove();lines.append(row);}
      q('[data-boq-add]').onclick=addLine;addLine();
      form.onsubmit=async event=>{event.preventDefault();if(!form.reportValidity()||form.dataset.saving==='true')return;if(!w.confirm('Save these entered lines as an advisory BoQ for the selected project? No certification or issue.'))return;
        const field=name=>form.elements.namedItem(name).value;
        const payload={title:field('title'),currency:field('currency'),contingencyPercent:field('contingencyPercent'),items:qa('[data-boq-line]').map(row=>Object.fromEntries(Array.from(row.querySelectorAll('input')).map(e=>[e.name,e.value])))};
        form.dataset.saving='true';renderBoq();try{await run('saveBoq',payload);}finally{delete form.dataset.saving;renderBoq();}
      };
      renderBoq=()=>{
        form.querySelectorAll('input,button').forEach(e=>e.disabled=!!data.error||data.readOnly===true||form.dataset.saving==='true');
        const history=q('[data-boq-history]');history.replaceChildren();
        for(const record of data.boq||[]){const item=d.createElement('article');item.className='panel';item.dataset.recordId=record.id;const heading=d.createElement('h4');heading.textContent=(record.input?.title||record.id)+' — '+(record.status||'Status unavailable');item.append(heading);
          const table=d.createElement('table');table.className='table';const body=d.createElement('tbody');for(const line of record.input?.items||[]){const row=d.createElement('tr');for(const value of [line.description,line.quantity,line.unit,line.rate,record.input?.currency]){const cell=d.createElement('td');cell.textContent=String(value??'—');row.append(cell);}body.append(row);}table.append(body);item.append(table);
          for(const format of ['json','csv','html','pdf']){const button=d.createElement('button');button.type='button';button.className='btn btn-secondary';button.dataset.boqExport=format;button.textContent='Download '+format.toUpperCase();button.onclick=()=>run('exportBoq',{id:record.id,format});item.append(button);}
          history.append(item);}
        if(!history.children.length)history.textContent=data.error||'No saved advisory BoQ records returned.';
      };
    }
    w.__v1DocumentFrame={
      apply(next){data=next;d.documentElement.dataset.documentState=next.error?'error':'ready';if(id==='specforge'){
        const ws=next.workspace||{};Object.assign(SAMPLE_WORKSPACE,{id:ws.id||'',projectId:ws.projectId||'',projectName:next.projectName||'',municipality:next.municipality||'',stage:next.stage||'',revision:ws.revisionCode||'',issueStatus:ws.status||'unavailable',team:[],sections:ws.sections||[],items:(ws.items||[]).map(i=>({...i,budgetAllowance:i.budgetAllowanceCents/100,estimatedCost:i.estimatedCostCents/100,clientDecision:i.requiresClientDecision,drawingRefs:i.drawingReferences||[],clauseRefs:i.clauseReferences||[],comments:[],image:'',sourceRevision:ws.revisionCode,supersededBy:null}))});currentRole=next.role||'';w.renderAll();
      }else if(id==='forms'){captureForm();if(!next.error){if(draft)draft=(data.drafts||[]).find(x=>x.id===draft.id)||draft;if(template)template=(data.templates||[]).find(x=>x.id===template.id)||template;}renderForms(tab);}else if(id==='bom'){renderBoq();}if(next.error)notice(next.error);},
      notice,
      restoreForm(snapshot){draft=snapshot?.draft||null;template=snapshot?.template||null;values=snapshot?.values?{...snapshot.values}:null;renderForms(snapshot?.tab||'library');},
      snapshotForm(){captureForm();return {tab,draft,template,values:values&&{...values}};},
      completeSave(submitted,saved){
        data.drafts=[...(data.drafts||[]).filter(r=>r.id!==saved.id),saved];
        captureForm();
        if(template?.id!==submitted.template?.id||draft?.id!==submitted.draft?.id){notice('Submitted draft saved and read back. Current editor selection was retained.');return;}
        const newer=Object.keys({...submitted.values,...values}).some(k=>values?.[k]!==submitted.values[k]);
        draft=saved;template=saved.template;if(!newer)values={...saved.values};renderForms(tab);captureForm();
        notice(newer?'Submitted draft saved and read back; newer edits remain unsaved.':'Draft saved and read back. Not issued or digitally signed.');
      },
      selectDraft(record){draft=record;template=record.template;values={...record.values};renderForms('editor');captureForm();}
    };
  }
  // Literal initializer scanner: preserves every byte outside targeted JS data.
  function replaceLiteral(html,name,value){
    const match=new RegExp('const '+name+'\\s*=\\s*').exec(html);if(!match)throw Error('Original initializer missing: '+name);const start=match.index+match[0].length;let depth=0,quote='',escaped=false,end=start;
    for(;end<html.length;end++){const c=html[end];if(quote){if(escaped)escaped=false;else if(c==='\\')escaped=true;else if(c===quote)quote='';continue;}if(c==='"'||c==="'"||c==='`'){quote=c;continue;}if(c==='['||c==='{')depth++;if(c===']'||c==='}')depth--;if(depth===0&&c===';')break;}
    if(end===html.length)throw Error('Unterminated original initializer: '+name);return html.slice(0,start)+JSON.stringify(value)+html.slice(end);
  }
  function install({context}){
    if(!root.V1CloneDocumentToolsAdapter)throw Error('Load adapters/document-tools.js before the document module.');
    const adapter=root.V1CloneDocumentToolsAdapter.create(context),frames=new Map(),formDrafts=new Map(),pendingMounts=new Map();let lifecycle=0;
    function supports(id){return ids.includes(id);}
    function prepareSource(id,html){if(!supports(id))return html;
      const boot='<script>window.__v1DocumentBoot='+frameBootstrap.toString()+';<\/script>';
      html=html.replace('<head>','<head>'+boot);
      if(id==='specforge'){
        const blank={id:'',projectId:'',projectName:'',municipality:'',stage:'',profile:'',revision:'',issueStatus:'unavailable',team:[],sections:[],items:[]};
        for(const [name,value] of Object.entries({SAMPLE_WORKSPACE:blank,PROJECTS:[],LIBRARY_PERSONAL:[],LIBRARY_PRACTICE:[],LIBRARY_PLATFORM:[],AI_SEARCH_RESULTS:{},PROJECT_DRAWINGS:[],DRAWING_AI_SUGGESTIONS:{},ISSUE_RECIPIENTS:[],PROCUREMENT_PIPELINE:{},bomData:[]}))html=replaceLiteral(html,name,value);
        html=html.replace('// ===== INIT =====','window.__v1DocumentBoot("specforge");\n// ===== INIT =====');
      }else if(id==='forms')html=html.replace('// Load library by default\nrenderLibrary();','// Load library by default\nwindow.__v1DocumentBoot("forms");\nrenderLibrary();');
      else html=html.replace('</body>','<script>window.__v1DocumentBoot("bom");<\/script></body>');
      return html;
    }
    async function mountFrame(id,frame){if(!supports(id))return false;
      // The production body poll can precede parser-blocked tail scripts. Wait for
      // this prepared document's parser, not merely the presence of body children.
      const document=frame.contentDocument,ticket={},epoch=lifecycle;pendingMounts.set(frame,ticket);
      const valid=()=>epoch===lifecycle&&pendingMounts.get(frame)===ticket&&frame.isConnected!==false&&frame.contentDocument===document;
      if(document?.readyState==='loading')await new Promise(resolve=>document.addEventListener('DOMContentLoaded',resolve,{once:true}));
      if(!valid())return false;
      if(id==='specforge')await frame.contentWindow?.__v1SpecShellReady;
      if(!valid())return false;
      pendingMounts.delete(frame);
      const win=frame.contentWindow;if(!win?.__v1DocumentFrame)throw Error('Document frame was not prepared before srcdoc.');const previous=frames.get(frame);previous?.dispose?.();let generation=0,selected='',current=null,disposed=false;const key=()=>{const s=context.getState();return [s.session?.userId,s.organisationId,s.selectedProjectId,!!s.session].join('|');};let formScope=key();
      const active=()=>!disposed&&frame.isConnected!==false&&frame.contentWindow===win&&frame.contentDocument===document;
      if(id==='forms'){win.__v1RememberForm=snapshot=>formDrafts.set(formScope,snapshot);win.__v1DocumentFrame.restoreForm(formDrafts.get(formScope));}
      async function refresh(){const ticket=++generation,scopeKey=key();if(!active())return;if(id==='forms'&&formScope!==scopeKey){formScope=scopeKey;win.__v1DocumentFrame.restoreForm(formDrafts.get(formScope));}current=null;win.__v1DocumentFrame.apply({templates:[],drafts:[],workspaces:[],error:'Loading authenticated project records…'});try{const result=await adapter.load(id);if(!active()||ticket!==generation||scopeKey!==key())return;const s=context.getState(),p=s.projects?.find(p=>p.id===s.selectedProjectId)||{};current=result;const ws=result.workspaces?.find(w=>w.id===selected)||result.workspaces?.[0];selected=ws?.id||'';current.workspace=ws;win.__v1DocumentFrame.apply((id==='specforge'?root.V1CloneDocumentToolsAdapter.safe:x=>x)({...result,workspace:ws,projectName:p.name||p.projectName||'',municipality:p.municipality||'',stage:p.currentPhase||'',role:s.projectRole||s.role||'',readOnly:s.readOnly===true,error:result.available===false?result.reason:null}));}catch(e){if(ticket===generation&&active()&&scopeKey===key())win.__v1DocumentFrame.apply({error:e.message,templates:[],drafts:[],workspaces:[]});}}
      win.__v1DocumentCommand=async(action,payload)=>{if(!active())throw Error('Document frame is no longer active.');if(action==='selectWorkspace'){selected=payload.id;await refresh();return;}if(!current)throw Error('Load current project records first.');const ws=current.workspace;if(action==='exportBoq'){if(id!=='bom')throw Error('Unavailable advisory artifact action.');const record=current.boq?.find(row=>row.id===payload.id);if(!record)throw Error('Select a saved BoQ in this project.');const scopeKey=key(),ticket=generation,artifact=await adapter.downloadBoq(record,payload.format);if(!active()||scopeKey!==key()||ticket!==generation)throw Error('Scope changed before the download.');const url=win.URL.createObjectURL(artifact.blob),link=win.document.createElement('a');link.href=url;link.download=artifact.filename;link.click();win.setTimeout(()=>win.URL.revokeObjectURL(url),30000);win.__v1DocumentFrame.notice('Verified server '+payload.format.toUpperCase()+' artifact retrieved; download requested. Not certification or a tender.');return artifact;}if(action==='saveBoq'){if(id!=='bom')throw Error('Unavailable BoQ action.');const scopeKey=key(),ticket=generation;await adapter.saveBoq(payload);if(!active()||scopeKey!==key()||ticket!==generation)return;await refresh();win.__v1DocumentFrame.notice('Advisory BoQ saved and read back. Not certified, issued, tendered or exported.');return;}if(action==='transitionForm'||action==='exportForm'){const draft=current.drafts?.find(d=>d.id===payload.id);if(!draft)throw Error('Select a saved draft in My Drafts first.');if(action==='exportForm'){const artifact=await adapter.exportArtifact(draft,payload.format);const blob=new win.Blob([artifact.content],{type:artifact.format==='html'?'text/html;charset=utf-8':'application/json'});const url=win.URL.createObjectURL(blob),link=win.document.createElement('a');link.href=url;link.download='form-'+draft.id+'.'+artifact.format;link.click();win.setTimeout(()=>win.URL.revokeObjectURL(url),30000);win.__v1DocumentFrame.notice('Server '+artifact.format.toUpperCase()+' artifact retrieved; download requested. No PDF or digital signature claimed.');return;}const transition={draft:'submit-review',review_required:'approve',approved:'issue'}[draft.status];if(!transition)throw Error('This form has no pending review transition.');const reason=win.prompt('Reason to '+transition+' the exact saved form');if(!reason?.trim()||!win.confirm('Confirm '+transition+' of '+draft.title+' revision '+draft.revision+'?'))return;const saved=await adapter.formTransition(draft,transition,{humanConfirmed:true,reason});await refresh();win.__v1DocumentFrame.selectDraft(saved);win.__v1DocumentFrame.notice('Server form state: '+saved.status+' — verified by readback.');return;}if(action==='saveForm'){const selectedTemplate=current.templates?.find(t=>t.id===payload.template?.id);const selectedDraft=payload.draft?current.drafts?.find(d=>d.id===payload.draft.id):null;if(!selectedTemplate||payload.draft&&!selectedDraft)throw Error('Select a verified template and current draft.');const reason=selectedDraft?win.prompt('Reason for creating a new revision (required)'):null;if(selectedDraft&&!reason?.trim())return;const submitted={template:selectedTemplate,draft:selectedDraft||undefined,values:{...payload.values}},scopeKey=key(),ticket=generation;const saved=await adapter.saveForm({...submitted,reason});const retained=formDrafts.get(scopeKey);if(retained&&retained.template?.id===submitted.template.id&&retained.draft?.id===submitted.draft?.id)formDrafts.set(scopeKey,{...retained,draft:saved,template:saved.template});if(!active()||scopeKey!==key()||ticket!==generation)return;current.drafts=[...(current.drafts||[]).filter(d=>d.id!==saved.id),saved];win.__v1DocumentFrame.completeSave(submitted,saved);return;}if(['section','item','decision','issue'].includes(action)){
        const writeTicket=generation,writeScope=key(),writeCurrent=current;const canWrite=()=>{const s=context.getState();return active()&&generation===writeTicket&&key()===writeScope&&current===writeCurrent&&s.readOnly!==true&&(s.projectRole||s.role)!=='viewer'&&s.ready!==false&&!!s.session&&!!ws&&current.available!==false;};if(!canWrite())throw Error('Read-only or stale specification context. Nothing was saved or issued.');
        let p=payload;if(action==='decision'||action==='issue'){const reason=win.prompt('Reason for '+action+' (required)');if(!reason?.trim())return;const role=win.prompt('Your assigned project discipline (server verifies authority)');if(!role?.trim()||!win.confirm('Confirm '+action+' of the exact current server record?'))return;p=action==='issue'?{humanConfirmed:true,issueReason:reason,professionalRole:role}:{action:payload.action,humanConfirmed:true,reason,actingRole:role};}
        if(!canWrite())throw Error('Read-only or stale specification context. Nothing was saved or issued.');await adapter.specWrite(ws,{section:'sections',item:'items',decision:'decisions',issue:'issue'}[action],p,payload.id);if(!canWrite())return;await refresh();if(!active()||writeScope!==key())return;win.closeModal?.();win.__v1DocumentFrame.notice('Server record updated and read back. No email, procurement, PDF or digital signature claimed.');return;
      }throw Error('Unavailable: this control is not connected to a verified write contract.');};
      const unsubscribe=context.subscribe?.(()=>{void refresh();});const dispose=()=>{if(id==='forms'&&!disposed){win.__v1DocumentFrame.snapshotForm();delete win.__v1RememberForm;}disposed=true;generation++;unsubscribe?.();delete win.__v1DocumentCommand;};frames.set(frame,{dispose});await refresh();return {refresh,dispose};
    }
    return {supports,prepareSource,mountFrame,dispose(){lifecycle++;pendingMounts.clear();frames.forEach(x=>x.dispose());frames.clear();formDrafts.clear();}};
  }
  root.V1CloneDocumentTools={install};
})(window);
