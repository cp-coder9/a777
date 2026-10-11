(function (global) {
  'use strict';
  const text = (node, value) => { if (node && node.textContent !== String(value)) node.textContent = String(value); };
  const all = selector => [...document.querySelectorAll(selector)];
  const one = selector => document.querySelector(selector);
  const field = (value, fallback = 'Not recorded') => typeof value === 'string' && value.trim() ? value : fallback;

  // Guidance mirrors WingmanRoleTutorials; workspace IDs come only from discovery.
  const priorities = Object.freeze({"architect": {"label": "Architect", "priority": "Brief & appointment"}, "client": {"label": "Client / Property Owner", "priority": "Brief"}, "bep": {"label": "Built Environment Professional", "priority": "Appointment"}, "engineer": {"label": "Engineer", "priority": "Brief & appointment"}, "quantity_surveyor": {"label": "Quantity Surveyor", "priority": "Cost planning"}, "town_planner": {"label": "Town Planner", "priority": "Property & zoning enquiry"}, "land_surveyor": {"label": "Land Surveyor", "priority": "Appointment"}, "energy_professional": {"label": "Energy Professional", "priority": "Appointment"}, "fire_engineer": {"label": "Fire Engineer", "priority": "Appointment & scope"}, "cpm": {"label": "Construction Project Manager", "priority": "Establish appointments & responsibilities"}, "contractor": {"label": "Principal Contractor", "priority": "Award & mobilisation"}, "subcontractor": {"label": "Trade Subcontractor", "priority": "Package award"}, "supplier": {"label": "Material Supplier", "priority": "Catalogue & product data"}, "site_manager": {"label": "Site Manager / Clerk of Works", "priority": "Site establishment"}, "health_safety": {"label": "Health & Safety Officer", "priority": "Safety file & appointment"}, "developer": {"label": "Property Developer", "priority": "Site & property"}, "freelancer": {"label": "Specialist Freelancer", "priority": "Task/package assignment"}, "firm_admin": {"label": "Practice Administrator", "priority": "Organisation setup"}, "organisation_admin": {"label": "Organisation Administrator", "priority": "Access & roles"}, "admin": {"label": "Practice Operations Admin", "priority": "Operations setup"}, "platform_admin": {"label": "Platform Administrator", "priority": "Platform health"}});
  function dashboardModel(current, discovery) {
    const member = current.session?.memberships?.find(m => m.organisationId === current.organisationId);
    const valid = current.ready && ['owner','admin','member','viewer'].includes(member?.role);
    const privileged = ['owner','admin'].includes(member?.role);
    const profile = Object.hasOwn(priorities, member?.profileRole) ? priorities[member.profileRole] : null;
    const safeProfile = valid && profile && (privileged || !/admin/.test(member?.profileRole));
    const tools = valid && discovery ? discovery.getCatalogue().filter(t => t.recommended === true && discovery.allowed(t.id)) : [];
    return { label: safeProfile ? profile.label : valid ? 'Project workspace' : 'Membership unavailable',
      priority: safeProfile ? profile.priority : 'Review the authorised project scope', tools,
      available: Boolean(valid && discovery), membership: valid ? member.role : 'Unavailable' };
  }

  function install({ context }) {
    if (!context?.getState || !context?.subscribe) throw Error('Shell data requires an authorised context.');
    let disposed = false, selectionGeneration = 0;
    const handlers = new Map();
    const view = () => typeof state === 'object' ? state : {};
    const snapshot = () => {
      const current = context.getState();
           const membership = current.session?.memberships?.find(m => m.organisationId === current.organisationId);
           const projects = current.ready && current.session && current.organisationId && ['owner','admin','member','viewer'].includes(membership?.role)
        ? (current.projects || []).filter(p => p.id && (!p.organisationId || p.organisationId === current.organisationId)) : [];
      return { ...current, projects, selected: projects.find(p => p.id === current.selectedProjectId) };
    };
    const scopeKey = current => JSON.stringify([current.session?.userId, current.organisationId, current.session?.memberships, current.ready, current.mode, current.selectedProjectId]);
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
    let dashboardSignature = '';
    function renderDashboard(current) {
      const main = one('#main'); if (!main) return;
      const discovery = global.V1CloneRuntime?.controllers?.discovery;
      const model = dashboardModel(current, discovery);
      const capturedScope = scopeKey(current);
      const signature = JSON.stringify([capturedScope,current.projects,model]);
      if (main.querySelector('[data-role-dashboard]') && signature === dashboardSignature) return;
      dashboardSignature = signature;
      const section = document.createElement('section'); section.className = 'role-dashboard'; section.dataset.roleDashboard = '';
      const add = (tag, value, parent = section) => { const node = document.createElement(tag); node.textContent = value; parent.append(node); return node; };
      add('h1', 'Command Centre'); add('h2', model.label);
      add('p', 'Guidance priority: ' + model.priority + '. Saved profile guidance does not grant authority.');
      add('p', 'Membership: ' + model.membership);
      const project = current.selected;
      add('h2', 'Authorised project');
      add('p', project ? field(project.name) : current.ready ? 'No authorised project selected' : 'Projects not loaded');
      if (project) add('p', 'Phase: ' + field(project.phase) + ' · Municipality: ' + field(project.municipality));

      const button = (label, action, parent = section) => {
        const node = add('button', label, parent); node.type = 'button'; node.className = 'btn';
        node.onclick = () => { const now = snapshot(); if (disposed || !now.ready || !dashboardModel(now, discovery).available || !node.isConnected || capturedScope !== scopeKey(now)) return false; return action(now); }; return node;
      };
      const primary = model.tools.find(t => discovery?.allowed(t.id));
      add('h2', 'Primary available workspace');
      if (primary) button('Open ' + field(primary.name, primary.id), now => discovery.allowed(primary.id) ? global.openTool?.(primary.id, now.mode) : false);
      else add('p', 'No recommended workspace is available in this scope. Discovery may be unavailable or no recommendation is authorised.');
      const groups = new Map(); for (const tool of model.tools) { const stage = field(tool.stage, 'Other workspaces'); if (!groups.has(stage)) groups.set(stage, []); groups.get(stage).push(tool); }
      add('h2', 'Recommended tool groups');
      for (const [stage, tools] of groups) { const group = add('div', ''); group.className = 'role-dashboard-group'; add('h3', stage, group); for (const tool of tools) button(field(tool.name, tool.id), now => discovery.allowed(tool.id) ? global.openTool?.(tool.id, now.mode) : false, group); }
      if (!groups.size) add('p', 'No authorised recommendations loaded.');
      add('h2', 'Attention'); add('p', 'Unavailable. Attention records are not loaded. No task, notification, payment or completion totals are inferred.');
      button('Open native inbox', () => global.openGlobal?.('inbox'));
      main.replaceChildren(section);
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
        if (/openGlobal\(['"]tools['"]\)/.test(action)) {const discovery=global.V1CloneRuntime?.controllers?.discovery;text(item.querySelector('.os-meta'),discovery?discovery.getCatalogue().filter(t=>discovery.allowed(t.id)).length:'Unavailable');}
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
          renderDashboard(current);
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
  global.V1CloneShellData = { install, dashboardModel, priorities };
})(window);
