(function(root){
  'use strict';
  // FM Bridge adapter — close-out, practical completion, digital handover pack and
  // occupation readiness. Backed by the verified CloseoutService / CloseoutRoutes
  // (closeout_practical_completions, closeout_handover_packs, closeout_handover_documents,
  // closeout_occupation_readiness). Follows the site-tools adapter contract exactly:
  // scope(), current(), context.write with an exact readBackPath, an explicit read-back
  // verification of the returned id, and never a fabricated success.
  //
  // Honest boundary: the closeout API supplies no external facilities-management (FM/CAFM)
  // synchronisation, no asset-register export and no municipal approval. Every write is an
  // internal controlled record; issueHandoverPack and issuePracticalCompletion produce a
  // professional-authored internal record, not a certificate and not statutory approval.
  const BOUNDARY='Internal close-out and handover records only — the closeout API provides no external facilities-management synchronisation, asset-register export or municipal approval.';
  const EXTERNAL_FM_SYNC_SUPPORTED=false;
  // Categories the backend accepts on a handover document. The first five are the
  // categories that must all be present (status=verified) before a pack can be issued.
  const CATEGORIES=['as_built','warranty','guarantee','om_manual','keys_access','compliance_certificate','other'];
  const REQUIRED_CATEGORIES=['as_built','warranty','om_manual','keys_access','compliance_certificate'];
  const unwrap=r=>r?.data??r;
  function create(context){
    function scope(){const s=context.getState();if(!s.ready||!s.session||!s.organisationId||!/^[a-f0-9]{32}$/i.test(s.selectedProjectId||''))throw Error('Select an authenticated project.');return {...s};}
    const path=(s,name)=>'/projects/'+encodeURIComponent(s.selectedProjectId)+'/'+name;
    function current(s){const now=context.getState();if(!now.ready||!now.session||now.selectedProjectId!==s.selectedProjectId||now.organisationId!==s.organisationId||now.session!==s.session)throw Error('Scope changed; reload this workspace.');}
    // The closeout projections strip organisation/project keys, so rows are validated by
    // collection shape only — the exact read-back identity check runs on the record id.
    function rows(data,key){if(!Array.isArray(data?.[key]))throw Error('Invalid '+key+' response.');return data[key];}
    function identity(value,label){if(!/^[a-f0-9]{32}$/i.test(String(value||'')))throw Error('A valid '+label+' id is required.');return String(value).toLowerCase();}
    function reasonText(value,label,required){const text=typeof value==='string'?value.trim():'';if(required&&!text)throw Error('A '+label+' reason is required.');return text||null;}
    function boolMap(value,label){if(!value||typeof value!=='object'||Array.isArray(value)||Object.keys(value).length===0)throw Error('A '+label+' checklist is required.');const out={};for(const key of Object.keys(value)){if(typeof value[key]!=='boolean'||!key)throw Error('Every '+label+' checklist value must be true or false.');out[key]=value[key];}return out;}

    async function readWorkspace(s){const data=unwrap(await context.read(path(s,'closeout-workspace')));current(s);return data;}
    async function verify(s,collection,id){const data=await readWorkspace(s);const record=rows(data,collection).find(r=>r.id===id);if(!record)throw Error('Close-out persistence not verified; refresh before retrying.');return record;}

    // Full close-out projection: the only closeout read this backend exposes. External FM
    // synchronisation is not part of it and is reported as unsupported rather than faked.
    async function listCloseout(){
      const s=scope();current(s);
      const data=await readWorkspace(s);
      return {
        projectId:s.selectedProjectId,
        organisationId:s.organisationId,
        practicalCompletions:rows(data,'practicalCompletions'),
        handoverPacks:rows(data,'handoverPacks'),
        handoverDocuments:rows(data,'handoverDocuments'),
        occupationReadiness:rows(data,'occupationReadiness'),
        defects:rows(data,'defects'),
        defectsLiabilityPeriods:rows(data,'defectsLiabilityPeriods'),
        finalAccounts:rows(data,'finalAccounts'),
        recommendations:rows(data,'recommendations'),
        boundaries:data?.boundaries&&typeof data.boundaries==='object'?data.boundaries:{},
        externalFmSyncSupported:EXTERNAL_FM_SYNC_SUPPORTED,
        boundary:BOUNDARY,
      };
    }
    async function createPracticalCompletion(input){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      const checklist=boolMap(input?.checklist,'completion');
      const saved=unwrap(await context.write(path(s,'practical-completions'),{checklist,humanConfirmed:true},{readBackPath:path(s,'closeout-workspace'),readBackCollection:'practicalCompletions'}));current(s);
      return verify(s,'practicalCompletions',saved?.id);
    }
    async function issueCompletion(completionId,{reason,humanConfirmed=false}={}){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(humanConfirmed!==true)throw Error('Practical-completion issue requires explicit human confirmation.');
      const id=identity(completionId,'practical-completion');const text=reasonText(reason,'issue',true);
      const existing=await verify(s,'practicalCompletions',id);
      if(existing.status!=='ready_for_issue')throw Error('The practical-completion record is not ready for issue (status: '+existing.status+'). Resolve its blockers first. Nothing was issued.');
      unwrap(await context.write('/practical-completions/'+encodeURIComponent(id)+'/issue',{reason:text,humanConfirmed:true},{readBackPath:path(s,'closeout-workspace'),readBackCollection:'practicalCompletions'}));current(s);
      const record=await verify(s,'practicalCompletions',id);
      if(record.status!=='issued')throw Error('Practical-completion issue not verified; refresh before retrying.');
      return record;
    }
    async function acceptCompletion(completionId,{reason,humanConfirmed=false}={}){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(humanConfirmed!==true)throw Error('Client acceptance of practical completion requires explicit human confirmation.');
      const id=identity(completionId,'practical-completion');const text=reasonText(reason,'acceptance',true);
      const existing=await verify(s,'practicalCompletions',id);
      if(existing.status!=='issued')throw Error('Only an issued practical-completion record can be accepted (status: '+existing.status+'). Nothing was accepted.');
      unwrap(await context.write('/practical-completions/'+encodeURIComponent(id)+'/accept',{reason:text,humanConfirmed:true},{readBackPath:path(s,'closeout-workspace'),readBackCollection:'practicalCompletions'}));current(s);
      const record=await verify(s,'practicalCompletions',id);
      if(record.status!=='client_accepted')throw Error('Client acceptance not verified; refresh before retrying.');
      return record;
    }
    async function createHandoverPack(){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      const saved=unwrap(await context.write(path(s,'handover-packs'),{}, {readBackPath:path(s,'closeout-workspace'),readBackCollection:'handoverPacks'}));current(s);
      const record=await verify(s,'handoverPacks',saved?.id);
      if(!Array.isArray(record.blockers)||record.blockers.length===0)throw Error('Handover pack persistence not verified; refresh before retrying.');
      return {...record,requiredCategories:REQUIRED_CATEGORIES.slice(),externalFmSyncSupported:EXTERNAL_FM_SYNC_SUPPORTED};
    }
    // A handover document must cite evidence the project already owns: a stored file or a
    // controlled-document revision. The backend rejects anything else; so does this guard.
    async function addHandoverDocument(packId,{category,title,versionLabel,fileId=null,documentRevisionId=null}={}){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      const id=identity(packId,'handover pack');
      if(!CATEGORIES.includes(category))throw Error('A valid handover category is required ('+CATEGORIES.join(', ')+').');
      const name=typeof title==='string'?title.trim():'';if(!name)throw Error('A handover document title is required.');
      const version=typeof versionLabel==='string'?versionLabel.trim():'';if(!version)throw Error('A handover document version label is required.');
      const file=fileId?identity(fileId,'stored file'):null;
      const revision=documentRevisionId?identity(documentRevisionId,'document revision'):null;
      if(file===null&&revision===null)throw Error('A handover document needs evidence: an owned stored file id or a controlled-document revision id. Nothing was saved.');
      const saved=unwrap(await context.write('/handover-packs/'+encodeURIComponent(id)+'/documents',{category,title:name,versionLabel:version,fileId:file,documentRevisionId:revision},{readBackPath:path(s,'closeout-workspace'),readBackCollection:'handoverDocuments'}));current(s);
      const data=await readWorkspace(s);
      const record=rows(data,'handoverDocuments').find(r=>r.id===saved?.id);
      if(!record||record.status!=='verified')throw Error('Handover document persistence not verified; refresh before retrying.');
      return record;
    }
    async function refreshPack(packId){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      const id=identity(packId,'handover pack');
      unwrap(await context.write('/handover-packs/'+encodeURIComponent(id)+'/refresh',{}, {readBackPath:path(s,'closeout-workspace'),readBackCollection:'handoverPacks'}));current(s);
      const record=await verify(s,'handoverPacks',id);
      return {...record,requiredCategories:REQUIRED_CATEGORIES.slice(),externalFmSyncSupported:EXTERNAL_FM_SYNC_SUPPORTED};
    }
    async function issuePack(packId,{reason,humanConfirmed=false}={}){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(humanConfirmed!==true)throw Error('Handover pack issue requires explicit human confirmation.');
      const id=identity(packId,'handover pack');const text=reasonText(reason,'issue',true);
      unwrap(await context.write('/handover-packs/'+encodeURIComponent(id)+'/refresh',{}, {readBackPath:path(s,'closeout-workspace'),readBackCollection:'handoverPacks'}));current(s);
      const refreshed=await verify(s,'handoverPacks',id);
      if(refreshed.status!=='ready_for_issue')throw Error('The handover pack is incomplete (missing: '+(Array.isArray(refreshed.blockers)?refreshed.blockers.join(', '):'unknown categories')+'). Nothing was issued.');
      unwrap(await context.write('/handover-packs/'+encodeURIComponent(id)+'/issue',{reason:text,humanConfirmed:true},{readBackPath:path(s,'closeout-workspace'),readBackCollection:'handoverPacks'}));current(s);
      const record=await verify(s,'handoverPacks',id);
      if(record.status!=='issued')throw Error('Handover pack issue not verified; refresh before retrying.');
      return {...record,externalFmSyncSupported:EXTERNAL_FM_SYNC_SUPPORTED,boundary:BOUNDARY};
    }
    // Occupation readiness is internal evidence review. It is not a municipal approval:
    // the backend always returns representsMunicipalApproval=false, and a certificate file
    // must already be owned by the project organisation.
    async function occupationReadiness(input){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      const checklist=boolMap(input?.utilitiesChecklist,'utilities');
      const file=input?.municipalCertificateFileId?identity(input.municipalCertificateFileId,'municipal certificate file'):null;
      const body={municipalCertificateFileId:file,municipalCertificateVerified:input?.municipalCertificateVerified===true,insuranceTransitionConfirmed:input?.insuranceTransitionConfirmed===true,utilitiesChecklist:checklist,humanConfirmed:true};
      const saved=unwrap(await context.write(path(s,'occupation-readiness'),body,{readBackPath:path(s,'closeout-workspace'),readBackCollection:'occupationReadiness'}));current(s);
      const record=await verify(s,'occupationReadiness',saved?.id);
      return {...record,representsMunicipalApproval:record.representsMunicipalApproval===true};
    }
    return {boundary:BOUNDARY,categories:CATEGORIES.slice(),requiredCategories:REQUIRED_CATEGORIES.slice(),externalFmSyncSupported:EXTERNAL_FM_SYNC_SUPPORTED,listCloseout,createPracticalCompletion,issueCompletion,acceptCompletion,createHandoverPack,addHandoverDocument,refreshPack,issuePack,occupationReadiness};
  }
  root.V1CloneCloseoutAdapter={create,boundary:BOUNDARY,categories:CATEGORIES.slice(),requiredCategories:REQUIRED_CATEGORIES.slice(),externalFmSyncSupported:EXTERNAL_FM_SYNC_SUPPORTED};
})(window);
