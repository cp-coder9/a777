# Release 17 — Persistent Demo Sandbox

Date: 2026-08-29

Scope: Pack 21, `21. persistent-demo-mode-implementation-pack`

Source commits: `5c881c4`, `832bd7e`, `30e7d1a`, `66facb8`, `aeff30f`, `af4d316`, `ea14a94`

Runtime: PHP 8.3.32, MariaDB 11.4 at `127.0.0.1:43320`, demo server `http://127.0.0.1:8792/`, production-mode control server `http://127.0.0.1:8793/`

## Delivered boundary

- Publicly registered, authenticated organisation owners can enter an isolated, MariaDB-backed demo organisation.
- The deterministic catalog contains 12 projects, 22 role personas, 54 seed records and 18 non-accredited learning records.
- Role selection is persisted server-side and recalculates visible projects through MariaDB; no `localStorage` or client-owned mock catalog is used.
- Reset is owner-only, reasoned, audited and destructive only inside the caller's current demo sandbox. The old organisation is superseded and a newly seeded generation is created.
- Demo records carry `illustrative_demo_not_for_reliance`; learning records carry `not_accredited_demo_record`.
- `APP_MODE=production` registers neither the demo API nor the demo UI controls.
- The canonical V1 shell remains the single owner of the rail, navigator, main canvas and inspector.

## Automated evidence

- Connected disposable MariaDB aggregate recorded before browser certification: 290 tests, 2,719 assertions, zero skips, failures or errors in 13:10.532.
- Final connected Pack 21 subset after browser remediation: 4 tests, 249 assertions, zero skips, failures or errors in 02:44.362.
- Pack 21 frontend regression after browser fixes: 1 test, 55 assertions, passed.
- JavaScript syntax checks passed for `shell.js` and `demo-sandbox.js`.
- The later broad `app/tests` invocation discovered four failing frontend contracts for future, not-yet-attended packs (Engineering, Finance shell tokens, RFQ Marketplace and Refuse Calculator). This is not represented as a green aggregate release gate.
- The bounded browser database was `architex_task21_ba770001_test`; disposable test execution used only the separate `architex_task21_fa570001_test` target.

## Chrome DevTools MCP evidence

Chrome DevTools MCP 1.8.0 ran with a persistent profile at `app/storage/tmp/pack21-chrome-profile`. The configured in-process transport was closed, so the same MCP server was started over stdio and exercised through JSON-RPC. The Chrome process and demo pages remained open throughout certification.

### Authenticated flow and network

- A fresh user signed in through the visible form and reached the demo workspace without a reload.
- The network sequence returned HTTP 200 for session creation, projects, inbox, tools, sandbox ensure, sandbox state, role-filtered projects and learning history.
- A clean authenticated page returned HTTP 200 for all eight fetch/XHR requests.
- The pre-authentication session probe returned the expected HTTP 401.
- The production-mode `GET /api/v1/demo/sandbox` probe returned HTTP 404 with `not_found`; role and status controls were absent.

### All role lenses

| Role key | Visible projects |
|---|---:|
| client | 12 |
| project_manager | 12 |
| architect | 10 |
| architectural_technologist | 10 |
| candidate_architect | 10 |
| engineer_structural | 10 |
| engineer_civil | 6 |
| engineer_electrical | 10 |
| engineer_mechanical | 8 |
| engineer_fire | 9 |
| quantity_surveyor | 10 |
| contractor | 5 |
| subcontractor | 5 |
| supplier | 5 |
| energy_consultant | 7 |
| town_planner | 12 |
| environmental_consultant | 8 |
| accessibility_consultant | 9 |
| bep | 7 |
| freelancer | 4 |
| cpd_officer | 4 |
| admin | 12 |

Every role PUT returned HTTP 200 and the selected key, visible label, heading and card count aligned. Reload retained the selected role. A second isolated authenticated context changed to Contractor without changing the first user's Administrator lens.

### Reset, responsive shell and accessibility

- The visible reset form created Generation 2 and restored the default Architect lens with 10 visible projects.
- Desktop shell ownership counts were exactly one each for rail, navigator, main and inspector.
- At 390 × 844, viewport, visual viewport and document width were all 390 px; horizontal overflow was zero.
- Settled desktop Lighthouse snapshot: Accessibility 100, Best Practices 100, SEO 100, Agentic Browsing 100; 30 passed, 0 failed.
- Settled mobile Lighthouse snapshot: Accessibility 100, Best Practices 100, SEO 100, Agentic Browsing 100; 29 passed, 0 failed.
- Browser inspection led to fixes for post-login CSRF hydration, immediate demo hydration, readable demo contrast and a literal `\\n` text node at the full-page boundary.

Reports:

- `docs/evidence/lighthouse/release17-pack21-desktop/report.html`
- `docs/evidence/lighthouse/release17-pack21-mobile/report.html`

Inspected screenshots:

- `docs/evidence/screenshots/release17-pack21-demo-desktop.png`
- `docs/evidence/screenshots/release17-pack21-demo-role-matrix.png`
- `docs/evidence/screenshots/release17-pack21-demo-mobile.png`
- `docs/evidence/screenshots/release17-pack21-demo-reset.png`
- `docs/evidence/screenshots/release17-pack21-immediate-login.png`
- `docs/evidence/screenshots/release17-pack21-immediate-login-mobile.png`
- `docs/evidence/screenshots/release17-pack21-packaged-candidate.png`

## Immutable local FTP candidate

- Candidate: `dist/architex-release-staged-20260829-8717e6d`
- Exact source commit: `8717e6dc9c00ae7f41e93fc2bccc2f06dd4ec900`
- Manifest verification: 315 files, passed before and after adding host-owned empty storage directories.
- FTP ZIP: `dist/architex-release-staged-20260829-8717e6d.zip`
- ZIP SHA-256: `B5F89922C6924CE5D6C87AC103B01CDA1829DD860A8099B26C01E2658D027222`
- ZIP expansion: `dist/ftp-redownload-verification-8717e6d`; independent manifest verification passed for 315 files.
- Packaged runtime: `http://127.0.0.1:8794/demo`; authenticated Generation 2 Architect lens, 10 cards, exactly one V1 shell owner per region and zero horizontal overflow at desktop width.
- Packaged session, projects, inbox, tools, ensure, state, projects and learning requests all returned HTTP 200.

## Readiness and release boundary

- Demo and production-mode `/api/v1/health` returned HTTP 200.
- `/api/v1/readiness` truthfully returned HTTP 503 because mail is unavailable; database, migrations and jobs were ready.
- No SMTP result was fabricated and no remote shared-hosting claim is made.
- `v1.html` and `packs/` remained untouched, untracked and excluded from commits.
- This evidence is local development and staged-release evidence. It is not FTP upload, target-MariaDB, SMTP, staging-domain or production-domain certification.

## 2026-09-04 current re-certification

- Focused Pack 21 MariaDB, HTTP, frontend and kernel suite: 42 tests, 760 assertions, zero failures, errors or skips.
- Authorization matrix: 7 tests, 11 assertions, zero failures, errors or skips.
- V1 extraction and Demo workspace regression after the accessibility correction: 3 tests, 85 assertions, zero failures, errors or skips.
- Final exact-tree connected aggregate: 440 tests, 5,295 assertions, zero failures, errors or skips in 26:12.659 on PHP 8.3.32 and local MariaDB 11.4.
- The aggregate run also corrected two stale test-harness contracts: the authenticated toolbox now verifies all 49 unique registry IDs, and the backup/restore rehearsal removes and restores test-only seed baselines around the production backup boundary. The production backup validator remains fail-closed.
- Chrome DevTools MCP exercised all 22 role lenses through the live role selector and API. The visible project counts were unchanged from the table above, and each selected key, visible label, heading and card count agreed.
- A reload retained the selected CPD Officer lens. A second isolated browser context changed to Contractor while the first context remained CPD Officer. Cross-sandbox access returned HTTP 404 with `demo_project_not_visible`.
- The visible reset confirmation created Generation 2 and restored the Architect lens. The sandbox state remained MariaDB-backed with 12 projects, 22 personas, 54 seed records and 18 learning records.
- Under `APP_MODE=production`, `/api/v1/demo/sandbox` and `/demo` returned HTTP 404, while authenticated `/app` reported production mode with no role controls, status rails or active demo state.
- Fresh geometry checks found one owner each for rail, navigator, main and inspector at 1440 px. At 390 x 844, document width remained 390 px with zero horizontal overflow.
- A pre-hydration accessibility audit exposed unlabeled V1 zoom and role-lens controls. The source extractor and generated shell now give them stable `id`, `name` and `aria-label` attributes. A post-fix mutation audit found no unlabeled instances of those controls.
- The persistent Demo route also exposed an unhandled compliance-hydration promise when the initial V1 tool workspace was replaced before its requests completed. A red frontend contract was added; the hydrator now checks that its captured root is still connected before rendering either success or error state. The focused post-fix frontend gate passed 4 tests and 106 assertions, and a cache-bypassing Chrome reload produced no console errors or warnings.
- The four committed Pack 21 screenshots listed above were re-opened and visually inspected for the V1 shell, desktop, mobile, role-matrix and reset states. The committed Lighthouse reports are retained as historical evidence; no new Lighthouse score is claimed by this re-certification.
- Public registration continued to fail closed with HTTP 503 because SMTP is not configured. No mail-delivery, remote database or shared-host deployment claim is made.
