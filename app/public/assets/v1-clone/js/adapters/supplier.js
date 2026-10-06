(function(root){
  'use strict';
  // supplier_catalog adapter — supplier register, catalogue revisions, procurement
  // baselines and advisory matching, backed by the verified SupplierProcurementService
  // routes. Honest boundary: this persists catalogue revision metadata and advisory
  // matches only. It never approves an award, issues a purchase order or moves money,
  // and it never invents supplier data. Every write is human-confirmed and
  // read-back-verified against the project supplier-procurement workspace.
  const unwrap=r=>r?.data??r;
  const HEX=/^[a-f0-9]{32}$/i;
  function create(context){
    function scope(){
      const s=context.getState();
      if(!s.ready||!s.session||!s.organisationId||!HEX.test(s.selectedProjectId||''))throw Error('Select an authenticated project.');
      return {...s,projects:(s.projects||[]).slice()};
    }
    function current(s){
      const now=context.getState();
      if(!now.ready||!now.session||now.selectedProjectId!==s.selectedProjectId||now.organisationId!==s.organisationId||now.session!==s.session)throw Error('Scope changed; reload this workspace.');
    }
    const path=(s,name)=>'/projects/'+encodeURIComponent(s.selectedProjectId)+'/'+name;
    const workspace=s=>path(s,'supplier-procurement');
    function collection(data,key){
      if(!data||typeof data!=='object')throw Error('Invalid supplier-procurement workspace response.');
      const list=data[key];
      return Array.isArray(list)?list:[];
    }
    function confirmed(input,what){
      if(!input||input.humanConfirmed!==true)throw Error(what+' requires explicit human confirmation.');
    }
    function id(value,what){
      if(typeof value!=='string'||!HEX.test(value))throw Error('A verified 32-hex '+what+' is required.');
      return value.toLowerCase();
    }
    function text(value,what){
      const out=typeof value==='string'?value.trim():'';
      if(!out)throw Error(what+' is required.');
      return out;
    }
    function num(value,what,min){
      const n=Number(value);
      if(!Number.isFinite(n)||n<min)throw Error(what+' must be a number not less than '+min+'.');
      return n;
    }
    function date(value){
      if(typeof value!=='string'||!/^\d{4}-\d{2}-\d{2}$/.test(value))throw Error('requiredOnSiteDate must be an ISO date (YYYY-MM-DD).');
      return value;
    }
    async function freshWorkspace(s){const data=unwrap(await context.read(workspace(s)));current(s);return data;}
    // Read-only projection of the verified supplier-procurement workspace.
    async function list(){
      const s=scope();
      const data=unwrap(await context.read(workspace(s)));current(s);
      return {
        projectId:s.selectedProjectId,
        organisationId:s.organisationId,
        projectName:s.projects?.find(p=>p.id===s.selectedProjectId)?.name||'Selected project',
        suppliers:collection(data,'suppliers'),
        catalogues:collection(data,'catalogues'),
        baselines:collection(data,'baselines'),
        packages:collection(data,'packages'),
        matches:collection(data,'matches'),
        rfqs:collection(data,'rfqs'),
        availableFiles:collection(data,'availableFiles'),
        access:data?.access||{role:null,canOperate:false,canApprove:false},
        boundaries:data?.boundaries||{aiMayRecommend:true,aiMayApprove:false,movesMoney:false,externalConnectorModes:['manual_ready','catalogue_upload_ready','integration_required']},
      };
    }
    // capabilityLevel 0 = manual, 1 = catalogue upload, 2+ = connector integration required.
    async function createSupplier(input={}){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      confirmed(input,'Registering a supplier');
      const name=text(input.name,'Supplier name');
      const level=input.capabilityLevel;
      if(!Number.isInteger(level)||level<0||level>6)throw Error('capabilityLevel must be an integer 0-6 (0 manual, 1 catalogue upload, 2+ integration required).');
      const regions=[].concat(input.serviceRegions||[]).map(r=>text(r,'Service region'));
      const email=input.contactEmail==null||input.contactEmail===''?null:String(input.contactEmail).trim();
      const body={name,capabilityLevel:level,serviceRegions:regions,contactEmail:email};
      const saved=unwrap(await context.write(path(s,'supplier-procurement/suppliers'),body,{readBackPath:workspace(s),readBackCollection:'suppliers'}));current(s);
      const data=await freshWorkspace(s);
      const record=collection(data,'suppliers').find(r=>r.id===saved?.id);
      if(!record)throw Error('Supplier registration not verified; refresh before retrying.');
      return record;
    }
    // A catalogue revision is per-item metadata for ONE supplier; it is not an
    // approved supplier list and carries no certification claim.
    async function addCatalogue(supplierId,input={}){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      confirmed(input,'Catalogue revision import');
      const supplier=id(supplierId,'supplierId');
      const raw=[].concat(input.items||[]);
      if(raw.length===0)throw Error('At least one catalogue item is required; no revision was created.');
      if(raw.length>500)throw Error('A catalogue revision accepts at most 500 items.');
      const items=raw.map(item=>{
        if(!item||typeof item!=='object')throw Error('Catalogue item must be an object.');
        return {
          sku:text(item.sku,'Catalogue item SKU'),
          name:text(item.name,'Catalogue item name'),
          category:text(item.category,'Catalogue item category'),
          unit:text(item.unit,'Catalogue item unit'),
          priceZar:num(item.priceZar,'priceZar',0),
          stockQuantity:num(item.stockQuantity??0,'stockQuantity',0),
          leadTimeDays:num(item.leadTimeDays??0,'leadTimeDays',0),
          standards:[].concat(item.standards||[]).map(v=>text(v,'standard')),
          deliveryRegions:[].concat(item.deliveryRegions||[]).map(v=>text(v,'delivery region')),
          substitutionEligible:item.substitutionEligible===true,
        };
      });
      const body={
        sourceKind:input.sourceKind==='csv_upload'?'csv_upload':'manual',
        sourceFileId:input.sourceFileId?String(input.sourceFileId).trim():null,
        idempotencyKey:text(input.idempotencyKey||('supplier-catalogue-'+Date.now()+'-'+Math.random().toString(36).slice(2,10)),'idempotencyKey'),
        items,
      };
      const saved=unwrap(await context.write('/supplier-procurement/suppliers/'+encodeURIComponent(supplier)+'/catalogues',body,{readBackPath:workspace(s),readBackCollection:'catalogues'}));current(s);
      const data=await freshWorkspace(s);
      const record=collection(data,'catalogues').find(r=>r.id===saved?.id);
      if(!record)throw Error('Catalogue revision persistence not verified; refresh before retrying.');
      return record;
    }
    // Procurement baseline draft from real project lines. Never auto-approved.
    async function baselines(input={}){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      confirmed(input,'Procurement baseline draft');
      const raw=[].concat(input.lines||[]);
      if(raw.length===0)throw Error('At least one baseline line is required.');
      if(raw.length>500)throw Error('A baseline accepts at most 500 lines.');
      const lines=raw.map(line=>{
        if(!line||typeof line!=='object')throw Error('Baseline line must be an object.');
        const quantity=num(line.quantity,'Baseline line quantity',0);
        if(quantity<=0)throw Error('Baseline line quantity must be greater than zero.');
        return {
          sourceItemRef:text(line.sourceItemRef,'Baseline line sourceItemRef'),
          description:text(line.description,'Baseline line description'),
          category:text(line.category,'Baseline line category'),
          specification:line.specification==null||line.specification===''?null:String(line.specification).trim(),
          requiredStandard:line.requiredStandard==null||line.requiredStandard===''?null:String(line.requiredStandard).trim(),
          quantity,
          unit:text(line.unit,'Baseline line unit'),
          allowSubstitution:line.allowSubstitution===true,
          requiredOnSiteDate:date(line.requiredOnSiteDate),
          sourceDocumentIds:[].concat(line.sourceDocumentIds||[]),
        };
      });
      const body={title:text(input.title,'Baseline title'),sourceFileIds:[].concat(input.sourceFileIds||[]),lines};
      const saved=unwrap(await context.write(path(s,'supplier-procurement/baselines'),body,{readBackPath:workspace(s),readBackCollection:'baselines'}));current(s);
      const data=await freshWorkspace(s);
      const record=collection(data,'baselines').find(r=>r.id===saved?.id);
      if(!record)throw Error('Procurement baseline persistence not verified; refresh before retrying.');
      return record;
    }
    async function approveBaseline(baselineId,input={}){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      confirmed(input,'Baseline approval');
      const baseline=id(baselineId,'baselineId');
      const reason=text(input.reason,'Baseline approval reason');
      const expectedVersion=input.expectedVersion;
      if(!Number.isInteger(expectedVersion)||expectedVersion<1)throw Error('expectedVersion (the loaded baseline version) is required so approval cannot overwrite a newer revision.');
      const saved=unwrap(await context.write('/supplier-procurement/baselines/'+encodeURIComponent(baseline)+'/approve',{humanConfirmed:true,reason,expectedVersion},{readBackPath:workspace(s),readBackCollection:'baselines'}));current(s);
      const data=await freshWorkspace(s);
      const record=collection(data,'baselines').find(r=>r.id===baseline);
      if(!record||record.status!=='approved'||Number(record.version)<=expectedVersion)throw Error('Baseline approval not verified; refresh before retrying.');
      return record;
    }
    async function packages(baselineId,input={}){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      confirmed(input,'Procurement package build');
      const baseline=id(baselineId,'baselineId');
      const body={title:text(input.title,'Package title'),projectRegion:text(input.projectRegion,'Project region').toLowerCase()};
      const saved=unwrap(await context.write('/supplier-procurement/baselines/'+encodeURIComponent(baseline)+'/packages',body,{readBackPath:workspace(s),readBackCollection:'packages'}));current(s);
      const data=await freshWorkspace(s);
      const record=collection(data,'packages').find(r=>r.id===saved?.id);
      if(!record)throw Error('Procurement package persistence not verified; refresh before retrying.');
      return record;
    }
    // Advisory matching only (advisoryOnly:true on every row). Matching writes real
    // match rows; a silently empty result is reported, never claimed as success.
    async function matches(packageId,input={}){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      confirmed(input,'Catalogue matching');
      const target=id(packageId,'packageId');
      await context.write('/supplier-procurement/packages/'+encodeURIComponent(target)+'/matches',{},{readBackPath:workspace(s),readBackCollection:'matches'});current(s);
      const data=await freshWorkspace(s);
      const found=collection(data,'matches').filter(m=>m.packageId===target);
      if(found.length===0)throw Error('Catalogue matching produced no advisory match rows for this package; nothing was verified.');
      return found;
    }
    async function rfqs(packageId,input={}){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      confirmed(input,'RFQ issue');
      const target=id(packageId,'packageId');
      const supplierId=id(input.supplierId,'supplierId');
      const days=input.expiresInDays==null?14:Number(input.expiresInDays);
      if(!Number.isInteger(days)||days<1||days>90)throw Error('expiresInDays must be an integer between 1 and 90.');
      const saved=unwrap(await context.write('/supplier-procurement/packages/'+encodeURIComponent(target)+'/rfqs',{supplierId,expiresInDays:days,humanConfirmed:true},{readBackPath:workspace(s),readBackCollection:'rfqs'}));current(s);
      const data=await freshWorkspace(s);
      const record=collection(data,'rfqs').find(r=>r.id===saved?.id);
      if(!record||record.status!=='issued')throw Error('RFQ issue not verified; refresh before retrying.');
      return {...record,accessToken:saved.accessToken||null,accessTokenShownOnce:saved.accessTokenShownOnce===true};
    }
    return {list,createSupplier,addCatalogue,baselines,approveBaseline,packages,matches,rfqs};
  }
  root.V1CloneSupplierAdapter={create};
})(window);
