(function (root) {
  'use strict';
  const unwrap = response => response?.data ?? response;
  // Governance module controller — project_passport, professional_directory,
  // team_workspace, admin_review, market_insights.
  // Renders real workspaces wired to the verified Governance adapter.
  const TOOLS = {
    project_passport: { title: 'Project Passport', kind: 'passport' },
    professional_directory: { title: 'Professional Directory', kind: 'directory' },
    team_workspace: { title: 'Team Workspace', kind: 'team' },
    admin_review: { title: 'Admin Review', kind: 'admin' },
    market_insights: { title: 'Market Insights', kind: 'analytics' },
  };
  function install({ context }) {
    const ownsRoute = toolId => { const view = typeof state === 'object' ? state : root.state; return !view || view.tool === undefined || view.tool === toolId; };
    const routeFor = def => Object.keys(TOOLS).find(id => TOOLS[id] === def);
    const adapter = root.V1CloneGovernanceAdapter.create(context);
    const esc = v => String(v == null ? '' : v).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    let generation = 0, disposed = false, active = null;
    function notice(m) { if (disposed || !ownsRoute(routeFor(active))) return; root.showToast?.(m); const el = root.document.querySelector('#main [data-gov-status]'); if (el) el.textContent = m; }
    function mainEl() { return root.document.querySelector('#main'); }

    async function load(def) {
      if (def.kind === 'passport') {
        const s = adapter.scope(true);
        const p = unwrap(await context.read('/projects/' + encodeURIComponent(s.selectedProjectId)));
        return p || {};
      }
      if (def.kind === 'team') return { team: await adapter.listTeam() };
      if (def.kind === 'directory') {
        const s = adapter.scope(false);
        // A caller with no professional profile yet gets 404 from this endpoint. Treating that as a
        // fatal error left the read-only scaffold on screen; it is simply an empty profile.
        let profile = null;
        try { profile = unwrap(await context.read('/professional-profile')) || null; }
        catch (error) { if (error && error.status !== 404) throw error; }
        return { directory: unwrap(await context.read('/directory/search')) || [], profile };
      }
      if (def.kind === 'admin') {
        const q = await adapter.reviewQueue();
        return { queue: Array.isArray(q) ? q : (q?.items || []) };
      }
      if (def.kind === 'analytics') { const w = await adapter.analyticsWorkspace(); return { analytics: w }; }
      return {};
    }

    function render(def, data) { if (disposed || !ownsRoute(routeFor(def))) return;
      const main = mainEl(); if (!main) return;
      const s = context.getState();
      const project = s.projects?.find(p => p.id === s.selectedProjectId)?.name || 'Selected project';
      let bodyHtml = '';
      if (def.kind === 'passport') {
        const f = k => esc(data[k] ?? '—');
        bodyHtml = `<section class="panel"><h2>Project Passport</h2>
          <table class="table"><tbody>
          <tr><th>Name</th><td>${f('name')}</td></tr>
          <tr><th>Code</th><td>${f('projectCode')}</td></tr>
          <tr><th>Phase</th><td>${f('phase')}</td></tr>
          <tr><th>Client</th><td>${f('clientName')}</td></tr>
          <tr><th>Municipality</th><td>${f('municipality')}</td></tr>
          <tr><th>Property</th><td>${f('propertyReference')} · ${f('propertyUse')}</td></tr>
          <tr><th>Lead role</th><td>${f('leadProfessionalRole')}</td></tr>
          <tr><th>Effective role</th><td>${f('effectiveRole')}</td></tr>
          </tbody></table></section>`;
      } else if (def.kind === 'team') {
        const rows = (data.team || []).map(m => `<tr><td>${esc(m.roleLabel || m.discipline || '—')}</td><td>${esc(m.candidateId || m.userId || '—')}</td><td><span class="pill">${esc(m.status || 'active')}</span></td></tr>`).join('') || '<tr><td colspan="3">No team members.</td></tr>';
        bodyHtml = `<section class="panel"><h2>Project team</h2><table class="table"><thead><tr><th>Role</th><th>Member</th><th>Status</th></tr></thead><tbody>${rows}</tbody></table></section>`;
      } else if (def.kind === 'directory') {
        const rows = (data.directory || []).map(d => `<tr><td>${esc(d.displayName || d.name || '—')}</td><td>${esc(d.profession || '—')}</td><td>${esc((d.serviceAreas || []).join(', ') || '—')}</td><td>${esc(d.verificationGrade || d.grade || 'unverified')}</td></tr>`).join('') || '<tr><td colspan="4">No directory results.</td></tr>';
        bodyHtml = `<section class="panel"><h2>Verified professionals</h2><table class="table"><thead><tr><th>Name</th><th>Profession</th><th>Service areas</th><th>Grade</th></tr></thead><tbody>${rows}</tbody></table></section>`;
      } else if (def.kind === 'admin') {
        const rows = (data.queue || []).map(v => `<tr><td>${esc(v.kind || '—')}</td><td>${esc(v.userId || '—')}</td><td>${esc(v.grade || '—')}</td><td><span class="pill">${esc(v.status || 'pending')}</span></td></tr>`).join('') || '<tr><td colspan="4">Verification review queue is empty.</td></tr>';
        bodyHtml = `<section class="panel"><h2>Verification review queue</h2><table class="table"><thead><tr><th>Kind</th><th>Applicant</th><th>Grade</th><th>Status</th></tr></thead><tbody>${rows}</tbody></table></section>`;
      } else if (def.kind === 'analytics') {
        const w = data.analytics || {};
        const metrics = w.latestMetrics || w.metrics || [];
        const rows = (Array.isArray(metrics) ? metrics : []).map(m => `<tr><td>${esc(m.formulaKey || m.title || '—')}</td><td>${esc(m.value ?? '—')}</td><td>${esc(m.unit || '')}</td></tr>`).join('') || '<tr><td colspan="3">No KPI metrics computed yet — refresh KPIs.</td></tr>';
        bodyHtml = `<section class="panel"><h2>Project KPIs</h2><table class="table"><thead><tr><th>Metric</th><th>Value</th><th>Unit</th></tr></thead><tbody>${rows}</tbody></table></section>
          <p class="pill" data-gov-note>Project analytics only — not external market intelligence.</p>`;
      }
      main.innerHTML = `<div class="gov-workspace" data-gov-tool="${esc(def.title)}">
        <div class="rfi-hero"><div><h1>${esc(def.title)}</h1><p>Project: <strong>${esc(project)}</strong></p></div></div>
        <p class="pill" data-gov-status role="status">Internal records only.</p>
        ${bodyHtml}</div>`;
    }

    function renderAvailability(def, message, retry) {
      if (disposed || !ownsRoute(routeFor(def))) return;
      const main = mainEl(); if (!main) return;
      main.innerHTML = `<div class="gov-workspace" data-gov-tool="${esc(def.title)}"><h1>${esc(def.title)}</h1><section class="panel"><p data-gov-status role="status">${esc(message)}</p>${retry ? '<button class="btn ghost" type="button" data-gov-retry>Retry loading</button>' : ''}</section></div>`;
      main.querySelector?.('[data-gov-retry]')?.addEventListener('click', () => { void refresh(def); });
    }
    async function refresh(def) { if (disposed || !ownsRoute(routeFor(def))) return false;
      const token = ++generation;
      renderAvailability(def, 'Loading '+def.title+'…', false);
      try { const data = await load(def); if (disposed || !ownsRoute(routeFor(def)) || token !== generation) return false; render(def, data); return true; }
      catch (e) { if (disposed || !ownsRoute(routeFor(def)) || token !== generation) return false; renderAvailability(def, [403,404].includes(e.status) ? 'These records are not available with your current access. No records loaded.' : 'This workspace could not be loaded. Retry when the connection is available. No records loaded.', true); return false; }
    }
    async function apply(toolId) {
      const def = TOOLS[toolId]; if (!def) return { applied: false, reason: 'not-governance' };
      active = def; const ok = await refresh(def); return { applied: ok, tool: toolId };
    }
    const unsubscribe = typeof context.subscribe === 'function' ? context.subscribe(() => { if (active && !disposed) void refresh(active); }) : null;
    function dispose() { disposed = true; ++generation; active = null; if (unsubscribe) unsubscribe(); }
    return { apply, dispose, tools: TOOLS };
  }
  root.V1CloneGovernance = { install };
})(window);
