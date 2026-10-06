(function(root){
  'use strict';
  // Fee Proposal adapter (Tier-1, professional fees).
  //
  // Wraps the real routes only:
  //   GET  /proposals                          -> the organisation/project proposal list
  //   POST /toolbox/runs                        -> runs the EXISTING, verified
  //        professional_fee_calculator tool and freezes (issues) the result as the
  //        proposal's fee basis. A proposal draft cannot reference an unissued run
  //        (ProposalService::feeRun requires tool_runs.status='issued'), so the
  //        calculator run is issued with an explicit professional reason.
  //   POST /proposals                           -> creates the draft from that issued run
  //   POST /proposals/{id}/issue                -> professionally confirms/issues it
  //
  // Every write is read-back verified before success is claimed. Nothing is
  // fabricated: the tool output is advisoryOnly / professional_decision_support
  // ("Indicative proposal aid only; not a fixed tariff or legal opinion"), and the
  // module never presents it as a fixed tariff, statutory approval or legal opinion.
  const TOOL_ID='professional_fee_calculator';
  const unwrap=r=>r?.data??r;
  const RUNS_PATH='/toolbox/runs?limit=100';
  function create(context){
    function scope(){const s=context.getState();if(!s.ready||!s.session||!s.organisationId||!/^[a-f0-9]{32}$/i.test(s.selectedProjectId||''))throw Error('Select an authenticated project.');return {...s};}
    function current(s){const now=context.getState();if(!now.ready||!now.session||now.selectedProjectId!==s.selectedProjectId||now.organisationId!==s.organisationId||now.session!==s.session)throw Error('Scope changed; reload this workspace.');}
    function feeRuns(rows,s){return rows.filter(r=>r&&r.toolId===TOOL_ID&&(!r.assignment?.projectId||r.assignment.projectId===s.selectedProjectId));}
    // (a) Fetch the fee proposal / proposals list (organisation-scoped, project-filtered).
    async function listProposals(){
      const s=scope();
      const data=unwrap(await context.read('/proposals'));current(s);
      if(!Array.isArray(data))throw Error('Invalid proposals response.');
      return data.filter(r=>r&&typeof r==='object'&&(!r.projectId||r.projectId===s.selectedProjectId));
    }
    // Issued fee calculations that can back a proposal for the selected project.
    async function listFeeRuns(){
      const s=scope();
      const data=unwrap(await context.read(RUNS_PATH));current(s);
      if(!Array.isArray(data))throw Error('Invalid toolbox history response.');
      return feeRuns(data,s).filter(r=>r.status==='issued');
    }
    // Validate + map the calculator input to the exact backend schema
    // (FeeCalculatorService::calculate). Rejects locally so a bad form never posts.
    function feeInput(values){
      const definitionId=values?.definitionId;
      if(typeof definitionId!=='string'||!/^[a-f0-9]{32}$/i.test(definitionId))throw Error('Select a verified fee definition.');
      const projectValue=Number(values.projectValue);
      if(!Number.isFinite(projectValue)||projectValue<0)throw Error('Enter a valid project value.');
      const complexityId=typeof values.complexityId==='string'?values.complexityId.trim():'';
      if(!complexityId)throw Error('Select a complexity rating.');
      const splits=values.categorySplits;
      if(!splits||typeof splits!=='object'||Array.isArray(splits)||Object.keys(splits).length===0)throw Error('Provide the fee category allocation.');
      const stages=values.selectedStages;
      if(!stages||typeof stages!=='object'||Array.isArray(stages))throw Error('Provide the stage selections.');
      const discount=values.discount??{percentage:0,reason:''};
      const input={definitionId:definitionId.toLowerCase(),projectValue,complexityId,categorySplits:splits,selectedStages:stages,disbursements:Array.isArray(values.disbursements)?values.disbursements:[],statutoryFees:Array.isArray(values.statutoryFees)?values.statutoryFees:[],hourlyLines:Array.isArray(values.hourlyLines)?values.hourlyLines:[],unitLines:Array.isArray(values.unitLines)?values.unitLines:[],discount,vatApplicable:values.vatApplicable===true};
      if(values.professionalOverride)input.professionalOverride=values.professionalOverride;
      return input;
    }
    // (b) Run the professional_fee_calculator and read-back-verify the persisted run.
    async function calculateProfessionalFee(values){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      const issueReason=typeof values?.issueReason==='string'?values.issueReason.trim():'';
      if(issueReason==='')throw Error('A reason is required to freeze a fee calculation as proposal evidence.');
      const body={toolId:TOOL_ID,input:feeInput(values),assignment:{mode:'internal_project',projectId:s.selectedProjectId},issueImmediately:true,issueReason};
      const saved=unwrap(await context.write('/toolbox/runs',body,{readBackPath:RUNS_PATH}));current(s);
      const history=unwrap(await context.read(RUNS_PATH));current(s);
      const verified=Array.isArray(history)&&history.find(r=>r.id===saved?.id&&r.toolId===TOOL_ID&&r.assignment?.projectId===s.selectedProjectId&&r.status==='issued');
      if(!verified)throw Error('Fee calculation persistence not verified; refresh history before retrying.');
      return verified;
    }
    // (c) Create the fee proposal draft from an issued fee run, then confirm/issue it.
    async function confirmProposal(values){
      const s=scope();if(s.readOnly)throw Error('Read-only session.');
      if(values?.humanConfirmed!==true)throw Error('Fee proposal requires explicit human confirmation.');
      const feeRunId=values.feeRunId;
      if(typeof feeRunId!=='string'||!/^[a-f0-9]{32}$/i.test(feeRunId))throw Error('An issued fee calculation is required.');
      const issueReason=typeof values.issueReason==='string'?values.issueReason.trim():'';
      if(issueReason==='')throw Error('An issue reason is required to confirm this proposal.');
      const templates=Array.isArray(values.selectedTemplateKeys)?values.selectedTemplateKeys:['standard_sa_professional'];
      if(templates.length===0)throw Error('Select at least one professional services term set.');
      const draftBody={projectId:s.selectedProjectId,feeRunId:feeRunId.toLowerCase(),clientName:values.clientName,clientEmail:values.clientEmail,validityDays:Number(values.validityDays),selectedTemplateKeys:templates,assumptions:Array.isArray(values.assumptions)?values.assumptions:[],exclusions:Array.isArray(values.exclusions)?values.exclusions:[],notes:Array.isArray(values.notes)?values.notes:[],customTerms:Array.isArray(values.customTerms)?values.customTerms:[]};
      const draft=unwrap(await context.write('/proposals',draftBody,{readBackPath:'/proposals'}));current(s);
      if(!draft?.id)throw Error('Proposal draft was not created.');
      const list=unwrap(await context.read('/proposals'));current(s);
      if(!Array.isArray(list)||!list.find(r=>r.id===draft.id))throw Error('Proposal draft persistence not verified; refresh before retrying.');
      const issuePath='/proposals/'+encodeURIComponent(draft.id);
      const issued=unwrap(await context.write(issuePath+'/issue',{professionalConfirmed:true,legalReviewConfirmed:true,issueReason},{readBackPath:issuePath}));current(s);
      const detail=unwrap(await context.read(issuePath));current(s);
      if(!detail||detail.status!=='issued')throw Error('Proposal confirmation not verified; refresh before retrying.');
      const feeOutput=detail.document?.feeSnapshot?.output??null;
      return {proposal:detail,acceptanceToken:issued?.acceptanceToken??null,advisoryOnly:feeOutput?.advisoryOnly===true,classification:feeOutput?.classification??'professional_decision_support'};
    }
    return {listProposals,listFeeRuns,calculateProfessionalFee,confirmProposal};
  }
  root.V1CloneProposalsAdapter={create,toolId:TOOL_ID};
})(window);
