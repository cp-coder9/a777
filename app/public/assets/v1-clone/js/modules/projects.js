(function (global) {
  'use strict';
  // Production new-project workflow for the V1 clone shell.
  // The original prototype has no create-project control; the user explicitly
  // requires working project creation and metadata editing. This module adds
  // them at the shell seam (project selector + dialog) using the shared
  // authorised context — no prototype fake success.
  const FIELDS = [
    ['projectCode', 'Project code', 'text', true, 'e.g. DAT-2026-01'],
    ['projectName', 'Project name', 'text', true, 'e.g. Camps Bay Residence'],
    ['clientName', 'Client name', 'text', true, 'Client or entity name'],
    ['municipality', 'Municipality', 'text', true, 'e.g. City of Cape Town'],
    ['propertyReference', 'Property reference', 'text', true, 'e.g. Erf 1234'],
    ['propertyUse', 'Property use', 'text', true, 'e.g. Residential'],
    ['landUseNotes', 'Land use notes', 'textarea', false, 'Zoning or consent notes'],
    ['leadProfessionalRole', 'Lead professional role', 'select', true, ''],
  ];
  const ROLES = ['architect', 'engineer', 'project_manager', 'quantity_surveyor', 'landscape_architect', 'town_planner'];
  let active = null;

  function install({ context, document: doc, window: win }) {
    if (!context?.getState || !context?.write) throw Error('Projects requires the shared authorised context.');
    doc = doc || global.document; win = win || global;
    const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
    const toast = message => { try { win.showToast?.(message); } catch { /* toast is best-effort feedback */ } };

    let dialogOrigin = null, ownedLayer = null, disposed = false;
    function captureOrigin(projectId = null) {
      const state = context.getState();
      return { userId: state.session?.userId, organisationId: state.organisationId,
        ready: state.ready === true, projectId, memberships: JSON.stringify(state.session?.memberships || []) };
    }
    function matches(origin) {
      const state = context.getState();
      return Boolean(!disposed && origin?.ready && (!origin.dialog || dialogOrigin === origin) && origin.userId && origin.organisationId && state.ready === true &&
        state.session?.userId === origin.userId && state.organisationId === origin.organisationId &&
        JSON.stringify(state.session?.memberships || []) === origin.memberships &&
        (!origin.projectId || state.selectedProjectId === origin.projectId));
    }
    function assertOrigin(origin) {
      if (!matches(origin) || context.getState().readOnly !== false) {
        if (dialogOrigin === origin) close(false);
        throw Error('Project scope is unavailable or changed. Reopen the dialog in an authorised session.');
      }
    }

    function currentProject() {
      const state = context.getState();
      return state.projects?.find(p => p.id === state.selectedProjectId) || null;
    }

    function values(form) {
      if (!form) throw Error('Open the project dialog before reading its values.');
      const out = {};
      for (const [key] of FIELDS) {
        const node = form.querySelector(`[data-field="${key}"]`);
        out[key] = String(node?.value ?? '').trim();
      }
      return out;
    }

    function validate(payload) {
      for (const [key, , , required] of FIELDS) {
        if (required && !payload[key]) return `Please complete ${key.replace(/([A-Z])/g, ' $1').toLowerCase()}.`;
      }
      return null;
    }

    async function create(payload, origin = dialogOrigin || captureOrigin()) {
      assertOrigin(origin);
      if (typeof payload !== 'object' || payload === null) payload = values(dialogForm());
      const invalid = validate(payload);
      if (invalid) { toast(invalid); throw Error(invalid); }
      let saved;
      try {
        saved = await context.write('/projects', payload, {
          projectScoped: false,
          readBackPath: savedRec => `/projects/${savedRec.projectId}`,
          readBackParentId: null,
        });
      } catch (error) {
        // Only a failed WRITE means creation failed. A 409 is specifically the
        // duplicate-code conflict; never infer it from loose message text on
        // other statuses (e.g. a 422 that happens to mention "exists").
        if (!matches(origin)) throw error;
        if (error?.status === 409) {
          toast(`Project code already exists. Choose another code. (${error.code || 'conflict'})`);
        } else if (error?.status === 422) {
          toast(`Validation failed: ${error.message}`);
        } else {
          toast(`Project could not be created: ${error.message}`);
        }
        throw error;
      }
      // Verify identity through the context read-back, then refresh + select.
      assertOrigin(origin);
      const id = saved?.projectId;
      if (!id) { toast('The server did not return the new project identity.'); throw Error('The server did not return the new project identity.'); }
      // The project IS created and read-back verified at this point. A refresh
      // or selection failure must not be reported as a creation failure, or a
      // retry would hit a confusing "code already exists" 409.
      try {
        if (typeof context.reloadProjects === 'function') await context.reloadProjects();
        assertOrigin(origin);
        await context.selectProject(id);
        assertOrigin(origin);
        close();
        toast(`Project created: ${payload.projectName}`);
      } catch (error) {
        if (!matches(origin)) throw error;
        close();
        toast(`Project created: ${payload.projectName}. The project list could not refresh: ${error.message} Reload the page to see the new project.`);
      }
      return saved;
    }

    async function edit(projectId, payload, expectedVersion, origin = dialogOrigin || captureOrigin(projectId)) {
      assertOrigin(origin);
      const state = context.getState();
      if (!projectId || state.selectedProjectId !== projectId) throw Error('Edit the currently selected authorised project.');
      const body = { ...payload, expectedVersion: Number(expectedVersion) };
      if (!Number.isInteger(body.expectedVersion) || body.expectedVersion < 1) throw Error('A current expectedVersion is required.');
      try {
        const saved = await context.write(`/projects/${projectId}/details`, body, {
          readBackPath: `/projects/${projectId}`,
        });
        assertOrigin(origin);
        if (typeof context.reloadProjects === 'function') await context.reloadProjects();
        assertOrigin(origin);
        close(); // a successful edit closes the dialog like create does
        toast('Project details saved.');
        return saved;
      } catch (error) {
        if (!matches(origin)) throw error;
        if (error?.status === 409) toast(/stale|version/i.test(error.code || '') ? 'The project changed while you were editing. Reload and retry with the current version.' : 'Project code already exists.');
        else if (error?.status === 422) toast(`Validation failed: ${error.message}`);
        else toast(`Project could not be saved: ${error.message}`);
        throw error;
      }
    }

    // ---- dialog DOM (only added on demand; removed on close) ----
    const DIALOG_ID = 'v1NewProjectDialog';
    function dialogForm() { return doc.querySelector(`#${DIALOG_ID} form`); }

    function renderDialog(mode = 'auto') {
      const selected = currentProject();
      const isEdit = mode === 'edit' || (mode === 'auto' && Boolean(selected));
      const project = isEdit ? selected : null; // create mode never prefills
      const origin = captureOrigin(isEdit ? project?.id : null);
      if (isEdit && !project) throw Error('Select an authorised project before editing.');
      assertOrigin(origin);
      origin.dialog = true;
      dialogOrigin = origin;
      const layers = doc.querySelectorAll(`#${DIALOG_ID}`);
      layers.forEach(n => n.remove());
      const layer = doc.createElement('div');
      layer.id = DIALOG_ID;
      ownedLayer = layer;
      layer.className = 'expressive-project-dialog';
      const opener = doc.activeElement;
      layer.style.cssText = 'position:fixed;inset:0;z-index:12000;display:flex;align-items:center;justify-content:center;background:rgba(16,32,51,.44);font:650 13px Inter,system-ui,sans-serif';
      layer.innerHTML = [
        `<div role="dialog" aria-modal="true" aria-label="${isEdit ? 'Edit project details' : 'Create a new project'}" style="width:min(560px,calc(100vw - 32px));max-height:min(680px,calc(100vh - 40px));overflow:auto;background:#fff;border-radius:14px;box-shadow:0 24px 60px rgba(1,12,20,.32);padding:22px 24px;color:#102033">`,
        `<h3 style="margin:0 0 4px;font-size:16px;font-weight:800;letter-spacing:-.2px">${isEdit ? 'Edit project details' : 'Create a new project'}</h3>`,
        `<p style="margin:0 0 16px;font-size:12px;color:#657287;font-weight:500">${isEdit ? `Changes save to <b>${esc(project.name)}</b> with version ${esc(String(project.version))}.` : 'The project is created in your authorised organisation and selected automatically.'}</p>`,
        '<form onsubmit="return false" style="display:grid;gap:11px">',
        FIELDS.map(([key, label, kind, required, hint]) => {
          if (kind === 'select') {
            const current = project?.[key === 'leadProfessionalRole' ? 'leadProfessionalRole' : key] || 'architect';
            return `<label style="display:grid;gap:5px;font-size:11.5px;color:#48586c;font-weight:650">${label}${required ? ' *' : ''}<select data-field="${key}" required="${required}" style="padding:8px 10px;border:1px solid rgba(16,32,51,.14);border-radius:9px;font:inherit;background:#fff;color:#102033">${ROLES.map(r => `<option value="${r}"${r === current ? ' selected' : ''}>${esc(r.replace(/_/g, ' '))}</option>`).join('')}</select></label>`;
          }
          const current = key === 'projectName' && project ? project.name : project?.[key] ?? '';
          return `<label style="display:grid;gap:5px;font-size:11.5px;color:#48586c;font-weight:650">${label}${required ? ' *' : ''}${kind === 'textarea'
            ? `<textarea data-field="${key}" rows="3" placeholder="${esc(hint)}" style="padding:8px 10px;border:1px solid rgba(16,32,51,.14);border-radius:9px;font:inherit;resize:vertical">${esc(current)}</textarea>`
            : `<input data-field="${key}" type="text" value="${esc(current)}" placeholder="${esc(hint)}" required="${required}" style="padding:8px 10px;border:1px solid rgba(16,32,51,.14);border-radius:9px;font:inherit">`}</label>`;
        }).join(''),
        `<div style="display:flex;gap:9px;justify-content:flex-end;margin-top:6px">`,
        `<button type="button" data-action="cancel" class="btn ghost" style="font-size:13.5px;padding:9px 14px;border-radius:10px">Cancel</button>`,
        `<button type="button" data-action="save" class="btn dark" style="font-size:13.5px;padding:9px 14px;border-radius:10px">${isEdit ? 'Save changes' : 'Create project'}</button>`,
        `</div></form></div>`,
      ].join('');
      doc.body.appendChild(layer);
      layer.restoreFocus = () => { if (opener?.isConnected) opener.focus(); };
      const form = dialogForm(), save = layer.querySelector('[data-action="save"]');
      save.type = 'submit';
      const status = doc.createElement('p');
      status.setAttribute('role', 'status'); status.setAttribute('aria-live', 'polite');
      form.append(status);
      let saving = false;
      form.onsubmit = async event => {
        event.preventDefault();
        if (saving || !form.reportValidity()) return;
        try { assertOrigin(origin); } catch { return; }
        saving = true; save.disabled = true; form.setAttribute('aria-busy', 'true');
        status.textContent = 'Saving project — awaiting server readback…';
        try {
          if (isEdit) await edit(project.id, values(form), project.version, origin);
          else await create(values(form), origin);
        } catch (error) { if (dialogOrigin !== origin || !matches(origin)) return; status.textContent = error.message || 'Project save failed. Inputs retained.'; }
        finally { if (dialogOrigin === origin && matches(origin)) { saving = false; save.disabled = false; form.removeAttribute('aria-busy'); } }
      };
      layer.addEventListener('click', event => {
        if (!matches(origin)) return;
        const action = event.target?.getAttribute?.('data-action');
        if (!saving && (action === 'cancel' || event.target === layer)) close();
      });
      layer.addEventListener('keydown', event => {
        if (!matches(origin)) return;
        if (event.key === 'Escape') { event.preventDefault(); if (!saving) close(); return; }
        if (event.key !== 'Tab') return;
        const controls = [...layer.querySelectorAll('input,select,textarea,button')].filter(e => !e.disabled && !e.hidden);
        const first = controls[0], last = controls[controls.length - 1];
        if (event.shiftKey && doc.activeElement === first) { event.preventDefault(); last?.focus(); }
        else if (!event.shiftKey && doc.activeElement === last) { event.preventDefault(); first?.focus(); }
      });
      form.querySelector('input')?.focus();
      return layer;
    }

    function open(mode = 'auto') { renderDialog(mode); return true; }
    function openCreate() { return open('create'); }
    function openEdit() { return open('edit'); }
    function close(restoreFocus = true) {
      dialogOrigin = null;
      const layer = ownedLayer;
      ownedLayer = null;
      if (layer) {
        const connected = layer.isConnected;
        layer.remove();
        if (restoreFocus && !disposed && connected) layer.restoreFocus?.();
      }
    }
    function isOpen() { return !disposed && Boolean(ownedLayer?.isConnected); }

    // Extend every original .project-select with "New project…" and
    // "Edit current project…" entries. The shell-data module rewrites select
    // options on every context reconcile; it preserves each <select> node but
    // replaces its children, so decoration must be idempotent AND re-append the
    // options when they are missing.
    const ENTRIES = [
      ['__v1_edit_project__', 'Edit current project…', 'edit'],
      ['__v1_new_project__', 'New project…', 'create'],
    ];
    const ownedSelectors = new Map();
    function decorateSelector(select) {
      if (!select) return;
      select.dataset.v1NewProject = '1';
      for (const [value, label] of ENTRIES) {
        if (![...select.options].some(option => option.value === value)) {
          const option = doc.createElement('option');
          option.value = value; option.textContent = label;
          select.appendChild(option);
        }
      }
      if (select.dataset.v1NewProjectHandler === '1') return;
      select.dataset.v1NewProjectHandler = '1';
      const previous = select.onchange;
      select.onchange = async event => {
        if (disposed) return;
        const entry = ENTRIES.find(([value]) => value === select.value);
        if (entry) {
          const state = context.getState();
          const keep = state.selectedProjectId || state.projects?.[0]?.id || '';
          select.value = keep; // visually restore before the dialog
          // Edit requires a selected project; if none, fall back to create.
          open(entry[2] === 'edit' && keep ? 'edit' : 'create');
          return;
        }
        if (typeof previous === 'function') return previous.call(select, event);
      };
      ownedSelectors.set(select, { previous, installed: select.onchange });
    }

    const unsubscribe = context.subscribe?.(() => {
      if (dialogOrigin && (!matches(dialogOrigin) || context.getState().readOnly !== false)) close(false);
    });
    const controller = { open, openCreate, openEdit, close, isOpen, create, edit, values, validate };
    active = { context, controller };

    // Decorate selectors once the DOM is ready; re-check on each reconcile hook.
    const decorateAll = () => { if (disposed) return; try { doc.querySelectorAll('.project-select').forEach(decorateSelector); } catch { /* not ready */ } };
    if (doc.readyState !== 'loading') decorateAll();
    else doc.addEventListener?.('DOMContentLoaded', decorateAll);
    // Retry decoration as the original re-renders navigators.
    const interval = win.setInterval?.(decorateAll, 1500);
    // The decoration interval must survive dialog close; the shell re-renders
    // selectors continuously. Only module teardown clears it.
    controller.dispose = () => {
      if (disposed) return;
      disposed = true;
      close(false);
      if (interval != null && win.clearInterval) win.clearInterval(interval);
      doc.removeEventListener?.('DOMContentLoaded', decorateAll);
      for (const [select, { previous, installed }] of ownedSelectors) {
        if (select.onchange !== installed) continue;
        select.onchange = previous;
        select.removeAttribute('data-v1-new-project-handler');
      }
      ownedSelectors.clear();
      if (typeof unsubscribe === 'function') unsubscribe();
      if (active?.controller === controller) active = null;
      // In-flight server writes are not aborted; only this controller's UI
      // ownership and continuation permissions are revoked.
    };

    return controller;
  }

  if (!global.V1CloneProjects) global.V1CloneProjects = { install, get controller() { return active?.controller || null; } };
})(window);
