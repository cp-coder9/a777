# Release 1 final local gate — 2026-08-27

## Commit-pinned artifact

- Source commit: `869cd9ca891891391c5fb0552c6344a3e0e7c268`.
- Artifact: `dist/architex-release-release1-gate-869cd9c`.
- Integrity verification: pass, 140 manifested runtime files.
- Production Composer metadata contains zero packages/dev packages; development tests and writable runtime data are absent.
- Persistent exact-artifact server: `http://127.0.0.1:8792/`, process 5968.

## Automated gate

- PHP: 8.3.32.
- Composer strict validation: pass.
- Composer advisory audit: no known vulnerability advisories.
- Direct PHPUnit: 156 tests, 502 assertions — pass in 10:07.886.
- Pack ledger: 50,868 extracted requirements; generator records 0 implemented requirements and retains every pack statement as `not_implemented` until separately evidenced.

## Backup and recovery rehearsal

- AES-256 encrypted archive: `dist/release1-test-backup.axbackup`.
- HMAC/SHA authenticated backup id: `f7126b62a66dc02e2a41e906dbf5c3f468b0f7601f0ae25360ad0d97d8372458`.
- Source snapshot: 16 MariaDB rows and one active private file.
- Guarded restore target: task-owned loopback database `architex_task10_deadbeef_test` and isolated private file tree.
- Restore: 16 rows and one file; migration status current at six migrations.

## Chrome DevTools MCP exact-artifact verification

Against `http://127.0.0.1:8792/`:

- restored authenticated session: HTTP 200 for `release1@example.test`;
- shell state: `ready`;
- restored project list: one project, `DevTools Verified Project`;
- restored private PDF: HTTP 200, exact original bytes and SHA-256 `0af15ace7fe6cdd62f98d19cb4d2b96f7010343f294676460b1af02cd6b212c5`;
- readiness: database, migrations, and jobs ready; mail unavailable; truthful HTTP 503;
- maintenance request without configured bearer token: fail-closed HTTP 404;
- desktop viewport: 1920px verification during API/recovery check;
- mobile viewport: 390×844 visual verification and screenshot; main workspace computed from x=74 to x=390 with body overflow locked;
- browser returned to a persistent 1440×900 desktop viewport after the mobile gate.

The Playwright package/browser runner was unavailable in this workspace. Per the user's explicit direction, current live evidence was executed with Chrome DevTools MCP rather than representing an unexecuted Playwright spec as a pass.

## Release boundary

Release 1 is locally implemented and certified. It is not remotely/stage certified. Production promotion remains blocked on target-host FTP re-verification, remote MariaDB backup/migration, control-panel PHP limits and cron, TLS/domain checks, writable private storage, and real SMTP verification/reset delivery. Because SMTP is not yet supplied, public registration and password reset correctly remain unavailable.
