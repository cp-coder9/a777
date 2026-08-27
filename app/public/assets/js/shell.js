(function () {
  'use strict';
  const api = new window.ArchitexApi.ApiClient();
  const root = document.documentElement;
  root.dataset.shellState = 'loading';
  const originalGlobalPage = window.renderGlobalPage;
  const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[character]));

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

  const originalRenderAll = window.renderAll;
  window.renderAll = function () {
    originalRenderAll();
    enforceProductionControls(window.__ARCHITEX_SHELL_STATE__?.session || null);
  };

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
    if (main && !main.querySelector('.release1-data-note')) main.insertAdjacentHTML('afterbegin', '<div class="notice release1-data-note"><b>Live implemented scope:</b> Release 1 project identity, phase, readiness, account, inbox and file data plus the Release 2 professional trust slice come from MariaDB. Other tool metrics remain non-authoritative prototypes until their release pack is implemented.</div>');
    document.querySelectorAll('#navigator .badge').forEach(badge => { if (badge.closest('button')?.textContent.includes('Action Centre')) badge.textContent = String(inbox.length); });
  }
  function renderEmptyProjects(session) {
    const main = document.getElementById('main');
    if (!main) return;
    const navigator = document.getElementById('navigator');
    if (navigator) navigator.innerHTML = '<div class="nav-head"><div class="kicker">Active organisation</div><div class="nav-title">No projects yet</div><div class="nav-sub">Create the first project to initialise its datum.</div></div>';
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
