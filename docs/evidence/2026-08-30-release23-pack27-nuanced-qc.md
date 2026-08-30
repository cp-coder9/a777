# Release 23 — Pack 27 Nuanced QC Control

## Certified source

- Source commit: `ab95cb9feb08bf21cd411a8a0b6fa1397704e628`
- Runtime: PHP 8.3 shared-hosting mode with MariaDB/InnoDB; no production Node process, SSH or terminal dependency.
- Staged release: `staged-20260830-ab95cb9`
- Browser server: `http://127.0.0.1:8809`, PHP process 32480.
- Persistent Chrome DevTools MCP: session 39702, page 2, `/qc/runs/92f083cd3930ed9226b28b43b620d5e5`.

## Implemented pack contract

- Exact ordered registry of all 35 nuanced gap categories.
- Organisation/project-scoped runs, source promises, technical summaries and append-only category revisions.
- Evidence-backed blocker/high/medium/low findings with required fix, workflow/cross-pack impact and retest checklist.
- Automatic blocker/high risk and retest records, optimistic versions, owner/admin-only risk acceptance and immutable human sign-off.
- Fail-closed acceptance while a blocker or unreviewed category remains.
- ProjectRecord, Inbox and audit speak-back for project runs.
- Authenticated API routes plus admin, project and run deep links.
- Responsive V1 workspace with server-returned capabilities; AI may draft/recommend but may not accept risk, sign off, merge or deploy.
- External connectors remain `integration_required`; no provider integration is fabricated.

## Automated evidence

- Red phase: registry/evaluator, migration, service, route and frontend tests each failed for the intended missing implementation before green implementation.
- Nuanced domain plus migration contract: 7 tests, 53 assertions.
- Guarded disposable MariaDB rebuild: all 53 migrations applied twice; 9 tests, 47 assertions.
- Connected lifecycle: 1 test, 26 assertions, including foreign project/file rejection, reviewer/developer separation, optimistic conflict, blocker gate, risk acceptance, passed retest, tenant isolation and speak-back.
- Final full suite: 320 tests, 1,366 assertions, 145 explicitly database-gated skips; exit 0.
- JavaScript and PHP syntax checks plus `git diff --check`: exit 0.

## Governed lifecycle evidence

- Browser database: `architex_task21_fa570001_test`, reserved disposable local target.
- Persisted counts after certification: 35 control definitions, 1 run, 35 latest category states, 1 finding, 1 retest, 1 risk and 1 sign-off.
- Speak-back counts: 3 ProjectRecords, 3 Inbox items and 41 Nuanced QC audit events.
- Deliberate premature acceptance rejected with `nuanced_qc_open_blocker_findings`.
- Risk status: `accepted`; retest status: `passed`; final decision: `accepted_with_notes`.
- Immutable sign-off SHA-256: `d4e6cf47a551aa3854b3bd0f109a9a445c2940dcd83ec6a3f6db37bc7d3deb31`.
- The connected test and direct service certification created the complete lifecycle. Chrome then independently loaded the persisted state through the authenticated API and performed another category revision through the real UI: POST category returned 201 and the following workspace GET returned 200.

## Browser evidence

- Authenticated owner and real project context loaded.
- Rendered: 1 run, 35 categories, 35 pass states, 1 finding, 1 passed retest and 1 immutable sign-off.
- Desktop and emulated 390 × 844 mobile both had zero horizontal overflow; mobile had zero hidden-state violations.
- Post-login clean reload: 5 fetch/XHR requests, all HTTP 200; no console warnings or errors.
- Authenticated mobile Lighthouse snapshot after accessibility remediation: accessibility 100, best practices 100, SEO 100, agentic browsing 100; 32 passed, 0 failed.
- Screenshots were visually inspected. The first mobile capture was rejected as stale/wrong and replaced from the authenticated QC run before certification.

## FTP artifact

- Directory and fresh ZIP extraction both passed `app/bin/verify-release.php`: 361 manifest files.
- ZIP: `dist/architex-release-staged-20260830-ab95cb9.zip`
- ZIP bytes: `1,189,041`
- ZIP SHA-256: `22BC4097F40E9EC1F667597AC39FB63F9A886FFF37FBC47AE923691C6C40F197`
- Extracted files: 362, comprising 361 manifest payload files plus release metadata.

## Evidence files

- `release23-pack27-nuanced-qc-desktop.png`
- `release23-pack27-nuanced-qc-mobile.png`
- `release23-lighthouse/report.json`
- `release23-lighthouse/report.html`

This is local staged-release certification. It does not claim an FTP upload or remote shared-host production deployment.
