(function () {
  'use strict';
  const api = new window.ArchitexApi.ApiClient();
  const root = document.documentElement;
  root.dataset.shellState = 'loading';
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
    if (main && !main.querySelector('.release1-data-note')) main.insertAdjacentHTML('afterbegin', '<div class="notice release1-data-note"><b>Live Release 1 scope:</b> project identity, phase, readiness, account and inbox data come from MariaDB. Tool workspaces and example metrics remain non-authoritative prototypes until their release pack is implemented.</div>');
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
