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

    async function create(payload) {
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
      const id = saved?.projectId;
      if (!id) { toast('The server did not return the new project identity.'); throw Error('The server did not return the new project identity.'); }
      // The project IS created and read-back verified at this point. A refresh
      // or selection failure must not be reported as a creation failure, or a
      // retry would hit a confusing "code already exists" 409.
      try {
        if (typeof context.reloadProjects === 'function') await context.reloadProjects();
        await context.selectProject(id);
        close();
        toast(`Project created: ${payload.projectName}`);
      } catch (error) {
        close();
        toast(`Project created: ${payload.projectName}. The project list could not refresh: ${error.message} Reload the page to see the new project.`);
      }
      return saved;
    }

    async function edit(projectId, payload, expectedVersion) {
      const state = context.getState();
      if (!projectId || state.selectedProjectId !== projectId) throw Error('Edit the currently selected authorised project.');
      const body = { ...payload, expectedVersion: Number(expectedVersion) };
      if (!Number.isInteger(body.expectedVersion) || body.expectedVersion < 1) throw Error('A current expectedVersion is required.');
      try {
        const saved = await context.write(`/projects/${projectId}/details`, body, {
          readBackPath: `/projects/${projectId}`,
        });
        if (typeof context.reloadProjects === 'function') await context.reloadProjects();
        close(); // a successful edit closes the dialog like create does
        toast('Project details saved.');
        return saved;
      } catch (error) {
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
      const layers = doc.querySelectorAll(`#${DIALOG_ID}`);
      layers.forEach(n => n.remove());
      const layer = doc.createElement('div');
      layer.id = DIALOG_ID;
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
      layer.addEventListener('click', event => {
        const action = event.target?.getAttribute?.('data-action');
        if (action === 'cancel') { close(); }
        else if (action === 'save') {
          const form = dialogForm();
          if (isEdit) { edit(project.id, values(form), project.version).catch(() => {}); }
          else { create(values(form)).catch(() => {}); }
        } else if (event.target === layer) close();
      });
      return layer;
    }

    function open(mode = 'auto') { renderDialog(mode); return true; }
    function openCreate() { return open('create'); }
    function openEdit() { return open('edit'); }
    function close() { doc.querySelectorAll(`#${DIALOG_ID}`).forEach(n => n.remove()); }
    function isOpen() { return Boolean(doc.querySelector(`#${DIALOG_ID}`)); }

    // Extend every original .project-select with "New project…" and
    // "Edit current project…" entries. The shell-data module rewrites select
    // options on every context reconcile; it preserves each <select> node but
    // replaces its children, so decoration must be idempotent AND re-append the
    // options when they are missing.
    const ENTRIES = [
      ['__v1_edit_project__', 'Edit current project…', 'edit'],
      ['__v1_new_project__', 'New project…', 'create'],
    ];
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
        const entry = ENTRIES.find(([value]) => value === select.value);
        if (entry) {
          const state = context.getState();
          const keep = state.selectedProjectId || state.projects?.[0]?.id || '';
          select.value = keep; // visually restore before the dialog
          // Edit requires a selected project; if none, fall back to create.
          open(entry[2] === 'edit' && keep ? 'edit' : 'create');
          return;
        }
        if (typeof previous === 'function') return previous(event);
      };
    }

    const controller = { open, openCreate, openEdit, close, isOpen, create, edit, values, validate };
    active = { context, controller };

    // Decorate selectors once the DOM is ready; re-check on each reconcile hook.
    const decorateAll = () => { try { doc.querySelectorAll('.project-select').forEach(decorateSelector); } catch { /* not ready */ } };
    if (doc.readyState !== 'loading') decorateAll();
    else doc.addEventListener?.('DOMContentLoaded', decorateAll);
    // Retry decoration as the original re-renders navigators.
    const interval = win.setInterval?.(decorateAll, 1500);
    // The decoration interval must survive dialog close; the shell re-renders
    // selectors continuously. Only module teardown clears it.
    controller.dispose = () => { if (interval && win.clearInterval) win.clearInterval(interval); };

    return controller;
  }

  if (!global.V1CloneProjects) global.V1CloneProjects = { install, get controller() { return active?.controller || null; } };
})(window);
