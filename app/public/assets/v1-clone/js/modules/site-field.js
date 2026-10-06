(function (root) {
  'use strict';
  const unwrap = response => response?.data ?? response;
  // Site-field controller — NCR Manager, Snag Manager, Site Instructions.
  // These were read-only scaffolds in v1.html; this replaces them with real,
  // project-scoped workspaces backed by the verified SiteExecutionService.
  // Every write is human-confirmed and read-back-verified; nothing fabricated.
  const TOOLS = {
    ncr_manager: { key: 'ncrs', title: 'Non-Conformance Reports', verb: 'NCR', singular: 'NCR' },
    snag_manager: { key: 'snags', title: 'Snag Manager', verb: 'Snag', singular: 'Snag' },
    site_instructions: { key: 'siteInstructions', title: 'Site Instructions', verb: 'Instruction', singular: 'Instruction' },
  };
  const SEVERITIES = ['low','medium','high','critical'];
  function install({ context }) {
    const ownsRoute = toolId => { const view = typeof state === 'object' ? state : root.state; return !view || view.tool === undefined || view.tool === toolId; };
    const routeFor = def => Object.keys(TOOLS).find(id => TOOLS[id] === def);
    const adapter = root.V1CloneSiteToolsAdapter.create(context);
    const esc = v => String(v == null ? '' : v).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    let generation = 0, disposed = false, active = null;

    function scope() {
      const s = context.getState();
      if (!s.ready || !s.session || !s.organisationId || !/^[a-f0-9]{32}$/i.test(s.selectedProjectId || '')) throw Error('Select an authenticated project.');
      return s;
    }
    function canWrite(){const s=context.getState();return s.readOnly!==true&&['owner','admin','member'].includes(s.session?.memberships?.find(m=>m.organisationId===s.organisationId)?.role);}
    function notice(msg) { if (!ownsRoute(routeFor(active))) return; root.showToast?.(msg); const el = root.document.querySelector('#main [data-sf-status]'); if (el) { el.textContent = msg; } }

    async function load(def) {
      const s = scope();
      const site = unwrap(await context.read('/projects/' + encodeURIComponent(s.selectedProjectId) + '/site-execution-workspace'));
      const rows = Array.isArray(site?.[def.key]) ? site[def.key].filter(r => r.projectId === s.selectedProjectId || r.projectId === undefined) : [];
      return rows;
    }

    function statusChip(st) { return `<span class="pill">${esc(st)}</span>`; }
    function rowActions(def, r) {
      if (def.key === 'ncrs') {
        if (r.status === 'open') return `<button class="btn" data-sf-act="corrective" data-sf-id="${esc(r.id)}">Add corrective action</button>`;
        if (r.status === 'corrective_action_submitted') return `<button class="btn" data-sf-act="decide" data-sf-id="${esc(r.id)}">Verify / close</button>`;
        return '';
      }
      if (def.key === 'snags') {
        if (r.status === 'open') return `<button class="btn" data-sf-act="allocate" data-sf-id="${esc(r.id)}">Allocate</button>`;
        if (r.status === 'allocated') return `<button class="btn" data-sf-act="ready" data-sf-id="${esc(r.id)}">Ready for reinspection</button>`;
        if (r.status === 'ready_for_reinspection') return `<button class="btn" data-sf-act="decide" data-sf-id="${esc(r.id)}">Close / reject</button>`;
        return '';
      }
      if (def.key === 'siteInstructions') {
        if (r.status === 'draft') return `<button class="btn" data-sf-act="issue" data-sf-id="${esc(r.id)}">Issue</button>`;
        return '';
      }
      return '';
    }
    function columns(def, r) {
      if (def.key === 'ncrs') return [esc(r.title), esc(r.severity || '—'), esc(r.responsibleParty || '—'), statusChip(r.status), esc(r.description || '')];
      if (def.key === 'snags') return [esc(r.locationLabel || r.location || '—'), esc(r.description || ''), esc(r.priority || '—'), esc(r.responsibleParty || '—'), statusChip(r.status), esc(r.dueAt ? r.dueAt.split('T')[0] : '—')];
      return [esc(r.title || '—'), esc(r.instruction || ''), esc(r.costImpact || 'none'), esc(r.timeImpact || 'none'), statusChip(r.status)];
    }
    function headers(def) {
      if (def.key === 'ncrs') return ['Title','Severity','Responsible','Status','Description','Action'];
      if (def.key === 'snags') return ['Location','Description','Priority','Responsible','Status','Due','Action'];
      return ['Title','Instruction','Cost impact','Time impact','Status','Action'];
    }

    function render(def, rows) {
      if (!ownsRoute(routeFor(def))) return;
      const main = root.document.querySelector('#main');
      if (!main) return;
      const s = scope();
      const project = s.projects?.find(p => p.id === s.selectedProjectId)?.name || 'Selected project';
      const body = rows.length ? rows.map(r => `<tr>${columns(def, r).map(c => `<td>${c}</td>`).join('')}<td>${rowActions(def, r)}</td></tr>`).join('')
        : `<tr><td colspan="${headers(def).length}">No ${esc(def.singular)} records for this project.</td></tr>`;
      main.innerHTML = `
        <div class="sf-workspace" data-sf-tool="${esc(def.title)}">
          <div class="rfi-hero">
            <div><h1>${esc(def.title)}</h1><p>Project: <strong>${esc(project)}</strong> · ${rows.length} recorded</p></div>
            <button class="btn" data-sf-create>+ New ${esc(def.verb)}</button>
          </div>
          <p class="pill" data-sf-status role="status">Internal field records only — not certification, statutory approval or contractual notice.</p>
          <section class="panel"><h2>Recorded ${esc(def.singular)}s</h2>
            <table class="table"><thead><tr>${headers(def).map(h => `<th>${h}</th>`).join('')}</tr></thead><tbody>${body}</tbody></table>
          </section>
        </div>`;
      if(!canWrite()){
        main.querySelectorAll('[data-sf-create],[data-sf-act]').forEach(button=>button.disabled=true);
        const status=main.querySelector('[data-sf-status]');if(status)status.textContent='Read-only membership: you can review these project records, but cannot create or change them. Internal records only.';
      }
      bind(def, main);
    }

    function createForm(def, main) {
      if(!canWrite())return;
      const s = scope();
      if (def.key === 'ncrs') {
        const title = root.prompt?.('NCR title'); if (!title?.trim()) return;
        const desc = root.prompt?.('Describe the non-conformance'); if (!desc?.trim()) return;
        const sev = root.prompt?.('Severity (low, medium, high, critical)', 'medium'); if (!SEVERITIES.includes(String(sev).trim())) { notice('Invalid severity. No NCR created.'); return; }
        const party = root.prompt?.('Responsible party'); if (!party?.trim()) return;
        if (!root.confirm?.('Raise this NCR on the selected project?')) return;
        const btn = main.querySelector('[data-sf-create]'); btn.disabled = true;
        adapter.createNcr({ title: title.trim(), description: desc.trim(), severity: sev.trim(), responsibleParty: party.trim(), evidenceIds: [], blocksPayment: false, humanConfirmed: true })
          .then(() => refresh(def)).then(() => notice('NCR raised and verified in project records.'))
          .catch(e => notice(e.message + ' No success claimed.')).finally(() => { btn.disabled = !canWrite(); });
      } else if (def.key === 'snags') {
        const loc = root.prompt?.('Location'); if (!loc?.trim()) return;
        const desc = root.prompt?.('Describe the snag'); if (!desc?.trim()) return;
        const pri = root.prompt?.('Priority (low, medium, high, critical)', 'medium'); if (!SEVERITIES.includes(String(pri).trim())) { notice('Invalid priority. No snag created.'); return; }
        const party = root.prompt?.('Responsible party'); if (!party?.trim()) return;
        if (!root.confirm?.('Record this snag on the selected project?')) return;
        const btn = main.querySelector('[data-sf-create]'); btn.disabled = true;
        adapter.createSnag({ location: loc.trim(), description: desc.trim(), priority: pri.trim(), responsibleParty: party.trim(), dueAt: null, evidenceIds: [], blocksPayment: false, humanConfirmed: true })
          .then(() => refresh(def)).then(() => notice('Snag recorded and verified in project records.'))
          .catch(e => notice(e.message + ' No success claimed.')).finally(() => { btn.disabled = !canWrite(); });
      } else {
        const title = root.prompt?.('Instruction title'); if (!title?.trim()) return;
        const body = root.prompt?.('Instruction text'); if (!body?.trim()) return;
        const cost = root.prompt?.('Cost impact (none, possible, confirmed)', 'none'); if (!['none','possible','confirmed'].includes(String(cost).trim())) { notice('Invalid cost impact.'); return; }
        const time = root.prompt?.('Time impact (none, possible, confirmed)', 'none'); if (!['none','possible','confirmed'].includes(String(time).trim())) { notice('Invalid time impact.'); return; }
        if (!root.confirm?.('Draft this site instruction on the selected project?')) return;
        const btn = main.querySelector('[data-sf-create]'); btn.disabled = true;
        adapter.createInstruction({ title: title.trim(), instruction: body.trim(), costImpact: cost.trim(), timeImpact: time.trim(), linkedRfiId: null, humanConfirmed: true })
          .then(() => refresh(def)).then(() => notice('Site instruction drafted and verified in project records.'))
          .catch(e => notice(e.message + ' No success claimed.')).finally(() => { btn.disabled = !canWrite(); });
      }
    }

    function bind(def, main) {
      const create = main.querySelector('[data-sf-create]');
      if (create) create.addEventListener('click', () => createForm(def, main));
      main.querySelectorAll('[data-sf-act]').forEach(btn => btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-sf-id'); const act = btn.getAttribute('data-sf-act');
        runAction(def, main, id, act);
      }));
    }

    async function runAction(def, main, id, act) {
      if(!canWrite())return;
      const s = scope();
      const rec = (await load(def)).find(r => r.id === id);
      if(!canWrite())return;
      if (def.key === 'ncrs') {
        if (act === 'corrective') {
          const ca = root.prompt?.('Corrective action taken'); if (!ca?.trim()) return;
          if (!root.confirm?.('Submit this corrective action?')) return;
          adapter.submitNcrCorrectiveAction(id, { correctiveAction: ca.trim(), evidenceIds: [], humanConfirmed: true })
            .then(() => refresh(def)).then(() => notice('Corrective action verified.')).catch(e => notice(e.message + ' No success claimed.'));
        } else if (act === 'decide') {
          const decision = root.prompt?.('Decision (verified_closed or rejected)', 'verified_closed');
          const reason = root.prompt?.('Reason'); if (!reason?.trim() || !['verified_closed','rejected'].includes(String(decision).trim())) return;
          if (!root.confirm?.('Record this NCR decision?')) return;
          adapter.decideNcr(id, { decision: decision.trim(), reason: reason.trim(), humanConfirmed: true })
            .then(() => refresh(def)).then(() => notice('NCR decision verified.')).catch(e => notice(e.message + ' No success claimed.'));
        }
      } else if (def.key === 'snags') {
        if (act === 'allocate') {
          if (!root.confirm?.('Allocate this snag to the responsible party?')) return;
          adapter.allocateSnag(id, { humanConfirmed: true }).then(() => refresh(def)).then(() => notice('Snag allocated and verified.')).catch(e => notice(e.message + ' No success claimed.'));
        } else if (act === 'ready') {
          if (!root.confirm?.('Mark this snag ready for reinspection?')) return;
          adapter.markSnagReady(id, { evidenceIds: [], humanConfirmed: true }).then(() => refresh(def)).then(() => notice('Snag marked ready for reinspection.')).catch(e => notice(e.message + ' No success claimed.'));
        } else if (act === 'decide') {
          const decision = root.prompt?.('Decision (closed or rejected)', 'closed');
          const reason = root.prompt?.('Reason'); if (!reason?.trim() || !['closed','rejected'].includes(String(decision).trim())) return;
          if (!root.confirm?.('Record this snag decision?')) return;
          adapter.decideSnag(id, { decision: decision.trim(), reason: reason.trim(), humanConfirmed: true }).then(() => refresh(def)).then(() => notice('Snag decision verified.')).catch(e => notice(e.message + ' No success claimed.'));
        }
      } else if (def.key === 'siteInstructions' && act === 'issue') {
        const reason = root.prompt?.('Issue reason'); if (!reason?.trim()) return;
        if (!root.confirm?.('Issue this site instruction?')) return;
        adapter.issueInstruction(id, { reason: reason.trim(), humanConfirmed: true }).then(() => refresh(def)).then(() => notice('Site instruction issued and verified.')).catch(e => notice(e.message + ' No success claimed.'));
      }
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
      if (!def) return { applied: false, reason: 'not-site-field' };
      active = def;
      const ok = await refresh(def);
      return { applied: ok, tool: toolId };
    }
    const unsubscribe = typeof context.subscribe === 'function' ? context.subscribe(() => { if (active && !disposed) void refresh(active); }) : null;
    function dispose() { disposed = true; ++generation; active = null; if (unsubscribe) unsubscribe(); }

    return { apply, dispose, tools: TOOLS };
  }
  root.V1CloneSiteField = { install };
})(window);
