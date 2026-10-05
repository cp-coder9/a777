(function(root){
  'use strict';
  function install({context}){
    if(root.V1ClonePractice.controller)return root.V1ClonePractice.controller;
    const adapter=root.V1ClonePracticeAdapter.create(context),doc=root.document;
    const ui=()=>typeof state==='object'?state:root.state||{};
    const data=root.__pmData||(root.__pmData={});
    let records={tasks:[],pipelineItems:[],timesheets:[],resourceAllocations:[],programme:[],milestones:[],fees:[]},generation=0,pending=false,loaded=false,error='',draft=null;
    const drafts=new Map(),scopeKey=()=>context.getState().organisationId+':'+context.getState().selectedProjectId;
    let current=scopeKey();
    const projectRequired=()=>adapter.projectRequired();
    async function attach(){try{if(await context.setMode('project')===false)return;if(projectRequired())return notice('No authorised project is available.');root.openTool?.('practice','project');}catch(e){notice(e.message);}}

    const all=s=>Array.from(doc.querySelectorAll(s)),one=s=>doc.querySelector(s);
    const notice=m=>{error=m;root.showToast?.(m);apply('practice');};
    function inject(){Object.assign(data,adapter.viewModel(records));}
    inject();
    function repaint(){if(ui().tool==='practice')root.renderMain?.();}
    async function refresh(){const token=++generation,key=scopeKey();if(projectRequired()){records={tasks:[],pipelineItems:[],timesheets:[],resourceAllocations:[],programme:[],milestones:[],fees:[]};loaded=true;error='';inject();repaint();return false;}try{const value=await adapter.workspace();if(token!==generation||key!==scopeKey())return false;records=value;loaded=true;error='';inject();repaint();return true;}catch(e){if(token===generation&&key===scopeKey()){loaded=false;error=e.message;records={tasks:[],pipelineItems:[],timesheets:[],resourceAllocations:[]};inject();repaint();}return false;}}
    async function save(operation){if(pending)return notice('A practice save is already pending.');pending=true;apply('practice');const submittedDraft=draft,key=scopeKey();try{const saved=await operation();if(key!==scopeKey())throw Error('Project changed; refresh the original project before retrying.');if(draft===submittedDraft){draft=null;drafts.delete(key);}await refresh();notice('Saved record verified by read-back.'+(loaded?'':' Workspace refresh failed; refresh before further changes.'));return saved;}catch(e){notice(e.message+' No verified success. Refresh before retrying an uncertain write.');return null;}finally{pending=false;apply('practice');}}
    function ask(kind,fields){if(pending){notice('A practice save is already pending.');return null;}if(!adapter.canWrite()){notice('Practice is read-only for this membership.');return null;}if(draft?.kind!==kind)draft={kind,values:{}};for(const [key,label,initial] of fields){const value=root.prompt?.(label,String(draft.values[key]??initial??''));if(value===null||value===undefined){if(!Object.keys(draft.values).length)draft=null;return null;}draft.values[key]=value;drafts.set(current,draft);}return {...draft.values};}
    root.addTask=async()=>{const s=context.getState();const p=ask('task',[['title','Task name'],['assignedUserId','Assigned member ID (32 hexadecimal characters)',s.session?.userId],['dueAt','Due date and time (include timezone, e.g. 2026-09-08T09:00:00+02:00)'],['slaHours','SLA hours',24],['priority','Priority: low, medium, high, critical','medium']]);if(!p)return;if(!root.confirm?.('Create this task in the selected project?'))return;return save(()=>adapter.createTask({...p,slaHours:Number(p.slaHours)}));};
    async function transition(i,status){const task=records.tasks[i];if(!task||!loaded)return notice('Refresh the task before changing it.');if(['completed','cancelled'].includes(task.status))return notice('This task is closed; reopening is not supported by the service.');const p=ask('transition:'+task.id,[['reason','Reason for changing task to '+status]]);if(!p||!root.confirm?.('Change this task to '+status+'?'))return;return save(()=>adapter.transitionTask(task.id,{status,reason:p.reason,humanConfirmed:true}));}
    root.completeTask=(i,checked)=>checked?transition(i,'completed'):notice('Reopening tasks is unavailable.');
    root.cycleTask=i=>transition(i,({open:'in_progress',in_progress:'blocked',blocked:'completed'})[records.tasks[i]?.status]||'completed');
    root.addTime=async()=>{const p=ask('time',[['hours','Hours worked'],['workDate','Work date (YYYY-MM-DD)'],['workstage','Work stage'],['description','Work description'],['billable','Billable? yes or no','no']]);if(!p||!root.confirm?.('Capture this time as a draft timesheet?'))return;if(!['yes','no'].includes(p.billable.toLowerCase()))return notice('Enter yes or no for billable.');return save(()=>adapter.captureTimesheet({minutes:Math.round(Number(p.hours)*60),workDate:p.workDate,workstage:p.workstage,description:p.description,billable:p.billable.toLowerCase()==='yes'}));};
    root.updateResource=(i,v,e)=>{if(pending)return;if(e?.parentElement?.querySelector('output'))e.parentElement.querySelector('output').textContent=v+'% (unsaved)';draft={kind:'allocation',values:{index:i,percent:v}};drafts.set(current,draft);};
    async function allocation(i,percent){const r=records.resourceAllocations[i];if(!r)return;const hours=r.capacityMinutes/60;const p={userId:r.userId,startsOn:r.startsOn,endsOn:r.endsOn,capacityHours:hours,allocatedHours:hours*Number(percent)/100,humanConfirmed:true};if(!root.confirm?.('Create an additional allocation record for '+p.allocatedHours+' hours? Existing allocations are retained.'))return;return save(()=>adapter.allocateResource(p));}
    const unavailable=()=>notice('This control has no connected write contract. No change was saved.');
    // Programme / milestone / fee controls now persist through the real backend.
    root.addProgramme=async()=>{const p=ask('programme',[['title','Workstream title'],['ownerRole','Owner role (e.g. engineer, architect, qs)','architect'],['weeks','Weeks',4],['progress','Progress % (0-100)',0]]);if(!p)return;if(!root.confirm?.('Add this workstream to the selected project?'))return;return save(()=>adapter.createProgramme({title:p.title,ownerRole:p.ownerRole,weeks:Number(p.weeks),progress:Number(p.progress),sortOrder:0}));};
    root.updateProgramme=(i,k,v)=>{const item=records.programme[i];if(!item)return;const value=[1,2].includes(k)?Number(v):v;const patch={title:item.title,ownerRole:item.ownerRole,weeks:item.weeks,progress:item.progress};patch[['title','weeks','progress','ownerRole'][k]]=value;return save(()=>adapter.updateProgramme(item.id,patch));};
    root.addMilestone=async()=>{const p=ask('milestone',[['title','Milestone name'],['targetDate','Target date (YYYY-MM-DD)']]);if(!p)return;if(!root.confirm?.('Add this milestone to the selected project?'))return;return save(()=>adapter.createMilestone({title:p.title,targetDate:p.targetDate}));};
    root.toggleMilestone=(i,v)=>{const m=records.milestones[i];if(!m)return;return save(()=>adapter.setMilestoneCompletion(m.id,v));};
    root.updateFee=(k,v)=>{const f=data.fee||{};const value=Number(v);const map={construction:f.construction,percent:f.percent,planned:f.planned,invoiced:f.invoiced,expenses:f.expenses};map[k]=value;return save(()=>adapter.saveFee({constructionCostCents:Math.round((map.construction||0)*100),feePercent:Number(map.percent||0),plannedDeliveryCents:Math.round((map.planned||0)*100),invoicedCents:Math.round((map.invoiced||0)*100),expensesCents:Math.round((map.expenses||0)*100)}));};
    // Export: produce a real CSV of the loaded practice records (tasks, timesheets,
    // resource allocations) and trigger a download, instead of a silent no-op. This
    // exports records already loaded and verified from the service; it does not invent data.
    function exportCsv(data=records){
      const esc=v=>{const s=String(v??'');return /[",\n]/.test(s)?'"'+s.replace(/"/g,'""')+'"':s;};
      const name=id=>{const s=context.getState().session;return id===s?.userId?s.email:(id||'Unassigned');};
      const lines=[['type','title','status','person','workstage','date','value'].join(',')];
      (data.tasks||[]).forEach(t=>lines.push(['task',esc(t.title),esc(t.status),esc(name(t.assignedUserId)),'',esc(t.dueAt),''].join(',')));
      (data.timesheets||[]).forEach(t=>lines.push(['timesheet','',esc(t.status||'draft'),esc(name(t.userId)),esc(t.workstage),esc(t.workDate),esc((t.minutes||0)/60+' h')].join(',')));
      (data.resourceAllocations||[]).forEach(r=>lines.push(['allocation','',esc(r.status||'planned'),esc(name(r.userId)),'',esc(r.startsOn+' – '+r.endsOn),esc(r.allocatedMinutes+' min')].join(',')));
      return lines.join('\n');
    }
    function downloadCsv(){
      const csv=exportCsv();const blob=new Blob([csv],{type:'text/csv;charset=utf-8'});const a=doc.createElement('a');
      a.href=URL.createObjectURL(blob);a.download='practice-records.csv';a.click();URL.revokeObjectURL(a.href);
      notice('Exported '+records.tasks.length+' tasks, '+records.timesheets.length+' timesheets, '+records.resourceAllocations.length+' allocations to practice-records.csv.');
    }
    function apply(id=ui().tool){if(id!=='practice'||ui().tool!=='practice')return;const shell=one('#main .pm-shell');if(!shell)return;
      const scope=context.getState(),project=scope.projects?.find(p=>p.id===scope.selectedProjectId);
      const title=one('#main .pm-toolbar > b');if(title)title.textContent=project?.name||'Select a project';
      // The reference renderer treats absent money as zero and divides by it. Replace
      // its metrics with supported values; time records do not include labour rates.
      const f=data.fee||{},known=v=>typeof v==='number'&&Number.isFinite(v),money=v=>known(v)?'R '+v.toLocaleString('en-ZA',{maximumFractionDigits:2}):'Not recorded';
      const fee=known(f.construction)&&known(f.percent)?f.construction*f.percent/100:null;
      const billed=known(f.invoiced)&&known(fee)&&fee>0?Math.round(f.invoiced/fee*100)+'% billed':'Billing percentage unavailable';
      const metrics={
        'Overall progress':[known(project?.progress)?project.progress+'%':'Not recorded',project?.phase?project.phase.replaceAll('_',' ')+' stage':'Project progress not supplied'],
        'Planned fee':[money(fee),known(f.percent)?f.percent+'% of construction cost':'Fee basis not recorded'],
        'Calculated fee':[money(fee),'Before VAT'],
        'Agreed fee':[money(fee),'Calculated from fee settings; acceptance not verified'],
        'Planned margin':[known(fee)&&fee>0&&known(f.planned)?Math.round((fee-f.planned)/fee*100)+'%':'Not calculated',known(fee)&&known(f.planned)?money(fee-f.planned):'Fee and delivery budget required'],
        'Invoiced':[money(f.invoiced),billed],
        'Expenses':[money(f.expenses),'Recorded project expenses'],
        'Labour cost':['Not calculated','Labour rates not supplied'],
        'Delivery cost':['Not calculated','Labour rates not supplied'],
        'Current margin':['Not calculated','Complete delivery costing required'],
        'Contribution margin':['Not calculated','Complete delivery costing required'],
      };
      all('#main .pm-kpi').forEach(card=>{const label=card.querySelector('label'),value=metrics[label?.textContent];if(!value)return;const strong=card.querySelector('strong'),small=card.querySelector('small');if(strong)strong.textContent=value[0];if(small)small.textContent=value[1];if(label.textContent==='Agreed fee')label.textContent='Calculated fee';});
      const note=one('#main .pm-note');if(note){note.textContent=projectRequired()?'Practice requires a selected project. Attach a project to load tasks, programme and fee records.':error||(!loaded?'Loading practice records…':'Project records loaded. Programme, milestones and fee settings are stored for this project; financial totals and forecasts remain advisory.');note.setAttribute('role','status');if(projectRequired()){const b=doc.createElement('button');b.className='btn';b.textContent='Attach project';b.disabled=!context.getState().projects?.length;b.onclick=attach;note.append(b);}if(!projectRequired()&&!loaded&&!pending){const b=doc.createElement('button');b.className='btn ghost';b.textContent='Retry load';b.onclick=refresh;note.append(b);}}
      all('#main .bar-chart i').forEach(e=>{e.style.height='0%';e.title='No forecast data';const label=e.querySelector('span');if(label)label.textContent='';});
      all('#main .pm-toolbar > span').forEach(e=>{if(/% complete|live performance/.test(e.textContent))e.textContent='Selected project records';});
      if(ui().pmTab!=='fees')all('#main .pm-field input').forEach(e=>{e.value='';e.disabled=true;e.placeholder='Unavailable';});
      if(ui().pmTab==='timesheets')all('#main tbody tr').forEach(tr=>{if(tr.cells[3])tr.cells[3].textContent='Not recorded';});
      if(ui().pmTab==='calendar'){const now=new Date(),year=now.getFullYear(),month=now.getMonth(),first=(new Date(year,month,1).getDay()+6)%7,last=new Date(year,month+1,0).getDate();const h=one('#main .pm-panel h2');if(h)h.textContent=now.toLocaleString('en-ZA',{month:'long',year:'numeric'})+' task calendar';all('#main .cal-day').forEach((cell,i)=>{cell.replaceChildren();const day=i-first+1,b=doc.createElement('b');b.textContent=day>0&&day<=last?day:'';cell.append(b);if(day<1||day>last)return;const date=year+'-'+String(month+1).padStart(2,'0')+'-'+String(day).padStart(2,'0');records.tasks.filter(t=>t.dueAt?.startsWith(date)).forEach(t=>{const e=doc.createElement('div');e.className='cal-event';e.textContent=t.title;cell.append(e);});});}
      const nativeTabs=['dashboard','programme','tasks','milestones','calendar','team','fees','timesheets','profitability','forecast'];if(!nativeTabs.includes(ui().pmTab)){all('#main .action-list').forEach(e=>{e.replaceChildren();const p=doc.createElement('p');p.className='pm-sub';p.textContent='No records supplied for this view.';e.append(p);});}
      // The service exposes opportunity pipeline, not a construction programme. Keep its own native panel.
      if(ui().pmTab==='forecast'){
        const bars=all('#main .bar-chart i'),items=records.pipelineItems.slice(0,bars.length);
        const maximum=Math.max(0,...items.map(item=>Number(item.weightedValueCents)||0));
        bars.forEach((bar,index)=>{const item=items[index];if(!item)return;const amount=Number(item.weightedValueCents)||0;bar.style.height=(maximum>0?Math.max(0,amount)/maximum*100:0)+'%';bar.title=item.title+' · '+item.stage+' · '+item.probabilityPercent+'% probability · weighted opportunity R '+(amount/100).toFixed(2);const label=bar.querySelector('span');if(label)label.textContent=item.title;});
        const sub=one('#main .pm-sub');if(sub)sub.textContent='Showing '+items.length+' of '+records.pipelineItems.length+' recorded opportunities. Weighted estimates, not earned income or a monthly cash-flow forecast.';
      }
      all('#main .task-card').forEach(e=>{e.style.pointerEvents=pending||!adapter.canWrite()?'none':'';e.setAttribute('aria-disabled',String(pending||!adapter.canWrite()));});all('#main .action-line input[type=checkbox]').forEach(e=>e.disabled=pending||!adapter.canWrite());
      all('#main .resource-row input').forEach((e,i)=>{e.onchange=()=>allocation(i,e.value);e.disabled=!adapter.canWrite()||pending;});
      if(projectRequired()){
        const nav=one('#navigator .nav-sub');if(nav)nav.textContent='Project attachment required';
        all('#main .pm-toolbar > span').forEach(e=>{if(/Selected project records|All projects|live performance/.test(e.textContent))e.textContent='No project records loaded';});
        all('#main input,#main select,#main textarea').forEach(e=>e.disabled=true);
        all('#main button[onclick]').forEach(b=>{if(/addTask|addTime|addProgramme|addMilestone|updateFee|updateProgramme|toggleMilestone|Practice report exported/.test(b.getAttribute('onclick')||''))b.disabled=true;});
        inspect();
      }
      all('#main button[onclick]').forEach(b=>{const action=b.getAttribute('onclick');if(action?.includes('Practice report exported')){b.removeAttribute('onclick');b.onclick=downloadCsv;b.title='Export loaded practice records as CSV';}if(/addTask|addTime/.test(action||''))b.disabled=!adapter.canWrite()||pending||!loaded;});
    }
    function inspect(){if(ui().tool!=='practice'||!projectRequired())return;all('#inspectBody .inspect-block').forEach(block=>{if(['Scope','Current scope'].includes(block.querySelector('h4')?.textContent)&&block.querySelector('p'))block.querySelector('p').textContent='Project attachment required. No project records are loaded.';});}
    const originalInspector=root.renderInspector;root.renderInspector=function(...args){const result=originalInspector?.apply(this,args);inspect();return result;};
    const original=root.renderMain;root.renderMain=function(...args){const result=original?.apply(this,args);apply();return result;};
    context.registerDraftGuard?.(()=>!pending); // Drafts are held by organisation/project; never discard on a switch.
    context.subscribe?.(()=>{const key=scopeKey();if(key===current)return;if(draft)drafts.set(current,draft);current=key;draft=drafts.get(key)||null;generation++;loaded=false;records={tasks:[],pipelineItems:[],timesheets:[],resourceAllocations:[]};inject();repaint();refresh();});
    const controller={apply,refresh,getDraft:()=>draft,ready:null,exportCsv,downloadCsv};root.V1ClonePractice.controller=controller;controller.ready=refresh();return controller;
  }
  root.V1ClonePractice={install};
})(window);
