(function (root) {
  'use strict';
  const unwrap = response => response?.data ?? response;
  // Professional Directory — search and invitation (A042).
  //
  // The governance controller rendered a result table with no way to search and no way to invite,
  // which is not the capability the item describes. This module owns the screen instead, driving
  // the SAME verified governance adapter: searchDirectory() for the query, invite() for the
  // project-team write (which already reads back and confirms the member appears in the team list).
  //
  // It is a separate file deliberately: governance.js is shared by five tools, and a bad edit there
  // takes the whole shell down.
  const TOOL = 'professional_directory';

  function install({ context }) {
    const ownsRoute = toolId => { const view = typeof state === 'object' ? state : root.state; return !view || view.tool === undefined || view.tool === toolId; };
    const adapter = root.V1CloneGovernanceAdapter.create(context);
    const esc = value => String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    let generation = 0, disposed = false, active = false;
    let results = [], profile = null, filters = { profession: '', serviceArea: '' }, message = 'Ready.';

    const notice = text => { if (disposed || !ownsRoute(TOOL)) return;
      message = text;
      root.showToast?.(text);
      const node = root.document.querySelector('#main [data-dir-status]');
      if (node) node.textContent = text;
    };

    async function load(token = generation) {
      let loadedProfile = null;
      try { loadedProfile = await adapter.profile?.(); } catch { loadedProfile = null; }
      if (disposed || !ownsRoute(TOOL) || token !== generation) return [];
      if (loadedProfile == null) {
        try { loadedProfile = unwrap(await context.read('/professional-profile')) || null; }
        catch (error) { if (!error || error.status !== 404) throw error; }
      }
      const found = await adapter.searchDirectory(filters.profession, filters.serviceArea);
      if (disposed || !ownsRoute(TOOL) || token !== generation) return [];
      profile = loadedProfile;
      results = Array.isArray(found) ? found : (found?.professionals || []);
      return results;
    }

    function render() { if (disposed || !ownsRoute(TOOL)) return;
      const main = root.document.querySelector('#main');
      if (!main) return;
      const state = context.getState();
      const project = state.projects?.find(p => p.id === state.selectedProjectId)?.name || 'Selected project';
      const rows = results.length
        ? results.map(person => {
            const id = person.profileId || person.id || person.candidateId || '';
            return `<tr>
              <td><b>${esc(person.displayName || person.name || '—')}</b></td>
              <td>${esc(person.profession || '—')}</td>
              <td>${esc((person.serviceAreas || []).join(', ') || '—')}</td>
              <td><span class="pill">${esc(person.verificationGrade || person.grade || 'unverified')}</span></td>
              <td>${esc(person.registrationBody || '—')}</td>
              <td class="dir-rowactions">
                <select data-dir-discipline="${esc(id)}">${['architectural', 'structural', 'civil', 'electrical', 'mechanical', 'quantity_surveying', 'general']
                  .map(d => `<option value="${d}"${(person.profession || '').toLowerCase().includes(d.split('_')[0]) ? ' selected' : ''}>${d}</option>`).join('')}</select>
                <button class="btn" data-dir-invite="${esc(id)}"${id ? '' : ' disabled title="This result carries no candidate id, so it cannot be invited."'}>Invite to project</button>
              </td></tr>`;
          }).join('')
        : '<tr><td colspan="6">No directory results for this filter. Only professionals who have published a profile and passed verification appear here.</td></tr>';

      main.innerHTML = `
        <div class="dir-workspace" data-dir-tool="${TOOL}">
          <header class="dir-hero">
            <div><h1>Professional Directory</h1>
              <p>Project: <strong>${esc(project)}</strong> · ${results.length} result(s)</p></div>
            <button class="btn" type="button" data-dir-reload>Refresh</button>
          </header>
          <p class="pill" data-dir-boundary>Internal directory — listing depends on professionals publishing a profile and a verification decision; it is not an accreditation register.</p>
          <section class="panel">
            <h2>Search</h2>
            <div class="dir-filters">
              <label class="field"><span>Profession</span><input data-dir-filter="profession" value="${esc(filters.profession)}" placeholder="architect"></label>
              <label class="field"><span>Service area</span><input data-dir-filter="serviceArea" value="${esc(filters.serviceArea)}" placeholder="Cape Town"></label>
              <button class="btn" type="button" data-dir-search>Search</button>
              <button class="btn" type="button" data-dir-clear>Clear</button>
            </div>
          </section>
          <section class="panel">
            <h2>Verified professionals</h2>
            <table class="table">
              <thead><tr><th>Name</th><th>Profession</th><th>Service areas</th><th>Grade</th><th>Registration body</th><th>Invite</th></tr></thead>
              <tbody>${rows}</tbody>
            </table>
          </section>
          ${profile ? `<section class="panel"><h2>Your directory profile</h2>
            <p class="dir-hint">Service areas: ${esc((profile.serviceAreas || []).join(', ') || 'none recorded')} · listed: ${profile.directoryVisible ? 'yes' : 'no'}</p></section>`
            : `<section class="panel"><h2>Your directory profile</h2>
               <p class="dir-hint">You have no professional profile yet, so you are not listed in the directory. Submitting a profile and passing verification is what puts a professional in these results.</p></section>`}
          <p class="pill" data-dir-status role="status">${esc(message)}</p>
        </div>`;
      bind(main);
    }

    function bind(main) {
      const readFilters = () => {
        filters.profession = String(main.querySelector('[data-dir-filter="profession"]')?.value || '').trim();
        filters.serviceArea = String(main.querySelector('[data-dir-filter="serviceArea"]')?.value || '').trim();
      };

      main.querySelector('[data-dir-search]')?.addEventListener('click', async event => {
        event.currentTarget.disabled = true;
        readFilters();
        try { await load(); notice(`Search complete — ${results.length} result(s) from the server.`); render(); }
        catch (error) { notice(error.message + ' The search did not complete.'); event.currentTarget.disabled = false; }
      });

      main.querySelector('[data-dir-clear]')?.addEventListener('click', async () => {
        filters = { profession: '', serviceArea: '' };
        try { await load(); notice('Filters cleared.'); render(); }
        catch (error) { notice(error.message + ' The search did not complete.'); }
      });

      main.querySelector('[data-dir-reload]')?.addEventListener('click', async event => {
        event.currentTarget.disabled = true;
        try { await load(); notice('Directory reloaded.'); render(); }
        catch (error) { notice(error.message + ' Nothing was loaded.'); event.currentTarget.disabled = false; }
      });

      main.querySelectorAll('[data-dir-invite]').forEach(button => button.addEventListener('click', async () => {
        const candidateId = button.getAttribute('data-dir-invite');
        const discipline = String(main.querySelector(`[data-dir-discipline="${candidateId}"]`)?.value || 'general');
        const roleLabel = root.prompt?.('Role label for this team member (e.g. Lead structural engineer)', discipline) || discipline;
        if (!root.confirm?.(`Invite this professional to the project team as "${roleLabel}"? The invitation is recorded on the project.`)) return;
        button.disabled = true;
        try {
          await adapter.invite({ candidateId: candidateId, discipline: discipline, roleLabel: roleLabel });
          notice('Invitation recorded and verified against the project team.');
        } catch (error) {
          notice(error.message + ' The invitation was not recorded.');
          button.disabled = false;
        }
      }));
    }

    async function apply(toolId) {
      if (disposed || !ownsRoute(toolId)) return { applied: false, reason: 'stale' };
      if (toolId !== TOOL) return { applied: false, reason: 'not-directory' };
      active = true;
      const token = ++generation;
      try {
        await load();
        if (disposed || !ownsRoute(TOOL) || token !== generation) return { applied: false, reason: 'stale' };
        render();
        return { applied: true, tool: TOOL, results: results.length };
      } catch (error) {
        if (disposed || !ownsRoute(TOOL) || token !== generation) return { applied: false, reason: 'stale' };
        notice(error.message + ' The directory could not be loaded.');
        return { applied: false, reason: 'error', message: error.message };
      }
    }

    const unsubscribe = typeof context.subscribe === 'function' ? context.subscribe(() => { if (active && !disposed) void apply(TOOL); }) : null;
    function dispose() { disposed = true; ++generation; active = false; if (typeof unsubscribe === 'function') unsubscribe(); }

    return { apply, dispose, supports: id => id === TOOL };
  }
  root.V1CloneDirectory = { install };
})(window);
