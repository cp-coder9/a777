(function (root) {
  'use strict';
  // Record workspace for the ONE module whose v1.html original had no domain form and which
  // has no backend of its own: refuse_calculator (versioned calculation inputs). Every other
  // module that the audit called read-only turned out to be real already, and is deliberately
  // NOT served from here — see ModuleRecordSchema for the checked list. The field set rendered
  // here comes from the server's own catalogue, so the form and the validator cannot disagree.
  //
  // The same controller also binds the two scaffold controls that previously could only
  // refuse: "Add implementation note" (A006) and "Attach project" (A007). Those now write a
  // real record; if the write fails, the failure is shown verbatim and nothing is claimed.
  const RECORD_TOOLS = ['refuse_calculator'];
  const BASE_INPUTS = ['ownerRole', 'dueOn', 'severity', 'reference'];
  const BOUNDARIES = {
    refuse_calculator: 'Stored calculation inputs — this is not a certified result, and a professional declaration is required before use.',
  };
  const LABELS = {
    title: 'Title', detail: 'Detail', ownerRole: 'Responsible role', dueOn: 'Due date',
    severity: 'Severity', reference: 'Reference', evidenceFileIds: 'Evidence file ids',
    ruleVersionId: 'Rule version id', occupancyUse: 'Occupancy use', quantity: 'Quantity',
    collectionInterval: 'Collection interval', binCount: 'Bin count', storageArea: 'Storage area',
    facilityRoute: 'Facility route', declarationNote: 'Declaration note', noticeType: 'Notice type',
    claimSummary: 'Claim summary', outcome: 'Outcome', packReference: 'Pack reference',
    authority: 'Authority', responseSummary: 'Response summary', respondBy: 'Respond by',
    scaffoldId: 'Scaffold', note: 'Note', attachmentRef: 'Attachment reference',
  };

  function install({ context }) {
    const ownsRoute = toolId => { const view = typeof state === 'object' ? state : root.state; return !view || view.tool === undefined || view.tool === toolId; };
    const adapter = root.V1CloneRecordsAdapter.create(context);
    const esc = value => String(value == null ? '' : value).replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
    const label = key => LABELS[key] || key.replace(/([A-Z])/g, ' $1').replace(/^./, c => c.toUpperCase());
    let generation = 0, disposed = false, active = null, records = [], specs = null;
    // The workspace re-renders after every write, so the last outcome is remembered and
    // re-rendered with it — otherwise a success message would be wiped by its own refresh.
    let lastNotice = 'Nothing saved yet.';
    let acted = false;

    function notice(message) {
      if (disposed || !ownsRoute(active)) return;
      acted = true;
      lastNotice = message;
      root.showToast?.(message);
      // Every status surface is updated: the form pill and the workspace banner. Updating
      // only one of them showed a stale success message next to a fresh failure.
      root.document.querySelectorAll('#main [data-records-status], #main [data-records-message]')
        .forEach(node => { node.textContent = message; });
    }

    async function specFor(toolId) {
      if (!specs) specs = await adapter.loadCatalogue();
      return specs.modules?.[toolId] || null;
    }

    function fieldInput(name, spec, required) {
      const requiredAttr = required ? ' required' : '';
      if (name === 'severity') {
        return `<label class="field"><span>${esc(label(name))}${required ? ' *' : ''}</span>
          <select data-field="${esc(name)}"${requiredAttr}><option value="">Not set</option>${['low', 'medium', 'high', 'critical'].map(v => `<option value="${v}">${v}</option>`).join('')}</select></label>`;
      }
      if (name === 'dueOn' || name === 'respondBy' || name === 'collectionInterval') {
        return `<label class="field"><span>${esc(label(name))}${required ? ' *' : ''}</span><input data-field="${esc(name)}" type="text"${requiredAttr} placeholder="${name === 'dueOn' ? 'YYYY-MM-DD' : ''}"></label>`;
      }
      return `<label class="field"><span>${esc(label(name))}${required ? ' *' : ''}</span><input data-field="${esc(name)}" type="text"${requiredAttr}></label>`;
    }

    function renderForm(spec) {
      const required = spec.requiredFields || [];
      const base = ['title', 'detail'].concat(BASE_INPUTS.filter(f => required.includes(f) || ['ownerRole', 'dueOn', 'severity'].includes(f)));
      const payloadFields = (spec.payloadFields || []).filter(f => !base.includes(f));
      const rows = base.concat(payloadFields, ['evidenceFileIds'])
        .filter(name => !['reference'].includes(name) || required.includes('reference'))
        .map(name => fieldInput(name, spec, required.includes(name)));
      const statuses = (spec.statuses || []).map(s => `<option value="${esc(s)}">${esc(s)}</option>`).join('');
      return `
        <section class="panel" data-records-form-panel>
          <h2>New ${esc(spec.recordType.replace(/_/g, ' '))}</h2>
          <form class="records-form" data-records-form>
            ${rows.join('')}
            <label class="field"><span>Initial status</span><select data-field="status">${statuses}</select></label>
            <label class="field checkbox"><input type="checkbox" data-field="humanConfirmed"> <span>I confirm this record as a human decision</span></label>
            <label class="field"><span>Reason / note (required for a decision status)</span><input data-field="confirmationNote" type="text" maxlength="500"></label>
            <div class="records-form-actions">
              <button class="btn" type="submit" data-records-create>Save record</button>
              <span class="pill" data-records-status role="status">${esc(lastNotice)}</span>
            </div>
          </form>
        </section>`;
    }

    function renderRows(spec) {
      if (!records.length) return '<tr><td colspan="6">No records saved for this project yet.</td></tr>';
      return records.map(record => {
        const options = (spec.statuses || []).filter(s => s !== record.status)
          .map(s => `<option value="${esc(s)}">${esc(s)}</option>`).join('');
        return `
          <tr data-record-row="${esc(record.id)}">
            <td><strong>${esc(record.title)}</strong><br><small>${esc(record.detail).slice(0, 160)}</small></td>
            <td><span class="pill">${esc(record.status)}</span></td>
            <td>${esc(record.ownerRole || '—')}</td>
            <td>${esc(record.dueOn || '—')}</td>
            <td>rev ${esc(record.revision)}</td>
            <td class="records-actions">
              <select data-record-target="${esc(record.id)}"><option value="">Set status…</option>${options}</select>
              <input data-record-reason="${esc(record.id)}" type="text" placeholder="Reason (required)" maxlength="500">
              <label class="checkbox"><input type="checkbox" data-record-confirm="${esc(record.id)}"> <span>confirmed</span></label>
              <button class="btn" data-record-apply="${esc(record.id)}">Apply</button>
              <button class="btn" data-record-history="${esc(record.id)}">History</button>
              <button class="btn" data-record-delete="${esc(record.id)}">Delete</button>
            </td>
          </tr>`;
      }).join('');
    }

    async function render(toolId) {
      if (disposed || !ownsRoute(toolId)) return false;
      const token = ++generation;
      const main = root.document.querySelector('#main');
      if (!main) return false;
      const spec = await specFor(toolId);
      if (disposed || !ownsRoute(toolId) || token !== generation) return false;
      if (!spec) {
        notice('This module has no record contract, so no form is offered.');
        return false;
      }
      // The store is project-scoped by design: a calculation or note is only meaningful
      // against a project. Say so plainly and offer no form that could not be saved, rather
      // than failing with an error toast.
      const state = context.getState();
      if (state.mode === 'standalone' || !/^[a-f0-9]{32}$/i.test(state.selectedProjectId || '')) {
        main.innerHTML = `
          <div class="records-workspace" data-records-tool="${esc(toolId)}" data-records-blocked="no-project">
            <header class="records-hero"><div>
              <h1>${esc(label(toolId.replace(/_/g, ' ')))}</h1>
              <p>No project selected</p>
            </div></header>
            <p class="pill" data-records-boundary>${esc(BOUNDARIES[toolId] || 'Internal records only.')}</p>
            <section class="panel">
              <h2>Select a project to continue</h2>
              <p>Records are stored against a project, so there is nothing to load and no form is offered until one is selected. Choose a project with the shared project selector, then reopen this module.</p>
            </section>
          </div>`;
        return false;
      }
      const loadedRecords = await adapter.list(toolId);
      if (disposed || !ownsRoute(toolId) || active !== toolId || token !== generation) return false;
      records = loadedRecords;
      // Until the user does something, the banner should describe what was actually loaded —
      // "Nothing saved yet" next to a table of saved records is simply wrong.
      if (!acted) lastNotice = records.length
        ? `${records.length} record(s) loaded from the database.`
        : 'Nothing saved yet.';
      const project = state.projects?.find(p => p.id === state.selectedProjectId)?.name || 'Selected project';
      main.innerHTML = `
        <div class="records-workspace" data-records-tool="${esc(toolId)}">
          <header class="records-hero">
            <div>
              <h1>${esc(label(toolId.replace(/_/g, ' ')))}</h1>
              <p>Project: <strong>${esc(project)}</strong> · ${records.length} saved record(s)</p>
            </div>
          </header>
          <p class="pill" data-records-boundary>${esc(BOUNDARIES[toolId] || 'Internal records only.')}</p>
          <p class="pill" data-records-message role="status">${esc(lastNotice)}</p>
          ${renderForm(spec)}
          <section class="panel">
            <h2>Saved records</h2>
            <table class="table">
              <thead><tr><th>Record</th><th>Status</th><th>Owner</th><th>Due</th><th>Version</th><th>Actions</th></tr></thead>
              <tbody>${renderRows(spec)}</tbody>
            </table>
          </section>
          <section class="panel" data-records-history hidden><h2>Status history</h2><div data-records-history-body></div></section>
        </div>`;
      bind(main, toolId, spec);
      return true;
    }

    function readFields(main) {
      const fields = {};
      main.querySelectorAll('[data-records-form] [data-field]').forEach(input => {
        const name = input.getAttribute('data-field');
        if (input.type === 'checkbox') { fields[name] = input.checked; return; }
        const value = String(input.value || '').trim();
        if (value === '' && !['status', 'confirmationNote'].includes(name)) return;
        fields[name] = value;
      });
      if (typeof fields.evidenceFileIds === 'string') {
        fields.evidenceFileIds = fields.evidenceFileIds.split(/[\s,]+/).filter(Boolean);
      }
      const spec = specs.modules[active];
      const payload = {};
      (spec.payloadFields || []).forEach(name => {
        if (name in fields && !['title', 'detail', 'ownerRole', 'dueOn', 'severity', 'reference', 'status', 'humanConfirmed', 'confirmationNote', 'evidenceFileIds'].includes(name)) {
          payload[name] = fields[name];
        }
      });
      const body = { title: fields.title, detail: fields.detail || '', status: fields.status, payload: payload };
      ['ownerRole', 'dueOn', 'severity', 'reference'].forEach(name => { if (fields[name]) body[name] = fields[name]; });
      if (Array.isArray(fields.evidenceFileIds)) body.evidenceFileIds = fields.evidenceFileIds;
      if (fields.humanConfirmed === true) body.humanConfirmed = true;
      if (fields.confirmationNote) body.confirmationNote = fields.confirmationNote;
      return body;
    }

    function bind(main, toolId, spec) {
      const form = main.querySelector('[data-records-form]');
      form?.addEventListener('submit', async event => {
        event.preventDefault();
        const button = form.querySelector('[data-records-create]');
        button.disabled = true;
        try {
          const body = readFields(main);
          if (!body.title) throw Error('A title is required.');
          await adapter.create(toolId, body);
          notice('Record saved and verified against the database.');
          await render(toolId);
        } catch (error) {
          notice(error.message + ' Nothing was saved.');
          button.disabled = false;
        }
      });

      main.querySelectorAll('[data-record-apply]').forEach(button => {
        button.addEventListener('click', async () => {
          const id = button.getAttribute('data-record-apply');
          const target = main.querySelector(`[data-record-target="${id}"]`)?.value || '';
          const reason = (main.querySelector(`[data-record-reason="${id}"]`)?.value || '').trim();
          const confirmed = main.querySelector(`[data-record-confirm="${id}"]`)?.checked === true;
          const record = records.find(item => item.id === id);
          if (!target) { notice('Choose the status to move to first.'); return; }
          if (!reason) { notice('A reason is required for a status change.'); return; }
          button.disabled = true;
          try {
            await adapter.transition(id, target, reason, confirmed, record.revision);
            notice('Status changed to "' + target + '" and verified.');
            await render(toolId);
          } catch (error) {
            notice(error.message + ' The status was not changed.');
            button.disabled = false;
          }
        });
      });

      main.querySelectorAll('[data-record-history]').forEach(button => {
        button.addEventListener('click', async () => {
          const id = button.getAttribute('data-record-history');
          const panel = main.querySelector('[data-records-history]');
          const body = main.querySelector('[data-records-history-body]');
          button.disabled = true;
          try {
            const events = await adapter.events(id);
            body.innerHTML = events.length
              ? `<ol>${events.map(event => `<li>${esc(event.fromStatus || '—')} → <b>${esc(event.toStatus)}</b> · ${esc(event.reason)} · ${esc(event.createdAt)}</li>`).join('')}</ol>`
              : '<p>No status changes recorded yet.</p>';
            panel.hidden = false;
          } catch (error) {
            notice(error.message + ' History unavailable.');
          } finally { button.disabled = false; }
        });
      });

      main.querySelectorAll('[data-record-delete]').forEach(button => {
        button.addEventListener('click', async () => {
          const id = button.getAttribute('data-record-delete');
          const record = records.find(item => item.id === id);
          if (!root.confirm?.(`Delete "${record.title}"? This removes the record permanently.`)) return;
          button.disabled = true;
          try {
            await adapter.remove(id, record.revision);
            notice('Record deleted and confirmed gone.');
            await render(toolId);
          } catch (error) {
            notice(error.message + ' The record was not deleted.');
            button.disabled = false;
          }
        });
      });
    }

    // A006 / A007 — the two scaffold controls the original could never save.
    async function bindScaffoldActions(main, toolId) {
      const notes = [...main.querySelectorAll('.page-head button')].find(button => button.textContent.trim() === 'Add implementation note');
      if (notes) {
        notes.removeAttribute('onclick');
        notes.disabled = false;
        notes.dataset.v1ScaffoldWrite = '';
        notes.title = 'Saves a real implementation note against this scaffold.';
        notes.onclick = async event => {
          event?.preventDefault();
          const note = root.prompt?.('Implementation note for this scaffold');
          if (!note?.trim()) return;
          notes.disabled = true;
          try {
            await adapter.create('scaffold_notes', {
              title: toolId + ' note', detail: note.trim(),
              payload: { scaffoldId: toolId, note: note.trim() },
            });
            notes.textContent = 'Implementation note saved';
            root.showToast?.('Implementation note saved and verified.');
          } catch (error) {
            root.showToast?.(error.message + ' The note was not saved.');
            notes.disabled = false;
          }
        };
      }
      const attach = [...main.querySelectorAll('.page-head button')].find(button => button.getAttribute?.('onclick')?.includes('attachProject(') || button.textContent.trim() === 'Attach project');
      if (attach) {
        const state = context.getState();
        const projectId = state.selectedProjectId;
        attach.removeAttribute('onclick');
        attach.onclick = null;
        if (!projectId) {
          attach.disabled = true;
          attach.title = 'Select an authorised project first — the attachment records which project this scaffold was attached to.';
          return;
        }
        const target = state.projects?.find(p => p.id === projectId);
        attach.disabled = false;
        attach.dataset.v1ScaffoldWrite = '';
        attach.title = 'Writes a real attachment record for ' + (target?.name || projectId) + '.';
        attach.onclick = async event => {
          event?.preventDefault();
          attach.disabled = true;
          try {
            await adapter.create('project_attachments', {
              title: toolId + ' attached to ' + (target?.name || projectId),
              detail: 'Scaffold attached to the selected project.',
              payload: { scaffoldId: toolId, attachmentRef: projectId },
            });
            attach.textContent = 'Attached to project';
            root.showToast?.('Attachment record saved and verified.');
          } catch (error) {
            root.showToast?.(error.message + ' The attachment was not saved.');
            attach.disabled = false;
          }
        };
      }
    }

    async function apply(toolId) {
      if (!RECORD_TOOLS.includes(toolId)) return { applied: false, reason: 'not-a-record-module' };
      active = toolId;
      const token = ++generation;
      try {
        const ok = await render(toolId);
        if (disposed || !ownsRoute(toolId)) return { applied: false, reason: 'stale' };
        return { applied: ok, tool: toolId };
      } catch (error) {
        notice(error.message + ' No records loaded.');
        return { applied: false, reason: 'error', message: error.message };
      }
    }

    const unsubscribe = typeof context.subscribe === 'function' ? context.subscribe(() => { if (active && !disposed) void render(active); }) : null;
    function dispose() { disposed = true; ++generation; active = null; if (typeof unsubscribe === 'function') unsubscribe(); }

    return { apply, bindScaffoldActions, dispose, supports: toolId => RECORD_TOOLS.includes(toolId), recordTools: RECORD_TOOLS };
  }
  root.V1CloneScaffoldRecords = { install, RECORD_TOOLS };
})(window);
