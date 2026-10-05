(function(root){
  'use strict';
  // Regulatory-case adapter — the shared backend (RegulatoryCaseService) behind the
  // eia_workspace and environmental_heritage Tier-2 tools. It follows the site-tools /
  // municipal adapter contract exactly: scope(), current(), context.write with an exact
  // readBackPath, an explicit read-back verification, a human-confirmation guard on the
  // determination transition, and never a fabricated success.
  //
  // Honest boundary: these are internal regulatory-case working records. The backend
  // returns authorityDecisionClaimed=false and authorisationClaimed=false, and it exposes
  // no route that lodges an application, issues an authorisation or records an authority
  // decision. The adapter never claims otherwise.
  const BOUNDARY='Internal regulatory-case records only — this does not lodge an application with an environmental or heritage authority, issue an authorisation, or record an authority decision.';
  const CASE_TYPES=['environmental','heritage'];
  const EVIDENCE_TYPES=['screening_report','site_sensitivity_verification','listed_activity_review','specialist_study','public_participation','empr','monitoring','audit','heritage_nid','heritage_impact_assessment','authority_correspondence','permit','condition'];
  const EVIDENCE_STATUSES=['draft','pending','complete','submitted_externally','received','accepted','rejected','closed'];
  const ROUTES=['no_ea_indicated','basic_assessment','scoping_eia','nid','heritage_impact_assessment','permit_required','other'];
  const REQUIRED_EVIDENCE={environmental:['screening_report','site_sensitivity_verification'],heritage:['heritage_nid','heritage_impact_assessment']};
  const HEX=/^[a-f0-9]{32}$/i;
  const unwrap=r=>r?.data??r;
  function create(context){
    function scope(){const s=context.getState();if(!s.ready||!s.session||!s.organisationId||!HEX.test(s.selectedProjectId||''))throw Error('Select an authenticated project.');return {...s};}
    function current(s){const now=context.getState();if(!now.ready||!now.session||now.selectedProjectId!==s.selectedProjectId||now.organisationId!==s.organisationId||now.session!==s.session)throw Error('Scope changed; reload this workspace.');}
    const listPath=(s,type)=>'/projects/'+encodeURIComponent(s.selectedProjectId)+'/regulatory-cases'+(type?'?type='+encodeURIComponent(type):'');
    const casePath=id=>'/regulatory-cases/'+encodeURIComponent(id);
    function hex(v,msg){if(typeof v!=='string'||!HEX.test(v.trim()))throw Error(msg);return v.trim().toLowerCase();}
    function text(v,max,msg){const t=String(v==null?'':v).trim();if(!t||t.length>max)throw Error(msg);return t;}
    function url(v){const t=text(v,1000,'A valid http(s) source URL is required.');if(!/^https?:\/\//i.test(t))throw Error('A valid http(s) source URL is required.');return t;}
    function date(v){const t=text(v,10,'A YYYY-MM-DD date is required.');if(!/^\d{4}-\d{2}-\d{2}$/.test(t))throw Error('A YYYY-MM-DD date is required.');return t;}
    function sourceBasis(v){if(!Array.isArray(v)||v.length===0||v.length>20)throw Error('At least one source-basis entry (title, url, retrievedAt) is required.');return v.map(s=>{if(!s||typeof s!=='object')throw Error('Each source-basis entry needs title, url and retrievedAt.');return {title:text(s.title,220,'Each source-basis entry needs a title, url and retrievedAt.'),url:url(s.url),retrievedAt:date(s.retrievedAt)};});}
    function cases(data,s){if(!Array.isArray(data))throw Error('Invalid regulatory-case response.');return data.filter(r=>r&&typeof r==='object'&&(!r.projectId||r.projectId===s.selectedProjectId));}
    async function listCases(type){
      if(type!=null&&!CASE_TYPES.includes(type))throw Error('Unsupported regulatory case type: '+type+'.');
      const s=scope();current(s);
      const data=unwrap(await context.read(listPath(s,type)));current(s);
      return cases(data,s);
    }
    async function createCase(input){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(context.capabilities?.readBackCollection!==true)throw Error('Regulatory case saving unavailable: shared collection read-back support is required. Nothing was submitted.');
      const caseType=input?.caseType;
      if(!CASE_TYPES.includes(caseType))throw Error('A valid case type (environmental or heritage) is required.');
      const body={caseType,caseNumber:text(input?.caseNumber,80,'A case number is required.'),title:text(input?.title,220,'A case title is required.'),authorityName:text(input?.authorityName,220,'The responsible authority name is required.'),responsibleRole:text(input?.responsibleRole,80,'A responsible professional role is required.'),sourceBasis:sourceBasis(input?.sourceBasis)};
      const readPath=listPath(s,caseType);
      const saved=unwrap(await context.write(listPath(s,null),body,{readBackPath:readPath}));current(s);
      const data=unwrap(await context.read(readPath));current(s);
      const record=cases(data,s).find(r=>r.id===saved?.id);
      if(!record)throw Error('Regulatory case persistence not verified; refresh before retrying.');
      return record;
    }
    async function addEvidence(caseId,input){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      const id=hex(caseId,'A valid regulatory case id is required.');
      const existing=unwrap(await context.read(casePath(id)));current(s);
      if(!existing||existing.id!==id)throw Error('The regulatory case could not be loaded.');
      if(existing.status!=='draft')throw Error('Evidence cannot be added: this regulatory case is already determined.');
      const evidenceType=input?.evidenceType;
      if(!EVIDENCE_TYPES.includes(evidenceType))throw Error('A valid evidence type is required.');
      const status=input?.status;
      if(!EVIDENCE_STATUSES.includes(status))throw Error('A valid evidence status is required.');
      const expected=Number.isInteger(input?.expectedVersion)?input.expectedVersion:(Number(existing.version)||1);
      const payload=(input?.payload&&typeof input.payload==='object'&&!Array.isArray(input.payload))?input.payload:{};
      const body={expectedVersion:expected,evidenceType,title:text(input?.title,220,'An evidence title is required.'),status,externalReference:text(input?.externalReference,180,'An external reference is required.'),sourceUrl:url(input?.sourceUrl),observedAt:date(input?.observedAt),payload};
      const saved=unwrap(await context.write(casePath(id)+'/evidence',body,{readBackPath:casePath(id),readBackCollection:'evidence'}));current(s);
      const after=unwrap(await context.read(casePath(id)));current(s);
      const record=(after?.evidence||[]).find(e=>e.id===saved?.id);
      if(!record)throw Error('Evidence persistence not verified; refresh before retrying.');
      return record;
    }
    // The only regulatory transition the backend exposes is the professional determination.
    // It requires a matching responsible role + verified professional authority and the
    // case-type evidence set, so an unconfigured authority path fails honestly here rather
    // than reporting a determination that never happened.
    async function determine(caseId,input){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(input?.humanConfirmed!==true)throw Error('Regulatory determination requires explicit human confirmation.');
      const id=hex(caseId,'A valid regulatory case id is required.');
      const route=input?.route;
      if(!ROUTES.includes(route))throw Error('A valid determination route is required.');
      const before=unwrap(await context.read(casePath(id)));current(s);
      const body={expectedVersion:Number.isInteger(input?.expectedVersion)?input.expectedVersion:(Number(before?.version)||1),humanConfirmed:true,professionalRole:text(input?.professionalRole,80,'The determining professional role is required.'),route,reason:text(input?.reason,500,'A determination reason is required.'),externalReference:text(input?.externalReference,180,'An external determination reference is required.')};
      const saved=unwrap(await context.write(casePath(id)+'/determine',body,{readBackPath:casePath(id)}));current(s);
      const after=unwrap(await context.read(casePath(id)));current(s);
      if(!after||after.id!==(saved?.id||id)||after.status!=='determined'||!after.determinationRoute)throw Error('Regulatory determination not verified; refresh before retrying.');
      return after;
    }
    return {boundary:BOUNDARY,caseTypes:CASE_TYPES.slice(),evidenceTypes:EVIDENCE_TYPES.slice(),evidenceStatuses:EVIDENCE_STATUSES.slice(),routes:ROUTES.slice(),requiredEvidence:REQUIRED_EVIDENCE,listCases,createCase,addEvidence,determine};
  }
  root.V1CloneRegulatoryAdapter={create};
})(window);
