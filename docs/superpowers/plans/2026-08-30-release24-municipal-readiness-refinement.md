# Release 24 Municipal Readiness Refinement Implementation Plan

**Design:** `docs/superpowers/specs/2026-08-30-municipal-readiness-refinement-design.md`  
**Pack:** `packs/28. architex-municipal-readiness-refinement` (current v0.2 and legacy v0.1 read in full)  
**Execution:** inline, test-first, sequential commits

## 1. Domain contract

- [x] Add focused unit tests for application types, profile validation, finding severities, route issue gates and canonical manifest wording.
- [x] Implement a framework-independent municipal refinement policy that normalises profiles, derives finding/route workflow state and rejects prohibited statutory claims.
- [x] Run the focused tests and PHP syntax checks.
- [x] Commit the domain contract.

## 2. MariaDB persistence

- [x] Add migration `0054_municipal_readiness_refinement.sql` for project profiles, materialised findings, professional routes and immutable submission packs.
- [x] Add schema assertions for foreign keys, tenant/project indexes, unique pack versions and immutable hash fields.
- [x] Rebuild a guarded disposable database through every migration and run the schema tests.
- [x] Commit the persistence schema.

## 3. Governed service lifecycle

- [x] Add failing integration tests for profile upsert/read, refined assessment materialisation, finding resolution, route assignment/sign-off and tenant isolation.
- [x] Implement profile persistence and enrich new assessments without changing existing response compatibility.
- [x] Materialise findings and required routes transactionally with the assessment.
- [x] Add stable workflow reads and audited state transitions.
- [x] Add fail-closed pack issue tests for unverified matrices, missing review, open blockers, unsigned routes, candidate supervision and missing authority.
- [x] Implement transactional issue, canonical manifest hashing, ProjectRecord/inbox/audit writes and prior-pack supersession.
- [x] Prove issued rows remain immutable and changed evidence requires a new assessment/revision.
- [x] Commit the service lifecycle.

## 4. Authenticated API

- [x] Add HTTP tests for all profile, workflow, resolution, route and pack endpoints, including CSRF, role and cross-tenant failures.
- [x] Add controller methods and authenticated routes using existing read/write/govern middleware.
- [x] Map stable domain errors to 403, 404, 409 and 422 responses.
- [x] Wire dependencies through `AuthenticationKernelFactory` without a second service graph.
- [x] Run focused HTTP and existing municipal regression tests.
- [x] Commit the API surface.

## 5. Canonical workspace

- [x] Extend the frontend contract test for the profile editor, status dashboard, findings, professional routes, issue gate and immutable pack history.
- [x] Implement the workspace within the existing `shell.js` municipal route and visual system.
- [x] Add responsive CSS, accessible names and live status output.
- [ ] Exercise profile save, assessment, resolution, assignment, sign-off and issue through the real UI against MariaDB.
- [x] Commit the workspace.

## 6. Release certification

- [ ] Run PHP syntax, JavaScript syntax, focused municipal tests and the complete test suite.
- [ ] Rebuild all migrations on a reserved disposable MariaDB target and record exact assertions/skips.
- [ ] Verify an authenticated blocked-to-issued lifecycle and database row counts.
- [ ] Use the persistent Chrome DevTools MCP browser to inspect desktop and 390 x 844 views, console/network output, accessibility and horizontal overflow.
- [ ] Capture desktop/mobile screenshots and Lighthouse evidence.
- [ ] Build the staged shared-hosting artifact from the exact certified source commit, excluding local secrets and development-only files.
- [ ] Verify the artifact manifest, fresh ZIP extraction, PHP syntax and SHA-256.
- [ ] Write Release 24 evidence with local-versus-remote boundaries and check every plan item.
- [ ] Move Pack 28 into `packs/Completed` outside Git while retaining its source ZIP under `packs/ZIP`.
- [ ] Commit certification documentation.

## Release boundary

Release 24 is complete when the exact source commit, disposable MariaDB lifecycle, authenticated browser workflow and staged artifact all pass. FTP upload, production secrets, remote migration, smoke tests and staged promotion remain explicitly uncertified until performed against the shared host.
