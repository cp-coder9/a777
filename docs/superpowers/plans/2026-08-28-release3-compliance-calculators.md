# Release 3 Compliance Calculators Implementation Plan

**Goal:** Deliver the four real compliance tools defined by the supplied compliance and FenCalc packs on the existing governed ToolRun spine, while refusing legal-reliance claims from unverified seed tables.

**Architecture:** Store immutable, status-controlled rule-source versions separately from ToolRuns. Calculator definitions resolve an explicit rule version, validate all human-entered inputs, and produce deterministic evidence, action cards and source snapshots. ToolRuns retain exports, assignment, audit and revision behavior; formal issue is allowed only for a verified rule source and explicit professional confirmation.

**Tech Stack:** PHP 8.3, PDO, MariaDB 11.4-compatible SQL, canonical V1 HTML/CSS/JavaScript, PHPUnit 12, Chrome DevTools MCP.

## Global constraints

- Treat every supplied pack capability as unimplemented until executable repository evidence exists.
- Preserve the canonical V1 shell and existing tool identity.
- Enforce organisation, user, project and role scope on every operation.
- Never describe a pre-check as municipal approval, professional signoff or legal compliance.
- Never infer drawing dimensions, room data, signatures or missing evidence.
- Keep old rule versions and issued ToolRun snapshots immutable.
- Use the supplied seed tables only with `review_required` status.
- Execute inline only; do not dispatch subagents.

### Task 1: Versioned compliance source registry

**Files:**
- Create: `app/database/migrations/0020_compliance_rule_versions.sql`
- Create: `app/src/Platform/Compliance/ComplianceException.php`
- Create: `app/src/Platform/Compliance/ComplianceRuleRegistry.php`
- Test: `app/tests/Integration/Compliance/ComplianceRuleRegistryTest.php`

- [x] Write a failing MariaDB test for the seeded review-required source, immutable version creation, verified-source activation and role enforcement.
- [x] Add the migration and registry with source reference, payload checksum, status, effective date, reviewer and audit history.
- [x] Prove old versions cannot be mutated or silently replaced.
- [x] Commit the registry.

### Task 2: Four production calculator definitions

**Files:**
- Create: `app/src/Platform/Compliance/ComplianceCalculatorService.php`
- Modify: `app/src/Platform/Toolbox/ProductionToolRegistry.php`
- Modify: `app/src/Platform/Toolbox/ToolboxEngine.php`
- Test: `app/tests/Integration/Compliance/ComplianceCalculatorServiceTest.php`
- Test: `app/tests/Integration/Toolbox/ProductionComplianceToolsTest.php`

- [x] Write failing tests for fenestration, R-value assemblies, SANS forms and drawing pre-check results.
- [x] Implement validated calculations, per-element/per-storey summaries, deterministic findings and professional-review requirements.
- [x] Register all four required standalone routes and write ProjectRecord/inbox-compatible output payloads.
- [x] Permit advisory completion on review-required rules but fail closed on formal issue until a verified rule source is selected.
- [x] Prove verified issued ToolRuns snapshot source version, inputs, outputs, action cards and SHA-256.

### Task 3: Governed rule administration and HTTP surface

**Files:**
- Create: `app/src/Http/ComplianceRoutes.php`
- Create: `app/src/Http/Controller/ComplianceController.php`
- Modify: `app/src/Http/AuthenticationKernelFactory.php`
- Test: `app/tests/Integration/Http/ComplianceHttpTest.php`

- [x] Write the failing authenticated workflow for source listing, version creation, verification and calculator execution.
- [x] Restrict source creation/verification to authorised organisation administrators.
- [x] Return existing API envelopes and fail closed on CSRF, role, tenant, source and validation errors.
- [x] Prove non-admin users can run authorised tools but cannot govern source data.

### Task 4: Canonical V1 compliance workspace

**Files:**
- Modify: `app/public/assets/js/shell.js`
- Modify: `app/public/assets/css/architex-v1.css`
- Test: `app/tests/Frontend/ComplianceWorkspaceTest.php`

- [x] Write the failing frontend contract for all four working tools, rule-source status, action cards, evidence history and explicit legal boundary.
- [x] Build native canonical-shell forms and results; do not iframe or copy the pack's independent visual shell.
- [x] Support standalone and internal-project assignment, completed advisory runs, verified formal issue and export retrieval.
- [x] Verify responsive behavior and escaped output.

### Task 5: Local release evidence

**Files:**
- Create: `docs/evidence/2026-08-28-release3-compliance-calculators.md`

- [x] Run syntax, frontend, focused MariaDB, HTTP, security and full applicable regression suites.
- [x] Migrate the live development database and prove applied/pending counts.
- [x] Use persistent Chrome DevTools to run all four tools, prove the seed-source issue block, reload persistence, mobile layout and clean console.
- [x] Read MariaDB directly for rule checksums, ToolRuns, source snapshots, exports, ProjectRecords, inbox tasks and audit events.
- [x] Record local evidence separately from staged-host certification.
