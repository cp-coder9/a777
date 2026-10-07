# Native deployment runner / hook integration

Runner: `tools/cpanel_native_stage.py`, invoked only by explicit `cpanel_git.py native-deploy`.
Production CI uses PHP 8.3 and real locked Composer dependencies, ReleaseBuilder production mode, external RUNNER_TEMP output, and ReleaseVerifier. No borrowed vendor or staging override. SHA must equal clean local HEAD and authenticated remote master HEAD.

Private incoming layout is `/home/archite4/.a777-deploy/incoming/<40hex>/` with flat release roots (`public`, `src`, `vendor`, `release-manifest.json`, etc). Unique zip is uploaded to incoming, extracts a commit-prefixed tree, is then trashed with absence readback. Exact manifest bytes are authenticated-read back before issuing the native task. The hook must independently verify and copy the entire tree before executing candidate application code. Digest includes the manifest, sorted relative name -> `{sha256,bytes}` compact JSON, matching CpanelGitDeploy::digest.

Plan includes both dispatcher and native class pins: expectedRuntimeConfigSha256/runtimeConfigSha256, artifactTreeSha256/treeDigest, artifactManifestSha256, buildSha256, sourceDatabase, artifactPath, eight latest candidate migration stems. Native class independently compares that exact list to actual pending migrations; these names are not an attestation that the live source has them pending. Fixed config pin: `5206e3a1f9447e6834fc3e2dd7cd254ce5dd47af33406c0c83085c4b4f28d0b8`.

Existing restore plan rollover requires exactly one actually completed commit/old-plan-bound rehearsal receipt, source DDL/live files false, archive SHA and backup ID, generated target namespace and independently authenticated Mysql inventory containing that exact target. Recovery/password directories are retained and journal.jsonl is never read.

## REQUIRED dispatcher receipt wrapper (integration acceptance gate)

Hook-owned dispatcher must write owner-0600 single-link private `native-deploy-<commit>-<16hex>.json`, not `native-deployment-receipt-<32hex>.json`. Runner intentionally fails closed on the latter until integrated.

Required envelope:

- format: `a777-cpanel-native-deploy-v1`
- mode: `native-deploy`
- commit, planSha256: actual approved checkout and actual private plan bytes
- phase: `complete`, `failed`, or `recovery_required`
- deployed: true ONLY when actual native class status is deployed and lastCompletedStage is deployed
- artifactVerified: actual independently verified full source/candidate artifact inventory
- artifactTreeSha256, artifactManifestSha256, buildSha256: actual readback hashes, not blindly copied plan claims
- readinessPassed: true only after actual readiness proof
- maintenanceActive, sourcePrivilegesRevoked: actual resolved final safety state
- sourceMigrationCount: actual integer source migration count
- archiveSha256, backupId: actual native recovery result
- deploymentId: actual 32hex owned namespace ID, when created

Never wrap a failure as deployed. Original native class statuses are not success receipts. The runner binds provider deploy_id/task_id/commit and terminal timestamps, then independently requires complete/readiness/artifact receipt before reporting runtime_deployed.

On failed or unknown active task/receipt, recovery_required, maintenance retained or source privileges revoked, retain the protected token and report `tokenRetainedForRecovery: true`, `tokenAbsenceVerified: false`. No automatic repair or replay. On proved safe terminal failure or completed success remove token and authenticate absence. Future manual authenticated recovery is separate authorization.

## Read-only failed-task diagnostics and safe retry

`python tools/cpanel_git.py native-status` authenticates the exact account/home, hashes the current private operator-plan bytes, and reads the old commit/plan-bound native receipt plus independent terminal deployment state. It never builds, pulls, provisions a host token, changes plans, or issues a deployment. The workflow skips its artifact build for this mode. Reports export allowlisted failureStage, lastCompletedStage, failureCode, failureType and failureLine; raw exceptions and provider messages are excluded. Codes are privately branded fixed coordinator gates, not exception-message matches. Older receipts lacking failureCode cannot retroactively establish an exact gate.

`retry_safe_native_deploy` defaults false. New-commit rollover requires an explicit typed safe failed receipt, pre-source-DDL completed stage, independently failed matching task, absent token, and private archived old-plan readback before replacement. Missing/unknown safety flags refuse. Consumed markers must bind the exact deployment ID/commit/plan SHA to that safe failed receipt or complete receipt. Retained password-bearing journals remain opaque.

**Retry remains fail-closed in CLI until the parent integrates the independent read-only controller preflight** through `readonly_source_preflight`: require the new commit, original pinned config SHA, actual `archite4_dev_rel1`, eight pending migrations and read-only/no-DDL evidence before build/pull/upload or plan writes. The callback is a trusted controller integration boundary, not a workflow boolean attestation. Coordinator independently repeats actual config/database/pending checks. Existing source backup evidence (280/747, as supplied by operator context) is unchanged; these edits did not re-observe it.

## Local proof

Clean detached HEAD `3530d58d7f8c4c4a9e5f81af5319dd1ee51d05e8` built using actual PHP 8.3.32 and downloaded Composer PHAR with real locked production installation. ReleaseVerifier and runner validated 769 files including manifest; deterministic archive validated. Tree SHA `3492fc4dfd21931d5e6deb5d5a96597d900f903a95ae402c17cf4ba9db9bb5aa`. Build time metadata makes independently rebuilt releases differ, but identical validated release inputs produce identical ZIP bytes. Output: `E:/Hermes/home/cache/scratch/native-runner-proof/output/verified-production.zip`. Not a provider deployment proof. Current working additions require parent commit/CI before their own production provenance can be proved.

Initial full clone timed out; clean detached worktree build succeeded. No hosting credentials, host operations, commit or push were performed.
