(function(root){
  'use strict';
  // Municipal readiness adapter — shared backend for the municipal_tracker and
  // council_navigator tools. Follows the site-tools adapter contract exactly:
  // scope(), current(), context.write with readBackPath, a humanConfirmed guard on
  // the sensitive transition, an explicit read-back verification, and never a
  // fabricated success.
  //
  // Honest boundary: these are internal coordination and readiness records. The
  // backend returns municipalSubmissionCreated=false and advisoryOnly=true, and it
  // has no route that lodges a submission with a municipality, issues a certificate
  // or records statutory approval. The adapter never claims otherwise.
  const BOUNDARY='Internal submission-readiness records only — this does not lodge a submission with a municipality, issue a certificate, or record statutory approval.';
  const STATUS_APPROVED='approved_for_professional_submission_review';
  const STATUS_READY='ready_for_professional_submission_review';
  const STATUS_ASSESSED='submission_readiness_assessed';
  const OCCUPANCY=['single_residential','multi_residential','commercial','public_assembly','mixed_use'];
  const BOOL_FACTS=['changesLoadBearing','changesDrainageOrStormwater','publicAccessOrAssembly','envelopeEnergyImpact','zoningKnown','coverageOrParkingRisk','boundaryOrServitudeUnclear','heritagePotential','environmentalSensitivity','trafficImpact','nbrAdvisoryReviewed','clientAuthorityConfirmed'];
  const DOCUMENTS=['title_deed','sg_diagram','zoning_certificate','client_authority','appointment_record','heritage_comment','traffic_comment','environmental_comment'];
  const DISCIPLINES=['client','lead_professional','architect','municipal_coordinator','structural_engineer','civil_engineer','fire_consultant','energy_consultant','town_planner','land_surveyor','quantity_surveyor','heritage_practitioner','environmental_practitioner','traffic_engineer'];
  const unwrap=r=>r?.data??r;
  function create(context){
    function scope(){const s=context.getState();if(!s.ready||!s.session||!s.organisationId||!/^[a-f0-9]{32}$/i.test(s.selectedProjectId||''))throw Error('Select an authenticated project.');return {...s};}
    const path=(s,name)=>'/projects/'+encodeURIComponent(s.selectedProjectId)+'/'+name;
    function current(s){const now=context.getState();if(!now.ready||!now.session||now.selectedProjectId!==s.selectedProjectId||now.organisationId!==s.organisationId||now.session!==s.session)throw Error('Scope changed; reload this workspace.');}
    function assessments(data,s){if(!Array.isArray(data))throw Error('Invalid submission-readiness response.');return data.filter(r=>r&&typeof r==='object'&&(!r.projectId||r.projectId===s.selectedProjectId));}
    // The backend readiness record has no single "status" column: derive a workflow
    // label from the professional review and the professional-approval eligibility.
    function statusOf(a){if(a?.professionalReview?.decision)return String(a.professionalReview.decision);if(a?.readyForProfessionalSubmissionReview===true&&a?.professionalApprovalEligible===true)return STATUS_READY;return STATUS_ASSESSED;}
    function submission(a){return {id:a.id,projectId:a.projectId||null,status:statusOf(a),overallScore:a.overallScore==null?null:a.overallScore,complexity:a.complexity?.level||null,readyForProfessionalSubmissionReview:a.readyForProfessionalSubmissionReview===true,professionalApprovalEligible:a.professionalApprovalEligible===true,municipality:a.project?.municipality||null,propertyReference:a.project?.propertyReference||null,matrixStatus:a.matrix?.status||null,matrixVersion:a.matrix?.version||null,blockers:Array.isArray(a.blockers)?a.blockers:[],professionalReviews:Array.isArray(a.professionalReviews)?a.professionalReviews:[],categories:a.categories&&typeof a.categories==='object'?a.categories:{},professionalReview:a.professionalReview||null,advisoryOnly:a.advisoryOnly!==false,municipalSubmissionCreated:a.municipalSubmissionCreated===true,createdAt:a.createdAt||null};}
    async function listSubmissions(){
      const s=scope();current(s);
      const data=unwrap(await context.read(path(s,'submission-readiness')));current(s);
      return assessments(data,s).map(a=>({id:a.id,status:statusOf(a),overallScore:a.overallScore==null?null:a.overallScore,readyForProfessionalSubmissionReview:a.readyForProfessionalSubmissionReview===true,professionallyReviewed:Boolean(a.professionalReview&&a.professionalReview.decision),municipality:a.project?.municipality||null,reviewDecision:a.professionalReview?.decision||null,advisoryOnly:a.advisoryOnly!==false,municipalSubmissionCreated:a.municipalSubmissionCreated===true,createdAt:a.createdAt||null}));
    }
    async function listReadiness(){
      const s=scope();current(s);
      const data=unwrap(await context.read(path(s,'submission-readiness')));current(s);
      return assessments(data,s).map(submission);
    }
    // The seeded implementation matrix is review-required and cannot be verified by
    // design, so a project's first submission readiness record must reuse whatever
    // matrix is configured for its municipality. Never invent a matrix id.
    async function resolveMatrix(s,input){
      if(typeof input?.matrixVersionId==='string'&&/^[a-f0-9]{32}$/i.test(input.matrixVersionId.trim()))return input.matrixVersionId.trim().toLowerCase();
      const project=(s.projects||[]).find(p=>p.id===s.selectedProjectId);
      const municipality=typeof project?.municipality==='string'?project.municipality.trim():'';
      if(!municipality)throw Error('No municipality is recorded for the selected project. Seed a requirement matrix before creating a submission. Nothing was submitted.');
      const matrices=unwrap(await context.read('/municipal-requirement-versions?municipality='+encodeURIComponent(municipality)));current(s);
      if(!Array.isArray(matrices)||matrices.length===0)throw Error('No municipal requirement matrix is configured for '+municipality+'. Seed a requirement matrix before creating a submission. Nothing was submitted.');
      const chosen=matrices.find(m=>m&&m.status==='verified')||matrices.find(m=>m&&m.id)||null;
      if(!chosen?.id)throw Error('Municipal requirement matrix could not be resolved. Nothing was submitted.');
      return chosen.id;
    }
    async function createSubmission(input){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(context.capabilities?.readBackCollection!==true)throw Error('Submission readiness saving unavailable: shared collection read-back support is required. Nothing was submitted.');
      const occupation=input?.occupancyType;
      if(!OCCUPANCY.includes(occupation))throw Error('A valid occupancy type is required.');
      const value=Number(input?.estimatedConstructionValueZar);
      if(!Number.isFinite(value)||value<=0)throw Error('A positive construction value is required.');
      const matrixVersionId=await resolveMatrix(s,input);current(s);
      const docs=(Array.isArray(input.availableSupportingDocuments)?input.availableSupportingDocuments:[]).filter(d=>DOCUMENTS.includes(d));
      const signoffs=(Array.isArray(input.professionalSignoffs)?input.professionalSignoffs:[]).filter(d=>DISCIPLINES.includes(d));
      const body={matrixVersionId,occupancyType:occupation,estimatedConstructionValueZar:value,availableSupportingDocuments:docs,professionalSignoffs:signoffs};
      for(const f of BOOL_FACTS)body[f]=input?.[f]===true;
      const saved=unwrap(await context.write(path(s,'submission-readiness'),body,{readBackPath:path(s,'submission-readiness')}));current(s);
      const data=unwrap(await context.read(path(s,'submission-readiness')));current(s);
      const record=assessments(data,s).find(r=>r.id===saved?.id);if(!record)throw Error('Submission readiness persistence not verified; refresh before retrying.');return submission(record);
    }
    // The only status transition this backend exposes is the professional submission
    // review approval. It requires a verified requirement matrix and an unblocked,
    // ready assessment, so an unconfigured authority path fails honestly here rather
    // than reporting a success that never happened.
    async function transitionSubmission(submissionId,{status=STATUS_APPROVED,reason,humanConfirmed=false}={}){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(humanConfirmed!==true)throw Error('Professional submission review approval requires explicit human confirmation.');
      if(status!==STATUS_APPROVED)throw Error('Unsupported municipal status transition: '+status+'. Only professional submission review approval is available; no external authority submission is offered.');
      const reviewReason=typeof reason==='string'?reason.trim():'';
      if(!reviewReason)throw Error('A professional review reason is required.');
      if(!/^[a-f0-9]{32}$/i.test(String(submissionId||'')))throw Error('A valid submission id is required.');
      unwrap(await context.write('/submission-readiness/'+encodeURIComponent(submissionId)+'/approve',{humanConfirmed:true,reviewReason},{readBackPath:path(s,'submission-readiness')}));current(s);
      const data=unwrap(await context.read(path(s,'submission-readiness')));current(s);
      const record=assessments(data,s).find(r=>r.id===submissionId);
      if(!record||record.professionalReview?.decision!==STATUS_APPROVED)throw Error('Professional review approval not verified; refresh before retrying.');return submission(record);
    }
    return {boundary:BOUNDARY,statuses:{approved:STATUS_APPROVED,ready:STATUS_READY,assessed:STATUS_ASSESSED},occupancy:OCCUPANCY.slice(),documents:DOCUMENTS.slice(),disciplines:DISCIPLINES.slice(),boolFacts:BOOL_FACTS.slice(),listSubmissions,listReadiness,createSubmission,transitionSubmission};
  }
  root.V1CloneMunicipalAdapter={create};
})(window);
