# Release 17 — Aggregate Staged-Release Candidate

Date: 2026-08-29

Candidate: `dist/architex-release-staged-20260829-8717e6d`

Source commit: `8717e6dc9c00ae7f41e93fc2bccc2f06dd4ec900`

FTP convenience archive: `dist/architex-release-staged-20260829-8717e6d.zip`

Archive size: 1,054,464 bytes

Archive SHA-256: `B5F89922C6924CE5D6C87AC103B01CDA1829DD860A8099B26C01E2658D027222`

## Local candidate gates

- `composer validate --strict`: valid.
- `composer audit`: no security vulnerability advisories.
- Pack 21 connected disposable MariaDB subset: 4 tests, 249 assertions, zero skips/failures/errors in 02:44.362.
- Earlier connected aggregate before final browser-only remediation: 290 tests, 2,719 assertions, zero skips/failures/errors in 13:10.532.
- The later broad `app/tests` run is not green: four future-pack frontend contracts remain failing. This candidate is therefore staged Pack 21 evidence, not a final all-pack release certification.
- JavaScript syntax and `git diff --check` passed before build.
- Release builder installed production-only dependencies and produced a 315-file manifest.
- Manifest verification passed before mounting the candidate and again after adding only host-owned empty storage directories.
- The ZIP was expanded into `dist/ftp-redownload-verification-8717e6d`; independent manifest verification passed for all 315 files.
- Candidate metadata requires PHP 8.3 and records the exact source commit above.
- Candidate excludes tests, packs, `.env`, source `v1.html`, development storage contents and development-only dependencies.

## Packaged-artifact runtime gate

The exact built candidate is mounted at `http://127.0.0.1:8794/` with PHP 8.3.32 and the bounded local MariaDB demo database.

- `/api/v1/health`: HTTP 200.
- `/api/v1/readiness`: HTTP 503 with database, migrations and jobs ready; mail unavailable.
- Persistent Chrome DevTools page 7 opened the packaged Demo Sandbox while retaining the authenticated owner session.
- Packaged state: Generation 2, Architect lens, 10 visible projects.
- Session, projects, inbox, tools, sandbox ensure, sandbox state, role-filtered projects and learning requests: HTTP 200.
- Desktop width and document width: 1,440 px; horizontal overflow: 0 px.
- Canonical shell ownership: one rail, navigator, main and inspector.
- Inspected screenshot: `docs/evidence/screenshots/release17-pack21-packaged-candidate.png`.
- The persistent browser remains open on the packaged candidate at port 8794.

## Truthful release status

This proves a local immutable FTP candidate and packaged-artifact runtime. It does not certify the shared host or declare the remaining packs complete.

Remote staging and production promotion remain blocked until the following are supplied and executed on the target:

- FTP/control-panel access and a staging hostname whose document root can point to the candidate `public/` directory;
- host-owned runtime secrets and PHP 8.3 extension/limit verification;
- real staging MariaDB credentials;
- encrypted target backup download and isolated restore rehearsal before migrations;
- protected remote preflight and gated migration;
- writable private storage and control-panel cron freshness;
- real SMTP delivery for registration verification and password reset;
- TLS/domain checks, cross-tenant acceptance tests, upload rejection checks, staged desktop/mobile browser evidence, rollback readiness and explicit promotion approval.

No remote upload, target database mutation or production promotion was attempted without those credentials and gates.
