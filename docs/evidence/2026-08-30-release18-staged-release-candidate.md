# Release 18 — Aggregate Staged-Release Candidate

Date: 2026-08-30

Candidate: `dist/architex-release-staged-20260829-e1078c3`

Source commit: `e1078c3d46c514b209883187a106ef945babf95e`

FTP convenience archive: `dist/architex-release-staged-20260829-e1078c3.zip`

Archive size: 1,077,678 bytes

Archive SHA-256: `5BC40F737BED2FC58001D34E1DF92810E895A6A3417C361F4E08F64EC900E6FF`

## Local candidate gates

- `composer validate --strict --no-check-publish`: valid.
- `composer audit --no-interaction`: no security vulnerability advisories.
- Release 18 focused contracts: 3 tests, 83 assertions, zero skips/failures/errors.
- Release 18 connected disposable MariaDB lifecycle: 2 tests, 26 assertions, zero skips/failures/errors in 02:42.
- JavaScript syntax, PHP syntax and source whitespace gates passed.
- Release builder installed production-only dependencies and produced a 322-file manifest.
- Manifest verification passed against the original candidate.
- The ZIP was expanded into `dist/ftp-redownload-verification-e1078c3`; independent manifest verification passed for all 322 files.
- Candidate metadata requires PHP 8.3 and records the exact source commit above.
- Candidate excludes tests, packs, `.env`, source `v1.html`, development storage contents and development-only dependencies.

## Packaged-artifact runtime gate

The exact built candidate remains mounted at `http://127.0.0.1:8795/` with PHP 8.3.32 and the bounded local MariaDB demo database.

- `/api/v1/health`: HTTP 200.
- `/api/v1/readiness`: truthful HTTP 503 with database, migrations and jobs ready; mail unavailable.
- Persistent Chrome DevTools page 8 opened the packaged CPD Learning Hub while retaining the authenticated owner session.
- Packaged workspace loaded one live course, one immutable CPD record and the new commercial settings control from MariaDB.
- Authenticated packaged PDF response: HTTP 200, `application/pdf`, 1,046 bytes and `%PDF-1.4`.
- Desktop width and document width: 1,440 px; horizontal overflow: 0 px.
- Canonical shell ownership: one OS rail, navigator, main canvas and inspector.
- Inspected screenshot: `docs/evidence/release18-pack22-packaged-candidate.png`.
- The persistent browser remains open on the packaged candidate at port 8795.

## Truthful release status

This proves a local immutable FTP candidate and packaged-artifact runtime for Release 18. It does not certify the shared host or declare Packs 23–32 complete.

Remote staging and production promotion remain blocked until the target FTP/control-panel credentials, staging hostname, MariaDB credentials, encrypted backup and restore rehearsal, protected migration gate, SMTP evidence, cron setup, TLS checks, cross-tenant acceptance tests and explicit promotion approval are supplied.

No remote upload, target database mutation or production promotion was attempted.
