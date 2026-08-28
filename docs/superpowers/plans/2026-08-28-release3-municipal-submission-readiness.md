# Release 3 Municipal Submission Readiness Implementation Plan

**Goal:** Deliver a tenant-safe, evidence-led municipal submission-readiness workflow across the whole professional team, without claiming statutory approval or pretending to lodge with a municipality.

**Architecture:** Add immutable municipality requirement versions and immutable project assessment snapshots. A deep `MunicipalReadinessService` reads live project, appointment, project-team, controlled-document, ProjectRecord and compliance evidence; combines those facts with explicit human-entered scope triggers; calculates eight category scores; routes only required disciplines; creates deduplicated role inbox tasks; and assembles a checksum-bound evidence pack. Human approval records review of the exact assessment only and never represents municipal acceptance.

**Tech Stack:** PHP 8.3, PDO, MariaDB 11.4-compatible SQL, canonical V1 HTML/CSS/JavaScript, PHPUnit 12, Chrome DevTools MCP.

## Global constraints

- Treat every supplied pack item as unimplemented until executable repository evidence exists.
- Preserve organisation, user, project and role scope at every seam.
- Municipality matrices are editable only by creating new immutable versions; issued assessments retain their source snapshot.
- Generic supplied requirements remain `review_required`; only an authorised administrator can create and verify a sourced municipal version.
- Do not infer property, zoning, drawing, appointment, professional or sign-off facts that are absent.
- Do not create a fake council integration, application reference or lodgement state.
- Make advisory scope, assumptions, confidence and required human review visible.
- Route candidate professionals to supervising/registered review where the stored team evidence is insufficient.
- Execute inline only; do not dispatch subagents.

### Task 1: Versioned municipal requirements and immutable assessments

**Files:**
- Create: `app/database/migrations/0021_municipal_submission_readiness.sql`
- Create: `app/src/Platform/Municipal/MunicipalReadinessException.php`
- Create: `app/src/Platform/Municipal/MunicipalRequirementRegistry.php`
- Test: `app/tests/Integration/Municipal/MunicipalRequirementRegistryTest.php`

- [ ] Write a failing MariaDB test for tenant scope, review-required seed, immutable versions, verification and role enforcement.
- [ ] Add versioned municipal matrices with municipality key, source reference, effective date, payload checksum, status and reviewer evidence.
- [ ] Add immutable readiness assessments and professional review records.
- [ ] Prove old versions and assessment snapshots cannot be mutated or silently replaced.

### Task 2: Whole-team readiness engine

**Files:**
- Create: `app/src/Platform/Municipal/MunicipalReadinessService.php`
- Test: `app/tests/Integration/Municipal/MunicipalReadinessServiceTest.php`

- [ ] Write failing tests for low/high complexity, required/optional/excluded disciplines, all eight readiness categories and source status.
- [ ] Read the project passport, latest appointment, active team, controlled documents, ProjectRecords and compliance ToolRuns from MariaDB.
- [ ] Calculate category and overall scores, explicit blockers, confidence, assumptions, missing documents and human sign-offs.
- [ ] Assemble a checksum-bound evidence pack, recommendations, audit event and only the triggered role inbox tasks.
- [ ] Fail closed on professional-review approval unless the matrix is verified and explicit human confirmation is supplied.

### Task 3: Authenticated municipal API

**Files:**
- Create: `app/src/Http/MunicipalReadinessRoutes.php`
- Create: `app/src/Http/Controller/MunicipalReadinessController.php`
- Modify: `app/src/Http/AuthenticationKernelFactory.php`
- Test: `app/tests/Integration/Http/MunicipalReadinessHttpTest.php`

- [ ] Write the failing owner/member authenticated workflow through the real kernel.
- [ ] Expose matrix listing/creation/verification and project assessment create/list/show/approve endpoints.
- [ ] Enforce CSRF, permissions, tenant boundary, role boundary, source state and input validation.
- [ ] Return the existing API envelope and stable failure reasons.

### Task 4: Canonical V1 readiness dashboard

**Files:**
- Modify: `app/public/assets/js/shell.js`
- Modify: `app/public/assets/css/architex-v1.css`
- Test: `app/tests/Frontend/MunicipalReadinessWorkspaceTest.php`

- [ ] Write the failing frontend contract before the workspace implementation.
- [ ] Build the native V1 dashboard with complexity inputs, matrix governance, overall/category scores, blockers, team routing, excluded disciplines, missing documents, evidence pack, sign-offs and next-best actions.
- [ ] Keep human approval and no-statutory-approval boundaries visible.
- [ ] Verify escaping, reload persistence and responsive behavior.

### Task 5: Local release evidence

**Files:**
- Create: `docs/evidence/2026-08-28-release3-municipal-submission-readiness.md`

- [ ] Run schema, service, HTTP, frontend, security and applicable regression suites.
- [ ] Apply migration to the bounded live development database and prove counts.
- [ ] Use persistent Chrome DevTools to create and reload a real assessment, inspect all required/excluded team decisions, and verify responsive layout and clean console.
- [ ] Read MariaDB directly for source checksum, assessment checksum, category scores, evidence, inbox deduplication, ProjectRecord and audit events.
- [ ] State local proof separately from shared-hosting staging certification.
