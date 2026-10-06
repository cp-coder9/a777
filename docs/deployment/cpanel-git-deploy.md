# cPanel Git hook: native preflight and guarded recovery integration

**Not a completed production deployment implementation.** `.cpanel.yml` now explicitly runs `--preflight` (the CLI/shell default). It cannot auto-deploy through plan changes. `--deploy` and `--stage-only` each require matching explicit plan modes. Change the example below to mode `preflight` for capability inspection.

Native preflight calls fixed authenticated UAPI Mysql list_databases/list_users and Cron list_cron before reading installed private config. Available observations survive missing-config failures in private immutable receipts. Only installed account-owned 0600 runtime.php is loaded in RAM; actual DB_HOST/DB_NAME/DB_USER/DB_PASSWORD configure PDO and DATABASE()/CURRENT_USER() must corroborate 127.0.0.1/archite4_dev_rel1. Active SQL bytes are hashed. Unknown native inventory shapes fail closed. Exclusive visibility requires the unique source database `users` list to contain only the actual existing app user; native shape remains unverified. Only then does own-user SHOW FULL PROCESSLIST report exact source connections, without global PROCESS or Ed25519 requirements. Drainage is observation, not enforced dispatch exclusion.

`CpanelGitDeploy::recovery()` now integrates actual BackupService, AccountOwnedRestoreTarget::provision(sourcePDO, '127.0.0.1', actual DB_USER), and RestoreService::restoreIntoProvisionedEmptyDatabase using active SQL. Secure random archive/HMAC secrets remain private 0600 outside archived storage. Archive SHA/target/readback receipts are retained. Actual source/target schema and row multiset hashes and restored-file SHA/size are compared. Schema comparison ignores AUTO_INCREMENT high-water values; row hash lists are not bounded-memory. Native provider/SQL execution is unverified, and this primitive is deliberately **not called by the hook until writer exclusion is implemented**.

`--deploy` records capability evidence then refuses before live writes/DDL with `unknown_writer_dispatch_exclusion_and_verified_promotion_not_implemented` or an earlier capability gate. It cannot silently report staging success. Still missing: atomic Apache byte maintenance/readback and external curl 503; exact owned Cron suppression/readback; old-bootstrap fence plus exclusion of unhooked workers/unknown same-user dispatchers; actual candidate MigrationRunner gates; byte-identical config and full component/build readiness; atomic promotion/lost-response reconciliation and restoration only after readiness. No provider worker or production DB was executed here.


The shell runs the fixed ea-php83 private CLI. A successful default task means native inspection only, not staging/promotion. Parent/operator must review before remote invocation.

## Private prebuilt artifact input

Upload a clean **production ReleaseBuilder** artifact (locked Composer install with real vendor; not a fixture installer) to `/home/archite4/.a777-deploy/incoming/<full-40-hex-commit>`. The artifact layout is the actual flattened ReleaseBuilder layout: `public`, `src`, `views`, `database`, `cron`, `config`, `bin`, `bootstrap.php`, Composer manifests, `vendor`, release metadata/manifest. Do not upload repository files into the document root. Incoming files must not contain runtime.php or secrets. The hook verifies provenance and the actual ReleaseVerifier twice and binds the complete artifact tree including release-manifest.json.

Create the account-owned private root mode 0700 and `operator-plan.json` mode 0600, outside public_html. Exact plan fields:

```json
{
  "mode": "stage-only",
  "account": "archite4",
  "repository": "/home/archite4/repositories/a777",
  "activeRoot": "/home/archite4/public_html/.a777-runtime-active",
  "documentRoot": "/home/archite4/public_html/php.architex.co.za",
  "expectedCommit": "FULL_REVIEWED_40_HEX_COMMIT",
  "artifactTreeSha256": "64_HEX_TREE_DIGEST"
}
```

Compute tree digest with the reviewed `CpanelGitDeploy::digest(CpanelGitDeploy::inventory($artifact))`; it hashes JSON with sorted relative-path keys, SHA256 and byte size, unescaped slashes, including the manifest. It is not a tar/zip checksum. Modes are preflight, explicit stage-only, and guarded deploy (currently refuses the missing enforcement/promotion gates). Unknown actions fail closed. The previous `60c3d6fb` clone revision is context, not a valid full-commit authorization for changed code.

The hook requires master, a clean checkout, exact account/root, private plan ownership, and an explicit commit match. Native subprocess arguments are arrays; no credentials are passed or captured. Artifact code/vendor/bootstrap is never executed. Intent precedes candidate copy; readback verifies source stability and exact destination bytes. Failure leaves private intent/partial candidate for inspection rather than guessing recovery. Repeated invocation refuses existing intent/candidate; review these privately rather than deleting them automatically. Receipts bind commit, plan SHA, artifact tree digest and report pending gates. No migration helper is exposed over HTTP.

## Concrete migration blocker found in existing source

The old RestoreService::restore() path remains namespace-restricted. The new AccountOwnedRestoreTarget native capability and restoreIntoProvisionedEmptyDatabase path avoid that incompatibility without relaxing DisposableDatabaseGuard. Actual UAPI issuance/restore are wired in the recovery primitive but remain native-unverified; the live deployment blocker is now enforced writer exclusion and verified promotion, not the old namespace limitation.

## Native migration path gates still required (NOT implemented/tested)

The retained receipt lists these gates, not fabricated provider attestations:

1. Discover `/usr/local/cpanel/bin/uapi` as the logged-in account. Use official Mysql create_database, set_privileges_on_database and read-back inventory APIs; confirm exact provider parameter/output shapes before implementing writes. Grant the existing app user only the owned temporary restore database. No generated DB password in argv/environment/logs. This worker made no provider calls.
2. Journal exact existing Apache bytes at the exact document root and enforce a verified maintenance boundary. Journal actual native `crontab -l`; remove only independently identified exact app-owned entries and read back. Never replace unrelated cron jobs or automatically expire maintenance.
3. Install a byte-verified private old-bootstrap hook before PDO and drain the actual persistent MaintenanceFence. Its lock only proves callers of enter() drained, not old unhooked processes.
4. Independently inventory every user granted access to `archite4_dev_rel1`. Own-user SHOW PROCESSLIST can establish drainage without global PROCESS only when all relevant grant users are independently verified to be that same app user; otherwise refuse. No broad KILL or global read_only.
5. Generate backup encryption secret securely on-host, private 0600, never in Git/config/logs. Store recovery archive outside FILE_STORAGE_PATH; invoke actual BackupService and bind archive SHA. Invoke actual RestoreService with a byte-matching ACTIVE migration tree and prove restored rows/files and backup identity, after resolving the namespace blocker.
6. Verify canonical pending 0062–0067,0069,0070 and invoke actual candidate MigrationRunner only after those gates. Preserve encrypted backup, receipts, maintenance and recovery state on partial DDL; file rollback is not DB rollback.
7. Verify full release, readiness component semantics and code promotion readbacks before restoring owned Apache/cron bytes and resuming the fence. Runtime config must remain byte-identical.

No host/SQL/fence/Apache/cron/backup/restore/promotion gate has been tested by the local file-fixture suite.

## Official references

- https://docs.cpanel.net/knowledge-base/web-services/guide-to-git-deployment/ (checked-in YAML tasks, clean checkout, manual pull/deploy)
- https://api.docs.cpanel.net/specifications/cpanel.openapi/user-management/mysql-set_privileges_on_database (official endpoint identified; extraction unavailable)
- https://support.cpanel.net/hc/en-us/community/posts/19661089848215-Creating-databases-and-users-with-uapi (CLI creation/grant context, not host capability proof)
