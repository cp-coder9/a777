(function (global) {
  'use strict';
  global.ArchitexToolRegistry = Object.freeze({ reconcile(local, remote) {
    const byId = new Map((remote || []).map(item => [item.id, item]));
    return Object.freeze(Object.keys(local).map(id => {
      const remoteTool=byId.get(id),access=remoteTool?.access;
      return Object.freeze({id,...local[id],deliveryStatus:remoteTool?.status||'unknown',recommended:remoteTool?.recommended===true,placement:remoteTool?.placement||null,
        access:Object.freeze({read:access?.read===true,write:access?.write===true,review:access?.review===true}),
        nativeActions:Object.freeze((remoteTool?.nativeActions||[]).map(action=>Object.freeze({...action}))),authorizationSource:'server_membership'});
    }));
  },nativeEntry(shell,id,orientation='project'){
    const tool=shell?.tools?.find(item=>item.id===id);
    if(tool?.access?.read!==true||!tool.nativeActions?.length||!['bom','safety','feedback','project_explorer','council_navigator','compliance_hub','municipal_tracker','approvals_queue','admin_review'].includes(id))return '';
    const mode=orientation==='standalone'?'standalone':'project';
    const project=/^[a-f0-9]{32}$/i.test(shell.selectedProjectId||'')&&mode==='project'?'&project='+encodeURIComponent(shell.selectedProjectId):'';
    return '/v1-candidate.php?workspace='+encodeURIComponent(id)+'&orientation='+mode+project;
  }});
  // The legacy shell uses the same authenticated native workspaces, rather than
  // leaving its nine limited IDs on disconnected sample/scaffold actions.
  global.addEventListener?.('DOMContentLoaded',()=>{
    const open=global.openTool;if(typeof open!=='function')return;
    function reconcileNavigation(){
      const shell=global.__ARCHITEX_SHELL_STATE__;if(!shell?.session)return;
      for(const button of global.document?.querySelectorAll('[onclick*="openTool("]')||[]){
        const id=/openTool\(['"]([a-z0-9_]+)['"]/.exec(button.getAttribute('onclick')||'')?.[1],tool=shell.tools?.find(item=>item.id===id);if(!tool)continue;
        const permitted=tool.access?.read===true;button.disabled=!permitted;button.setAttribute('aria-disabled',String(!permitted));button.dataset.serverToolAccess=String(permitted);
        button.title=permitted?(tool.recommended?'Suggested for your saved profile; source operations still verify authority.':'Available with this membership.'):'This workspace requires a different server membership scope.';
      }
    }
    if(global.document?.body&&global.MutationObserver)new global.MutationObserver(reconcileNavigation).observe(global.document.body,{childList:true,subtree:true});
    reconcileNavigation();
    global.openTool=function(id,orientation,...rest){
      const shell=global.__ARCHITEX_SHELL_STATE__;
      if(shell?.session){
        const tool=shell.tools?.find(item=>item.id===id);
        if(tool&&tool.access?.read!==true){global.showToast?.('This workspace is not available with your membership.');return false;}
        const href=global.ArchitexToolRegistry.nativeEntry(shell,id,orientation||(typeof state==='object'?state.mode:'project'));
        if(href){global.location.assign(href);return true;}
      }
      return open.call(this,id,orientation,...rest);
    };
  },{once:true});
})(window);
