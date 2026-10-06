(function (root) {
  'use strict';
  // Regulatory controller — EIA Workspace and Environmental & Heritage.
  // Backed by the shared RegulatoryCaseService through the regulatory adapter: the
  // two tools are distinct case types (environmental vs heritage) over one backend.
  // Every write is human-confirmed and read-back-verified; nothing fabricated.
  const TOOLS = {
    eia_workspace: { caseType: 'environmental', title: 'EIA Workspace', verb: 'Environmental case', singular: 'environmental case', defaultEvidence: 'screening_report', defaultRole: 'environmental_practitioner' },
    environmental_heritage: { caseType: 'heritage', title: 'Environmental & Heritage', verb: 'Heritage case', singular: 'heritage case', defaultEvidence: 'heritage_nid', defaultRole: 'heritage_practitioner' },
  };
  const EVIDENCE_TYPES = ['screening_report','site_sensitivity_verification','listed_activity_review','specialist_study','public_participation','empr','monitoring','audit','heritage_nid','heritage_impact_assessment','authority_correspondence','permit','condition'];
  const EVIDENCE_STATUSES = ['draft','pending','complete','submitted_externally','received','accepted','rejected','closed'];
  const ROUTES = ['no_ea_indicated','basic_assessment','scoping_eia','nid','heritage_impact_assessment','permit_required','other'];
  const today = () => new Date().toISOString().slice(0, 10);
  function install({ context }) {
    // Async work may finish after the shared shell has navigated to another tool.
    const ownsRoute = toolId => { const view = typeof state === 'object' ? state : root.state; return !view || view.tool === undefined || view.tool === toolId; };
    const routeFor = def => Object.keys(TOOLS).find(id => TOOLS[id] === def);
    const adapter = root.V1CloneRegulatoryAdapter.create(context);
    const esc = v => String(v == null ? '' : v).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    let generation = 0, disposed = false, active = null;

    function scope() {
      const s = context.getState();
      if (!s.ready || !s.session || !s.organisationId || !/^[a-f0-9]{32}$/i.test(s.selectedProjectId || '')) throw Error('Select an authenticated project.');
      return s;
    }
    function notice(msg) {if (!ownsRoute(routeFor(active))) return; root.showToast?.(msg); const el = root.document.querySelector('#main [data-reg-status]'); if (el) { el.textContent = msg; } }

    async function load(def) { return adapter.listCases(def.caseType); }

    function statusChip(st) { return `<span class="pill">${esc(st || 'draft')}</span>`; }
    function headers() { return ['Case','Title','Authority','Responsible role','Status','Version','Evidence','Action']; }
    function columns(r) {
      return [esc(r.caseNumber || '—'), esc(r.title || '—'), esc(r.authorityName || '—'), esc(r.responsibleRole || '—'),
        statusChip(r.status), esc(String(r.version == null ? 1 : r.version)), esc(String((r.evidence || []).length))];
    }
    function rowActions(r) {
      if (r.status !== 'draft') return '';
      return `<button class="btn" data-reg-act="evidence" data-reg-id="${esc(r.id)}">Add evidence</button>`
        + `<button class="btn" data-reg-act="determine" data-reg-id="${esc(r.id)}">Record determination</button>`;
    }

    function render(def, rows) {
      if (!ownsRoute(routeFor(def))) return false;
      const main = root.document.querySelector('#main');
      if (!main) return;
      const s = scope();
      const project = s.projects?.find(p => p.id === s.selectedProjectId)?.name || 'Selected project';
      const body = rows.length ? rows.map(r => `<tr>${columns(r).map(c => `<td>${c}</td>`).join('')}<td>${rowActions(r)}</td></tr>`).join('')
        : `<tr><td colspan="${headers().length}">No ${esc(def.singular)} records for this project.</td></tr>`;
      main.innerHTML = `
        <div class="reg-workspace" data-reg-tool="${esc(Object.keys(TOOLS).find(k => TOOLS[k] === def) || '')}" data-reg-type="${esc(def.caseType)}">
          <div class="rfi-hero">
            <div><h1>${esc(def.title)}</h1><p>Project: <strong>${esc(project)}</strong> · ${rows.length} recorded</p></div>
            <button class="btn" data-reg-create>+ New ${esc(def.verb)}</button>
          </div>
          <p class="pill" data-reg-status role="status">${esc(adapter.boundary)}</p>
          <section class="panel"><h2>Regulatory cases (${esc(def.caseType)})</h2>
            <table class="table"><thead><tr>${headers().map(h => `<th>${h}</th>`).join('')}</tr></thead><tbody>${body}</tbody></table>
          </section>
        </div>`;
      bind(def, main);
    }

    function createForm(def, main) {
      const s = scope();
      const title = root.prompt?.('Case title'); if (!title?.trim()) return;
      const authority = root.prompt?.('Responsible authority name', def.caseType === 'heritage' ? 'SAHRA' : 'Department of Environmental Affairs'); if (!authority?.trim()) return;
      const role = root.prompt?.('Responsible professional role', def.defaultRole); if (!role?.trim()) return;
      const sourceTitle = root.prompt?.('Source-basis title', def.caseType === 'heritage' ? 'National Heritage Resources Act 25 of 1999' : 'National Environmental Management Act 107 of 1998'); if (!sourceTitle?.trim()) return;
      const sourceUrl = root.prompt?.('Source-basis URL', 'https://www.gov.za/documents'); if (!sourceUrl?.trim()) return;
      if (!root.confirm?.(`Create this ${def.caseType} regulatory case on the selected project?`)) return;
      const btn = main.querySelector('[data-reg-create]'); if (btn) btn.disabled = true;
      const caseNumber = 'REG-' + def.caseType.slice(0, 3).toUpperCase() + '-' + Date.now().toString(36).toUpperCase();
      const sourceBasis = [{ title: sourceTitle.trim(), url: sourceUrl.trim(), retrievedAt: today() }];
      adapter.createCase({ caseType: def.caseType, caseNumber, title: title.trim(), authorityName: authority.trim(), responsibleRole: role.trim(), sourceBasis })
        .then(() => refresh(def)).then(() => notice(`${def.title} case created and verified in project records.`))
        .catch(e => notice(e.message + ' No success claimed.')).finally(() => { if (btn) btn.disabled = false; });
    }

    function evidenceForm(def, main, id) {
      const type = root.prompt?.('Evidence type (' + EVIDENCE_TYPES.join(', ') + ')', def.defaultEvidence);
      if (!EVIDENCE_TYPES.includes(String(type).trim())) { notice('Invalid evidence type. No evidence added.'); return; }
      const title = root.prompt?.('Evidence title'); if (!title?.trim()) return;
      const status = root.prompt?.('Evidence status (' + EVIDENCE_STATUSES.join(', ') + ')', 'complete');
      if (!EVIDENCE_STATUSES.includes(String(status).trim())) { notice('Invalid evidence status. No evidence added.'); return; }
      const reference = root.prompt?.('External reference'); if (!reference?.trim()) return;
      const sourceUrl = root.prompt?.('Source URL', 'https://www.gov.za/documents'); if (!sourceUrl?.trim()) return;
      const observedAt = root.prompt?.('Observed on (YYYY-MM-DD)', today()); if (!observedAt?.trim()) return;
      const summary = root.prompt?.('Evidence note (optional)', '');
      if (!root.confirm?.('Add this evidence to the regulatory case?')) return;
      const btn = main.querySelector(`[data-reg-act="evidence"][data-reg-id="${id}"]`); if (btn) btn.disabled = true;
      adapter.addEvidence(id, { evidenceType: type.trim(), title: title.trim(), status: status.trim(), externalReference: reference.trim(), sourceUrl: sourceUrl.trim(), observedAt: observedAt.trim(), payload: { summary: String(summary || '').trim() } })
        .then(() => refresh(def)).then(() => notice('Evidence added and verified in project records.'))
        .catch(e => notice(e.message + ' No success claimed.')).finally(() => { if (btn) btn.disabled = false; });
    }

    function determineForm(def, main, id) {
      const role = root.prompt?.('Determining professional role', def.defaultRole); if (!role?.trim()) return;
      const route = root.prompt?.('Determination route (' + ROUTES.join(', ') + ')'); if (!route?.trim()) return;
      const reason = root.prompt?.('Determination reason'); if (!reason?.trim()) return;
      const reference = root.prompt?.('External determination reference'); if (!reference?.trim()) return;
      if (!root.confirm?.('Record this regulatory determination? It is a professional determination record, not an authority decision.')) return;
      const btn = main.querySelector(`[data-reg-act="determine"][data-reg-id="${id}"]`); if (btn) btn.disabled = true;
      adapter.determine(id, { professionalRole: role.trim(), route: route.trim(), reason: reason.trim(), externalReference: reference.trim(), humanConfirmed: true })
        .then(() => refresh(def)).then(() => notice('Regulatory determination recorded and verified.'))
        .catch(e => notice(e.message + ' No success claimed.')).finally(() => { if (btn) btn.disabled = false; });
    }

    function bind(def, main) {
      const create = main.querySelector('[data-reg-create]');
      if (create) create.addEventListener('click', () => createForm(def, main));
      main.querySelectorAll('[data-reg-act]').forEach(btn => btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-reg-id'); const act = btn.getAttribute('data-reg-act');
        if (act === 'evidence') evidenceForm(def, main, id);
        else if (act === 'determine') determineForm(def, main, id);
      }));
    }

    async function refresh(def) {
      if (!ownsRoute(routeFor(def))) return false;
      const token = ++generation;
      try { const rows = await load(def); if (disposed || !ownsRoute(routeFor(def)) || token !== generation) return false; render(def, rows); return true; }
      catch (e) { if (disposed || !ownsRoute(routeFor(def)) || token !== generation) return false; notice(e.message + ' No records loaded.'); return false; }
    }

    function toolFor(toolId) { return TOOLS[toolId]; }
    async function apply(toolId) {
      const def = toolFor(toolId);
      if (!def) return { applied: false, reason: 'not-regulatory' };
      active = def;
      const ok = await refresh(def);
      return { applied: ok, tool: toolId };
    }
    const unsubscribe = typeof context.subscribe === 'function' ? context.subscribe(() => { if (active && !disposed) void refresh(active); }) : null;
    function dispose() { disposed = true; ++generation; active = null; if (unsubscribe) unsubscribe(); }

    return { apply, dispose, tools: TOOLS };
  }
  root.V1CloneRegulatory = { install };
})(window);
