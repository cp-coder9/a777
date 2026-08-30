# Release 19 / Pack 23 certification

Date: 2026-08-30 (Africa/Johannesburg)

Scope: product unification, Mobile Decision Centre, API capability parity, CPD reminders and exact-provenance deployment gates for PHP 8.3 / MariaDB shared hosting.

## Exact candidate

- Candidate: `dist/architex-release-staged-20260830-a4a7488`
- FTP ZIP: `dist/architex-release-staged-20260830-a4a7488.zip`
- Source commit: `a4a7488d72c85a080ac76e07af447ae9445623ac`
- Branch: `master`
- Build timestamp: `2026-08-29T22:45:26+00:00`
- Runtime/API mode: PHP `8.3`; `php-shared-hosting`
- Archive bytes: `1,096,869`
- SHA-256: `59BAE2B25D4AB6AD1B8FF2B8B7577044CB73544B36774606B24CB53A55FA4991`
- Independent `Expand-Archive` extraction and `verify-release.php`: passed, 331 manifest files.

The build-info endpoint and release-gate form preserved the exact timestamp above, including seconds and timezone. No `datetime-local` conversion is used.

## Automated proof

- PHP runtime: 8.3.32.
- Release 19 static/release contracts: 6 tests, 49 assertions passed during the implementation gate.
- Connected disposable-MariaDB service workflow: 2 tests, 24 assertions passed against all 48 migrations.
- Exact timestamp regression suite: 6 tests, 29 assertions passed; two connected cases were intentionally skipped in that non-DB invocation.
- Contrast regression: 1 test, 13 assertions passed.
- Release artifact verifier: passed, 331 files.
- JavaScript syntax and `git diff --check`: passed.

## Persistent Chrome DevTools MCP proof

Final candidate URL: `http://127.0.0.1:8798/app` (browser page 13 remains open).

- Authenticated session, projects, inbox, tool catalogue and demo sandbox requests returned 200.
- `/api/v1/mobile-decision-inbox`, `/api/v1/api-capabilities`, `/api/v1/release-gates` and `/api/v1/build-info` returned 200.
- Exact release-gate UI POST returned 201.
- Health returned 200.
- Readiness truthfully returned 503: database, migrations and jobs were ready; mail was unavailable.
- A missing human confirmation returned 409 with `operating_human_confirmation_required`.
- A stale deployed commit produced `fail`; the final exact candidate produced `warn` because mail remains unavailable.
- A second authenticated organisation returned an empty release-gate history, proving tenant isolation for the tested flow.
- Desktop width: zero horizontal overflow.
- Mobile emulation: 390 x 844, zero horizontal overflow.
- Final console: no warnings or errors.
- Final fetch/XHR capture: 16 requests, all successful (200/201).
- Lighthouse snapshot: Accessibility 100, Best Practices 100, SEO 100, Agentic Browsing 100; 30 passed, 0 failed.

Screenshots:

- `docs/evidence/release19-pack23-decision-centre-desktop.png`
- `docs/evidence/release19-pack23-decision-centre-mobile.png`

The first Lighthouse pass exposed a 4.47:1 metadata contrast defect. It was fixed at commit `a4a7488`, retested, rebuilt and only then certified at 100 accessibility.

## Guardrails retained

- Decisions use `advisory`, `awaiting_human`, `blocked`, `ready` or `completed`.
- AI may draft or recommend but cannot approve, sign, submit, certify, pay or release funds.
- Payment capability remains provider-gated and explicitly reports that Architex does not hold or move money.
- CPD remains professional-body-specific record support, separate from Professional Toolboxes.
- Remote FTP upload, target MariaDB migration, SMTP/TLS checks and production promotion were not performed and remain deployment-stage gates.
