(function(root){
  'use strict';
  // Controlled documents & drawings — controlled_documents / document_revisions /
  // drawing_register_entries, verified by live read-back. Mirrors the site-tools
  // family: scope()/current() guards, project-scoped writes, read-back verification
  // and explicit human confirmation. No external transmittal or provider is claimed.
  const unwrap=r=>r?.data??r;
  const DOC_TYPES=['drawing','specification','appointment','approval_letter','municipal_form','submission_pack','tender_pack','rfi','site_instruction','payment_certificate','closeout_certificate','warranty','closeout_pack'];
  const DISCIPLINES=['architectural','structural','civil','electrical','mechanical','fire','quantity_surveying','general'];
  const PURPOSES=['for_review','for_information','for_municipal_submission','for_tender','for_construction','as_built','closeout'];
  const SHEETS=['site_plan','floor_plan','section','elevation','detail','schedule','cover','general'];
  const ID=/^[a-f0-9]{32}$/i;
  function create(context){
    function scope(){const s=context.getState();if(!s.ready||!s.session||!s.organisationId||!ID.test(s.selectedProjectId||''))throw Error('Select an authenticated project.');return {...s};}
    function current(s){const now=context.getState();if(!now.ready||!now.session||now.selectedProjectId!==s.selectedProjectId||now.organisationId!==s.organisationId||now.session!==s.session)throw Error('Scope changed; reload this workspace.');}
    const listPath=s=>'/documents?projectId='+encodeURIComponent(s.selectedProjectId);
    const revisionPath=id=>'/documents/'+encodeURIComponent(id);
    function rows(data,s){data=unwrap(data);if(!Array.isArray(data))throw Error('Invalid document list response.');return data.filter(r=>r&&(!r.projectId||r.projectId===s.selectedProjectId)&&(!r.organisationId||r.organisationId===s.organisationId));}
    function identifier(id){if(!ID.test(id||''))throw Error('A real document revision identifier is required.');return id;}
    async function list(){const s=scope();const records=rows(await context.read(listPath(s)),s);current(s);return records;}
    async function load(){const s=scope();return {projectId:s.selectedProjectId,organisationId:s.organisationId,projectName:s.projects?.find(p=>p.id===s.selectedProjectId)?.name||'Selected project',documents:await list()};}
    // Create a document (first revision = draft). Drawings populate the drawing register.
    async function create(input){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(input.humanConfirmed!==true)throw Error('Document creation requires explicit human confirmation.');
      if(context.capabilities?.readBackCollection!==true)throw Error('Draft document save unavailable: shared collection read-back support is required. Nothing was submitted.');
      const type=DOC_TYPES.includes(input.documentType)?input.documentType:null;
      const discipline=DISCIPLINES.includes(input.discipline)?input.discipline:null;
      const purpose=PURPOSES.includes(input.issuePurpose)?input.issuePurpose:null;
      if(!type)throw Error('A valid document type is required.');
      if(!discipline)throw Error('A valid discipline is required.');
      if(!purpose)throw Error('A valid issue purpose is required.');
      if(!input.title||!String(input.title).trim())throw Error('A document title is required.');
      if(!input.revisionCode||!String(input.revisionCode).trim())throw Error('A revision code is required.');
      const body={projectId:s.selectedProjectId,title:String(input.title).trim(),documentType:type,discipline,issuePurpose:purpose,revisionCode:String(input.revisionCode).trim(),notes:input.notes??'',fileId:input.fileId||null,authorRole:input.authorRole||null,reviewerRole:input.reviewerRole||null,phase:input.phase||null};
      if(type==='drawing'){
        const d=input.drawing||{};
        if(!d.drawingNumber||!String(d.drawingNumber).trim())throw Error('A drawing number is required for a drawing document.');
        if(!SHEETS.includes(d.sheetType))throw Error('A valid drawing sheet type is required.');
        body.drawing={drawingNumber:String(d.drawingNumber).trim(),sheetType:d.sheetType,scale:d.scale||null};
      }
      const saved=unwrap(await context.write(listPath(s),body,{readBackPath:listPath(s)}));current(s);
      const data=rows(await context.read(listPath(s)),s);current(s);
      const record=data.find(r=>r.id===saved?.id);if(!record)throw Error('Document persistence not verified; refresh before retrying.');return record;
    }
    // Add a revision of an issued document -> new draft revision (supersedes the issued rev).
    async function createRevisionTransition(revisionId,input){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(input.humanConfirmed!==true)throw Error('Creating a revision requires explicit human confirmation.');
      identifier(revisionId);
      if(!input.revisionCode||!String(input.revisionCode).trim())throw Error('A revision code is required.');
      if(!input.reason||!String(input.reason).trim())throw Error('A revision reason is required.');
      const body={revisionCode:String(input.revisionCode).trim(),reason:String(input.reason).trim(),notes:input.notes??'',issuePurpose:PURPOSES.includes(input.issuePurpose)?input.issuePurpose:'for_review',fileId:input.fileId||null};
      const saved=unwrap(await context.write(revisionPath(revisionId)+'/revisions',body,{readBackPath:rec=>revisionPath(rec?.id)}));current(s);
      const record=unwrap(await context.read(revisionPath(saved?.id)));current(s);
      if(!record||record.id!==saved?.id||record.status!=='draft'||record.previousRevisionId!==revisionId)throw Error('New revision not verified; refresh before retrying.');return record;
    }
    function transition(tool,from,to){
      return async function(revisionId,{reason,issuePurpose=null,humanConfirmed=false}={}){
        const s=scope();if(s.readOnly)throw Error('Read-only session.');
        if(humanConfirmed!==true)throw Error(tool+' requires explicit human confirmation.');
        identifier(revisionId);
        if(!reason||!String(reason).trim())throw Error('A reason is required for '+tool+'.');
        const body={reason:String(reason).trim(),humanConfirmed:true};if(issuePurpose&&PURPOSES.includes(issuePurpose))body.issuePurpose=issuePurpose;
        const saved=unwrap(await context.write(revisionPath(revisionId)+'/'+tool,body,{readBackPath:revisionPath(revisionId)}));current(s);
        const record=unwrap(await context.read(revisionPath(revisionId)));current(s);
        if(!record||record.id!==revisionId||record.id!==saved?.id||record.status!==to)throw Error(tool+' not verified; refresh before retrying.');return record;
      };
    }
    const submitReview=transition('submit-review','draft','pending_review');
    const approve=transition('approve','pending_review','approved');
    const issue=transition('issue','approved','issued');
    return {load,list,create,createRevisionTransition,submitReview,approve,issue};
  }
  root.V1CloneDocumentsAdapter={create,DOC_TYPES,DISCIPLINES,PURPOSES,SHEETS};
})(window);
