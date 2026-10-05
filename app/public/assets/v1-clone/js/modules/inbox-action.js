(function (root) {
  'use strict';
  // Inbox & action — the decision inbox.
  //
  // The shell shipped this as a read-only projection with no controller at all, even though the
  // backend has had GET /api/v1/inbox and POST /api/v1/inbox/{id}/transition all along. This is the
  // workspace: list what needs a decision, open one, and decide it.
  //
  // Deciding is deliberately not a one-click: 'resolved' and 'dismissed' are decisions the server
  // will reject without an explicit human confirmation and a reason, so the screen asks for both up
  // front rather than letting the user discover it from an error.
  const TOOL = 'inbox_action';
  const DECIDING = ['resolved', 'dismissed'];

  function install({ context }) {
    const ownsRoute = toolId => { const view = typeof state === 'object' ? state : root.state; return !view || view.tool === undefined || view.tool === toolId; };
    const adapter = root.V1CloneInboxAdapter.create(context);
    const esc = value => String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    let generation = 0, disposed = false, active = false;
    let items = [], selected = null, message = 'Ready.';
    let blank = false;

    const notice = text => { if (disposed || !ownsRoute(TOOL)) return;
      message = text;
      root.showToast?.(text);
      const node = root.document.querySelector('#main [data-inbox-status]');
      if (node) node.textContent = text;
    };

    async function refresh(token = generation) {
      const loaded = await adapter.list();
      if (disposed || !ownsRoute(TOOL) || token !== generation) return;
      items = loaded;
      if (selected) selected = items.find(item => item.id === selected.id) || null;
      if (!selected && !blank && items.length) selected = items[0];
    }

    function statusPill(status) {
      const known = ['unread', 'read', 'resolved', 'dismissed'];
      const value = known.includes(String(status)) ? status : 'pending';
      return `<span class="pill inbox-status is-${esc(value)}">${esc(status || 'pending')}</span>`;
    }

    function render() { if (disposed || !ownsRoute(TOOL)) return;
      const main = root.document.querySelector('#main');
      if (!main) return;
      const state = context.getState();
      const project = state.projects?.find(p => p.id === state.selectedProjectId)?.name || 'Selected project';
      const open = items.filter(item => !['resolved', 'dismissed'].includes(String(item.status)));
      const rows = items.length
        ? items.map(item => `
            <tr${selected && selected.id === item.id ? ' class="is-selected"' : ''}>
              <td><b>${esc(item.title || 'Untitled')}</b><br><small>${esc(item.eventType || item.event_type || '—')}</small></td>
              <td>${statusPill(item.status)}</td>
              <td>${esc(item.priority || '—')}</td>
              <td>${esc(item.assignedRole || item.assigned_role || '—')}</td>
              <td class="inbox-rowactions"><button class="btn" data-inbox-open="${esc(item.id)}">Open</button></td>
            </tr>`).join('')
        : '<tr><td colspan="5">Nothing in the inbox for this organisation.</td></tr>';

      main.innerHTML = `
        <div class="inbox-workspace" data-inbox-tool="${TOOL}">
          <header class="inbox-hero">
            <div><h1>Inbox &amp; Action</h1>
              <p>Project: <strong>${esc(project)}</strong> · ${open.length} awaiting a decision of ${items.length} item(s)</p></div>
            <button class="btn" type="button" data-inbox-reload>Refresh</button>
          </header>
          <p class="pill" data-inbox-boundary>Internal decision queue — acting here records your decision and its reason; it does not notify anyone outside this organisation.</p>
          <section class="panel">
            <h2>Decision queue</h2>
            <table class="table">
              <thead><tr><th>Item</th><th>Status</th><th>Priority</th><th>Assigned role</th><th>Action</th></tr></thead>
              <tbody>${rows}</tbody>
            </table>
          </section>
          ${detailPanel()}
        </div>`;
      bind(main);
    }

    function detailPanel() {
      if (!selected) return '<p class="inbox-hint">Select an item to act on it.</p>';
      const deciding = DECIDING;
      const detail = selected.detail || selected.details || '';
      const transitions = ['read', 'resolved', 'dismissed'].filter(s => s !== selected.status);
      return `<section class="panel inbox-detail" data-inbox-detail>
        <h2>${esc(selected.title || 'Item')}</h2>
        <p class="inbox-detail-meta">${statusPill(selected.status)} · ${esc(selected.eventType || selected.event_type || '—')} · ${esc(selected.priority || '')}</p>
        <p class="inbox-detail-body">${esc(detail) || '<em>No further detail recorded.</em>'}</p>
        <div class="inbox-grid">
          <label class="field"><span>Decision</span>
            <select data-inbox-target>${transitions.map(s => `<option value="${esc(s)}">${esc(s)}</option>`).join('')}</select></label>
          <label class="field"><span>Reason ${'' + (deciding.length ? '' : '')}<small>(required to resolve or dismiss)</small></span>
            <input data-inbox-reason placeholder="Why this decision" maxlength="1000"></label>
          <label class="field checkbox"><input type="checkbox" data-inbox-confirm> <span>I confirm this decision as a human</span></label>
        </div>
        <p class="inbox-hint">Resolving or dismissing records your name against the decision and requires the confirmation above and a reason — the server refuses it otherwise.</p>
        <div class="inbox-actions">
          <button class="btn" type="button" data-inbox-apply>Apply decision</button>
          <span class="pill" data-inbox-status role="status">${esc(message)}</span>
        </div>
      </section>`;
    }

    function bind(main) {
      main.querySelector('[data-inbox-reload]')?.addEventListener('click', async event => {
        const button = event.currentTarget;
        button.disabled = true;
        try { await refresh(); notice('Inbox reloaded from the server.'); render(); }
        catch (error) { notice(error.message + ' Nothing was loaded.'); button.disabled = false; }
      });

      main.querySelectorAll('[data-inbox-open]').forEach(button => button.addEventListener('click', () => {
        selected = items.find(item => item.id === button.getAttribute('data-inbox-open')) || null;
        blank = selected === null;
        render();
      }));

      main.querySelector('[data-inbox-apply]')?.addEventListener('click', async event => {
        const button = event.currentTarget;
        if (!selected) return;
        const status = String(main.querySelector('[data-inbox-target]')?.value || '').trim();
        const reason = String(main.querySelector('[data-inbox-reason]')?.value || '').trim();
        const confirmed = main.querySelector('[data-inbox-confirm]')?.checked === true;
        if (DECIDING.includes(status) && !reason) { notice('A reason is required to resolve or dismiss — the server will refuse it otherwise.'); return; }
        if (DECIDING.includes(status) && !confirmed) { notice('Confirm the decision before applying it.'); return; }
        button.disabled = true;
        try {
          await adapter.transition(selected.id, status, { reason: reason, humanConfirmed: confirmed });
          notice('Decision recorded as "' + status + '" and verified in the inbox.');
          await refresh();
          render();
        } catch (error) {
          notice(error.message + ' The decision was not recorded.');
          button.disabled = false;
        }
      });
    }

    async function apply(toolId) {
      if (disposed || !ownsRoute(toolId)) return { applied: false, reason: 'stale' };
      if (toolId !== TOOL) return { applied: false, reason: 'not-inbox' };
      active = true;
      const token = ++generation;
      try {
        await refresh();
        if (disposed || !ownsRoute(TOOL) || token !== generation) return { applied: false, reason: 'stale' };
        render();
        return { applied: true, tool: TOOL, items: items.length };
      } catch (error) {
        if (disposed || !ownsRoute(TOOL) || token !== generation) return { applied: false, reason: 'stale' };
        notice(error.message + ' The inbox could not be loaded.');
        return { applied: false, reason: 'error', message: error.message };
      }
    }

    const unsubscribe = typeof context.subscribe === 'function' ? context.subscribe(() => { if (active && !disposed) void apply(TOOL); }) : null;
    function dispose() { disposed = true; ++generation; active = false; if (typeof unsubscribe === 'function') unsubscribe(); }

    return { apply, dispose, supports: id => id === TOOL };
  }
  root.V1CloneInbox = { install };
})(window);
