(function(root){
  'use strict';
  // Governance adapter — professional_directory, team_workspace, admin_review, market_insights.
  // Backed by the real ProjectTeamService, Trust\VerificationService and AnalyticsReportingService.
  // Follows the canonical site-tools.js pattern: scope()/current(), context.write with read-back
  // verify, humanConfirmed guards, never fabricated success.
  const aliases={professional_directory:'professional_directory',team_workspace:'team_workspace',admin_review:'admin_review',market_insights:'market_insights'};
  const unwrap=r=>r?.data??r;
  function create(context){
    function scope(projectRequired){
      const s=context.getState();
      if(!s.ready||!s.session||!s.organisationId)throw Error('Sign in to continue.');
      if(projectRequired&&!/^[a-f0-9]{32}$/i.test(s.selectedProjectId||''))throw Error('Select a project.');
      return {...s};
    }
    function current(s){const now=context.getState();if(!now.ready||!now.session||now.session!==s.session||now.organisationId!==s.organisationId)throw Error('Scope changed; reload this workspace.');}
    const path=(s,name)=>'/projects/'+encodeURIComponent(s.selectedProjectId)+'/'+name;

    // --- Team (professional_directory + team_workspace) ---
    async function listTeam(){
      const s=scope(true);
      const data=unwrap(await context.read(path(s,'team')));current(s);
      const list=Array.isArray(data?.teamMembers)?data.teamMembers:(Array.isArray(data)?data:[]);
      return list.filter(r=>!r.projectId||r.projectId===s.selectedProjectId);
    }
    async function invite({candidateId,discipline,roleLabel}){
      const s=scope(true);if(s.readOnly)throw Error('Read-only session.');
      if(!candidateId||!discipline)throw Error('A candidate and discipline are required.');
      const saved=unwrap(await context.write(path(s,'team'),{candidateId,discipline,roleLabel:roleLabel||discipline},{readBackPath:path(s,'team')}));current(s);
      const list=await listTeam();current(s);
      const rec=list.find(r=>r.id===saved?.id||r.candidateId===candidateId);
      if(!rec)throw Error('Invitation persistence not verified; refresh before retrying.');return rec;
    }
    async function removeMember(teamMemberId,reason){
      const s=scope(true);if(s.readOnly)throw Error('Read-only session.');
      if(!reason||!String(reason).trim())throw Error('A removal reason is required.');
      await context.write('/projects/'+encodeURIComponent(s.selectedProjectId)+'/team/'+encodeURIComponent(teamMemberId),{reason:String(reason).trim(),humanConfirmed:true},{method:'DELETE',readBackPath:path(s,'team')});current(s);
      return true;
    }
    // --- Directory verification (professional_directory + admin_review) ---
    async function searchDirectory(profession,serviceArea){
      const s=scope(false);
      const q=[];if(profession)q.push('profession='+encodeURIComponent(profession));if(serviceArea)q.push('serviceArea='+encodeURIComponent(serviceArea));
      return unwrap(await context.read('/directory/search'+(q.length?'?'+q.join('&'):'')));
    }
    async function saveProfile({serviceAreas,directoryVisible}){
      const s=scope(false);if(s.readOnly)throw Error('Read-only session.');
      return unwrap(await context.write('/professional-profile',{serviceAreas:serviceAreas??[],directoryVisible:directoryVisible===true,humanConfirmed:true},{readBackPath:'/professional-profile'}));
    }
    async function submitVerification({kind='professional_registration',grade,registrationBody,registrationNumber,purpose,expiresOn,evidenceFileId,consentGiven,retentionUntil}={}){
      const s=scope(false);if(s.readOnly)throw Error('Read-only session.');
      if(consentGiven!==true)throw Error('Explicit consent is required before submitting a verification.');
      if(!['self_declared','document_uploaded'].includes(grade))throw Error('A valid grade (self_declared or document_uploaded) is required.');
      if(!['SACAP','ECSA','SACQSP','SACLAP','SACPCMP'].includes(String(registrationBody).toUpperCase()))throw Error('A recognised professional body is required (SACAP, ECSA, SACQSP, SACLAP, SACPCMP).');
      if(!purpose||!String(purpose).trim())throw Error('A POPIA purpose is required.');
      if(!retentionUntil)throw Error('A retention date is required.');
      return unwrap(await context.write('/verifications',{kind,grade,registrationBody:String(registrationBody).toUpperCase(),registrationNumber:registrationNumber||null,purpose:String(purpose).trim(),expiresOn:expiresOn||null,evidenceFileId:evidenceFileId||null,consentGiven:true,retentionUntil},{readBackPath:'/verification-review-queue'}));
    }
    async function reviewQueue(){const s=scope(false);return unwrap(await context.read('/verification-review-queue'));}
    async function reviewVerification(verificationId,decision,{provider,providerReference,note}={}){
      const s=scope(false);if(s.readOnly)throw Error('Read-only session.');
      if(!['manually_reviewed','externally_verified','rejected'].includes(decision))throw Error('A valid decision is required (manually_reviewed, externally_verified, rejected).');
      if(decision==='externally_verified'&&(!provider||!providerReference))throw Error('External verification requires a provider and provider reference.');
      return unwrap(await context.write('/verifications/'+encodeURIComponent(verificationId)+'/review',{decision,provider:provider||null,providerReference:providerReference||null,note:note||null,humanConfirmed:true},{readBackPath:'/verification-review-queue'}));
    }
    // --- Analytics (market_insights) ---
    async function analyticsWorkspace(){const s=scope(true);return unwrap(await context.read(path(s,'analytics-workspace')));}
    async function refreshKpis({asOf}={}){
      const s=scope(true);if(s.readOnly)throw Error('Read-only session.');
      const body={asOf:asOf||new Date().toISOString().slice(0,10)};
      return unwrap(await context.write(path(s,'analytics/kpis/refresh'),body,{readBackPath:path(s,'analytics-workspace')}));
    }
    async function createAlertRule(rule){
      const s=scope(true);if(s.readOnly)throw Error('Read-only session.');
      const body={name:rule.name,recordType:rule.recordType,payloadPath:rule.payloadPath,operator:rule.operator,comparisonValue:rule.comparisonValue,severity:rule.severity||'medium',recipientRole:rule.recipientRole||'owner',throttleMinutes:rule.throttleMinutes??60};
      return unwrap(await context.write(path(s,'analytics/alert-rules'),body,{readBackPath:path(s,'analytics-workspace')}));
    }
    return {scope,current,listTeam,invite,removeMember,searchDirectory,saveProfile,submitVerification,reviewQueue,reviewVerification,analyticsWorkspace,refreshKpis,createAlertRule};
  }
  root.V1CloneGovernanceAdapter={create,aliases};
})(window);
