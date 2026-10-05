(function(root){
 'use strict';
 const warning='Advisory working records only — not statutory approval, certification or municipal lodgement. Current official-source verification and appointed professional review remain required.';
 // Serialized into the original document. No replacement shell, styles or tab implementation.
 function initialise(id){
  const d=document,w=window;
  const note='No project evidence loaded';
  w.__v1ComplianceTemplates=new Map();
  d.querySelectorAll('tbody').forEach(body=>{w.__v1ComplianceTemplates.set(body,body.firstElementChild?.cloneNode(true));if(id!=='xa'||!body.querySelector('input,select'))body.replaceChildren();});
  d.querySelectorAll('.stat-value,.dept-score,.f-value,.doc-source,.row-label,.app-row-ref,.app-row-desc,.deadline-label,.deadline-days').forEach(el=>el.textContent=note);
  d.querySelectorAll('.sev-badge,.badge,.status-pass,.status-fail,.status-pending,.status-na').forEach(el=>{el.textContent='Not assessed';});
  d.querySelectorAll('.dept-bar').forEach(el=>el.style.width='0%');
  d.querySelectorAll('.risk-item,.deadline-row,.app-row,.audit-entry,.timeline-item').forEach(el=>{el.querySelectorAll('*').forEach(x=>{if(!x.children.length)x.textContent=note;});el.removeAttribute('onclick');});
  d.querySelectorAll('input').forEach(el=>{if(el.type==='checkbox')el.checked=false;else el.value='';el.removeAttribute('value');});
  d.querySelectorAll('select').forEach(el=>{el.selectedIndex=-1;if(id==='planning')el.querySelectorAll('option').forEach(option=>{option.textContent='Select project in parent';option.value='';});});
  const protectedText=/advisory|does not|must be reviewed|not constitute|professional.*required|formula|clause|table \d|XA \d|SANS 10400/i;
  // Static reference records are not a source of project facts. Keep their containers and instructional copy.
  const sample=/APP-2026|Hillside|Constantia|Kloof|Riverside|Sunset Views|Faerie Glen|Justin Kruger|ARC-COCT|Erf \d|City of Cape Town|29 Jun|15 Mar|Jul(?:y)? 2026|2026-0|Last run:|Last:|synced|Issue #XA|all .*complete|no blockers|Municipal.Ready|signed off|^signed$|^generated$|^pre-populated$|^uploaded$|^complete$|^pass$|^all pass$|\d\s*\/\s*\d|\d[.,]?\d*\s*(?:%|m²|W\/m²)|^R[\d,]+|\bR \d|SHGC \d|\d.*(?:≥|≤|>|<)|^Zone 5|^5 — Cold Interior|^A1 — Entertainment|^North$|HP 30|sensors pending|no HVAC system|no special requirements/i;
  d.querySelectorAll('body *').forEach(el=>{if(el.children.length||el.closest('script,style,button,select,label,thead')||el.matches('h1,h2,h3,h4,.card-title,.stat-label,.card-label,.tab-trigger,.tab-btn'))return;const text=el.textContent.trim();if(text&&!protectedText.test(text)&&sample.test(text))el.textContent=note;});
  d.querySelectorAll('textarea').forEach(el=>{el.value='';el.textContent='';});
  d.querySelectorAll('h1,h2,h3,h4,.card-label').forEach(el=>{el.childNodes.forEach(n=>{if(n.nodeType===3)n.textContent=n.textContent.replace(/APP-2026-\d+|Hillside[^—]*|July 2026|NFA: [\d,]+ m²/g,'No stored record');});});
  d.querySelectorAll('.audit-date,.audit-action,.audit-detail,.message-bubble,.gantt-bar').forEach(el=>el.textContent=note);
  d.querySelectorAll('body *').forEach(el=>{
   if(el.children.length||el.closest('script,style,button,select,label,thead')||el.matches('h1,h2,h3,h4,.stat-label,.card-label,.card-title,.doc-seq,.stage-circle'))return;
   const t=el.textContent.trim();if(protectedText.test(t))return;
   if(id==='municipal'&&(/\d/.test(t)||/Approved 1st|COCT|BDM-/.test(t)))el.textContent=note;
   if(id==='xa'&&(/\d/.test(t)||/Shopfront|Curtain wall|Retail|Offices|Glasswool|masonry|Metal roof|Tile roof/i.test(t)))el.textContent=note;
   if(id==='planning'&&(/\d.*(?:Jul|Jun|May)|Sarah van|SACPLAN|No appeal lodged|Statutory basis:|Heritage consultant|Draft HIA|objection period|screening mandatory|pre-application meeting required/i.test(t)))el.textContent=note;
  });
  // Clear sample text nodes adjacent to <br>/<strong>, without replacing their parent structure.
  const walker=d.createTreeWalker(d.body,4);let node;while((node=walker.nextNode())){const el=node.parentElement;if(!el||el.closest('script,style,button,select,label,thead')||protectedText.test(node.textContent))continue;if(/APP-2026|Hillside|Sarah van|MPT Hearing:|23 Jul|Hillside HOA/.test(node.textContent))node.textContent=note;}
  d.querySelectorAll('.cal-day').forEach(el=>{el.textContent='';el.removeAttribute('title');});
  d.querySelectorAll('.filter-btn').forEach(el=>el.textContent=el.textContent.replace(/\(\d+\)/,'(0)'));
  d.querySelectorAll('.p-toggle').forEach(el=>{el.textContent='Select project in parent';el.removeAttribute('onclick');});
  d.querySelectorAll('[onclick]').forEach(el=>{if(/alert\(|switchProject\(/.test(el.getAttribute('onclick')))el.removeAttribute('onclick');});
  w.alert=message=>{const n=d.getElementById('v1-compliance-status');if(n)n.textContent='Control boundary: '+String(message)+'. No external action or approval was performed.';};
  const status=d.createElement('p');status.id='v1-compliance-status';status.className=id==='planning'?'card-subtitle':id==='municipal'?'row-label':'text-xs text-surface-400';status.setAttribute('role','status');status.textContent='Advisory working records only — not statutory approval, certification or municipal lodgement. Select an authenticated project to load evidence.';
  (d.querySelector('.main-content,.content-area,main')||d.body).prepend(status);
 }
 function install({context}){
  const adapter=root.V1CloneComplianceToolsAdapter.create(context),frames=new Map();
  const supports=id=>['planning','municipal','xa'].includes(id);
  function prepareSource(id,html){if(!supports(id))return html;const script='<script data-v1-compliance-bridge>('+initialise.toString()+')('+JSON.stringify(id)+');<\/script>';return html.includes('</body>')?html.replace('</body>',script+'</body>'):html+script;}
  function mountFrame(id,frame){
   if(!supports(id))return false;
   const w=frame.contentWindow,d=frame.contentDocument||w?.document;if(!w||!d)throw Error('Compliance frame must be accessible to the parent.');
   if(frames.get(frame)?.document===d)return frames.get(frame).api;
   let rows=[],selected=null,epoch=0,busy=false;
   const text=(selector,value)=>d.querySelectorAll(selector).forEach(el=>el.textContent=String(value??'Not recorded'));
   const status=message=>text('#v1-compliance-status',message+' '+warning);
   function table(selector,data,fields,onSelect){d.querySelectorAll(selector).forEach(body=>{const template=w.__v1ComplianceTemplates?.get(body);body.replaceChildren();for(const record of data){const tr=template?template.cloneNode(true):d.createElement('tr');tr.removeAttribute('onclick');tr.removeAttribute('data-status');tr.querySelectorAll('[onclick]').forEach(el=>el.removeAttribute('onclick'));while(tr.children.length<fields.length)tr.append(d.createElement('td'));Array.from(tr.children).forEach((cell,i)=>cell.textContent=String(fields[i]?.(record)??'—'));if(onSelect){tr.tabIndex=0;tr.dataset.status=record.status;tr.onclick=()=>onSelect(record);tr.onkeydown=e=>{if(e.key==='Enter')onSelect(record);};}body.append(tr);}});}
   function planningDetail(app){selected=app.id;w.navigateTo?.('detail');text('#detail-title,#detail-header-title',app.applicationNumber+' — '+app.title);text('#detail-header-meta',app.propertyReference+' · '+app.municipality+' · '+app.stage);const entries=app.entries||[];for(const [tab,type] of Object.entries({documents:'document',deadlines:'deadline',participation:'participation',conditions:'condition',appeal:'appeal'}))table('#dtab-'+tab+' tbody',entries.filter(e=>e.entryType===type),[e=>e.title,e=>e.status,e=>e.basis,e=>e.dueAt,e=>e.amountCents]);table('#dtab-audit tbody',app.events||[],[e=>e.createdAt,e=>e.eventType,e=>e.reason,e=>e.externalReference]);w.showDetailTab?.('overview');}
   function render(){
    const s=context.getState(),project=s.projects?.find(p=>p.id===s.selectedProjectId),name=project?.name||project?.projectName||'Selected project';
    if(id==='planning'){
     text('#dashboard-title',name+' — Planning Portfolio');text('#dashboard-meta',rows.length+' stored applications · selected project only');
     table('#appTable tbody,#view-applications tbody',rows,[a=>a.applicationNumber,a=>a.applicationType,a=>a.stage,a=>a.municipality,a=>a.status,a=>(a.entries||[]).find(e=>e.entryType==='deadline')?.dueAt],planningDetail);
     const entries=rows.flatMap(a=>(a.entries||[]).map(e=>({...e,applicationNumber:a.applicationNumber})));
     for(const [tab,type] of Object.entries({dl:'deadline',part:'participation',cond:'condition',hear:'hearing'}))table('#dash-'+tab+' tbody',entries.filter(e=>e.entryType===type),[e=>e.applicationNumber,e=>e.title,e=>e.status,e=>e.dueAt,e=>e.basis]);
     for(const [view,type] of Object.entries({deadlines:'deadline',participation:'participation',conditions:'condition',hearings:'hearing',payments:'fee',messages:'message',municipalities:'municipality_profile'}))table('#view-'+view+' tbody',entries.filter(e=>e.entryType===type),[e=>e.applicationNumber,e=>e.title,e=>e.status,e=>e.dueAt,e=>e.basis]);
     const metrics={'Total Applications':rows.length,Active:rows.filter(a=>a.status==='active').length,'Conditions Pending':entries.filter(e=>e.entryType==='condition'&&e.status!=='fulfilled').length};d.querySelectorAll('.stat-card').forEach(card=>{const label=card.querySelector('.stat-label')?.textContent,value=card.querySelector('.stat-value');if(value&&Object.hasOwn(metrics,label))value.textContent=metrics[label];});
     d.querySelectorAll('.filter-btn').forEach(el=>{const m=el.getAttribute('onclick')?.match(/filterApps\(this,'([^']+)'/);if(m)el.textContent=el.textContent.replace(/\(\d+\)/,'('+(m[1]==='all'?rows.length:rows.filter(a=>a.status===m[1]).length)+')');});
     w.showDetail=appId=>{const app=rows.find(a=>a.id===appId);if(app)planningDetail(app);else status('Select a stored application.');};
     text('#breadcrumb',name+' — Town Planning');
    }else if(id==='municipal'){
     const a=rows[0];selected=a?.id||null;text('#project-title',a?.project?.projectName||name);text('#project-meta',a?[a.project.municipality,a.project.propertyReference,a.createdAt].join(' · '):'No stored readiness assessment');
     const categories=Object.entries(a?.categories||{});d.querySelectorAll('.dept-row').forEach((row,i)=>{const category=categories[i];row.querySelector('.dept-name').textContent=category?category[0].replaceAll('_',' '):'No category assessed';row.querySelector('.dept-score').textContent=category?category[1].score+'%':'—';row.querySelector('.dept-bar').style.width=category?Math.max(0,Math.min(100,category[1].score))+'%':'0%';row.querySelector('.sev-badge').textContent=category?'Advisory':'Not assessed';});
     const findings=d.querySelectorAll('#tab-overview .list-row');findings.forEach((row,i)=>{row.querySelector('.row-label').textContent=a?([...a.blockers,...a.professionalReviews][i]||'No stored blockers; professional review still required'):'No assessment loaded';});
     d.querySelectorAll('#tab-pack .doc-row').forEach((row,i)=>{const item=a?.evidencePack?.[i];row.querySelector('.doc-title').textContent=item?.title||'No evidence item';row.querySelector('.doc-source').textContent=item?.source||'Not recorded';row.querySelector('.sev-badge').textContent=item?.status||'Missing';});
     status(a?`Stored assessment ${a.id}: ${a.overallScore}% advisory readiness. ${(a.assumptions||[]).join(' ')}`:'No stored assessment. Run Validation to enter explicit scope facts.');
    }else{
     const run=rows[0];selected=run?.id||null;table('#tab-results tbody',rows.flatMap(r=>(r.output?.results||[]).map(result=>({...result,run:r}))),[r=>r.id,r=>r.type||r.run.toolId,r=>r.run.output.sourceReference,r=>r.totalR??r.message,r=>r.minR??r.run.output.sourceVersion,r=>r.verdict]);
     status(run?`Stored XA calculation ${run.id}: ${run.output?.verdict||run.status}. ${run.output?.limitations||''}`:'No stored XA calculation. Prototype numeric fields are unsaved inputs, not certified outputs. Add window/door or Add Layer to enter a source-bound calculation.');
    }
   }
   async function refresh(){const token=++epoch;rows=[];selected=null;d.querySelectorAll('tbody').forEach(body=>{if(!body.querySelector('input,select'))body.replaceChildren();});text('.stat-value,.dept-score,#detail-title,#detail-header-title,#detail-header-meta','Not loaded');render();status('Loading project evidence.');try{const result=await adapter.list(id);if(token!==epoch)return;rows=result;render();if(id==='planning')status('Loaded '+rows.length+' stored applications.');}catch(e){if(token===epoch)status(e.message);}}
   async function operate(operation,payload){if(busy)throw Error('Wait for the current operation to finish.');busy=true;status('Saving; awaiting server readback.');try{const result=await adapter.operate(id,operation,payload);await refresh();status('Server operation and readback completed.');return result;}catch(e){status(e.message);throw e;}finally{busy=false;}}
   function ask(label){const value=w.prompt(label,'');if(value===null)throw Error('Cancelled. No record was changed.');if(!value.trim())throw Error('A value is required: '+label);return value.trim();}
   function json(label){try{return JSON.parse(ask(label));}catch(e){throw Error('Valid JSON is required. '+e.message);}}
   async function action(label){
    if(id==='planning'&&/New Application/i.test(label)){const input={};for(const key of ['applicationNumber','applicationType','title','municipality','propertyReference','responsibleRole'])input[key]=ask(key+(key==='applicationType'?' (rezoning, consent_use, subdivision, consolidation, removal_restrictions, township_establishment, site_development_plan, other)':''));input.sourceBasis=json('sourceBasis: JSON array of {title,url,retrievedAt YYYY-MM-DD}, from current official sources');return operate('create',input);}
    if(id==='planning'&&/Submit Response/i.test(label)){if(!selected)throw Error('Select a stored application first.');return operate('entry',{applicationId:selected,entryType:'participation',title:ask('Response title'),status:'responded',required:false,basis:ask('Evidence/source basis'),dueAt:null,amountCents:null,payload:{response:ask('Response text')}});}
    if(id==='municipal'&&/Run Validation|Re-Run/i.test(label)){const matrices=await context.read('/api/v1/municipal-requirement-versions');const items=matrices?.data??matrices;const matrixVersionId=ask('Matrix version ID. Available records: '+(Array.isArray(items)?items.map(m=>m.id+' '+m.municipality+' '+m.status).join('; '):'none'));const input={matrixVersionId,occupancyType:ask('occupancyType: single_residential, multi_residential, commercial, public_assembly, mixed_use'),estimatedConstructionValueZar:Number(ask('estimatedConstructionValueZar'))};for(const key of ['changesLoadBearing','changesDrainageOrStormwater','publicAccessOrAssembly','envelopeEnergyImpact','zoningKnown','coverageOrParkingRisk','boundaryOrServitudeUnclear','heritagePotential','environmentalSensitivity','trafficImpact','nbrAdvisoryReviewed','clientAuthorityConfirmed']){const value=ask(key+' (true or false)');if(!['true','false'].includes(value))throw Error('Enter true or false explicitly.');input[key]=value==='true';}input.availableSupportingDocuments=json('availableSupportingDocuments: JSON array of evidence identifiers (or [])');input.professionalSignoffs=json('professionalSignoffs: JSON array of actual recorded professional disciplines (or [])');return operate('assess',input);}
    if(id==='xa'&&/Add window\/door|Add Layer/i.test(label)){const toolId=/Layer/i.test(label)?'r_value_thermal_calculator':'fenestration_xa_calculator';const input=json(toolId+': enter exact calculator input JSON including ruleVersionId, project and '+(toolId==='r_value_thermal_calculator'?'assemblies':'elements')+'. No issue or certification will be claimed.');return operate('calculate',{toolId,input});}
    throw Error(label+': this original control has no bound '+(/certificate|sign.off/i.test(label)?'certification workflow; no certificate or professional sign-off is created.':/export|report/i.test(label)?'export selection; no file has been generated.':/submission/i.test(label)?'external lodgement workflow; no municipal submission is made.':'production operation; no record is changed.'));
   }
   d.addEventListener('click',event=>{const button=event.target.closest('button,a,.action-card,[onclick]');if(!button)return;const inline=button.getAttribute('onclick')||'';if(/^(?:showTab|switchTab|showDashTab|showDetailTab|navigateTo|filterApps|setMode)\(/.test(inline))return;if(button.closest('tr')&&button.tagName!=='BUTTON')return;event.preventDefault();event.stopImmediatePropagation();action(button.textContent.trim()).catch(e=>status(e.message));},true);
   const nativeMode=w.setMode;if(id==='planning')w.setMode=mode=>{nativeMode?.(mode);render();};
   w.switchProject=()=>status('Change project using the parent authenticated project selector.');
   w.V1ComplianceBridge={refresh,operate};
   const api={refresh,operate,dispose(){epoch++;unsubscribe?.();frames.delete(frame);}};const unsubscribe=context.subscribe?.(()=>refresh());frames.set(frame,{document:d,api});refresh();return api;
  }
  return {supports,prepareSource,mountFrame};
 }
 root.V1CloneComplianceTools={install};
})(window);
