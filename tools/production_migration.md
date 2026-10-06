# Production migration operator boundary

`tools.production_migration.MigrationPipeline` implements the ordered recovery gates;
`production_host_backup.php` calls the actual AES-256 BackupService and fingerprints
its immutable archive; `production_restore.php` runs the actual RestoreService with
explicit operator-local environment and its existing loopback/task namespace guard.
No network call occurs on import. These tools are not public endpoints or runtime
proof buttons. Contract fixtures are exclusively in tools/tests.

## Required hosting adapter (still fail-closed)

Do not wire the old `deploy_cpanel_session.py migrate` single helper to DDL. Implement
HostOperations only after exact authenticated account/domain/documentRoot/runtimeRoot
and actual selected database identity are independently observed. Parent controls
hosting access and must obtain the existing runtime settings without printing them.

Before issuing quiescence receipts, install a fenced maintenance gate covering ALL
web entry points and all writes, suppress the **owned** dispatch/cron launchers,
and drain in-flight HTTP requests and queue workers. Existing MAINTENANCE_ENABLED
only authenticates maintenance.php; it is NOT a global web-write gate. There is no
established owned-worker stop/drain or cron ownership adapter in these sources.
A cron listing or runtime.php boolean is not proof. Use the actual host scheduler's
documented interface once ownership is established, preserve unrelated entries,
read back removals, and verify no owned process can still write. If the host cannot
prove drainage, retain maintenance and refuse backup/DDL. Do not guess cPanel params.

The adapter must pin runtime.php SHA byte-for-byte and active AND candidate SQL trees
on every observation. SQL tree digest is SHA-256 of Python canonical JSON mapping
sorted SQL basenames to file SHA-256. Receipts use canonical JSON HMAC-SHA256 with a
fresh 32-byte per-session key provisioned out-of-band to authenticated one-time helper;
never accept receipt envelopes from CLI input or operator booleans. Include challenge,
exact target, stage, lease and all pipeline binding fields. Do not echo client binding
values blindly: recompute host fingerprints, check the fenced lease server-side and
persist the restore receipt/archive binding before any MigrationRunner invocation.

`backup` calls a777CreateRetainedBackup with actual active PDO/private fileRoot and
existing backup password/APP_KEY, in retained non-web-accessible storage. Supply the
signed BackupReport-derived fields, then transfer that encrypted archive unchanged.
`candidate` calls candidate MigrationRunner.status/migrate only through the lease-owning
adapter. Return the actual serialized MigrationReport under `report`. Exceptions and
lost replies are potential partial DDL; never retry automatically or rename files and
call that DB rollback. Persist server-side operation intent/results before replying.
`readiness` reads candidate database/migrations/jobs/mail readiness and build identity
under maintenance. `cleanup_helpers` must remove helpers and read back each exact
path as absent, treating listing failure as unknown, not absent.

## Operator-local restore

Construct LocalRestore with absolute PHP binary, this repository, explicitly supplied
subprocess environment, and an empty task-owned private file root. Include system
subprocess essentials plus APP_ENV=test, ARCHITEX_ALLOW_DISPOSABLE_DB=1, loopback
DB_HOST, DB_NAME=architex_task<owned-id>_<8hex>_test, local DB credentials,
BACKUP_ARCHIVE_PASSWORD and APP_KEY. The restore is destructive ONLY to this guarded
local disposable schema. Never pass production connection settings here. Supply
byte-identical ACTIVE migrations to restore, not candidate migrations: RestoreService
requires its newly applied versions to exactly equal the archive's migration list.
The local PHP bridge authenticates manifest HMAC/entry hashes through RestoreService;
its receipt contains backupId, manifest SHA, actual restored row/file counts/database.

Call pipeline.run(active_migrations, candidate_migrations). Candidate methods are
unreachable until restore returns a matching archive/tree/backup receipt. A final
verified result retains maintenance lease and both backups; this library intentionally
exposes no promotion/unquiesce shortcut. Parent must separately review promotion,
read back live build/readiness, and restore only owned controls after success. Store
retained_directory outside repository/web roots with operator-only OS permissions.
On failure inspect migration-<challenge>.json, retain remote/local backups and partial
DDL recovery state; journal databaseRollbackPerformed remains false.

## Evidence limits

The new unit tests exercise controlled transport/restore fixtures and failure ordering.
No production call, encrypted live backup, or real disposable restore was performed
for this change: no explicit safe operator source and hosting lease controls were supplied.
This is an implemented orchestration/service bridge, NOT a completed hosting adapter.
