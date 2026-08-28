# Release 3 SpecForge Core Plan

**Goal:** Replace the canonical SpecForge demo state with a tenant-scoped, project-connected specification authoring, review, risk and immutable issue workflow.

**Architecture:** A project owns one or more versioned specification workspaces. Draft sections and items remain editable under optimistic version checks. Item decisions are append-only review evidence and update the current draft state transactionally. Formal issue snapshots the exact workspace, sections, items, budget/lead-time findings and professional confirmation under SHA-256, creates a controlled specification document revision plus ProjectRecord, and cannot be mutated. Procurement, RFQ, payment, delivery, OCR and AI suggestions remain explicitly unavailable until their later releases/providers exist.

**Tech Stack:** PHP 8.3, PDO, MariaDB 11.4-compatible SQL, canonical V1 HTML/CSS/JavaScript, PHPUnit 12, Chrome DevTools MCP.

## Global constraints

- Treat the embedded SpecForge prototype and every pack status as unimplemented until executed evidence proves otherwise.
- Preserve the canonical terminology and information hierarchy: Overview, Sections, Products, Approvals, Budget & Risk, Document Preview, Issue & Distribute.
- Never claim AI drawing analysis, external delivery, supplier ordering, RFQs, escrow, digital signature or PDF export.
- Keep organisation, project, user, role and exact source-version scope on every operation.
- Issued workspaces and document revisions are immutable; corrections create a new workspace revision.
- Formal issue requires explicit human professional-responsibility confirmation and an issue reason.
- Execute inline only; do not dispatch subagents.

### Task 1: Persisted specification workspace

**Files:**
- Create: `app/database/migrations/0023_specforge_core.sql`
- Create: `app/src/Platform/SpecForge/SpecForgeException.php`
- Create: `app/src/Platform/SpecForge/SpecForgeService.php`
- Test: `app/tests/Integration/SpecForge/SpecForgeServiceTest.php`

- [ ] Write failing tests for workspace creation, sections, items, tenant isolation and stale-version rejection.
- [ ] Persist exact product/specification fields, source revision, cost allowance, estimate and lead time.
- [ ] Derive budget, long-lead, client-decision and approval findings from current MariaDB records.

### Task 2: Review and immutable issue

- [ ] Write failing tests for role-scoped decisions, rejection reasons and professional confirmation.
- [ ] Store append-only approve/reject/flag decisions with actor, role and timestamp.
- [ ] Block issue while required client decisions or unapproved items remain.
- [ ] Snapshot the exact issued specification under SHA-256 and create controlled document/ProjectRecord/audit evidence.
- [ ] Prove issued records reject mutation and external delivery remains false.

### Task 3: Authenticated API and canonical V1 workspace

**Files:**
- Create: `app/src/Http/SpecForgeRoutes.php`
- Create: `app/src/Http/Controller/SpecForgeController.php`
- Modify: `app/src/Http/AuthenticationKernelFactory.php`
- Modify: `app/public/assets/js/shell.js`
- Modify: `app/public/assets/css/architex-v1.css`
- Test: `app/tests/Integration/Http/SpecForgeHttpTest.php`
- Test: `app/tests/Frontend/SpecForgeWorkspaceTest.php`

- [ ] Add failing API and frontend contracts first.
- [ ] Expose project workspace, section, item, decision and issue commands with CSRF/session/permission enforcement.
- [ ] Render real empty/loading/error states and persisted canonical SpecForge views.
- [ ] Label unavailable provider and Release 4 actions honestly.

### Task 4: Local evidence

**Files:**
- Create: `docs/evidence/2026-08-28-release3-specforge-core.md`

- [ ] Run focused and applicable regression suites against the disposable MariaDB database.
- [ ] Apply migration only to the bounded live development database.
- [ ] Use persistent Chrome DevTools to create, review and issue a specification, then prove reload persistence and responsive layout.
- [ ] Correlate browser output with MariaDB snapshot hash, document revision, ProjectRecord and audit evidence.
- [ ] Keep local proof separate from shared-hosting staged-release certification.
