(function(root){
  'use strict';
  // BIM / IFC adapter (Tier-2, Design & Documentation) — wraps ONLY the real
  // model-metadata routes that exist in the backend:
  //   GET  /projects/{projectId}/model-sources                -> listSources
  //   POST /projects/{projectId}/model-sources                -> createSource
  //   POST /model-sources/{sourceId}/revisions                -> addRevision
  //   POST /model-metadata-revisions/{revisionId}/decisions   -> decide
  //   POST /model-sources/{sourceId}/issue                    -> issue
  //   GET  /model-sources/{sourceId}                          -> exact read-back
  //
  // HONEST BOUNDARY: there is NO IFC parser here and none is simulated. This
  // adapter records model-source metadata, hand-entered / structured-export
  // metadata facts and mapping decisions only. extractionMode 'native_parser'
  // is refused locally before any POST, and every server payload reports
  // nativeParsingAvailable:false. Nothing is fabricated: a write is only
  // reported after a read-back-verified round trip.
  const FORMATS=['ifc','ifc_json','revit_schedule_csv','cobie_csv','bcf','manual'];
  const EXTRACTION_MODES=['human_entered','structured_export'];
  const CATEGORIES=['model','level','space','room','area','wall','opening','window','door','stair','roof','fire_route','parking','site_coverage','boundary','building_line','fixture','finish','schedule','note','title_block','grid','element','clash_risk'];
  const DECISION_ACTIONS=['approve','reject','flag'];
  const DECISION_STATUS={approve:'approved',reject:'rejected',flag:'flagged'};
  const unwrap=r=>r?.data??r;
  const isId=v=>typeof v==='string'&&/^[a-f0-9]{32}$/i.test(v);
  function required(v,max,label){const s=typeof v==='string'?v.trim():'';if(s===''||s.length>max)throw Error(label+' is required (max '+max+' characters).');return s;}
  function optional(v,max,label){const s=v==null?'':String(v).trim();if(s.length>max)throw Error(label+' is too long (max '+max+' characters).');return s;}
  function role(v){const s=typeof v==='string'?v.trim():'';if(!/^[a-z][a-z0-9_]{1,79}$/.test(s))throw Error('A valid responsible professional role is required.');return s;}
  function create(context){
    function scope(){const s=context.getState();if(!s.ready||!s.session||!s.organisationId||!isId(s.selectedProjectId||''))throw Error('Select an authenticated project.');return {...s};}
    function current(s){const now=context.getState();if(!now.ready||!now.session||now.selectedProjectId!==s.selectedProjectId||now.organisationId!==s.organisationId||now.session!==s.session)throw Error('Scope changed; reload this workspace.');}
    const sourcePath=id=>'/model-sources/'+encodeURIComponent(id);
    const listPath=s=>'/projects/'+encodeURIComponent(s.selectedProjectId)+'/model-sources';
    async function loadSource(s,sourceId){
      if(!isId(sourceId))throw Error('Select a model source.');
      const data=unwrap(await context.read(sourcePath(sourceId)));current(s);
      if(!data||data.id!==sourceId)throw Error('Model source not found.');
      if(data.projectId&&data.projectId!==s.selectedProjectId)throw Error('That model source belongs to another project.');
      return data;
    }
    function facts(value){
      const raw=Array.isArray(value)?value:[];
      if(raw.length===0||raw.length>2000)throw Error('Provide between 1 and 2000 metadata facts.');
      const seen=new Set();
      return raw.map(f=>{
        if(!f||typeof f!=='object'||Array.isArray(f))throw Error('Each metadata fact must be an object.');
        const category=String(f.category||'').trim();
        if(!CATEGORIES.includes(category))throw Error('Fact category is not registered: '+(category||'(empty)')+'.');
        const label=required(f.label,220,'Fact label');
        const externalId=optional(f.externalId,180,'Fact externalId');
        if(externalId!==''){if(seen.has(externalId))throw Error('Duplicate fact externalId: '+externalId+'.');seen.add(externalId);}
        const confidence=f.confidence==null?0:Number(f.confidence);
        if(!Number.isInteger(confidence)||confidence<0||confidence>100)throw Error('Fact confidence must be a whole number from 0 to 100.');
        return {category,externalId,label,level:optional(f.level,180,'Fact level'),value:f.value==null?null:optional(String(f.value),180,'Fact value'),unit:optional(f.unit,40,'Fact unit'),sourceReference:optional(f.sourceReference,500,'Fact sourceReference'),confidence,requiresVerification:f.requiresVerification===true};
      });
    }
    // (a) Organisation/project-scoped model source list (read-only).
    async function listSources(){
      const s=scope();
      const data=unwrap(await context.read(listPath(s)));current(s);
      if(!Array.isArray(data))throw Error('Invalid model source list response.');
      return data.filter(r=>r&&typeof r==='object'&&(!r.projectId||r.projectId===s.selectedProjectId));
    }
    // (b) Register a model source. No parser: format + revision metadata only.
    async function createSource(input){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      const format=String(input?.format||'').trim();
      if(!FORMATS.includes(format))throw Error('Select a supported model format: '+FORMATS.join(', ')+'.');
      const extractionMode=String(input?.extractionMode||'human_entered').trim();
      if(extractionMode==='native_parser')throw Error('No IFC parser is available. Record metadata with extractionMode human_entered or structured_export. Nothing was submitted.');
      if(!EXTRACTION_MODES.includes(extractionMode))throw Error('extractionMode must be human_entered or structured_export.');
      const assumptionsIn=Array.isArray(input?.assumptions)?input.assumptions:[];
      if(assumptionsIn.length>50)throw Error('At most 50 assumptions are allowed.');
      const body={
        title:required(input?.title,220,'Model source title'),
        format,
        schemaVersion:required(input?.schemaVersion,80,'Schema version'),
        sourceRevision:required(input?.sourceRevision,80,'Source revision'),
        extractionMode,
        providerName:optional(input?.providerName,180,'Provider name'),
        responsibleRole:role(input?.responsibleRole),
        assumptions:assumptionsIn.map(a=>required(a,500,'Assumption')),
      };
      const saved=unwrap(await context.write(listPath(s),body,{readBackPath:r=>sourcePath(r?.id)}));current(s);
      if(!saved||!isId(saved.id))throw Error('The model source was not created.');
      const record=unwrap(await context.read(sourcePath(saved.id)));current(s);
      if(!record||record.id!==saved.id||record.projectId!==s.selectedProjectId||record.status!=='draft')throw Error('Model source persistence not verified; refresh before retrying.');
      return record;
    }
    // (c) Add a metadata revision (facts + notes). Concurrency guarded by expectedVersion.
    async function addRevision(input){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      const sourceId=input?.sourceId;
      const source=await loadSource(s,sourceId);
      if(source.status!=='draft')throw Error('This model source is already issued; no further revisions can be added. Nothing was submitted.');
      const body={
        expectedVersion:source.version,
        revisionLabel:required(input?.revisionLabel,120,'Revision label'),
        facts:facts(input?.facts),
        notes:optional(input?.notes,4000,'Notes'),
      };
      const saved=unwrap(await context.write(sourcePath(sourceId)+'/revisions',body,{readBackPath:sourcePath(sourceId),readBackCollection:'revisions'}));current(s);
      if(!saved||!isId(saved.id))throw Error('The metadata revision was not created.');
      const detail=await loadSource(s,sourceId);
      const record=(detail.revisions||[]).find(r=>r.id===saved.id);
      if(!record)throw Error('Revision persistence not verified; refresh before retrying.');
      return {source:detail,revision:record};
    }
    // (d) Record an approve / reject / flag mapping decision on a revision.
    async function decide(input){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(input?.humanConfirmed!==true)throw Error('A mapping decision requires explicit human confirmation.');
      const action=String(input?.action||'').trim();
      if(!DECISION_ACTIONS.includes(action))throw Error('Decision action must be approve, reject or flag.');
      const sourceId=input?.sourceId,revisionId=input?.revisionId;
      const source=await loadSource(s,sourceId);
      if(source.status!=='draft')throw Error('This model source is already issued; decisions are closed. Nothing was submitted.');
      const revision=(source.revisions||[]).find(r=>r.id===revisionId);
      if(!revision)throw Error('The selected revision does not belong to this model source.');
      if(revision.status==='issued')throw Error('This revision is already issued.');
      const body={
        actingRole:role(input?.actingRole||source.responsibleRole),
        action,
        humanConfirmed:true,
        reason:required(input?.reason,500,'Decision reason'),
      };
      const saved=unwrap(await context.write('/model-metadata-revisions/'+encodeURIComponent(revisionId)+'/decisions',body,{readBackPath:sourcePath(sourceId),readBackCollection:'revisions'}));current(s);
      if(!saved||!isId(saved.id))throw Error('The decision was not recorded.');
      const detail=await loadSource(s,sourceId);
      const record=(detail.revisions||[]).find(r=>r.id===revisionId);
      if(!record||record.status!==DECISION_STATUS[action])throw Error('Decision persistence not verified; refresh before retrying.');
      return {source:detail,revision:record};
    }
    // (e) Professionally issue an approved revision (freezes a snapshot hash).
    async function issue(input){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(input?.humanConfirmed!==true)throw Error('Issuing model metadata requires explicit professional confirmation.');
      const sourceId=input?.sourceId,revisionId=input?.revisionId;
      const source=await loadSource(s,sourceId);
      if(source.status!=='draft')throw Error('This model source is already issued. Nothing was submitted.');
      const revision=(source.revisions||[]).find(r=>r.id===revisionId);
      if(!revision||revision.status!=='approved')throw Error('Only an approved metadata revision can be issued. Nothing was submitted.');
      const body={
        revisionId,
        expectedVersion:source.version,
        humanConfirmed:true,
        professionalRole:role(input?.professionalRole||source.responsibleRole),
        issueReason:required(input?.issueReason,500,'Issue reason'),
      };
      const saved=unwrap(await context.write(sourcePath(sourceId)+'/issue',body,{readBackPath:sourcePath(sourceId)}));current(s);
      if(!saved||!isId(saved.id))throw Error('The model source issue was not accepted.');
      const detail=await loadSource(s,sourceId);
      if(!detail||detail.status!=='issued'||detail.issuedRevisionId!==revisionId||!detail.snapshotSha256)throw Error('Model metadata issue not verified; refresh before retrying.');
      return detail;
    }
    return {listSources,createSource,addRevision,decide,issue,formats:FORMATS.slice(),extractionModes:EXTRACTION_MODES.slice(),categories:CATEGORIES.slice(),decisionActions:DECISION_ACTIONS.slice(),boundaries:{nativeParsing:false,externalProviderVerified:false}};
  }
  root.V1CloneModelsAdapter={create,formats:FORMATS.slice(),boundaries:{nativeParsing:false,externalProviderVerified:false}};
})(window);
