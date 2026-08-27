# Shared-hosting staged release by FTP

This runbook assumes PHP 8.3, MariaDB, TLS, a hosting control panel, FTP/SFTP-style file upload, and no SSH. Do not promote a host that cannot point the site document root at the release `public/` directory; exposing the release root would expose operational PHP and metadata unnecessarily.

## 1. Local candidate gate

Run locally from the repository root:

```text
composer validate --strict
composer audit
vendor/bin/phpunit
php app/bin/build-release.php --version=release1-candidate
php app/bin/verify-release.php dist/architex-release-release1-candidate
```

The builder installs production-only Composer dependencies inside the candidate. It does not modify the development `vendor/` tree. Never upload `.env`, source packs, tests, local evidence, development dependencies, or `app/storage` contents.

## 2. Staging upload

1. Create a versioned private directory in the hosting file manager, for example `releases/architex-release-release1-candidate`.
2. Upload the complete verified candidate directory by FTP in binary/automatic mode.
3. Compare the uploaded `release-manifest.json` against the local copy. If the control panel cannot hash files, re-download the uploaded directory to a new local folder and run `verify-release.php` against that copy.
4. Copy `config/runtime.example.php` to `config/runtime.php` on the host and replace every placeholder. This file is host-owned and is deliberately absent from the integrity manifest.
5. Set `config/runtime.php` to the most restrictive permissions supported by the host (normally 0600). Hosting environment variables override this file when both exist.
6. Create writable private directories `storage/files`, `storage/logs`, and `storage/tmp`; deny web access. The configured document root must remain the candidate's `public/` directory.
7. Point a staging subdomain at `<candidate>/public` and require TLS.

Required PHP extensions: `json`, `openssl`, `pdo`, `pdo_mysql`, `fileinfo`, and `zip`. Required PHP limits must exceed the application limit: `upload_max_filesize >= 25M`, `post_max_size >= 26M`, and a suitable request timeout.

## 3. Protected preflight and migration

Temporarily set these host-only values:

```text
MAINTENANCE_ENABLED=1
MAINTENANCE_TOKEN=<at-least-32-random-characters>
MAINTENANCE_ALLOW_MIGRATIONS=0
```

Send an authenticated `POST /maintenance.php` with `Authorization: Bearer <token>` and JSON `{"action":"preflight"}`. Confirm PHP, database, migrations, and storage are ready. Mail remains unavailable until real SMTP is configured; public registration and password reset intentionally fail closed meanwhile.

Before applying migrations, create and verify an encrypted backup. Put its returned `backupId` in `LAST_VERIFIED_BACKUP_ID`, set `MAINTENANCE_ALLOW_MIGRATIONS=1`, and send `{"action":"migrate"}` with the same bearer token plus `X-Backup-Id: <backupId>`. Immediately return both maintenance flags to `0` after the request.

## 4. Staged acceptance

On the staging hostname, verify:

- `/api/v1/health` is 200;
- `/api/v1/readiness` accurately reports every dependency;
- login, CSRF, organisation scope, and one representative project read/write work;
- another organisation cannot read the project or a known file id;
- project data persists after a full reload;
- an allowed file uploads/downloads and a disguised executable is rejected;
- desktop and mobile V1 shell geometry remains faithful;
- cron produces a fresh successful dispatch report;
- SMTP verification/reset messages arrive before enabling public registration.

## 5. Promotion and rollback

Preferred promotion changes the control-panel document root from the previous version's `public/` directory to the verified candidate's `public/` directory. Keep the previous immutable release present. If the host cannot switch atomically, enable its maintenance page, upload the candidate completely, verify the re-downloaded manifest, then make the smallest final front-controller change.

Rollback application code by restoring the previous document root. Do not roll back a database migration unless its documented inverse has been rehearsed against a restored copy. Additive Release 1 migrations are designed for forward compatibility. A failed migration or integrity check stops promotion; it is not an acceptable warning.

## External release gates

Local completion does not certify the shared host. Production promotion remains blocked until real hosting credentials, SMTP, TLS/domain configuration, remote MariaDB migration, writable private storage, cron freshness, encrypted backup download, and isolated restore rehearsal all pass on the target environment.
