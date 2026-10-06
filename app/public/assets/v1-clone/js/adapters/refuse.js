(function (root) {
  'use strict';
  // Municipal Refuse Area Calculator — adapter.
  //
  // Every write goes through the shared context with an exact read-back path, so a saved
  // calculation is only reported as saved once the server returns the persisted record.
  function create(context) {
    if (!context || typeof context.write !== 'function' || typeof context.read !== 'function') {
      throw Error('The refuse adapter requires the shared context.');
    }
    let catalogue = null;
    const unwrap = value => (value && typeof value === 'object' && 'data' in value && value.data !== undefined ? value.data : value);

    function scope() {
      const state = context.getState();
      if (!state.ready || !state.session || !state.organisationId) throw Error('Sign in and select an organisation first.');
      if (!/^[a-f0-9]{32}$/i.test(state.selectedProjectId || '')) throw Error('Select an authorised project first.');
      return state;
    }

    async function loadCatalogue(force) {
      // The catalogue carries the stored rule packs, so it must be re-readable after one is
      // added or verified — otherwise the screen shows the list it fetched on first paint.
      if (catalogue && force !== true) return catalogue;
      const payload = unwrap(await context.read('/refuse/catalogue'));
      if (!payload || typeof payload !== 'object') throw Error('The refuse catalogue is unavailable.');
      catalogue = payload;
      return catalogue;
    }

    async function listForProject() {
      const state = scope();
      const payload = unwrap(await context.read('/projects/' + encodeURIComponent(state.selectedProjectId) + '/refuse-calculations'));
      if (!Array.isArray(payload)) throw Error('Invalid refuse calculation list response.');
      return payload;
    }

    async function find(id) {
      const payload = unwrap(await context.read('/refuse-calculations/' + encodeURIComponent(id)));
      if (!payload || typeof payload !== 'object') throw Error('The calculation could not be read back.');
      return payload;
    }

    async function create(input) {
      const state = scope();
      const path = '/projects/' + encodeURIComponent(state.selectedProjectId) + '/refuse-calculations';
      return context.write(path, input, { readBackPath: saved => '/refuse-calculations/' + encodeURIComponent(saved?.id || '') });
    }

    async function update(id, input) {
      return context.write('/refuse-calculations/' + encodeURIComponent(id), input, {
        method: 'PUT',
        readBackPath: '/refuse-calculations/' + encodeURIComponent(id),
      });
    }

    async function declare(id, declarationNote, expectedRevision) {
      return context.write('/refuse-calculations/' + encodeURIComponent(id) + '/declare', {
        declarationNote: declarationNote, expectedRevision: expectedRevision,
      }, {
        method: 'POST',
        readBackPath: '/refuse-calculations/' + encodeURIComponent(id),
      });
    }

    async function remove(id, expectedRevision) {
      return context.write('/refuse-calculations/' + encodeURIComponent(id), { expectedRevision: expectedRevision }, {
        method: 'DELETE',
        readBackPath: '/refuse-calculations/' + encodeURIComponent(id),
      });
    }

    async function saveRulePack(input) {
      // A rule pack is organisation-scoped, not project-scoped, so it bypasses the project guard.
      return context.write('/refuse/rule-packs', input, {
        projectScoped: false,
        readBackPath: (saved) => '/refuse/rule-packs?municipalityKey=' + encodeURIComponent(saved?.municipalityKey || ''),
      });
    }

    async function reviewRulePack(id, decision, reason) {
      return context.write('/refuse/rule-packs/' + encodeURIComponent(id) + '/review', {
        decision: decision, reason: reason,
      }, {
        projectScoped: false,
        readBackPath: '/refuse/rule-packs',
      });
    }

    return { loadCatalogue, listForProject, find, create, update, declare, remove, saveRulePack, reviewRulePack, scope };
  }
  root.V1CloneRefuseAdapter = { create };
})(window);
