(function (root) {
  'use strict';
  // Supplier catalogue controller — supplier register, catalogue revisions, procurement
  // baselines and advisory catalogue matching. Backed by the verified
  // SupplierProcurementService routes through the supplier_catalog adapter.
  // Honest boundary: catalogue revision metadata and advisory matches only — never an
  // approved supplier list, award, purchase order or payment, and no invented supplier
  // data. Every write is human-confirmed and read-back verified before success is shown.
  const TOOL = 'supplier_catalog';
  const LEVEL_LABEL = { 0: 'manual', 1: 'catalogue upload', 2: 'integration required' };
  function install({ context }) {
    // Async work may finish after the shared shell has navigated to another tool.
    const ownsRoute = toolId => { const view = typeof state === 'object' ? state : root.state; return !view || view.tool === undefined || view.tool === toolId; };
    const adapter = root.V1CloneSupplierAdapter.create(context);
    const esc = v => String(v == null ? '' : v).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    let generation = 0, disposed = false, active = false, model = null;

    function scope() {
      const s = context.getState();
      if (!s.ready || !s.session || !s.organisationId || !/^[a-f0-9]{32}$/i.test(s.selectedProjectId || '')) throw Error('Select an authenticated project.');
      return s;
    }
    function notice(msg) {if (!ownsRoute(TOOL)) return; root.showToast?.(msg); const el = root.document.querySelector('#main [data-sc-status]'); if (el) el.textContent = msg; }
    function levelLabel(level) { const n = Number(level); return LEVEL_LABEL[n] || (n > 1 ? LEVEL_LABEL[2] : String(level)); }
    function shortSha(sha) { return sha ? String(sha).slice(0, 12) : '—'; }

    async function load() { return adapter.list(); }

    function supplierRows(model) {
      if (!model.suppliers.length) return '<tr><td colspan="5">No suppliers registered on this project yet.</td></tr>';
      return model.suppliers.map(s => `<tr data-sc-supplier="${esc(s.id)}">
        <td>${esc(s.name)}</td>
        <td>${esc(levelLabel(s.capabilityLevel))}</td>
        <td>${esc(s.connectorStatus || '—')}</td>
        <td>${esc((s.serviceRegions || []).join(', ') || '—')}</td>
        <td><button class="btn" data-sc-act="catalogue" data-sc-id="${esc(s.id)}">Add catalogue</button></td>
      </tr>`).join('');
    }
    function catalogueRows(model) {
      if (!model.catalogues.length) return '<tr><td colspan="5">No catalogue revisions recorded.</td></tr>';
      return model.catalogues.map(c => {
        const supplier = model.suppliers.find(s => s.id === c.supplierId);
        return `<tr data-sc-catalogue="${esc(c.id)}">
          <td>${esc(supplier ? supplier.name : c.supplierId)}</td>
          <td>r${esc(c.revision)}</td>
          <td>${esc(c.sourceKind || '—')}</td>
          <td>${esc(c.itemCount)}</td>
          <td>${esc(shortSha(c.snapshotSha256))}</td>
        </tr>`;
      }).join('');
    }
    function baselineRows(model) {
      if (!model.baselines.length) return '<tr><td colspan="5">No procurement baselines drafted.</td></tr>';
      return model.baselines.map(b => {
        const action = b.status === 'draft'
          ? `<button class="btn" data-sc-act="approve" data-sc-id="${esc(b.id)}" data-sc-version="${esc(b.version)}">Approve baseline</button>`
          : b.status === 'approved'
            ? `<button class="btn" data-sc-act="package" data-sc-id="${esc(b.id)}">Build package</button>`
            : '';
        return `<tr data-sc-baseline="${esc(b.id)}">
          <td>${esc(b.title)}</td>
          <td><span class="pill">${esc(b.status)}</span></td>
          <td>v${esc(b.version)}</td>
          <td>${esc(shortSha(b.snapshotSha256))}</td>
          <td>${action}</td>
        </tr>`;
      }).join('');
    }
    function packageRows(model) {
      if (!model.packages.length) return '<tr><td colspan="5">No procurement packages built.</td></tr>';
      return model.packages.map(p => `<tr data-sc-package="${esc(p.id)}">
        <td>${esc(p.title)}</td>
        <td>${esc(p.projectRegion || '—')}</td>
        <td><span class="pill">${esc(p.status)}</span></td>
        <td>${esc(model.matches.filter(m => m.packageId === p.id).length)}</td>
        <td><button class="btn" data-sc-act="match" data-sc-id="${esc(p.id)}">Run advisory matching</button>
          <button class="btn" data-sc-act="rfq" data-sc-id="${esc(p.id)}">Issue RFQ</button></td>
      </tr>`).join('');
    }

    function render(m) {
      if (!ownsRoute(TOOL)) return false;
      const main = root.document.querySelector('#main');
      if (!main) return;
      const summary = `${m.suppliers.length} supplier${m.suppliers.length === 1 ? '' : 's'} · ${m.catalogues.length} catalogue revision${m.catalogues.length === 1 ? '' : 's'} · ${m.baselines.length} baseline${m.baselines.length === 1 ? '' : 's'} · ${m.packages.length} package${m.packages.length === 1 ? '' : 's'}`;
      main.innerHTML = `
        <div class="sc-workspace m3-commercial-workspace" data-sc-tool="${TOOL}">
          <div class="rfi-hero">
            <div><h1>Supplier Catalogue</h1><p>Project: <strong>${esc(m.projectName)}</strong> · ${esc(summary)}</p></div>
            <div><button class="btn" data-sc-create>+ Add supplier</button>
              <button class="btn" data-sc-baseline>+ New baseline</button></div>
          </div>
          <p class="pill" data-sc-status role="status">Catalogue revision metadata and advisory matches only — not an approved supplier list, award, purchase order or payment. No supplier data is invented here.</p>
          <section class="panel"><h2>Suppliers</h2>
            <table class="table"><thead><tr><th>Name</th><th>Capability</th><th>Connector</th><th>Service regions</th><th>Action</th></tr></thead>
              <tbody data-sc-suppliers></tbody></table>
          </section>
          <section class="panel"><h2>Catalogue revisions</h2>
            <table class="table"><thead><tr><th>Supplier</th><th>Revision</th><th>Source</th><th>Items</th><th>Snapshot</th></tr></thead>
              <tbody data-sc-catalogues></tbody></table>
          </section>
          <section class="panel"><h2>Procurement baselines</h2>
            <table class="table"><thead><tr><th>Title</th><th>Status</th><th>Version</th><th>Snapshot</th><th>Action</th></tr></thead>
              <tbody data-sc-baselines></tbody></table>
          </section>
          <section class="panel"><h2>Packages, matches and RFQs</h2>
            <table class="table"><thead><tr><th>Package</th><th>Region</th><th>Status</th><th>Advisory matches</th><th>Action</th></tr></thead>
              <tbody data-sc-packages></tbody></table>
          </section>
        </div>`;
      const set = (sel, html) => { const el = main.querySelector(sel); if (el) el.innerHTML = html; };
      set('[data-sc-suppliers]', supplierRows(m));
      set('[data-sc-catalogues]', catalogueRows(m));
      set('[data-sc-baselines]', baselineRows(m));
      set('[data-sc-packages]', packageRows(m));
      const status = main.querySelector('[data-sc-status]');
      if (status) status.textContent = summary + ' · catalogue revision metadata and advisory matches only; not an approved supplier list, award, order or payment.';
      bind(main);
    }

    async function refresh() {
      if (!ownsRoute(TOOL)) return false;
      const token = ++generation;
      try { const next = await load(); if (disposed || !ownsRoute(TOOL) || token !== generation) return false; model = next; render(model); return true; }
      catch (e) { if (disposed || !ownsRoute(TOOL) || token !== generation) return false; notice(e.message + ' No records loaded.'); return false; }
    }
    function busy(main, selector, run) {
      const btn = main.querySelector(selector);
      if (btn) btn.disabled = true;
      return Promise.resolve().then(run).finally(() => { if (btn) btn.disabled = false; });
    }

    function addSupplier(main) {
      const name = root.prompt?.('Supplier name'); if (!name?.trim()) return;
      const levelRaw = root.prompt?.('Capability level (0 manual, 1 catalogue upload — higher levels require an external connector)', '1');
      const level = Number(String(levelRaw).trim());
      if (!Number.isInteger(level) || level < 0 || level > 6) { notice('Invalid capability level. No supplier registered.'); return; }
      const regions = String(root.prompt?.('Service regions (comma separated)', '') || '').split(',').map(v => v.trim()).filter(Boolean);
      const email = String(root.prompt?.('Contact email (optional)', '') || '').trim();
      if (!root.confirm?.('Register this supplier on the selected project?')) return;
      busy(main, '[data-sc-create]', () => adapter.createSupplier({ name: name.trim(), capabilityLevel: level, serviceRegions: regions, contactEmail: email || null, humanConfirmed: true })
        .then(() => refresh()).then(() => notice('Supplier registered and verified in project records.')))
        .catch(e => notice(e.message + ' No success claimed.'));
    }

    function addCatalogue(main, supplierId) {
      const sku = root.prompt?.('Item SKU'); if (!sku?.trim()) return;
      const name = root.prompt?.('Item name'); if (!name?.trim()) return;
      const category = root.prompt?.('Category'); if (!category?.trim()) return;
      const unit = root.prompt?.('Unit (e.g. bag, m2)', 'each'); if (!unit?.trim()) return;
      const priceRaw = root.prompt?.('Unit price (ZAR)', '0'); const priceZar = Number(priceRaw);
      if (!Number.isFinite(priceZar) || priceZar < 0) { notice('Invalid price. No catalogue revision created.'); return; }
      const stockRaw = root.prompt?.('Stock quantity', '0'); const stockQuantity = Number(stockRaw);
      if (!Number.isFinite(stockQuantity) || stockQuantity < 0) { notice('Invalid stock quantity. No catalogue revision created.'); return; }
      const leadRaw = root.prompt?.('Lead time (days)', '0'); const leadTimeDays = Number(leadRaw);
      if (!Number.isInteger(leadTimeDays) || leadTimeDays < 0) { notice('Invalid lead time. No catalogue revision created.'); return; }
      const standards = String(root.prompt?.('Standards (comma separated, optional)', '') || '').split(',').map(v => v.trim()).filter(Boolean);
      const deliveryRegions = String(root.prompt?.('Delivery regions (comma separated, optional)', '') || '').split(',').map(v => v.trim()).filter(Boolean);
      if (!root.confirm?.('Record this catalogue revision against the selected supplier?')) return;
      busy(main, '[data-sc-act="catalogue"][data-sc-id="' + supplierId + '"]', () => adapter.addCatalogue(supplierId, {
        sourceKind: 'manual', items: [{ sku: sku.trim(), name: name.trim(), category: category.trim(), unit: unit.trim(), priceZar, stockQuantity, leadTimeDays, standards, deliveryRegions, substitutionEligible: false }], humanConfirmed: true,
      }).then(() => refresh()).then(() => notice('Catalogue revision recorded and verified.')))
        .catch(e => notice(e.message + ' No success claimed.'));
    }

    function newBaseline(main) {
      const title = root.prompt?.('Baseline title'); if (!title?.trim()) return;
      const ref = root.prompt?.('First line reference (sourceItemRef)'); if (!ref?.trim()) return;
      const description = root.prompt?.('Line description'); if (!description?.trim()) return;
      const category = root.prompt?.('Line category'); if (!category?.trim()) return;
      const quantityRaw = root.prompt?.('Line quantity', '1'); const quantity = Number(quantityRaw);
      if (!Number.isFinite(quantity) || quantity <= 0) { notice('Invalid quantity. No baseline drafted.'); return; }
      const unit = root.prompt?.('Line unit (e.g. bag, m2)', 'each'); if (!unit?.trim()) return;
      const requiredStandard = String(root.prompt?.('Required standard (optional)', '') || '').trim();
      const requiredOnSiteDate = String(root.prompt?.('Required on site (YYYY-MM-DD)', '') || '').trim();
      if (!/^\d{4}-\d{2}-\d{2}$/.test(requiredOnSiteDate)) { notice('Invalid required-on-site date. No baseline drafted.'); return; }
      if (!root.confirm?.('Draft this procurement baseline on the selected project?')) return;
      busy(main, '[data-sc-baseline]', () => adapter.baselines({
        title: title.trim(),
        lines: [{ sourceItemRef: ref.trim(), description: description.trim(), category: category.trim(), specification: null, requiredStandard: requiredStandard || null, quantity, unit: unit.trim(), allowSubstitution: true, requiredOnSiteDate, sourceDocumentIds: [] }],
        humanConfirmed: true,
      }).then(() => refresh()).then(() => notice('Procurement baseline drafted (not yet approved).')))
        .catch(e => notice(e.message + ' No success claimed.'));
    }

    function approveBaseline(main, id, version) {
      const reason = root.prompt?.('Approval reason'); if (!reason?.trim()) return;
      if (!root.confirm?.('Approve this baseline version? Approval is a human decision recorded against your account.')) return;
      busy(main, '[data-sc-act="approve"][data-sc-id="' + id + '"]', () => adapter.approveBaseline(id, { reason: reason.trim(), expectedVersion: Number(version), humanConfirmed: true })
        .then(() => refresh()).then(() => notice('Baseline approved and verified.')))
        .catch(e => notice(e.message + ' No success claimed.'));
    }

    function buildPackage(main, id) {
      const title = root.prompt?.('Package title'); if (!title?.trim()) return;
      const region = root.prompt?.('Project region (e.g. gauteng)', ''); if (!region?.trim()) return;
      if (!root.confirm?.('Build a procurement package from this approved baseline?')) return;
      busy(main, '[data-sc-act="package"][data-sc-id="' + id + '"]', () => adapter.packages(id, { title: title.trim(), projectRegion: region.trim(), humanConfirmed: true })
        .then(() => refresh()).then(() => notice('Procurement package built and verified.')))
        .catch(e => notice(e.message + ' No success claimed.'));
    }

    function runMatching(main, id) {
      if (!root.confirm?.('Run advisory catalogue matching for this package? Matches are advisory only.')) return;
      busy(main, '[data-sc-act="match"][data-sc-id="' + id + '"]', () => adapter.matches(id, { humanConfirmed: true })
        .then(() => refresh()).then(() => notice('Advisory matches recorded and verified.')))
        .catch(e => notice(e.message + ' No success claimed.'));
    }

    function issueRfq(main, id) {
      if (!model || !model.suppliers.length) { notice('Register a supplier before issuing an RFQ.'); return; }
      let supplier = model.suppliers[0];
      if (model.suppliers.length > 1) {
        const wanted = String(root.prompt?.('Supplier name for this RFQ', supplier.name) || '').trim().toLowerCase();
        const found = model.suppliers.find(s => s.name.toLowerCase() === wanted);
        if (!found) { notice('No matching supplier. No RFQ issued.'); return; }
        supplier = found;
      }
      if (!root.confirm?.('Issue an RFQ to ' + supplier.name + '? An access link is shown once.')) return;
      busy(main, '[data-sc-act="rfq"][data-sc-id="' + id + '"]', () => adapter.rfqs(id, { supplierId: supplier.id, expiresInDays: 14, humanConfirmed: true })
        .then(result => refresh().then(() => notice(result.accessToken
          ? 'RFQ issued and verified. One-time supplier link token: ' + result.accessToken
          : 'RFQ issued and verified.'))))
        .catch(e => notice(e.message + ' No success claimed.'));
    }

    function bind(main) {
      const create = main.querySelector('[data-sc-create]'); if (create) create.addEventListener('click', () => addSupplier(main));
      const baseline = main.querySelector('[data-sc-baseline]'); if (baseline) baseline.addEventListener('click', () => newBaseline(main));
      main.querySelectorAll('[data-sc-act]').forEach(btn => btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-sc-id'); const act = btn.getAttribute('data-sc-act');
        if (act === 'catalogue') addCatalogue(main, id);
        else if (act === 'approve') approveBaseline(main, id, btn.getAttribute('data-sc-version'));
        else if (act === 'package') buildPackage(main, id);
        else if (act === 'match') runMatching(main, id);
        else if (act === 'rfq') issueRfq(main, id);
      }));
    }

    async function apply(toolId) {
      if (toolId !== TOOL) return { applied: false, reason: 'not-supplier' };
      active = true;
      const ok = await refresh();
      return { applied: ok, tool: TOOL };
    }
    const unsubscribe = typeof context.subscribe === 'function' ? context.subscribe(() => { if (active && !disposed) void refresh(); }) : null;
    function dispose() { disposed = true; ++generation; active = false; model = null; if (unsubscribe) unsubscribe(); }
    return { apply, dispose, tool: TOOL };
  }
  root.V1CloneSupplier = { install };
})(window);
