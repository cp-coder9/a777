(function (root) {
  'use strict';
  // Governed-register controller — Dispute Resolution and NHBRC Enrolment.
  // Both were read-only scaffolds in v1.html; this installs real, project-scoped
  // workspaces backed by the verified ControlledRegisterService. Every create and
  // status transition is human-confirmed and read-back-verified. HONEST BOUNDARY:
  // these are internal working records, not a statutory submission, an NHBRC
  // enrolment certificate, or an inferred legal resolution.
  const TOOLS = {
    dispute_resolution: {
      registerKey: 'dispute_resolution',
      title: 'Dispute Resolution',
      singular: 'dispute entry',
      verb: 'Dispute entry',
      roles: ['architect','principal_agent','contract_administrator','quantity_surveyor','client'],
      boundary: 'Internal dispute notice and evidence register only — recording an entry is not a statutory submission and does not resolve, determine or conclude any dispute.',
    },
    nhbrc_enrolment: {
      registerKey: 'nhbrc_enrolment',
      title: 'NHBRC Enrolment',
      singular: 'enrolment entry',
      verb: 'Enrolment entry',
      roles: ['architect','principal_agent','home_builder','client'],
      boundary: 'Internal enrolment evidence register only — recording an entry is not an NHBRC submission and does not claim a certificate of enrolment.',
    },
  };
  function install({ context }) {
    // Async work may finish after the shared shell has navigated to another tool.
    const ownsRoute = toolId => { const view = typeof state === 'object' ? state : root.state; return !view || view.tool === undefined || view.tool === toolId; };
    const routeFor = def => Object.keys(TOOLS).find(id => TOOLS[id] === def);
    const adapter = root.V1CloneRegistersAdapter.create(context);
    const esc = v => String(v == null ? '' : v).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const humanise = v => String(v == null ? '' : v).replace(/_/g, ' ');
    let generation = 0, disposed = false, active = null, definition = null;

    function scope() {
      const s = context.getState();
      if (!s.ready || !s.session || !s.organisationId || !/^[a-f0-9]{32}$/i.test(s.selectedProjectId || '')) throw Error('Select an authenticated project.');
      return s;
    }
    function notice(msg) {if (!ownsRoute(routeFor(active))) return;
      root.showToast?.(msg);
      const el = root.document.querySelector('#main [data-rg-status]');
      if (el) el.textContent = msg;
    }
    async function load(def) {
      const ws = await adapter.workspace(def.registerKey);
      return ws;
    }
    function nextStates(status) { return (definition?.transitions?.[status] || []).slice(); }
    function statusChip(st) { return `<span class="pill" data-rg-status-value="${esc(st)}">${esc(humanise(st))}</span>`; }
    function rowActions(r) {
      const next = nextStates(r.status);
      if (!next.length) return '';
      return next.map(state => `<button class="btn" data-rg-act="${esc(state)}" data-rg-id="${esc(r.id)}">→ ${esc(humanise(state))}</button>`).join(' ');
    }
    function columns(def, r) {
      return [esc(r.title || '—'), esc(r.reference || '—'), esc(humanise(r.ownerRole || '—')), esc(r.dueOn ? String(r.dueOn).split('T')[0] : '—'), statusChip(r.status), 'v' + esc(r.version ?? '')];
    }
    function headers() { return ['Title','Reference','Owner role','Due','Status','Version','Action']; }

    function render(def, rows) {
      if (!ownsRoute(routeFor(def))) return false;
      const main = root.document.querySelector('#main');
      if (!main) return;
      const s = scope();
      const project = s.projects?.find(p => p.id === s.selectedProjectId)?.name || 'Selected project';
      const body = rows.length
        ? rows.map(r => `<tr>${columns(def, r).map(c => `<td>${c}</td>`).join('')}<td>${rowActions(r)}</td></tr>`).join('')
        : `<tr><td colspan="${headers().length}">No ${esc(def.singular)} records for this project.</td></tr>`;
      main.innerHTML = `
        <div class="rg-workspace" data-rg-tool="${esc(def.registerKey)}">
          <div class="rfi-hero">
            <div><h1>${esc(def.title)}</h1><p>Project: <strong>${esc(project)}</strong> · ${rows.length} recorded</p></div>
            <button class="btn" data-rg-create>+ New ${esc(def.verb)}</button>
          </div>
          <p class="pill" data-rg-boundary role="status">${esc(def.boundary)}</p>
          <p class="pill" data-rg-status role="status">Human-confirmed internal register — no external submission performed, no statutory approval claimed.</p>
          <section class="panel"><h2>Recorded ${esc(def.singular)}s</h2>
            <table class="table"><thead><tr>${headers().map(h => `<th>${esc(h)}</th>`).join('')}</tr></thead><tbody>${body}</tbody></table>
          </section>
        </div>`;
      bind(def, main, rows);
    }

    async function createForm(def, main) {
      const s = scope();
      const title = root.prompt?.(`${def.title} — entry title`); if (!title?.trim()) return;
      const reference = root.prompt?.('Register reference'); if (!reference?.trim()) return;
      const role = root.prompt?.(`Owner role (${def.roles.join(', ')})`, def.roles[0]);
      if (!def.roles.includes(String(role || '').trim())) { notice('Invalid owner role. No register entry created.'); return; }
      const dueOn = root.prompt?.('Due date (YYYY-MM-DD, blank for none)', ''); const due = String(dueOn || '').trim();
      if (due && !/^\d{4}-\d{2}-\d{2}$/.test(due)) { notice('Invalid due date. No register entry created.'); return; }
      const summary = root.prompt?.('Recorded summary / evidence note'); if (!summary?.trim()) return;
      if (!root.confirm?.(`Record this ${def.singular} on the selected project?`)) return;
      const btn = main.querySelector('[data-rg-create]'); if (btn) btn.disabled = true;
      adapter.createEntry(def.registerKey, { title: title.trim(), reference: reference.trim(), ownerRole: role.trim(), dueOn: due || null, details: { summary: summary.trim() }, evidenceFileIds: [], humanConfirmed: true })
        .then(() => refresh(def)).then(() => notice(`${def.title} entry recorded and verified in project records.`))
        .catch(e => notice(e.message + ' No success claimed.')).finally(() => { if (btn) btn.disabled = false; });
    }

    function bind(def, main, rows) {
      const create = main.querySelector('[data-rg-create]');
      if (create) create.addEventListener('click', () => createForm(def, main));
      main.querySelectorAll('[data-rg-act]').forEach(btn => btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-rg-id'); const target = btn.getAttribute('data-rg-act');
        const rec = rows.find(r => r.id === id); if (rec) transitionForm(def, rec, target);
      }));
    }

    async function transitionForm(def, entry, target) {
      const allowed = nextStates(entry.status);
      if (!allowed.includes(target)) { notice('That transition is not allowed from the current status. No change recorded.'); return; }
      const reason = root.prompt?.(`Reason for moving to ${humanise(target)}`); if (!reason?.trim()) return;
      if (!root.confirm?.(`Record this ${def.title} transition?`)) return;
      adapter.transitionEntry(entry.id, { expectedVersion: entry.version, status: target, reason: reason.trim(), humanConfirmed: true })
        .then(() => refresh(def)).then(() => notice('Register entry transition verified in project records.'))
        .catch(e => notice(e.message + ' No success claimed.'));
    }

    async function refresh(def) {
      if (!ownsRoute(routeFor(def))) return false;
      const token = ++generation;
      try { const ws = await load(def); if (disposed || !ownsRoute(routeFor(def)) || token !== generation) return false; definition = ws.definition; render(def, ws.entries); return true; }
      catch (e) { if (disposed || !ownsRoute(routeFor(def)) || token !== generation) return false; notice(e.message + ' No records loaded.'); return false; }
    }
    function toolFor(toolId) { return TOOLS[toolId]; }
    async function apply(toolId) {
      const def = toolFor(toolId);
      if (!def) return { applied: false, reason: 'not-controlled-register' };
      active = def;
      const ok = await refresh(def);
      return { applied: ok, tool: toolId };
    }
    const unsubscribe = typeof context.subscribe === 'function' ? context.subscribe(() => { if (active && !disposed) void refresh(active); }) : null;
    function dispose() { disposed = true; ++generation; active = null; definition = null; if (unsubscribe) unsubscribe(); }

    return { apply, dispose, tools: TOOLS };
  }
  root.V1CloneRegisters = { install };
})(window);
