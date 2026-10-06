# Isolated cPanel restore rehearsal (no promotion)

`NEWtools/CpanelRestoreRehearsal.php` exports
`Architex\Deployment\CpanelRestoreRehearsal::run(AppConfig $config, PDO $source,
string $canonicalStorage, string $runtimePin, string $expectedCommit,
string $planMode, string $trustedRepository): array`.

## Caller contract

Call from the authenticated **Linux archite4 account shell** only, after the parent's
actual pinned-source/provider preflight. Pass actual AppConfig and its actual PDO,
canonical FILE_STORAGE_PATH, exact pinned private runtime SHA-256, actual clean Git
HEAD (40 lowercase hex), literal `restore-rehearsal`, and canonical clean checkout.
Do not pass Boolean attestations, environment-derived guesses, imported provider
receipts, an uploaded artifact, candidate migrations, or production credentials to
local test workers. There is no public HTTP endpoint or dependency injection seam.
The module does not change the existing config or another owner's issuer/hook.

The checkout itself must be account-owned 0700. Already-loaded Architex classes and
explicit backup/restore dependencies must resolve inside it and match committed
Git blobs. All app/src files are checked for canonical non-symlink tracked byte
identity. Caller must load the trusted reviewed autoloader, not incoming bootstrap.
Deploy this component and reviewed issuer in a clean commit before calling it;
the current uncommitted development tree intentionally cannot pass this gate.

Private config is `/home/archite4/public_html/.a777-runtime-active/config/runtime.php`
(owner 0600 in owner-0700 config directory). Its actual AppConfig and storage value
must exactly match the caller. APP_KEY is read from this pinned file into RAM, never
from a new environment value. Config is rechecked by the existing managed target
preflight, including actual PDO selected DB, CURRENT_USER, host/port/server and
provider inventories. Any managed production-config compatibility changes remain
with the issuer owner, not this component.

Account token comes exclusively from `CpanelRestoreProvisioner::fromAccountToken()`;
provisioning exclusively uses `AccountOwnedRestoreTarget::provisionManaged()`.
No reflection/injected provider in production, no caller-specified target database.
Provider host authorization and source-pin prerequisites still require the parent's
actual hosting proof; local green fixtures cannot satisfy them.

## Evidence and retention

Before backup, the module checks InnoDB source tables and sets the source's **next
transaction READ ONLY**. BackupService performs its actual repeatable-read snapshot;
there is no source DDL or source-row update. No application privileges are revoked,
no fence is installed, and no writer quiescence is claimed.

An unpredictable owner-0700 directory is created outside public_html and outside
source storage: `/home/archite4/.a777-deploy/recovery-<commit>-<nonce>`.
The recovery root is canonical owner-0700. Storage and its descendants must have
owner-0700 directories, owner-0600 regular single-link files, and no symlinks.
The random archive password is durably written to a private owner-0600 append-only
`journal.jsonl` before backup. It must NEVER be logged, copied into Git/artifacts,
or printed to chat. Neither APP_KEY nor database credentials are journaled.
The actual BackupReport.path is hashed, not a guessed archive filename.

A new exact provider-owned target receives the same host/port/user/password and
all AppConfig settings; only DB_NAME changes in RAM. Target provisioning intent
and its actual capability receipt are retained. An ambiguous provider failure may
leave its own durable `/home/archite4/.a777-deploy/restore-<target>.json` receipt even
if this component never receives the capability. Operators inspect both journals.

RestoreService uses **only** active `database/migrations`, not checkout/candidate
migrations. Its managed path verifies the authenticated archive, source identity,
exact archived schema_migrations checksums, empty target identity and full import.
Restored files go into `recovery-.../restored-files`, never live storage.
The actual RestoreReport must match archive backupId, target DB and row/file totals.
A second actual BackupService snapshot of the target then verifies every table's
row count and encoded row hash/bytes, migration IDs and every file hash/bytes/binding
against the immutable SOURCE ARCHIVE. It does not reread changing source data.
The original archive SHA must still match. Target verification archive is retained.

Every attempted target is retained, on success or failure; no automatic DROP,
cleanup, promotion, automatic resume or source recovery exists here. Future cleanup
must be separately authorized and restricted to receipt-owned exact targets.

## Safe return shape

Always contains `status`, `lastCompletedStage`, `isolatedRehearsalPassed`,
`sourceDatabaseDdlTouched=false`, `liveFilesTouched=false`, and
`targetDatabaseDdlTouched`. Status phases are `backup-created`, `target-provisioned`,
`rehearsal-verified`; any refusal/failure returns `status=failed`, preserving the
last completed stage. Available evidence adds `archiveSha256`, `backupId`,
`rowCount`, `fileCount`, `targetDatabase`, and on full success `tableCount`.
No password/config/key/raw provider responses/exception strings are returned.
`targetDatabaseDdlTouched=true` is a conservative **may-have-touched** flag from
provisioning intent onwards (including a lost reply), not proof the API succeeded.
A complete success is only `isolatedRehearsalPassed=true`; it is NOT a recoverable
production backup certificate, production write fence, or permission to run source DDL.
If journal persistence fails, failure is returned and prior receipts are retained;
operators must reconcile, not automatically retry.

## Executed local checks

```text
php -l NEWtools/CpanelRestoreRehearsal.php
php -l NEWtools/tests/CpanelRestoreRehearsal_test.php
php NEWtools/tests/CpanelRestoreRehearsal_test.php
php -l NEWtools/tests/CpanelRestoreRehearsal_native.php
php NEWtools/tests/CpanelRestoreRehearsal_native.php
php app/tests/Release/restore-integrity-native.php
```

Reflection/caller-contract fixture: **41 assertions**, including row/hash/bytes/file,
migration and identity corruption, explicit plan refusal and unmockable host guard.
Native local integration: **12 assertions**, using real BackupService and
RestoreService, real encrypted archives, binary rows/private file, deliberate source
mutation after snapshot, detected target corruption, and exact owned cleanup.
It invokes only the component's comparison via reflection; it does not impersonate
the production host or claim full managed run/provider authentication.
Existing native restore integrity fixture: **84 assertions + 16 exact owned cleanup
assertions**, covers actual managed-capability reflection and legacy import engines,
foreign keys, row/file failures, malformed bindings and retained failed staging.
Its two filesystem warnings are deliberately forced file-write-refusal cases.
Native tests are pinned to scratch MariaDB port 3398, version 11.4.3 and exact local
datadir, never hosting. The native fixture uses the existing external borrowed-vendor
bootstrap with candidate reflection guards. No hosting credentials are used.

The first native fixture attempt caught retry-safe migration syntax (missing IF NOT
EXISTS), fixed before successful rerun. That attempt retained local synthetic schema
`architex_taskrehearsal_f11b50b5_test`; no broad cleanup was performed. Later runs
persist exact fixture names before creation and clean only those tracked schemas.
This local residue is not a production recovery target.
