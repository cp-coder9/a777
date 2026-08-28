# Release 3 BIM/IFC Metadata Plan

**Goal:** Add a production-responsible, tenant-scoped model-source register and versioned metadata workflow that records provenance, validates structured model facts, requires professional review, and issues an immutable project record without claiming native BIM interpretation.

**Architecture:** Projects own immutable model-source records and append-only metadata revisions. A source links to a controlled uploaded file when available and records its exact SHA-256, format, schema, source revision and extraction provenance. Metadata is supplied by a human or a structured export and stored as a canonical fact list covering model, spatial, element, schedule, site and drawing-reference information. Deterministic validation reports gaps and duplicate identifiers. Review decisions are append-only and role-scoped. Formal issue snapshots one approved revision under SHA-256 and creates a ProjectRecord. Native IFC, DWG and Revit interpretation remains explicitly unavailable until a real parser/provider is configured.

**Tech Stack:** PHP 8.3, PDO, MariaDB 11.4-compatible SQL, canonical V1 HTML/CSS/JavaScript, PHPUnit 12, Chrome DevTools MCP.

## Global constraints

- Treat all BIM/model pack status and embedded demo data as unimplemented until executed evidence proves otherwise.
- Preserve the canonical V1 shell, project context and professional-role vocabulary.
- Never claim that Architex parsed IFC, RVT, DWG, DXF, BCF or schedules when the metadata was human-entered or supplied by an external export.
- Every source and revision must expose source revision, provenance, extraction mode, confidence boundary, assumptions and required manual/professional verification.
- Keep organisation, project, user, role and exact revision scope on every operation.
- Approved and issued revisions are immutable; corrections create a new metadata revision.
- Formal issue requires an authorised project professional, explicit human confirmation and a reason.
- Execute inline only; do not dispatch subagents.

### Task 1: Controlled model sources and structured metadata

**Files:**
- Create: `app/database/migrations/0024_bim_ifc_metadata.sql`
- Create: `app/src/Platform/Models/ModelMetadataException.php`
- Create: `app/src/Platform/Models/ModelMetadataService.php`
- Modify: `app/src/Platform/Files/FileService.php`
- Test: `app/tests/Integration/Models/ModelMetadataServiceTest.php`

- [x] Write failing tests for safe IFC source upload, source registration, metadata revision creation, tenant isolation and stale-version rejection.
- [x] Store exact source filename, format, schema, source revision, file/SHA provenance and extraction mode.
- [x] Accept a bounded canonical fact list for rooms, areas, levels, elements, openings, fire routes, parking, site controls, schedules, notes and title-block metadata.
- [x] Derive deterministic category counts, duplicate external identifiers, missing source references and verification gaps.
- [x] Reject unsupported native-parsing claims and expose provider availability as false.

### Task 2: Professional review and immutable issue

- [x] Write failing tests for role-scoped approve/reject/flag decisions, required reasons and explicit confirmation.
- [x] Store append-only decisions with actor, acting role and timestamp.
- [x] Block approval on validation errors and block issue until the selected revision is approved.
- [x] Snapshot the exact source, metadata, findings, assumptions, decision and professional confirmation under SHA-256.
- [x] Create a ProjectRecord and prove issued revisions reject mutation.

### Task 3: Authenticated API and canonical V1 workspace

**Files:**
- Create: `app/src/Http/ModelMetadataRoutes.php`
- Create: `app/src/Http/Controller/ModelMetadataController.php`
- Modify: `app/src/Http/AuthenticationKernelFactory.php`
- Modify: `app/public/assets/js/shell.js`
- Modify: `app/public/assets/css/architex-v1.css`
- Test: `app/tests/Integration/Http/ModelMetadataHttpTest.php`
- Test: `app/tests/Frontend/ModelMetadataWorkspaceTest.php`

- [ ] Add failing API and frontend contracts first.
- [ ] Expose source, revision, decision and issue commands with session, CSRF, tenant and permission enforcement.
- [ ] Render real loading, empty, error, validation, review and issued states inside the canonical project workspace.
- [ ] Label native BIM parsing and external-provider functionality honestly and visibly.

### Task 4: Local evidence

**Files:**
- Create: `docs/evidence/2026-08-28-release3-bim-ifc-metadata.md`

- [ ] Run focused and applicable regression suites against the disposable MariaDB database.
- [ ] Apply migration only to the bounded live development database after tests pass.
- [ ] Use persistent Chrome DevTools to upload/register a source, create metadata, review and issue it, then prove reload persistence and responsive layout.
- [ ] Correlate browser output with MariaDB source hash, issued snapshot SHA-256, ProjectRecord and audit evidence.
- [ ] Keep local proof separate from shared-hosting staged-release certification.
