(function(root){
  'use strict';
  // Survey & Geomatics adapter — the shared SurveyJobService backend for the
  // survey_geomatics tool. Mirrors the site-tools adapter contract exactly:
  // scope(), current(), context.write with an exact read-back path, an explicit
  // read-back verification, and never a fabricated success.
  //
  // Honest boundary: these are internal survey-and-geomatics coordination records.
  // The backend stamps surveyorGeneralApprovalClaimed=false and
  // officialCoordinatesClaimed=false on every job, and it exposes no route that
  // lodges a diagram with the Surveyor-General, issues a beacon certificate or
  // certifies official coordinates. The adapter never claims otherwise.
  const BOUNDARY='Internal survey and geomatics coordination records only — this does not lodge a diagram with the Surveyor-General, issue a beacon certificate, or certify official coordinates.';
  const SURVEY_TYPES=['topographical','boundary','beacon_relocation','subdivision','consolidation','sectional_title','servitude','lease_diagram','general_plan','as_built','other'];
  const OBSERVATION_TYPES=['control_point','benchmark','beacon','boundary_marker','detail_point','other'];
  const OBSERVATION_STATUSES=['observed','verified','rejected','superseded'];
  const DELIVERABLE_TYPES=['topographical_plan','cadastral_diagram','general_plan','beacon_certificate','control_report','servitude_diagram','sectional_title_plan','as_built_plan','coordinate_schedule','other'];
  const DELIVERABLE_STATUSES=['draft','pending_review','complete','submitted_externally','accepted','rejected','superseded'];
  const ROLE=/^[a-z][a-z0-9_]{1,79}$/;
  const unwrap=r=>r?.data??r;
  function create(context){
    function scope(){const s=context.getState();if(!s.ready||!s.session||!s.organisationId||!/^[a-f0-9]{32}$/i.test(s.selectedProjectId||''))throw Error('Select an authenticated project.');return {...s};}
    const path=(s,name)=>'/projects/'+encodeURIComponent(s.selectedProjectId)+'/'+name;
    const jobPath=id=>'/survey-jobs/'+encodeURIComponent(id);
    function current(s){const now=context.getState();if(!now.ready||!now.session||now.selectedProjectId!==s.selectedProjectId||now.organisationId!==s.organisationId||now.session!==s.session)throw Error('Scope changed; reload this workspace.');}
    function text(v,name,max){const t=typeof v==='string'?v.trim():v==null?'':String(v).trim();if(!t)throw Error(name+' is required.');if(t.length>max)throw Error(name+' is too long.');return t;}
    function choice(v,allowed,name){if(!allowed.includes(v))throw Error('A valid '+name+' is required.');return v;}
    function role(v){const r=text(v,'Responsible professional role',80);if(!ROLE.test(r))throw Error('Responsible professional role is invalid.');return r;}
    function id(v,name){if(!/^[a-f0-9]{32}$/i.test(String(v==null?'':v)))throw Error('A valid '+name+' is required.');return String(v).toLowerCase();}
    function url(v,name){const u=text(v,name,1000);if(!/^https?:\/\/\S+$/i.test(u))throw Error(name+' must be an http(s) URL.');return u;}
    function date(v,name){const d=text(v,name,10);const parsed=/^\d{4}-\d{2}-\d{2}$/.test(d)?new Date(d+'T00:00:00Z'):null;if(!parsed||Number.isNaN(parsed.getTime())||parsed.toISOString().slice(0,10)!==d)throw Error(name+' must be a valid YYYY-MM-DD date.');return d;}
    // The backend stores coordinates as fixed-precision decimal STRINGS and money as
    // whole cents. Numbers from a workspace are normalised here, then re-validated
    // against the backend's own precision rule before anything is submitted.
    function decimal(v,places,name){
      const raw=typeof v==='number'?(Number.isFinite(v)?v.toFixed(places):''):typeof v==='string'?v.trim():'';
      if(new RegExp('^-?\\d{1,15}(?:\\.\\d{1,'+places+'})?$').test(raw)!==true)throw Error(name+' is invalid; use up to '+places+' decimal places.');
      return raw;
    }
    function hundredths(v,name){
      const raw=typeof v==='number'?(Number.isFinite(v)?v.toFixed(2):''):typeof v==='string'?v.trim():'';
      if(/^\d{1,7}(?:\.\d{1,2})?$/.test(raw)!==true)throw Error(name+' is invalid; use a non-negative number with up to 2 decimal places.');
      return raw;
    }
    function money(v,name){const n=Number(v);if(!Number.isInteger(n)||n<0||n>100000000000)throw Error(name+' must be a non-negative whole number of cents.');return n;}
    function object(v,name){if(!v||typeof v!=='object'||Array.isArray(v))throw Error(name+' is required.');return v;}
    function sources(v){
      if(!Array.isArray(v)||v.length===0)throw Error('At least one source-basis reference is required.');
      return v.map(x=>{if(!x||typeof x!=='object'||Array.isArray(x))throw Error('Each source-basis reference must be an object.');return {title:text(x.title,'Source title',220),url:url(x.url,'Source URL'),retrievedAt:date(x.retrievedAt,'Source date')};});
    }
    async function readJob(s,jobId){const job=unwrap(await context.read(jobPath(jobId)));current(s);if(!job||job.id!==jobId)throw Error('Survey job read-back failed; refresh before retrying.');return job;}
    async function versionOf(s,jobId,override){if(Number.isInteger(override)&&override>=1)return override;return (await readJob(s,jobId)).version;}
    async function listJobs(){
      const s=scope();
      const data=unwrap(await context.read(path(s,'survey-jobs')));current(s);
      if(!Array.isArray(data))throw Error('Invalid survey-jobs response.');
      return data.filter(r=>r&&typeof r==='object'&&(!r.projectId||r.projectId===s.selectedProjectId)&&(!r.organisationId||r.organisationId===s.organisationId));
    }
    async function getJob(jobId){const s=scope();return readJob(s,id(jobId,'survey job id'));}
    async function createJob(input){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(context.capabilities?.readBackCollection!==true)throw Error('Survey job saving unavailable: shared collection read-back support is required. Nothing was submitted.');
      const body={jobNumber:text(input?.jobNumber,'Survey job number',80),surveyType:choice(input?.surveyType,SURVEY_TYPES,'survey type'),title:text(input?.title,'Survey job title',220),responsibleRole:role(input?.responsibleRole),coordinateReferenceSystem:text(input?.coordinateReferenceSystem,'Coordinate reference system',180),verticalDatum:text(input?.verticalDatum,'Vertical datum',180),sourceBasis:sources(input?.sourceBasis)};
      const saved=unwrap(await context.write(path(s,'survey-jobs'),body,{readBackPath:path(s,'survey-jobs')}));current(s);
      if(!saved?.id)throw Error('Survey job persistence not verified; refresh before retrying.');
      const job=await readJob(s,saved.id);
      if(job.projectId!==s.selectedProjectId)throw Error('Survey job persistence not verified; refresh before retrying.');
      return job;
    }
    async function addObservation(jobId,jobInput){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(context.capabilities?.readBackCollection!==true)throw Error('Observation saving unavailable: shared collection read-back support is required. Nothing was submitted.');
      const j=id(jobId,'survey job id');
      const expectedVersion=await versionOf(s,j,jobInput?.expectedVersion);
      const body={expectedVersion,payload:object(jobInput?.payload,'Observation payload'),pointCode:text(jobInput?.pointCode,'Point code',80),observationType:choice(jobInput?.observationType,OBSERVATION_TYPES,'observation type'),easting:decimal(jobInput?.easting,4,'Easting'),northing:decimal(jobInput?.northing,4,'Northing'),elevation:decimal(jobInput?.elevation,4,'Elevation'),status:choice(jobInput?.status,OBSERVATION_STATUSES,'observation status'),sourceReference:text(jobInput?.sourceReference,'Source reference',180),sourceUrl:url(jobInput?.sourceUrl,'Source URL'),observedAt:date(jobInput?.observedAt,'Observation date')};
      const saved=unwrap(await context.write(jobPath(j)+'/observations',body,{readBackPath:jobPath(j),readBackCollection:'observations'}));current(s);
      const job=await readJob(s,j);
      const record=(Array.isArray(job.observations)?job.observations:[]).find(r=>r.id===saved?.id);
      if(!record)throw Error('Observation persistence not verified; refresh before retrying.');
      return record;
    }
    async function addDeliverable(jobId,jobInput){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(context.capabilities?.readBackCollection!==true)throw Error('Deliverable saving unavailable: shared collection read-back support is required. Nothing was submitted.');
      const j=id(jobId,'survey job id');
      // The deliverable route answers with the parent job, so the created deliverable is
      // identified by diffing the job's deliverable set across the verified write.
      const before=await readJob(s,j);
      const expectedVersion=Number.isInteger(jobInput?.expectedVersion)&&jobInput.expectedVersion>=1?jobInput.expectedVersion:before.version;
      const known=new Set((Array.isArray(before.deliverables)?before.deliverables:[]).map(r=>r.id));
      const body={expectedVersion,payload:object(jobInput?.payload,'Deliverable payload'),deliverableType:choice(jobInput?.deliverableType,DELIVERABLE_TYPES,'deliverable type'),title:text(jobInput?.title,'Deliverable title',220),status:choice(jobInput?.status,DELIVERABLE_STATUSES,'deliverable status'),externalReference:text(jobInput?.externalReference,'External reference',180),sourceUrl:url(jobInput?.sourceUrl,'Source URL')};
      unwrap(await context.write(jobPath(j)+'/deliverables',body,{readBackPath:jobPath(j)}));current(s);
      const job=await readJob(s,j);
      const record=(Array.isArray(job.deliverables)?job.deliverables:[]).find(r=>!known.has(r.id));
      if(!record)throw Error('Deliverable persistence not verified; refresh before retrying.');
      return record;
    }
    async function addFee(jobId,feeInput){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      const j=id(jobId,'survey job id');
      const expectedVersion=await versionOf(s,j,feeInput?.expectedVersion);
      const body={expectedVersion,fieldDays:hundredths(feeInput?.fieldDays,'Field days'),officeDays:hundredths(feeInput?.officeDays,'Office days'),travelDistanceKm:hundredths(feeInput?.travelDistanceKm,'Travel distance'),fieldDayRateCents:money(feeInput?.fieldDayRateCents,'Field day rate'),officeDayRateCents:money(feeInput?.officeDayRateCents,'Office day rate'),travelRateCentsPerKm:money(feeInput?.travelRateCentsPerKm,'Travel rate'),complexityAllowanceCents:money(feeInput?.complexityAllowanceCents,'Complexity allowance'),statutoryFeeCents:money(feeInput?.statutoryFeeCents,'Statutory fee'),statutorySourceTitle:text(feeInput?.statutorySourceTitle,'Statutory source title',220),statutorySourceUrl:url(feeInput?.statutorySourceUrl,'Statutory source URL'),statutorySourceRetrievedAt:date(feeInput?.statutorySourceRetrievedAt,'Statutory source date')};
      unwrap(await context.write(jobPath(j)+'/fees',body,{readBackPath:jobPath(j)}));current(s);
      const job=await readJob(s,j);
      if(job.feePayload===null||job.feePayload===undefined)throw Error('Fee estimate persistence not verified; refresh before retrying.');
      return {jobVersion:job.version,privateProfessionalFeeCents:job.privateProfessionalFeeCents,statutoryFeeCents:job.statutoryFeeCents,totalFeeCents:job.totalFeeCents,feePayload:job.feePayload};
    }
    // The only terminal transition this backend exposes. It is gated: the backend
    // itself requires two verified observations, one complete deliverable, a fee
    // estimate, the responsible professional role, and a verified professional
    // registration. An unmet gate fails honestly here rather than reporting success.
    async function issue(jobId,{confirmed=false,professionalRole,reason,reference,expectedVersion}={}){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(confirmed!==true)throw Error('Survey job issue requires explicit human confirmation.');
      const j=id(jobId,'survey job id');
      const expected=await versionOf(s,j,expectedVersion);
      const body={expectedVersion:expected,humanConfirmed:true,professionalRole:role(professionalRole),reason:text(reason,'Issue reason',500),externalReference:text(reference,'Issue reference',180)};
      unwrap(await context.write(jobPath(j)+'/issue',body,{readBackPath:jobPath(j)}));current(s);
      const job=await readJob(s,j);
      if(job.status!=='issued')throw Error('Survey job issue not verified; refresh before retrying.');
      return job;
    }
    return {boundary:BOUNDARY,surveyTypes:SURVEY_TYPES.slice(),observationTypes:OBSERVATION_TYPES.slice(),observationStatuses:OBSERVATION_STATUSES.slice(),deliverableTypes:DELIVERABLE_TYPES.slice(),deliverableStatuses:DELIVERABLE_STATUSES.slice(),listJobs,getJob,createJob,addObservation,addDeliverable,addFee,issue};
  }
  root.V1CloneSurveyAdapter={create};
})(window);
