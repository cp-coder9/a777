(function(root){
  'use strict';
  // Presentation only: endpoint middleware and domain rules remain authoritative.
  // Explicit write controls avoid disabling search, downloads, navigation or feedback.
  const attributes = [
    'com-new-baseline','com-new-claim','com-new-schedule','com-new-variation','com-create-baseline','com-create-claim','com-create-schedule','com-create-variation','com-submit-variation','com-approve-variation','com-certify','com-release',
    'compliance-create','compliance-review','compliance-transition','rg-create','rg-act','sc-create','sc-act','sc-baseline',
    'cpd-start','cpd-submit','approvals-act','approvals-create-gate','closeout-act','closeout-assess-occupation','closeout-create-pack','closeout-create-pc',
    'doc-action','documents-create-toggle','documents-create-submit','proposals-calculate','proposals-confirm-submit',
    'proc-create-toggle','proc-create-submit','proc-addendum-submit','proc-invite','proc-invite-submit','proc-issue',
    'survey-create','survey-observation','survey-deliverable','survey-fee','survey-issue','survey-submit',
    'models-create','models-add-revision','models-decide','models-issue','reg-create','reg-act','explorer-create',
    'mun-create','mun-approve','mun-signoff','mun-submit','inbox-apply','dir-invite',
    'refuse-save','refuse-save-rule','refuse-declare','refuse-delete','refuse-verify','refuse-withdraw',
    'records-create','record-apply','record-delete','v1-scaffold-write'
  ];
  const selector=attributes.map(name=>'button[data-'+name+'],input[type="submit"][data-'+name+']').join(',');
  function install({context}){
    const main=root.document.querySelector('#main');if(!main)return {reconcile(){},dispose(){}};
    let disposed=false;
    function reconcile(){
      if(disposed)return;
      const notice=main.querySelector('[data-v1-read-only-notice]');
      if(context.getState().readOnly!==true){notice?.remove();return;}
      const controls=main.querySelectorAll(selector);
      controls.forEach(control=>{if(!control.disabled)control.disabled=true;});
      if(controls.length&&!notice){
        const message=root.document.createElement('p');message.className='panel panel-pad';message.dataset.v1ReadOnlyNotice='';message.setAttribute('role','status');
        message.textContent='Read-only access: you can review these records. Creating or changing records requires a member, administrator or owner permission.';
        main.prepend(message);
      }else if(!controls.length)notice?.remove();
    }
    function guard(event){
      if(context.getState().readOnly!==true)return;
      const target=event.target;
      const blocked=event.type==='submit'?target?.querySelector?.(selector):target?.closest?.(selector);
      if(blocked&&main.contains(blocked)){event.preventDefault();event.stopImmediatePropagation();reconcile();}
    }
    main.addEventListener('click',guard,true);main.addEventListener('submit',guard,true);
    const observer=new root.MutationObserver(reconcile);
    observer.observe(main,{childList:true,subtree:true,attributes:true,attributeFilter:['disabled']});
    const unsubscribe=context.subscribe?.(reconcile);reconcile();
    return {reconcile,dispose(){disposed=true;observer.disconnect();unsubscribe?.();main.removeEventListener('click',guard,true);main.removeEventListener('submit',guard,true);}};
  }
  root.V1ClonePermissionControls={install};
})(window);
