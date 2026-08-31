(function () {
  'use strict';

  const root = document.documentElement;
  const access = document.getElementById('publicAccess');
  const landing = document.getElementById('publicLandingFrame');
  const authMount = document.getElementById('publicAuthMount');
  const workspace = document.getElementById('app');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const accessStates = Object.freeze(['checking-session', 'public', 'auth', 'workspace']);
  let transitionTimer = null;

  function setState(state) {
    if (!accessStates.includes(state)) throw new Error('Unknown public access state.');
    root.dataset.accessState = state;
  }

  function showLanding() {
    if (transitionTimer !== null) window.clearTimeout(transitionTimer);
    access?.classList.remove('is-flocking', 'is-auth-ready');
    if (authMount) {
      authMount.hidden = true;
      authMount.replaceChildren();
    }
    if (workspace) {
      workspace.inert = true;
      workspace.setAttribute('aria-hidden', 'true');
    }
    setState('public');
  }

  function showAuth(mode) {
    const registration = mode === 'sign up' || mode === 'create account' || mode === 'register';
    if (transitionTimer !== null) window.clearTimeout(transitionTimer);
    setState('auth');
    access?.classList.remove('is-auth-ready');
    access?.classList.add('is-flocking');
    if (authMount) {
      authMount.hidden = false;
      authMount.innerHTML = `<main class="public-auth-placeholder"><div><small>V8 secure ${registration ? 'enrolment' : 'access'}</small><h1>${registration ? 'Join Architex OS' : 'Welcome back'}</h1><p>${registration ? 'Create your governed organisation workspace.' : 'Resume your governed workspace with project context, evidence controls, and role permissions intact.'}</p></div></main>`;
    }
    const finish = () => {
      access?.classList.remove('is-flocking');
      access?.classList.add('is-auth-ready');
      authMount?.querySelector('h1')?.setAttribute('tabindex', '-1');
      authMount?.querySelector('h1')?.focus();
    };
    transitionTimer = window.setTimeout(finish, reducedMotion.matches ? 0 : 2400);
  }

  function showWorkspace() {
    if (transitionTimer !== null) window.clearTimeout(transitionTimer);
    access?.classList.remove('is-flocking', 'is-auth-ready');
    if (workspace) {
      workspace.inert = false;
      workspace.removeAttribute('aria-hidden');
    }
    setState('workspace');
  }

  function bindLandingActions() {
    const document = landing?.contentDocument;
    if (!document || document.documentElement.dataset.architexAccessBound === 'true') return;
    document.documentElement.dataset.architexAccessBound = 'true';
    document.addEventListener('click', event => {
      const trigger = event.target?.closest?.('a,button,[role="button"]');
      if (!trigger) return;
      const label = String(trigger.textContent || trigger.getAttribute('aria-label') || '').trim().toLowerCase();
      if (!['sign in', 'sign up', 'create account'].some(action => label.includes(action))) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      showAuth(label);
    }, true);
  }

  landing?.addEventListener('load', bindLandingActions);
  window.ArchitexPublicAccess = Object.freeze({ showLanding, showAuth, showWorkspace });
}());
