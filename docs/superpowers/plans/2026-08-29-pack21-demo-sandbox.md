# Pack 21 Demo Sandbox Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver a production PHP 8.3/MariaDB demo deployment with persistent per-user sandboxes, 12 deterministic projects, 22 role lenses, audited reset, exact V1 shell integration, complete all-role verification and stored browser evidence.

**Architecture:** The same immutable FTP artifact runs in `production` or `demo` mode. Demo mode uses a separate MariaDB database and conditionally registered server routes. Existing domain tables hold real sandbox projects and records; demo-only personas and illustrative seed projections live in dedicated tenant-scoped tables. The browser applies a role lens without changing authenticated identity or production authorization.

**Tech Stack:** PHP 8.3, PDO/MySQL, MariaDB, PHPUnit 12, generated V1 HTML/CSS/JavaScript, Chrome DevTools MCP, shared-hosting FTP artifact.

## Global Constraints

- Preserve `v1.html` unchanged and use its generated shell as the visual authority.
- Do not introduce Firebase, React, Vite, Vercel, Node runtime dependencies or client-only persistence.
- Do not include `packs/` or `v1.html` in commits or release artifacts.
- Write each test first, run it, and observe the expected failure before implementation.
- Demo mode must use separate MariaDB credentials and must return 404 for demo APIs in production mode.
- Every synthetic professional, statutory, compliance and CPD record must be labelled non-authoritative.
- Pack 21 is not complete without all 22 role lenses, the four platform membership roles, two-user isolation, reload persistence, reset, mobile/desktop E2E and stored screenshots.
- Remote DNS, FTP, SMTP, target MariaDB, cron and production promotion are external gates and must not be claimed locally.

---

### Task 1: Restore the pack-ledger baseline after source reorganisation

**Files:**
- Modify: `app/tests/Requirements/PackLedgerBuilderTest.php`
- Modify: `app/src/Requirements/PackLedgerBuilder.php`

**Interfaces:**
- Consumes: the current `packs/Completed`, `packs/ZIP` and active numbered-folder layout.
- Produces: `PackLedgerBuilder::build()` entries whose `pack` field identifies the logical pack rather than the storage bucket.

- [ ] **Step 1: Tighten the existing failing test**

Add assertions that a real workspace build contains logical packs `10. architex-site-execution-field-control-pack` and `21. architex-demo-pack`, and does not use `Completed` or `ZIP` as a pack name:

```php
$ledger = (new PackLedgerBuilder())->build(dirname(__DIR__, 3) . '/packs');
$packs = array_values(array_unique(array_column($ledger['sources'], 'pack')));
self::assertContains('10. architex-site-execution-field-control-pack', $packs);
self::assertContains('21. architex-demo-pack', $packs);
self::assertNotContains('Completed', $packs);
self::assertNotContains('ZIP', $packs);
```

- [ ] **Step 2: Run the red test**

Run: `vendor/bin/phpunit app/tests/Requirements/PackLedgerBuilderTest.php`

Expected: failure because `Completed` is currently returned as the pack name.

- [ ] **Step 3: Normalize logical pack paths**

Replace the one-line `pack()` helper with a method that strips storage prefixes while preserving ordinary temporary-test paths:

```php
private function pack(string $relative): string
{
    $parts = explode('/', str_replace('\\', '/', $relative));
    if (($parts[0] ?? '') === 'Completed') {
        array_shift($parts);
        if (($parts[0] ?? '') === 'Supporting Sources') array_shift($parts);
        if (($parts[0] ?? '') === '2026-06-21') array_shift($parts);
        if (($parts[0] ?? '') === 'extracted') {
            array_shift($parts);
            array_shift($parts);
        }
    }
    if (($parts[0] ?? '') === 'ZIP') {
        array_shift($parts);
        if (($parts[0] ?? '') === 'Source Metadata') array_shift($parts);
    }
    return $parts[0] ?? 'unknown-pack';
}
```

Keep ZIP-entry pack naming based on the archive entry's logical root. Do not collapse Pack 9 and Pack 10.

- [ ] **Step 4: Verify the focused and full non-connected suites**

Run:

```powershell
vendor/bin/phpunit app/tests/Requirements/PackLedgerBuilderTest.php
composer test
```

Expected: zero failures; connected tests may still skip when the disposable database flag is absent and must be executed later.

- [ ] **Step 5: Commit**

```powershell
git add app/tests/Requirements/PackLedgerBuilderTest.php app/src/Requirements/PackLedgerBuilder.php
git commit -m "fix: preserve logical pack names after organization"
```

---

### Task 2: Add fail-closed runtime mode and demo schema

**Files:**
- Modify: `.env.example`
- Modify: `app/src/Infrastructure/Config/AppConfig.php`
- Modify: `app/tests/Unit/Infrastructure/AppConfigTest.php`
- Create: `app/database/migrations/0044_demo_sandbox.sql`
- Modify: `app/tests/Integration/Database/MigrationRunnerTest.php` if it asserts a fixed migration count.

**Interfaces:**
- Produces: `AppConfig::$appMode` with values `production|demo` and migration tables used by `DemoSandboxService`.

- [ ] **Step 1: Write failing configuration tests**

Add tests proving absent mode defaults to production, `APP_MODE=demo` is accepted, and any other value is rejected:

```php
self::assertSame('production', AppConfig::fromEnvironment($this->validEnvironment())->appMode);
$environment = $this->validEnvironment();
$environment['APP_MODE'] = 'demo';
self::assertSame('demo', AppConfig::fromEnvironment($environment)->appMode);
$environment['APP_MODE'] = 'preview';
$this->expectExceptionMessage('APP_MODE must be production or demo');
AppConfig::fromEnvironment($environment);
```

- [ ] **Step 2: Run the red configuration tests**

Run: `vendor/bin/phpunit app/tests/Unit/Infrastructure/AppConfigTest.php`

Expected: failure because `appMode` does not exist.

- [ ] **Step 3: Add the runtime mode**

Add `public string $appMode` to `AppConfig`, parse it before database validation, and reject values outside `['production','demo']`. Add this documented line to `.env.example`:

```dotenv
# production hides all demo routes and controls; demo requires a separate MariaDB database.
APP_MODE=production
```

- [ ] **Step 4: Write migration assertions before the migration**

Extend a connected migration test to assert these tables after migrating from zero:

```php
foreach (['demo_sandboxes','demo_personas','demo_project_role_assignments','demo_seed_items','demo_sandbox_events'] as $table) {
    self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='" . $table . "'")->fetchColumn());
}
```

- [ ] **Step 5: Run the red migration test**

Run the focused migration test with the disposable MariaDB environment. Expected: failure because the demo tables are absent.

- [ ] **Step 6: Create migration `0044_demo_sandbox.sql`**

Create tenant-scoped tables with binary UUID foreign keys, unique generation keys, indexed role/project lookups, canonical JSON payloads and SHA-256 columns. Use these lifecycle values exactly:

```sql
status ENUM('active','superseded','failed') NOT NULL DEFAULT 'active'
```

Use an `active_owner_key` generated column that is the owner user ID only for active rows and a unique key over it, preventing two active generations per user. `demo_seed_items.item_type` accepts `submission`, `message`, `compliance`, `learning_article`, `learning_assessment`, `learning_module`, and `learning_certificate`.

- [ ] **Step 7: Verify config and migration tests**

Run focused config and connected migration tests. Expected: all pass.

- [ ] **Step 8: Commit**

```powershell
git add .env.example app/src/Infrastructure/Config/AppConfig.php app/tests/Unit/Infrastructure/AppConfigTest.php app/database/migrations/0044_demo_sandbox.sql app/tests/Integration/Database/MigrationRunnerTest.php
git commit -m "feat: add demo runtime and sandbox schema"
```

---

### Task 3: Build the deterministic role and seed contracts

**Files:**
- Create: `app/src/Platform/Demo/DemoException.php`
- Create: `app/src/Platform/Demo/DemoRoleLensRegistry.php`
- Create: `app/src/Platform/Demo/DemoSeedCatalog.php`
- Create: `app/tests/Unit/Demo/DemoRoleLensRegistryTest.php`
- Create: `app/tests/Unit/Demo/DemoSeedCatalogTest.php`

**Interfaces:**
- `DemoRoleLensRegistry::all(): array<string,array<string,mixed>>`
- `DemoRoleLensRegistry::get(string $role): array<string,mixed>`
- `DemoSeedCatalog::version(): string`
- `DemoSeedCatalog::personas(): list<array<string,mixed>>`
- `DemoSeedCatalog::projects(): list<array<string,mixed>>`
- `DemoSeedCatalog::items(): list<array<string,mixed>>`
- `DemoSeedCatalog::hash(): string`

- [ ] **Step 1: Write failing role-registry tests**

Assert the exact ordered 22-role set from the design, seven UI groups, unique values, valid canonical V1 mappings, and non-empty tool categories/project keys for every role. Assert an unknown role throws `DemoException('demo_role_invalid')`.

- [ ] **Step 2: Run the red role tests**

Run: `vendor/bin/phpunit app/tests/Unit/Demo/DemoRoleLensRegistryTest.php`

Expected: class-not-found failure.

- [ ] **Step 3: Implement the immutable role registry**

Use private constants and return value arrays; do not read roles from the client. Each entry contains:

```php
[
    'key' => 'engineer_structural',
    'label' => 'Structural Engineer (PrEng)',
    'group' => 'Engineering',
    'canonicalRole' => 'engineer',
    'focus' => 'Structural analysis, coordinated evidence and sign-off boundaries',
    'toolCategories' => ['engineering','documents','compliance'],
    'projectKeys' => ['parkview','sandton','seapoint','fourways','greenpoint','rosebank','springs','bokaap','lenasia','umhlanga_tower'],
]
```

Implement all 22 entries explicitly.

- [ ] **Step 4: Write failing seed-catalog tests**

Assert version `pack21-v1`, exactly 22 personas, 12 projects, all required lifecycle phases, unique source keys, fixed dates, no `Math.random` equivalent, stable hashes across two instances, and reliance labels `illustrative_demo_not_for_reliance` and `not_accredited_demo_record`.

- [ ] **Step 5: Run the red catalog tests**

Run: `vendor/bin/phpunit app/tests/Unit/Demo/DemoSeedCatalogTest.php`

Expected: class-not-found failure.

- [ ] **Step 6: Implement the seed catalog**

Transcribe the 12 source projects, 22 personas and pack counts. Map source stages to existing phases:

```php
private const PHASE_MAP = [
    'brief_enquiry' => 'onboarding',
    'shortlisted' => 'feasibility',
    'appointed' => 'appointment',
    'concept_design' => 'concept_design',
    'design_development' => 'design_development',
    'tender_documentation' => 'tender_procurement',
    'construction' => 'construction_execution',
    'close_out' => 'closeout',
];
```

Use fixed ISO timestamps, integer cents for budgets, synthetic `.example` emails, and explicit reliance fields. Build the hash from recursively key-sorted canonical JSON.

- [ ] **Step 7: Verify focused unit tests**

Run both demo unit-test files. Expected: all pass with no warnings.

- [ ] **Step 8: Commit**

```powershell
git add app/src/Platform/Demo app/tests/Unit/Demo
git commit -m "feat: add deterministic demo catalog"
```

---

### Task 4: Implement persistent sandbox lifecycle and isolation

**Files:**
- Create: `app/src/Platform/Demo/DemoSandboxService.php`
- Create: `app/tests/Integration/Demo/DemoSandboxServiceTest.php`

**Interfaces:**
- `ensure(string $organisationId,string $userId,string $membershipRole): array`
- `state(string $organisationId,string $userId): array`
- `selectRole(string $organisationId,string $userId,string $role): array`
- `projects(string $organisationId,string $userId): array`
- `project(string $organisationId,string $userId,string $projectId): array`
- `learning(string $organisationId,string $userId): array`
- `reset(string $organisationId,string $userId,string $membershipRole,array $input): array`

- [ ] **Step 1: Write failing connected lifecycle tests**

Create two active users and organisations. Assert:

- ensure creates exactly one generation and is idempotent;
- the seed contains 12 projects, 22 personas and the exact learning counts;
- selected role persists after reconstructing the service;
- every role sees only its configured projects;
- user A cannot read user B's project or sandbox;
- a created project record persists after reload;
- reset rejects an incorrect phrase and missing reason;
- reset supersedes generation 1, creates generation 2, revokes only the old membership and leaves user B unchanged;
- a forced seed failure rolls back without replacing the active generation.

- [ ] **Step 2: Run the red connected tests**

Run the focused test with `ARCHITEX_ALLOW_DISPOSABLE_DB=1` and the explicit local test database. Expected: class-not-found failure after migrations succeed.

- [ ] **Step 3: Implement membership and sandbox guards**

Private guards query active memberships and compare the supplied organisation/user. Every lookup joins `demo_sandboxes` on both organisation and owner. Return `demo_sandbox_not_found` or `demo_project_not_visible`; never return another tenant's existence.

- [ ] **Step 4: Implement transactional ensure and seed**

Generate IDs with `Identifier::generate()`. Insert the sandbox row, personas, projects, assignments, seed items, project records, workflow events and inbox items in one transaction. Canonically hash each item and the aggregate seed. On duplicate concurrent ensure, roll back and return the existing active generation.

- [ ] **Step 5: Implement state, role, projects and learning**

`selectRole()` validates only through `DemoRoleLensRegistry`. Project reads join assignments for the active stored lens. Learning returns the source counts and reliance labels; it never exposes answer keys to client/project roles.

- [ ] **Step 6: Implement factory reset**

Require owner membership, phrase `RESET DEMO SANDBOX`, and a 10-to-500-character reason. Lock the active sandbox row `FOR UPDATE`, create a replacement organisation slug using generation and a random identifier suffix, seed generation 2, revoke only the old membership, close the old organisation, append reset events, then commit. Roll back everything on failure.

- [ ] **Step 7: Run connected tests green**

Run the focused connected service test twice. Expected: all pass both times with stable seed hashes and no leaked rows.

- [ ] **Step 8: Commit**

```powershell
git add app/src/Platform/Demo/DemoSandboxService.php app/tests/Integration/Demo/DemoSandboxServiceTest.php
git commit -m "feat: implement persistent demo sandboxes"
```

---

### Task 5: Expose fail-closed demo APIs

**Files:**
- Create: `app/src/Http/Controller/DemoSandboxController.php`
- Create: `app/src/Http/DemoSandboxRoutes.php`
- Modify: `app/src/Http/AuthenticationKernelFactory.php`
- Modify: `app/public/index.php`
- Create: `app/tests/Integration/Http/DemoSandboxHttpTest.php`
- Modify: `app/tests/Http/KernelTest.php`

**Interfaces:**
- Authentication kernel constructor gains `string $appMode = 'production'`.
- Demo routes are registered only when `$appMode === 'demo'`.

- [ ] **Step 1: Write failing production/demo route tests**

Build one kernel with the default mode and one with `appMode: 'demo'`. Assert all demo paths return 404 in production. In demo mode assert authentication, CSRF, ensure, state, role, projects, project detail, learning and reset contracts.

Loop through `array_keys((new DemoRoleLensRegistry())->all())`, persist each role and verify the returned role and project list. Test owner/admin/member/viewer production permissions remain unchanged.

- [ ] **Step 2: Run the red HTTP tests**

Expected: demo-mode constructor argument or routes are missing.

- [ ] **Step 3: Implement controller and routes**

Use existing `Response::success/error` conventions. Error mapping:

```php
$status = match ($reason) {
    'demo_sandbox_not_found','demo_project_not_visible' => 404,
    'demo_membership_required','demo_reset_owner_required' => 403,
    'demo_role_invalid','demo_reset_confirmation_required','demo_reset_reason_required' => 422,
    default => 422,
};
```

Register reads with `projects.read`, role selection with `projects.read`, ensure/reset with `organisation.manage`, and reset with a dedicated rate limit of 3 per hour per user.

- [ ] **Step 4: Wire runtime mode**

Pass `$config->appMode` from `app/public/index.php` into `AuthenticationKernelFactory`. Add `/demo` to the shell route allowlist. Keep the default factory mode production so all existing tests remain fail-closed.

- [ ] **Step 5: Run HTTP and authorization tests**

Run the focused demo HTTP test, `KernelTest`, `AuthorizationMatrixTest`, and existing identity/session tests. Expected: all pass.

- [ ] **Step 6: Commit**

```powershell
git add app/src/Http/Controller/DemoSandboxController.php app/src/Http/DemoSandboxRoutes.php app/src/Http/AuthenticationKernelFactory.php app/public/index.php app/tests/Integration/Http/DemoSandboxHttpTest.php app/tests/Http/KernelTest.php
git commit -m "feat: expose governed demo sandbox api"
```

---

### Task 6: Add the V1-consistent Demo Hub

**Files:**
- Create: `app/public/assets/js/demo-sandbox.js`
- Create: `app/public/assets/css/demo-sandbox.css`
- Modify: `app/public/app.php`
- Modify: `app/bin/extract-v1-assets.php`
- Create: `app/tests/Frontend/DemoSandboxWorkspaceTest.php`
- Modify: `app/tests/Frontend/V1ExtractionTest.php`

**Interfaces:**
- `window.demoSandboxWorkspace()` returns the V1-native workspace markup.
- `window.hydrateDemoSandbox()` loads and renders server state.
- `window.setDemoRole(role)` persists a lens through the API.
- `window.resetDemoSandbox()` performs the confirmed reset and reload.

- [ ] **Step 1: Write failing frontend contract tests**

Assert generated assets contain:

```php
foreach (['demoSandboxWorkspace','DEMO SANDBOX','/api/v1/demo/sandbox','RESET DEMO SANDBOX','illustrative_demo_not_for_reliance','not_accredited_demo_record'] as $needle) {
    self::assertStringContainsString($needle, $javascript . $html);
}
```

Assert all 22 labels are present, `/demo` is a supported direct route, the canonical rail/navigator/main/inspector remain single owners, and CSS includes mobile overflow and visible-focus rules.

- [ ] **Step 2: Run the red frontend tests**

Expected: missing demo assets and route.

- [ ] **Step 3: Implement the JavaScript module**

Use the existing session object, organisation header and CSRF token. Treat 404 from the demo endpoint as production mode and make no DOM changes. In demo mode:

- install the topbar role button;
- install the bottom status rail;
- mount the Demo Hub on `/demo` or the existing God Mode entry;
- render role-filtered project cards, seed counts and reliance notices;
- update navigation/tool categories after role change;
- require typed reset phrase and reason before POST;
- update the organisation context from the reset response and reload.

Never use localStorage as the source of truth and never render source-pack mock data when the API fails.

- [ ] **Step 4: Implement restrained V1 CSS**

Use existing custom properties and component classes. Add only namespaced `.demo-sandbox-*` rules. The fixed amber rail reserves body space, uses an opaque V1 surface, and remains one line on desktop. At `max-width: 760px`, the role menu is a bounded sheet, project cards are one column, and no element exceeds the visual viewport. Include `:focus-visible` and `prefers-reduced-motion` rules.

- [ ] **Step 5: Make extraction repeatable**

Teach `extract-v1-assets.php` to include the demo CSS/JS without replacing the canonical inline shell or removing Releases 2-16 extensions. Running extraction twice must be byte-stable.

- [ ] **Step 6: Run frontend and syntax checks**

```powershell
vendor/bin/phpunit app/tests/Frontend/DemoSandboxWorkspaceTest.php app/tests/Frontend/V1ExtractionTest.php
node --check app/public/assets/js/demo-sandbox.js
php -l app/public/app.php
php -l app/bin/extract-v1-assets.php
```

Expected: all pass, no syntax warnings.

- [ ] **Step 7: Commit**

```powershell
git add app/public/assets/js/demo-sandbox.js app/public/assets/css/demo-sandbox.css app/public/app.php app/bin/extract-v1-assets.php app/tests/Frontend/DemoSandboxWorkspaceTest.php app/tests/Frontend/V1ExtractionTest.php
git commit -m "feat: add v1 demo sandbox workspace"
```

---

### Task 7: Certify all roles and end-to-end behavior

**Files:**
- Create: `app/tests/Browser/pack21-demo.spec.ts`
- Create: `docs/evidence/2026-08-29-release17-pack21-demo-sandbox.md`
- Create during Chrome testing: `docs/evidence/screenshots/release17-pack21-demo-desktop.png`
- Create during Chrome testing: `docs/evidence/screenshots/release17-pack21-demo-mobile.png`
- Create during Chrome testing: `docs/evidence/screenshots/release17-pack21-demo-role-matrix.png`
- Create during Chrome testing: `docs/evidence/screenshots/release17-pack21-demo-reset.png`

**Interfaces:**
- Browser target uses a local demo-mode server and disposable MariaDB database.
- A separate production-mode server proves demo controls and routes are absent.

- [ ] **Step 1: Write the E2E specification before browser certification**

The spec logs in through the real session flow, ensures a sandbox, loops through all 22 role options, and asserts the exact active label and non-empty expected project/tool projection. It then changes persistent data, reloads, checks two isolated browser contexts, resets, verifies generation increment, checks desktop/mobile geometry and confirms production invisibility.

- [ ] **Step 2: Start bounded production and demo runtimes**

Use separate local ports and explicit environment blocks. The demo runtime uses `APP_MODE=demo` and a disposable `_test` database. Wait for `/api/v1/health` and `/api/v1/readiness`; do not infer readiness from a listening port.

- [ ] **Step 3: Run the entire connected test suite**

Run PHPUnit with `ARCHITEX_ALLOW_DISPOSABLE_DB=1`. Certification requires 100% of executed tests passing, zero failures and zero errors. No Pack 21 test may be skipped.

- [ ] **Step 4: Execute Chrome DevTools MCP E2E**

Use the persistent Chrome page, not screenshots alone. Record:

- accessibility snapshots for desktop and mobile;
- all 22 role selections and rendered counts;
- two-context isolation;
- persistence after reload;
- reset generation change;
- production-mode absence;
- network requests and statuses;
- console messages;
- desktop/mobile Lighthouse accessibility and best-practices results.

- [ ] **Step 5: Store required screenshots**

Capture the exact paths declared above. Inspect each image after writing it. Reject screenshots with clipped controls, horizontal overflow, loading placeholders, console errors or inconsistent V1 chrome.

- [ ] **Step 6: Write truthful evidence**

Record exact commands, test counts, assertion counts, database target, browser page ID, role matrix, request statuses, console count, screenshot paths, Lighthouse results, source commit and remaining remote gates. Distinguish local evidence from shared-host certification.

- [ ] **Step 7: Commit browser tests and evidence**

```powershell
git add app/tests/Browser/pack21-demo.spec.ts docs/evidence/2026-08-29-release17-pack21-demo-sandbox.md docs/evidence/screenshots/release17-pack21-demo-*.png
git commit -m "docs: certify pack 21 demo sandbox"
```

---

### Task 8: Build and verify the immutable FTP candidate

**Files:**
- Modify only if required by test evidence: `app/src/Infrastructure/Deployment/ReleaseBuilder.php`
- Update: `docs/evidence/2026-08-29-release17-pack21-demo-sandbox.md`

**Interfaces:**
- Produces a source-commit-pinned production artifact and ZIP suitable for FTP upload.

- [ ] **Step 1: Run the aggregate source gates**

Run Composer validation/audit, PHP lint over changed PHP files, JavaScript syntax checks, the complete connected PHPUnit suite, `git diff --check`, and focused release tests.

- [ ] **Step 2: Build the release from the exact source commit**

The artifact must exclude tests, `packs/`, `.env`, `v1.html`, development storage and database credentials. Its metadata must require PHP 8.3 and include the source commit.

- [ ] **Step 3: Verify original and re-expanded manifests**

Run the release verifier against the built directory, create the FTP ZIP, expand it into a fresh verification directory, and verify every manifest entry again.

- [ ] **Step 4: Mount and recheck the packaged candidate**

Run the exact packaged candidate in demo mode, re-run the critical Chrome flow, confirm zero console errors and leave the persistent browser open on the packaged Demo Hub.

- [ ] **Step 5: Review scope and commit any final evidence update**

Confirm `git status --short` contains only the intentionally untracked `packs/` and `v1.html`. Confirm every Pack 21 requirement in the design has direct current evidence. Commit only the evidence update if it changed.
