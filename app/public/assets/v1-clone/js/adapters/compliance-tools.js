(function(root){
 'use strict';
 const ids=['planning','municipal','xa'];
 const tools=['fenestration_xa_calculator','r_value_thermal_calculator'];
 const escape=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const safe=v=>Array.isArray(v)?v.map(safe):v&&typeof v==='object'?Object.fromEntries(Object.entries(v).map(([k,x])=>[k,safe(x)])):typeof v==='string'?escape(v):v;
 const unwrap=v=>v?.data??v;
 const valid=v=>typeof v==='string'&&/^[a-f0-9]{32}$/i.test(v);
 function create(context){
  function scope(){const s=context.getState();if(!s.ready||!s.session||!s.organisationId||!valid(s.selectedProjectId))throw Error('Select an authenticated project in the parent workspace.');return s;}
  function path(id){const s=scope();if(!ids.includes(id))throw Error('Unsupported compliance tool.');return id==='xa'?'/api/v1/toolbox/runs?limit=100':`/api/v1/projects/${s.selectedProjectId}/${id==='planning'?'planning-applications':'submission-readiness'}`;}
  function scoped(id,rows,s){return rows.filter(r=>(id==='municipal'?r.project?.id:id==='xa'?r.assignment?.projectId:r.projectId)===s.selectedProjectId&&(id!=='xa'||tools.includes(r.toolId)));}
  async function list(id){const s=scope(), rows=unwrap(await context.read(path(id)));if(!Array.isArray(rows))throw Error('Invalid compliance records response.');if(scope().selectedProjectId!==s.selectedProjectId||scope().organisationId!==s.organisationId)throw Error('Project scope changed; reload records.');return scoped(id,rows,s);}
  function viewModel(id,rows){const clean=safe(scoped(id,rows,scope()));return id==='planning'?{applications:clean,entries:clean.flatMap(a=>a.entries||[])}:id==='municipal'?{assessments:clean,latest:clean[0]||null}:{runs:clean};}
  const pick=(input,keys)=>Object.fromEntries(keys.filter(k=>Object.hasOwn(input,k)).map(k=>[k,input[k]]));
  async function operate(id,operation,input={}){
   const s=scope();let target,back,payload;const verification={};
   if(id==='planning'&&operation==='create'){
    target=back=path(id);payload=pick(input,['applicationNumber','applicationType','title','municipality','propertyReference','responsibleRole','sourceBasis']);
   }else if(id==='planning'&&['entry','submit','stage'].includes(operation)){
    const app=(await list(id)).find(a=>a.id===input.applicationId);if(!app)throw Error('Application does not belong to the selected project.');
    back='/api/v1/planning-applications/'+app.id;target=back+'/'+({entry:'entries',submit:'submit',stage:'stages'}[operation]);
    payload={...pick(input,operation==='entry'?['entryType','title','status','required','basis','dueAt','amountCents','payload']:['humanConfirmed','professionalRole','reason','externalReference','toStage']),expectedVersion:app.version};
    if(operation==='entry')Object.assign(verification,{readBackCollection:'entries',readBackParentId:app.id,retryPrecondition:'expectedVersion'});
    if(operation!=='entry'&&payload.humanConfirmed!==true)throw Error('Explicit human confirmation is required; this does not assert authority acceptance.');
   }else if(id==='municipal'&&operation==='assess'){
    target=back=path(id);payload=pick(input,['matrixVersionId','occupancyType','changesLoadBearing','changesDrainageOrStormwater','publicAccessOrAssembly','envelopeEnergyImpact','zoningKnown','coverageOrParkingRisk','boundaryOrServitudeUnclear','heritagePotential','environmentalSensitivity','trafficImpact','nbrAdvisoryReviewed','clientAuthorityConfirmed','estimatedConstructionValueZar','availableSupportingDocuments','professionalSignoffs']);
   }else if(id==='municipal'&&operation==='review'){
    const row=(await list(id)).find(a=>a.id===input.assessmentId);if(!row)throw Error('Assessment does not belong to the selected project.');if(input.humanConfirmed!==true)throw Error('Explicit human confirmation is required.');
    back='/api/v1/submission-readiness/'+row.id;target=back+'/approve';payload=pick(input,['humanConfirmed','reviewReason']);
   }else if(id==='xa'&&operation==='calculate'){
    if(!tools.includes(input.toolId))throw Error('XA calculator is not supported.');target='/api/v1/toolbox/runs';back=path(id);payload={toolId:input.toolId,input:input.input,assignment:{mode:'internal_project',projectId:s.selectedProjectId,notes:''},issueImmediately:false,issueReason:null};
   }else throw Error(`${id}: ${operation} is not supported by this embedded control. No record was changed.`);
   if(scope().selectedProjectId!==s.selectedProjectId||scope().organisationId!==s.organisationId)throw Error('Project scope changed; retry.');
   return context.write(target,payload,{...verification,readBackPath:back,projectScoped:true});
  }
  return {list,path,viewModel,operate};
 }
 root.V1CloneComplianceToolsAdapter={create,escape,safe,valid,tools};
})(window);
