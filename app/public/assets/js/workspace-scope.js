(function (window) {
  'use strict';
  const instances = new WeakMap();

  /**
   * Controlled component: callbacks own shell state and API authorisation.
   * Call mount with a fresh snapshot after every navigator render. No main-render
   * wrappers, network requests or record mutations are installed here.
   * config: navigator (element), formRoot (stable workspace element), mode, tool,
   * projects [{id,name}], selectedProjectId, changeMode(mode, context),
   * selectProject(id), backToProject(context), backToLibrary(context).
   * Optional canNavigate(intent) must resolve true to proceed; it replaces the
   * built-in dirty confirmation. Call markClean only after successful saves.
   */
  function mount(config) {
    const nav = config.navigator;
    if (!nav) return null;
    const existing = instances.get(nav);
    if (existing) { existing.update(config); return existing; }
    const document = nav.ownerDocument;
    const root = document.createElement('section');
    root.className = 'workspace-scope';
    root.setAttribute('aria-label', 'Workspace scope');
    let dirty = false;
    let pending = false;
    let generation = 0;
    let destroyed = false;
    const status = document.createElement('p');
    status.className = 'workspace-scope-status';
    status.setAttribute('role', 'status');
    let listeners = [];
    function listen(node, type, handler) {
      node.addEventListener(type, handler);
      listeners.push(() => node.removeEventListener(type, handler));
    }
    function cleanup() { listeners.forEach(remove => remove()); listeners = []; }
    function context() { return { mode: config.mode, tool: config.tool, selectedProjectId: config.selectedProjectId }; }
    async function navigate(action, callback, destination = {}) {
      if (!callback || pending || destroyed) return false;
      pending = true;
      status.textContent = '';
      const revision = generation;
      try {
        if (config.canNavigate) {
          if (await config.canNavigate({ action, dirty, ...context(), ...destination }) !== true) return false;
        } else if (dirty && !await (config.confirm || window.confirm)('You have unsaved changes. Leave this workspace and discard them?')) return false;
        if (destroyed || revision !== generation) return false;
        if (await callback() === false) return false;
        dirty = false;
        return true;
      } catch (error) {
        if (!destroyed && revision === generation) status.textContent = error?.message || 'Navigation failed. Your context has not been changed by this control.';
        return false;
      } finally { pending = false; }
    }
    function element(tag, text) {
      const node = document.createElement(tag);
      node.textContent = text;
      return node;
    }
    function render() {
      cleanup();
      root.replaceChildren();
      const modes = element('div', '');
      modes.className = 'workspace-scope-modes';
      for (const mode of ['project', 'standalone']) {
        const button = element('button', mode === 'project' ? 'Project' : 'Standalone');
        button.type = 'button';
        button.setAttribute('aria-pressed', config.mode === mode);
        button.disabled = !config.changeMode;
        listen(button, 'click', () => mode !== config.mode && navigate('changeMode', config.changeMode && (() => config.changeMode(mode, context())), { targetMode: mode }));
        modes.append(button);
      }
      root.append(modes);
      const projects = config.projects || [];
      const current = projects.find(project => project.id === config.selectedProjectId);
      const label = element('label', 'Project for project-required records');
      const select = element('select', '');
      const placeholder = element('option', projects.length ? 'Select a project' : 'No authorised projects available');
      placeholder.value = '';
      select.append(placeholder);
      for (const project of projects) {
        const option = element('option', project.name);
        option.value = project.id;
        select.append(option);
      }
      select.value = current?.id || '';
      select.disabled = !projects.length || !config.selectProject;
      listen(select, 'change', async () => {
        const selected = projects.find(project => project.id === select.value);
        if (!selected) { select.value = current?.id || ''; return; }
        if (selected.id === config.selectedProjectId) return;
        if (!await navigate('selectProject', config.selectProject && (() => config.selectProject(selected.id)), { targetProjectId: selected.id })) select.value = current?.id || '';
      });
      label.append(select);
      root.append(label, element('p', `${config.mode === 'standalone' ? 'Standalone tool' : 'Project workspace'} · ${current ? current.name : 'No project selected'}. Project-required saves need an explicitly selected authorised project. Changing orientation or project does not reassign existing records.`));
      const backAction = config.mode === 'standalone' ? 'backToLibrary' : 'backToProject';
      const back = element('button', config.mode === 'standalone' ? 'Back to tool library' : 'Back to project');
      back.type = 'button';
      back.disabled = !config[backAction] || (backAction === 'backToProject' && !current);
      listen(back, 'click', () => !back.disabled && navigate(backAction, config[backAction] && (() => config[backAction](context()))));
      root.append(back, status);
      if (config.formRoot) {
        const track = event => {
          if (root.contains(event.target)) return;
          if (!['INPUT', 'SELECT', 'TEXTAREA'].includes(event.target?.tagName)) return;
          let parent = event.target.parentNode;
          while (parent && parent !== config.formRoot) {
            if (parent.tagName === 'FORM') { dirty = true; return; }
            parent = parent.parentNode;
          }
          if (parent?.tagName === 'FORM') dirty = true;
        };
        listen(config.formRoot, 'input', track);
        listen(config.formRoot, 'change', track);
      }
      if (root.parentNode !== nav) nav.prepend(root);
    }
    const control = {
      root,
      setDirty(value = true) { dirty = Boolean(value); },
      markClean() { dirty = false; },
      update(next) { if (destroyed) return; generation++; config = { ...config, ...next }; render(); },
      destroy() { destroyed = true; generation++; cleanup(); root.remove(); instances.delete(nav); }
    };
    instances.set(nav, control);
    render();
    return control;
  }
  window.ArchitexWorkspaceScope = { mount };
})(window);
