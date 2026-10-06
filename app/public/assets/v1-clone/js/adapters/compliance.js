(function(root){
  'use strict';
  // Compliance Hub / Contractor Compliance / Insurance Register adapter.
  //
  // Wraps the verified backend surfaces that already exist for these three tools:
  //   * Trust compliance  — GET  /projects/{projectId}/trust-compliance-workspace
  //                         POST /projects/{projectId}/trust/compliance-items
  //                         POST /trust/compliance-items/{itemId}/review
  //   * Drawing compliance — POST /drawing-compliance/checklist (stateless screen)
  //                          POST /drawing-compliance/reviews (persisted snapshot)
  //                          GET  /drawing-compliance/reviews/{reviewId}
  //   * Controlled register — GET/POST /projects/{projectId}/controlled-registers/{registerKey}
  //                           POST     /controlled-registers/{entryId}/transition
  //
  // Every write is human-confirmed and read-back verified against the exact scope.
  // Boundary: internal evidence is never reported as statutory certification and
  // Architex performs no external submission or external verification.
  const REGISTERS=['contractor_compliance','insurance_register'];
  const REVIEW_DECISIONS=['manually_reviewed','externally_verified','rejected'];
  const OVERALL=['pass','watch','fail','needs_input'];
  const unwrap=r=>r?.data??r;
  const valid=v=>typeof v==='string'&&/^[a-f0-9]{32}$/i.test(v);
  const text=v=>String(v==null?'':v).trim();
  function create(context){
    function scope(){const s=context.getState();if(!s.ready||!s.session||!s.organisationId||!valid(s.selectedProjectId))throw Error('Select an authenticated project.');return {...s};}
    const path=(s,name)=>'/projects/'+encodeURIComponent(s.selectedProjectId)+'/'+name;
    function current(s){const now=context.getState();if(!now.ready||!now.session||now.selectedProjectId!==s.selectedProjectId||now.organisationId!==s.organisationId||now.session!==s.session)throw Error('Scope changed; reload this workspace.');}
    function register(key){if(!REGISTERS.includes(key))throw Error('Unsupported controlled register.');return key;}
    function scopedRows(rows,key,s){if(!Array.isArray(rows))throw Error('Invalid '+key+' response.');return rows.filter(r=>r&&(!r.projectId||r.projectId===s.selectedProjectId)&&(!r.organisationId||r.organisationId===s.organisationId));}
    async function trustWorkspace(s){const data=unwrap(await context.read(path(s,'trust-compliance-workspace')));current(s);return data;}
    async function registerWorkspace(s,key){
      const data=unwrap(await context.read(path(s,'controlled-registers/'+encodeURIComponent(key))));current(s);
      if(!data||typeof data!=='object'||!Array.isArray(data.entries))throw Error('Invalid controlled-register response.');
      return data;
    }

    // --- Trust compliance (Compliance Hub). ---------------------------------
    async function listComplianceItems(){
      const s=scope(),data=await trustWorkspace(s);
      return scopedRows(data?.complianceItems,'complianceItems',s);
    }
    // Records a contractor/supplier/company/insurance document as internal evidence.
    // It never asserts external verification; that requires recorded provider evidence.
    async function createComplianceItem(input={}){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(input.humanConfirmed!==true)throw Error('A trust compliance item requires explicit human confirmation.');
      if(!text(input.subjectName)||!text(input.authority)||!text(input.referenceNumber))throw Error('Subject name, authority and reference number are required.');
      if(!text(input.purpose)||!input.retentionUntil)throw Error('A processing purpose and retention date are required for the record.');
      if(input.consentGiven!==true)throw Error('Recorded consent is required before storing contact/company compliance detail.');
      const body={
        subjectType:input.subjectType,subjectName:text(input.subjectName),category:input.category,
        documentType:text(input.documentType),authority:text(input.authority),referenceNumber:text(input.referenceNumber),
        grade:input.grade,evidenceFileId:input.evidenceFileId??null,details:input.details??{},
        expiresOn:input.expiresOn??null,coverageAmountCents:input.coverageAmountCents??null,
        requiredCoverageCents:input.requiredCoverageCents??null,purpose:text(input.purpose),
        retentionUntil:input.retentionUntil,consentGiven:true,humanConfirmed:true,
      };
      const saved=unwrap(await context.write(path(s,'trust/compliance-items'),body,{readBackPath:path(s,'trust-compliance-workspace'),readBackCollection:'complianceItems'}));current(s);
      const data=await trustWorkspace(s);
      const record=scopedRows(data?.complianceItems,'complianceItems',s).find(r=>r.id===saved?.id);
      if(!record)throw Error('Compliance item persistence not verified; refresh before retrying.');
      if(record.externalVerificationClaimed===true)throw Error('External verification cannot be self-asserted.');
      return record;
    }
    async function reviewItem(itemId,input={}){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(!valid(itemId))throw Error('A trust compliance item id is required.');
      if(input.humanConfirmed!==true)throw Error('A compliance review requires explicit human confirmation.');
      if(!REVIEW_DECISIONS.includes(input.decision))throw Error('Review decision must be manually_reviewed, externally_verified or rejected.');
      if(!text(input.note))throw Error('A review note is required.');
      if(input.decision==='externally_verified'&&(!text(input.provider)||!text(input.providerReference)))throw Error('External verification requires a named provider and provider reference; Architex does not verify externally.');
      const body={decision:input.decision,note:text(input.note),provider:input.provider??null,providerReference:input.providerReference??null,humanConfirmed:true};
      unwrap(await context.write('/trust/compliance-items/'+encodeURIComponent(itemId)+'/review',body,{readBackPath:path(s,'trust-compliance-workspace'),readBackCollection:'complianceItems'}));current(s);
      const data=await trustWorkspace(s);
      const record=scopedRows(data?.complianceItems,'complianceItems',s).find(r=>r.id===itemId);
      if(!record||record.grade!==input.decision)throw Error('Compliance review not verified; refresh before retrying.');
      return record;
    }
    // Deterministic drawing screen (SANS/NBR illustrative source). The backend stores
    // nothing here, so nothing is claimed as persisted: the serverside evaluation is
    // returned and its source boundary + registry identity are verified on read-back.
    async function checklist(input={}){
      const s=scope();
      const body={...input};
      const evaluation=unwrap(await context.write('/drawing-compliance/checklist',body,{readBackPath:'/drawing-compliance/sources'}));current(s);
      const sources=unwrap(await context.read('/drawing-compliance/sources'));current(s);
      if(!evaluation||typeof evaluation!=='object'||!OVERALL.includes(evaluation.overallStatus))throw Error('Drawing checklist evaluation was not returned.');
      if(evaluation.source?.productionRelianceAllowed!==false)throw Error('Drawing compliance source must remain non-production-reliant.');
      if(evaluation.professionalReviewRequired!==true)throw Error('Drawing checklist must require professional review.');
      if(!Array.isArray(sources)||!sources.some(x=>x.key===evaluation.source.key))throw Error('Drawing compliance source registry did not verify.');
      return {persisted:false,...evaluation};
    }
    async function drawingReview(input={}){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(!text(input.title))throw Error('A drawing review title is required.');
      if(!text(input.responsibleRole))throw Error('A responsible role is required for a project drawing review.');
      const body={...input,mode:'project',projectId:s.selectedProjectId,title:text(input.title),responsibleRole:text(input.responsibleRole)};
      const saved=unwrap(await context.write('/drawing-compliance/reviews',body,{readBackPath:r=>'/drawing-compliance/reviews/'+encodeURIComponent(r.id)}));current(s);
      if(!saved?.id)throw Error('Drawing review was not created.');
      const record=unwrap(await context.read('/drawing-compliance/reviews/'+encodeURIComponent(saved.id)));current(s);
      if(!record||record.id!==saved.id)throw Error('Drawing review persistence not verified; refresh before retrying.');
      if(record.professionalReviewStatus!=='pending')throw Error('Drawing review must remain pending professional review.');
      if(record.input?.mode!=='project')throw Error('Drawing review was not stored against the project.');
      return record;
    }

    // --- Controlled registers (Contractor Compliance / Insurance Register). --
    async function listRegister(registerKey){
      const key=register(registerKey),s=scope();
      const data=await registerWorkspace(s,key);
      return {definition:data.definition||{key},entries:scopedRows(data.entries,'entries',s),guardrails:data.guardrails||{}};
    }
    async function createRegisterEntry(registerKey,input={}){
      const key=register(registerKey),s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(input.humanConfirmed!==true)throw Error('A controlled-register entry requires explicit human confirmation.');
      if(!text(input.title)||!text(input.reference)||!text(input.ownerRole))throw Error('Title, reference and owner role are required.');
      if(!input.details||typeof input.details!=='object'||Array.isArray(input.details))throw Error('A details object is required for a controlled-register entry.');
      const body={title:text(input.title),reference:text(input.reference),ownerRole:text(input.ownerRole),dueOn:input.dueOn??null,details:input.details,evidenceFileIds:Array.isArray(input.evidenceFileIds)?input.evidenceFileIds:[],humanConfirmed:true};
      const saved=unwrap(await context.write(path(s,'controlled-registers/'+encodeURIComponent(key)),body,{readBackPath:path(s,'controlled-registers/'+encodeURIComponent(key)),readBackCollection:'entries'}));current(s);
      const data=await registerWorkspace(s,key);
      const record=scopedRows(data.entries,'entries',s).find(r=>r.id===saved?.id);
      if(!record)throw Error('Register entry persistence not verified; refresh before retrying.');
      if(record.registerKey!==key)throw Error('Register entry was stored against a different register.');
      if(record.status!==data.definition?.initialStatus)throw Error('Register entry did not start in the declared initial status.');
      return record;
    }
    async function transitionEntry(entryId,input={}){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(!valid(entryId))throw Error('A controlled-register entry id is required.');
      if(input.humanConfirmed!==true)throw Error('A register transition requires explicit human confirmation.');
      if(!text(input.status))throw Error('A target status is required.');
      if(!text(input.reason))throw Error('A transition reason is required.');
      if(!Number.isInteger(input.expectedVersion)||input.expectedVersion<1)throw Error('A current entry version is required to transition safely.');
      const body={status:input.status,reason:text(input.reason),expectedVersion:input.expectedVersion,humanConfirmed:true};
      const saved=unwrap(await context.write('/controlled-registers/'+encodeURIComponent(entryId)+'/transition',body,{readBackPath:r=>{
        const key=r?.registerKey;return REGISTERS.includes(key)?path(s,'controlled-registers/'+encodeURIComponent(key)):null;
      },readBackCollection:'entries'}));current(s);
      const key=saved?.registerKey;
      if(!REGISTERS.includes(key))throw Error('Register transition did not return a known register.');
      const data=await registerWorkspace(s,key);
      const record=scopedRows(data.entries,'entries',s).find(r=>r.id===entryId);
      if(!record||record.status!==input.status)throw Error('Register transition not verified; refresh before retrying.');
      if(record.version!==input.expectedVersion+1)throw Error('Register transition version did not advance; refresh before retrying.');
      return record;
    }
    return {listComplianceItems,createComplianceItem,reviewItem,checklist,drawingReview,listRegister,createRegisterEntry,transitionEntry,registers:REGISTERS,reviewDecisions:REVIEW_DECISIONS};
  }
  root.V1CloneComplianceAdapter={create,registers:REGISTERS,reviewDecisions:REVIEW_DECISIONS};
})(window);
