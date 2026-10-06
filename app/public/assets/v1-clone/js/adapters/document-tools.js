(function(root){
  'use strict';
  const unwrap=x=>x?.data??x;
  const escape=x=>String(x??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const safe=x=>Array.isArray(x)?x.map(safe):x&&typeof x==='object'?Object.fromEntries(Object.entries(x).map(([k,v])=>[k,safe(v)])):typeof x==='string'?escape(x):x;
  function create(context){
    function scope(){const s=context.getState();if(s.ready===false||!s.session||!s.organisationId||!/^[a-f0-9]{32}$/i.test(s.selectedProjectId||''))throw Error('Select an authenticated project.');return s;}
    function rows(x){x=unwrap(x);if(!Array.isArray(x))throw Error('Invalid document list response.');return x;}
    async function load(id){const s=scope(),p=s.selectedProjectId;if(id==='bom'){const runs=rows(await context.read('/toolbox/runs?limit=100'));return {available:true,boq:runs.filter(r=>r.toolId==='boq_takeoff'&&r.assignment?.projectId===p),boundaries:{qsCertification:false,tenderGeneration:false,exports:['json','csv','html','pdf'],reason:'BoQ takeoff runs and checksum-verified JSON, CSV, HTML and PDF artifacts persist in Toolbox. QS certification and tender generation are unavailable here.'}};}if(id==='specforge')return {workspaces:rows(await context.read('/projects/'+p+'/specforge-workspaces')).filter(w=>w.projectId===p)};if(id==='forms'){const [t,d]=await Promise.all([context.read('/form-templates'),context.read('/form-drafts?projectId='+p)]);return {templates:rows(t),drafts:rows(d).filter(x=>x.projectId===p)};}throw Error('Unsupported document tool.');}
    async function saveBoq({title,currency,contingencyPercent,items}){
      const s={...scope()};if(s.readOnly)throw Error('Read-only session.');
      const number=v=>v===null||v===undefined||String(v).trim()===''?NaN:Number(v);
      const contingency=number(contingencyPercent);
      if(!title?.trim()||!currency?.trim()||!Number.isFinite(contingency)||contingency<0||contingency>100)throw Error('Title, currency and valid contingency are required.');
      if(!Array.isArray(items)||items.length===0)throw Error('Enter at least one BoQ line item.');
      const lines=items.map(i=>{const quantity=number(i.quantity),rate=number(i.rate);if(!i.description?.trim()||!i.unit?.trim()||!Number.isFinite(quantity)||quantity<=0||!Number.isFinite(rate)||rate<0)throw Error('Invalid BoQ line: description, unit, positive quantity and non-negative rate are required.');return {description:i.description.trim(),unit:i.unit.trim(),quantity,rate};});
      if(writing)throw Error('A write is already pending.');writing=true;
      try{
        const body={toolId:'boq_takeoff',input:{title:title.trim(),currency:currency.trim(),contingencyPercent:contingency,items:lines},assignment:{mode:'internal_project',projectId:s.selectedProjectId},issueImmediately:false,issueReason:null};
        const saved=unwrap(await context.write('/toolbox/runs',body,{readBackPath:'/toolbox/runs?limit=100',projectScoped:true}));
        const history=rows(await context.read('/toolbox/runs?limit=100')),now=scope();
        if(now.session!==s.session||now.organisationId!==s.organisationId||now.selectedProjectId!==s.selectedProjectId)throw Error('Scope changed; reload to verify the BoQ.');
        const verified=history.find(r=>r.id===saved?.id&&r.toolId==='boq_takeoff'&&r.assignment?.projectId===s.selectedProjectId&&r.status==='completed');
        if(!verified)throw Error('BoQ persistence not verified; refresh history before retrying.');return verified;
      }finally{writing=false;}
    }
    async function downloadBoq(run,format){
      const s={...scope()};
      if(!['json','csv','html','pdf'].includes(format))throw Error('Unsupported advisory artifact format.');
      if(!run||run.toolId!=='boq_takeoff'||run.assignment?.projectId!==s.selectedProjectId||!['completed','issued','superseded'].includes(run.status))throw Error('Select a saved advisory BoQ from this project.');
      identifier(run.id);if(typeof context.download!=='function')throw Error('Verified binary downloads are not connected.');
      const list=rows(await context.read('/toolbox/runs/'+run.id+'/exports'));
      const matches=list.filter(item=>item.format===format),artifact=matches[0],mime={json:'application/json',csv:'text/csv',html:'text/html',pdf:'application/pdf'}[format];
      if(matches.length!==1||!artifact||artifact.mime_type!==mime||artifact.filename!=='boq_takeoff-'+run.id+'.'+format||!/^[a-f0-9]{64}$/.test(artifact.sha256||''))throw Error('Invalid advisory artifact metadata.');
      identifier(artifact.id);
      const result=await context.download('/toolbox/exports/'+artifact.id,{mimeType:mime,filename:artifact.filename,sha256:artifact.sha256});
      const now=scope();if(now.session!==s.session||now.organisationId!==s.organisationId||now.selectedProjectId!==s.selectedProjectId)throw Error('Scope changed while retrieving the advisory artifact.');return result;
    }
    function identifier(id){if(!/^[a-f0-9]{32}$/i.test(id||''))throw Error('A real record identifier is required.');return id;}
    function record(r){const s=scope();if(!r||r.projectId!==s.selectedProjectId)throw Error('Record belongs to a different project.');identifier(r.id);return s;}
    let writing=false;
    async function persist(path,payload,back,verification={}){if(writing)throw Error('A write is already pending.');const s=scope(),key=s.selectedProjectId+'|'+s.organisationId;writing=true;try{await context.write(path,payload,{...verification,readBackPath:back,projectScoped:true});const result=unwrap(await context.read(back));const n=scope();if(key!==n.selectedProjectId+'|'+n.organisationId)throw Error('Project changed; reload to verify the write.');return result;}finally{writing=false;}}
    async function specWrite(ws,action,payload,itemId){record(ws);if(ws.status!=='draft'||!Number.isInteger(ws.version)||ws.version<1)throw Error('A current draft version is required.');if(!['sections','items','decisions','issue'].includes(action))throw Error('Unavailable specification operation.');if(['decisions','issue'].includes(action)){if(payload.humanConfirmed!==true)throw Error('Human confirmation is required.');const reason=action==='issue'?payload.issueReason:payload.reason;const role=action==='issue'?payload.professionalRole:payload.actingRole;if(!reason?.trim()||!role?.trim())throw Error('A reason and verified acting role are required.');}if(action==='issue'&&(!ws.items?.length||ws.items.some(i=>i.status!=='approved')))throw Error('Issue blocked: all items must be approved.');if(action==='decisions'&&!ws.items.some(i=>i.id===itemId))throw Error('Item is not in this workspace.');const back='/specforge-workspaces/'+ws.id;const path=action==='decisions'?'/specforge-items/'+identifier(itemId)+'/decisions':back+'/'+action;const verification={readBackParentId:ws.id};if(action!=='issue')verification.readBackCollection=action==='sections'?'sections':'items';const result=await persist(path,{...payload,expectedVersion:ws.version},back,verification);if(result?.id!==ws.id||result.projectId!==ws.projectId||result.version<=ws.version)throw Error('Write readback could not verify the new workspace version.');return result;}
    async function saveForm({draft,template,values,reason}){const s=scope(),projectId=s.selectedProjectId,organisationId=s.organisationId;if(draft){record(draft);if(!reason?.trim())throw Error('A revision reason is required.');}identifier(template?.id);if(writing)throw Error('A write is already pending.');writing=true;try{const path=draft?'/form-drafts/'+draft.id+'/revisions':'/form-drafts';const payload=draft?{values,reason}:{projectId,templateId:template.id,title:template.name,values};const result=unwrap(await context.write(path,payload,{readBackPath:'/form-drafts?projectId='+projectId,projectScoped:true}));identifier(result?.id);const verified=unwrap(await context.read('/form-drafts/'+result.id));const n=scope();if(n.selectedProjectId!==projectId||n.organisationId!==organisationId||verified?.id!==result.id||verified?.projectId!==projectId)throw Error('Form readback failed or project changed.');return verified;}finally{writing=false;}}
    async function formTransition(draft,action,payload){record(draft);const from={'submit-review':'draft',approve:'review_required',issue:'approved'},to={'submit-review':'review_required',approve:'approved',issue:'issued'};if(!from[action]||draft.status!==from[action])throw Error('Form state does not allow this action.');if(!payload.reason?.trim()||(action!=='submit-review'&&payload.humanConfirmed!==true))throw Error('Explicit confirmation and reason required.');const back='/form-drafts/'+draft.id;const result=await persist(back+'/'+action,payload,back);if(result?.id!==draft.id||result.projectId!==draft.projectId||result.status!==to[action])throw Error('Form transition readback failed.');return result;}
    async function exports(draft){record(draft);if(draft.status!=='issued')throw Error('Only issued forms have export artifacts.');return rows(await context.read('/form-drafts/'+draft.id+'/exports'));}
    async function exportArtifact(draft,format){if(!['html','json'].includes(format))throw Error('PDF and digital signature providers are unavailable.');const list=await exports(draft),match=list.find(e=>e.format===format);identifier(match?.id);const result=unwrap(await context.read('/form-exports/'+match.id));record(draft);if(result?.id!==match.id||result.formDraftId!==draft.id||result.format!==format||typeof result.content!=='string')throw Error('Export artifact readback failed.');return result;}
    return {load,scope,specWrite,saveForm,formTransition,exports,exportArtifact,saveBoq,downloadBoq};
  }
  root.V1CloneDocumentToolsAdapter={create,escape,safe};
})(window);
