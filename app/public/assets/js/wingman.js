(function () {
  'use strict';

  const api = new window.ArchitexApi.ApiClient();
  let root = null;
  let conversationId = null;
  let scopeKey = null;
  let dismissedRoute = null;
  let bootstrapRoute = null;
  let tutorial = null;
  let tutorialScope = null;
  let tutorialBusy = false;
  let tutorialVisible = false;

  function shellScope() {
    const shell = window.__ARCHITEX_SHELL_STATE__;
    const membership = shell?.session?.memberships?.[0];
    if (!shell?.session || !membership) return null;
    const workspaceRoute = typeof state !== 'undefined' ? (state.tool || state.global || 'command') : 'command';
    const projectId = typeof state !== 'undefined' && state.mode === 'project' ? shell.selectedProjectId || null : null;
    // Keep general guidance available to every signed-in profile without
    // granting tool permissions or inventing an authorised project context.
    const route = !projectId || membership.role === 'viewer' ? 'command' : workspaceRoute;
    const selectedRecordId = document.querySelector('#main [data-record-id][aria-selected="true"]')?.dataset?.recordId || null;
    return { shell, membership, route, projectId, selectedRecordId };
  }

  function configureApi() {
    const scope = shellScope();
    if (!scope) return null;
    const nextKey = `${scope.shell.session.userId}:${scope.membership.organisationId}`;
    if (scopeKey !== nextKey) {
      scopeKey = nextKey;
      conversationId = null;
      bootstrapRoute = null;
      tutorial = null;
      tutorialScope = null;
      tutorialVisible = false;
      tutorialBusy = false;
      renderTutorial();
      clearMessages();
    }
    api.setOrganisation(scope.membership.organisationId);
    api.setCsrf(scope.shell.session.csrfToken);
    return scope;
  }

  function setState(next) {
    if (!root) return;
    root.setAttribute('data-wingman-state', next);
    root.querySelector('.wingman-bird')?.setAttribute('aria-expanded', String(next === 'expanded'));
  }

  function mount() {
    if (document.getElementById('wingmanAssistant')) {
      root = document.getElementById('wingmanAssistant');
      return;
    }
    root = document.createElement('aside');
    root.id = 'wingmanAssistant';
    root.className = 'wingman-assistant';
    root.setAttribute('data-wingman-state', 'quiet');
    root.setAttribute('role', 'complementary');
    root.setAttribute('aria-label', 'Architex guided Wingman');
    root.innerHTML = `<button class="wingman-bird" type="button" aria-label="Open guided Wingman" aria-expanded="false"><img src="/assets/img/wingman-bird.svg" alt=""></button><div class="wingman-suggestion"><span>Ask Wingman about this workspace.</span><button type="button">Ask</button></div><section class="wingman-panel" aria-label="Guided Wingman panel"><header class="wingman-head"><img src="/assets/img/wingman-bird.svg" alt=""><div><strong>Wingman</strong><span>Guided copilot · Nemotron 3.5 Lightning</span></div><button class="wingman-close" type="button" aria-label="Close Wingman">×</button></header><div class="wingman-context">Waiting for an authenticated workspace</div><div class="wingman-messages" aria-live="polite"></div><form class="wingman-compose"><textarea name="prompt" maxlength="4000" aria-label="Ask Wingman" placeholder="Ask what to review or where to go next…" required></textarea><button type="submit">Send</button><span role="status"></span></form></section>`;
    document.body.appendChild(root);
    const tutorialButton = document.createElement('button');
    tutorialButton.type = 'button';
    tutorialButton.className = 'wingman-tour-toggle';
    tutorialButton.textContent = 'Tutorial';
    tutorialButton.setAttribute('aria-label', 'Open or replay your role tutorial');
    root.querySelector('.wingman-close').before(tutorialButton);
    tutorialButton.addEventListener('click', async () => {
      tutorialVisible = true;
      if (!tutorial) await loadTutorial(true);
      renderTutorial();
    });
    const tutorialPanel = document.createElement('section');
    tutorialPanel.className = 'wingman-tour';
    tutorialPanel.setAttribute('aria-label', 'Your role tutorial');
    tutorialPanel.hidden = true;
    root.querySelector('.wingman-context').after(tutorialPanel);
    root.querySelector('.wingman-bird').addEventListener('click', open);
    root.querySelector('.wingman-suggestion button').addEventListener('click', open);
    root.querySelector('.wingman-close').addEventListener('click', close);
    root.querySelector('.wingman-compose').addEventListener('submit', sendMessage);
    renderWelcome();
  }

  function clearMessages() {
    const messages = root?.querySelector('.wingman-messages');
    if (messages) messages.replaceChildren();
    renderWelcome();
  }

  function renderWelcome() {
    const messages = root?.querySelector('.wingman-messages');
    if (!messages || messages.children.length) return;
    const welcome = document.createElement('div');
    welcome.className = 'wingman-welcome';
    const heading = document.createElement('strong');
    heading.textContent = 'I can help you find the next governed step.';
    const copy = document.createElement('span');
    copy.textContent = 'I use authorised project summaries and Architex knowledge. I suggest; you decide and click.';
    const prompts = document.createElement('div');
    prompts.className = 'wingman-prompts';
    ['What needs attention?', 'What should I review?', 'Where do I go next?'].forEach(label => {
      const button = document.createElement('button');
      button.type = 'button';
      button.textContent = label;
      button.addEventListener('click', () => {
        const textarea = root.querySelector('[name="prompt"]');
        textarea.value = label;
        textarea.focus();
      });
      prompts.appendChild(button);
    });
    welcome.append(heading, copy, prompts);
    messages.appendChild(welcome);
  }

  async function open() {
    mount();
    setState('expanded');
    const scope = configureApi();
    if (!scope) {
      showError({ code: 'authentication_required' });
      return;
    }
    updateContext();
    if (!tutorial) await loadTutorial(true);
    if (tutorial && ['new','in_progress'].includes(tutorial.progress.status)) tutorialVisible = true;
    renderTutorial();
    if (bootstrapRoute === `${scope.route}:${scope.projectId || ''}`) return;
    bootstrapRoute = `${scope.route}:${scope.projectId || ''}`;
    const query = new URLSearchParams({ route: scope.route });
    if (scope.projectId) query.set('projectId', scope.projectId);
    try {
      const bootstrap = await api.get('/wingman/bootstrap?' + query.toString());
      root.querySelector('.wingman-context').textContent = `${bootstrap.context.route} · ${bootstrap.context.family} · ${bootstrap.context.stage}`;
      if (conversationId) await restoreConversation(conversationId);
    } catch (error) {
      showError(error);
    }
  }

  function close() {
    dismissedRoute = shellScope()?.route || null;
    setState('quiet');
  }

  function updateContext() {
    mount();
    installTopButton();
    const scope = configureApi();
    const context = root.querySelector('.wingman-context');
    const suggestion = root.querySelector('.wingman-suggestion span');
    if (!scope) {
      tutorial = null;
      tutorialScope = null;
      tutorialVisible = false;
      scopeKey = null;
      renderTutorial();
      context.textContent = 'Sign in to use permission-aware guidance';
      if (root.dataset.wingmanState !== 'expanded') setState('quiet');
      return;
    }
    if (tutorialScope !== scopeKey) loadTutorial();
    context.textContent = `${scope.route}${scope.projectId ? ' · current project' : ' · portfolio scope'}`;
    suggestion.textContent = tutorial?.progress.status === 'new' ? `New here? Start your ${tutorial.label} tour.` : `Ask Wingman about ${scope.route.replaceAll('_', ' ')}.`;
    if (root.dataset.wingmanState !== 'expanded' && dismissedRoute !== scope.route) setState('suggestion');
  }

  async function loadTutorial(retry = false) {
    const scope = configureApi();
    if (!scope || (!retry && tutorialScope === scopeKey)) return;
    const requestedScope = scopeKey;
    tutorialScope = requestedScope;
    try {
      const result = await api.get('/wingman/tutorial');
      if (scopeKey !== requestedScope) return;
      tutorial = result;
      if (result.progress.status === 'new') {
        root.querySelector('.wingman-suggestion span').textContent = `New here? Start your ${result.label} tour.`;
        if (root.dataset.wingmanState === 'expanded') tutorialVisible = true;
      }
      renderTutorial();
    } catch (error) {
      if (scopeKey !== requestedScope) return;
      if (tutorialVisible) renderTutorial('Could not load your tutorial. Use Retry; chat is still available.');
    }
  }

  function renderTutorial(error = '') {
    const panel = root?.querySelector('.wingman-tour');
    if (!panel) return;
    panel.replaceChildren();
    panel.hidden = !tutorialVisible;
    if (!tutorialVisible) return;
    const add = (tag, text, className) => {
      const element = document.createElement(tag);
      element.textContent = text;
      if (className) element.className = className;
      panel.appendChild(element);
      return element;
    };
    const actions = document.createElement('div');
    actions.className = 'wingman-tour-actions';
    const button = (label, action) => {
      const element = document.createElement('button');
      element.type = 'button'; element.textContent = label;
      element.disabled = tutorialBusy;
      element.addEventListener('click', action);
      actions.appendChild(element);
      return element;
    };
    if (!tutorial) {
      add('p', error || 'Loading your role tutorial…');
      button('Retry', () => loadTutorial(true));
      panel.appendChild(actions);
      return;
    }
    const progress = tutorial.progress;
    const step = tutorial.steps[progress.step];
    add('small', `${tutorial.label} · ${progress.status === 'completed' ? 'Tour complete' : `Step ${progress.step + 1} of ${tutorial.steps.length}`}`);
    const heading = add('h3', progress.status === 'completed' ? 'You are ready to explore' : step.title);
    heading.tabIndex = -1;
    add('p', progress.status === 'completed' ? 'Tutorial finished. Ask Wingman whenever you need help, or replay the tour.' : step.body);
    if (error) add('p', error, 'wingman-tour-error').setAttribute('role','alert');
    if (progress.status === 'in_progress') {
      button('Show me', () => navigateToRoute(step.route));
      const back = button('Back', () => tutorialAction('back'));
      back.disabled = tutorialBusy || progress.step === 0;
      button(progress.step === tutorial.steps.length - 1 ? 'Finish' : 'Next', () => tutorialAction(progress.step === tutorial.steps.length - 1 ? 'finish' : 'next'));
      button('Ask Wingman', () => {
        const input = root.querySelector('[name="prompt"]');
        input.value = `I am a ${tutorial.label}. Please explain this tutorial step and my next safe action: ${step.title}. ${step.body}`;
        input.focus();
      });
      button('Skip for now', () => tutorialAction('skip'));
    } else {
      button(progress.status === 'completed' ? 'Replay' : progress.status === 'dismissed' ? 'Resume tour' : 'Start tour', () => tutorialAction(progress.status === 'completed' ? 'replay' : 'start'));
      if (progress.status !== 'new') button('Restart', () => tutorialAction('replay'));
      button('Skip for now', () => tutorialAction('skip'));
    }
    panel.appendChild(actions);
  }

  async function tutorialAction(action) {
    if (tutorialBusy || !tutorial || !configureApi()) return;
    const requestedScope = scopeKey;
    tutorialBusy = true;
    renderTutorial();
    try {
      const result = await api.post('/wingman/tutorial', {action, revision:tutorial.progress.revision, profile:tutorial.profile, version:tutorial.version});
      if (scopeKey !== requestedScope) return;
      tutorial = result;
      tutorialVisible = action !== 'skip';
      tutorialBusy = false;
      renderTutorial();
      root.querySelector('.wingman-tour h3')?.focus();
    } catch (error) {
      if (scopeKey !== requestedScope) return;
      tutorialBusy = false;
      if (error.code === 'tutorial_revision_conflict') {
        await loadTutorial(true);
        renderTutorial('Progress changed in another tab. Review the current step and try again.');
      } else renderTutorial('Progress was not saved. Please try again.');
    }
  }

  function installTopButton() {
    const button = document.getElementById('wingmanTop');
    if (!button) return;
    button.removeAttribute('onclick');
    button.setAttribute('aria-label', 'Open guided Wingman');
    if (button.dataset.wingmanBound === '1') return;
    button.dataset.wingmanBound = '1';
    button.addEventListener('click', open);
  }

  async function sendMessage(event) {
    event.preventDefault();
    const scope = configureApi();
    const form = event.currentTarget;
    const status = form.querySelector('[role="status"]');
    const prompt = form.elements.prompt.value.trim();
    if (!scope || !prompt) return;
    appendMessage('user', prompt);
    form.elements.prompt.value = '';
    form.elements.prompt.disabled = true;
    form.querySelector('[type="submit"]').disabled = true;
    status.textContent = 'Wingman is checking authorised context…';
    try {
      const response = await api.post('/wingman/messages', {
        prompt,
        route: scope.route,
        projectId: scope.projectId,
        selectedRecordId: scope.selectedRecordId,
        conversationId,
      });
      conversationId = response.conversationId;
      appendAssistant(response);
      status.textContent = 'Guidance ready. Navigation still requires your click.';
    } catch (error) {
      showError(error);
      status.textContent = honestError(error);
    } finally {
      form.elements.prompt.disabled = false;
      form.querySelector('[type="submit"]').disabled = false;
      form.elements.prompt.focus();
    }
  }

  function appendMessage(role, content, extraClass = '') {
    const messages = root.querySelector('.wingman-messages');
    if (messages.querySelector('.wingman-welcome')) messages.replaceChildren();
    const item = document.createElement('article');
    item.className = `wingman-message ${role} ${extraClass}`.trim();
    item.textContent = content;
    messages.appendChild(item);
    messages.scrollTop = messages.scrollHeight;
    return item;
  }

  function appendAssistant(response) {
    const item = appendMessage('assistant', response.message.content);
    if (response.citations?.length) {
      const citations = document.createElement('div');
      citations.className = 'wingman-citations';
      response.citations.forEach(citation => {
        const element = document.createElement(citation.routeTarget ? 'button' : 'div');
        if (element instanceof HTMLButtonElement) element.type = 'button';
        element.className = 'wingman-citation';
        const title = document.createElement('strong'); title.textContent = citation.title;
        const detail = document.createElement('small'); detail.textContent = citation.sourceReference + (citation.excerpt ? ` · ${citation.excerpt}` : '');
        element.append(title, detail);
        if (citation.routeTarget) element.addEventListener('click', () => navigateToRoute(citation.routeTarget));
        citations.appendChild(element);
      });
      item.appendChild(citations);
    }
    if (response.suggestions?.length) {
      const actions = document.createElement('div');
      actions.className = 'wingman-actions';
      response.suggestions.forEach(suggestion => {
        const card = document.createElement('div');
        card.className = 'wingman-navigation';
        card.setAttribute('data-wingman-navigate', suggestion.routeTarget || '');
        const label = document.createElement('span'); label.textContent = suggestion.label;
        const reason = document.createElement('small'); reason.textContent = suggestion.reason;
        const navigate = document.createElement('button'); navigate.type = 'button'; navigate.className = 'btn'; navigate.textContent = 'Open this workspace';
        navigate.addEventListener('click', () => navigateToRoute(suggestion.routeTarget));
        const feedback = document.createElement('div'); feedback.className = 'wingman-feedback';
        ['helpful', 'not_helpful'].forEach(rating => {
          const button = document.createElement('button'); button.type = 'button'; button.textContent = rating === 'helpful' ? 'Helpful' : 'Not helpful';
          button.addEventListener('click', () => sendFeedback(suggestion.id, rating, feedback));
          feedback.appendChild(button);
        });
        card.append(label, reason, navigate, feedback);
        actions.appendChild(card);
      });
      item.appendChild(actions);
    }
  }

  function routeDestination(route) {
    const toolDestinations = { municipal: 'workspace-tools', planning: 'workspace-tools', wingman: 'workspace-tools', cpd_learning: 'knowledge-cpd', meetings: 'collaboration' };
    if (['command-centre', 'projects', 'workspace-tools', 'collaboration', 'knowledge-cpd', 'admin-settings'].includes(route)) return route;
    return toolDestinations[route] || (route === 'inbox' ? 'collaboration' : route === 'cpd' ? 'knowledge-cpd' : route === 'settings' ? 'admin-settings' : 'workspace-tools');
  }

  function navigateToRoute(route) {
    if (!route) return;
    const globalRoutes = ['command', 'projects', 'tools', 'inbox', 'documents', 'finance', 'knowledge', 'cpd', 'feedback', 'dedicated', 'completeness', 'settings'];
    if (globalRoutes.includes(route) && typeof window.openGlobal === 'function') {
      window.openGlobal(route);
      return;
    }
    const toolExists = typeof TOOLS !== 'undefined' && Boolean(TOOLS[route]);
    if (toolExists && typeof window.openTool === 'function') {
      window.openTool(route, shellScope()?.projectId ? 'project' : 'standalone');
      return;
    }
    window.flightpathOpenDestination?.(routeDestination(route));
  }

  async function sendFeedback(suggestionId, rating, container) {
    try {
      await api.post(`/wingman/suggestions/${encodeURIComponent(suggestionId)}/feedback`, { rating });
      container.textContent = rating === 'helpful' ? 'Marked helpful.' : 'Feedback recorded.';
    } catch (error) {
      container.textContent = honestError(error);
    }
  }

  async function restoreConversation(id) {
    try {
      const conversation = await api.get(`/wingman/conversations/${encodeURIComponent(id)}`);
      const messages = root.querySelector('.wingman-messages');
      messages.replaceChildren();
      conversation.messages.forEach(message => appendMessage(message.role, message.content, message.status === 'complete' ? '' : 'error'));
    } catch (error) {
      conversationId = null;
      showError(error);
    }
  }

  function showError(error) {
    appendMessage('assistant', honestError(error), 'error');
  }

  function honestError(error) {
    return ({
      authentication_required: 'Sign in to use the permission-aware Wingman.',
      configuration_required: 'Wingman needs a server-side NVIDIA configuration before it can answer.',
      provider_unavailable: 'Nemotron is currently unavailable. Your message remains recorded; try again later.',
      provider_timeout: 'Nemotron did not answer in time. No action was taken.',
      rate_limited: 'Wingman is receiving too many requests. Wait briefly and try again.',
      context_denied: 'This workspace or record is outside your authorised context.',
      no_authorised_evidence: 'No authorised evidence was found for that question.',
    })[error?.code] || 'Wingman could not complete that request. No action was taken.';
  }

  mount();
  installTopButton();
  const previousRenderAll = window.renderAll;
  window.renderAll = function wingmanRenderAll() {
    previousRenderAll();
    updateContext();
  };
  updateContext();
})();
