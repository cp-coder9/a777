(function (root) {
  'use strict';
  function install({context}) {
    const api=root.V1CloneScaffoldRegistry;
    if (!api || typeof context?.getState !== 'function' || typeof context?.read !== 'function') throw Error('Scaffold adapters require the shared context and registry.');
    const esc=api.escape;
    let generation=0, activeId=null, activeShell=null, disposed=false, lastScope=api.scopeKey(context.getState());
    const items=lines=>lines.map(line=>'<li>'+esc(line)+'</li>').join('');
    function slots(shell) {
      const cards=shell.querySelectorAll('.scaffold-card');
      return {inputs:cards[0]?.querySelector('ul'),outputs:cards[1]?.querySelector('ul'),functional:cards[3]?.querySelector('p'),status:shell.querySelector('.scaffold-hero .status'),copy:shell.querySelectorAll('.scaffold-hero > p'),chips:shell.querySelectorAll('.contract-chip')};
    }
    function showScope(s, state) {
      const project=state.projects?.find(p=>p.id===state.selectedProjectId);
      const scope=state.selectedProjectId ? 'Project: '+(project?.name || state.selectedProjectId) : 'Standalone / no project selected';
      if (s.inputs) s.inputs.innerHTML=items([scope,'Organisation: '+(state.organisationId || state.org?.id || state.org || 'Not selected'),'Authenticated user: '+(state.session?.userId || 'Not signed in'),'Original prototype has no editable domain fields.']);
      if (s.copy[1]) s.copy[1].textContent=scope+'. API reads retain server-side permissions. No original form is available for domain writes.';
      // Leave Orientation, Stage, Module group, icon, headings and tabs untouched.
      if (s.chips[3]) s.chips[3].innerHTML='<b>Source</b>Existing API · read-only projection';
    }
    function bindOriginalActions(main, s, contract) {
      // The two controls the original scaffold shipped but could never save. When the
      // module-record controller is installed they perform a real, read-back verified write;
      // otherwise they keep the honest refusal below.
      const records = root.V1CloneRuntime?.controllers?.scaffoldRecords;
      if (records && typeof records.bindScaffoldActions === 'function') {
        void records.bindScaffoldActions(main, contract.id);
        return;
      }
      for (const button of main.querySelectorAll('.page-head button')) {
        if (button.textContent.trim()==='Add implementation note') {
          button.removeAttribute('onclick');
          button.title='View the implementation gap; no note persistence endpoint or note input exists in this original scaffold.';
          button.onclick=event=>{event?.preventDefault(); if(s.functional)s.functional.textContent=contract.gap+' Implementation note not saved: the original has no note field or persistence contract.';};
        } else if (button.getAttribute?.('onclick')?.includes('attachProject(')) {
          button.removeAttribute('onclick'); button.onclick=null; button.disabled=true;
          button.title='Select a project with the shared project selector. This prototype has no record to attach.';
        }
      }
    }
    async function apply(toolId) {
      const token=++generation;
      activeId=null;
      if (disposed) return {applied:false,reason:'disposed'};
      const contract=api.registry[toolId],main=root.document.querySelector('#main'),shell=main?.querySelector('.scaffold-shell');
      if (!contract || !shell) return {applied:false,reason:'not-original-scaffold'};
      const s=slots(shell); if (!s.inputs || !s.outputs || !s.functional) return {applied:false,reason:'original-slots-missing'};
      activeId=toolId; activeShell=shell; lastScope=api.scopeKey(context.getState());
      showScope(s,context.getState()); bindOriginalActions(main,s,contract);
      s.functional.textContent=contract.gap;
      s.outputs.innerHTML=items(['Loading existing API records…']);
      if (s.status) s.status.textContent='Read-only · loading';
      const result=await api.load(context,toolId);
      if (disposed || token!==generation || result.scope!==api.scopeKey(context.getState()) || shell!==root.document.querySelector('#main .scaffold-shell') || shell.isConnected===false) return {applied:false,reason:'stale'};
      s.outputs.innerHTML=result.sections.flatMap(section=>[
        '<li><b>'+esc(section.label)+'</b> · '+esc(section.status==='ready' ? section.count+' returned record(s)' : section.message)+'</li>',
        ...section.lines.map(line=>'<li>'+esc(line)+'</li>')
      ]).join('');
      if (s.status) s.status.textContent=contract.integration==='approved-skip'?'Integration skipped · metadata only':result.sections.some(x=>x.status!=='ready')?'Read-only · records unavailable':'Read-only · API records';
      shell.dataset.v1Scaffold=toolId;
      return {applied:true,...result};
    }
    const unsubscribe=typeof context.subscribe==='function' ? context.subscribe(event=>{
      const next=api.scopeKey(context.getState());
      if (next===lastScope && !['write','refresh','invalidate'].includes(event?.type)) return;
      lastScope=next;
      const id=activeId;
      ++generation;
      if (id && activeShell===root.document.querySelector('#main .scaffold-shell')) void apply(id);
    }) : null;
    function dispose() { disposed=true; ++generation; activeId=null; if(typeof unsubscribe==='function')unsubscribe(); }
    return {apply,dispose};
  }
  root.V1CloneScaffolds={install};
})(window);
