(function(root){
  'use strict';
  // Approvals Queue adapter — the operating-system decision queue plus deployment
  // release-gate evidence. Backed by ProductOperatingSystemService (inbox_items
  // transitions and the operating snapshot), InboxService (GET /inbox) and
  // DeploymentReleaseGateService (deployment_release_gates).
  //
  // Honest boundary, two parts:
  // 1. The queue is NOT approvals-only. The operating snapshot deliberately emits
  //    missing-record tasks (id 'missing-<projectId>-<recordType>', status blocked)
  //    alongside real inbox items and record reviews. They are surfaced and labelled,
  //    never presented as pending approvals.
  // 2. A release gate records deployment evidence for human review. It does not deploy,
  //    promote, approve or roll back a release, and the API exposes no action that does.
  const BOUNDARY='Decision queue and deployment release-gate evidence only — this queue includes missing-record tasks and is not an approvals-only register; recording a release gate does not deploy, promote or approve a release.';
  const RELEASE_GATE_ACTIONS_SUPPORTED=false;
  const TRANSITIONS=['read','resolved','dismissed'];
  const HUMAN_CONFIRMED_TRANSITIONS=['resolved','dismissed'];
  const ENVIRONMENTS=['staging','production'];
  const COMMIT=/^[a-f0-9]{40}$/i;
  const unwrap=r=>r?.data??r;
  function create(context){
    function scope(){const s=context.getState();if(!s.ready||!s.session||!s.organisationId||!/^[a-f0-9]{32}$/i.test(s.selectedProjectId||''))throw Error('Select an authenticated project.');return {...s};}
    const path=(s,name)=>'/projects/'+encodeURIComponent(s.selectedProjectId)+'/'+name;
    function current(s){const now=context.getState();if(!now.ready||!now.session||now.selectedProjectId!==s.selectedProjectId||now.organisationId!==s.organisationId||now.session!==s.session)throw Error('Scope changed; reload this workspace.');}
    function identity(value,label){if(!/^[a-f0-9]{32}$/i.test(String(value||'')))throw Error('A valid '+label+' id is required.');return String(value).toLowerCase();}
    function projectItems(data,s){if(!Array.isArray(data))throw Error('Invalid inbox response.');return data.filter(r=>r&&typeof r==='object'&&(!r.projectId||r.projectId===s.selectedProjectId));}
    function decisions(data){if(!Array.isArray(data))throw Error('Invalid operating snapshot.');const list=data.filter(d=>d&&typeof d==='object');return list.map(d=>({...d,kind:decisionKind(d)}));}
    // A missing-record task is not an approval. Label it so the UI can never imply otherwise.
    function decisionKind(decision){const id=String(decision?.id||'');if(id.startsWith('missing-'))return 'missing_record';if(id.startsWith('record-'))return 'record_review';if(id.startsWith('risk-')||decision?.inboxType==='risk_warning')return 'risk_warning';return 'inbox_action';}
    function isApproval(decision){return decisionKind(decision)==='approval'||decision?.inboxType==='approval_required';}

    async function readInbox(s){const data=unwrap(await context.read('/inbox'));current(s);return projectItems(data,s);}
    async function readGates(s){const data=unwrap(await context.read('/release-gates'));current(s);if(!Array.isArray(data))throw Error('Invalid release-gate response.');return data;}

    // Real inbox rows for the selected project only (GET /api/v1/inbox is organisation-wide).
    async function listInbox(){
      const s=scope();current(s);
      const data=await readInbox(s);
      return data.map(r=>({id:r.id,projectId:r.projectId||null,eventType:r.eventType||null,title:r.title||null,detail:r.detail||null,priority:r.priority||null,status:r.status||null,humanGate:r.payload?.humanGate||null,payload:r.payload||null,createdAt:r.createdAt||null}));
    }
    // Operating-snapshot decisions for the project: inbox actions, record reviews,
    // advisory risks and missing-record tasks. Missing-record tasks are not approvals.
    async function listDecisions(){
      const s=scope();current(s);
      const snapshot=unwrap(await context.read(path(s,'operating-snapshot')));current(s);
      const list=decisions(snapshot?.decisions);
      return {
        projectId:s.selectedProjectId,
        decisions:list,
        missingRecordTasks:list.filter(d=>d.kind==='missing_record'),
        approvalsOnly:false,
        operatingState:snapshot?.operatingState||null,
        guardrails:snapshot?.guardrails||null,
        boundary:BOUNDARY,
      };
    }
    async function transitionInbox(inboxItemId,{status,reason,humanConfirmed=false}={}){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      const id=identity(inboxItemId,'inbox item');
      if(!TRANSITIONS.includes(status))throw Error('Unsupported inbox transition: '+status+'. Allowed: '+TRANSITIONS.join(', ')+'.');
      const sensitive=HUMAN_CONFIRMED_TRANSITIONS.includes(status);
      const text=typeof reason==='string'?reason.trim():'';
      if(sensitive&&humanConfirmed!==true)throw Error('Resolving or dismissing a decision requires explicit human confirmation.');
      if(sensitive&&!text)throw Error('A reason is required to resolve or dismiss a decision.');
      const body={status,reason:text||null};
      if(sensitive)body.humanConfirmed=true;
      unwrap(await context.write('/inbox/'+encodeURIComponent(id)+'/transition',body,{readBackPath:'/inbox'}));current(s);
      const data=await readInbox(s);
      const record=data.find(r=>r.id===id);
      if(!record||record.status!==status)throw Error('Inbox transition not verified; refresh before retrying.');
      return {id:record.id,status:record.status,eventType:record.eventType||null,humanGate:record.payload?.humanGate||null,humanConfirmed:sensitive,reason:text||null};
    }
    async function listReleaseGates(){
      const s=scope();current(s);
      const data=await readGates(s);
      return data.map(g=>({id:g.id,environment:g.environment,status:g.status,repoCommit:g.repoCommit,deployedCommit:g.deployedCommit,repoBranch:g.repoBranch,deployedBranch:g.deployedBranch,summary:g.summary,checks:Array.isArray(g.checks)?g.checks:[],dependencyVulnerabilityCount:g.dependencyVulnerabilityCount,createdAt:g.createdAt,releaseActionsSupported:RELEASE_GATE_ACTIONS_SUPPORTED}));
    }
    // Records deployment evidence for human review. No deploy/promote action exists in the
    // API, so none is offered; the returned gate never claims a release happened.
    async function createReleaseGate(input){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(input?.humanConfirmed!==true)throw Error('Recording a release gate requires explicit human confirmation.');
      if(!ENVIRONMENTS.includes(input?.environment))throw Error('A valid environment is required ('+ENVIRONMENTS.join(', ')+').');
      const repoBranch=String(input?.repoBranch||'').trim(),deployedBranch=String(input?.deployedBranch||'').trim();
      if(!repoBranch||!deployedBranch)throw Error('Both the reviewed and deployed branch names are required.');
      const repoCommit=String(input?.repoCommit||'').trim().toLowerCase(),deployedCommit=String(input?.deployedCommit||'').trim().toLowerCase();
      if(!COMMIT.test(repoCommit)||!COMMIT.test(deployedCommit))throw Error('Both the reviewed and deployed commits must be 40-character hex commits.');
      const apiMode=String(input?.apiMode||'').trim();if(!apiMode)throw Error('An API mode is required.');
      if(!input?.buildTimestamp)throw Error('A build timestamp is required.');
      for(const key of ['apiHealthOk','databaseReady','migrationsReady','mailReady'])if(typeof input?.[key]!=='boolean')throw Error('Release readiness flags must be true or false: '+key+'.');
      const routes=Array.isArray(input?.routeSmoke)?input.routeSmoke:[];
      if(routes.length===0)throw Error('At least one route smoke probe is required.');
      const routeSmoke=routes.map(probe=>{const route=String(probe?.route||'').trim();const status=probe?.status;if(!route||!Number.isInteger(status)||status<100||status>599)throw Error('Each route smoke probe needs a route and an HTTP status between 100 and 599.');return {route,status};});
      const vulnerabilities=input?.dependencyVulnerabilityCount;
      if(!Number.isInteger(vulnerabilities)||vulnerabilities<0)throw Error('A dependency vulnerability count (0 or more) is required.');
      const body={environment:input.environment,repoBranch,repoCommit,deployedBranch,deployedCommit,buildTimestamp:input.buildTimestamp,apiMode,apiHealthOk:input.apiHealthOk,databaseReady:input.databaseReady,migrationsReady:input.migrationsReady,mailReady:input.mailReady,routeSmoke,dependencyVulnerabilityCount:vulnerabilities,humanConfirmed:true};
      const saved=unwrap(await context.write('/release-gates',body,{readBackPath:'/release-gates'}));current(s);
      const gates=await readGates(s);
      const record=gates.find(g=>g.id===saved?.id);
      if(!record)throw Error('Release-gate persistence not verified; refresh before retrying.');
      return {id:record.id,environment:record.environment,status:record.status,summary:record.summary,checks:Array.isArray(record.checks)?record.checks:[],releaseActionsSupported:RELEASE_GATE_ACTIONS_SUPPORTED,humanConfirmed:true};
    }
    return {boundary:BOUNDARY,transitions:TRANSITIONS.slice(),environments:ENVIRONMENTS.slice(),releaseGateActionsSupported:RELEASE_GATE_ACTIONS_SUPPORTED,approvalsOnly:false,listInbox,listDecisions,transitionInbox,listReleaseGates,createReleaseGate};
  }
  root.V1CloneApprovalsAdapter={create,boundary:BOUNDARY,transitions:TRANSITIONS.slice(),environments:ENVIRONMENTS.slice(),releaseGateActionsSupported:RELEASE_GATE_ACTIONS_SUPPORTED,approvalsOnly:false};
})(window);
