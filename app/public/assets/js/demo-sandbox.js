(function () {
  'use strict';
  const api = new window.ArchitexApi.ApiClient();
  const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[character]));
  const roleGroups = [
    ['Client-side',[['client','Client / Developer'],['project_manager','Project Manager']]],
    ['Design Team',[['architect','Architect (PrArch)'],['architectural_technologist','Architectural Technologist'],['candidate_architect','Candidate Architect'],['energy_consultant','Energy Consultant'],['town_planner','Town Planner'],['environmental_consultant','Environmental Consultant'],['accessibility_consultant','Accessibility Consultant']]],
    ['Engineering',[['engineer_structural','Structural Engineer (PrEng)'],['engineer_civil','Civil Engineer (PrEng)'],['engineer_electrical','Electrical Engineer (PrEng)'],['engineer_mechanical','Mechanical Engineer (PrEng)'],['engineer_fire','Fire Engineer']]],
    ['Quantity Surveying',[['quantity_surveyor','Quantity Surveyor (PrQS)']]],
    ['Construction',[['contractor','Contractor (Main)'],['subcontractor','Subcontractor'],['supplier','Supplier']]],
    ['Platform',[['bep','BEP / Professional'],['freelancer','Freelancer / Draughtsperson'],['admin','Administrator']]],
    ['CPD & Learning',[['cpd_officer','CPD Officer / Administrator']]],
  ];
  let state = null;
  let projects = [];
  let learning = null;
  let enabled = false;
  let unavailable = false;
  let hydrating = false;
  const directRoute = () => location.pathname === '/demo';
  const roleLabel = key => roleGroups.flatMap(group => group[1]).find(role => role[0] === key)?.[1] || key;
  const roleOptions = selected => roleGroups.map(([group,roles]) => `<optgroup label="${escapeHtml(group)}">${roles.map(([key,label])=>`<option value="${key}" ${key===selected?'selected':''}>${escapeHtml(label)}</option>`).join('')}</optgroup>`).join('');

  function configureDemoApi() {
    const shell = window.__ARCHITEX_SHELL_STATE__;
    const membership = shell?.session?.memberships?.[0];
    if (!membership) return false;
    api.setOrganisation(membership.organisationId);
    api.setCsrf(shell.session.csrfToken);
    return true;
  }

  function demoSandboxWorkspace() {
    const counts = state?.counts || {};
    const learningCounts = learning?.counts || {};
    return `<div id="demoSandboxWorkspace" class="demo-sandbox-workspace">
      <div class="page-head"><div class="page-icon">◎</div><div class="page-copy"><h1>Demo Sandbox</h1><p>Explore a deterministic, role-filtered built-environment portfolio backed by the isolated demo organisation.</p></div><div class="page-actions"><span class="status amber">Generation ${Number(state?.generation||1)}</span></div></div>
      <div class="notice demo-sandbox-boundary"><b>Illustrative demonstration only.</b> Records are labelled <code>illustrative_demo_not_for_reliance</code>; learning history is <code>not_accredited_demo_record</code>. Nothing shown is professional advice, certification, submission or accreditation.</div>
      <section class="demo-sandbox-metrics" aria-label="Server-owned demo seed counts">
        ${[['Projects',counts.projects||0],['Personas',counts.personas||0],['Seed records',counts.items||0],['Learning records',Object.values(learningCounts).reduce((sum,value)=>sum+Number(value||0),0)]].map(([label,value])=>`<div class="demo-sandbox-metric"><b>${Number(value)}</b><span>${escapeHtml(label)}</span></div>`).join('')}
      </section>
      <section class="panel panel-pad"><div class="page-copy"><h2>${escapeHtml(roleLabel(state?.selectedRole))} portfolio</h2><p>Visibility is recalculated by MariaDB for the selected role lens.</p></div><div class="demo-sandbox-project-grid">${projects.length?projects.map(project=>`<article class="demo-sandbox-card"><small>${escapeHtml(project.projectCode)}</small><h3>${escapeHtml(project.projectName)}</h3><p>${escapeHtml(project.clientName)} · ${escapeHtml(project.municipality)}<br>${escapeHtml(project.currentPhase)}</p><button class="btn ghost" type="button" onclick="openDemoProject('${escapeHtml(project.id)}')">Inspect project</button></article>`).join(''):'<div class="notice">No projects are assigned to this role lens.</div>'}</div></section>
    </div>`;
  }

  function installControls() {
    document.body.classList.add('demo-sandbox-active');
    const top = document.querySelector('.topbar');
    let control = document.getElementById('demoSandboxRoleControl');
    if (top && !control) {
      control = document.createElement('div');
      control.id = 'demoSandboxRoleControl';
      control.className = 'demo-sandbox-role-control';
      top.insertBefore(control, document.getElementById('wingmanTop'));
    }
    if (control) control.innerHTML = `<label for="demoSandboxRole">Demo role</label><select id="demoSandboxRole" aria-label="Demo role lens" onchange="setDemoRole(this.value)">${roleOptions(state.selectedRole)}</select>`;
    let rail = document.getElementById('demoSandboxStatusRail');
    if (!rail) {
      rail = document.createElement('div');
      rail.id = 'demoSandboxStatusRail';
      rail.className = 'demo-sandbox-status-rail';
      rail.setAttribute('role','status');
      document.body.appendChild(rail);
    }
    rail.innerHTML = `<strong>DEMO SANDBOX</strong><span>${escapeHtml(roleLabel(state.selectedRole))}</span><span>Generation ${Number(state.generation)}</span><span class="demo-sandbox-rail-spacer"></span><code>illustrative_demo_not_for_reliance</code>`;
  }

  function renderWorkspace() {
    const main = document.getElementById('main');
    if (!main) return;
    main.innerHTML = demoSandboxWorkspace();
    const breadcrumb = document.getElementById('breadcrumb');
    if (breadcrumb) breadcrumb.textContent = 'Architex OS › Demo Sandbox';
    document.title = 'Demo Sandbox — Architex';
    renderResetInspector();
  }

  function renderResetInspector() {
    const title = document.getElementById('inspectTitle');
    const sub = document.getElementById('inspectSub');
    const body = document.getElementById('inspectBody');
    if (title) title.textContent = 'Sandbox controls';
    if (sub) sub.textContent = `Generation ${Number(state?.generation||1)} · owner only`;
    if (body) body.innerHTML = `<form id="demoSandboxReset" class="demo-sandbox-reset"><div class="inspect-note"><b>Destructive demo reset</b><br>The current sandbox is superseded and a newly seeded organisation is created.</div><label>Reason<input name="reason" required minlength="10" maxlength="500" value="Restarting the guided demonstration"></label><button class="btn ghost" type="submit">RESET DEMO SANDBOX</button><span role="status"></span></form>`;
    document.getElementById('demoSandboxReset')?.addEventListener('submit', resetDemoSandbox);
  }

  async function refreshData() {
    [state,projects,learning] = await Promise.all([api.get('/demo/sandbox'),api.get('/demo/sandbox/projects'),api.get('/demo/sandbox/learning')]);
  }

  async function hydrateDemoSandbox() {
    if (hydrating || unavailable) return;
    if (!configureDemoApi()) {
      if (document.documentElement.dataset.shellState !== 'signed-out') setTimeout(hydrateDemoSandbox, 60);
      return;
    }
    hydrating = true;
    try {
      state = await api.post('/demo/sandbox/ensure', {});
      await refreshData();
      enabled = true;
      installControls();
      if (directRoute() || document.body.classList.contains('god-mode')) renderWorkspace();
    } catch (error) {
      if (error.status === 404) { unavailable = true; return; }
      if (directRoute()) {
        const main = document.getElementById('main');
        if (main) main.innerHTML = `<div class="notice">${escapeHtml(error.message)}</div>`;
      }
    } finally { hydrating = false; }
  }

  window.setDemoRole = async role => {
    if (!enabled) return;
    try {
      state = await api.put('/demo/sandbox/role', {role});
      await refreshData();
      installControls();
      if (directRoute() || document.body.classList.contains('god-mode')) renderWorkspace();
    } catch (error) { window.showToast?.(error.message); }
  };

  window.openDemoProject = async projectId => {
    try {
      const project = await api.get(`/demo/sandbox/projects/${encodeURIComponent(projectId)}`);
      const body = document.getElementById('inspectBody');
      if (body) body.innerHTML = `<div class="demo-sandbox-project-detail"><div class="inspect-note"><b>${escapeHtml(project.projectName)}</b><br>${escapeHtml(project.projectCode)}</div><dl><dt>Client</dt><dd>${escapeHtml(project.clientName)}</dd><dt>Municipality</dt><dd>${escapeHtml(project.municipality)}</dd><dt>Property</dt><dd>${escapeHtml(project.propertyReference)}</dd><dt>Phase</dt><dd>${escapeHtml(project.currentPhase)}</dd><dt>Records</dt><dd>${Number(project.recordCount)}</dd></dl><button class="btn ghost" type="button" onclick="window.location.href='/demo'">Back to sandbox controls</button></div>`;
    } catch (error) { window.showToast?.(error.message); }
  };

  async function resetDemoSandbox(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const status = form.querySelector('[role=status]');
    if (!confirm('Supersede this demo sandbox and create a clean generation?')) return;
    status.textContent = 'Resetting…';
    try {
      const replacement = await api.post('/demo/sandbox/reset', {confirmation:'RESET DEMO SANDBOX',reason:new FormData(form).get('reason')});
      api.setOrganisation(replacement.organisationId);
      window.location.assign('/demo');
    } catch (error) { status.textContent = error.message; }
  }
  window.resetDemoSandbox = resetDemoSandbox;
  window.demoSandboxWorkspace = demoSandboxWorkspace;
  window.hydrateDemoSandbox = hydrateDemoSandbox;

  const renderSafe = window.renderAll;
  window.renderAll = function () {
    renderSafe();
    if (!enabled && !unavailable && window.__ARCHITEX_SHELL_STATE__?.session) setTimeout(hydrateDemoSandbox,0);
    if (enabled) {
      installControls();
      if (directRoute() || document.body.classList.contains('god-mode')) setTimeout(renderWorkspace,0);
    }
  };
  window.addEventListener('DOMContentLoaded', hydrateDemoSandbox, {once:true});
})();
