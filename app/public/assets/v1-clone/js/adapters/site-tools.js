(function(root){
  'use strict';
  const aliases={itp:'itp',safety:'safety',engineering_calc:'engineering_calc'};
  const unwrap=r=>r?.data??r;
  function create(context){
    function scope(){const s=context.getState();if(!s.ready||!s.session||!s.organisationId||!/^[a-f0-9]{32}$/i.test(s.selectedProjectId||''))throw Error('Select an authenticated project.');return {...s};}
    const path=(s,name)=>'/projects/'+encodeURIComponent(s.selectedProjectId)+'/'+name;
    function rows(data,key,s){if(!Array.isArray(data?.[key]))throw Error('Invalid '+key+' response.');return data[key].filter(r=>r&&(!r.projectId||r.projectId===s.selectedProjectId)&&(!r.organisationId||r.organisationId===s.organisationId));}
    async function load(id){
      if(!aliases[id])throw Error('Unsupported site tool.');
      const s=scope(),site=unwrap(await context.read(path(s,'site-execution-workspace')));
      const model={projectId:s.selectedProjectId,organisationId:s.organisationId,projectName:s.projects?.find(p=>p.id===s.selectedProjectId)?.name||'Selected project',certified:false,inspections:rows(site,'inspections',s),ncrs:rows(site,'ncrs',s),evidence:rows(site,'evidence',s),materialTests:null,holdPoints:null,labResults:null,permits:null,incidents:null,inductions:null,hazards:null,plans:[]};
      if(id==='safety'){const trust=unwrap(await context.read(path(s,'trust-compliance-workspace')));model.plans=rows(trust,'complianceItems',s).filter(r=>r.documentType==='health_safety_plan');model.inspections=model.inspections.filter(r=>r.inspectionType==='safety');}
      current(s);return model;
    }
    function current(s){const now=context.getState();if(!now.ready||!now.session||now.selectedProjectId!==s.selectedProjectId||now.organisationId!==s.organisationId||now.session!==s.session)throw Error('Scope changed; reload this workspace.');}
    async function createInspection(input){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(context.capabilities?.readBackCollection!==true)throw Error('Draft inspection saving unavailable: shared collection read-back support is required. Nothing was submitted.');
      const body={inspectionType:input.inspectionType,findings:input.findings??[],checklist:input.checklist??[],followUps:input.followUps??[],signOffRequired:input.signOffRequired===true};
      const saved=unwrap(await context.write(path(s,'inspections'),body,{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'inspections'}));current(s);
      const data=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);
      const record=rows(data,'inspections',s).find(r=>r.id===saved?.id);if(!record)throw Error('Inspection persistence not verified; refresh before retrying.');return record;
    }
    async function saveRational(values){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      const c=Number(values.coefficient),i=Number(values.intensity),area=Number(values.areaHa);
      if(![c,i,area].every(Number.isFinite)||c<=0||c>1||i<=0||area<=0)throw Error('Invalid rational-method input.');
      const body={toolId:'rational_runoff_calculator',input:{catchmentAreaM2:area*10000,runoffCoefficient:c,rainfallIntensityMmPerHour:i},assignment:{mode:'internal_project',projectId:s.selectedProjectId},issueImmediately:false,issueReason:null};
      const saved=unwrap(await context.write('/toolbox/runs',body,{readBackPath:'/toolbox/runs?limit=100'}));current(s);
      const history=unwrap(await context.read('/toolbox/runs?limit=100'));current(s);
      const verified=Array.isArray(history)&&history.find(r=>r.id===saved?.id&&r.toolId===body.toolId&&r.assignment?.projectId===s.selectedProjectId&&r.status==='completed');
      if(!verified)throw Error('Calculation persistence not verified; refresh history before retrying.');return verified;
    }
    // Cable sizing / voltage-drop tab persists as an advisory Toolbox run.
    async function saveVoltageDrop(values){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      const volt=Number(values.nominalVoltageV!=null?values.nominalVoltageV:400),amps=Number(values.currentA),route=Number(values.routeLengthM),resist=Number(values.resistanceOhmPerKm),react=Number(values.reactanceOhmPerKm!=null?values.reactanceOhmPerKm:0),pf=Number(values.powerFactor!=null?values.powerFactor:0.9),limit=Number(values.maximumDropPercent);
      if(![volt,amps,route,resist,limit].every(Number.isFinite)||volt<=0||amps<=0||route<=0||resist<=0||limit<=0||limit>100)throw Error('Invalid voltage-drop input.');
      const body={toolId:'voltage_drop_calculator',input:{phase:values.phase||'three_phase',nominalVoltageV:volt,currentA:amps,routeLengthM:route,resistanceOhmPerKm:resist,reactanceOhmPerKm:react,powerFactor:pf,maximumDropPercent:limit},assignment:{mode:'internal_project',projectId:s.selectedProjectId},issueImmediately:false,issueReason:null};
      const saved=unwrap(await context.write('/toolbox/runs',body,{readBackPath:'/toolbox/runs?limit=100'}));current(s);
      const history=unwrap(await context.read('/toolbox/runs?limit=100'));current(s);
      const verified=Array.isArray(history)&&history.find(r=>r.id===saved?.id&&r.toolId===body.toolId&&r.assignment?.projectId===s.selectedProjectId&&r.status==='completed');
      if(!verified)throw Error('Calculation persistence not verified; refresh history before retrying.');return verified;
    }
    // --- Registered engineering / compliance calculators (ProductionToolRegistry).
    // Each persists as an advisory Toolbox run (issueImmediately:false) and is only
    // reported after a read-back-verified completed run. advisoryOnly===true is the
    // hard boundary: calculators are controlled working records, never a certification.
    function num(v){const n=Number(v);return Number.isFinite(n)?n:NaN;}
    async function persistRun(toolId,input,outputKey){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      const body={toolId,input,assignment:{mode:'internal_project',projectId:s.selectedProjectId},issueImmediately:false,issueReason:null};
      const saved=unwrap(await context.write('/toolbox/runs',body,{readBackPath:'/toolbox/runs?limit=100'}));current(s);
      const history=unwrap(await context.read('/toolbox/runs?limit=100'));current(s);
      const verified=Array.isArray(history)&&history.find(r=>r.id===saved?.id&&r.toolId===toolId&&r.assignment?.projectId===s.selectedProjectId&&r.status==='completed'&&r.output?.[outputKey]!=null&&r.output?.advisoryOnly===true);
      if(!verified)throw Error('Calculation persistence not verified; refresh history before retrying.');
      return verified;
    }
    function projectObject(v){
      if(!v||typeof v!=='object')throw Error('A compliance project description is required.');
      const buildingType=['residential','commercial','public'].includes(v.buildingType)?v.buildingType:null;
      const sansNCheck=v.sansNCheck===true;
      const p={projectName:String(v.projectName||'').trim(),reference:String(v.reference||'').trim(),address:v.address!=null?String(v.address).trim():null,sansEdition:'2021',sansNCheck,climateZone:num(v.climateZone),storeys:num(v.storeys),floorArea:num(v.floorArea),buildingType};
      if(!p.projectName||!p.reference||!buildingType||!Number.isFinite(p.climateZone)||p.climateZone<1||p.climateZone>6||!Number.isInteger(p.storeys)||p.storeys<1||p.storeys>100||!Number.isFinite(p.floorArea)||p.floorArea<=0)throw Error('Invalid compliance project description.');
      return p;
    }
    function ruleVersion(v){if(!/^[a-f0-9]{32}$/i.test(v))throw Error('A registered 32-hex ruleVersionId is required for this calculator.');return v;}
    async function saveBeamCheck(values){
      const spanM=num(values.spanM),udlKnPerM=num(values.udlKnPerM),pointLoadKn=num(values.pointLoadKn),elasticModulusMpa=num(values.elasticModulusMpa),inertiaMm4=num(values.inertiaMm4),sectionModulusMm3=num(values.sectionModulusMm3),allowableStressMpa=num(values.allowableStressMpa),deflectionLimitRatio=num(values.deflectionLimitRatio);
      if(![spanM,udlKnPerM,pointLoadKn,elasticModulusMpa,inertiaMm4,sectionModulusMm3,allowableStressMpa,deflectionLimitRatio].every(Number.isFinite)||spanM<=0||udlKnPerM<0||pointLoadKn<0||(udlKnPerM===0&&pointLoadKn===0)||elasticModulusMpa<=0||inertiaMm4<=0||sectionModulusMm3<=0||allowableStressMpa<=0||deflectionLimitRatio<=0)throw Error('Invalid beam quick-check input.');
      return persistRun('beam_quick_check',{spanM,udlKnPerM,pointLoadKn,elasticModulusMpa,inertiaMm4,sectionModulusMm3,allowableStressMpa,deflectionLimitRatio},'results');
    }
    async function saveConcreteQuantity(values){
      const els=[].concat(values.elements||[]);const waste=num(values.wastePercent);
      if(!Number.isFinite(waste)||waste<0||waste>100||els.length===0||els.length>200)throw Error('Invalid concrete-quantity input.');
      const elements=els.map(e=>{const reference=String(e.reference||'').trim(),lengthM=num(e.lengthM),widthM=num(e.widthM),depthM=num(e.depthM),quantity=num(e.quantity);if(!reference||![lengthM,widthM,depthM,quantity].every(n=>Number.isFinite(n)&&n>0))throw Error('Invalid concrete element; each needs reference and positive lengthM/widthM/depthM/quantity.');return {reference,lengthM,widthM,depthM,quantity};});
      return persistRun('concrete_quantity_calculator',{elements,wastePercent:waste},'results');
    }
    async function saveMasonryQuantity(values){
      const gross=num(values.grossWallAreaM2),openings=num(values.openingsAreaM2),length=num(values.unitLengthM),height=num(values.unitHeightM),waste=num(values.wastePercent);
      if(![gross,openings,length,height,waste].every(Number.isFinite)||gross<=0||openings<0||openings>=gross||length<=0||height<=0||waste<0||waste>100)throw Error('Invalid masonry-quantity input.');
      return persistRun('masonry_quantity_calculator',{grossWallAreaM2:gross,openingsAreaM2:openings,unitLengthM:length,unitHeightM:height,wastePercent:waste},'results');
    }
    async function saveFinishesQuantity(values){
      const mode=['paint','tile'].includes(values.mode)?values.mode:null,area=num(values.surfaceAreaM2),waste=num(values.wastePercent);
      if(!mode||![area,waste].every(Number.isFinite)||area<=0||waste<0||waste>100)throw Error('Invalid finishes-quantity input.');
      const input={mode,surfaceAreaM2:area,wastePercent:waste};
      if(mode==='paint'){const coats=num(values.coats),coverage=num(values.coverageM2PerLitre);if(!Number.isFinite(coats)||coats<=0||!Number.isFinite(coverage)||coverage<=0)throw Error('Paint mode requires positive coats and coverageM2PerLitre.');input.coats=coats;input.coverageM2PerLitre=coverage;}
      else{const pack=num(values.packCoverageM2);if(!Number.isFinite(pack)||pack<=0)throw Error('Tile mode requires positive packCoverageM2.');input.packCoverageM2=pack;}
      return persistRun('finishes_quantity_calculator',input,'results');
    }
    async function saveTenderRateBuildUp(values){
      const quantity=num(values.quantity),unit=String(values.unit||'').trim(),materials=num(values.materialsPerUnit),labour=num(values.labourPerUnit),plant=num(values.plantPerUnit),subcontract=num(values.subcontractPerUnit),overheads=num(values.overheadsPercent),profit=num(values.profitPercent),risk=num(values.riskPercent);
      if(!unit||![quantity,materials,labour,plant,subcontract,overheads,profit,risk].every(Number.isFinite)||quantity<=0||materials<0||labour<0||plant<0||subcontract<0||overheads<0||overheads>100||profit<0||profit>100||risk<0||risk>100)throw Error('Invalid tender rate build-up input.');
      return persistRun('tender_rate_build_up_calculator',{quantity,unit,materialsPerUnit:materials,labourPerUnit:labour,plantPerUnit:plant,subcontractPerUnit:subcontract,overheadsPercent:overheads,profitPercent:profit,riskPercent:risk},'results');
    }
    async function saveLabourProductivity(values){
      const quantity=num(values.quantity),unit=String(values.unit||'').trim(),productivity=num(values.productivityPerCrewDay),crews=num(values.parallelCrews),hours=num(values.hoursPerDay);
      if(!unit||![quantity,productivity,crews,hours].every(Number.isFinite)||quantity<=0||productivity<=0||crews<=0||hours<=0)throw Error('Invalid labour-productivity input.');
      return persistRun('labour_productivity_calculator',{quantity,unit,productivityPerCrewDay:productivity,parallelCrews:crews,hoursPerDay:hours},'results');
    }
    async function saveRefuseArea(values){
      const project=projectObject(values.project),ruleVersionId=ruleVersion(values.ruleVersionId),occupancyUse=String(values.occupancyUse||''),binType=String(values.binType||''),quantity=num(values.quantity),interval=num(values.collectionIntervalDays),contingency=num(values.contingencyPercent),proposed=num(values.proposedStorageAreaM2),facilities=values.facilities,assumptions=String(values.assumptions||'').trim();
      if(!occupancyUse||!binType||![quantity,interval,contingency,proposed].every(Number.isFinite)||quantity<=0||!Number.isInteger(interval)||interval<1||interval>7||contingency<0||contingency>100||proposed<=0||!facilities||typeof facilities!=='object'||!assumptions)throw Error('Invalid refuse-area input; confirm occupancyUse, binType, collection interval and a recorded facilities object.');
      return persistRun('refuse_area_calculator',{ruleVersionId,project,occupancyUse,quantity,collectionIntervalDays:interval,contingencyPercent:contingency,binType,proposedStorageAreaM2:proposed,facilities,assumptions},'verdict');
    }
    async function saveFenestrationXa(values){
      const project=projectObject(values.project),ruleVersionId=ruleVersion(values.ruleVersionId);
      const raw=[].concat(values.elements||[]);if(raw.length===0)throw Error('At least one fenestration element is required.');
      const elements=raw.map(e=>{
        const id=String(e.id||'').trim(),storey=num(e.storey),room=String(e.room||'').trim(),roomUse=e.roomUse||'habitable',orientation=e.orientation,frameType=e.frameType,elementType=e.elementType||'fenestration',glassArea=num(e.glassArea),openableArea=e.openableArea!=null?num(e.openableArea):glassArea,roomFloorArea=e.roomFloorArea!=null?num(e.roomFloorArea):(e.wallArea!=null?num(e.wallArea):undefined),wallArea=num(e.wallArea),uValue=num(e.uValue),shgc=num(e.shgc),shadingFactor=num(e.shadingFactor),description=String(e.description||'').trim();
        if(!id||!room||!Number.isInteger(storey)||storey<1||storey>project.storeys||!['habitable','bathroom','kitchen','garage','stair','public'].includes(roomUse)||!['N','NE','E','SE','S','SW','W','NW','HORIZONTAL'].includes(orientation)||!['alu','thermal','timber'].includes(frameType)||!['fenestration','roof_lights','doors','curtain_wall','skylight'].includes(elementType)||![glassArea,wallArea,uValue,shgc,shadingFactor].every(Number.isFinite)||glassArea<=0||openableArea<0||openableArea>glassArea||wallArea<=0||uValue<0||shgc<0||shgc>1||shadingFactor<0||shadingFactor>1)throw Error('Invalid fenestration element fields.');
        const item={id,storey,room,roomUse,orientation,frameType,elementType,glassArea,openableArea,wallArea,uValue,shgc,shadingFactor,description};if(roomFloorArea!=null)item.roomFloorArea=roomFloorArea;return item;});
      return persistRun('fenestration_xa_calculator',{ruleVersionId,project,elements},'verdict');
    }
    async function saveRValueThermal(values){
      const project=projectObject(values.project),ruleVersionId=ruleVersion(values.ruleVersionId);
      const raw=[].concat(values.assemblies||[]);if(raw.length===0)throw Error('At least one thermal assembly is required.');
      const assemblies=raw.map(a=>{
        const id=String(a.id||'').trim(),type=a.type,description=String(a.description||'').trim(),climate=num(a.climateZone),area=num(a.area);
        const layers=[].concat(a.layers||[]).map(l=>{const material=String(l.material||'').trim(),thicknessMm=num(l.thicknessMm);const layer={material,thicknessMm};if(l.rValue!=null)layer.rValue=num(l.rValue);if(l.conductivity!=null)layer.conductivity=num(l.conductivity);if(!material||!Number.isFinite(thicknessMm)||thicknessMm<0||(layer.rValue!=null&&!(Number.isFinite(layer.rValue)&&layer.rValue>0))||(layer.conductivity!=null&&!(Number.isFinite(layer.conductivity)&&layer.conductivity>0)))throw Error('Invalid thermal layer; each needs material and thicknessMm.');return layer;});
        if(!id||!['roof','wall','floor'].includes(type)||!description||![climate,area].every(Number.isFinite)||!Number.isInteger(climate)||climate<1||climate>6||area<=0||layers.length===0)throw Error('Invalid thermal assembly; each needs id, type, description, climateZone, area and layers.');
        return {id,type,description,climateZone:climate,area,layers};});
      return persistRun('r_value_thermal_calculator',{ruleVersionId,project,assemblies},'verdict');
    }
    // Site-execution RFI write path (pack 9/10, proven backend). A real open RFI
    // persists, can be responded to, and closed. Mirrors createInspection (project-scoped,
    // read-back-verified, never fabricated success on an unavailable path).
    async function createRfi(input){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(input.humanConfirmed!==true)throw Error('RFI requires explicit human confirmation.');if(!input.dueAt)throw Error('A response due date is required — the record cannot be created without one.');
      if(context.capabilities?.readBackCollection!==true)throw Error('RFI save unavailable: shared collection read-back support is required. Nothing was submitted.');
      const discipline=(['architectural','structural','civil','electrical','mechanical','quantity_surveying'].includes(input.discipline)&&input.discipline)||null;
      const body={subject:input.subject,question:input.question,discipline,dueAt:input.dueAt||null,linkedDocumentIds:input.linkedDocumentIds??[],humanConfirmed:true};
      const saved=unwrap(await context.write(path(s,'rfis'),body,{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'rfis'}));current(s);
      const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);
      const record=rows(site,'rfis',s).find(r=>r.id===saved?.id);if(!record)throw Error('RFI persistence not verified; refresh before retrying.');return record;
    }
    async function respondRfi(rfiId,{answer,requiresInstruction=false,humanConfirmed=false}={}){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(humanConfirmed!==true)throw Error('RFI response requires explicit human confirmation.');
      if(context.capabilities?.readBackCollection!==true)throw Error('RFI response save unavailable: shared collection read-back support is required. Nothing was submitted.');
      const saved=unwrap(await context.write('/rfis/'+encodeURIComponent(rfiId)+'/responses',{answer,requiresInstruction,humanConfirmed:true},{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'rfis'}));current(s);
      const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);
      const record=rows(site,'rfis',s).find(r=>r.id===rfiId);if(!record||record.status==='open')throw Error('RFI response not verified; refresh before retrying.');return record;
    }
    async function closeRfi(rfiId,{reason,humanConfirmed=false}={}){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(humanConfirmed!==true)throw Error('RFI close requires explicit human confirmation.');
      const saved=unwrap(await context.write('/rfis/'+encodeURIComponent(rfiId)+'/close',{reason,humanConfirmed:true},{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'rfis'}));current(s);
      const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);
      const record=rows(site,'rfis',s).find(r=>r.id===rfiId);if(!record||record.status!=='closed')throw Error('RFI close not verified; refresh before retrying.');return record;
    }
    // --- Site-field family: NCR / Snag / Site Instruction (pack 9/10, proven backend).
    // Each create/transition is confirmed, project-scoped and read-back-verified.
    async function createNcr(input){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(input.humanConfirmed!==true)throw Error('NCR requires explicit human confirmation.');
      const body={title:input.title,description:input.description,severity:input.severity,responsibleParty:input.responsibleParty,evidenceIds:input.evidenceIds??[],blocksPayment:input.blocksPayment===true,humanConfirmed:true};
      const saved=unwrap(await context.write(path(s,'ncrs'),body,{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'ncrs'}));current(s);
      const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);
      const rec=rows(site,'ncrs',s).find(r=>r.id===saved?.id);if(!rec)throw Error('NCR persistence not verified; refresh before retrying.');return rec;
    }
    async function submitNcrCorrectiveAction(ncrId,{correctiveAction,evidenceIds=[],humanConfirmed=false}={}){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(humanConfirmed!==true)throw Error('NCR corrective action requires explicit human confirmation.');
      const saved=unwrap(await context.write('/ncrs/'+encodeURIComponent(ncrId)+'/corrective-actions',{correctiveAction,evidenceIds,humanConfirmed:true},{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'ncrs'}));current(s);
      const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);
      const rec=rows(site,'ncrs',s).find(r=>r.id===ncrId);if(!rec||rec.status==='open')throw Error('NCR action not verified; refresh before retrying.');return rec;
    }
    async function decideNcr(ncrId,{decision,reason,humanConfirmed=false}={}){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(humanConfirmed!==true||!['verified_closed','rejected'].includes(decision))throw Error('NCR decision requires confirmation and a valid decision.');
      const saved=unwrap(await context.write('/ncrs/'+encodeURIComponent(ncrId)+'/decisions',{decision,reason,humanConfirmed:true},{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'ncrs'}));current(s);
      const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);
      const rec=rows(site,'ncrs',s).find(r=>r.id===ncrId);if(!rec||!(rec.status==='verified_closed'||rec.status==='rejected'))throw Error('NCR decision not verified; refresh before retrying.');return rec;
    }
    async function createSnag(input){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(input.humanConfirmed!==true)throw Error('Snag requires explicit human confirmation.');if(!input.dueAt)throw Error('A due date is required — the record cannot be created without one.');
      const body={location:input.location,description:input.description,priority:input.priority,responsibleParty:input.responsibleParty,dueAt:input.dueAt||null,evidenceIds:input.evidenceIds??[],blocksPayment:input.blocksPayment===true,humanConfirmed:true};
      const saved=unwrap(await context.write(path(s,'snags'),body,{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'snags'}));current(s);
      const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);
      const rec=rows(site,'snags',s).find(r=>r.id===saved?.id);if(!rec)throw Error('Snag persistence not verified; refresh before retrying.');return rec;
    }
    async function allocateSnag(snagId,{humanConfirmed=false}={}){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(humanConfirmed!==true)throw Error('Snag allocation requires explicit human confirmation.');
      const saved=unwrap(await context.write('/snags/'+encodeURIComponent(snagId)+'/allocate',{humanConfirmed:true},{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'snags'}));current(s);
      const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);
      const rec=rows(site,'snags',s).find(r=>r.id===snagId);if(!rec||rec.status==='open')throw Error('Snag allocation not verified; refresh before retrying.');return rec;
    }
    async function markSnagReady(snagId,{evidenceIds=[],humanConfirmed=false}={}){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(humanConfirmed!==true)throw Error('Snag ready-for-reinspection requires explicit human confirmation.');
      const saved=unwrap(await context.write('/snags/'+encodeURIComponent(snagId)+'/ready-for-reinspection',{evidenceIds,humanConfirmed:true},{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'snags'}));current(s);
      const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);
      const rec=rows(site,'snags',s).find(r=>r.id===snagId);if(!rec||rec.status!=='ready_for_reinspection')throw Error('Snag reinspection mark not verified; refresh before retrying.');return rec;
    }
    async function decideSnag(snagId,{decision,reason,humanConfirmed=false}={}){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(humanConfirmed!==true||!['closed','rejected'].includes(decision))throw Error('Snag decision requires confirmation and a valid decision.');
      const saved=unwrap(await context.write('/snags/'+encodeURIComponent(snagId)+'/decisions',{decision,reason,humanConfirmed:true},{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'snags'}));current(s);
      const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);
      const rec=rows(site,'snags',s).find(r=>r.id===snagId);if(!rec||!(rec.status==='closed'||rec.status==='rejected'))throw Error('Snag decision not verified; refresh before retrying.');return rec;
    }
    async function createInstruction(input){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(input.humanConfirmed!==true)throw Error('Site instruction requires explicit human confirmation.');
      const body={title:input.title,instruction:input.instruction,costImpact:input.costImpact||'none',timeImpact:input.timeImpact||'none',linkedRfiId:input.linkedRfiId||null,humanConfirmed:true};
      const saved=unwrap(await context.write(path(s,'site-instructions'),body,{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'siteInstructions'}));current(s);
      const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);
      const rec=(site?.siteInstructions||[]).find(r=>r.id===saved?.id);if(!rec)throw Error('Site instruction persistence not verified; refresh before retrying.');return rec;
    }
    async function issueInstruction(instructionId,{reason,humanConfirmed=false}={}){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(humanConfirmed!==true)throw Error('Site instruction issue requires explicit human confirmation.');
      const saved=unwrap(await context.write('/site-instructions/'+encodeURIComponent(instructionId)+'/issue',{reason,humanConfirmed:true},{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'siteInstructions'}));current(s);
      const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);
      const rec=(site?.siteInstructions||[]).find(r=>r.id===instructionId);if(!rec||rec.status==='draft')throw Error('Site instruction issue not verified; refresh before retrying.');return rec;
    }
    return {load,createInspection,saveRational,saveVoltageDrop,saveBeamCheck,saveConcreteQuantity,saveMasonryQuantity,saveFinishesQuantity,saveTenderRateBuildUp,saveLabourProductivity,saveRefuseArea,saveFenestrationXa,saveRValueThermal,createRfi,respondRfi,closeRfi,createNcr,submitNcrCorrectiveAction,decideNcr,createSnag,allocateSnag,markSnagReady,decideSnag,createInstruction,issueInstruction};
  }
  root.V1CloneSiteToolsAdapter={create,aliases};
})(window);
