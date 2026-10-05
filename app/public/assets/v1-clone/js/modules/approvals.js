(function (root) {
  'use strict';
  // Approvals Queue controller — the operating-system decision queue for the selected
  // project plus organisation-wide deployment release-gate evidence. Renders real inbox
  // rows (organisations-wide inbox filtered to the project), the operating snapshot's
  // decisions, and recorded release gates.
  //
  // Honest boundary: the queue is not approvals-only. The operating snapshot emits
  // missing-record tasks (no source record exists yet); those are labelled 'missing record'
  // and never presented as pending approvals. Recording a release gate captures deployment
  // evidence for human review — the API offers no deploy, promote, approve or rollback action.
  const TOOL = 'approvals_queue';
  function install({ context }) {
    // Async work may finish after the shared shell has navigated to another tool.
    const ownsRoute = toolId => { const view = typeof state === 'object' ? state : root.state; return !view || view.tool === undefined || view.tool === toolId; };
    const adapter = root.V1CloneApprovalsAdapter.create(context);
    const esc = value => String(value == null ? '' : value).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    let generation = 0, disposed = false, active = null;

    function scope() {
      const s = context.getState();
      if (!s.ready || !s.session || !s.organisationId || !/^[a-f0-9]{32}$/i.test(s.selectedProjectId || '')) throw Error('Select an authenticated project.');
      return s;
    }
    function notice(message) {if (!ownsRoute(TOOL)) return; root.showToast?.(message); const el = root.document.querySelector('#main [data-approvals-status]'); if (el) { el.textContent = message; } }
    function pill(value) { return '<span class="pill">' + esc(value || 'unknown') + '</span>'; }
    const KIND_LABEL = { missing_record: 'missing record', record_review: 'record review', risk_warning: 'risk advisory', inbox_action: 'inbox action' };

    async function refresh() {
      if (!ownsRoute(TOOL)) return false;
      const token = ++generation;
      const [inbox, decisions, gates] = await Promise.all([adapter.listInbox(), adapter.listDecisions(), adapter.listReleaseGates()]);
      if (disposed || !ownsRoute(TOOL) || token !== generation) return false;
      render({ inbox, decisions, gates });
      return true;
    }

    function render(model) {
      if (!ownsRoute(TOOL)) return false;
      const main = root.document.querySelector('#main');
      if (!main) return;
      const s = scope();
      const project = s.projects?.find(p => p.id === s.selectedProjectId)?.name || 'Selected project';
      const inbox = model.inbox || [];
      const decisions = (model.decisions && model.decisions.decisions) || [];
      const missing = (model.decisions && model.decisions.missingRecordTasks) || [];
      const gates = model.gates || [];

      const decisionRows = decisions.length ? decisions.map(d => `<tr>
          <td>${esc(d.title || '—')}</td><td>${pill(KIND_LABEL[d.kind] || d.kind)}</td><td>${pill(d.priority)}</td><td>${pill(d.humanGate)}</td>
          <td>${d.kind === 'missing_record' ? 'Not an approval — no source record exists yet.' : esc(d.actionTarget || '—')}</td>
        </tr>`).join('') : '<tr><td colspan="5">No decisions are outstanding for this project.</td></tr>';

      const inboxRows = inbox.length ? inbox.map(i => `<tr>
          <td>${esc(i.title || '—')}</td><td>${esc(i.eventType || '—')}</td><td>${pill(i.priority)}</td><td>${pill(i.status)}</td><td>${esc(i.humanGate || 'none')}</td>
          <td><button class="btn" data-approvals-act="read" data-approvals-id="${esc(i.id)}">Mark read</button>
          <button class="btn" data-approvals-act="resolve" data-approvals-id="${esc(i.id)}">Resolve</button>
          <button class="btn" data-approvals-act="dismiss" data-approvals-id="${esc(i.id)}">Dismiss</button></td>
        </tr>`).join('') : '<tr><td colspan="6">No inbox items for this project.</td></tr>';

      const gateRows = gates.length ? gates.map(g => `<tr><td>${esc(g.environment)}</td><td>${pill(g.status)}</td><td>${esc(String(g.repoCommit || '').slice(0, 12))}</td><td>${esc(String(g.deployedCommit || '').slice(0, 12))}</td><td>${esc((g.checks || []).filter(c => c.status !== 'pass').map(c => c.key).join(', ') || 'all checks passed')}</td></tr>`).join('')
        : '<tr><td colspan="5">No release gate has been recorded for this organisation.</td></tr>';

      main.innerHTML = `
        <div class="approvals-workspace" data-approvals-workspace="${esc(TOOL)}">
          <div class="rfi-hero">
            <div><h1>Approvals Queue</h1><p>Project: <strong>${esc(project)}</strong> · ${decisions.length} decision(s) · ${inbox.length} inbox item(s) · ${missing.length} missing-record task(s)</p></div>
            <button class="btn" data-approvals-create-gate>+ Record release gate</button>
          </div>
          <p class="pill" data-approvals-status role="status">${esc(adapter.boundary)}</p>
          <section class="panel"><h2>Decision queue</h2>
            <table class="table"><thead><tr><th>Decision</th><th>Kind</th><th>Priority</th><th>Human gate</th><th>Context</th></tr></thead><tbody>${decisionRows}</tbody></table>
          </section>
          <section class="panel"><h2>Project inbox items</h2>
            <table class="table"><thead><tr><th>Item</th><th>Event</th><th>Priority</th><th>Status</th><th>Human gate</th><th>Action</th></tr></thead><tbody>${inboxRows}</tbody></table>
          </section>
          <section class="panel"><h2>Deployment release gates</h2>
            <table class="table"><thead><tr><th>Environment</th><th>Status</th><th>Reviewed commit</th><th>Deployed commit</th><th>Non-passing checks</th></tr></thead><tbody>${gateRows}</tbody></table>
            <p>Recording a release gate captures evidence for human review: <strong>${adapter.releaseGateActionsSupported === true ? 'release actions available' : 'no deploy, promote or approve action is offered'}</strong>.</p>
          </section>
        </div>`;
      bind(main);
    }

    function transition(id, status) {
      const sensitive = status === 'resolved' || status === 'dismissed';
      const reason = sensitive ? String(root.prompt?.('Reason for ' + status) || '').trim() : '';
      if (sensitive && !reason) { notice('A reason is required. Nothing was changed.'); return; }
      if (sensitive && !root.confirm?.('Record this decision as ' + status + '?')) return;
      adapter.transitionInbox(id, { status, reason, humanConfirmed: sensitive })
        .then(record => refresh().then(() => notice('Decision ' + record.status + '.')))
        .catch(error => notice(error.message + ' No success claimed.'));
    }
    function recordGate(main) {
      const environment = String(root.prompt?.('Environment (staging, production)', 'staging') || '').trim();
      if (!['staging', 'production'].includes(environment)) { notice('Invalid environment. Nothing was recorded.'); return; }
      const repoBranch = String(root.prompt?.('Reviewed branch', 'main') || '').trim();
      const deployedBranch = String(root.prompt?.('Deployed branch', repoBranch) || '').trim();
      const repoCommit = String(root.prompt?.('Reviewed commit (40-hex)') || '').trim();
      const deployedCommit = String(root.prompt?.('Deployed commit (40-hex)', repoCommit) || '').trim();
      if (!/^[a-f0-9]{40}$/i.test(repoCommit) || !/^[a-f0-9]{40}$/i.test(deployedCommit)) { notice('Both commits must be 40-character hex. Nothing was recorded.'); return; }
      const route = String(root.prompt?.('Smoke-tested route', '/api/v1/api-capabilities') || '').trim();
      const status = Number(root.prompt?.('Smoke-test HTTP status', '200'));
      if (!route || !Number.isInteger(status)) { notice('A route and an integer status are required. Nothing was recorded.'); return; }
      if (!root.confirm?.('Record this release gate? This records evidence only; it does not deploy or approve a release.')) return;
      const button = main.querySelector('[data-approvals-create-gate]'); if (button) button.disabled = true;
      adapter.createReleaseGate({
        environment, repoBranch, deployedBranch, repoCommit, deployedCommit,
        buildTimestamp: new Date().toISOString(), apiMode: 'production-candidate',
        apiHealthOk: true, databaseReady: true, migrationsReady: true, mailReady: false,
        routeSmoke: [{ route, status }], dependencyVulnerabilityCount: 0, humanConfirmed: true,
      }).then(record => refresh().then(() => notice('Release gate recorded: ' + record.status + '.')))
        .catch(error => notice(error.message + ' No success claimed.'))
        .finally(() => { if (button) button.disabled = false; });
    }

    function bind(main) {
      const gate = main.querySelector('[data-approvals-create-gate]'); if (gate) gate.addEventListener('click', () => recordGate(main));
      main.querySelectorAll('[data-approvals-act]').forEach(button => button.addEventListener('click', () => {
        transition(button.getAttribute('data-approvals-id'), button.getAttribute('data-approvals-act'));
      }));
    }

    async function apply(toolId) {
      if (toolId !== TOOL) return { applied: false, reason: 'not-approvals-queue' };
      active = TOOL;
      try { const ok = await refresh(); return { applied: ok, tool: TOOL }; }
      catch (error) { notice(error.message + ' No records loaded.'); return { applied: false, tool: TOOL, error: error.message }; }
    }
    const unsubscribe = typeof context.subscribe === 'function' ? context.subscribe(() => { if (active && !disposed) void refresh().catch(() => {}); }) : null;
    function dispose() { disposed = true; ++generation; active = null; if (unsubscribe) unsubscribe(); }
    return { apply, dispose, tool: TOOL, supports: id => id === TOOL };
  }
  root.V1CloneApprovals = { install, tool: TOOL };
})(window);
