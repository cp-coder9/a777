(function (global) {
  'use strict';
  const text = (node, value) => { if (node && node.textContent !== String(value)) node.textContent = String(value); };
  const all = selector => [...document.querySelectorAll(selector)];
  const one = selector => document.querySelector(selector);
  const field = (value, fallback = 'Not recorded') => typeof value === 'string' && value.trim() ? value : fallback;

  function install({ context }) {
    if (!context?.getState || !context?.subscribe) throw Error('Shell data requires an authorised context.');
    let disposed = false, selectionGeneration = 0;
    const handlers = new Map();
    const view = () => typeof state === 'object' ? state : {};
    const snapshot = () => {
      const current = context.getState();
      const projects = current.ready && current.session && current.organisationId
        ? (current.projects || []).filter(p => p.id && (!p.organisationId || p.organisationId === current.organisationId)) : [];
      return { ...current, projects, selected: projects.find(p => p.id === current.selectedProjectId) };
    };
    const scopeKey = current => JSON.stringify([current.session?.userId, current.organisationId]);
    function bindSelect(select, current) {
      const entries = current.projects.length ? current.projects.map(p => [p.id, p.name]) : [['', current.ready ? 'No authorised projects' : 'Projects not loaded']];
      if (JSON.stringify([...select.options].map(o => [o.value, o.textContent])) !== JSON.stringify(entries)) {
        select.replaceChildren(...entries.map(([id, name]) => { const option = document.createElement('option'); option.value = id; option.textContent = name; return option; }));
      }
      select.value = current.selected?.id || '';
      select.disabled = !current.projects.length;
      if (handlers.has(select)) return;
      const previous = select.onchange;
      const handler = async () => {
        const start = snapshot(), token = ++selectionGeneration, id = select.value;
        if (disposed || !start.projects.some(p => p.id === id)) return;
        try { await context.selectProject(id); }
        catch {
          if (!disposed && token === selectionGeneration && scopeKey(snapshot()) === scopeKey(start) && select.isConnected) {
            global.showToast?.('Project selection was not changed. Please retry.');
          }
        } finally {
          if (!disposed && token === selectionGeneration && scopeKey(snapshot()) === scopeKey(start)) reconcile();
        }
      };
      select.onchange = handler;
      handlers.set(select, { previous, handler });
    }
    function reconcile() {
      if (disposed) return;
      const current = snapshot(), route = view(), record = current.selected;
      const name = field(record?.name, current.ready ? 'Select a project' : 'Projects not loaded');
      const email = field(current.session?.email, 'Identity not loaded');
      const membership = current.session?.memberships?.find(m => m.organisationId === current.organisationId);
      text(one('.rail-user .user-copy b'), email);
      text(one('.rail-user .user-copy span'), field(membership?.role, 'Role not loaded'));
      text(one('.rail-user .avatar'), current.session?.email ? current.session.email.slice(0, 2).toUpperCase() : '—');
      for (const [node] of handlers) if (!node.isConnected) handlers.delete(node);
      all('.project-select').forEach(select => bindSelect(select, current));
      for (const item of all('#globalMenu .os-item')) {
        const action = item.getAttribute('onclick') || '';
        if (/openGlobal\(['"]projects['"]\)/.test(action)) text(item.querySelector('.os-meta'), current.ready ? current.projects.length : 'Not loaded');
        if (/openGlobal\(['"]tools['"]\)/.test(action)) text(item.querySelector('.os-meta'), '47');
        if (/openGlobal\(['"](?:inbox|documents|finance)['"]\)/.test(action)) text(item.querySelector('.os-meta'), 'Not loaded');
      }
      if (route.mode === 'project') {
        text(one('#projectChip'), '● ' + name);
        text(one('#inspectSub'), name);
        // Preserve the breadcrumb's original bold node and hierarchy.
        const crumb = one('#breadcrumb');
        if (crumb && (route.global === 'projects' || route.tool)) {
          const lead = crumb.firstChild;
          if (lead?.nodeType === 3) lead.nodeValue = route.tool ? `Architex OS › Projects › ${name} › ` : 'Architex OS › Projects › ';
          if (!route.tool) text(crumb.querySelector('b'), name);
        }
        if (!route.tool) {
          text(one('#navigator .nav-title'), name);
          text(one('#navigator .nav-sub'), `${field(record?.municipality)} · Phase: ${field(record?.phase)}`);
          const mark = one('#navigator .nav-compact-mark'); if (mark) mark.title = name;
        }
      }
      // A mounted native RFI owns its records; the original registry still calls it a scaffold.
      // Only replace shell guidance once that actual workspace is present.
      if (current.ready && current.session && current.organisationId && record && route.mode === 'project' && route.tool === 'issues_rfis' && one('#main [data-rfi-tool]')) {
        text(one('#navigator .nav-note'), 'Review this project’s RFIs, responses and due dates. Creating, responding and closing require write permission and confirmation.');
        const body = one('#inspectBody');
        if (body && route.inspector === 'context') {
          if (!body.querySelector('[data-rfi-context]')) body.innerHTML = '<div class="inspect-note" data-rfi-context><b>Project RFI workspace</b><p>Review project questions and saved responses. The workspace shows the current loading and save status.</p></div><div class="inspect-block"><h4>Scope</h4><p data-rfi-scope></p></div><div class="inspect-block"><h4>Workflow boundary</h4><p>Create a question, record its response and close it with a reason. Write actions require authorised membership and explicit confirmation. These internal records do not constitute contractual notice or statutory approval.</p></div><div class="inspect-block"><h4>Feedback context</h4><p>Feedback includes this tool and the selected project.</p></div>';
          text(body.querySelector('[data-rfi-scope]'), name);
        } else if (body && route.inspector === 'activity') {
          if (!body.querySelector('[data-rfi-activity]')) body.innerHTML = '<div class="inspect-block" data-rfi-activity><h4>RFI activity</h4><p>Audit activity is not loaded in this inspector. Review recorded RFIs and responses in the workspace.</p></div>';
        }
      }
      // Other native module bodies and inspectors remain owned by their adapters.
      if (!route.tool) {
        all('#navigator .nav-row .badge').filter(n => /^\d+$/.test(n.textContent.trim()) && !n.parentElement.getAttribute('onclick')?.includes('openStandalone')).forEach(n => text(n, 'Not loaded'));
        if (route.global === 'projects') {
          text(one('#main .page-copy h1'), name);
          text(one('.project-hero h2'), name);
          text(one('.project-top p'), `${field(record?.location, 'Location not recorded')} · ${field(record?.clientName)} · ${field(record?.professionalName, 'Professional not recorded')}`);
          text(one('.project-top > .status'), 'Progress not loaded');
          const hero = one('.project-hero'); if (hero) hero.dataset.projectId = record?.id || '';
          all('.datum-card').forEach(card => {
            card.dataset.projectId = record?.id || '';
            allFrom(card, '.mini-row > span:first-child').forEach(n => text(n, 'Not loaded'));
          });
        }
        if (route.global === 'command') {
          const card = one('#main .scaffold-card[onclick="openProjectSpace()"]');
          if (card) { card.dataset.projectId = record?.id || ''; text(card.querySelector('p'), record ? `Enter ${name} and work through the project’s single line of truth.` : 'Select an authorised project to open its datum.'); }
        }
        if (route.global === 'inbox') all('#main .collab-card .mtg-badge').forEach(n => text(n, 'Not loaded'));
        if (['documents', 'finance'].includes(route.global)) text(one('#main .notice'), `${route.global === 'documents' ? 'Document records' : 'Finance and payment records'} are not loaded for this global scope.`);
        if (route.inspector === 'activity') {
          all('#inspectBody .activity-row').forEach(row => {
            text(row.querySelector('.activity-dot'), '—');
            text(row.querySelector('.activity-copy b'), 'Activity not loaded');
            text(row.querySelector('.activity-copy span'), 'No verified activity for this scope');
          });
        } else if (route.inspector === 'context' && route.mode === 'project') {
          all('#inspectBody .inspect-block').filter(block => block.querySelector('h4')?.textContent === 'Current scope').forEach(block => {
            const p = block.querySelector('p');
            if (!p) return;
            // Reuse the original line breaks; do not interpolate API values as HTML.
            const lines = [name, `Phase: ${field(record?.phase)} · Progress not loaded`, field(record?.municipality)];
            const nodes = [...p.childNodes].filter(n => n.nodeType === 3);
            if (nodes.length === 3) nodes.forEach((n, i) => { n.nodeValue = lines[i]; });
          });
        }
      }
    }
    const allFrom = (root, selector) => [...root.querySelectorAll(selector)];
    const unsubscribe = context.subscribe(reconcile);
    reconcile();
    return { reconcile, dispose() {
      if (disposed) return;
      disposed = true; selectionGeneration++; unsubscribe();
      for (const [node, { previous, handler }] of handlers) if (node.onchange === handler) node.onchange = previous;
      handlers.clear();
    } };
  }
  global.V1CloneShellData = { install };
})(window);
