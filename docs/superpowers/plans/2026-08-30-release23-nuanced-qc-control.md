# Release 23 Nuanced QC Control Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver Pack 27 as a production PHP 8.3/MariaDB Nuanced QC control with all 35 categories, evidence-backed findings, retests, risk acceptance, cross-pack speak-back and immutable human sign-off.

**Architecture:** Add a focused `NuancedQc` platform module with a deterministic registry/evaluator, a transactional tenant/project-scoped service, authenticated HTTP routes and a V1-native workspace. Reuse ProjectRecord, Inbox, Audit and FileManager persistence rather than duplicating those stores.

**Tech Stack:** PHP 8.3, MariaDB/InnoDB migration 0053, PDO, PHPUnit 12.5, vanilla JavaScript/CSS V1 shell, Chrome DevTools MCP, FTP ZIP release builder.

## Global Constraints

- Treat all ten Pack 27 files as unimplemented requirements.
- Shared hosting only: no daemon, Node runtime, SSH or terminal requirement in production.
- Every organisation/project read and write is enforced server-side.
- Amy/Hymie labels are assistance metadata; the authenticated user remains accountable.
- AI may recommend or draft but may never accept risk, sign off, merge or deploy.
- External services remain `integration_required`; do not fabricate connectors.
- Use test-first implementation and commit coherent slices only.
- Do not stage `packs/`, `v1.html` or `dist/`.

---

### Task 1: Category registry and deterministic recommendation

**Files:**
- Create: `app/src/Platform/NuancedQc/NuancedQcRegistry.php`
- Create: `app/src/Platform/NuancedQc/NuancedQcEvaluator.php`
- Create: `app/src/Platform/NuancedQc/NuancedQcException.php`
- Test: `app/tests/Unit/NuancedQc/NuancedQcRegistryTest.php`
- Test: `app/tests/Unit/NuancedQc/NuancedQcEvaluatorTest.php`

**Interfaces:**
- Produces: `NuancedQcRegistry::categories(): list<array{key:string,title:string,sortOrder:int}>`.
- Produces: `NuancedQcEvaluator::summarise(array $categoryResults, array $findings): array{recommendation:string,counts:array<string,int>,readyForSignoff:bool,blockReason:?string}`.
- Produces: `NuancedQcException::$reason` for deterministic controller mapping.

- [ ] **Step 1: Write failing registry and evaluator tests**

```php
self::assertCount(35, $registry->categories());
self::assertSame('contract_legal_basis', $registry->categories()[0]['key']);
$summary = $evaluator->summarise($allPassed, [['severity'=>'blocker','status'=>'open','riskAccepted'=>false]]);
self::assertSame('block_merge_or_deploy', $summary['recommendation']);
self::assertFalse($summary['readyForSignoff']);
```

- [ ] **Step 2: Run the tests and confirm missing-class failures**

Run: `php vendor/bin/phpunit app/tests/Unit/NuancedQc`

Expected: FAIL because the Nuanced QC classes do not exist.

- [ ] **Step 3: Implement the exact registry and evaluator**

```php
public function summarise(array $categoryResults,array $findings):array
{
    $open=array_filter($findings,static fn(array$f):bool=>!in_array($f['status'],['retest_passed','not_applicable'],true));
    $blockers=array_filter($open,static fn(array$f):bool=>$f['severity']==='blocker'&&!($f['riskAccepted']??false));
    $highs=array_filter($open,static fn(array$f):bool=>$f['severity']==='high');
    $unreviewed=array_filter($categoryResults,static fn(array$r):bool=>$r['status']==='not_reviewed');
    $recommendation=$blockers?'block_merge_or_deploy':($highs?'changes_requested':($unreviewed?'continue_qc':($open?'accept_with_notes':'accept')));
    return ['recommendation'=>$recommendation,'counts'=>['blocker'=>count($blockers),'high'=>count($highs),'unreviewed'=>count($unreviewed)],'readyForSignoff'=>!$blockers&&!$unreviewed,'blockReason'=>$blockers?'open_blocker_findings':($unreviewed?'unreviewed_gap_categories':null)];
}
```

- [ ] **Step 4: Run focused tests and PHP lint**

Run: `php vendor/bin/phpunit app/tests/Unit/NuancedQc && php -l app/src/Platform/NuancedQc/NuancedQcRegistry.php && php -l app/src/Platform/NuancedQc/NuancedQcEvaluator.php`

Expected: PASS with all 35 categories and severity gates proven.

- [ ] **Step 5: Commit the domain slice**

```text
git add app/src/Platform/NuancedQc app/tests/Unit/NuancedQc
git commit -m "feat: add nuanced qc domain rules"
```

### Task 2: MariaDB schema and migration contract

**Files:**
- Create: `app/database/migrations/0053_nuanced_qc_control.sql`
- Create: `app/tests/Unit/Infrastructure/NuancedQcMigrationAssetTest.php`

**Interfaces:**
- Consumes: the registry keys and statuses from Task 1.
- Produces: the ten `qc_*` tables, foreign keys, tenant/project indexes, idempotency keys and optimistic versions required by the service.

- [ ] **Step 1: Write a failing migration asset test**

```php
$sql=(string)file_get_contents(dirname(__DIR__,3).'/database/migrations/0053_nuanced_qc_control.sql');
foreach(['qc_control_definitions','qc_run_records','qc_run_scope_items','qc_category_results','qc_gap_findings','qc_evidence_links','qc_retest_tasks','qc_risk_entries','qc_signoffs','qc_role_assignments'] as $table) self::assertStringContainsString("CREATE TABLE IF NOT EXISTS {$table}",$sql);
self::assertStringContainsString('expected_version INT UNSIGNED NOT NULL DEFAULT 1',$sql);
self::assertStringContainsString('FOREIGN KEY (organisation_id) REFERENCES organisations(id)',$sql);
```

- [ ] **Step 2: Run it and confirm the missing migration failure**

Run: `php vendor/bin/phpunit app/tests/Unit/Infrastructure/NuancedQcMigrationAssetTest.php`

Expected: FAIL because migration 0053 does not exist.

- [ ] **Step 3: Implement the schema with explicit constraints**

```sql
CREATE TABLE IF NOT EXISTS qc_run_records (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  project_id BINARY(16) NULL,
  scope_type ENUM('pr','repo','live_site','feature','implementation_pack','site_audit') NOT NULL,
  lifecycle_status ENUM('draft','in_review','changes_requested','accepted','accepted_with_notes','blocked') NOT NULL DEFAULT 'draft',
  recommendation ENUM('continue_qc','accept','accept_with_notes','changes_requested','block_merge_or_deploy') NOT NULL DEFAULT 'continue_qc',
  expected_version INT UNSIGNED NOT NULL DEFAULT 1,
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Define the other tables with these exact field groups:

- definitions: id, organisation, control/category keys, title, description, version, sort order, active flag and unique organisation/control/version/category;
- scope items: run, kind, reference, source promise, affected workflow and cross-pack target;
- category results: run, category, revision, status, reason, reviewer and timestamp with unique run/category/revision;
- findings: run, category, severity, title, description, evidence type/reference, affected pack/workflow, cross-pack impact, required fix, retest steps, owner, status and expected version;
- evidence links: run, optional finding, optional stored file, evidence type/reference, label, linker and timestamp;
- retest tasks: finding, assignee, due date, expected test, result/evidence, status and expected version;
- risk entries: finding, severity, description, status, risk-acceptance actor/reason/review date and expected version;
- sign-offs: run, signer, decision, reason, human-confirmed flag, snapshot SHA-256 and timestamp;
- role assignments: organisation, user, QC role, assigner and unique organisation/user.

Every child table includes `organisation_id`, an index beginning with organisation and its parent identifier, and foreign keys to organisation, run and user/project/file parents where present.

- [ ] **Step 4: Run the migration asset test and disposable 53-migration rebuild**

Run: `php vendor/bin/phpunit app/tests/Unit/Infrastructure/NuancedQcMigrationAssetTest.php`

Run the existing guarded MariaDB migration command against `architex_task21_fa570001_test` and confirm all 53 migrations apply.

- [ ] **Step 5: Commit the schema slice**

```text
git add app/database/migrations/0053_nuanced_qc_control.sql app/tests/Unit/Infrastructure/NuancedQcMigrationAssetTest.php
git commit -m "feat: add nuanced qc persistence schema"
```

### Task 3: Transactional QC service and speak-back

**Files:**
- Create: `app/src/Platform/NuancedQc/NuancedQcService.php`
- Create: `app/tests/Integration/NuancedQc/NuancedQcServiceTest.php`

**Interfaces:**
- Consumes: `NuancedQcRegistry`, `NuancedQcEvaluator`, `ProjectRecordService`, `InboxService`, `AuditService`, `ProjectContext` and migration 0053.
- Produces: `workspace`, `createRun`, `recordTechnicalSummary`, `recordCategoryResult`, `addFinding`, `linkEvidence`, `createRetest`, `completeRetest`, `acceptRisk`, `signOff`, and `assignRole` methods.

- [ ] **Step 1: Write the connected service test first**

```php
$run=$service->createRun($organisation,$owner,'owner',[
    'title'=>'Pack 27 acceptance','scopeType'=>'implementation_pack','target'=>'Pack 27',
    'projectId'=>$project,'sourceItems'=>[['kind'=>'pack','reference'=>'27. architex-nuanced-qc-control-pack']],
    'idempotencyKey'=>'release23-pack27-run',
]);
$finding=$service->addFinding($organisation,$reviewer,'member',$run['id'],[
    'category'=>'tenant_project_isolation','severity'=>'blocker','title'=>'Cross-tenant quote visible',
    'description'=>'A scoped read returned another tenant record.','evidenceType'=>'test_output','evidenceRef'=>'NuancedQcServiceTest::tenantIsolation',
    'requiredFix'=>'Scope the query by organisation and project.','retestSteps'=>'Repeat as tenant B and expect not found.',
]);
$this->expectReason(fn()=>$service->signOff($organisation,$owner,'owner',$run['id'],['decision'=>'accepted','humanConfirmed'=>true,'reason'=>'Premature']),'nuanced_qc_open_blocker_findings');
```

Add assertions for foreign project/file rejection, reviewer/developer separation, optimistic conflict, all category exception reasons, blocker/high risk/retest creation, risk acceptance, passed retest, ProjectRecord/inbox/audit rows and immutable snapshot SHA-256.

- [ ] **Step 2: Run the test and confirm the missing-service failure**

Run the connected test against the guarded disposable MariaDB database.

Expected: FAIL because `NuancedQcService` is missing.

- [ ] **Step 3: Implement service validation and role resolution**

```php
private function accessRole(string $organisationId,string $userId,string $claimedRole):string
{
    $actual=$this->membershipRole($organisationId,$userId);
    if($actual!==$claimedRole) throw new NuancedQcException('nuanced_qc_membership_required');
    if($actual==='owner') return 'platform_super_admin';
    if($actual==='admin') return 'product_owner';
    return $this->assignedRole($organisationId,$userId) ?? 'viewer';
}
```

Implement every query with organisation scope, optional project scope, bounded input lengths, enum validation and non-enumerating lookup errors.

- [ ] **Step 4: Implement transactional findings, risks, retests and speak-back**

```php
$this->pdo->beginTransaction();
try {
    // Insert immutable finding and required risk/retest rows.
    // For a project run, issue ProjectRecord and create blocker/high Inbox item.
    (new AuditService($this->pdo))->append($organisationId,$userId,'nuanced_qc.finding_added','qc_gap_finding',$findingId,$auditPayload);
    $this->pdo->commit();
} catch (Throwable $error) {
    if($this->pdo->inTransaction()) $this->pdo->rollBack();
    throw $error;
}
```

- [ ] **Step 5: Implement sign-off fail-closed gates and immutable snapshot**

```php
$summary=$this->evaluator->summarise($this->latestCategoryResults($runId),$this->findings($runId));
if(!$summary['readyForSignoff']&&in_array($decision,['accepted','accepted_with_notes'],true)) throw new NuancedQcException((string)$summary['blockReason']);
$sha=hash('sha256',json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
```

- [ ] **Step 6: Run connected and full non-database suites**

Run the connected service test, then `php vendor/bin/phpunit`.

Expected: connected PASS; full suite remains green with database-gated skips explicit.

- [ ] **Step 7: Commit the service slice**

```text
git add app/src/Platform/NuancedQc/NuancedQcService.php app/tests/Integration/NuancedQc/NuancedQcServiceTest.php
git commit -m "feat: implement governed nuanced qc runs"
```

### Task 4: Authenticated HTTP contract

**Files:**
- Create: `app/src/Http/NuancedQcRoutes.php`
- Create: `app/src/Http/Controller/NuancedQcController.php`
- Modify: `app/src/Http/AuthenticationKernelFactory.php`
- Modify: `app/public/index.php`
- Create: `app/tests/Http/NuancedQcRoutesContractTest.php`

**Interfaces:**
- Consumes: the Task 3 service methods.
- Produces: `/api/v1/nuanced-qc/*` authenticated endpoints and three V1 deep-link patterns.

- [ ] **Step 1: Write failing route-contract tests**

```php
foreach(['/api/v1/nuanced-qc','/api/v1/nuanced-qc/runs','/api/v1/nuanced-qc/runs/{runId}/categories','/api/v1/nuanced-qc/runs/{runId}/findings','/api/v1/nuanced-qc/runs/{runId}/signoffs'] as $route) self::assertStringContainsString($route,$routes);
self::assertStringContainsString('new NuancedQcService',$factory);
self::assertStringContainsString('admin/qc-controls/nuanced-gap-test',$front);
```

- [ ] **Step 2: Run the contract test and confirm failure**

Run: `php vendor/bin/phpunit app/tests/Http/NuancedQcRoutesContractTest.php`

Expected: FAIL because routes and wiring are absent.

- [ ] **Step 3: Implement route/controller mappings**

```php
$router->get('/api/v1/nuanced-qc',fn(Request$r):Response=>$this->read->process($r,$this->controller->workspace(...)));
$router->post('/api/v1/nuanced-qc/runs',fn(Request$r):Response=>$this->write->process($r,$this->controller->createRun(...)));
$router->post('/api/v1/nuanced-qc/runs/{runId}/findings',fn(Request$r):Response=>$this->write->process($r,$this->controller->addFinding(...)));
$router->post('/api/v1/nuanced-qc/runs/{runId}/signoffs',fn(Request$r):Response=>$this->write->process($r,$this->controller->signOff(...)));
```

Map not-found to 404, authority failures to 403, optimistic/sign-off/blocker conflicts to 409 and malformed JSON to 400.

- [ ] **Step 4: Wire middleware and deep links**

Use `projects.read` for reads and `projects.write` for mutations. Instantiate registry/evaluator/service in `AuthenticationKernelFactory`. Add the admin/run/project deep links to the production shell route regex.

- [ ] **Step 5: Run route, syntax and service tests**

Run the route contract, `php -l` for each new PHP file and the connected service test.

- [ ] **Step 6: Commit the HTTP slice**

```text
git add app/src/Http/NuancedQcRoutes.php app/src/Http/Controller/NuancedQcController.php app/src/Http/AuthenticationKernelFactory.php app/public/index.php app/tests/Http/NuancedQcRoutesContractTest.php
git commit -m "feat: expose nuanced qc api"
```

### Task 5: V1-native QC workspace

**Files:**
- Modify: `app/public/assets/js/shell.js`
- Modify: `app/public/assets/css/architex-v1.css`
- Create: `app/tests/Frontend/NuancedQcWorkspaceTest.php`

**Interfaces:**
- Consumes: the `/api/v1/nuanced-qc` workspace/run contracts.
- Produces: `nuancedQcWorkspace()`, run/category/finding/retest/risk/sign-off renderers and responsive `.nq-*` styles.

- [ ] **Step 1: Write a failing frontend contract test**

```php
foreach(['nuancedQcWorkspace','Nuanced QC Control','35 nuanced categories','Cross-pack speak-back','Risk acceptance','Retest checklist','AI may execute: no'] as $token) self::assertStringContainsString($token,$shell);
foreach(['.nq-workspace','.nq-category-grid','.nq-findings','@media(max-width:760px)'] as $token) self::assertStringContainsString($token,$css);
```

- [ ] **Step 2: Run it and confirm missing UI tokens**

Run: `php vendor/bin/phpunit app/tests/Frontend/NuancedQcWorkspaceTest.php`

Expected: FAIL before UI implementation.

- [ ] **Step 3: Implement the route-aware workspace and safe renderers**

```js
function nuancedQcWorkspace(){
  setTimeout(loadNuancedQc,0);
  return `<div id="nuancedQc" class="nq-workspace"><section class="nq-hero"><div><span class="decision-kicker">Governance · evidence-led acceptance</span><h1>Nuanced QC Control</h1><p>Technical verification plus 35 real-world gap categories.</p></div><span class="status amber">AI may execute: no</span></section><div id="nqContent" class="notice">Loading tenant-scoped QC runs…</div></div>`;
}
```

Escape all persisted values, filter available controls by server-returned capabilities, and refresh from the API after each mutation.

- [ ] **Step 4: Implement responsive CSS and hidden-state correctness**

```css
.nq-workspace{display:grid;gap:14px;max-width:1540px;margin:0 auto}
.nq-category-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px}
.nq-workspace [hidden]{display:none!important}
@media(max-width:760px){.nq-workspace{display:block}.nq-category-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.nq-layout{grid-template-columns:1fr}}
```

- [ ] **Step 5: Run frontend, JavaScript syntax and full tests**

Run: `php vendor/bin/phpunit app/tests/Frontend/NuancedQcWorkspaceTest.php`

Run: `node --check app/public/assets/js/shell.js`

Run: `php vendor/bin/phpunit`

- [ ] **Step 6: Commit the UI slice**

```text
git add app/public/assets/js/shell.js app/public/assets/css/architex-v1.css app/tests/Frontend/NuancedQcWorkspaceTest.php
git commit -m "feat: add nuanced qc workspace"
```

### Task 6: Release 23 certification and Pack 27 completion

**Files:**
- Create: `docs/evidence/2026-08-30-release23-pack27-nuanced-qc.md`
- Create: `docs/evidence/release23-pack27-nuanced-qc-desktop.png`
- Create: `docs/evidence/release23-pack27-nuanced-qc-mobile.png`
- Create: `docs/evidence/release23-lighthouse/report.json`
- Create: `docs/evidence/release23-lighthouse/report.html`
- Modify: `docs/superpowers/plans/2026-08-30-release23-nuanced-qc-control.md`
- Move outside git: `packs/27. architex-nuanced-qc-control-pack` to `packs/Completed/27. architex-nuanced-qc-control-pack`

**Interfaces:**
- Consumes: all Release 23 implementation slices.
- Produces: exact-source FTP artifact, persistent Chrome evidence and truthful completion record.

- [ ] **Step 1: Run all automated gates**

Run full PHPUnit, focused Nuanced QC suites, all 53 guarded MariaDB migrations, connected service tests, PHP/JavaScript syntax and `git diff --check`.

Expected: every gate exits 0; database skips remain clearly separated from the connected test.

- [ ] **Step 2: Exercise the complete browser story in persistent Chrome DevTools**

Create a real project-scoped run; record all category states; add blocker/high findings with FileManager evidence; prove recommendation blocking; create/complete a retest; accept risk as owner; sign off; verify ProjectRecord, inbox and audit speak-back. Prove reviewer, developer and viewer controls and direct API denials independently.

- [ ] **Step 3: Capture responsive and quality evidence**

Verify production mode, exact build info, HTTP 200 workspace requests, no console warning/error, zero horizontal overflow at desktop and 390 by 844, and visually inspect both screenshots. Run an authenticated Chrome DevTools Lighthouse snapshot and retain JSON/HTML reports.

- [ ] **Step 4: Commit implementation evidence before artifact build**

Write the exact test counts, MariaDB assertions, role evidence, browser page/port and truthful external-integration boundaries. Mark plan boxes only for executed work and commit source evidence.

- [ ] **Step 5: Build and independently verify the exact-source FTP ZIP**

```powershell
$release23Sha = git rev-parse --short HEAD
php app/bin/build-release.php --version="staged-20260830-$release23Sha"
php app/bin/verify-release.php "dist/architex-release-staged-20260830-$release23Sha"
Compress-Archive -Path "dist\architex-release-staged-20260830-$release23Sha\*" -DestinationPath "dist\architex-release-staged-20260830-$release23Sha.zip" -CompressionLevel Optimal
Expand-Archive -LiteralPath "dist\architex-release-staged-20260830-$release23Sha.zip" -DestinationPath "dist\ftp-redownload-verification-$release23Sha"
php app/bin/verify-release.php "dist/ftp-redownload-verification-$release23Sha"
```

Record ZIP bytes, SHA-256, manifest file count and extracted file count.

- [ ] **Step 6: Move the completed pack and commit certification**

Move only the verified Pack 27 folder under `packs/Completed`, leave `packs/` and `v1.html` untracked, commit the plan/evidence/screenshots/reports, and confirm `git status --short` contains no tracked changes.
