# Release 3 Document Control Spine Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the tenant- and project-scoped document/drawing evidence spine required by all later Release 3 and delivery packs.

**Architecture:** Store document identity separately from append-only revisions and drawing metadata. Draft content may be edited, but reviewed or issued content can change only through a linked revision; issue and approval are explicit human actions. Readiness checks operate only on structured, human-entered metadata and never claim OCR, PDF parsing, AI classification, signature, or municipal-provider integration.

**Tech Stack:** PHP 8.3, PDO, MariaDB 11.4-compatible SQL, canonical V1 HTML/CSS/JavaScript, PHPUnit 12, Chrome DevTools MCP.

## Global Constraints

- Preserve the canonical `v1.html` shell and existing tool identity.
- Enforce organisation and project scope on every read and write.
- Support the 13 pack document types, eight disciplines, seven issue purposes and eight drawing sheet types.
- Require human confirmation for professional review and formal issue.
- Issued revisions are immutable and superseded revisions remain visible.
- Private file storage may be linked by an existing same-project file ID; no provider simulation.
- Local evidence is not staging or production certification.
- Execute inline only; do not dispatch subagents.

---

### Task 1: MariaDB document and revision model

**Files:**
- Create: `app/database/migrations/0019_document_control_spine.sql`
- Create: `app/src/Platform/Documents/DocumentException.php`
- Create: `app/src/Platform/Documents/DocumentService.php`
- Test: `app/tests/Integration/Documents/DocumentServiceTest.php`

**Interfaces:**
- Consumes: active organisation membership, project IDs, private project-file IDs, `AuditService`, `ProjectRecordService` and inbox tables.
- Produces: `DocumentService::create`, `updateDraft`, `submitForReview`, `approve`, `issue`, `revise`, `get`, `list`, `readiness`.

- [ ] **Step 1: Write the failing service test**

```php
$draft=$service->create($org,$user,'owner',['projectId'=>$project,'title'=>'A-101 Plans','documentType'=>'drawing','discipline'=>'architectural','phase'=>'design_development','issuePurpose'=>'for_review','revisionCode'=>'P01','drawing'=>['drawingNumber'=>'A-101','sheetType'=>'floor_plan','scale'=>'1:100']]);
$issued=$service->issue($org,$user,'owner',$service->approve($org,$user,'owner',$service->submitForReview($org,$user,'owner',$draft['id'],'Ready')['id'],true,'Reviewed')['id'],true,'Issued for construction');
self::assertSame('issued',$issued['status']);
self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/',$issued['auditHash']);
```

- [ ] **Step 2: Run the test and verify RED**

Run: `php vendor/bin/phpunit --configuration phpunit.xml app/tests/Integration/Documents/DocumentServiceTest.php`

Expected: failure because the migration and service do not exist.

- [ ] **Step 3: Implement the schema and state machine**

Create `controlled_documents`, `document_revisions`, and `drawing_register_entries` with binary tenant/project/user foreign keys, linked revision IDs, status checks, private file linkage, audit hashes and indexes. Validate enum-like inputs in PHP, require same-tenant/same-project file linkage, use transactions, and require `rowCount() === 1` for transitions.

- [ ] **Step 4: Run the service test and verify GREEN**

Expected: the full create → edit draft → review → approve → issue → revise chain passes, cross-tenant access fails, and prior issued content remains unchanged.

- [ ] **Step 5: Commit**

```text
git commit -m "feat: add immutable document control spine"
```

### Task 2: Readiness and event integration

**Files:**
- Modify: `app/src/Platform/Documents/DocumentService.php`
- Test: `app/tests/Integration/Documents/DocumentReadinessTest.php`

**Interfaces:**
- Consumes: current issued document revisions and current drawing register entries.
- Produces: municipal, tender, construction and closeout reports with deterministic finding codes and inbox/project-record write-backs.

- [ ] **Step 1: Write failing readiness tests**

```php
$reports=$service->readiness($org,$user,$project);
self::assertFalse($reports['municipal_submission']['ready']);
self::assertContains('MUNICIPAL_SHEET_MISSING',array_column($reports['municipal_submission']['findings'],'code'));
self::assertContains('NO_CURRENT_CONSTRUCTION_DRAWINGS',array_column($reports['construction_issue']['findings'],'code'));
```

- [ ] **Step 2: Run and verify RED**

Expected: missing `readiness` behavior.

- [ ] **Step 3: Implement deterministic structured checks**

Require municipal site/floor/section/elevation sheets plus approved form; tender floor/section/elevation sheets plus issued tender specification; at least one current construction drawing and no superseded construction reliance; issued closeout pack plus issued as-built drawing. Emit idempotent inbox items and governed project records only after human-controlled transitions.

- [ ] **Step 4: Run and verify GREEN**

Expected: incomplete packs fail with exact finding codes; complete packs pass without an OCR/AI/provider claim.

- [ ] **Step 5: Commit**

```text
git commit -m "feat: add document readiness evidence"
```

### Task 3: Authenticated HTTP surface

**Files:**
- Create: `app/src/Http/DocumentRoutes.php`
- Create: `app/src/Http/Controller/DocumentController.php`
- Modify: `app/src/Http/AuthenticationKernelFactory.php`
- Test: `app/tests/Integration/Http/DocumentHttpTest.php`

**Interfaces:**
- Consumes: `DocumentService` and existing `projects.read` / `projects.write` permission middleware.
- Produces: `/api/v1/documents`, `/documents/{id}`, draft update/revisions/review/approve/issue endpoints, and `/projects/{projectId}/document-readiness`.

- [ ] **Step 1: Write the failing authenticated HTTP workflow**

Create a draft by POST, retrieve it by GET, submit/approve/issue by POST, create a revision, list the revision chain, and read readiness; verify missing CSRF, wrong tenant and missing human confirmation fail closed.

- [ ] **Step 2: Run and verify RED**

Expected: 404 for unregistered routes.

- [ ] **Step 3: Implement controller, route validation and registration**

Return the existing success/error envelope, map validation to 422, state conflicts to 409, cross-scope access to 404/403, and creation to 201.

- [ ] **Step 4: Run and verify GREEN**

Expected: authenticated workflow passes against disposable MariaDB.

- [ ] **Step 5: Commit**

```text
git commit -m "feat: expose document control APIs"
```

### Task 4: Canonical V1 document workspace

**Files:**
- Modify: `app/public/assets/js/shell.js`
- Modify: `app/public/assets/css/architex-v1.css`
- Test: `app/tests/Frontend/DocumentWorkspaceTest.php`

**Interfaces:**
- Consumes: authenticated document APIs and the current selected project.
- Produces: live `documents_drawings` workspace with register, revision editor, drawing metadata, review/issue gates, readiness and immutable history.

- [ ] **Step 1: Write the failing frontend contract**

Assert exact workspace IDs/headings, all workflow endpoints, explicit no-OCR/no-signature boundary, human confirmation, live tool status and responsive CSS.

- [ ] **Step 2: Run and verify RED**

Expected: missing production document workspace markers.

- [ ] **Step 3: Implement the workspace**

Render Document Register, Drawing Register, Revision History, Pack Readiness and Evidence Boundary in the canonical shell. Use escaped DOM output, project selection, status-specific actions and prompt/confirm gates. Do not expose fake OCR or automated classification actions.

- [ ] **Step 4: Run and verify GREEN**

Expected: frontend contract and `node --check` pass.

- [ ] **Step 5: Commit**

```text
git commit -m "feat: complete document control workspace"
```

### Task 5: Local release evidence

**Files:**
- Create: `docs/evidence/2026-08-28-release3-document-control.md`

**Interfaces:**
- Consumes: automated tests, live development MariaDB and persistent Chrome page 2.
- Produces: local-only evidence record suitable for later staged-release certification.

- [ ] **Step 1: Run automated gates**

Run syntax checks, frontend test, focused service/HTTP MariaDB tests, unit suite and migration status; all must pass.

- [ ] **Step 2: Execute Chrome flow**

Create a drawing draft, edit it, submit, approve, issue, revise, confirm superseded history, reload, test 390 × 844 at zero overflow, restore 1440 × 900, and verify no console errors/warnings.

- [ ] **Step 3: Read MariaDB directly**

Confirm revision linkage, immutable issue hashes, current drawing metadata, project records, inbox events and audit events.

- [ ] **Step 4: Record exact evidence and boundaries**

Write IDs, counts, hashes, test totals and the statement that local proof is not staged-host certification.

- [ ] **Step 5: Commit**

```text
git commit -m "docs: certify local document control workflow"
```
