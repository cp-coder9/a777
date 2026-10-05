(function (root) {
  'use strict';
  const unwrap = response => response?.data ?? response;
  const TOOL = 'issues_rfis';
  const DISCIPLINES = ['architectural','structural','civil','electrical','mechanical','quantity_surveying'];
  function install({context}) {
    const adapter = root.V1CloneSiteToolsAdapter.create(context);
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const ownsRoute = () => { const view = typeof state === 'object' ? state : root.state; return !view || view.tool === undefined || view.tool === TOOL; };
    const key = () => { const s=context.getState();return [s.session?.userId,s.organisationId,s.selectedProjectId].join('|'); };
    const membership = () => { const s=context.getState();return s.session?.memberships?.find(m=>m.organisationId===s.organisationId)?.role; };
    const signature = () => [key(),membership(),context.getState().readOnly,context.getState().mode].join('|');
    const hasProject = () => { const s=context.getState();return !!(s.ready&&s.session&&s.organisationId&&s.mode!=='standalone'&&/^[a-f0-9]{32}$/i.test(s.selectedProjectId||'')); };
    const canWrite = () => hasProject() && context.getState().readOnly!==true && ['owner','admin','member'].includes(membership());
    const slots = new Map();
    let generation=0, disposed=false, active=false, pending=null, currentSignature=signature();
    function scope() {
      const s=context.getState();
      if(!s.ready||!s.session||!s.organisationId||s.mode==='standalone'||!/^[a-f0-9]{32}$/i.test(s.selectedProjectId||''))throw Error('Select an authenticated project.');
      return s;
    }
    function slot() {
      const id=key();if(!slots.has(id))slots.set(id,{records:[],drafts:new Map(),form:null,message:'',loaded:false});return slots.get(id);
    }
    function valid(origin) { return !disposed && signature()===origin; }
    function draftFor(form) {
      const data=slot();if(!data.drafts.has(form))data.drafts.set(form,{fields:{subject:'',question:'',discipline:'architectural',due:'',answer:'',requiresInstruction:false,reason:'',confirmed:false},dirty:false,needsReview:false,reviewLoaded:false});
      return data.drafts.get(form);
    }
    function status(message) { slot().message=message;if(ownsRoute()&&!disposed){const el=root.document.querySelector('[data-rfi-status]');if(el)el.textContent=message;} }
    function disabled(flag) { return flag?' disabled':''; }
    function field(name,label,value,options={}) {
      const attr=' data-rfi-field="'+name+'" data-rfi-'+(options.hook||name)+' '+(options.required?'required ':'')+(options.max?'maxlength="'+options.max+'" ':'');
      const input=options.area?'<textarea '+attr+' rows="3">'+esc(value)+'</textarea>':'<input '+attr+'type="'+(options.type||'text')+'" value="'+esc(value)+'">';
      return '<label class="field"><span>'+label+'</span>'+input+'</label>';
    }
    function formHTML(data) {
      if(!data.form||!canWrite())return '';
      const [kind,id]=data.form.split(':');const draft=draftFor(data.form),f=draft.fields,record=data.records.find(r=>r.id===id);
      const allowed=kind==='create'||(kind==='respond'&&['open','overdue'].includes(record?.status))||(kind==='close'&&record?.status==='responded');
      const title=kind==='create'?'New RFI':kind==='respond'?'Respond to RFI':'Close RFI';
      const inputs=kind==='create'
        ? field('subject','Subject',f.subject,{required:true,max:220})+field('question','Question and drawing/specification references',f.question,{required:true,area:true,max:12000})+'<label class="field"><span>Discipline</span><select data-rfi-field="discipline" data-rfi-discipline>'+DISCIPLINES.map(d=>'<option value="'+d+'"'+(d===f.discipline?' selected':'')+'>'+esc(d.replaceAll('_',' '))+'</option>').join('')+'</select></label>'+field('due','Response due date (17:00 South Africa time)',f.due,{required:true,type:'date'})
        : kind==='respond'?field('answer','Response',f.answer,{required:true,area:true,max:12000})+'<label><input type="checkbox" data-rfi-field="requiresInstruction"'+(f.requiresInstruction?' checked':'')+'> Flag for formal instruction review</label>'
        :field('reason','Close-out reason',f.reason,{required:true,area:true,max:1000,hook:'close-reason'});
      return '<section class="panel" data-rfi-detail><h2>'+title+'</h2>'+(record?'<p><strong>'+esc(record.subject)+'</strong></p><p>'+esc(record.question)+'</p>':'')+
        (!allowed?'<p role="status">This record has changed. Review its current status below. Your draft is kept.</p>':'')+
        (draft.needsReview?'<p role="status">The earlier save may have reached the server. Reload records and review them before sending this draft again.</p>'+(draft.reviewLoaded?'<label><input type="checkbox" data-rfi-retry-reviewed> I reviewed the loaded records and need to send this draft again.</label>':''):'')+
        '<form data-rfi-'+kind+'-form>'+inputs+'<label><input type="checkbox" data-rfi-field="confirmed" data-rfi-confirm required'+(f.confirmed?' checked':'')+'> I confirm this '+(kind==='create'?'RFI should be recorded':kind==='respond'?'response should be saved':'RFI should be closed with this reason')+'.</label>'+
        '<button class="btn dark" type="submit" data-rfi-submit-'+kind+disabled(pending||!allowed||draft.needsReview)+'>'+(pending?'Saving…':kind==='create'?'Create RFI':kind==='respond'?'Save response':'Close RFI')+'</button></form>'+
        '<button class="btn ghost" type="button" data-rfi-hide-form>Close form (keep draft)</button> <button class="btn ghost" type="button" data-rfi-discard'+disabled(pending)+'>Discard draft</button></section>';
    }
    function render() {
      if(disposed||!ownsRoute()||!hasProject())return;
      const main=root.document.querySelector('#main');if(!main)return;
      const s=context.getState(),data=slot(),readOnly=!canWrite();
      const project=s.projects?.find(p=>p.id===s.selectedProjectId)?.name||'Selected project';
      const rows=[...data.records].sort((a,b)=>String(a.status).localeCompare(String(b.status))).map(r=>'<tr data-rfi-row="'+esc(r.id)+'"><td><strong>'+esc(r.subject)+'</strong><p>'+esc(r.question)+'</p></td><td>'+esc(r.discipline||'—')+'</td><td>'+esc(r.dueAt?.split('T')[0]||'—')+'</td><td>'+esc(r.status)+'</td><td>'+esc(r.response?.answer||'—')+'</td><td>'+(['open','overdue'].includes(r.status)?'<button class="btn" data-rfi-respond="'+esc(r.id)+'"'+disabled(readOnly||pending)+'>Respond</button>':r.status==='responded'?'<button class="btn" data-rfi-close="'+esc(r.id)+'"'+disabled(readOnly||pending)+'>Close</button>':'')+'</td></tr>').join('');
      main.innerHTML='<div class="rfi-workspace" data-rfi-tool="'+TOOL+'" aria-busy="'+Boolean(pending)+'"><div class="rfi-hero"><div><h1>Issues / RFIs</h1><p>Project: <strong>'+esc(project)+'</strong> · '+data.records.length+' recorded</p></div><button class="btn" data-rfi-create'+disabled(readOnly||pending)+'>+ New RFI</button> <button class="btn ghost" data-rfi-refresh'+disabled(pending)+'>Reload records</button></div>'+
        '<p>Internal field records only — not contractual notice or statutory approval.</p>'+(readOnly?'<p class="pill">Read-only membership: you can review RFIs but cannot create, respond or close them.</p>':'')+
        '<p class="pill" data-rfi-status role="status">'+esc(data.message||(!data.loaded?'Loading RFIs…':'Project records loaded.'))+'</p>'+formHTML(data)+
        '<section class="panel"><h2>Open &amp; recorded RFIs</h2><table class="table"><thead><tr><th>Subject / question</th><th>Discipline</th><th>Due</th><th>Status</th><th>Response</th><th>Action</th></tr></thead><tbody>'+(rows||'<tr><td colspan="6">No RFIs recorded for this project.</td></tr>')+'</tbody></table></section></div>';
      bind(main,data);
      root.V1CloneRuntime?.controllers?.shell?.reconcile();
    }
    function open(form) { if(pending||!canWrite()||!ownsRoute())return;slot().form=form;draftFor(form);render(); }
    function bind(main,data) {
      main.querySelector('[data-rfi-create]')?.addEventListener('click',()=>open('create'));
      main.querySelector('[data-rfi-refresh]')?.addEventListener('click',()=>{if(!pending)void refresh();});
      for(const kind of ['respond','close'])main.querySelectorAll('[data-rfi-'+kind+']').forEach(b=>b.addEventListener('click',()=>open(kind+':'+b.getAttribute('data-rfi-'+kind))));
      const form=data.form,draft=form?draftFor(form):null,origin=signature();
      main.querySelectorAll('[data-rfi-field]').forEach(input=>{
        const save=()=>{if(!valid(origin)||slot()!==data||data.form!==form||!canWrite())return;draft.fields[input.getAttribute('data-rfi-field')]=input.type==='checkbox'?input.checked:input.value;draft.dirty=true;};
        input.addEventListener('input',save);input.addEventListener('change',save);
      });
      main.querySelector('form')?.addEventListener('submit',event=>{event.preventDefault();void submit(form);});
      main.querySelector('[data-rfi-hide-form]')?.addEventListener('click',()=>{data.form=null;render();});
      main.querySelector('[data-rfi-discard]')?.addEventListener('click',()=>{if(!pending&&root.confirm?.('Discard this unsaved RFI draft?')){data.drafts.delete(form);data.form=null;render();}});
      main.querySelector('[data-rfi-retry-reviewed]')?.addEventListener('change',event=>{if(event.target.checked&&draft.reviewLoaded&&!pending){draft.needsReview=false;render();}});
    }
    async function submit(form) {
      if(disposed||pending||!ownsRoute()||!canWrite()||slot().form!==form)return;
      const data=slot(),draft=draftFor(form),[kind,id]=form.split(':'),f={...draft.fields},origin=signature();
      if(draft.needsReview)return;
      try{scope();}catch(error){status(error.message);return;}
      if(!f.confirmed){status('Confirm the action before saving. Your draft is kept.');return;}
      let input;
      if(kind==='create') {
        if(!f.subject.trim()||!f.question.trim()||!DISCIPLINES.includes(f.discipline)||!/^\d{4}-\d{2}-\d{2}$/.test(f.due)){status('Subject, question, discipline and response due date are required.');return;}
        input={subject:f.subject.trim(),question:f.question.trim(),discipline:f.discipline,dueAt:f.due+'T17:00:00+02:00',linkedDocumentIds:[],humanConfirmed:true};
      } else {
        const record=data.records.find(r=>r.id===id);
        if(!record||(kind==='respond'?!['open','overdue'].includes(record.status):record.status!=='responded')){status('The RFI status has changed. Reload records before continuing. Your draft is kept.');return;}
        const text=String(kind==='respond'?f.answer:f.reason).trim();if(!text){status(kind==='respond'?'Enter a response.':'Enter a close-out reason.');return;}
        input=kind==='respond'?{answer:text,requiresInstruction:f.requiresInstruction,humanConfirmed:true}:{reason:text,humanConfirmed:true};
      }
      const operation={origin,form};pending=operation;data.message='Saving and verifying the project record…';render();
      try {
        const saved=await (kind==='create'?adapter.createRfi(input):kind==='respond'?adapter.respondRfi(id,input):adapter.closeRfi(id,input));
        if(!valid(origin))return;
        if(!saved?.id)throw Error('The adapter did not return a verified RFI identity. Reload records before retrying.');
        const index=data.records.findIndex(r=>r.id===saved.id);if(index===-1)data.records.push(saved);else data.records[index]=saved;
        if(JSON.stringify(draft.fields)===JSON.stringify(f)){data.drafts.delete(form);if(data.form===form)data.form=null;}
        data.message='RFI '+(kind==='create'?'created':kind==='respond'?'response saved':'closed')+' and verified in project records.';
      } catch(error) {
        // A failed read-back can follow an accepted POST. Never silently repost it.
        draft.needsReview=![400,401,403,404,409,422].includes(error.status);draft.reviewLoaded=false;
        data.message=(error.message||'Save failed')+' Your draft is kept. '+(draft.needsReview?'Reload and review records before retrying.':'No success claimed.');
      } finally {
        if(pending===operation)pending=null;
        // Release the current identity's busy UI without restoring the old slot.
        if(!disposed&&hasProject()&&ownsRoute())render();
      }
    }
    async function refresh() {
      if(disposed||!ownsRoute()||!hasProject())return false;
      const ticket=++generation,origin=signature(),data=slot();
      try {
        const s=scope(),site=unwrap(await context.read('/projects/'+encodeURIComponent(s.selectedProjectId)+'/site-execution-workspace'));
        if(!valid(origin)||ticket!==generation||!ownsRoute())return false;
        if(!Array.isArray(site?.rfis))throw Error('Invalid RFI workspace response.');
        data.records=site.rfis.filter(r=>(!r.projectId||r.projectId===s.selectedProjectId)&&(!r.organisationId||r.organisationId===s.organisationId));data.loaded=true;
        for(const draft of data.drafts.values())if(draft.needsReview)draft.reviewLoaded=true;
        render();return true;
      }catch(error){if(valid(origin)&&ticket===generation&&ownsRoute()){data.message=(error.message||'Load failed')+' No records loaded.';render();}return false;}
    }
    async function apply(toolId) {
      if(toolId!==TOOL)return{applied:false,reason:'not-issues-rfis'};
      if(disposed||!ownsRoute())return{applied:false,reason:'stale'};
      active=true;return{applied:await refresh(),tool:TOOL};
    }
    const unsubscribe=context.subscribe?.(()=>{const next=signature();if(next!==currentSignature){currentSignature=next;++generation;}if(active&&!disposed&&ownsRoute())void refresh();});
    const unregister=context.registerDraftGuard?.(()=>!pending);
    const beforeUnload=event=>{if(pending||[...slots.values()].some(s=>[...s.drafts.values()].some(d=>d.dirty))){event.preventDefault();event.returnValue='';}};
    root.addEventListener?.('beforeunload',beforeUnload);
    function dispose(){disposed=true;active=false;++generation;unsubscribe?.();unregister?.();root.removeEventListener?.('beforeunload',beforeUnload);slots.clear();}
    return{apply,dispose,spec:{tool:TOOL,name:'Issues / RFIs',functional:true}};
  }
  root.V1CloneRfi={install};
})(window);
