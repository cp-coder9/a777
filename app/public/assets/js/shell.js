(function () {
  'use strict';
  const api = new window.ArchitexApi.ApiClient();
  const root = document.documentElement;
  root.dataset.shellState = 'loading';
  const originalGlobalPage = window.renderGlobalPage;
  const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[character]));
  let activeBriefId = null;
  let briefHydrationTimer = null;

  function scheduleGuidedBriefHydration() {
    if (briefHydrationTimer !== null) clearTimeout(briefHydrationTimer);
    briefHydrationTimer = setTimeout(() => { briefHydrationTimer = null; hydrateGuidedBriefWorkspace(); }, 0);
  }

  function guidedBriefWorkspace() {
    scheduleGuidedBriefHydration();
    return `${pageHead('Guided brief','Capture the client intent, constraints and evidence before converting an approved brief into the project record.','project_passport','core')}
      <div class="notice brief-boundary"><b>Advisory boundary:</b> the interpretation is rule-based guidance for human review. It is not professional advice, an approval determination, a fee quotation or an appointment.</div>
      <section class="brief-layout">
        <div class="panel panel-pad brief-card">
          <div class="trust-card-head"><div><h2>Your briefs</h2><p>Drafts resume from MariaDB across sessions.</p></div><span class="status green">Private</span></div>
          <form id="guidedBriefCreate" class="brief-create"><input class="form-input" name="title" required maxlength="180" placeholder="e.g. Home alteration brief"><button class="btn dark" type="submit">Start brief</button></form>
          <div id="guidedBriefList" class="trust-list"><div class="notice">Loading briefs…</div></div>
        </div>
        <div id="guidedBriefEditor" class="panel panel-pad brief-card"><div class="notice">Select a saved brief or start a new one.</div></div>
      </section>`;
  }

  function briefEditor(brief, files) {
    const answers=brief.answers||{},locked=brief.status!=='draft',projects=window.__ARCHITEX_SHELL_STATE__?.projects||[];
    const selected=(name,value)=>answers[name]===value?' selected':'';
    const checked=(name,value)=>Array.isArray(answers[name])&&answers[name].includes(value)?' checked':'';
    const interpretation=brief.interpretation?`<div class="notice brief-interpretation"><b>Advisory interpretation</b><br>${escapeHtml(brief.interpretation.clientFriendlySummary)}<br><small>${escapeHtml(brief.interpretation.suggestedNextAction)} ${escapeHtml(brief.interpretation.limitations)}</small></div>`:'';
    return `<div class="trust-card-head"><div><h2>${escapeHtml(brief.title)}</h2><p>Step ${escapeHtml(brief.currentStep)} of 8 · version ${escapeHtml(brief.version)}</p></div><span class="status ${brief.status==='draft'?'amber':'green'}">${escapeHtml(brief.status)}</span></div>
      <form id="guidedBriefAnswers" class="scaffold-grid">
        <label>What do you need?<select class="form-select" name="intent" required ${locked?'disabled':''}><option value="">Choose one</option><option value="renovate_or_add"${selected('intent','renovate_or_add')}>Renovate or add</option><option value="plans_approved"${selected('intent','plans_approved')}>Get plans approved</option><option value="updated_plans_for_sale"${selected('intent','updated_plans_for_sale')}>Update plans for sale</option><option value="construction_price"${selected('intent','construction_price')}>Obtain construction pricing</option><option value="not_sure"${selected('intent','not_sure')}>Not sure yet</option></select></label>
        <label>Urgency<select class="form-select" name="urgency" required ${locked?'disabled':''}><option value="">Choose one</option><option value="normal"${selected('urgency','normal')}>Normal</option><option value="soon"${selected('urgency','soon')}>Soon</option><option value="urgent"${selected('urgency','urgent')}>Urgent — validate feasibility</option></select></label>
        <label class="trust-wide">What outcome are you trying to achieve?<textarea class="form-textarea" name="goal" required maxlength="4000" ${locked?'disabled':''}>${escapeHtml(answers.goal||'')}</textarea></label>
        <fieldset><legend>Existing plans available?</legend><label><input type="radio" name="hasExistingPlans" value="true" ${answers.hasExistingPlans===true?'checked':''} ${locked?'disabled':''}> Yes</label><label><input type="radio" name="hasExistingPlans" value="false" ${answers.hasExistingPlans===false?'checked':''} ${locked?'disabled':''}> No</label></fieldset>
        <fieldset><legend>Work already exists?</legend><label><input type="radio" name="workAlreadyExists" value="true" ${answers.workAlreadyExists===true?'checked':''} ${locked?'disabled':''}> Yes</label><label><input type="radio" name="workAlreadyExists" value="false" ${answers.workAlreadyExists===false?'checked':''} ${locked?'disabled':''}> No</label></fieldset>
        <label>Budget position<select class="form-select" name="budgetBand" required ${locked?'disabled':''}><option value="">Choose one</option><option value="defined"${selected('budgetBand','defined')}>Defined</option><option value="range"${selected('budgetBand','range')}>Working range</option><option value="needs_guidance"${selected('budgetBand','needs_guidance')}>Needs guidance</option><option value="unknown"${selected('budgetBand','unknown')}>Unknown</option></select></label>
        <fieldset class="trust-wide"><legend>Support needed</legend><label><input type="checkbox" name="supportNeeds" value="plans"${checked('supportNeeds','plans')} ${locked?'disabled':''}> Plans</label><label><input type="checkbox" name="supportNeeds" value="approvals"${checked('supportNeeds','approvals')} ${locked?'disabled':''}> Approvals</label><label><input type="checkbox" name="supportNeeds" value="construction_pricing"${checked('supportNeeds','construction_pricing')} ${locked?'disabled':''}> Construction pricing</label><label><input type="checkbox" name="supportNeeds" value="full_delivery"${checked('supportNeeds','full_delivery')} ${locked?'disabled':''}> Full delivery</label><label><input type="checkbox" name="supportNeeds" value="advice_only"${checked('supportNeeds','advice_only')} ${locked?'disabled':''}> Advice only</label></fieldset>
        ${locked?'':`<div class="trust-actions trust-wide"><button class="btn ghost" type="submit">Save draft</button><button class="btn dark" type="button" id="guidedBriefSubmit">Submit for human review</button><span id="guidedBriefMessage" role="status"></span></div>`}
      </form>${interpretation}
      <section class="brief-evidence"><div class="trust-card-head"><div><h3>Private evidence</h3><p>Files are content-checked, integrity-hashed and kept outside the public web root.</p></div></div>
        ${locked?'':`<form id="guidedBriefEvidence" class="scaffold-grid"><label class="trust-wide">Evidence file<input class="form-input" type="file" name="file" required accept="application/pdf,image/jpeg,image/png,.docx"></label><label>Category<select class="form-select" name="category"><option value="existing_plan">Existing plan</option><option value="title_deed">Title deed</option><option value="site_photo">Site photo</option><option value="brief_document">Brief document</option><option value="other">Other</option></select></label><label>Retain until<input class="form-input" type="date" name="retentionUntil" required></label><label class="trust-wide">Processing purpose<input class="form-input" name="processingPurpose" required maxlength="500" value="Assess information supplied for this guided brief"></label><label class="trust-check trust-wide"><input type="checkbox" name="consentGiven" required> I consent to processing this file for the stated purpose and period.</label><div class="trust-actions trust-wide"><button class="btn dark" type="submit">Upload private evidence</button><span id="guidedBriefEvidenceMessage" role="status"></span></div></form>`}
        <div class="trust-list">${files.length?files.map(file=>`<article class="trust-row"><div><b>${escapeHtml(file.originalName)}</b><span>${escapeHtml(file.category.replaceAll('_',' '))} · ${Math.ceil(file.byteSize/1024)} KB</span><small>Retain until ${escapeHtml(file.retentionUntil)} · SHA-256 ${escapeHtml(file.sha256.slice(0,12))}…</small></div><a class="btn ghost" href="/api/v1/guided-brief-files/${encodeURIComponent(file.id)}">Download</a></article>`).join(''):'<div class="notice">No evidence attached.</div>'}</div>
      </section>${brief.status==='submitted'?`<form id="guidedBriefAssign" class="brief-assign"><label>Convert into project record<select class="form-select" name="projectId" required><option value="">Select project</option>${projects.map(project=>`<option value="${escapeHtml(project.id)}">${escapeHtml(project.name)}</option>`).join('')}</select></label><button class="btn dark" type="submit" ${projects.length?'':'disabled'}>Assign approved brief</button><span id="guidedBriefAssignMessage" role="status"></span></form>`:''}`;
  }

  async function hydrateGuidedBriefWorkspace() {
    const list=document.getElementById('guidedBriefList');if(!list)return;
    try{
      const briefs=await api.get('/guided-briefs');if(activeBriefId&&!briefs.some(item=>item.id===activeBriefId))activeBriefId=null;
      list.innerHTML=briefs.length?briefs.map(item=>`<button class="brief-list-row ${item.id===activeBriefId?'active':''}" type="button" data-brief-id="${escapeHtml(item.id)}"><span><b>${escapeHtml(item.title)}</b><small>Step ${item.currentStep}/8 · v${item.version}</small></span><span class="status ${item.status==='draft'?'amber':'green'}">${escapeHtml(item.status)}</span></button>`).join(''):'<div class="notice">No guided briefs yet.</div>';
      list.querySelectorAll('[data-brief-id]').forEach(button=>button.addEventListener('click',()=>{activeBriefId=button.dataset.briefId;hydrateGuidedBriefWorkspace();}));
      const active=briefs.find(item=>item.id===activeBriefId);if(active){const files=await api.get(`/guided-briefs/${encodeURIComponent(active.id)}/files`);document.getElementById('guidedBriefEditor').innerHTML=briefEditor(active,files);bindGuidedBriefEditor(active);}
      const create=document.getElementById('guidedBriefCreate');if(create&&!create.dataset.bound){create.dataset.bound='1';create.addEventListener('submit',createGuidedBrief);}
    }catch(error){list.innerHTML=`<div class="notice">${escapeHtml(error.message)}</div>`;}
  }

  async function createGuidedBrief(event){event.preventDefault();const title=new FormData(event.currentTarget).get('title');try{const brief=await api.post('/guided-briefs',{title});activeBriefId=brief.id;await hydrateGuidedBriefWorkspace();}catch(error){showToast(error.message);}}
  function bindGuidedBriefEditor(brief){const form=document.getElementById('guidedBriefAnswers');form?.addEventListener('submit',event=>saveGuidedBrief(event,brief,false));document.getElementById('guidedBriefSubmit')?.addEventListener('click',event=>saveGuidedBrief(event,brief,true));document.getElementById('guidedBriefEvidence')?.addEventListener('submit',event=>uploadGuidedBriefEvidence(event,brief));document.getElementById('guidedBriefAssign')?.addEventListener('submit',event=>assignGuidedBrief(event,brief));const retention=document.querySelector('#guidedBriefEvidence [name="retentionUntil"]');if(retention&&!retention.value){const date=new Date();date.setFullYear(date.getFullYear()+5);retention.value=date.toISOString().slice(0,10);}}
  async function saveGuidedBrief(event,brief,submit){event.preventDefault();const form=document.getElementById('guidedBriefAnswers'),values=new FormData(form),message=document.getElementById('guidedBriefMessage');if(message)message.textContent=submit?'Validating…':'Saving…';const bool=name=>values.get(name)==='true';const body={expectedVersion:brief.version,currentStep:submit?8:Math.max(brief.currentStep,7),intent:values.get('intent'),goal:values.get('goal'),hasExistingPlans:bool('hasExistingPlans'),workAlreadyExists:bool('workAlreadyExists'),urgency:values.get('urgency'),budgetBand:values.get('budgetBand'),supportNeeds:values.getAll('supportNeeds')};try{const saved=await api.post(`/guided-briefs/${encodeURIComponent(brief.id)}`,body);if(submit)await api.post(`/guided-briefs/${encodeURIComponent(brief.id)}/submit`,{expectedVersion:saved.version});await hydrateGuidedBriefWorkspace();showToast(submit?'Brief submitted for human review.':'Draft saved.');}catch(error){if(message)message.textContent=error.message;}}
  async function uploadGuidedBriefEvidence(event,brief){event.preventDefault();const form=event.currentTarget,message=document.getElementById('guidedBriefEvidenceMessage'),body=new FormData(form);message.textContent='Uploading…';try{const response=await fetch(`/api/v1/guided-briefs/${encodeURIComponent(brief.id)}/files`,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','X-Organisation-Id':api.organisationId,'X-CSRF-Token':api.csrfToken},body});const payload=await response.json();if(!response.ok)throw Object.assign(new Error(payload?.error?.message||'Evidence upload failed.'),{status:response.status});message.textContent='Evidence stored privately and audited.';form.elements.file.value='';await hydrateGuidedBriefWorkspace();}catch(error){message.textContent=error.message;}}
  async function assignGuidedBrief(event,brief){event.preventDefault();const message=document.getElementById('guidedBriefAssignMessage'),projectId=new FormData(event.currentTarget).get('projectId');message.textContent='Converting…';try{await api.post(`/guided-briefs/${encodeURIComponent(brief.id)}/assign`,{projectId,expectedVersion:brief.version});await hydrateGuidedBriefWorkspace();showToast('Guided brief converted into an approved project record.');}catch(error){message.textContent=error.message;}}

  function trustWorkspace() {
    setTimeout(hydrateTrustWorkspace, 0);
    return `${pageHead('Professional trust & directory','Manage the public-safe practice profile and evidence-backed verification status for this organisation.','settings','core')}
      <div class="notice trust-boundary"><b>Verification boundary:</b> self-declared, document-uploaded, manually reviewed and externally verified are different states. Architex does not claim an external provider check unless a real provider reference is recorded.</div>
      <section class="trust-layout">
        <form id="trustProfileForm" class="panel panel-pad trust-card">
          <div class="trust-card-head"><div><h2>Directory profile</h2><p>Only these public profile fields and the current badge are returned by directory search.</p></div><span id="trustProfileStatus" class="status">Loading…</span></div>
          <div class="scaffold-grid">
            <label>Display name<input class="form-input" name="displayName" required maxlength="180"></label>
            <label>Profession<select class="form-select" name="profession"><option value="architect">Architect</option><option value="engineer">Engineer</option><option value="quantity_surveyor">Quantity surveyor</option><option value="town_planner">Town planner</option><option value="project_manager">Project manager</option><option value="contractor">Contractor</option><option value="supplier">Supplier</option></select></label>
            <label class="trust-wide">Service areas<input class="form-input" name="serviceAreas" placeholder="Gauteng, Western Cape"></label>
            <label class="trust-wide">Public bio<textarea class="form-textarea" name="bio" maxlength="4000"></textarea></label>
            <label class="trust-check"><input type="checkbox" name="directoryVisible"> Publish this profile in directory search</label>
          </div>
          <div class="trust-actions"><button class="btn dark" type="submit">Save directory profile</button><span id="trustProfileMessage" role="status"></span></div>
        </form>
        <form id="trustVerificationForm" class="panel panel-pad trust-card">
          <div class="trust-card-head"><div><h2>Submit verification evidence</h2><p>Evidence stays private and requires purpose, consent and retention metadata.</p></div></div>
          <div class="scaffold-grid">
            <label>Verification type<select class="form-select" name="kind"><option value="professional_registration">Professional registration</option><option value="company_document">Company document</option><option value="insurance_compliance">Insurance / PI</option><option value="contractor_supplier_compliance">Contractor / supplier compliance</option></select></label>
            <label>Evidence grade<select class="form-select" name="grade"><option value="self_declared">Self-declared</option><option value="document_uploaded">Document uploaded</option></select></label>
            <label>Registration body<input class="form-input" name="registrationBody" maxlength="120" placeholder="e.g. SACAP"></label>
            <label>Registration / reference number<input class="form-input" name="registrationNumber" maxlength="120"></label>
            <label class="trust-wide">Private evidence file<input class="form-input" name="evidence" type="file" accept="application/pdf,image/jpeg,image/png"></label>
            <label class="trust-wide">Processing purpose<input class="form-input" name="purpose" required maxlength="500" value="Professional directory verification"></label>
            <label>Retain until<input class="form-input" name="retentionUntil" type="date" required></label>
            <label>Credential expires<input class="form-input" name="expiresOn" type="date"></label>
            <label class="trust-check trust-wide"><input type="checkbox" name="consentGiven" required> I consent to this evidence being processed for the stated purpose and retention period.</label>
          </div>
          <div class="trust-actions"><button class="btn dark" type="submit">Submit evidence</button><span id="trustVerificationMessage" role="status"></span></div>
        </form>
      </section>
      <section class="trust-layout trust-results"><div class="panel panel-pad trust-card"><div class="trust-card-head"><div><h2>Verification history</h2><p>Review decisions are appended to immutable history.</p></div></div><div id="trustVerificationList" class="trust-list"><div class="notice">Loading verification records…</div></div></div><div class="panel panel-pad trust-card"><div class="trust-card-head"><div><h2>Directory preview</h2><p>Exactly the safe projection visible to authenticated directory users.</p></div></div><div id="trustDirectoryList" class="trust-list"><div class="notice">Loading directory…</div></div></div></section>`;
  }

  window.renderGlobalPage = function (id) {
    return id === 'settings' ? trustWorkspace() : originalGlobalPage(id);
  };

  TOOLS.professional_directory.status = 'live';
  TOOLS.team_workspace.status = 'live';
  TOOLS.project_passport.status = 'live';
  const originalRenderMain = window.renderMain;
  window.renderMain = function () {
    if (state.tool === 'project_passport') {
      document.getElementById('main').innerHTML = guidedBriefWorkspace();
      return;
    }
    if (state.tool === 'team_workspace') {
      document.getElementById('main').innerHTML = teamWorkspace();
      setTimeout(hydrateTeamWorkspace, 0);
      return;
    }
    originalRenderMain();
  };

  function teamWorkspace() {
    const activeProject=window.__ARCHITEX_SHELL_STATE__?.projects.find(item=>item.id===window.__ARCHITEX_SHELL_STATE__?.selectedProjectId);
    return `${pageHead('Project team','Invite independently verified professionals, track acceptance and preserve assignment history.','team_workspace','coral')}
      <div class="notice team-boundary"><b>Access boundary:</b> a project invitation grants only the recorded project assignment after acceptance. It never grants organisation administration or changes the professional’s own practice membership.</div>
      <section class="team-layout">
        <form id="projectTeamInviteForm" class="panel panel-pad team-card">
          <div class="trust-card-head"><div><h2>Invite a verified professional</h2><p>${escapeHtml(activeProject?.name||'Select a project')} · eligible directory records only</p></div><span class="status">Audited</span></div>
          <div class="scaffold-grid">
            <label class="trust-wide">Professional<select class="form-select" name="candidateId" required><option value="">Loading verified directory…</option></select></label>
            <label>Discipline<input class="form-input" name="discipline" required maxlength="80" placeholder="e.g. structure"></label>
            <label>Project role<input class="form-input" name="roleLabel" required maxlength="120" placeholder="e.g. Structural engineer"></label>
          </div>
          <div class="trust-actions"><button class="btn dark" type="submit">Send invitation</button><span id="projectTeamInviteMessage" role="status"></span></div>
        </form>
        <div class="panel panel-pad team-card"><div class="trust-card-head"><div><h2>Incoming invitations</h2><p>Accept only into the named project and discipline.</p></div></div><div id="projectTeamPending" class="trust-list"><div class="notice">Loading invitations…</div></div></div>
      </section>
      <section class="panel panel-pad team-card team-current"><div class="trust-card-head"><div><h2>Current project team</h2><p>Pending and active assignments. Removed records remain in audit history.</p></div><button class="btn ghost" type="button" onclick="refreshProjectTeam()">Refresh</button></div><div id="projectTeamList" class="trust-list"><div class="notice">Loading project team…</div></div></section>`;
  }

  async function hydrateTeamWorkspace() {
    const form=document.getElementById('projectTeamInviteForm');if(!form)return;
    const projectId=window.__ARCHITEX_SHELL_STATE__?.selectedProjectId;
    if(!projectId){
      document.getElementById('projectTeamList').innerHTML='<div class="notice">Create or select a project before managing its team.</div>';
      form.querySelectorAll('input,select,button').forEach(control=>control.disabled=true);
      try{renderPendingInvitations(await api.get('/team-invitations'));}catch(error){document.getElementById('projectTeamPending').innerHTML=`<div class="notice">${escapeHtml(error.message)}</div>`;}
      return;
    }
    try {
      const [directory,team,pending]=await Promise.all([api.get('/directory/search'),api.get(`/projects/${encodeURIComponent(projectId)}/team`),api.get('/team-invitations')]);
      const currentUser=window.__ARCHITEX_SHELL_STATE__?.session.userId;
      const eligible=directory.filter(item=>['manually_reviewed','externally_verified'].includes(item.verificationGrade)&&item.userId!==currentUser);
      const select=form.elements.candidateId;
      select.innerHTML='<option value="">Select a verified professional</option>'+eligible.map(item=>`<option value="${escapeHtml(item.userId)}">${escapeHtml(item.displayName)} · ${escapeHtml(item.profession.replaceAll('_',' '))} · ${escapeHtml(item.verificationGrade.replaceAll('_',' '))}</option>`).join('');
      if(!eligible.length)select.innerHTML='<option value="">No independently verified candidates available</option>';
      renderProjectTeam(team);renderPendingInvitations(pending);
      if(!form.dataset.teamBound){form.dataset.teamBound='1';form.addEventListener('submit',submitProjectTeamInvitation);}
    } catch(error){document.getElementById('projectTeamList').innerHTML=`<div class="notice">${escapeHtml(error.message)}</div>`;if(!form.dataset.teamBound){form.dataset.teamBound='1';form.addEventListener('submit',submitProjectTeamInvitation);}}
  }

  async function submitProjectTeamInvitation(event) {
    event.preventDefault();const form=event.currentTarget,values=new FormData(form),message=document.getElementById('projectTeamInviteMessage'),projectId=window.__ARCHITEX_SHELL_STATE__?.selectedProjectId;message.textContent='Sending…';
    try{await api.post(`/projects/${encodeURIComponent(projectId)}/team`,{candidateId:values.get('candidateId'),discipline:values.get('discipline'),roleLabel:values.get('roleLabel')});message.textContent='Invitation sent and audited.';form.reset();await hydrateTeamWorkspace();}
    catch(error){message.textContent=error.message;}
  }

  function renderProjectTeam(team) {
    const list=document.getElementById('projectTeamList');if(!list)return;
    list.innerHTML=team.length?team.map(member=>`<article class="trust-row team-row"><div><b>${escapeHtml(member.displayName||member.roleLabel)}</b><span>${escapeHtml(member.roleLabel)} · ${escapeHtml(member.discipline)}</span><small>${escapeHtml(member.profession||'registered professional')} · ${escapeHtml(member.verificationGrade.replaceAll('_',' '))}</small></div><div class="trust-row-actions"><span class="status ${member.status==='active'?'green':'amber'}">${escapeHtml(member.status)}</span><button class="btn ghost" type="button" onclick="removeProjectTeamMember('${escapeHtml(member.id)}')">Remove</button></div></article>`).join(''):'<div class="notice">No pending or active project team assignments.</div>';
  }

  function renderPendingInvitations(invitations) {
    const list=document.getElementById('projectTeamPending');if(!list)return;
    list.innerHTML=invitations.length?invitations.map(item=>`<article class="trust-row team-row"><div><b>${escapeHtml(item.projectName)}</b><span>${escapeHtml(item.organisationName)} · ${escapeHtml(item.roleLabel)}</span><small>${escapeHtml(item.discipline)} · invited by ${escapeHtml(item.inviterEmail)}</small></div><button class="btn dark" type="button" onclick="acceptProjectTeamInvitation('${escapeHtml(item.id)}')">Accept</button></article>`).join(''):'<div class="notice">No pending project team invitations.</div>';
  }

  window.refreshProjectTeam=hydrateTeamWorkspace;
  window.acceptProjectTeamInvitation=async function(id){try{await api.post(`/team-invitations/${encodeURIComponent(id)}/accept`);showToast('Project team invitation accepted.');await hydrateTeamWorkspace();}catch(error){showToast(error.message)}};
  window.removeProjectTeamMember=async function(id){const reason=prompt('Reason for ending this project assignment?');if(!reason?.trim())return;const projectId=window.__ARCHITEX_SHELL_STATE__?.selectedProjectId;try{await api.request('DELETE',`/projects/${encodeURIComponent(projectId)}/team/${encodeURIComponent(id)}`,{reason:reason.trim()});showToast('Project team assignment ended; history preserved.');await hydrateTeamWorkspace();}catch(error){showToast(error.message)}};

  const originalRenderAll = window.renderAll;
  window.renderAll = function () {
    originalRenderAll();
    if(state.tool==='project_passport'){
      renderBriefNavigator();renderBriefInspector();
    }
    if(state.tool==='team_workspace'){
      document.getElementById('main').innerHTML=teamWorkspace();
      renderTeamNavigator();renderTeamInspector();setTimeout(hydrateTeamWorkspace,0);
    }
    enforceProductionControls(window.__ARCHITEX_SHELL_STATE__?.session || null);
  };

  function renderTeamNavigator(){const nav=document.getElementById('navigator'),active=window.__ARCHITEX_SHELL_STATE__?.projects.find(item=>item.id===window.__ARCHITEX_SHELL_STATE__?.selectedProjectId);if(!nav)return;nav.innerHTML=`<div class="nav-head"><div class="kicker">Project collaboration</div><div class="nav-title">Project team</div><div class="nav-sub">${escapeHtml(active?.name||'No project selected')}</div></div><div class="back-row" onclick="openProjectSpace()"><span>←</span><span class="nav-label">Back to datum project space</span></div><div class="nav-section"><div class="nav-heading">Team workflow</div><button class="nav-row active" onclick="document.getElementById('projectTeamList')?.scrollIntoView({behavior:'smooth'})"><span class="nav-icon">${icon('team_workspace',21)}</span><span class="nav-label">Current team</span></button><button class="nav-row" onclick="document.getElementById('projectTeamInviteForm')?.scrollIntoView({behavior:'smooth'})"><span class="nav-icon">${icon('professional_directory',21)}</span><span class="nav-label">Invite professional</span></button><button class="nav-row" onclick="document.getElementById('projectTeamPending')?.scrollIntoView({behavior:'smooth'})"><span class="nav-icon">${icon('inbox',21)}</span><span class="nav-label">Incoming invitations</span></button></div><div class="nav-note"><b>Production workflow:</b><br>Only independently verified directory professionals are eligible. Acceptance, removal and notifications are persisted and audited.</div>`;}
  function renderTeamInspector(){const title=document.getElementById('inspectTitle'),sub=document.getElementById('inspectSub'),body=document.getElementById('inspectBody');if(title)title.textContent='Project team';if(sub)sub.textContent='Verified assignments';if(body)body.innerHTML='<div class="inspect-note"><b>Least-privilege assignment</b><br>Accepting an invitation adds a project-team record only. Organisation membership is never inferred.</div><div class="inspect-block"><h4>Controlled states</h4><p>Invited<br>Active<br>Removed with reason<br>Immutable audit events</p></div><div class="inspect-block"><h4>Verification gate</h4><p>Manual or external verification must be current both when invited and when accepted.</p></div>';}
  function renderBriefNavigator(){const nav=document.getElementById('navigator');if(!nav)return;nav.innerHTML=`<div class="nav-head"><div class="kicker">Client onboarding</div><div class="nav-title">Guided brief</div><div class="nav-sub">Standalone first, project-connected when approved.</div></div><div class="back-row" onclick="openProjectSpace()"><span>←</span><span class="nav-label">Back to datum project space</span></div><div class="nav-section"><div class="nav-heading">Brief workflow</div><div class="nav-row active"><span class="nav-icon">${icon('brief',21)}</span><span class="nav-label">Capture intent</span></div><div class="nav-row"><span class="nav-icon">${icon('documents_drawings',21)}</span><span class="nav-label">Private evidence</span></div><div class="nav-row"><span class="nav-icon">${icon('integration',21)}</span><span class="nav-label">Human review & convert</span></div></div><div class="nav-note"><b>Resumable by design:</b><br>Every save uses optimistic version control. Submitted content locks before project conversion.</div>`;}
  function renderBriefInspector(){const title=document.getElementById('inspectTitle'),sub=document.getElementById('inspectSub'),body=document.getElementById('inspectBody');if(title)title.textContent='Guided brief';if(sub)sub.textContent='Advisory onboarding';if(body)body.innerHTML='<div class="inspect-note"><b>Human decision remains required.</b><br>Automated interpretation identifies possible routes and risks; it never represents statutory or professional approval.</div><div class="inspect-block"><h4>Evidence controls</h4><p>Explicit consent<br>Declared purpose<br>Retention date<br>Content validation<br>SHA-256 integrity</p></div>';}

  async function hydrateTrustWorkspace() {
    const profileForm = document.getElementById('trustProfileForm');
    if (!profileForm) return;
    const profileResult = await api.get('/professional-profile').then(data => ({data})).catch(error => ({error}));
    if (profileResult.data) {
      const profile = profileResult.data;
      profileForm.elements.displayName.value = profile.displayName;
      profileForm.elements.profession.value = profile.profession;
      profileForm.elements.serviceAreas.value = profile.serviceAreas.join(', ');
      profileForm.elements.bio.value = profile.bio;
      profileForm.elements.directoryVisible.checked = profile.directoryVisible;
      document.getElementById('trustProfileStatus').textContent = profile.directoryVisible ? 'Published' : 'Private';
      document.getElementById('trustProfileStatus').className = `status ${profile.directoryVisible ? 'green' : ''}`;
    } else {
      document.getElementById('trustProfileStatus').textContent = profileResult.error?.status === 404 ? 'Not created' : 'Unavailable';
    }
    const retention = document.querySelector('#trustVerificationForm [name="retentionUntil"]');
    if (retention && !retention.value) { const date = new Date(); date.setFullYear(date.getFullYear() + 5); retention.value = date.toISOString().slice(0,10); }
    profileForm.addEventListener('submit', saveTrustProfile, { once:true });
    document.getElementById('trustVerificationForm')?.addEventListener('submit', submitTrustVerification, { once:true });
    await refreshTrustResults();
  }

  async function saveTrustProfile(event) {
    event.preventDefault();
    const form = event.currentTarget, message = document.getElementById('trustProfileMessage'), values = new FormData(form);
    message.textContent = 'Saving…';
    try {
      await api.post('/professional-profile', {displayName:values.get('displayName'),profession:values.get('profession'),bio:values.get('bio'),serviceAreas:String(values.get('serviceAreas')||'').split(',').map(value=>value.trim()).filter(Boolean),directoryVisible:values.get('directoryVisible')==='on'});
      message.textContent = 'Saved and audited.';
      await refreshTrustResults();
    } catch (error) { message.textContent = error.message; }
    form.addEventListener('submit', saveTrustProfile, { once:true });
  }

  async function submitTrustVerification(event) {
    event.preventDefault();
    const form=event.currentTarget,message=document.getElementById('trustVerificationMessage'),values=new FormData(form),grade=values.get('grade'),file=form.elements.evidence.files[0];
    message.textContent='Submitting…';
    try {
      let evidenceFileId=null;
      if (grade==='document_uploaded') {
        if (!file) throw new Error('Select a real evidence file for document-uploaded status.');
        const projectId=window.__ARCHITEX_SHELL_STATE__?.selectedProjectId;
        if (!projectId) throw new Error('Create or select a project before uploading private evidence.');
        const uploadBody=new FormData();uploadBody.append('file',file);
        const response=await fetch(`/api/v1/projects/${encodeURIComponent(projectId)}/files`,{method:'POST',credentials:'same-origin',headers:{'Accept':'application/json','X-Organisation-Id':api.organisationId,'X-CSRF-Token':api.csrfToken},body:uploadBody});
        const payload=await response.json();if(!response.ok)throw new Error(payload?.error?.message||'Evidence upload failed.');evidenceFileId=payload.data.id;
      }
      await api.post('/verifications',{kind:values.get('kind'),grade,registrationBody:values.get('registrationBody'),registrationNumber:values.get('registrationNumber'),evidenceFileId,consentGiven:values.get('consentGiven')==='on',purpose:values.get('purpose'),retentionUntil:values.get('retentionUntil'),expiresOn:values.get('expiresOn')||null});
      message.textContent='Evidence submitted with its declared grade.';form.elements.evidence.value='';await refreshTrustResults();
    } catch(error){message.textContent=error.message;}
    form.addEventListener('submit',submitTrustVerification,{once:true});
  }

  async function reviewTrustVerification(id, decision) {
    try { await api.post(`/verifications/${encodeURIComponent(id)}/review`,{decision,note:'Reviewed by an authorised organisation administrator.'}); await refreshTrustResults(); }
    catch(error){showToast(error.message);}
  }
  window.reviewTrustVerification=reviewTrustVerification;

  async function refreshTrustResults() {
    const [records,directory]=await Promise.all([api.get('/verifications'),api.get('/directory/search')]);
    const recordList=document.getElementById('trustVerificationList'),directoryList=document.getElementById('trustDirectoryList');
    if(recordList)recordList.innerHTML=records.length?records.map(record=>`<article class="trust-row"><div><b>${escapeHtml(record.kind.replaceAll('_',' '))}</b><span>${escapeHtml(record.registration_body||'No registration body')} · ${escapeHtml(record.registration_number||'No reference')}</span><small>Retention ${escapeHtml(record.retention_until)}${record.expires_on?' · Expires '+escapeHtml(record.expires_on):''}</small></div><div class="trust-row-actions"><span class="status ${record.effectiveGrade==='expired'||record.grade==='rejected'?'amber':'green'}">${escapeHtml(record.effectiveGrade.replaceAll('_',' '))}</span>${['self_declared','document_uploaded'].includes(record.grade)?'<small>Independent review pending</small>':''}</div></article>`).join(''):'<div class="notice">No verification evidence has been submitted.</div>';
    if(directoryList)directoryList.innerHTML=directory.length?directory.map(profile=>`<article class="trust-row"><div><b>${escapeHtml(profile.displayName)}</b><span>${escapeHtml(profile.profession.replaceAll('_',' '))} · ${profile.serviceAreas.map(escapeHtml).join(', ')}</span><small>${escapeHtml(profile.bio)}</small></div><span class="status ${profile.verificationGrade?'green':''}">${escapeHtml((profile.verificationGrade||'unverified').replaceAll('_',' '))}</span></article>`).join(''):'<div class="notice">No published directory profiles.</div>';
  }
  function enforceProductionControls(session) {
    const roleSwitch = document.getElementById('roleSwitch');
    roleSwitch?.style.setProperty('display', 'none', 'important');
    roleSwitch?.setAttribute('aria-hidden', 'true');
    const roleSelect = roleSwitch?.querySelector('select');
    if (roleSelect) { roleSelect.id = 'productionRoleLens'; roleSelect.name = 'productionRoleLens'; roleSelect.disabled = true; roleSelect.setAttribute('aria-label', 'Demo role lens disabled in production'); }
    document.getElementById('godModeToggle')?.style.setProperty('display', 'none', 'important');
    const user = document.querySelector('.rail-user');
    if (user) user.innerHTML = session
      ? `<div class="avatar">${session.email.slice(0,2).toUpperCase()}</div><div class="user-copy"><b>${session.email}</b><span>${session.memberships[0]?.role || 'member'} · verified organisation access</span></div>`
      : '<div class="avatar">—</div><div class="user-copy"><b>Signed out</b><span>No organisation data loaded</span></div>';
  }
  function renderSignIn() {
    const main = document.getElementById('main');
    if (!main) return;
    enforceProductionControls(null);
    const navigator = document.getElementById('navigator');
    if (navigator) navigator.innerHTML = '<div class="nav-head"><div class="kicker">Account access</div><div class="nav-title">Sign in required</div><div class="nav-sub">Project, inbox, and tool context loads only after authentication.</div></div>';
    const breadcrumb = document.getElementById('breadcrumb');
    if (breadcrumb) breadcrumb.textContent = 'Architex OS › Sign in';
    const projectChip = document.getElementById('projectChip');
    if (projectChip) projectChip.textContent = '○ No project loaded';
    const inspectorTitle = document.getElementById('inspectTitle');
    if (inspectorTitle) inspectorTitle.textContent = 'Account context';
    const inspectorSub = document.getElementById('inspectSub');
    if (inspectorSub) inspectorSub.textContent = 'Signed out';
    const inspectorBody = document.getElementById('inspectBody');
    if (inspectorBody) inspectorBody.innerHTML = '<div class="inspect-note"><b>No project data is loaded.</b><br>Sign in with a verified organisation account to continue.</div>';
    main.innerHTML = `<section class="panel panel-pad" style="max-width:520px;margin:40px auto"><div class="page-copy"><h1>Sign in to Architex</h1><p>Use your verified organisation account to load live project data.</p></div><form id="architexSignIn" style="display:grid;gap:12px;margin-top:20px"><label>Email<input class="form-input" name="email" type="email" autocomplete="username" required></label><label>Password<input class="form-input" name="password" type="password" autocomplete="current-password" required></label><button class="btn dark" type="submit">Sign in</button><div id="architexSignInError" role="alert"></div></form></section>`;
    document.getElementById('architexSignIn').addEventListener('submit', async event => {
      event.preventDefault();
      const form = new FormData(event.currentTarget);
      const error = document.getElementById('architexSignInError');
      error.textContent = '';
      try {
        const result = await api.post('/sessions', { email:form.get('email'), password:form.get('password') });
        api.setCsrf(result.csrfToken);
        await hydrate(result.identity);
      } catch (failure) { error.textContent = failure.message; }
    });
  }
  async function hydrate(session) {
    const membership = session.memberships?.[0];
    if (!membership) throw new Error('No active organisation membership.');
    api.setOrganisation(membership.organisationId);
    const [projects, inbox, tools] = await Promise.all([api.get('/projects'), api.get('/inbox'), api.get('/tools')]);
    window.__ARCHITEX_SHELL_STATE__ = Object.freeze({ session, projects:Object.freeze(projects), inbox:Object.freeze(inbox), tools:window.ArchitexToolRegistry.reconcile(TOOLS, tools), selectedProjectId:projects[0]?.id || null });
    if (projects[0]) window.ArchitexDatum.apply(project, projects[0]);
    state.role = projects[0]?.leadProfessionalRole || 'architect';
    window.setRole = () => showToast('Production access follows your verified organisation role.');
    root.dataset.shellState = projects.length ? 'ready' : 'empty';
    renderAll();
    enforceProductionControls(session);
    if (!projects.length) renderEmptyProjects(session);
    else { syncProjectSelector(projects, session, inbox); markPrototypeSurfaces(inbox); }
  }
  function syncProjectSelector(projects, session, inbox) {
    const selector = document.querySelector('#navigator select');
    if (!selector) return;
    selector.id = 'activeProjectSelector'; selector.name = 'activeProjectId'; selector.setAttribute('aria-label', 'Active project');
    selector.replaceChildren(...projects.map(item => Object.assign(document.createElement('option'), { value:item.id, textContent:item.name, selected:item.id === window.__ARCHITEX_SHELL_STATE__.selectedProjectId })));
    selector.onchange = null;
    selector.addEventListener('change', () => {
      const selected = projects.find(item => item.id === selector.value);
      if (!selected) return;
      window.ArchitexDatum.apply(project, selected);
      window.__ARCHITEX_SHELL_STATE__ = Object.freeze({ ...window.__ARCHITEX_SHELL_STATE__, selectedProjectId:selected.id });
      renderAll(); enforceProductionControls(session); syncProjectSelector(projects, session, inbox); markPrototypeSurfaces(inbox);
    }, { once:true });
  }
  function markPrototypeSurfaces(inbox) {
    document.querySelectorAll('.datum-card').forEach(card => {
      const rows = card.querySelectorAll('.mini-row');
      if (rows[0]) rows[0].textContent = 'Prototype workspace';
      if (rows[1]) rows[1].textContent = 'No live Release 1 metrics';
    });
    const main = document.getElementById('main');
    if (main && !main.querySelector('.release1-data-note')) main.insertAdjacentHTML('afterbegin', '<div class="notice release1-data-note"><b>Live implemented scope:</b> Release 1 project identity, phase, readiness, account, inbox and file data plus Release 2 professional trust, guided briefs and project-team invitations come from MariaDB. Other tool metrics remain non-authoritative prototypes until their release pack is implemented.</div>');
    document.querySelectorAll('#navigator .badge').forEach(badge => { if (badge.closest('button')?.textContent.includes('Action Centre')) badge.textContent = String(inbox.length); });
  }
  function renderEmptyProjects(session) {
    const main = document.getElementById('main');
    if (!main) return;
    const navigator = document.getElementById('navigator');
    if (navigator) navigator.innerHTML = '<div class="nav-head"><div class="kicker">Active organisation</div><div class="nav-title">No projects yet</div><div class="nav-sub">Start with a standalone brief or create the first project.</div></div><div class="nav-section"><button class="nav-row" onclick="openTool(\'project_passport\',\'standalone\')"><span class="nav-icon">'+icon('brief',21)+'</span><span class="nav-label">Open guided brief</span></button></div>';
    const breadcrumb = document.getElementById('breadcrumb');
    if (breadcrumb) breadcrumb.textContent = 'Architex OS › Projects › New project';
    const projectChip = document.getElementById('projectChip');
    if (projectChip) projectChip.textContent = '○ No project yet';
    const inspectorTitle = document.getElementById('inspectTitle');
    if (inspectorTitle) inspectorTitle.textContent = 'Organisation context';
    const inspectorSub = document.getElementById('inspectSub');
    if (inspectorSub) inspectorSub.textContent = session.email;
    const inspectorBody = document.getElementById('inspectBody');
    if (inspectorBody) inspectorBody.innerHTML = '<div class="inspect-note"><b>No demo project is shown.</b><br>Create a live project to initialise its passport, lifecycle, records and audit trail.</div>';
    main.innerHTML = `<section class="panel panel-pad" style="max-width:720px;margin:30px auto"><div class="page-copy"><h1>Create your first project</h1><p>Your organisation is ready. This project will persist in MariaDB and become the active datum.</p></div><form id="architexCreateProject" class="scaffold-grid" style="margin-top:18px"><label>Project code<input class="form-input" name="projectCode" required maxlength="60"></label><label>Project name<input class="form-input" name="projectName" required maxlength="180"></label><label>Client / developer<input class="form-input" name="clientName" required></label><label>Municipality<input class="form-input" name="municipality" required></label><label>Property reference<input class="form-input" name="propertyReference" required></label><label>Property use<input class="form-input" name="propertyUse" required></label><label>Lead professional role<select class="form-select" name="leadProfessionalRole"><option value="architect">Architect</option><option value="engineer">Engineer</option><option value="quantity_surveyor">Quantity surveyor</option></select></label><label>Land-use notes<textarea class="form-textarea" name="landUseNotes"></textarea></label><button class="btn dark" type="submit">Create project</button><div id="architexProjectError" role="alert"></div></form></section>`;
    document.getElementById('architexCreateProject').addEventListener('submit', async event => {
      event.preventDefault();
      const values = Object.fromEntries(new FormData(event.currentTarget).entries());
      try { await api.post('/projects', values); await hydrate(session); }
      catch (failure) { document.getElementById('architexProjectError').textContent = failure.message; }
    });
  }
  async function load() {
    try {
      const session = await api.get('/session');
      api.setCsrf(session.csrfToken);
      await hydrate(session);
    } catch (error) {
      root.dataset.shellState = error.status === 401 ? 'signed-out' : 'error';
      if (error.status === 401) { renderSignIn(); return; }
      const main = document.getElementById('main');
      if (main) main.innerHTML = '<section class="panel panel-pad"><div class="notice"><b>Workspace unavailable</b><br>Live project data could not be loaded. Retry shortly.</div></section>';
    }
  }
  window.addEventListener('DOMContentLoaded', load, { once:true });
})();
