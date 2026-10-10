(function (global) {
  'use strict';
  // Serialized into the trusted original source. No CSS or original control labels are replaced.
  function frameBootstrap() {
    if (window.__v1Wingman) return;
    const d=document, q=s=>d.querySelector(s), qa=s=>Array.from(d.querySelectorAll(s));
    const preview=q('.editable-preview');
    const status=q('.hero .sub');status.id='v1-wingman-status';status.setAttribute('role','status');status.setAttribute('aria-live','polite');
    function notice(message){status.textContent=message;}
    const limitation='AI drafts require human review. Only returned citations are evidence; no compliance certification, approval, record creation or external dispatch is performed.';
    notice('Loading authenticated Wingman. '+limitation);
    const messages=q('.messages-area');
    // Retain the exact editable preview and its controls, removing the fabricated conversation around it.
    const previewRow=preview.closest('.message');
    preview.parentElement.replaceChildren(preview);
    previewRow.querySelector('.message-footer').textContent='Unsaved preview · human review required';
    previewRow.remove();messages.replaceChildren();messages.append(previewRow);
    qa('.editable-field .value').forEach(e=>{e.textContent='';});
    qa('.thread-item').forEach(e=>e.remove());
    qa('.import-form input,.import-form textarea').forEach(e=>{e.value='';e.removeAttribute('value');if(e.tagName==='TEXTAREA')e.textContent='';});
    qa('.import-form select').forEach(e=>e.selectedIndex=0);
    const heading=q('.conversation-header h3'), meta=q('.conversation-header .meta');
    heading.textContent='New conversation';meta.textContent='No verified conversation loaded';
    const pills=qa('.hero-pills .pill');pills[0].textContent='Awaiting authenticated context';pills[1].textContent='Quota not supplied by API';pills[2].textContent='No project selected';
    const projectItems=qa('.tool-nav-section:last-child .tool-nav-item');
    projectItems.forEach(e=>e.textContent='Not supplied by authenticated context');
    q('.empty-state p').textContent=limitation;
    const input=q('.input-field'), capability=q('.capability-selector'), send=q('.input-area button');
    const invoke=(action,payload)=>action==='send'&&send.disabled ? notice('Wingman is not available for sending. Your draft has been kept.') : window.__v1WingmanCommand ? window.__v1WingmanCommand(action,payload) : notice('Authenticated Wingman adapter is not mounted. Your draft has been kept.');
    let dirty=false, providerConfigured=false, requestPending=false;
    send.disabled=true;
    const originalControls=qa('button,.tool-nav-item,.os-nav-icon');
    originalControls.forEach(control=>{const label=control.textContent.trim();if(label.includes('Finalise')||label==='Import with Provenance'){control.disabled=true;control.title=label.includes('Finalise')?'Unavailable in Wingman. Use the governed RFI workflow to create and issue records.':'Unavailable: a governed provenance import service is not connected.';}});
    // Capture prevents later reference adaptFrame handlers from running prototype behaviours.
    d.addEventListener('click',event=>{
      const el=event.target.closest('button,.tool-nav-item,.os-nav-icon,.thread-item');if(!el)return;
      event.preventDefault();event.stopImmediatePropagation();
      const label=el.textContent.trim();
      if(el===send)return invoke('send',{prompt:input.value,capability:capability.value});
      if(el.dataset.routeSuggestion)return invoke('navigate',{id:el.dataset.routeSuggestion,route:el.dataset.routeTarget});
      if(el.dataset.conversationId)return invoke('load',{id:el.dataset.conversationId});
      if(label==='+ New'||label==='Start New Conversation')return invoke('new');
      if(label.includes('Finalise'))return notice('RFI Register finalisation is not exposed by Wingman. Keep this as an unapproved draft and use the governed RFI workflow.');
      if(label.includes('Discard')){if(window.confirm('Discard this unapproved preview?')){qa('.editable-field .value').forEach(e=>e.textContent='');dirty=false;}return;}
      if(label==='✎ Edit'){q('.editable-field [contenteditable]')?.focus();return;}
      if(label==='Cancel'){qa('.import-form input,.import-form textarea').forEach(e=>e.value='');qa('.import-form select').forEach(e=>e.selectedIndex=0);return;}
      if(label==='Import with Provenance')return notice('BYOAI import requires a governed provenance import endpoint. It is not exposed by Wingman; the content remains an unsaved draft.');
      if(label.includes('Import (BYOAI)'))return q('.import-form').scrollIntoView({block:'center'});
      if(label.includes('Provenance Audit')){messages.scrollIntoView({block:'center'});return notice('Provenance is shown only from persisted message citations. There is no Wingman audit-list endpoint. '+limitation);}
      if(label.includes('Conversations'))return q('.copilot-layout').scrollIntoView({block:'start'});
      const choices={'Draft RFI':'Draft RFI','Status Summary':'Summarise Status','Flag Compliance':'Flag Compliance','Narratives':'Generate Narrative','Explain Clause':'Explain Clause'};
      const choice=Object.keys(choices).find(key=>label.includes(key));
      if(choice){capability.value=choices[choice];input.focus();return;}
      notice('This original navigation control has no authenticated Wingman route binding. Use the parent workspace navigation.');
    },true);
    d.addEventListener('keydown',event=>{if(event.target===input&&event.key==='Enter'&&!event.isComposing){event.preventDefault();event.stopImmediatePropagation();invoke('send',{prompt:input.value,capability:capability.value});}},true);
    d.addEventListener('input',event=>{if(event.target.closest('.editable-preview'))dirty=true;});
    function draft(){return {prompt:input.value,capability:capability.value,preview:qa('.editable-field .value').map(e=>e.textContent),dirty,imports:qa('.import-form input,.import-form textarea,.import-form select').map(e=>e.value)};}
    function restore(value){if(!value)return;input.value=value.prompt;capability.value=value.capability;qa('.editable-field .value').forEach((e,i)=>e.textContent=value.preview[i]||'');dirty=value.dirty;qa('.import-form input,.import-form textarea,.import-form select').forEach((e,i)=>e.value=value.imports[i]||'');}
    function clear(){input.value='';dirty=false;qa('.editable-field .value').forEach(e=>e.textContent='');qa('.import-form input,.import-form textarea').forEach(e=>e.value='');qa('.import-form select').forEach(e=>e.selectedIndex=0);messages.querySelectorAll('[data-persisted-message]').forEach(e=>e.remove());heading.textContent='New conversation';meta.textContent='No verified conversation loaded';qa('.thread-item').forEach(e=>e.remove());}
    function render(record){
      messages.querySelectorAll('[data-persisted-message]').forEach(e=>e.remove());
      for(const item of record.messages){
        const row=d.createElement('div');row.className='message '+(item.role==='user'?'user':'assistant');row.dataset.persistedMessage=item.id;
        const avatar=d.createElement('div');avatar.className='message-avatar';avatar.textContent=item.role==='user'?'You':'◈';
        const content=d.createElement('div'),bubble=d.createElement('div');bubble.className='message-bubble';bubble.textContent=item.content;
        const footer=d.createElement('div');footer.className='message-footer';footer.textContent=[item.status,item.model,item.createdAt].filter(Boolean).join(' · ');
        content.append(bubble,footer);
        for(const citation of item.citations||[]){const el=d.createElement('div');el.className='provenance-badge';el.textContent=[citation.title,citation.sourceReference,citation.excerpt].filter(Boolean).join(' · ');content.append(el);}
        for(const suggestion of item.suggestions||[]){const el=d.createElement('div');el.className='gap-item';el.textContent=[suggestion.label,suggestion.reason,suggestion.routeTarget?'Suggested destination: '+suggestion.routeTarget:''].filter(Boolean).join(' · ');content.append(el);
          if(suggestion.type==='navigate'&&suggestion.status==='proposed'&&/^[a-f0-9]{32}$/i.test(suggestion.id||'')&&/^[a-z][a-z0-9_-]*$/.test(suggestion.routeTarget||'')){const open=d.createElement('button');open.type='button';open.textContent=suggestion.label||'Open suggested workspace';open.dataset.routeSuggestion=suggestion.id;open.dataset.routeTarget=suggestion.routeTarget;el.append(open);}
        }
        if(item.role==='assistant'){const warning=d.createElement('div');warning.className='disclaimer-tag';warning.textContent=(item.citations?.length?'Review the cited sources. ':'No source citations returned. ')+limitation;content.append(warning);}
        row.append(avatar,content);messages.insertBefore(row,previewRow);
      }
      heading.textContent='Verified conversation';meta.textContent='Conversation '+record.id;
    }
    window.__v1Wingman={notice,draft,restore,clear,render,
      previewReply(record,selected){
        if(selected!=='Draft RFI'||dirty)return;
        const reply=[...record.messages].reverse().find(m=>m.role==='assistant'&&m.status==='complete');
        if(!reply)return;
        const fields=qa('.editable-field .value');fields[3].textContent=reply.content;
        fields[4].textContent=(reply.citations||[]).map(c=>c.sourceReference||c.title).join('\n');
        dirty=true;
      },
      hasDraft:()=>!!(input.value.trim()||dirty||qa('.import-form input,.import-form textarea').some(e=>e.value.trim())),
      availability(value){providerConfigured=value?.configured??null;send.disabled=requestPending||providerConfigured===false;},
      busy(value){requestPending=value;send.disabled=value||providerConfigured===false;input.readOnly=value;capability.disabled=value;},
      clearSent(prompt){if(input.value===prompt)input.value='';},
      scope(state){const project=state.projects?.find(p=>p.id===state.selectedProjectId);const name=project?.name||'Standalone context';pills[0].textContent=state.ready?'Authenticated context':'Context unavailable';pills[2].textContent=name;projectItems[0].textContent=name;projectItems[1].textContent='Phase: '+(project?.phase||project?.currentPhase||project?.stage||'Not supplied');projectItems[2].textContent='Role: '+(state.session?.memberships?.find(m=>m.organisationId===state.organisationId)?.role||'Not supplied');},
      threads(items){qa('.thread-item').forEach(e=>e.remove());for(const item of items){const el=d.createElement('div');el.className='thread-item';el.dataset.conversationId=item.id;el.tabIndex=0;el.setAttribute('role','button');el.textContent=item.title;el.onkeydown=e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();invoke('load',{id:item.id});}};q('.thread-panel-header').after(el);}},
      controlCount:originalControls.length,
    };
  }
  function install({context}) {
    const adapter=global.V1CloneWingmanAdapter.create({context}), drafts=new Map(), mounts=new Set();
    let disposed=false;
    async function mountFrame(id,frame){
      if(id!=='wingman'||disposed)return;
      // The shell's body poll can beat stylesheet-blocked tail scripts. The
      // prepared source owns bootstrap; never recreate it through CSP-forbidden eval.
      const document=frame.contentDocument;
      if(document?.readyState==='loading')await new Promise(resolve=>document.addEventListener('DOMContentLoaded',resolve,{once:true}));
      if(disposed||frame.isConnected===false||frame.contentDocument!==document)return false;
      const win=frame.contentWindow;
      if(!win?.__v1Wingman)throw Error('Wingman frame was not prepared before srcdoc.');
      const view=win.__v1Wingman;let scope=adapter.scopeKey(),live=true,revision=0,commandPending=false,suggestions=[];
      view.scope(context.getState());view.restore(drafts.get(scope));
      const valid=ticket=>live&&!disposed&&ticket===revision&&scope===adapter.scopeKey();
      async function refresh(){const ticket=revision;try{const data=await adapter.bootstrap();if(!valid(ticket))return;view.threads(data.conversations);view.availability(data.availability);view.notice(data.availability?.configured===false?'Wingman AI is unavailable: provider configuration required. Review saved conversations or keep a draft; no AI request has been sent.':'Authenticated Wingman ready. '+(data.availability?.configured===true?'Provider configured; a successful response is verified only after a request. ':'Provider configuration was not reported. ')+(data.withheldConversations?'Unverified conversation history is withheld. ':'')+'Only cited sources are evidence; all outputs require human review.');}catch(e){if(valid(ticket)){view.availability({configured:false});view.notice(e.message);}}}
      function show(record){suggestions=record.messages.flatMap(message=>message.suggestions||[]);view.render(record);}
      win.__v1WingmanCommand=async(action,payload={})=>{
        if(!live||disposed)return;
        if(commandPending||adapter.isBusy()){view.notice('A Wingman request is pending.');return;}
        const ticket=++revision;if(!valid(ticket))return;
        commandPending=true;
        try{
          if(action==='send'){
            view.busy(true);view.notice('Waiting for configured provider and persisted conversation readback.');
            const result=await adapter.send(payload);if(!valid(ticket))return;show(result);view.previewReply(result,payload.capability);view.clearSent(payload.prompt);drafts.set(scope,view.draft());await refresh();
          }else if(action==='load'){
            if(view.hasDraft()&&!win.confirm('Discard the current unsaved draft and open this conversation?'))return;
            const before=JSON.stringify(view.draft());view.busy(true);
            const result=await adapter.load(payload.id);if(valid(ticket)){if(JSON.stringify(view.draft())===before)view.clear();show(result);await refresh();}
          }else if(action==='navigate'){
            const suggestion=suggestions.find(item=>item.id===payload.id&&item.routeTarget===payload.route&&item.type==='navigate'&&item.status==='proposed');
            if(!suggestion)throw Error('This navigation proposal is not part of the verified conversation.');
            let accepted;
            if(typeof TOOLS==='object'&&Object.hasOwn(TOOLS,payload.route))accepted=await global.openTool(payload.route,context.getState().selectedProjectId?'project':'standalone');
            else if(['command','projects','tools','inbox','documents','knowledge','finance','feedback','settings'].includes(payload.route))accepted=await global.openGlobal(payload.route);
            else throw Error('This suggested destination is unavailable in this dashboard. Use the workspace navigation.');
            if(accepted===false)throw Error('Navigation was cancelled. Your draft has been kept.');
          }else if(action==='new'){
            if(!win.confirm('Start a new conversation? Unsaved drafts will be discarded. A previous failed request may already be stored.'))return;
            adapter.newConversation();suggestions=[];view.clear();drafts.delete(scope);await refresh();
          }
        }catch(e){if(valid(ticket))view.notice(e.message);}finally{commandPending=false;if(valid(ticket))view.busy(false);}
      };
      const unregister=context.registerDraftGuard?.(()=>{
        if(adapter.isBusy())return false;
        if(view.hasDraft()){if(!win.confirm('Leave this Wingman draft? It will be kept only in memory for this project.'))return false;drafts.set(scope,view.draft());}
        return true;
      });
      const unsubscribe=context.subscribe?.(()=>{
        const next=adapter.scopeKey();if(next===scope)return;
        drafts.set(scope,view.draft());scope=next;++revision;suggestions=[];view.clear();view.busy(false);view.scope(context.getState());view.restore(drafts.get(scope));void refresh();
      });
      const mount={dispose(){if(!live)return;drafts.set(scope,view.draft());live=false;++revision;unregister?.();unsubscribe?.();win.__v1WingmanCommand=()=>view.notice('Wingman frame is no longer active. Reopen it.');mounts.delete(mount);}};
      mounts.add(mount);void refresh();return mount;
    }
    return {supports:id=>id==='wingman',prepareSource(id,html){return id==='wingman'?html.replace(/<\/body>/i,'<script data-v1-wingman>('+frameBootstrap.toString()+')();<\/script></body>'):html;},mountFrame,
      dispose(){disposed=true;for(const mount of [...mounts])mount.dispose();drafts.clear();adapter.dispose();}};
  }
  global.V1CloneWingman={install};
})(window);
