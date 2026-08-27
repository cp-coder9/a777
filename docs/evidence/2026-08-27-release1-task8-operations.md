# Release 1 Task 8 operations evidence — 2026-08-27

## Scope

- PHP 8.3 SMTP transport with TLS or STARTTLS and optional authenticated delivery.
- AES-256-GCM encrypted scheduled-job payloads; verification and reset tokens are not stored in plaintext.
- Transactional MariaDB job claiming, active-lock exclusion, stale-lock recovery, bounded exponential retries, deduplication, and dead-letter handling.
- Shared-hosting cron entry point: `php app/cron/dispatch.php --once --limit=25`.
- Separate liveness and readiness endpoints. Registration and password reset fail closed while SMTP is unavailable.

## Executed checks

- Task-owned MariaDB migration on `127.0.0.1:43319`, database `architex_task7_live`: 1 applied, 0 pending.
- Targeted queue/readiness suite: 7 tests, 26 assertions — pass.
- SMTP configuration/message suite: 8 tests, 15 assertions — pass.
- Full direct PHPUnit suite: 148 tests, 470 assertions — pass in 08:54.772.
- Composer metadata: strict validation pass; no known security advisories reported by Composer.
- Cron with a valid local APP_KEY/APP_URL and no queued work: `{"claimed":0,"succeeded":0,"retried":0,"dead":0}`, exit 0.

## Chrome DevTools MCP verification

Persistent server: `http://127.0.0.1:8790/`.

- Authenticated shell remained ready after migration and displayed the persisted `DevTools Verified Project` from MariaDB.
- `GET /api/v1/health`: HTTP 200, `status=ok`.
- `GET /api/v1/readiness`: HTTP 503 with database, migrations, and jobs `ready`; mail `unavailable`.
- A syntactically valid public `POST /api/v1/registrations` returned HTTP 503 with `registration_email_unavailable`, confirming the approved fail-closed behavior while SMTP is not configured.

## Certification boundary

This is local release-slice evidence. Real SMTP delivery and staged shared-hosting cron execution remain external release gates until the user supplies SMTP and hosting configuration. No remote MariaDB target was mutated.
