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
    'records-create','record-apply','record-delete','v1-scaffold-write','gov-review','gov-review-submit'
  ];
  const selector=attributes.map(name=>'button[data-'+name+'],input[type="submit"][data-'+name+']').join(',')+',[data-review-form] button[type="submit"]';
  const reviewSelector=['compliance-review','compliance-transition','gov-review','gov-review-submit'].map(name=>'button[data-'+name+'],input[type="submit"][data-'+name+']').join(',')+',[data-review-form] button[type="submit"]';
  function install({context}){
    const main=root.document.querySelector('#main');if(!main)return {reconcile(){},dispose(){}};
    let disposed=false;
    const owned=new WeakSet();
    function denied(control){const s=context.getState();const role=s.session?.memberships?.find(m=>m.organisationId===s.organisationId)?.role;
      return !s.ready||!['owner','admin','member'].includes(role)||s.readOnly===true||(control.matches(reviewSelector)&&!['owner','admin'].includes(role));
    }
    function reconcile(){
      if(disposed)return;
      const notice=main.querySelector('[data-v1-read-only-notice]');
      const controls=main.querySelectorAll(selector);
      let blocked=0;
      controls.forEach(control=>{if(denied(control)){blocked++;if(!control.disabled){owned.add(control);control.disabled=true;}}else if(owned.has(control)){owned.delete(control);control.disabled=false;}});
      if(blocked&&!notice){
        const message=root.document.createElement('p');message.className='panel panel-pad';message.dataset.v1ReadOnlyNotice='';message.setAttribute('role','status');
        message.textContent='Some actions are unavailable with this membership. Ordinary edits require write scope; independent verification and compliance review require administrator or owner scope. Professional issue still requires verified authority.';
        main.prepend(message);
      }else if(!blocked)notice?.remove();
    }
    function guard(event){
      const target=event.target;
      const blocked=event.type==='submit'?target?.querySelector?.(selector):target?.closest?.(selector);
      if(blocked&&denied(blocked)&&main.contains(blocked)){event.preventDefault();event.stopImmediatePropagation();reconcile();}
    }
    main.addEventListener('click',guard,true);main.addEventListener('submit',guard,true);
    const observer=new root.MutationObserver(reconcile);
    observer.observe(main,{childList:true,subtree:true,attributes:true,attributeFilter:['disabled']});
    const unsubscribe=context.subscribe?.(reconcile);reconcile();
    return {reconcile,dispose(){disposed=true;observer.disconnect();unsubscribe?.();main.removeEventListener('click',guard,true);main.removeEventListener('submit',guard,true);}};
  }
  root.V1ClonePermissionControls={install};
})(window);
