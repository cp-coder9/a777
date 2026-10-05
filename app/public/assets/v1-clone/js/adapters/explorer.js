(function(root){
  'use strict';
  // Project Explorer adapter — a bounded search/browse surface over the real
  // project records that the operating-snapshot endpoint exposes, joined with the
  // controlled-document register for the selected project. Mirrors the site-tools
  // family: scope()/current() guards, project-scoped writes, read-back verification
  // and explicit human confirmation. Nothing is fabricated and no record editor,
  // asset-wide search or archive is claimed beyond what the backend returns.
  const aliases={project_explorer:'project_explorer'};
  const unwrap=r=>r?.data??r;
  const ID=/^[a-f0-9]{32}$/i;
  const DOC_TYPES=['drawing','specification','appointment','approval_letter','municipal_form','submission_pack','tender_pack','rfi','site_instruction','payment_certificate','closeout_certificate','warranty','closeout_pack'];
  const DISCIPLINES=['architectural','structural','civil','electrical','mechanical','fire','quantity_surveying','general'];
  const PURPOSES=['for_review','for_information','for_municipal_submission','for_tender','for_construction','as_built','closeout'];
  function create(context){
    function scope(){const s=context.getState();if(!s.ready||!s.session||!s.organisationId||!ID.test(s.selectedProjectId||''))throw Error('Select an authenticated project.');return {...s};}
    function current(s){const now=context.getState();if(!now.ready||!now.session||now.selectedProjectId!==s.selectedProjectId||now.organisationId!==s.organisationId||now.session!==s.session)throw Error('Scope changed; reload this workspace.');}
    const snapshotPath=s=>'/projects/'+encodeURIComponent(s.selectedProjectId)+'/operating-snapshot';
    const listPath=s=>'/documents?projectId='+encodeURIComponent(s.selectedProjectId);
    // The operating-snapshot returns a bounded {records:[...]} projection of the
    // project's own records across source modules. Reject anything else.
    function snapshotRows(data,s){data=unwrap(data);if(!Array.isArray(data?.records))throw Error('Invalid operating-snapshot response.');return data.records.filter(r=>r&&(!r.projectId||r.projectId===s.selectedProjectId)&&(!r.organisationId||r.organisationId===s.organisationId));}
    function documentRows(data,s){data=unwrap(data);if(!Array.isArray(data))throw Error('Invalid document list response.');return data.filter(r=>r&&(!r.projectId||r.projectId===s.selectedProjectId)&&(!r.organisationId||r.organisationId===s.organisationId));}
    // Flatten snapshot records + controlled-document revisions into one browsable row shape.
    function normalise(records,docs){
      const rows=[];
      records.forEach(r=>rows.push({kind:'record',id:r.id,sourceModule:r.sourceModule||'project',recordType:r.recordType||'record',title:r.title||'(untitled)',status:r.status||'—',revisionCode:null,discipline:null,issuePurpose:null,createdAt:r.createdAt||null}));
      docs.forEach(r=>rows.push({kind:'document',id:r.id,documentId:r.documentId||null,sourceModule:'controlled_documents',recordType:r.documentType||'document',title:r.title||'(untitled)',status:r.status||'—',revisionCode:r.revisionCode||null,discipline:r.discipline||null,issuePurpose:r.issuePurpose||null,createdAt:r.createdAt||null}));
      return rows;
    }
    // Pure client-side filter over an already-loaded record set. No server call,
    // no hidden paging: only what the bounded endpoints returned is searchable.
    function searchRecords(query,rows){
      const q=String(query==null?'':query).trim().toLowerCase();
      const list=Array.isArray(rows)?rows:[];
      if(!q)return list.slice();
      return list.filter(r=>[r.title,r.recordType,r.sourceModule,r.status,r.revisionCode,r.discipline,r.issuePurpose]
        .some(v=>String(v==null?'':v).toLowerCase().includes(q)));
    }
    async function listRecords(){const s=scope();const records=snapshotRows(await context.read(snapshotPath(s)),s);current(s);return records;}
    async function listDocuments(){const s=scope();const records=documentRows(await context.read(listPath(s)),s);current(s);return records;}
    async function load(id){
      if(!aliases[id])throw Error('Unsupported explorer tool.');
      const s=scope();
      const snap=await context.read(snapshotPath(s));
      const docs=await context.read(listPath(s));
      current(s);
      const records=snapshotRows(snap,s),documents=documentRows(docs,s);
      const rows=normalise(records,documents);
      return {projectId:s.selectedProjectId,organisationId:s.organisationId,projectName:s.projects?.find(p=>p.id===s.selectedProjectId)?.name||'Selected project',records:rows,recordCount:records.length,documentCount:documents.length};
    }
    // Create a real controlled-document draft (first revision) for the selected
    // project, then read the register back and confirm the exact revision landed.
    async function createDocument(input){
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
      const body={projectId:s.selectedProjectId,title:String(input.title).trim(),documentType:type,discipline,issuePurpose:purpose,revisionCode:String(input.revisionCode).trim(),notes:input.notes??''};
      const saved=unwrap(await context.write(listPath(s),body,{readBackPath:listPath(s)}));current(s);
      const data=documentRows(await context.read(listPath(s)),s);current(s);
      const record=data.find(r=>r.id===saved?.id);if(!record)throw Error('Document persistence not verified; refresh before retrying.');return record;
    }
    return {load,listRecords,listDocuments,searchRecords,createDocument};
  }
  root.V1CloneExplorerAdapter={create,aliases,DOC_TYPES,DISCIPLINES,PURPOSES};
})(window);
