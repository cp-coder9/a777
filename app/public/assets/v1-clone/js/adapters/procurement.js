(function(root){
  'use strict';
  // rfq_marketplace adapter — procurement packages + RFQ marketplace (Tier-1).
  // Mirrors adapters/site-tools.js exactly: scope()/current() guards, project-scoped
  // writes, context.write read-back verification, explicit human confirmation and no
  // fabricated success. Honest boundaries enforced: there is NO automatic appointment
  // path here (award/appointment routes are deliberately not exposed); addenda are
  // equal-information and must answer real material clarifications; comparisons are
  // advisory and every quote keeps its missing returnables and exclusions visible.
  const unwrap=r=>r?.data??r;
  const ID=/^[a-f0-9]{32}$/i;
  const METHODS=['open_tender','invited_tender','rfq','direct_appointment'];
  const COMPLEXITIES=['low','medium','high'];
  const URGENCIES=['normal','urgent','emergency'];
  const CRITERIA_KEYS=['price','returnables','lead_time','risk','conflict'];
  function create(context){
    function scope(){const s=context.getState();if(!s.ready||!s.session||!s.organisationId||!ID.test(s.selectedProjectId||''))throw Error('Select an authenticated project.');return {...s};}
    function current(s){const now=context.getState();if(!now.ready||!now.session||now.selectedProjectId!==s.selectedProjectId||now.organisationId!==s.organisationId||now.session!==s.session)throw Error('Scope changed; reload this workspace.');}
    const listPath=s=>'/projects/'+encodeURIComponent(s.selectedProjectId)+'/procurement-packages';
    const packagePath=id=>'/procurement-packages/'+encodeURIComponent(id);
    const workspacePath=id=>packagePath(id)+'/workspace';
    function packages(data,s){data=unwrap(data);if(!Array.isArray(data))throw Error('Invalid procurement package list response.');return data.filter(r=>r&&(!r.projectId||r.projectId===s.selectedProjectId));}
    function identifier(id){if(!ID.test(id||''))throw Error('A real procurement package identifier is required.');return id;}
    function tokens(v){if(!Array.isArray(v))return [];return v.map(item=>String(item).trim()).filter(Boolean);}
    function criteria(v){
      const out={};let total=0;
      CRITERIA_KEYS.forEach(key=>{const n=Number(v?.[key]??0);const safe=Number.isFinite(n)&&n>=0&&n<=100?n:0;out[key]=safe;total+=safe;});
      if(Math.abs(total-100)>0.001)throw Error('Evaluation criteria weights must total 100.');
      return out;
    }
    async function listPackages(){
      const s=scope();const records=packages(await context.read(listPath(s)),s);current(s);return records;
    }
    async function workspace(packageId){
      const s=scope();identifier(packageId);
      const data=unwrap(await context.read(workspacePath(packageId)));current(s);
      if(!data||typeof data!=='object')throw Error('Invalid procurement workspace response.');
      return data;
    }
    async function createPackage(input){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(input.humanConfirmed!==true)throw Error('Package creation requires explicit human confirmation.');
      if(context.capabilities?.readBackCollection!==true)throw Error('Package save unavailable: shared collection read-back support is required. Nothing was submitted.');
      const method=METHODS.includes(input.procurementMethod)?input.procurementMethod:null;
      if(!method)throw Error('Select a valid procurement method.');
      const complexity=COMPLEXITIES.includes(input.complexity)?input.complexity:null;
      const urgency=URGENCIES.includes(input.urgency)?input.urgency:null;
      if(!complexity||!urgency)throw Error('Select a valid complexity and urgency.');
      const documents=[].concat(input.documentRevisionIds||[]).filter(id=>ID.test(id));
      if(documents.length===0)throw Error('At least one controlled document revision issued for tender is required.');
      const returnables=tokens(input.requiredReturnables);
      if(returnables.length===0)throw Error('At least one required returnable must be listed.');
      const trades=tokens(input.requiredTrades),specialists=tokens(input.requiredSpecialists);
      if(trades.length===0&&specialists.length===0)throw Error('List at least one required trade or specialist scope.');
      const need=String(input.procurementNeed||input.scopeSummary||'').trim();
      if(!need)throw Error('A procurement need or scope summary is required.');
      const deadline=String(input.deadlineAt||'').trim();
      if(!deadline)throw Error('A future submission deadline is required.');
      const body={procurementNeed:need,packageNumber:String(input.packageNumber||'').trim(),title:String(input.title||'').trim(),
        procurementMethod:method,methodReason:String(input.methodReason||'').trim(),scopeSummary:String(input.scopeSummary||'').trim(),
        estimatedValueZar:Number(input.estimatedValueZar),complexity,urgency,
        province:String(input.province||'').trim(),location:String(input.location||'').trim(),
        requiredTrades:trades,requiredSpecialists:specialists,requiredReturnables:returnables,
        evaluationCriteria:criteria(input.evaluationCriteria),documentRevisionIds:documents,
        municipalReadinessScore:Number(input.municipalReadinessScore),deadlineAt:deadline};
      const saved=unwrap(await context.write(listPath(s),body,{readBackPath:listPath(s)}));current(s);
      const data=packages(await context.read(listPath(s)),s);current(s);
      const record=data.find(r=>r.id===saved?.id);if(!record)throw Error('Package persistence not verified; refresh before retrying.');return record;
    }
    async function issuePackage(packageId,{expectedVersion,reason,humanConfirmed=false}={}){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(humanConfirmed!==true)throw Error('Package issue requires explicit human confirmation.');
      identifier(packageId);
      if(!Number.isInteger(expectedVersion)||expectedVersion<1)throw Error('The current package version is required to issue.');
      if(!reason||!String(reason).trim())throw Error('A reason is required to issue a package.');
      const body={expectedVersion,humanConfirmed:true,reason:String(reason).trim()};
      const saved=unwrap(await context.write(packagePath(packageId)+'/issue',body,{readBackPath:packagePath(packageId)}));current(s);
      const record=unwrap(await context.read(packagePath(packageId)));current(s);
      if(!record||record.id!==saved?.id||record.status!=='issued')throw Error('Package issue not verified; refresh before retrying.');
      return record;
    }
    // Advisory-only marketplace matching for an issued package. Nothing is appointed.
    async function matches(packageId){
      const s=scope();identifier(packageId);
      const listed=unwrap(await context.write(packagePath(packageId)+'/matches',{},{readBackPath:packagePath(packageId)}));current(s);
      if(!Array.isArray(listed))throw Error('Marketplace match response was not a list.');
      return listed.map(m=>({listing:m?.listing??null,score:m?.score??0,reasons:m?.reasons??[],conflict:m?.conflict===true,supervisionRequired:m?.supervisionRequired===true,advisoryOnly:true}));
    }
    async function invite(packageId,{listingId,humanConfirmed=false}={}){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(humanConfirmed!==true)throw Error('Bidder invitation requires explicit human confirmation.');
      identifier(packageId);
      if(!ID.test(listingId||''))throw Error('A real marketplace listing identifier is required.');
      const saved=unwrap(await context.write(packagePath(packageId)+'/invitations',{listingId,humanConfirmed:true},{readBackPath:workspacePath(packageId),readBackCollection:'invitations'}));current(s);
      const ws=await workspace(packageId);
      const record=(ws.invitations||[]).find(r=>r.id===saved?.id);
      if(!record)throw Error('Invitation persistence not verified; refresh before retrying.');
      return record;
    }
    // Material clarifications are answered once and distributed to every active bidder.
    async function addenda(packageId,{title,content,reason,clarificationIds,humanConfirmed=false}={}){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(humanConfirmed!==true)throw Error('Addendum issue requires explicit human confirmation.');
      identifier(packageId);
      const clarifications=[].concat(clarificationIds||[]).filter(id=>ID.test(id));
      if(clarifications.length===0)throw Error('An addendum must reference at least one submitted material clarification id.');
      const body={title:String(title||'').trim(),content:String(content||'').trim(),reason:String(reason||'').trim(),clarificationIds:clarifications,humanConfirmed:true};
      const saved=unwrap(await context.write(packagePath(packageId)+'/addenda',body,{readBackPath:workspacePath(packageId),readBackCollection:'addenda'}));current(s);
      const ws=await workspace(packageId);
      const record=(ws.addenda||[]).find(r=>r.id===saved?.id);
      if(!record)throw Error('Addendum persistence not verified; refresh before retrying.');
      if(record.equalInformationCompliant!==true)throw Error('Addendum was not recorded as equal-information.');
      return record;
    }
    async function comparisons(packageId){
      const s=scope();identifier(packageId);
      if(context.capabilities?.readBackCollection!==true)throw Error('Comparison save unavailable: shared collection read-back support is required. Nothing was submitted.');
      const saved=unwrap(await context.write(packagePath(packageId)+'/comparisons',{},{readBackPath:workspacePath(packageId),readBackCollection:'comparisons'}));current(s);
      const ws=await workspace(packageId);
      const record=(ws.comparisons||[]).find(r=>r.id===saved?.id);
      if(!record)throw Error('Comparison snapshot not verified; refresh before retrying.');
      return {comparison:record,quotes:ws.quotes||[],invitations:ws.invitations||[],clarifications:ws.clarifications||[],addenda:ws.addenda||[]};
    }
    return {listPackages,workspace,createPackage,issuePackage,matches,invite,addenda,comparisons};
  }
  root.V1CloneProcurementAdapter={create,METHODS,COMPLEXITIES,URGENCIES,CRITERIA_KEYS};
})(window);
