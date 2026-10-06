(function (global) {
  'use strict';
  const endpoint = '/api/v1/wingman/tutorial';
  function install({context, navigate = step => {
    if (step.tool && typeof TOOLS === 'object' && Object.hasOwn(TOOLS, step.tool)) return global.openTool(step.tool, 'project');
    if (['command','projects','inbox','documents','finance','knowledge','settings','tools'].includes(step.route)) return global.openGlobal(step.route);
    throw Error('This lesson destination is unavailable. Use the dashboard navigation.');
  }}) {
    let tutorial = null, busy = false, disposed = false, generation = 0, message = '', scope = identity(), needsReload = false;
    const dialog = document.createElement('dialog'); dialog.className = 'v1-training'; dialog.setAttribute('aria-labelledby','v1-training-title');
    document.body.append(dialog);
    function identity() { const s=context.getState(); return JSON.stringify([s.organisationId,s.session?.userId,s.session?.memberships]); }
    function valid(token) { return !disposed && token === generation && scope === identity(); }
    function element(tag, text, parent = dialog) { const el=document.createElement(tag); if(text) el.textContent=text; parent.append(el); return el; }
    function button(text, action, parent = dialog) { const el=element('button',text,parent); el.className='btn dark'; el.type='button'; el.disabled=busy; el.onclick=action; return el; }
    function render() {
      if(disposed) return;
      const focused=document.activeElement?.dataset.trainingAction;
      dialog.replaceChildren();
      const header=element('header'); const title=element('h2',tutorial ? tutorial.label+' training' : 'Your role training',header); title.id='v1-training-title'; title.tabIndex=-1;
      const close=button('Close training',()=>dialog.close(),header);close.disabled=false;close.className='btn ghost';close.dataset.trainingAction='close';
      const status=element('p',message || (busy?'Loading your saved training…':''));status.setAttribute('role','status');status.setAttribute('aria-live','polite');
      if(!tutorial) { if(!busy)button('Retry',load); return; }
      const {progress,steps}=tutorial,step=steps[progress.step];
      const s=context.getState(), membership=s.session?.memberships?.find(m=>m.organisationId===s.organisationId);
      element('p','Authenticated profile: '+tutorial.label+' · Access: '+(membership?.role || 'Unavailable'));
      element('p','Your training follows your signed-in account. Changing the viewing lens does not change your permissions.');
      const meter=element('progress');meter.max=steps.length;meter.value=progress.status==='completed'?steps.length:progress.step;meter.setAttribute('aria-label','Saved lesson progress');
      element('p','Lesson '+(progress.step+1)+' of '+steps.length+' · '+(busy?'Saving…':'Saved progress: '+progress.status.replaceAll('_',' ')));
      if(needsReload) { button('Reload saved progress',load); return; }
      if(progress.status==='completed') {
        element('h3','Training complete');element('p','You have completed the lessons. This records training progress, not approval or completion of project work.');button('Replay training',()=>action('replay'));return;
      }
      element('h3',step.title);element('p',step.objective || step.body);
      if(step.objective)element('p',step.body);
      if(step.instructions?.length){const list=element('ol');step.instructions.forEach(text=>element('li',text,list));}
      if(step.expectedResult){element('h4','Check your result');element('p',step.expectedResult);}
      if(step.troubleshooting?.length){const details=element('details');element('summary','If you get stuck',details);const list=element('ul',null,details);step.troubleshooting.forEach(text=>element('li',text,list));}
      if(progress.status!=='in_progress') {button(progress.status==='new'?'Start training':'Resume training',()=>action('start'));return;}
      button('Open lesson workspace',async()=>{try{const result=await navigate(step);if(result===false)throw Error('Navigation was cancelled. Your training progress is unchanged.');dialog.close();}catch(error){message=error.message;render();}});
      const label=element('label');label.className='v1-training-checkpoint';const checkbox=element('input',null,label);checkbox.type='checkbox';checkbox.disabled=busy;
      element('span',step.checkpoint || 'I checked the expected result',label);
      const actions=element('footer');const back=button('Previous lesson',()=>action('back'),actions);back.className='btn ghost';back.disabled=busy||progress.step===0;
      const next=button(progress.step===steps.length-1?'Complete training':'Save and next lesson',()=>action(progress.step===steps.length-1?'finish':'next'),actions);next.disabled=true;
      checkbox.onchange=()=>next.disabled=busy||!checkbox.checked;
      button('Save and pause',()=>action('skip'),actions).className='btn ghost';
      if(focused)dialog.querySelector('[data-training-action="'+focused+'"]')?.focus();
    }
    async function load() {
      if(busy||disposed)return;busy=true;message='';const token=++generation;render();
      try {const result=await context.read(endpoint);if(!valid(token))return;check(result);tutorial=result;needsReload=false;}
      catch(error){if(valid(token))message='Could not load training. '+error.message;}
      finally{if(valid(token)){busy=false;render();}else reconcile();}
    }
    function check(result) {
      if(!result?.profile || !Number.isInteger(result.version) || !Array.isArray(result.steps) || !result.steps.length || !Number.isInteger(result.progress?.revision) || !result.steps[result.progress.step])throw Error('The saved training response is invalid. Reload before continuing.');
    }
    async function action(actionName) {
      if(busy||!tutorial||needsReload||disposed)return;
      busy=true;message='';const token=++generation,previous=tutorial;render();
      try {
        await context.write(endpoint,{action:actionName,revision:previous.progress.revision,profile:previous.profile,version:previous.version},{projectScoped:false,readBackPath:endpoint});
        const result=await context.read(endpoint);if(!valid(token))return;check(result);
        if(result.profile!==previous.profile||result.version!==previous.version||result.progress.revision<=previous.progress.revision)throw Error('Progress readback did not confirm your save.');
        tutorial=result;message='Progress saved.';
        if(actionName==='skip')dialog.close();
      } catch(error) {
        if(valid(token)){needsReload=true;message=error.code==='tutorial_revision_conflict'?'Progress changed in another session. Reload your saved progress before continuing.':'Your save could not be confirmed. Reload saved progress before retrying. '+error.message;}
      } finally {if(valid(token)){busy=false;render();if(dialog.open)dialog.querySelector('h2')?.focus();}else reconcile();}
    }
    function reconcile() {
      if(disposed)return;
      const next=identity();if(next!==scope){scope=next;++generation;tutorial=null;busy=false;needsReload=false;message='';render();if(dialog.open)void load();}
      const lens=document.querySelector('#roleSwitch select');
      if(lens){lens.id='productionRoleLens';lens.setAttribute('aria-label','Presentation lens (does not change account permissions)');
        if(typeof state==='object' && state.role==='viewer'){
          if(!lens.querySelector('option[value="viewer"]')){const option=document.createElement('option');option.value='viewer';option.textContent='Viewer';lens.append(option);}lens.value='viewer';
          const name=document.querySelector('.role-copy b');if(name)name.textContent='Viewer';
          const copy=document.querySelector('.role-copy span');if(copy)copy.textContent='Read authorised project information; request changes from the responsible team.';
        }
      }
      if(!document.querySelector('#v1-role-training')){
        const main=document.querySelector('#main');if(main){const entry=document.createElement('button');entry.id='v1-role-training';entry.type='button';entry.className='btn v1-training-entry';entry.textContent='Role training · Learn your workflows';entry.onclick=()=>{dialog.showModal();render();if(!tutorial)void load();};main.prepend(entry);}
      }
    }
    const unsubscribe=context.subscribe(reconcile);reconcile();
    // Native workspaces finish loading after shell reconciliation and replace main's children.
    const main=document.querySelector('#main');
    const observer=new MutationObserver(()=>{if(!document.querySelector('#v1-role-training'))reconcile();});
    if(main)observer.observe(main,{childList:true});
    return {reconcile,dispose(){disposed=true;++generation;observer.disconnect();unsubscribe?.();dialog.remove();document.querySelector('#v1-role-training')?.remove();}};
  }
  global.V1CloneTraining={install};
})(window);
