(function (root) {
  'use strict';
  // Project Explorer controller — a real, browsable workspace over the selected
  // project's records. The backend exposes a bounded record projection
  // (/operating-snapshot) joined with the controlled-document register
  // (/documents?projectId=). This controller lists and filters those records and
  // lets a user create a real controlled-document draft that is read-back
  // verified. It is an explorer of project records, not an asset-wide search or
  // archive; nothing is fabricated.
  const TOOLS = {
    project_explorer: { title: 'Project Explorer', verb: 'document' },
  };
  function install({ context }) {
    // Async work may finish after the shared shell has navigated to another tool.
    const ownsRoute = toolId => { const view = typeof state === 'object' ? state : root.state; return !view || view.tool === undefined || view.tool === toolId; };
    const adapter = root.V1CloneExplorerAdapter.create(context);
    const esc = v => String(v == null ? '' : v).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    let generation = 0, disposed = false, active = null, lastRows = [];

    function scope() {
      const s = context.getState();
      if (!s.ready || !s.session || !s.organisationId || !/^[a-f0-9]{32}$/i.test(s.selectedProjectId || '')) throw Error('Select an authenticated project.');
      return s;
    }
    function notice(msg) {if (!ownsRoute('project_explorer')) return; root.showToast?.(msg); }

    function rowHtml(r) {
      const meta = r.kind === 'document'
        ? [r.revisionCode ? 'rev ' + r.revisionCode : null, r.discipline || null, r.issuePurpose || null].filter(Boolean).join(' · ')
        : (r.sourceModule || 'project');
      return `<tr data-explorer-id="${esc(r.id)}">`
        + `<td>${esc(r.title)}</td>`
        + `<td>${esc(r.recordType)}</td>`
        + `<td>${esc(meta)}</td>`
        + `<td><span class="pill">${esc(r.status)}</span></td>`
        + `<td>${esc(r.createdAt ? String(r.createdAt).slice(0, 10) : '—')}</td>`
        + `</tr>`;
    }
    function rowsHtml(rows) {
      if (!rows.length) return '<tr><td colspan="5">No project records match this search.</td></tr>';
      return rows.map(rowHtml).join('');
    }

    function render(model) {
      if (!ownsRoute('project_explorer')) return false;
      const main = root.document.querySelector('#main');
      if (!main) return;
      const project = model.projectName || 'Selected project';
      const rows = adapter.searchRecords('', model.records);
      lastRows = model.records;
      main.innerHTML = `
        <div class="explorer-workspace" data-explorer-tool="project_explorer">
          <div class="rfi-hero">
            <div><h1>Project Explorer</h1><p>Project: <strong>${esc(project)}</strong> · ${model.recordCount} project records · ${model.documentCount} document revisions</p></div>
            <button class="btn" data-explorer-create>+ New ${esc(TOOLS.project_explorer.verb)}</button>
          </div>
          <p class="pill" data-explorer-status role="status">Browsing the bounded record projection the server returns for this project — not a complete archive.</p>
          <section class="panel">
            <h2>Records</h2>
            <div class="field"><label for="explorer-search">Search records</label>
              <input id="explorer-search" class="input" type="search" placeholder="Filter by title, type, module, status or revision" data-explorer-search>
            </div>
            <table class="table"><thead><tr><th>Title</th><th>Type</th><th>Source</th><th>Status</th><th>Created</th></tr></thead>
              <tbody data-explorer-rows>${rowsHtml(rows)}</tbody></table>
          </section>
        </div>`;
      bind(main);
    }

    function filter(main, query) {
      const body = main.querySelector('[data-explorer-rows]');
      if (!body) return;
      body.innerHTML = rowsHtml(adapter.searchRecords(query, lastRows));
    }

    async function createDocument(main) {
      const title = root.prompt?.('Document title'); if (!title?.trim()) return;
      const type = root.prompt?.('Document type (specification, drawing, municipal_form, …)', 'specification'); if (!adapter.DOC_TYPES.includes(type)) { notice('Invalid document type. No document created.'); return; }
      const discipline = root.prompt?.('Discipline (architectural, structural, civil, …)', 'architectural'); if (!adapter.DISCIPLINES.includes(discipline)) { notice('Invalid discipline. No document created.'); return; }
      const rev = root.prompt?.('Revision code', 'A'); if (!rev?.trim()) return;
      if (!root.confirm?.('Create this controlled document on the selected project?')) return;
      const btn = main.querySelector('[data-explorer-create]'); if (btn) btn.disabled = true;
      adapter.createDocument({ title: title.trim(), documentType: type, discipline, issuePurpose: 'for_review', revisionCode: rev.trim(), notes: 'Created from Project Explorer', humanConfirmed: true })
        .then(() => refresh()).then(() => notice('Document created and verified in project records.'))
        .catch(e => notice(e.message + ' No success claimed.')).finally(() => { if (btn) btn.disabled = false; });
    }

    function bind(main) {
      const search = main.querySelector('[data-explorer-search]');
      if (search) search.addEventListener('input', () => filter(main, search.value));
      const create = main.querySelector('[data-explorer-create]');
      if (create) create.addEventListener('click', () => createDocument(main));
    }

    async function refresh() {
      if (!ownsRoute('project_explorer')) return false;
      const token = ++generation;
      try {
        const model = await adapter.load('project_explorer');
        if (disposed || !ownsRoute('project_explorer') || token !== generation) return false;
        render(model);
        return true;
      } catch (e) { if (disposed || !ownsRoute('project_explorer') || token !== generation) return false; notice(e.message + ' No records loaded.'); return false; }
    }

    function toolFor(toolId) { return TOOLS[toolId]; }
    async function apply(toolId) {
      const def = toolFor(toolId);
      if (!def) return { applied: false, reason: 'not-explorer' };
      active = def;
      const ok = await refresh();
      return { applied: ok, tool: toolId };
    }
    const unsubscribe = typeof context.subscribe === 'function' ? context.subscribe(() => { if (active && !disposed) void refresh(); }) : null;
    function dispose() { disposed = true; ++generation; active = null; if (unsubscribe) unsubscribe(); }

    return { apply, dispose, tools: TOOLS, adapter };
  }
  root.V1CloneExplorer = { install };
})(window);
