(function(root){
  'use strict';
  let serial=0;
  function open(options,isPrompt){
    const doc=root.document,invoker=options.invoker||doc.activeElement;
    const ancestry=[];let owner=invoker?.ownerDocument;
    while(owner){const frame=owner.defaultView?.frameElement;ancestry.push({doc:owner,frame});if(!frame)break;owner=frame.ownerDocument;}
    const validInvoker=()=>!!invoker?.isConnected&&ancestry.every(({doc,frame})=>frame?frame.isConnected&&frame.contentDocument===doc:doc===root.document);
    const id='m3-dialog-'+(++serial),dialog=doc.createElement('dialog');
    dialog.className='m3-dialog';dialog.setAttribute('aria-labelledby',id+'-title');
    const heading=doc.createElement('h2');heading.id=id+'-title';heading.textContent=options.title;dialog.append(heading);
    const help=doc.createElement('p');help.id=id+'-help';help.textContent=options.help||options.message||'';dialog.append(help);dialog.setAttribute('aria-describedby',help.id);
    const form=doc.createElement('form');form.noValidate=true;dialog.append(form);
    let input,error;
    if(isPrompt){
      const label=doc.createElement('label');label.htmlFor=id+'-input';label.textContent=options.label;form.append(label);
      input=doc.createElement('textarea');input.id=label.htmlFor;input.required=!!options.required;if(options.maxLength)input.maxLength=options.maxLength;
      error=doc.createElement('p');error.id=id+'-error';error.setAttribute('role','alert');error.hidden=true;
      input.setAttribute('aria-describedby',help.id+' '+error.id);form.append(input,error);
    }
    const actions=doc.createElement('div');actions.className='m3-dialog-actions';form.append(actions);
    const cancel=doc.createElement('button');cancel.type='button';cancel.textContent=options.cancelLabel||'Cancel';actions.append(cancel);
    const submit=doc.createElement('button');submit.type='submit';submit.textContent=options.confirmLabel||(isPrompt?'Continue':'Confirm');submit.dataset.primary='';if(options.destructive)submit.dataset.destructive='';actions.append(submit);
    return new Promise(resolve=>{
      let settled=false,observer;const frameLoads=[];
      const finish=(value,restoreFocus=true)=>{if(settled)return;settled=true;observer?.disconnect();for(const {frame,callback} of frameLoads)frame.removeEventListener('load',callback);options.signal?.removeEventListener('abort',abort);root.removeEventListener('pagehide',abort);dialog.close();dialog.remove();if(restoreFocus&&!options.signal?.aborted&&validInvoker())invoker.focus();resolve(value);};
      const abort=()=>finish(isPrompt?null:false,false);
      const cancelByUser=()=>finish(isPrompt?null:false);
      cancel.onclick=cancelByUser;dialog.addEventListener('cancel',event=>{event.preventDefault();cancelByUser();});
      dialog.addEventListener('keydown',event=>{
        if(event.key!=='Tab')return;
        const controls=[input,cancel,submit].filter(Boolean),first=controls[0],last=controls.at(-1);
        if(event.shiftKey&&doc.activeElement===first){event.preventDefault();last.focus();}
        else if(!event.shiftKey&&doc.activeElement===last){event.preventDefault();first.focus();}
      });
      form.onsubmit=event=>{event.preventDefault();if(isPrompt&&options.required&&!input.value.trim()){error.textContent='Enter an inspection finding.';error.hidden=false;input.setAttribute('aria-invalid','true');input.focus();return;}finish(isPrompt?input.value:true);};
      options.signal?.addEventListener('abort',abort,{once:true});root.addEventListener('pagehide',abort,{once:true});
      doc.body.append(dialog);dialog.showModal();(input||cancel).focus();
      for(const {frame} of ancestry)if(frame){const callback=()=>{if(!validInvoker())abort();};frameLoads.push({frame,callback});frame.addEventListener('load',callback,{once:true});}
      observer=new root.MutationObserver(()=>{if(invoker&&!validInvoker())abort();});observer.observe(doc,{childList:true,subtree:true});
      if(options.signal?.aborted)abort();
    });
  }
  root.V1CloneM3Dialog={prompt:options=>open(options,true),confirm:options=>open(options,false)};
})(window);
