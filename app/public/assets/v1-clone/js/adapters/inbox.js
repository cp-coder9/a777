(function (root) {
  'use strict';
  // Inbox adapter — the decision inbox the shell left read-only.
  //
  // List:     GET  /api/v1/inbox
  // Decision: POST /api/v1/inbox/{id}/transition  { status, reason?, humanConfirmed? }
  //           status is one of read | resolved | dismissed; the server requires a reason and an
  //           explicit human confirmation for the deciding statuses on sensitive items.
  function create(context) {
    if (!context || typeof context.write !== 'function' || typeof context.read !== 'function') {
      throw Error('The inbox adapter requires the shared context.');
    }
    const unwrap = value => (value && typeof value === 'object' && 'data' in value && value.data !== undefined ? value.data : value);

    function scope() {
      const state = context.getState();
      if (!state.ready || !state.session || !state.organisationId) throw Error('Sign in and select an organisation first.');
      return state;
    }

    async function list() {
      scope();
      const payload = unwrap(await context.read('/inbox'));
      if (!Array.isArray(payload)) throw Error('Invalid inbox response.');
      return payload;
    }

    async function transition(id, status, options = {}) {
      const body = { status: status };
      if (options.reason) body.reason = options.reason;
      if (options.humanConfirmed === true) body.humanConfirmed = true;
      return context.write('/inbox/' + encodeURIComponent(id) + '/transition', body, {
        // The inbox is organisation/user scoped, not project scoped, so the project guard is off;
        // the read-back confirms the item's new status inside the returned list.
        projectScoped: false,
        readBackPath: '/inbox',
      });
    }

    return { list, transition, scope };
  }
  root.V1CloneInboxAdapter = { create };
})(window);
