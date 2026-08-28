# Release 3 Transmittals and Drawing Intelligence Completion Plan

**Goal:** Complete the Pack 3 evidence layer with immutable document transmittals and a real, provider-honest drawing-intelligence candidate workflow.

**Architecture:** Keep controlled revisions as the source of truth. Transmittals snapshot exact approved/issued revision IDs, recipients, purpose and issue reason under SHA-256 and cannot mutate their contents after issue. Drawing-intelligence candidates store structured metadata proposed by a human or a future configured provider; candidates never silently change document metadata, require exact human accept/reject review, and preserve provider/provenance/confidence fields without fabricating OCR or AI results.

**Tech Stack:** PHP 8.3, PDO, MariaDB 11.4-compatible SQL, canonical V1 HTML/CSS/JavaScript, PHPUnit 12, Chrome DevTools MCP.

## Global constraints

- Treat every Pack 3 claim as unimplemented until repository evidence proves it.
- No fake OCR, PDF parsing, AI classification, digital signature, email delivery or municipal integration.
- Human-entered metadata must be labelled human-entered; unavailable provider outputs must remain unavailable.
- Issued documents and transmittals are immutable and revised/superseded rather than edited.
- Candidate-professional formal outputs require supervising professional review.
- Keep tenant, user, project, revision and role scope on every operation.
- Execute inline only; do not dispatch subagents.

### Task 1: Immutable transmittal spine

**Files:**
- Create: `app/database/migrations/0022_document_transmittals_intelligence.sql`
- Create: `app/src/Platform/Documents/DocumentTransmittalService.php`
- Test: `app/tests/Integration/Documents/DocumentTransmittalServiceTest.php`

- [x] Write failing tests for draft creation, exact revision selection, eligibility, issue confirmation, SHA-256 snapshot and immutability.
- [x] Persist project-scoped transmittals and item links with deduplicated codes.
- [x] Create issued ProjectRecord, audit event and recipient-role inbox evidence without claiming delivery.
- [ ] Prove cross-tenant and post-issue mutation attempts fail closed.

### Task 2: Provider-honest drawing-intelligence candidates

**Files:**
- Create: `app/src/Platform/Documents/DrawingIntelligenceService.php`
- Test: `app/tests/Integration/Documents/DrawingIntelligenceServiceTest.php`

- [ ] Write failing tests for the complete output shape, provenance, nullable confidence, findings and exact human review.
- [ ] Accept human-entered candidates now and reserve explicit future-provider provenance states.
- [ ] Never infer or auto-apply title-block, classification, discipline, revision or issue-purpose metadata.
- [ ] Persist accept/reject reason, reviewer, checksum, ProjectRecord and audit evidence.

### Task 3: Authenticated API and canonical V1 workspace

**Files:**
- Create: `app/src/Http/DocumentEvidenceRoutes.php`
- Create: `app/src/Http/Controller/DocumentEvidenceController.php`
- Modify: `app/src/Http/AuthenticationKernelFactory.php`
- Modify: `app/public/assets/js/shell.js`
- Modify: `app/public/assets/css/architex-v1.css`
- Test: `app/tests/Integration/Http/DocumentEvidenceHttpTest.php`
- Test: `app/tests/Frontend/DocumentEvidenceWorkspaceTest.php`

- [ ] Add failing authenticated API and frontend contracts first.
- [ ] Expose transmittal create/list/show/issue and intelligence candidate create/list/review operations.
- [ ] Add native sections to Documents & Drawings with explicit provider and delivery boundaries.
- [ ] Verify escaping, revision eligibility, responsive behavior and reload persistence.

### Task 4: Local release evidence

**Files:**
- Create: `docs/evidence/2026-08-28-release3-transmittals-drawing-intelligence.md`

- [ ] Run schema, service, HTTP, frontend, security and applicable regression suites.
- [ ] Apply the migration only to the bounded live development database.
- [ ] Use persistent Chrome DevTools to create/review a human-entered candidate and issue an exact-revision transmittal.
- [ ] Correlate browser state with MariaDB snapshots, hashes, reviews, ProjectRecords, inbox and audit events.
- [ ] Keep local proof separate from shared-hosting staging certification.
