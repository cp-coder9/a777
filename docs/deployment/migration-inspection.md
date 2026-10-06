# Safe production migration inspection

Manual Actions mode: `migration-inspect` in `.github/workflows/deploy-php.yml`.
The production variable `A777_EXPECTED_DATABASE` (or `--expected-database`) is an
optional exact assertion, not a required human input. Without it, the authenticated
helper observes AppConfig DB_NAME and PDO SELECT DATABASE(), requires equality, then
the runner matches that exact name in authenticated UAPI Mysql/list_databases.
Never select the only database or infer a name from an organisation. Official API:
https://api.docs.cpanel.net/openapi/cpanel/operation/list_databases/ (account-scoped
`result.data` rows with `database`; user arrays are never reported).
The existing API-token secret is the only hosting authentication input. No runtime
credential is read by the Actions runner or included in evidence.

Executable command (after downloading the verified release artifact):

```sh
python tools/repair_php_deployment.py migration-inspect \
  --release "$RUNNER_TEMP/php-release" \
  --target-url https://php.architex.co.za \
  --document-root /home/archite4/public_html/php.architex.co.za \
  --account-home /home/archite4
```

The job confirms authenticated cPanel account home and DomainInfo document root,
then creates a randomly named, short-lived, authenticated helper in that exact root.
The secret is checked before loading any runtime dependency. The helper does not
load bootstrap (which would enter/create fence locks). It connects through the
existing private runtime settings and compares configured and SELECT DATABASE()
identity, optionally asserts the supplied name, and returns the exact observed
name and SHA-256 of the unchanged private runtime config, bound to the exact active
runtime root, helper digest and authenticated challenge. The runner crosschecks the
account-scoped MySQL inventory and rechecks authenticated account home before
reporting `databaseIdentity.independentlyConfirmed=true`. Full private config stays
on the host, unchanged and never logged; no DB user/password or APP_KEY is returned. A READ ONLY
transaction reads schema_migrations and its schema contract. It compares the actual
ledger checksums with the verified candidate's canonical SQL-file inventory. Unknown
ledger values and grant/cron/proc contents are never printed. Mismatches suppress all
IDs and remain a refusal boundary, not permission to repair the ledger.

Only `NNNN_snake_ids` from the actual local SQL-file inventory can be returned;
counts, uniqueness, capability booleans and receipt challenge are checked strictly.
The existing preflight adds validated pendingVersions for CLI diagnostics only;
its legacy HTTP probe is not turned into a public migration-ID endpoint. Host IDs
are available exclusively through the authenticated inspection helper.

Capability results distinguish PROCESS grant, same-account /proc readability,
proc_open availability, actual /usr/local/bin/php >= 8.3 execution, sodium signature
verification availability, ZIP AES-256 encode/decode support, backup password/HMAC
key shape, canonical private storage and observational writability. These are
necessary support checks, NOT backup readiness proof. No archive is created and no
restore runs. Archive-directory separation, sufficient capacity, file integrity,
backup read-back and a real disposable restore still require subsequent gates.

Cron API2 listcron is observational only. Complete inventory is validated without
printing commands or schedules. An empty complete inventory establishes that no
entries need ownership assignment at that observation; any nonempty inventory
leaves entry ownership unestablished. No command-substring ownership heuristics,
remove_line, add_line or scheduler changes occur.

After success OR failure (including lost upload acknowledgement), the runner
re-authenticates the helper and checks its exact SHA-256 before requesting self
unlink; an exact cPanel listing must then prove absence. Replaced bytes are never
deleted. Listing failure is unknown, not absence. Inspection expiration does not
prevent authenticated identity/cleanup. An interrupted/killed runner may leave the
random helper: it refuses expired inspections, but there is no claim that timeout
itself performs cleanup. Keep run concurrency serialization and review cleanup
failures before another deployment.

## Exact executable DDL boundary for the reported eight pending migrations

This change does not know their IDs until the parent executes migration-inspect;
it never manufactures them from a count. Existing `deploy` remains fail-closed at
migrations_pending. There is no unconditional migrate command or alternate bypass.

The existing actual code path is:
`MigrationPipeline.run(active_migrations, candidate_migrations)` with
`CpanelHostOperations` and `LocalRestore` (see tools/production_migration.py and
cpanel_host_operations.py). It requires approved exact active bootstrap/hook
identity; persisted owned maintenance lease; complete scheduler ownership and
verified owned suppression; full DB visibility; actual drained fence-aware web and
worker processes; retained encrypted BackupService archive; identical transfer;
actual RestoreService restore into its guarded local disposable namespace using
byte-identical ACTIVE migrations; matched archive/tree/target/lease receipts;
then candidate MigrationRunner.migrate and readiness under the lease. RestoreService
requires the archived migration set exactly, so candidate SQL is not a substitute
for active SQL in the recovery rehearsal.

The currently implemented worker-drain verifier additionally requires a fresh,
Ed25519 provider-signed process inventory bound to target, gate fingerprint, lease,
bootstrap hash, process start ticks and gate-aware idle worker identity. cPanel file,
DomainInfo and Cron APIs do not supply this attestation. sodium+/proc+PROCESS true
cannot create it. This inspection deliberately returns providerInventoryEstablished,
quiescenceEstablished, recoverableBackupEstablished and ddlAuthorized=false. The
CLI also reports `providerEd25519InventoryEvidence="unavailable"`. The
provider inventory API/public key is unknown; do not invent either, sign a local
fixture, remove the verifier, or use an operator boolean to satisfy it. If the host
cannot supply that existing verifier's evidence, DDL remains blocked at quiescence;
a separately reviewed concrete alternative drain verifier is required before the
migration pipeline can be deployed for execution.

This worker change runs local contract tests/lint only. The parent must publish
and run the Actions job, obtain the actual eight IDs/capabilities, and report that
run separately. No hosting secret reads, remote operations, commits or pushes
were performed by this worker.
