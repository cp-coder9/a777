# Aggregate staged-release candidate

Date: 2026-08-29

Candidate: `dist/architex-release-staged-20260829-f3398d7`

Source commit: `f3398d7dc79777990289ec26e9c7337b164636cb`

FTP convenience archive: `dist/architex-release-staged-20260829-f3398d7.zip`

Archive SHA-256: `18C2B47FBDFD171A0CB70AE6125B75E787D92F58DE81DDAC497B224D95FDDE9B`

## Local candidate gates

- `composer validate --strict`: valid.
- `composer audit`: no security vulnerability advisories.
- Aggregate source suite: 281 tests, 637 assertions, 134 skipped integration tests. Integration tests require the explicit disposable-database flag and were not represented as passes.
- Release 16 connected disposable MariaDB subset: 3 tests, 32 assertions, passed separately in 10:05.373.
- Release builder installed production-only dependencies and produced a 306-file manifest.
- Local manifest verification passed before mounting the candidate.
- The ZIP was expanded into `dist/ftp-redownload-verification-f3398d7`; independent manifest verification passed for all 306 files.
- Candidate metadata requires PHP 8.3 and records the exact source commit above.
- Candidate excludes tests, packs, `.env`, source `v1.html`, and development storage contents.

## Packaged-artifact runtime gate

The exact built candidate was mounted at `http://127.0.0.1:8791/` with PHP 8.3.32 and the bounded local MariaDB development database.

- `/api/v1/health`: HTTP 200.
- `/api/v1/readiness`: HTTP 503 with database, migrations and jobs ready; mail unavailable.
- Protected maintenance preflight on a separate local port: PHP, database, migrations and storage ready; mail unavailable; overall HTTP 503.
- Maintenance endpoint without the bearer token: HTTP 404.
- Persistent Chrome DevTools page 12 opened the packaged SA Council Drawing Compliance Navigator while retaining the authenticated owner session.
- Session, projects, inbox, tools and drawing-compliance source requests were all HTTP 200.
- Packaged browser console warnings/errors: 0.
- The persistent browser remains open on the packaged candidate at port 8791.
- Manifest verification still passed after adding only host-owned empty storage directories.

## Truthful release status

This proves a local immutable FTP candidate and packaged-artifact runtime. It does not certify the shared host.

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
