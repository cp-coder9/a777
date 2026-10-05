(function(root){
  'use strict';
  // Compliance Hub / Contractor Compliance / Insurance Register controller.
  //
  // These three tools were read-only scaffolds in the original v1 document. This
  // module replaces them with real, project-scoped workspaces backed by the verified
  // backend: trust compliance items + drawing-compliance screens for compliance_hub,
  // and the controlled-register workflow for contractor_compliance / insurance_register.
  //
  // Every write is human-confirmed, project-scoped and read-back verified by the
  // adapter. Boundary: recorded internal evidence is not statutory certification,
  // and the backend performs no external submission, verification or lodgement.
  const WARNING = 'Internal compliance evidence only — not statutory certification, municipal approval or an externally verified status. Responsible-professional review remains required.';
  const TOOLS = {
    compliance_hub: { mode: 'trust', title: 'Compliance Hub', reference: 'trust compliance items' },
    contractor_compliance: { mode: 'register', registerKey: 'contractor_compliance', title: 'Contractor Compliance', reference: 'controlled register entries' },
    insurance_register: { mode: 'register', registerKey: 'insurance_register', title: 'Insurance Register', reference: 'controlled register entries' },
  };
  function install({ context }) {
    // Async work may finish after the shared shell has navigated to another tool.
    const ownsRoute = toolId => { const view = typeof state === 'object' ? state : root.state; return !view || view.tool === undefined || view.tool === toolId; };
    const routeFor = def => Object.keys(TOOLS).find(id => TOOLS[id] === def);
    const adapter = root.V1CloneComplianceAdapter.create(context);
    const esc = v => String(v == null ? '' : v).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    let generation = 0, disposed = false, active = null;

    function scope() {
      const s = context.getState();
      if (!s.ready || !s.session || !s.organisationId || !/^[a-f0-9]{32}$/i.test(s.selectedProjectId || '')) throw Error('Select an authenticated project.');
      return s;
    }
    function current(s) {
      const now = context.getState();
      if (!now.ready || !now.session || now.selectedProjectId !== s.selectedProjectId || now.organisationId !== s.organisationId) throw Error('Scope changed; reload this workspace.');
    }
    function notice(message) {if (!ownsRoute(routeFor(active))) return;
      root.showToast?.(message);
      const el = root.document.querySelector('#main [data-compliance-status]');
      if (el) el.textContent = message;
    }
    function mainEl() { return root.document.querySelector('#main'); }
    function projectName(s) { return s.projects?.find(p => p.id === s.selectedProjectId)?.name || 'Selected project'; }

    async function load(def) {
      const s = scope();
      if (def.mode === 'trust') return { mode: 'trust', items: await adapter.listComplianceItems() };
      const register = await adapter.listRegister(def.registerKey);
      return { mode: 'register', register };
    }

    function trustBody(items) {
      const rows = items.length
        ? items.map(i => `<tr data-compliance-item="${esc(i.id)}">
            <td><strong>${esc(i.subjectName || i.documentType || '—')}</strong></td>
            <td>${esc(i.category || '—')}</td>
            <td>${esc(i.documentType || '—')}</td>
            <td>${esc(i.authority || '—')}</td>
            <td>${esc(i.referenceNumber || '—')}</td>
            <td>${esc(i.expiresOn || '—')}</td>
            <td><span class="pill">${esc(i.status || '—')}</span></td>
            <td>${i.grade === 'externally_verified' ? '' : `<button class="btn" data-compliance-review="${esc(i.id)}">Review</button>`}</td>
          </tr>`).join('')
        : '<tr><td colspan="8">No trust compliance items recorded for this project.</td></tr>';
      return `<section class="panel">
          <h2>Trust &amp; professional compliance items</h2>
          <table class="table"><thead><tr><th>Subject</th><th>Category</th><th>Document</th><th>Authority</th><th>Reference</th><th>Expires</th><th>Status</th><th>Action</th></tr></thead>
          <tbody>${rows}</tbody></table>
        </section>
        <section class="panel">
          <h2>Drawing compliance screen</h2>
          <p class="text-xs">Deterministic SANS/NBR illustrative screen against a non-production source. It evaluates drawing checks and returns pass / watch / fail / needs_input — it cannot grant or deny municipal approval.</p>
          <button class="btn" data-compliance-checklist>Run drawing checklist</button>
        </section>`;
    }
    function registerBody(register, def) {
      const transitions = register.definition?.transitions || {};
      const rows = register.entries.length
        ? register.entries.map(e => {
          const allowed = Array.isArray(transitions[e.status]) ? transitions[e.status] : [];
          const actions = allowed.map(status => `<button class="btn" data-compliance-transition="${esc(e.id)}" data-compliance-target="${esc(status)}" data-compliance-version="${esc(e.version)}">${esc(status.replace(/_/g, ' '))}</button>`).join(' ');
          return `<tr data-compliance-entry="${esc(e.id)}">
            <td><strong>${esc(e.title)}</strong></td>
            <td>${esc(e.reference)}</td>
            <td>${esc(e.ownerRole)}</td>
            <td>${esc(e.dueOn || '—')}</td>
            <td><span class="pill">${esc(e.status)}</span></td>
            <td>v${esc(e.version)}</td>
            <td>${actions || '—'}</td>
          </tr>`;
        }).join('')
        : `<tr><td colspan="7">No ${esc(def.title)} entries recorded for this project.</td></tr>`;
      const guard = register.guardrails || {};
      return `<section class="panel">
          <h2>${esc(register.definition?.label || def.title)} entries</h2>
          <p class="text-xs">Initial status on create: <strong>${esc(register.definition?.initialStatus || '—')}</strong>. Transitions are version-checked by the backend; external submission: <strong>${guard.externalSubmissionPerformed === true ? 'claimed' : 'not performed'}</strong>.</p>
          <table class="table"><thead><tr><th>Title</th><th>Reference</th><th>Owner role</th><th>Due</th><th>Status</th><th>Version</th><th>Transition</th></tr></thead>
          <tbody>${rows}</tbody></table>
        </section>`;
    }

    function render(def, model) {
      if (!ownsRoute(routeFor(def))) return false;
      const main = mainEl();
      if (!main) return;
      const s = scope();
      const stats = model.mode === 'trust'
        ? `${model.items.length} recorded compliance item(s) · internal evidence only`
        : `${model.register.entries.length} recorded ${esc(def.reference)}`;
      main.innerHTML = `
        <div class="compliance-workspace" data-compliance-tool="${esc(def.mode === 'trust' ? 'compliance_hub' : def.registerKey)}">
          <div class="rfi-hero">
            <div><h1>${esc(def.title)}</h1><p>Project: <strong>${esc(projectName(s))}</strong> · ${stats}</p></div>
            <button class="btn" data-compliance-create>${def.mode === 'trust' ? '+ New compliance item' : '+ New register entry'}</button>
          </div>
          <p class="pill" data-compliance-status role="status">${esc(WARNING)}</p>
          ${model.mode === 'trust' ? trustBody(model.items) : registerBody(model.register, def)}
        </div>`;
      bind(def, main);
    }

    function bind(def, main) {
      const create = main.querySelector('[data-compliance-create]');
      if (create) create.addEventListener('click', () => createFlow(def).catch(e => notice(e.message + ' No success claimed.')));
      main.querySelectorAll('[data-compliance-review]').forEach(btn => btn.addEventListener('click', () => reviewFlow(btn.getAttribute('data-compliance-review')).catch(e => notice(e.message + ' No success claimed.'))));
      main.querySelectorAll('[data-compliance-transition]').forEach(btn => btn.addEventListener('click', () => transitionFlow(btn).catch(e => notice(e.message + ' No success claimed.'))));
      const checklist = main.querySelector('[data-compliance-checklist]');
      if (checklist) checklist.addEventListener('click', () => checklistFlow().catch(e => notice(e.message + ' No success claimed.')));
    }

    async function createFlow(def) {
      const s = scope(); if (s.readOnly) throw Error('Read-only session.');
      if (def.mode === 'trust') {
        const subjectName = root.prompt?.('Subject name (professional, company, contractor or supplier)'); if (!subjectName?.trim()) return;
        const subjectType = root.prompt?.('Subject type (professional, company, contractor, supplier)', 'contractor'); if (!['professional','company','contractor','supplier'].includes(String(subjectType).trim())) throw Error('Invalid subject type; no item created.');
        const category = root.prompt?.('Category (professional_registration, company_document, insurance, contractor_supplier)', 'contractor_supplier'); if (!['professional_registration','company_document','insurance','contractor_supplier'].includes(String(category).trim())) throw Error('Invalid category; no item created.');
        const documentType = root.prompt?.('Document type (e.g. health_safety_plan, cidb_registration, professional_indemnity)', 'health_safety_plan'); if (!documentType?.trim()) throw Error('A document type is required.');
        const authority = root.prompt?.('Issuing authority / body'); if (!authority?.trim()) return;
        const referenceNumber = root.prompt?.('Reference number on the document'); if (!referenceNumber?.trim()) return;
        const purpose = root.prompt?.('Processing purpose for this recorded detail'); if (!purpose?.trim()) return;
        const retentionUntil = root.prompt?.('Retention until (YYYY-MM-DD)', '2029-01-01'); if (!retentionUntil?.trim()) return;
        const expiresOn = root.prompt?.('Document expiry (YYYY-MM-DD, blank if none)', '') || null;
        const insurance = String(category).trim() === 'insurance';
        let coverageAmountCents = null, requiredCoverageCents = null;
        if (insurance) {
          coverageAmountCents = Number(root.prompt?.('Recorded cover (cents)'));
          requiredCoverageCents = Number(root.prompt?.('Required cover (cents)'));
          if (!Number.isFinite(coverageAmountCents) || !Number.isFinite(requiredCoverageCents) || requiredCoverageCents <= 0) throw Error('Insurance items require recorded and required cover amounts.');
        }
        if (!root.confirm?.('Record this compliance item as internal evidence? No external verification is claimed.')) return;
        await adapter.createComplianceItem({ subjectType: String(subjectType).trim(), subjectName: subjectName.trim(), category: String(category).trim(), documentType: documentType.trim(), authority: authority.trim(), referenceNumber: referenceNumber.trim(), grade: 'self_declared', details: {}, expiresOn, coverageAmountCents, requiredCoverageCents, purpose: purpose.trim(), retentionUntil: retentionUntil.trim(), consentGiven: true, humanConfirmed: true });
        await refresh(def);
        notice('Compliance item recorded and verified in project records.');
        return;
      }
      const title = root.prompt?.('Entry title'); if (!title?.trim()) return;
      const reference = root.prompt?.('Reference (unique in this register)'); if (!reference?.trim()) return;
      const ownerRole = root.prompt?.(def.registerKey === 'insurance_register' ? 'Owner role (e.g. broker, financial_manager)' : 'Owner role (e.g. contractor_manager, safety_officer)'); if (!ownerRole?.trim()) return;
      const dueOn = root.prompt?.('Due / review date (YYYY-MM-DD, blank if none)', '') || null;
      const detailKey = root.prompt?.(def.registerKey === 'insurance_register' ? 'Policy description' : 'Compliance note'); if (!detailKey?.trim()) return;
      if (!root.confirm?.('Create this controlled-register entry? It starts in the declared initial status and no external submission is made.')) return;
      await adapter.createRegisterEntry(def.registerKey, { title: title.trim(), reference: reference.trim(), ownerRole: ownerRole.trim(), dueOn, details: def.registerKey === 'insurance_register' ? { policyDescription: detailKey.trim() } : { complianceNote: detailKey.trim() }, evidenceFileIds: [], humanConfirmed: true });
      await refresh(def);
      notice('Register entry created and verified.');
    }

    async function reviewFlow(itemId) {
      const s = scope(); if (s.readOnly) throw Error('Read-only session.');
      const decision = root.prompt?.('Review decision (manually_reviewed, externally_verified, rejected)', 'manually_reviewed');
      if (!['manually_reviewed','externally_verified','rejected'].includes(String(decision).trim())) throw Error('Invalid review decision; no review recorded.');
      const note = root.prompt?.('Review note (what was checked and by whom)'); if (!note?.trim()) return;
      let provider = null, providerReference = null;
      if (String(decision).trim() === 'externally_verified') {
        provider = root.prompt?.('External provider name'); providerReference = root.prompt?.('Provider reference');
        if (!provider?.trim() || !providerReference?.trim()) throw Error('External verification requires a provider and reference; Architex does not verify externally.');
      }
      if (!root.confirm?.('Record this review? Internal review is not a certification.')) return;
      await adapter.reviewItem(itemId, { decision: String(decision).trim(), note: note.trim(), provider: provider?.trim() || null, providerReference: providerReference?.trim() || null, humanConfirmed: true });
      await refresh(active);
      notice('Compliance review recorded and verified.');
    }

    async function transitionFlow(btn) {
      const s = scope(); if (s.readOnly) throw Error('Read-only session.');
      const entryId = btn.getAttribute('data-compliance-transition');
      const status = btn.getAttribute('data-compliance-target');
      const expectedVersion = Number(btn.getAttribute('data-compliance-version'));
      const reason = root.prompt?.('Reason for moving to "' + status + '"'); if (!reason?.trim()) return;
      if (!root.confirm?.('Record this register transition? No external submission or statutory status is created.')) return;
      await adapter.transitionEntry(entryId, { status, reason: reason.trim(), expectedVersion, humanConfirmed: true });
      await refresh(active);
      notice('Register transition recorded and verified.');
    }

    async function checklistFlow() {
      scope();
      const buildingDescription = root.prompt?.('Building description (feed the deterministic screen)'); if (!buildingDescription?.trim()) return;
      const result = await adapter.checklist({ buildingDescription: buildingDescription.trim() });
      notice('Drawing checklist: ' + result.overallStatus + ' (not persisted; professional review required).');
    }

    async function refresh(def) {
      if (!ownsRoute(routeFor(def))) return false;
      const token = ++generation;
      try {
        const model = await load(def);
        if (disposed || !ownsRoute(routeFor(def)) || token !== generation) return false;
        render(def, model);
        return true;
      } catch (e) { if (disposed || !ownsRoute(routeFor(def)) || token !== generation) return false; notice(e.message + ' No records loaded.'); return false; }
    }

    async function apply(toolId) {
      const def = TOOLS[toolId];
      if (!def) return { applied: false, reason: 'not-compliance' };
      active = def;
      const ok = await refresh(def);
      return { applied: ok, tool: toolId };
    }
    const unsubscribe = typeof context.subscribe === 'function' ? context.subscribe(() => { if (active && !disposed) void refresh(active); }) : null;
    function dispose() { disposed = true; ++generation; active = null; if (unsubscribe) unsubscribe(); }

    return { apply, dispose, tools: Object.keys(TOOLS), spec: { tools: Object.keys(TOOLS), functional: true, boundary: 'internal-evidence-only' } };
  }
  root.V1CloneComplianceHub = { install };
})(window);
