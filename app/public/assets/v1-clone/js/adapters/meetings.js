(function(root){
  'use strict';
  const escape=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const safe=v=>Array.isArray(v)?v.map(safe):v&&typeof v==='object'?Object.fromEntries(Object.entries(v).map(([k,x])=>[k,safe(x)])):typeof v==='string'?escape(v):v;
  const identity=v=>{if(!/^[a-f0-9]{32}$/i.test(v||''))throw Error('Invalid meeting identity.');return v;};
  const required=(v,label)=>{if(typeof v!=='string'||!v.trim())throw Error(label+' is required.');return v.trim();};
  const phases=['onboarding','feasibility','appointment','concept_design','design_development','municipal_submission','tender_procurement','construction_execution','closeout'];
  function create(context){
    const projectRequired=()=>{const s=context.getState();return s.mode==='standalone'||!s.selectedProjectId;};
    function scope(write=false){const s=context.getState();if(!s.ready||!s.session||!s.organisationId)throw Error('An authenticated project is required.');if(projectRequired())throw Error('Select an authorised project before using meetings.');identity(s.selectedProjectId);if(write&&!['owner','admin','member'].includes(s.session.memberships?.find(m=>m.organisationId===s.organisationId)?.role))throw Error('Meetings are read-only for this membership.');return s;}
    async function list(){const s=scope(),rows=await context.read('/meetings?projectId='+s.selectedProjectId);if(!Array.isArray(rows))throw Error('Invalid meetings response.');return rows.filter(m=>m.projectId===s.selectedProjectId);}
    async function write(path,payload){const s=scope(true);return context.write(path,{...payload,projectId:s.selectedProjectId},{projectScoped:true,readBackPath:saved=>'/meetings/'+identity(saved.id)});}
    async function schedule(p){scope(true);required(p.title,'Meeting title');required(p.meetingType,'Meeting type');if(!phases.includes(p.phase))throw Error('Choose a valid work stage.');required(p.startsAt,'Start time');if(!p.agenda?.length||!p.attendees?.length)throw Error('Agenda and attendees are required.');if(!p.policy||!Number.isInteger(p.durationMinutes))throw Error('Duration and policy are required.');return write('/meetings',p);}
    async function consent(id,attendeeIdentity,consented){scope(true);identity(id);required(attendeeIdentity,'Attendee');if(typeof consented!=='boolean')throw Error('Consent choice is required.');return write('/meetings/'+id+'/consents',{attendeeIdentity,consented});}
    async function start(id){scope(true);return write('/meetings/'+identity(id)+'/start',{});}
    async function conclude(id,p){scope(true);identity(id);if(!p.transcript?.length||!p.minutes?.length||!p.outcomes?.length)throw Error('Human-entered transcript, sourced minutes and outcomes are required.');return write('/meetings/'+id+'/conclude',p);}
    async function reviewOutcome(id,index,state,reason){scope(true);identity(id);required(reason,'Review reason');if(!Number.isInteger(index)||index<0||!['accepted','rejected'].includes(state))throw Error('Invalid outcome review.');return write('/meetings/'+id+'/outcomes/'+index+'/review',{state,reason});}
    async function publish(id,p){scope(true);identity(id);if(p.reviewerConfirmed!==true)throw Error('Human review confirmation is required.');required(p.publishReason,'Publication reason');return write('/meetings/'+id+'/minutes/publish',p);}
    function viewModel(rows,selectedId){const s=context.getState(),selected=rows.find(m=>m.id===selectedId),labels={scheduled:'Scheduled',live:'Started · media unavailable',review_required:'Review required',published:'Published'};
      const mapped=rows.map(m=>({...safe(m),time:escape(String(m.startsAt||'').slice(11,16)),date:escape(m.startsAt),type:escape(m.meetingType),stage:escape(m.phase),status:labels[m.status]||'Unknown',attendees:m.attendees?.length||0,actions:m.minutes?.outcomes?.filter(x=>x.type==='action'&&x.state==='accepted').length||0,project:escape(s.projects.find(p=>p.id===m.projectId)?.name||''),state:labels[m.status]||'Unknown',retention:'Minutes record · no media recording'}));
      return {meetings:mapped,upcoming:mapped,invitations:[],recordings:mapped.filter(m=>rows.find(r=>r.id===m.id)?.minutes),agenda:safe(selected?.agenda||[]),transcript:safe(selected?.minutes?.transcript||[]),minutes:safe(selected?.minutes?.content||[]),outcomes:safe(selected?.minutes?.outcomes||[])};
    }
    return {projectRequired,list,viewModel,schedule,consent,start,conclude,reviewOutcome,publish,canWrite:()=>{try{scope(true);return true;}catch{return false;}}};
  }
  root.V1CloneMeetingsAdapter={create,escape,phases};
})(window);
