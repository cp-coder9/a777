(function () {
  'use strict';

  const manifestUrl = '/assets/route-placement.json';
  const familyClasses = ['orient', 'find', 'create', 'review', 'administer'].map(name => `fp-page-family--${name}`);
  const destinationRoutes = Object.freeze({
    'command-centre': 'command',
    projects: 'projects',
    'workspace-tools': 'tools',
    collaboration: 'inbox',
    'knowledge-cpd': 'cpd',
    'admin-settings': 'settings',
  });
  const destinationIcons = Object.freeze({
    'command-centre': 'dashboard',
    projects: 'projects',
    'workspace-tools': 'tools',
    collaboration: 'inbox',
    'knowledge-cpd': 'knowledge',
    'admin-settings': 'settings',
  });
  let placement = null;

  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  })[character]);

  function currentPlacement() {
    if (!placement || typeof state === 'undefined') return null;
    if (state.tool) return placement.tools.find(item => item.id === state.tool) || null;
    return placement.globalRoutes.find(item => item.id === state.global) || null;
  }

  function activeDestination() {
    return currentPlacement()?.destination || null;
  }

  function normalisedStage() {
    if (typeof project === 'undefined') return null;
    const value = String(project.stage || '').toLowerCase().replaceAll('_', '-').replaceAll(' ', '-');
    if (value.includes('close') || value.includes('handover')) return 'close-out';
    return placement?.stages.some(stage => stage.id === value) ? value : null;
  }

  window.flightpathOpenDestination = function flightpathOpenDestination(destination) {
    const route = destinationRoutes[destination];
    if (!route || typeof window.openGlobal !== 'function') return;
    window.openGlobal(route);
  };

  function renderGlobalDestinations() {
    const menu = document.getElementById('globalMenu');
    if (!menu || !placement) return;
    const active = activeDestination();
    menu.innerHTML = placement.globalDestinations.map(destination => {
      const selected = destination.id === active;
      const iconMarkup = typeof icon === 'function' ? icon(destinationIcons[destination.id] || 'document', 24) : '';
      return `<button class="os-item fp-global-destination ${selected ? 'active' : ''}" type="button" data-destination="${escapeHtml(destination.id)}" data-flightpath-focus="destination-${escapeHtml(destination.id)}" aria-label="${escapeHtml(destination.label)}" ${selected ? 'aria-current="page"' : ''}><span class="icon-wrap">${iconMarkup}</span><span class="os-label">${escapeHtml(destination.label)}</span><span class="os-meta">${String(destination.order / 10).padStart(2, '0')}</span></button>`;
    }).join('');
    menu.querySelectorAll('[data-destination]').forEach(button => button.addEventListener('click', () => {
      window.flightpathOpenDestination(button.dataset.destination);
    }));
  }

  function renderLifecycleNavigation() {
    const navigator = document.getElementById('navigator');
    if (!navigator || !placement || typeof state === 'undefined' || state.mode !== 'project') return;
    const existing = navigator.querySelector('#flightpathLifecycle');
    if (existing) existing.remove();
    const current = normalisedStage();
    const section = document.createElement('section');
    section.id = 'flightpathLifecycle';
    section.className = 'fp-lifecycle-shell';
    section.innerHTML = `<div class="fp-lifecycle-title"><span>Datum Flightpath</span><small>Project workflow</small></div><nav class="fp-lifecycle-nav" aria-label="Datum Flightpath">${placement.stages.map(stage => `<button type="button" data-flightpath-stage="${escapeHtml(stage.id)}" data-flightpath-focus="stage-${escapeHtml(stage.id)}" ${stage.id === current ? 'aria-current="step"' : ''}><i>${stage.order / 10}</i><span>${escapeHtml(stage.label)}</span></button>`).join('')}</nav>`;
    section.querySelectorAll('[data-flightpath-stage]').forEach(button => button.addEventListener('click', () => {
      const stage = placement.stages.find(item => item.id === button.dataset.flightpathStage);
      if (stage && typeof window.selectProjectStage === 'function') window.selectProjectStage(stage.label);
    }));
    navigator.prepend(section);
  }

  function applyPageFamily() {
    const main = document.getElementById('main');
    if (!main) return;
    main.classList.remove(...familyClasses);
    const family = currentPlacement()?.family || 'orient';
    main.classList.add(`fp-page-family--${family}`);
    main.dataset.pageFamily = family;
    document.documentElement.dataset.flightpathDestination = activeDestination() || 'unplaced';
  }

  function enhanceShell() {
    if (!placement) return;
    renderGlobalDestinations();
    renderLifecycleNavigation();
    applyPageFamily();
    document.documentElement.dataset.flightpathPlacement = 'ready';
  }

  const previousRenderAll = window.renderAll;
  window.renderAll = function flightpathRenderAll() {
    const focusKey = document.activeElement?.dataset?.flightpathFocus || null;
    previousRenderAll();
    enhanceShell();
    if (focusKey) requestAnimationFrame(() => document.querySelector(`[data-flightpath-focus="${CSS.escape(focusKey)}"]`)?.focus());
  };

  fetch(manifestUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
    .then(response => {
      if (!response.ok) throw new Error(`route placement returned ${response.status}`);
      return response.json();
    })
    .then(manifest => {
      if (!Array.isArray(manifest.stages) || !Array.isArray(manifest.globalDestinations) || !Array.isArray(manifest.globalRoutes) || !Array.isArray(manifest.tools)) {
        throw new Error('route placement is invalid');
      }
      placement = manifest;
      window.renderAll();
    })
    .catch(error => {
      document.documentElement.dataset.flightpathPlacement = 'flightpath-placement-unavailable';
      console.warn('Datum Flightpath placement unavailable; existing navigation remains available.', error);
    });
})();
