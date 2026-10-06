(function (root) {
  'use strict';
  // Shared module-record adapter.
  //
  // This serves ONLY the modules whose v1.html original had no domain form AND which have
  // no backend of their own: dispute_resolution, council_navigator, refuse_calculator. The
  // two scaffold controls that could never save ("Add implementation note", "Attach
  // project") ride the same store.
  //
  // Every write goes through the shared context with an explicit read-back path, so a
  // success message can only be shown after the server returns the persisted record (or,
  // for a delete, after the record genuinely 404s). Nothing here claims a save that the
  // database did not accept.
  function create(context) {
    if (!context || typeof context.write !== 'function' || typeof context.read !== 'function') {
      throw Error('The module-record adapter requires the shared context.');
    }
    let catalogue = null;

    function scope() {
      const state = context.getState();
      if (!state.ready || !state.session || !state.organisationId) throw Error('Sign in and select an organisation first.');
      if (!/^[a-f0-9]{32}$/i.test(state.selectedProjectId || '')) throw Error('Select an authorised project first.');
      return state;
    }
    const unwrap = value => (value && typeof value === 'object' && 'data' in value && value.data !== undefined ? value.data : value);

    async function loadCatalogue() {
      if (catalogue) return catalogue;
      const payload = unwrap(await context.read('/module-records/catalogue'));
      if (!payload || typeof payload.modules !== 'object') throw Error('The module-record catalogue is unavailable.');
      catalogue = payload;
      return catalogue;
    }

    async function list(moduleKey) {
      const state = scope();
      const query = '/module-records?moduleKey=' + encodeURIComponent(moduleKey) + '&projectId=' + encodeURIComponent(state.selectedProjectId);
      const payload = unwrap(await context.read(query));
      if (!Array.isArray(payload)) throw Error('Invalid module-record list response.');
      return payload;
    }

    async function events(id) {
      const payload = unwrap(await context.read('/module-records/' + encodeURIComponent(id) + '/events'));
      return Array.isArray(payload) ? payload : [];
    }

    async function create(moduleKey, fields) {
      const state = scope();
      const payload = Object.assign({ moduleKey: moduleKey, projectId: state.selectedProjectId }, fields);
      return context.write('/module-records', payload, {
        readBackPath: saved => '/module-records/' + encodeURIComponent(saved?.id || ''),
      });
    }

    async function update(id, moduleKey, fields) {
      const state = scope();
      const payload = Object.assign({ moduleKey: moduleKey, projectId: state.selectedProjectId }, fields);
      return context.write('/module-records/' + encodeURIComponent(id), payload, {
        method: 'PUT',
        readBackPath: '/module-records/' + encodeURIComponent(id),
      });
    }

    async function transition(id, toStatus, reason, humanConfirmed, expectedRevision) {
      return context.write('/module-records/' + encodeURIComponent(id) + '/transition', {
        toStatus: toStatus, reason: reason, humanConfirmed: humanConfirmed === true, expectedRevision: expectedRevision,
      }, {
        method: 'PUT',
        readBackPath: '/module-records/' + encodeURIComponent(id),
      });
    }

    async function remove(id, expectedRevision) {
      return context.write('/module-records/' + encodeURIComponent(id), { expectedRevision: expectedRevision }, {
        method: 'DELETE',
        readBackPath: '/module-records/' + encodeURIComponent(id),
      });
    }

    return { loadCatalogue, list, events, create, update, transition, remove, scope };
  }
  root.V1CloneRecordsAdapter = { create };
})(window);
