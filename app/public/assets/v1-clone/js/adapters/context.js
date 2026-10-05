(function (global) {
  'use strict';
  function create({ api }) {
    const state = { session: null, projects: [], selectedProjectId: null, rememberedProjectId: null, mode: 'project', organisationId: null, ready: false };
    let generation = 0, navigationGeneration = 0, pendingWrite = false, disposed = false;
    const guards = new Set();
    const acceptedWrites = new Map();
    const listeners = new Set();
    const getState = () => ({ ...state, projects: state.projects.slice(), readOnly: !state.ready || !['owner','admin','member'].includes(state.session?.memberships?.find(member => member.organisationId === state.organisationId)?.role) });
    const emit = () => listeners.forEach(listener => listener(getState()));
    async function bootstrap() {
      const session = await api.get('/session');
      const membership = session?.memberships?.[0];
      if (!membership?.organisationId) throw Error('An active organisation membership is required.');
      api.setOrganisation(membership.organisationId);
      api.setCsrf(session.csrfToken);
      const projects = await api.get('/projects');
      if (!Array.isArray(projects)) throw Error('Invalid authorised project response.');
      Object.assign(state, { session, projects, organisationId: membership.organisationId, selectedProjectId: projects[0]?.id || null, rememberedProjectId: projects[0]?.id || null, mode: 'project', ready: true });
      emit(); return getState();
    }
    async function selectProject(id) {
      assertReady();
      const navigationToken = ++navigationGeneration;
      if (pendingWrite) throw Error('A write is pending. Wait before changing projects.');
      if (!state.projects.some(project => project.id === id)) throw Error('The selected project is not authorised.');
      if (id === state.selectedProjectId) return true;
      const token = generation;
      for (const guard of guards) if (await guard({ from: state.selectedProjectId, to: id }) === false) return false;
      if (navigationToken !== navigationGeneration) return false;
      if (token !== generation || pendingWrite || disposed) throw Error('Project context changed during navigation.');
      generation++; state.selectedProjectId = id; state.rememberedProjectId = id; state.mode = 'project'; emit(); return true;
    }
    async function setMode(mode) {
      assertReady();
      const navigationToken = ++navigationGeneration;
      if (!['project','standalone'].includes(mode)) throw Error('Unknown workspace orientation.');
      if (mode === state.mode) return true;
      if (mode === 'project' && state.projects.length) return selectProject(state.rememberedProjectId || state.projects[0].id);
      if (pendingWrite) throw Error('A write is pending. Wait before changing orientation.');
      const token = generation;
      for (const guard of guards) if (await guard({ from: state.selectedProjectId, to: null, mode }) === false) return false;
      if (navigationToken !== navigationGeneration) return false;
      if (token !== generation || pendingWrite || disposed) throw Error('Workspace context changed during navigation.');
      generation++; state.mode = mode; state.selectedProjectId = null; emit(); return true;
    }
    async function reloadProjects() {
      assertReady();
      const token = generation;
      const projects = await api.get('/projects');
      if (disposed || token !== generation) throw Error('Project context changed while reloading.');
      if (!Array.isArray(projects)) throw Error('Invalid authorised project response.');
      state.projects = projects;
      if (state.mode === 'standalone') {
        // Refreshing the selector must not attach a project behind a standalone view.
        if (!projects.some(project => project.id === state.rememberedProjectId)) state.rememberedProjectId = projects[0]?.id || null;
      } else if (!state.projects.some(project => project.id === state.selectedProjectId)) {
        // Fallback selection changes the visible scope: bump the generation so
        // in-flight reads/writes keyed on the old scope fail safely, exactly
        // like selectProject() does.
        generation++;
        state.selectedProjectId = state.projects[0]?.id || null;
        state.rememberedProjectId = state.selectedProjectId;
      }
      emit();
      return getState();
    }
    function assertReady() { if (disposed || !state.ready) throw Error('The production context is not ready.'); }
    function safePath(path) {
      if (typeof path !== 'string' || !path.startsWith('/') || path.startsWith('//') || path.includes('://') || path.includes('..') || /[\r\n]/.test(path)) throw Error('Invalid application API path.');
      return path.startsWith('/api/v1/') ? path.slice('/api/v1'.length) : path;
    }
    async function read(path) {
      assertReady(); const token = generation;
      const value = await api.get(safePath(path));
      if (disposed || token !== generation) throw Error('Project context changed while loading.');
      return value;
    }
    async function download(path,{sha256,mimeType,filename}={}){
      assertReady();
      if(!/^\/toolbox\/exports\/[a-f0-9]{32}$/i.test(safePath(path))||!/^boq_takeoff-[a-f0-9]{32}\.(json|csv|html)$/.test(filename||'')||!/^[a-f0-9]{64}$/.test(sha256||'')||!['application/json','text/csv','text/html'].includes(mimeType))throw Error('Invalid advisory artifact download contract or path.');
      if(getState().readOnly)throw Error('Toolbox download requires tools-use membership scope.');
      if(!global.crypto?.subtle||typeof global.fetch!=='function')throw Error('Artifact integrity verification requires a secure browser context.');
      const token=generation,key=JSON.stringify([state.organisationId,state.session.userId,state.session.memberships]),project=state.selectedProjectId;
      const response=await global.fetch('/api/v1'+safePath(path),{method:'GET',credentials:'same-origin',cache:'no-store',headers:{'X-Organisation-Id':state.organisationId}});
      if(!response.ok)throw Error('Artifact download refused (HTTP '+response.status+').');
      const bytes=await response.arrayBuffer();
      const digest=await global.crypto.subtle.digest('SHA-256',bytes),actual=Array.from(new Uint8Array(digest),b=>b.toString(16).padStart(2,'0')).join('');
      const disposition=response.headers.get('Content-Disposition')||'',declaredName=/filename="([^"\\]+)"/.exec(disposition)?.[1];
      const declaredLength=response.headers.get('Content-Length'),encoding=response.headers.get('Content-Encoding');
      const verifyLength=declaredLength!==null&&(!encoding||encoding.toLowerCase()==='identity');
      const invalidLength=verifyLength&&(!/^\d+$/.test(declaredLength)||!Number.isSafeInteger(Number(declaredLength))||Number(declaredLength)!==bytes.byteLength);
      if(actual!==sha256||response.headers.get('X-Content-Sha256')!==sha256||response.headers.get('Content-Type')?.split(';')[0]!==mimeType||declaredName!==filename||invalidLength)throw Error('Artifact integrity, MIME, filename or length read-back did not match.');
      if(disposed||token!==generation||project!==state.selectedProjectId||key!==JSON.stringify([state.organisationId,state.session.userId,state.session.memberships]))throw Error('Scope changed while downloading.');
      return {blob:new global.Blob([bytes],{type:mimeType}),filename,mimeType,sha256,byteSize:bytes.byteLength};
    }
    async function write(path, payload, options = {}) {
      assertReady();
      if (!options.readBackPath) throw Error('An exact read-back path is required before writing.');
      const method = String(options.method || 'POST').toUpperCase();
      if (!['POST', 'PUT', 'DELETE'].includes(method)) throw Error('Unsupported write method.');
      if (pendingWrite) throw Error('A write is already pending.');
      const token = generation, projectId = state.selectedProjectId;
      if (options.projectScoped !== false && !projectId) throw Error('Select an authorised project before writing.');
      if (options.projectScoped !== false && payload?.projectId && payload.projectId !== projectId) throw Error('Payload project does not match the current context.');
      // Planning refreshes its concurrency precondition before retrying an accepted
      // entry. Keep business values (including nested payload) and exact scope in
      // the receipt; only this explicit precondition is excluded, never the POST.
      const receiptPayload = options.retryPrecondition === 'expectedVersion'
        ? Object.fromEntries(Object.entries(payload).filter(([key]) => key !== 'expectedVersion')) : payload;
      const receiptKey = JSON.stringify([state.session.userId, state.organisationId, projectId, method, safePath(path), receiptPayload, typeof options.readBackPath === 'string' ? options.readBackPath : null, options.readBackCollection, options.readBackParentId]);
      pendingWrite = true;
      try {
      let saved = acceptedWrites.get(receiptKey);
      if (!saved) {
        const send = () => method === 'DELETE' ? api.del(safePath(path), payload)
          : method === 'PUT' ? api.put(safePath(path), payload)
          : api.post(safePath(path), payload);
        try { saved = await send(); }
        catch (error) {
          // The server's CSRF middleware rejects before executing the controller.
          if (error.status !== 419 || error.code !== 'csrf_invalid') throw error;
          const session = await api.get('/session');
          if (disposed || token !== generation || session.userId !== state.session.userId || !session.memberships?.some(member => member.organisationId === state.organisationId)) throw Error('Authentication changed while refreshing CSRF.');
          api.setCsrf(session.csrfToken);
          state.session = session;
          saved = await send();
        }
        acceptedWrites.set(receiptKey, saved);
      }
      const target = typeof options.readBackPath === 'function' ? options.readBackPath(saved) : options.readBackPath;
      if (method === 'DELETE') {
        // A delete is only accepted once the record is genuinely gone: reading it back
        // must fail closed with 404, not merely return a cached or optimistic value.
        let absence = false;
        try { await api.get(safePath(target)); } catch (error) { if (error && error.status === 404) absence = true; else throw error; }
        if (!absence) throw Error('The delete was accepted but the record is still readable.');
        if (disposed || token !== generation) throw Error('Project context changed after the write. Refresh the original project.');
        acceptedWrites.delete(receiptKey);
        return saved;
      }
      const persisted = await api.get(safePath(target));
      const expectedId = saved?.id || saved?.conversationId;
      // A pending-review queue removes completed records. Verify only the exact
      // acknowledged independent-review transition, not a generic empty list.
      if(options.readBackAbsentId){
        if(options.projectScoped!==false||safePath(path)!=='/verifications/'+options.readBackAbsentId+'/review'||safePath(target)!=='/verification-review-queue'||expectedId!==options.readBackAbsentId||!['manually_reviewed','externally_verified','rejected'].includes(saved?.grade)||saved.grade!==payload?.decision||!Array.isArray(persisted))throw Error('Invalid independent-review read-back contract.');
        if(persisted.some(record=>record?.id===options.readBackAbsentId))throw Error('The verification is still pending; review persistence was not confirmed.');
        if(disposed||token!==generation)throw Error('Organisation context changed after the review.');
        acceptedWrites.delete(receiptKey);return saved;
      }
      if (options.readBackParentId && persisted?.id !== options.readBackParentId) throw Error('Read-back parent identity did not match.');
      if (persisted?.projectId && options.projectScoped !== false && persisted.projectId !== projectId) throw Error('Read-back parent project does not match the write context.');
      const collection = options.readBackCollection ? persisted?.[options.readBackCollection] : persisted;
      if (options.readBackCollection && !Array.isArray(collection)) throw Error('The read-back collection is unavailable.');
      const record = Array.isArray(collection) ? collection.find(item => item.id === expectedId) : collection;
      if (expectedId && record?.id !== expectedId) throw Error('Write accepted, but read-back identity did not match. Refresh before retrying.');
      if (record?.projectId && options.projectScoped !== false && record.projectId !== projectId) throw Error('Read-back project does not match the write context.');
      if (disposed || token !== generation) throw Error('Project context changed after the write. Refresh the original project.');
      acceptedWrites.delete(receiptKey);
      return saved;
      } finally { pendingWrite = false; }
    }
    return {
      bootstrap, getState, selectProject, setMode, reloadProjects, read, write, download,
      // A newer route can supersede a draft-guard wait without invalidating data reads.
      cancelNavigation() { navigationGeneration++; },
      capabilities: Object.freeze({ readBackCollection: true, methods: Object.freeze(['POST', 'PUT', 'DELETE']) }),
      registerDraftGuard(guard) { guards.add(guard); return () => guards.delete(guard); },
      subscribe(listener) { listeners.add(listener); return () => listeners.delete(listener); },
      dispose() { disposed = true; generation++; state.ready = false; listeners.clear(); guards.clear(); acceptedWrites.clear(); },
    };
  }
  global.V1CloneContext = { create };
})(window);
