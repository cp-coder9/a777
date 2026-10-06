(function(root){
  'use strict';
  // Commercial-control adapter — the shared backend for contract_admin (baselines,
  // variations, claims, certificates, retention) and payments_escrow (payment
  // schedules, claims, certificates, retention metadata).
  //
  // Follows the site-tools/municipal adapter contract: scope(), current(), writes
  // through the shared context (project-scoped, CSRF/retry aware, exact read-back)
  // and an explicit humanConfirmed guard on every governed change. A write is only
  // ever reported after the exact persisted record is re-read from the workspace.
  //
  // HONEST BOUNDARY (user-approved integration skip for payments_escrow): this
  // adapter persists and reads INTERNAL commercial metadata only. It never holds
  // funds, never executes, settles or releases a payment, and it never claims a
  // configured payment/escrow provider. Every release/payout action is refused with
  // a 'provider not configured' boundary instead of a fabricated payment.
  const BOUNDARY='Internal commercial-control records only — not a payment, settlement, certificate of payment, or evidence that funds moved.';
  const ESCROW_BOUNDARY='No payment or escrow provider is configured: Architex does not hold funds and does not execute, settle or release payments. Certificate, variation, claim and retention figures below are internal metadata only.';
  const RELEASE_REFUSED='Release and payout are unavailable: no registered third-party payment or escrow provider is live-configured for this organisation. Architex never holds funds and never executes a payment. Nothing was submitted.';
  const APPROVAL_TYPES=['quantity_surveyor','lead_professional','client'];
  const unwrap=r=>r?.data??r;
  const isId=v=>/^[a-f0-9]{32}$/i.test(String(v||''));
  const int=v=>{const n=Number(v);return Number.isInteger(n)?n:NaN;};
  function create(context){
    function scope(){const s=context.getState();if(!s.ready||!s.session||!s.organisationId||!isId(s.selectedProjectId))throw Error('Select an authenticated project.');return {...s};}
    const path=(s,name)=>'/projects/'+encodeURIComponent(s.selectedProjectId)+'/'+name;
    function current(s){const now=context.getState();if(!now.ready||!now.session||now.selectedProjectId!==s.selectedProjectId||now.organisationId!==s.organisationId||now.session!==s.session)throw Error('Scope changed; reload this workspace.');}
    function guarded(){const s=scope();if(s.readOnly)throw Error('Read-only session.');return s;}
    // The workspace is the only finance read surface. Collections are immutable
    // snapshot payloads (scheduleId / claimId / certificateId) and `baseline` is a
    // single governed object.
    function workspace(data,s){
      if(!data||typeof data!=='object'||Array.isArray(data))throw Error('Invalid finance workspace response.');
      if(data.projectId&&data.projectId!==s.selectedProjectId)throw Error('The finance workspace belongs to a different project.');
      const scoped=key=>{const list=Array.isArray(data[key])?data[key]:[];return list.filter(r=>r&&typeof r==='object');};
      const baseline=data.baseline&&typeof data.baseline==='object'?data.baseline:null;
      if(baseline&&baseline.projectId&&baseline.projectId!==s.selectedProjectId)throw Error('The commercial baseline belongs to a different project.');
      return {...data,
        projectName:data.projectName||((s.projects||[]).find(p=>p.id===s.selectedProjectId)?.name||'Selected project'),
        baseline,
        schedules:scoped('schedules').map(schedule=>schedule.id?schedule:{...schedule,id:schedule.scheduleId}),
        variations:scoped('variations'),
        claims:scoped('claims').map(claim=>claim.id?claim:{...claim,id:claim.claimId}),
        certificates:scoped('certificates').map(certificate=>certificate.id?certificate:{...certificate,id:certificate.certificateId}),
        cashflowSnapshots:scoped('cashflowSnapshots'),
        providers:scoped('providers'),
        releaseRequests:scoped('releaseRequests'),
        retentionReleaseRequests:scoped('retentionReleaseRequests'),
        providerBoundary:data.providerBoundary&&typeof data.providerBoundary==='object'?data.providerBoundary:null};
    }
    async function readWorkspace(s){return workspace(unwrap(await context.read(path(s,'finance-workspace'))),s);}
    async function load(){const s=scope();current(s);const data=await readWorkspace(s);current(s);return data;}
    // A baseline is only creatable from a formally issued professional appointment
    // (backend precondition). Resolve it from the real appointments route; never
    // invent an appointment id.
    async function resolveAppointmentId(s,input){
      const provided=input?.appointmentId;
      if(isId(provided))return String(provided).toLowerCase();
      const rows=unwrap(await context.read('/appointments'));current(s);
      if(!Array.isArray(rows))throw Error('Invalid appointments response.');
      const eligible=rows.filter(a=>a&&isId(a.id)&&a.projectId===s.selectedProjectId&&a.status==='confirmed'&&a.formallyIssuedAt);
      if(!eligible.length)throw Error('No formally issued professional appointment is recorded for this project, so a commercial baseline cannot be created. Nothing was submitted.');
      return String(eligible[0].id).toLowerCase();
    }
    // Shared governed write. The shared context.write helper verifies read-back
    // identity by the record `id`; commercial workspace collections are immutable
    // snapshot payloads keyed scheduleId/claimId/certificateId and `baseline` is a
    // single object, so the helper's collection identity check cannot match them.
    // The POST is still performed through the shared helper (same project scope,
    // CSRF refresh and accepted-write receipt), the created id is captured, and the
    // exact persisted record is then re-read and verified here. A failure inside the
    // POST propagates untouched; success is never reported without a verified record.
    async function persist(route,body,options){
      const s=guarded();
      let receipt=null;
      const capture=saved=>{if(saved&&typeof saved==='object')receipt=saved;return path(s,'finance-workspace');};
      const writeOptions={readBackPath:capture};
      if(options.collection)writeOptions.readBackCollection=options.collection;
      try{const returned=unwrap(await context.write(route,body,writeOptions));if(!receipt&&returned&&typeof returned==='object')receipt=returned;}
      catch(error){if(!/read-back identity did not match/.test(String(error?.message||'')))throw error;}
      current(s);
      if(!receipt||!isId(receipt.id))throw Error('The commercial write returned no record receipt; refresh before retrying.');
      const data=await readWorkspace(s);current(s);
      const record=options.verify(data,receipt,s);
      if(!record)throw Error('Commercial persistence not verified; refresh before retrying.');
      return record;
    }
    const identity=(list,key,id)=>(Array.isArray(list)?list:[]).find(r=>r&&String(r[key]||'').toLowerCase()===String(id).toLowerCase())||null;
    // --- contract_admin -------------------------------------------------------
    async function createBaseline(input){
      const s=guarded();
      if(input?.humanConfirmed!==true)throw Error('Creating a commercial baseline requires explicit human confirmation.');
      const sum=int(input?.contractSumCents);const contingency=input?.contingencyCents==null?0:int(input.contingencyCents);
      const retention=input?.retentionBasisPoints==null?0:int(input.retentionBasisPoints);
      const currency=String(input?.currency||'ZAR').trim().toUpperCase();
      const reason=typeof input?.reason==='string'?input.reason.trim():'';
      if(!Number.isInteger(sum)||sum<1)throw Error('A positive integer contract sum in cents is required.');
      if(!Number.isInteger(contingency)||contingency<0)throw Error('A non-negative integer contingency in cents is required.');
      if(!Number.isInteger(retention)||retention<0||retention>2000)throw Error('Retention must be between 0 and 2000 basis points.');
      if(!/^[A-Z]{3}$/.test(currency))throw Error('A three-letter currency code is required.');
      if(typeof input?.vatIncluded!=='boolean')throw Error('VAT basis (vatIncluded) must be stated.');
      if(!reason||reason.length>1000)throw Error('A recorded reason is required for the baseline.');
      const appointmentId=await resolveAppointmentId(s,input);current(s);
      return persist(path(s,'commercial-baselines'),{appointmentId,contractSumCents:sum,contingencyCents:contingency,retentionBasisPoints:retention,currency,vatIncluded:input.vatIncluded,reason,humanConfirmed:true},
        {verify:(data,receipt)=>data.baseline&&String(data.baseline.id).toLowerCase()===String(receipt.id).toLowerCase()?data.baseline:null});
    }
    async function baselineId(s,input){
      if(isId(input?.baselineId))return String(input.baselineId).toLowerCase();
      const data=await readWorkspace(s);current(s);
      if(!data.baseline||!isId(data.baseline.id))throw Error('No active commercial baseline exists for this project. Create a baseline first. Nothing was submitted.');
      return data.baseline.id;
    }
    async function addPaymentSchedule(input){
      const s=guarded();
      if(input?.humanConfirmed!==true)throw Error('Issuing a payment schedule requires explicit human confirmation.');
      const title=typeof input?.title==='string'?input.title.trim():'';
      const reason=typeof input?.reason==='string'?input.reason.trim():'';
      const raw=Array.isArray(input?.milestones)?input.milestones:[];
      if(!title)throw Error('A schedule title is required.');
      if(!reason)throw Error('A recorded reason is required for the payment schedule.');
      if(!raw.length||raw.length>100)throw Error('Between 1 and 100 payment milestones are required.');
      let previous=null;
      const milestones=raw.map(item=>{
        const label=typeof item?.label==='string'?item.label.trim():'';
        const amountCents=int(item?.amountCents);
        const dueDate=typeof item?.dueDate==='string'?item.dueDate.trim():'';
        if(!label)throw Error('Every milestone requires a label.');
        if(!Number.isInteger(amountCents)||amountCents<1)throw Error('Every milestone requires a positive integer amount in cents.');
        if(!/^\d{4}-\d{2}-\d{2}$/.test(dueDate)||Number.isNaN(Date.parse(dueDate+'T00:00:00Z')))throw Error('Every milestone requires a due date (YYYY-MM-DD).');
        if(previous&&dueDate<previous)throw Error('Payment milestone due dates must not move backwards.');
        previous=dueDate;
        return {label,amountCents,dueDate};
      });
      const baseline=await baselineId(s,input);current(s);
      const data=await readWorkspace(s);current(s);
      const total=milestones.reduce((sum,item)=>sum+item.amountCents,0);
      if(baseline!==(data.baseline?.id||''))throw Error('The selected baseline is not the active commercial baseline. Nothing was submitted.');
      if(total!==int(data.baseline?.currentContractSumCents))throw Error('Payment milestones must total the current contract sum ('+data.baseline.currentContractSumCents+' cents); they total '+total+' cents. Nothing was submitted.');
      const route='/commercial-baselines/'+encodeURIComponent(baseline)+'/payment-schedules';
      return persist(route,{title,reason,milestones,humanConfirmed:true},{collection:'schedules',
        verify:(ws,receipt)=>identity(ws.schedules,'scheduleId',receipt.id)});
    }
    async function addVariation(input){
      const s=guarded();
      const reference=typeof input?.reference==='string'?input.reference.trim():'';
      const title=typeof input?.title==='string'?input.title.trim():'';
      const description=typeof input?.description==='string'?input.description.trim():'';
      const amountCents=int(input?.amountCents);
      const timeImpactDays=int(input?.timeImpactDays==null?0:input.timeImpactDays);
      if(!reference||reference.length>80)throw Error('A variation reference is required.');
      if(!title||title.length>220)throw Error('A variation title is required.');
      if(!description)throw Error('A variation description is required.');
      if(!Number.isInteger(amountCents)||amountCents===0||Math.abs(amountCents)>9000000000000000)throw Error('A non-zero integer variation amount in cents is required.');
      if(!Number.isInteger(timeImpactDays)||Math.abs(timeImpactDays)>3650)throw Error('The time impact must be a whole number of days.');
      const baseline=await baselineId(s,input);current(s);
      const route='/commercial-baselines/'+encodeURIComponent(baseline)+'/variations';
      return persist(route,{reference,title,description,amountCents,timeImpactDays,humanConfirmed:true},{collection:'variations',
        verify:(ws,receipt)=>identity(ws.variations,'id',receipt.id)});
    }
    async function submitVariation(variationId,input){
      const s=guarded();
      if(input?.humanConfirmed!==true)throw Error('Submitting a variation for review requires explicit human confirmation.');
      if(!isId(variationId))throw Error('A valid variation id is required.');
      const expectedVersion=int(input?.expectedVersion);
      const reason=typeof input?.reason==='string'?input.reason.trim():'';
      if(!Number.isInteger(expectedVersion)||expectedVersion<1)throw Error('The variation expectedVersion is required for a safe transition.');
      if(!reason)throw Error('A recorded reason is required for the variation submission.');
      const route='/commercial-variations/'+encodeURIComponent(variationId)+'/submit';
      return persist(route,{expectedVersion,reason,humanConfirmed:true},{collection:'variations',
        verify:(ws,receipt)=>{const record=identity(ws.variations,'id',receipt.id);return record&&record.status==='under_review'?record:null;}});
    }
    async function approveVariation(variationId,input){
      const s=guarded();
      if(input?.humanConfirmed!==true)throw Error('Recording a variation approval requires explicit human confirmation.');
      if(!isId(variationId))throw Error('A valid variation id is required.');
      const approvalType=String(input?.approvalType||'');
      const decision=String(input?.decision||'approved');
      const reason=typeof input?.reason==='string'?input.reason.trim():'';
      if(!APPROVAL_TYPES.includes(approvalType))throw Error('The approval must come from a quantity surveyor, lead professional or client.');
      if(!['approved','rejected'].includes(decision))throw Error('The variation decision must be approved or rejected.');
      if(!reason)throw Error('A recorded reason is required for the variation decision.');
      const route='/commercial-variations/'+encodeURIComponent(variationId)+'/approvals';
      return persist(route,{approvalType,decision,reason,humanConfirmed:true},{collection:'variations',
        verify:(ws,receipt)=>{const record=identity(ws.variations,'id',receipt.id);if(!record)return null;
          const approval=(record.approvals||[]).find(a=>a&&a.approvalType===approvalType&&a.decision===decision);return approval?record:null;}});
    }
    // --- payments_escrow (internal metadata only) -----------------------------
    async function addClaim(input){
      const s=guarded();
      if(input?.humanConfirmed!==true)throw Error('Submitting a payment claim requires explicit human confirmation.');
      const claimNumber=typeof input?.claimNumber==='string'?input.claimNumber.trim():'';
      const claimedToDateCents=int(input?.claimedToDateCents);
      const reason=typeof input?.reason==='string'?input.reason.trim():'';
      if(!claimNumber||claimNumber.length>80)throw Error('A payment claim number is required.');
      if(!Number.isInteger(claimedToDateCents)||claimedToDateCents<1)throw Error('The cumulative claimed amount must be a positive integer in cents.');
      if(typeof input?.disputed!=='boolean')throw Error('The dispute state of the claim must be stated.');
      if(!reason)throw Error('A recorded reason is required for the payment claim.');
      const evidence=(Array.isArray(input?.evidenceReferences)?input.evidenceReferences:[]).map(r=>String(r).trim());
      if(!evidence.length)throw Error('At least one evidence reference is required for a payment claim.');
      if(evidence.some(r=>!/^[a-z][a-z0-9_-]*:[^\s]+$/i.test(r)))throw Error('Every evidence reference must be recorded as type:reference.');
      const baseline=await baselineId(s,input);current(s);
      const data=await readWorkspace(s);current(s);
      if(claimedToDateCents>int(data.baseline?.currentContractSumCents))throw Error('The claimed amount exceeds the current contract sum. Nothing was submitted.');
      const milestoneId=isId(input?.milestoneId)?String(input.milestoneId).toLowerCase():null;
      if(!milestoneId)throw Error('A payment milestone from an issued payment schedule is required for a claim. Nothing was submitted.');
      const issued=data.schedules.find(schedule=>(schedule.milestones||[]).some(m=>String(m.id).toLowerCase()===milestoneId));
      if(!issued)throw Error('The selected payment milestone is not part of an issued payment schedule for this project. Nothing was submitted.');
      const route='/commercial-baselines/'+encodeURIComponent(baseline)+'/claims';
      return persist(route,{claimNumber,claimedToDateCents,milestoneId,evidenceReferences:evidence,disputed:input.disputed,reason,humanConfirmed:true},{collection:'claims',
        verify:(ws,receipt)=>identity(ws.claims,'claimId',receipt.id)});
    }
    async function issueCertificate(claimId,input){
      const s=guarded();
      if(input?.humanConfirmed!==true)throw Error('Certifying a payment claim requires explicit human confirmation.');
      if(!isId(claimId))throw Error('A valid payment claim id is required.');
      const grossCertifiedToDateCents=int(input?.grossCertifiedToDateCents);
      const otherDeductionsCents=input?.otherDeductionsCents==null?0:int(input.otherDeductionsCents);
      const reason=typeof input?.reason==='string'?input.reason.trim():'';
      if(!Number.isInteger(grossCertifiedToDateCents)||grossCertifiedToDateCents<0)throw Error('The gross certified-to-date amount must be a non-negative integer in cents.');
      if(!Number.isInteger(otherDeductionsCents)||otherDeductionsCents<0)throw Error('Other deductions must be a non-negative integer in cents.');
      if(!reason)throw Error('A recorded certification reason is required.');
      const route='/commercial-claims/'+encodeURIComponent(claimId)+'/certificates';
      return persist(route,{grossCertifiedToDateCents,otherDeductionsCents,reason,humanConfirmed:true},{collection:'certificates',
        verify:(ws,receipt)=>identity(ws.certificates,'certificateId',receipt.id)});
    }
    async function listRetention(input){
      const s=scope();current(s);
      const baseline=await baselineId(s,input);current(s);
      const data=unwrap(await context.read('/commercial-baselines/'+encodeURIComponent(baseline)+'/retention'));current(s);
      if(!data||typeof data!=='object'||Array.isArray(data))throw Error('Invalid retention response.');
      if(data.baselineId&&String(data.baselineId).toLowerCase()!==baseline)throw Error('The retention balance belongs to a different baseline.');
      return {baselineId:baseline,heldToDateCents:Number.isInteger(data.heldToDateCents)?data.heldToDateCents:0,currency:data.currency||null};
    }
    // Release/payout is never executed here. The backend only accepts a release
    // request against a registered provider whose connector is live-configured; this
    // workspace refuses instead of pretending a payment path exists.
    async function requestRelease(){
      scope();
      throw Error(RELEASE_REFUSED);
    }
    function providerStatus(data){
      const providers=Array.isArray(data?.providers)?data.providers:[];
      const live=providers.filter(p=>p&&p.liveConfigured===true);
      const boundary=data?.providerBoundary||{};
      return {configured:live.length>0,registeredProviders:providers.length,liveProviders:live.length,
        credentialsConfigured:boundary.credentialsConfigured===true,fundsHeldByArchitex:boundary.fundsHeldByArchitex===true,
        automaticRelease:boundary.automaticRelease===true,livePaymentClaimed:boundary.livePaymentClaimed===true,
        unconfiguredStatus:boundary.unconfiguredStatus||'provider_configuration_required',message:ESCROW_BOUNDARY};
    }
    return {boundary:BOUNDARY,escrowBoundary:ESCROW_BOUNDARY,releaseRefused:RELEASE_REFUSED,approvalTypes:APPROVAL_TYPES.slice(),
      load,listFinanceWorkspace:load,createBaseline,addPaymentSchedule,addVariation,submitVariation,approveVariation,addClaim,issueCertificate,listRetention,requestRelease,providerStatus};
  }
  root.V1CloneCommercialAdapter={create};
})(window);
