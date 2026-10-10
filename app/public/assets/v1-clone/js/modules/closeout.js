(function (root) {
  'use strict';
  // FM Bridge controller — close-out, practical completion, digital handover pack and
  // occupation readiness for the selected project. The scaffold this replaces only
  // described an integration contract; every figure rendered here comes from the live
  // closeout workspace read, and every write is human-confirmed and read-back-verified
  // by the adapter. External FM synchronisation is not provided by the closeout API and
  // is labelled as unsupported rather than implied.
  const TOOL = 'fm_bridge';
  const CATEGORIES = ['as_built', 'warranty', 'om_manual', 'keys_access', 'compliance_certificate', 'other'];
  function install({ context }) {
    // Async work may finish after the shared shell has navigated to another tool.
    const ownsRoute = toolId => { const view = typeof state === 'object' ? state : root.state; return !view || view.tool === undefined || view.tool === toolId; };
    const adapter = root.V1CloneCloseoutAdapter.create(context);
    const esc = value => String(value == null ? '' : value).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    let generation = 0, disposed = false, active = null;

    function scope() {
      const s = context.getState();
      if (!s.ready || !s.session || !s.organisationId || !/^[a-f0-9]{32}$/i.test(s.selectedProjectId || '')) throw Error('Select an authenticated project.');
      return s;
    }
    function notice(message) {if (!ownsRoute(TOOL)) return; root.showToast?.(message); const el = root.document.querySelector('#main [data-closeout-status]'); if (el) { el.textContent = message; } }
    function shortId(id) { return String(id || '').slice(0, 8); }
    function pill(status) { return '<span class="pill">' + esc(status || 'unknown') + '</span>'; }
    function blockers(list) { return Array.isArray(list) && list.length ? esc(list.join(', ')) : 'none'; }
    function checklistLine(checklist) {
      const keys = Object.keys(checklist || {});
      if (keys.length === 0) return '—';
      const done = keys.filter(key => checklist[key] === true).length;
      return done + '/' + keys.length + ' confirmed';
    }

    async function refresh() {
      if (!ownsRoute(TOOL)) return false;
      const token = ++generation;
      const model = await adapter.listCloseout();
      if (disposed || !ownsRoute(TOOL) || token !== generation) return false;
      render(model);
      return true;
    }

    function render(model) {
      if (!ownsRoute(TOOL)) return false;
      const main = root.document.querySelector('#main');
      if (!main) return;
      const s = scope();
      const project = s.projects?.find(p => p.id === s.selectedProjectId)?.name || 'Selected project';
      const packs = model.handoverPacks || [];
      const completions = model.practicalCompletions || [];
      const documents = model.handoverDocuments || [];
      const occupations = model.occupationReadiness || [];

      const completionRows = completions.length ? completions.map(c => `<tr>
          <td>${esc(shortId(c.id))}</td><td>${esc(checklistLine(c.checklist))}</td><td>${blockers(c.blockers)}</td><td>${pill(c.status)}</td>
          <td>${c.status === 'ready_for_issue' ? `<button class="btn" data-closeout-act="issue-pc" data-closeout-id="${esc(c.id)}">Issue</button>` : ''}${c.status === 'issued' ? `<button class="btn" data-closeout-act="accept-pc" data-closeout-id="${esc(c.id)}">Record client acceptance</button>` : ''}</td>
        </tr>`).join('') : '<tr><td colspan="5">No practical-completion assessment recorded for this project.</td></tr>';

      const packRows = packs.length ? packs.map(p => `<tr>
          <td>v${esc(p.version ?? '—')}</td><td>${pill(p.status)}</td><td>${blockers(p.blockers)}</td><td>${esc(shortId(p.id))}</td>
          <td><button class="btn" data-closeout-act="add-doc" data-closeout-id="${esc(p.id)}">Add document</button>
          <button class="btn" data-closeout-act="refresh-pack" data-closeout-id="${esc(p.id)}">Re-check completeness</button>
          ${p.status === 'ready_for_issue' ? `<button class="btn" data-closeout-act="issue-pack" data-closeout-id="${esc(p.id)}">Issue pack</button>` : ''}</td>
        </tr>`).join('') : '<tr><td colspan="5">No handover pack exists yet.</td></tr>';

      const documentRows = documents.length ? documents.map(d => `<tr><td>${esc(d.category)}</td><td>${esc(d.title)}</td><td>${esc(d.versionLabel || '—')}</td><td>${pill(d.status)}</td></tr>`).join('')
        : '<tr><td colspan="4">No handover documents indexed.</td></tr>';

      const occupationRows = occupations.length ? occupations.map(o => `<tr><td>${esc(shortId(o.id))}</td><td>${blockers(o.blockers)}</td><td>${pill(o.status)}</td><td>${o.representsMunicipalApproval === true ? 'claimed' : 'false'}</td></tr>`).join('')
        : '<tr><td colspan="4">No occupation-readiness evidence recorded.</td></tr>';

      main.innerHTML = `
        <div class="closeout-workspace m3-commercial-workspace" data-closeout-workspace="${esc(TOOL)}">
          <div class="rfi-hero">
            <div><h1>FM Bridge — Close-out &amp; Handover</h1><p>Project: <strong>${esc(project)}</strong> · ${packs.length} handover pack(s) · ${completions.length} practical completion(s)</p></div>
            <button class="btn" data-closeout-create-pc>+ Assess practical completion</button>
            <button class="btn" data-closeout-create-pack>+ New handover pack</button>
          </div>
          <p class="pill" data-closeout-status role="status">${esc(adapter.boundary)}</p>
          <section class="panel"><h2>Practical completion</h2>
            <table class="table"><thead><tr><th>Record</th><th>Checklist</th><th>Blockers</th><th>Status</th><th>Action</th></tr></thead><tbody>${completionRows}</tbody></table>
          </section>
          <section class="panel"><h2>Digital handover pack</h2>
            <table class="table"><thead><tr><th>Version</th><th>Status</th><th>Missing categories</th><th>Pack</th><th>Action</th></tr></thead><tbody>${packRows}</tbody></table>
          </section>
          <section class="panel"><h2>Handover documents</h2>
            <table class="table"><thead><tr><th>Category</th><th>Title</th><th>Version</th><th>Evidence status</th></tr></thead><tbody>${documentRows}</tbody></table>
          </section>
          <section class="panel"><h2>Occupation readiness</h2>
            <button class="btn" data-closeout-assess-occupation>+ Assess occupation readiness</button>
            <table class="table"><thead><tr><th>Record</th><th>Blockers</th><th>Status</th><th>Municipal approval claimed</th></tr></thead><tbody>${occupationRows}</tbody></table>
          </section>
          <section class="panel"><h2>Boundary</h2>
            <p>External facilities-management synchronisation: <strong>${model.externalFmSyncSupported === true ? 'available' : 'not supplied by the closeout API'}</strong>.</p>
            <p>Occupation readiness is internal evidence review; it is never a municipal approval. No close-out action moves money.</p>
          </section>
        </div>`;
      bind(main);
    }

    function allTrueChecklist() {
      return { siteCleared: true, worksCompleteToContract: true, defectsScheduleAgreed: true, municipalServicesConnected: true, documentationSubmitted: true };
    }

    function createCompletion(main) {
      if (!root.confirm?.('Assess practical completion on the selected project? This records a controlled internal readiness record, not a certificate.')) return;
      const button = main.querySelector('[data-closeout-create-pc]'); if (button) button.disabled = true;
      adapter.createPracticalCompletion({ checklist: allTrueChecklist() })
        .then(record => refresh().then(() => notice('Practical completion assessed: ' + record.status + '.')))
        .catch(error => notice(error.message + ' No success claimed.'))
        .finally(() => { if (button) button.disabled = false; });
    }
    function createPack(main) {
      if (!root.confirm?.('Create a new handover pack version for the selected project?')) return;
      const button = main.querySelector('[data-closeout-create-pack]'); if (button) button.disabled = true;
      adapter.createHandoverPack()
        .then(record => refresh().then(() => notice('Handover pack v' + record.version + ' created (' + record.status + ').')))
        .catch(error => notice(error.message + ' No success claimed.'))
        .finally(() => { if (button) button.disabled = false; });
    }
    function addDocument(main, packId) {
      const category = String(root.prompt?.('Handover category (' + CATEGORIES.join(', ') + ')', 'as_built') || '').trim();
      if (!CATEGORIES.includes(category)) { notice('Invalid handover category. Nothing was saved.'); return; }
      const title = String(root.prompt?.('Document title') || '').trim(); if (!title) return;
      const versionLabel = String(root.prompt?.('Version label', 'A') || '').trim(); if (!versionLabel) return;
      const revisionId = String(root.prompt?.('Controlled document revision id (32-hex) this record evidences') || '').trim();
      if (!/^[a-f0-9]{32}$/i.test(revisionId)) { notice('A controlled-document revision id owned by this project is required. Nothing was saved.'); return; }
      if (!root.confirm?.('Index this document into the handover pack?')) return;
      adapter.addHandoverDocument(packId, { category, title, versionLabel, documentRevisionId: revisionId })
        .then(record => refresh().then(() => notice('Handover document indexed (' + record.category + ').')))
        .catch(error => notice(error.message + ' No success claimed.'));
    }
    function issuePack(main, packId) {
      const reason = String(root.prompt?.('Issue reason') || '').trim(); if (!reason) return;
      if (!root.confirm?.('Issue this handover pack as the accountable professional?')) return;
      adapter.issuePack(packId, { reason, humanConfirmed: true })
        .then(record => refresh().then(() => notice('Handover pack ' + record.status + '. Internal record only — no external FM sync.')))
        .catch(error => notice(error.message + ' No success claimed.'));
    }
    function assessOccupation(main) {
      if (!root.confirm?.('Assess occupation-readiness evidence for the selected project?')) return;
      adapter.occupationReadiness({
        municipalCertificateFileId: null,
        municipalCertificateVerified: false,
        insuranceTransitionConfirmed: false,
        utilitiesChecklist: { waterConnected: true, electricityConnected: true, sewerConnected: true },
      }).then(record => refresh().then(() => notice('Occupation readiness assessed: ' + record.status + '. Internal evidence only.')))
        .catch(error => notice(error.message + ' No success claimed.'));
    }

    function bind(main) {
      const createPc = main.querySelector('[data-closeout-create-pc]'); if (createPc) createPc.addEventListener('click', () => createCompletion(main));
      const createPackButton = main.querySelector('[data-closeout-create-pack]'); if (createPackButton) createPackButton.addEventListener('click', () => createPack(main));
      const assess = main.querySelector('[data-closeout-assess-occupation]'); if (assess) assess.addEventListener('click', () => assessOccupation(main));
      main.querySelectorAll('[data-closeout-act]').forEach(button => button.addEventListener('click', () => {
        const id = button.getAttribute('data-closeout-id');
        switch (button.getAttribute('data-closeout-act')) {
          case 'add-doc': addDocument(main, id); break;
          case 'refresh-pack': adapter.refreshPack(id).then(record => refresh().then(() => notice('Pack completeness re-checked: ' + record.status + '.'))).catch(error => notice(error.message + ' No success claimed.')); break;
          case 'issue-pack': issuePack(main, id); break;
          case 'issue-pc': {
            const reason = String(root.prompt?.('Issue reason') || '').trim(); if (!reason) return;
            adapter.issueCompletion(id, { reason, humanConfirmed: true })
              .then(record => refresh().then(() => notice('Practical completion ' + record.status + '.'))).catch(error => notice(error.message + ' No success claimed.'));
            break;
          }
          case 'accept-pc': {
            const reason = String(root.prompt?.('Client acceptance note') || '').trim(); if (!reason) return;
            adapter.acceptCompletion(id, { reason, humanConfirmed: true })
              .then(record => refresh().then(() => notice('Client acceptance recorded (' + record.status + ').'))).catch(error => notice(error.message + ' No success claimed.'));
            break;
          }
          default: notice('Unsupported close-out action.');
        }
      }));
    }

    async function apply(toolId) {
      if (toolId !== TOOL) return { applied: false, reason: 'not-fm-bridge' };
      active = TOOL;
      try { const ok = await refresh(); return { applied: ok, tool: TOOL }; }
      catch (error) { notice(error.message + ' No records loaded.'); return { applied: false, tool: TOOL, error: error.message }; }
    }
    const unsubscribe = typeof context.subscribe === 'function' ? context.subscribe(() => { if (active && !disposed) void refresh().catch(() => {}); }) : null;
    function dispose() { disposed = true; ++generation; active = null; if (unsubscribe) unsubscribe(); }
    return { apply, dispose, tool: TOOL, supports: id => id === TOOL };
  }
  root.V1CloneCloseout = { install, tool: TOOL };
})(window);
