(function (global) {
  'use strict';
  const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
  const unwrap = value => value?.data ?? value;
  const identifier = value => typeof value === 'string' && /^[a-f0-9]{32}$/i.test(value);
  // Requires context.read(path) and context.write(path, body, {readBackPath(id), projectScoped}).
  // Context owns the /api/v1 prefix, session, organisation and CSRF headers.
  // Missing transport is a hard error: never fall back to the original in-memory submit.
  async function install({context}) {
    const element = id => document.querySelector('#' + id);
    const records = typeof feedbackRecords !== 'undefined' ? feedbackRecords : [];
    records.splice(0);
    const notify = message => global.showToast?.(message);
    let busy = false, pending = null, generation = 0, loadError = '';
    const ownsWorkspace = () => (typeof state === 'object' ? state.tool : global.state?.tool) === 'feedback';
    const ownerKey = () => { const s = context?.getState?.() || {}; return [s.organisationId || s.selectedOrganisationId || '',s.session?.userId || s.session?.user?.id || ''].join('|'); };
    let owner = ownerKey();
    const capturedContext = () => String(typeof feedbackContextText === 'function' ? feedbackContextText() : '').trim();
    function render() {
      const target = element('feedbackRecords');
      if (target) target.innerHTML = records.length ? records.map(record => `<div class="feedback-record"><b>${escape(record.message)}</b><span>${escape(record.category)} · ${escape(record.status)} · ${escape(record.createdAt)}</span><span>${escape(record.context)}</span><span>Reference: ${escape(record.id)}</span></div>`).join('') : '<div class="feedback-record"><span>No feedback submitted yet.</span></div>';
      renderWorkspace();
    }
    function renderWorkspace() {
      if (!ownsWorkspace()) return;
      const target=element('v1-feedback-workspace-records');if(!target)return;
      const scope=context.getState(),projectId=scope.mode==='standalone'?null:scope.selectedProjectId;
      const scoped=records.filter(record=>(record.projectId??null)===(projectId??null));
      target.innerHTML=loadError?`<p role="status">${escape(loadError)}</p>`:scoped.length?scoped.map(record=>`<article class="panel" style="padding:16px;margin:12px 0;overflow-wrap:anywhere"><h3>${escape(record.category)}</h3><p>${escape(record.message)}</p><p>${escape(record.status)} · ${escape(record.createdAt)}</p><p>${escape(record.context)}</p><small>Reference: ${escape(record.id)}</small></article>`).join(''):'<p>No feedback submitted in this workspace yet.</p>';
    }
    function apply(id) {
      if(id!=='feedback'||!ownsWorkspace())return false;
      const main=element('main');if(!main)return false;
      main.innerHTML='<section data-v1-feedback-workspace><h1>Feedback Intelligence</h1><p>Review feedback saved in this workspace, or report an issue with its project context.</p><div class="toolbar"><button type="button" class="btn dark" id="v1-feedback-new">Submit feedback</button><button type="button" class="btn ghost" id="v1-feedback-refresh">Refresh feedback</button></div><div id="v1-feedback-workspace-records" aria-live="polite"></div></section>';
      element('v1-feedback-new').onclick=()=>global.toggleFeedback?.(true);
      element('v1-feedback-refresh').onclick=()=>void refresh();
      renderWorkspace();void refresh();return true;
    }
    global.renderFeedbackRecords = render;
    global.updateFeedbackContext = function () {
      const target = element('feedbackContext');
      if (target) target.innerHTML = '<b>Captured context</b><br>' + escape(capturedContext());
    };
    render();global.updateFeedbackContext();
    // Replace unsafe original handler before validating the transport.
    global.submitFeedback = async () => notify('Feedback is unavailable until the authenticated context is ready. Your text has been kept.');
    if (!context?.read || !context?.write) throw Error('Feedback requires context.read and context.write.');
    async function refresh() {
      const ticket = ++generation, scope = ownerKey();
      try {
        const loaded = unwrap(await context.read('/feedback'));
        if (ticket !== generation || scope !== ownerKey()) return;
        if (!Array.isArray(loaded)) throw Error('Invalid feedback list response.');
        loadError='';records.splice(0,records.length,...loaded);render();
      } catch (error) {
        if (ticket !== generation || scope !== ownerKey()) return;
        loadError='Feedback records could not be loaded. Refresh after reconnecting.';renderWorkspace();
        const target = element('feedbackRecords');
        if (target) target.innerHTML = '<div class="feedback-record"><span>Feedback records could not be loaded. Reopen after reconnecting.</span></div>';
        notify(error.message || 'Feedback records could not be loaded.');
      }
    }
    global.submitFeedback = async function () {
      if (busy) return;
      const field = element('feedbackText');
      const originalText = field?.value || '', message = originalText.trim();
      if ([...message].length < 10 || [...message].length > 2000) { notify('Please enter between 10 and 2,000 characters.'); return; }
      busy = true;
      try {
        const scope = ownerKey(), current = context.getState();
        const body = {category:typeof feedbackCategory === 'string' ? feedbackCategory : 'Feature request',message,context:capturedContext()};
        if ([...body.context].length > 512) throw Error('Captured context is too long. Please use a shorter workspace context.');
        if (typeof state !== 'undefined' && state.mode === 'project') {
          if (!identifier(current.selectedProjectId)) throw Error('Select an authorised project before submitting project feedback.');
          body.projectId = current.selectedProjectId;
        }
        const signature = JSON.stringify([scope,body]);
        if (pending?.signature !== signature) pending = {signature,requestId:global.crypto.randomUUID()};
        body.clientRequestId = pending.requestId;
        const result = unwrap(await context.write('/feedback',body,{readBackPath:saved => {
          const id = typeof saved === 'string' ? saved : unwrap(saved)?.id;
          if (!identifier(id)) throw Error('Feedback response did not contain a valid record ID.');
          return '/feedback/' + id;
        },projectScoped:false}));
        if (scope !== ownerKey()) throw Error('Organisation changed. Your feedback text has been kept.');
        if (!identifier(result?.id)) throw Error('Feedback response did not contain a valid record ID.');
        const verified = unwrap(await context.read('/feedback/' + result.id));
        if (scope !== ownerKey()) throw Error('Organisation changed. Your feedback text has been kept.');
        if (verified?.id !== result.id || verified.message !== body.message || verified.category !== body.category || verified.context !== body.context || (verified.projectId ?? null) !== (body.projectId ?? null)) throw Error('Feedback could not be verified. Your text has been kept; retry to confirm.');
        ++generation; // A stale list request must not overwrite this verified record.
        const old = records.findIndex(record => record.id === verified.id);
        if (old !== -1) records.splice(old,1);
        records.unshift(verified);pending = null;render();
        const unchanged = field.value === originalText;
        if (unchanged) { field.value = '';global.updateFeedbackCount?.(); }
        notify('Feedback submitted with workspace context. Reference: ' + verified.id);
        if (unchanged) global.toggleFeedback?.(false);
      } catch (error) { notify(error.message || 'Feedback could not be saved. Your text has been kept.'); }
      finally { busy = false; }
    };
    const unsubscribe = context.subscribe?.(() => {
      const next = ownerKey();
      if (next !== owner) { owner = next;pending = null;++generation;records.splice(0);render();void refresh(); }
      global.updateFeedbackContext();
    });
    const originalToggle = global.toggleFeedback;
    if (originalToggle) global.toggleFeedback = function (...args) {
      const result = originalToggle.apply(this,args);
      if (args[0] !== false) void refresh();
      return result;
    };
    await refresh();
    return {render,refresh,apply,dispose:() => { ++generation;unsubscribe?.(); }};
  }
  global.V1CloneFeedback = {install};
})(window);
