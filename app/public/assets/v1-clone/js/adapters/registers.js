(function (root) {
  'use strict';
  // Controlled-register adapter — the governed Dispute Resolution and NHBRC
  // Enrolment registers (ControlledRegisterService / ControlledRegisterPolicy).
  // Every create/transition is human-confirmed, project-scoped and read-back
  // verified against the real workspace endpoint. HONEST BOUNDARY: an internal
  // register entry is recorded evidence only — it is never a statutory
  // submission, an NHBRC certificate, or an inferred legal resolution.
  const REGISTERS = ['dispute_resolution', 'nhbrc_enrolment'];
  const unwrap = r => (r && typeof r === 'object' && 'data' in r ? r.data : r);
  function create(context) {
    function scope() {
      const s = context.getState();
      if (!s.ready || !s.session || !s.organisationId || !/^[a-f0-9]{32}$/i.test(s.selectedProjectId || '')) throw Error('Select an authenticated project.');
      return { ...s };
    }
    function current(s) {
      const now = context.getState();
      if (!now.ready || !now.session || now.selectedProjectId !== s.selectedProjectId || now.organisationId !== s.organisationId || now.session !== s.session) throw Error('Scope changed; reload this workspace.');
    }
    function key(registerKey) {
      if (!REGISTERS.includes(registerKey)) throw Error('Unsupported controlled register.');
      return registerKey;
    }
    const registerPath = (s, k) => '/projects/' + encodeURIComponent(s.selectedProjectId) + '/controlled-registers/' + encodeURIComponent(k);
    function workspaceOf(data) {
      const ws = unwrap(data);
      if (!ws || !Array.isArray(ws.entries)) throw Error('Invalid controlled register response.');
      return ws;
    }
    function entriesOf(ws, k) { return ws.entries.filter(r => r && r.registerKey === k); }
    function text(value, max, error) {
      const t = typeof value === 'string' ? value.trim() : '';
      if (!t || t.length > max) throw Error(error);
      return t;
    }
    function date(value) {
      if (value === undefined || value === null || value === '') return null;
      if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(value)) throw Error('Due date must be an ISO date (YYYY-MM-DD).');
      return value;
    }
    function details(value) {
      if (!value || typeof value !== 'object' || Array.isArray(value) || Object.keys(value).length === 0) throw Error('Register entry details must be a recorded object.');
      return value;
    }
    function evidence(value) {
      if (value === undefined || value === null) return [];
      if (!Array.isArray(value) || value.length > 50) throw Error('Evidence file ids must be a list of at most 50 stored-file ids.');
      value.forEach(id => { if (typeof id !== 'string' || !/^[a-f0-9]{32}$/i.test(id)) throw Error('Evidence file ids must be 32-character stored-file ids.'); });
      return value.map(id => id.toLowerCase());
    }
    // Full governed workspace: definition (initial status + allowed transitions),
    // server guardrails, and exactly the entries for this register.
    async function workspace(registerKey) {
      const s = scope(), k = key(registerKey);
      const ws = workspaceOf(await context.read(registerPath(s, k)));
      current(s);
      return { definition: ws.definition || null, guardrails: ws.guardrails || {}, entries: entriesOf(ws, k) };
    }
    async function listEntries(registerKey) {
      return (await workspace(registerKey)).entries;
    }
    async function createEntry(registerKey, input) {
      const s = scope(), k = key(registerKey);
      if (s.readOnly) throw Error('Read-only session.');
      if (input.humanConfirmed !== true) throw Error('Controlled register entry requires explicit human confirmation.');
      const body = {
        title: text(input.title, 220, 'A register entry title is required.'),
        reference: text(input.reference, 120, 'A register reference is required.'),
        ownerRole: text(input.ownerRole, 80, 'An owner role is required.'),
        dueOn: date(input.dueOn),
        details: details(input.details),
        evidenceFileIds: evidence(input.evidenceFileIds),
        humanConfirmed: true,
      };
      const saved = unwrap(await context.write(registerPath(s, k), body, { readBackPath: registerPath(s, k), readBackCollection: 'entries' }));
      current(s);
      const ws = workspaceOf(await context.read(registerPath(s, k)));
      current(s);
      const record = entriesOf(ws, k).find(r => r.id === saved?.id);
      if (!record) throw Error('Controlled register persistence not verified; refresh before retrying.');
      return record;
    }
    async function transitionEntry(entryId, input) {
      const s = scope();
      if (s.readOnly) throw Error('Read-only session.');
      if (input.humanConfirmed !== true) throw Error('Controlled register transition requires explicit human confirmation.');
      if (!/^[a-f0-9]{32}$/i.test(entryId || '')) throw Error('A valid controlled register entry id is required.');
      const expectedVersion = input.expectedVersion;
      if (!Number.isInteger(expectedVersion) || expectedVersion < 1) throw Error('A current entry version (expectedVersion) is required.');
      const status = text(input.status, 80, 'A target status is required.');
      const reason = text(input.reason, 1000, 'A transition reason is required.');
      const saved = unwrap(await context.write(
        '/controlled-registers/' + encodeURIComponent(entryId) + '/transition',
        { expectedVersion, status, reason, humanConfirmed: true },
        { readBackPath: entry => registerPath(s, key(entry?.registerKey)), readBackCollection: 'entries' }
      ));
      current(s);
      const k = key(saved?.registerKey);
      const ws = workspaceOf(await context.read(registerPath(s, k)));
      current(s);
      const record = entriesOf(ws, k).find(r => r.id === entryId);
      if (!record || record.status !== status || record.version !== expectedVersion + 1) throw Error('Controlled register transition not verified; refresh before retrying.');
      return record;
    }
    return { workspace, listEntries, createEntry, transitionEntry };
  }
  root.V1CloneRegistersAdapter = { create, registers: REGISTERS };
})(window);
