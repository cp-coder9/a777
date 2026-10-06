(function(root){
  'use strict';
  const escape=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const identity=v=>{if(!/^[a-f0-9]{32}$/i.test(v||''))throw Error('Invalid record identity.');return v;};
  const required=(v,label)=>{if(typeof v!=='string'||!v.trim())throw Error(label+' is required.');return v.trim();};
  function create(context){
    const projectRequired=()=>{const s=context.getState();return s.mode==='standalone'||!s.selectedProjectId;};
    function scope(write=false){const s=context.getState();if(!s.ready||!s.session||!s.organisationId)throw Error('An authenticated project is required.');if(projectRequired())throw Error('Select an authorised project before using practice.');identity(s.selectedProjectId);if(write&&!['owner','admin','member'].includes(s.session.memberships?.find(m=>m.organisationId===s.organisationId)?.role))throw Error('Practice is read-only for this membership.');return s;}
    const path=()=>'/projects/'+scope().selectedProjectId+'/practice-workspace';
    async function workspace(){const result=await context.read(path());for(const k of ['tasks','pipelineItems','resourceAllocations','timesheets'])if(!Array.isArray(result?.[k]))throw Error('Invalid practice workspace: '+k);return result;}
    async function write(route,payload,collection){const s=scope(true);return context.write(route,{...payload,projectId:s.selectedProjectId},{projectScoped:true,readBackPath:path(),readBackCollection:collection});}
    const confirmed=p=>{if(p.humanConfirmed!==true)throw Error('Human confirmation is required.');};
    async function createTask(p){scope(true);required(p.title,'Task title');identity(p.assignedUserId);required(p.dueAt,'Due date');return write('/projects/'+scope().selectedProjectId+'/practice/tasks',p,'tasks');}
    async function transitionTask(id,p){scope(true);identity(id);confirmed(p);required(p.reason,'Transition reason');return write('/practice/tasks/'+id+'/transitions',p,'tasks');}
    async function captureTimesheet(p){scope(true);required(p.workstage,'Work stage');required(p.workDate,'Work date');required(p.description,'Description');if(!Number.isInteger(p.minutes)||p.minutes<1||p.minutes>1440||typeof p.billable!=='boolean')throw Error('Valid minutes and billable choice are required.');return write('/projects/'+scope().selectedProjectId+'/practice/timesheets',p,'timesheets');}
    async function allocateResource(p){scope(true);confirmed(p);identity(p.userId);required(p.startsOn,'Start date');required(p.endsOn,'End date');return write('/projects/'+scope().selectedProjectId+'/practice/resource-allocations',p,'resourceAllocations');}
    async function createProgramme(p){scope(true);required(p.title,'Workstream title');required(p.ownerRole,'Owner role');if(!Number.isInteger(p.weeks)||p.weeks<1)throw Error('A positive number of weeks is required.');if(!Number.isInteger(p.progress)||p.progress<0||p.progress>100)throw Error('Progress must be 0-100.');return write('/projects/'+scope().selectedProjectId+'/practice/programme',p,'programme');}
    async function updateProgramme(id,p){scope(true);identity(id);return write('/practice/programme/'+id,p,'programme');}
    async function createMilestone(p){scope(true);required(p.title,'Milestone title');required(p.targetDate,'Target date (YYYY-MM-DD)');return write('/projects/'+scope().selectedProjectId+'/practice/milestones',p,'milestones');}
    async function setMilestoneCompletion(id,completed){scope(true);identity(id);return write('/projects/'+scope().selectedProjectId+'/practice/milestones/'+id+'/completion',{completed:!!completed},'milestones');}
    async function saveFee(p){scope(true);return write('/projects/'+scope().selectedProjectId+'/practice/fees',p,'fees');}
    function viewModel(w){const s=context.getState(),name=id=>escape(id===s.session?.userId?s.session.email:id||'Unassigned');const fee=(w.fees||[])[0]||{};
      return {
      tasks:(w.tasks||[]).map(t=>[escape(t.title),({open:'To do',in_progress:'In progress',blocked:'Review',completed:'Done',cancelled:'Done'})[t.status]||'Review',name(t.assignedUserId),escape(t.dueAt),escape(t.priority?.replace(/^./,c=>c.toUpperCase()))]),
      programme:(w.programme||[]).map(p=>[escape(p.title),p.weeks,p.progress,escape(p.ownerRole),p.id]),
      milestones:(w.milestones||[]).map(m=>[escape(m.title),escape(m.targetDate),!!m.completed,m.id]),
      resources:(w.resourceAllocations||[]).map(r=>[name(r.userId),escape(r.startsOn+' – '+r.endsOn),r.capacityMinutes?Math.round(r.allocatedMinutes/r.capacityMinutes*100):0]),
      times:(w.timesheets||[]).map(t=>[name(t.userId),t.minutes/60,escape(t.workstage),null]),
      fee:{construction:fee.constructionCostCents!=null?fee.constructionCostCents/100:null,percent:fee.feePercent!=null?Number(fee.feePercent):null,planned:fee.plannedDeliveryCents!=null?fee.plannedDeliveryCents/100:null,invoiced:fee.invoicedCents!=null?fee.invoicedCents/100:null,expenses:fee.expensesCents!=null?fee.expensesCents/100:null},
    };}
    return {projectRequired,workspace,viewModel,createTask,transitionTask,captureTimesheet,allocateResource,createProgramme,updateProgramme,createMilestone,setMilestoneCompletion,saveFee,canWrite:()=>{try{scope(true);return true;}catch{return false;}}};
  }
  root.V1ClonePracticeAdapter={create,escape};
})(window);
