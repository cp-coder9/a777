# Concrete operator safety and recovery prerequisites

This adapter is not production approved. No hosting or production database was contacted by the safety tests.

## Mandatory configuration

- Exact canonical `/home/<account>/...` document, runtime, candidate, helper and recovery paths. Traversal, URL metacharacters and noncanonical/symlink runtime paths are refused before runtime loading.
- `recoveryPaths`: `maintenanceDirectory` must equal the existing configured file-storage path plus `/maintenance`; `archiveDirectory` must equal the storage parent's `/operator-recovery`, outside runtime, public root and archived storage.
- `processModel`: `inventoryPath` is an exact account-scoped provider observation file; `publicKey` is a pinned base64 Ed25519 provider public key. The provider must independently verify real FPM/LSAPI workers are idle and gate-aware, and continuously refresh the signed inventory. No process is killed and no visibility check is skipped.
- Inventory envelope: `{payload,signature}` where signature is base64 Ed25519 over canonical JSON payload. Payload contains `target`, `gateFingerprint` (SHA256 canonical target), `leaseOwner` (persisted fence owner), `bootstrapSha256`, `observedAt`, `expiresAt`, `model` (`fpm` or `lsapi`) and `processes` keyed by decimal PID. Every observed same-UID process other than the current helper must match `commandSha256` (raw `/proc/PID/cmdline`), string `startTicks`, `state: idle`, `gateAware: true`, and `kind` (`fpm-master`, `fpm-worker`, `lsapi-worker`). Observations older than five seconds or with a future timestamp are refused. Provider proof must attest the enforced gate, not merely a process name.
- Full DB PROCESS visibility, `/proc` ownership/command/stat visibility, POSIX and sodium PHP extensions, functional CLI gate probe, exact scheduler inventory including all five schedule fields, and explicitly declared owned/unrelated commands.
- Task-owned guarded local restore environment, real candidate provenance and actual readiness must be supplied before deployment approval.

## Durable ownership

Before upload, the adapter protects the local recovery directory (Windows explicit current-user-only DACL; POSIX 0700), acquires an operator lock, and writes/fsyncs the fresh session key, helper path, target, candidate root and server recovery paths. Local files are atomic 0600 writes. The helper holds a shared runtime operator lock across each request, independent of session key. Retained metadata is sensitive: do not paste it into chat or publish it.

Do not restart with a new key following an ambiguous response. Reuse the recorded exact helper/key and query authenticated `recovery_state`; it exposes only operator state/fence receipts, including the lease, without opening PDO and even after the DDL authorization TTL expired. It does not renew a lease or authorize DDL. The adapter's automatic cleanup refuses removal while it owns recovery effects. The pipeline distinguishes verified `removed` from authenticated `retained_recovery` outcomes using helper-byte and recovery-state readbacks. Intentional retention after verified migration is reported as `migration_verified_recovery_helper_retained`, not cleaned deployment success; failures remain `recovery_required` and preserve the original exception. Unexpected retention is not accepted. Final CLI cleanup never deletes the sole recovery channel.

## Reconciliation, not replay

Cron removal records selected row, entire before inventory and expected after inventory before calling cPanel. Complete command/schedule multisets must match after removal. A lost response with the exact expected effect can be reconciled; unchanged, wrong-deletion or concurrently edited inventories remain unresolved and cannot be blindly replayed. Provider cron has no compare-and-swap: stop all other scheduler editors operationally. Entries and their schedules remain suppressed until an explicitly reviewed restore; no automatic resume is provided.

Hook installation prevalidates every source with PHP token parsing and destination ancestry, captures original bytes and journals intended hashes before the first rename. `reconcile_hooks` compares actual bytes against original/intended hashes, including effects whose observation update was lost. `restore_hooks` restores only matching owned bytes and refuses any active maintenance lease (including expired/corrupt lease files). Never remove a gate during a lease. Hook restore itself has persistent intent and may be reconciled again after interruption.

Server backup intent/backup identity are immutable per session. A second backup cannot replace the recovery archive. Status persists immutable complete restore authorization before migration. DDL persists intent before calling the actual runner; any replay of `migrate` is refused even after a successful response was lost. Use status and recovery state to inspect; do not clear DDL intent or describe filesystem restoration as database rollback.

## Verification scope

Focused tests exercise concrete adapter receipt-to-pipeline acceptance, complete cron schedule readback and lost-response reconciliation, upload-crash key retention, traversal refusal, native PHP authenticated hook installation/reconciliation/owned restore, active-lease refusal and native cross-process operator locking. Existing pipeline/fence tests cover partial DDL and authenticated replay rejection at the pipeline boundary.

A full native Linux helper + provider-proof + guarded MariaDB BackupService/RestoreService/MigrationRunner run remains necessary. The Windows-only local PHP fixture cannot prove Linux process enumeration/provider observations or real DDL replay prevention. Do not substitute mocks for this deployment prerequisite.
