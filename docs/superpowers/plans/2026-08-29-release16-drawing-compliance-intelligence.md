# Release 16 Drawing Compliance Intelligence Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use test-driven-development and execute inline; this workspace does not use subagents for this release.

**Goal:** Deliver Pack 20 as a source-versioned, professional-gated drawing and plan-submission compliance workflow.

**Architecture:** Add a focused compliance service and MariaDB snapshot model behind the existing drawing checker/toolbox. Reuse ProjectRecords, Inbox and Municipal Readiness only through explicit saved reviews, while preserving the supplied illustrative-source boundary.

**Tech Stack:** PHP 8.3, MariaDB 11.4, vanilla JavaScript/CSS, PHPUnit 12, Chrome DevTools MCP.

## Global Constraints

- POC rules remain `illustrative_poc_until_verified`; production reliance requires licensed/verified sources.
- Never claim municipal approval, AI professional certification or guaranteed compliance.
- Missing values produce `needs_input`; dimensions and evidence are never invented.
- One service supports standalone and project-aware modes.
- Canonical `v1.html` and `packs/` remain untouched and untracked.

### Task 1: Source registry and deterministic review engine

**Files:** Create `app/database/migrations/0038_drawing_compliance_intelligence.sql`, `app/src/Platform/DrawingCompliance/DrawingComplianceService.php`, `app/src/Platform/DrawingCompliance/DrawingComplianceException.php`; test `app/tests/Unit/DrawingCompliance/DrawingComplianceServiceTest.php` and `app/tests/Integration/DrawingCompliance/DrawingCompliancePersistenceTest.php`.

- [ ] Write failing tests for source status, Part K boundary-wall pass/watch/fail/needs-input, occupancy, package completeness, internal references, SPLUMA triggers and no-go wording.
- [ ] Run red tests, implement validated deterministic evaluation and source-versioned MariaDB snapshots, then rerun green.

### Task 2: Project write-backs and governed review

**Files:** Modify the drawing-compliance service; test `app/tests/Integration/DrawingCompliance/DrawingComplianceWriteBackTest.php`.

- [ ] Write failing tests proving watch/fail/needs-input creates audited ProjectRecord and inbox/readiness blocker only after explicit human save.
- [ ] Implement project/drawing revision binding, snapshot SHA-256, responsible-role and professional-review status.
- [ ] Verify cross-tenant denial and immutable saved snapshot behavior.

### Task 3: Authenticated API and toolbox registration

**Files:** Create `app/src/Http/Controller/DrawingComplianceController.php`, `app/src/Http/DrawingComplianceRoutes.php`; modify `app/src/Http/AuthenticationKernelFactory.php` and `app/src/Platform/Toolbox/ProductionToolRegistry.php`; add HTTP tests.

- [ ] Write failing route tests for source data, checklist build, review save, read and export endpoints.
- [ ] Register permission-wrapped routes and the SA Council Drawing Compliance Navigator toolbox definition.
- [ ] Run focused and connected HTTP suites to green.

### Task 4: Drawing checker and navigator UI

**Files:** Modify `app/public/assets/js/shell.js` and `app/public/assets/css/architex-v1.css`; create `app/tests/Frontend/DrawingComplianceNavigatorTest.php`.

- [ ] Write a failing frontend contract for project prefill, manual checklist states, source badges, missing-input cards, SPLUMA/package/reference layers and professional boundary.
- [ ] Build one responsive UI used by standalone and project-aware entry points, backed only by authenticated APIs.
- [ ] Run JavaScript syntax and frontend tests to green.

### Task 5: Release certification

**Files:** Create `docs/evidence/2026-08-29-release16-drawing-compliance-intelligence.md` and screenshots.

- [ ] Run focused/connected suites against only the disposable database.
- [ ] Apply migration 0038 to local development and verify snapshot/write-back/audit hashes.
- [ ] Prove standalone and project-aware flows in persistent Chrome, responsive geometry, Lighthouse and clean console.
- [ ] Recheck canonical SHA-256, stage only release files, and commit.
