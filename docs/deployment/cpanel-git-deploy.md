# cPanel Git hook: review-gated staging only

`.cpanel.yml` executes the real private CLI hook from `/home/archite4/repositories/a777` using cPanel ea-php83. It does **not** migrate, touch the live runtime/configuration, establish maintenance, or promote public files. A successful cPanel task means **staged-not-promoted**, never a completed production deployment. Parent/operator must review before invoking VersionControlDeployment/create. No remote operations have been performed by this implementation.

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

Compute tree digest with the reviewed `CpanelGitDeploy::digest(CpanelGitDeploy::inventory($artifact))`; it hashes JSON with sorted relative-path keys, SHA256 and byte size, unescaped slashes, including the manifest. It is not a tar/zip checksum. Stage-only is the only accepted mode. Unknown actions fail closed. The previous `60c3d6fb` clone revision is context, not a valid full-commit authorization for changed code.

The hook requires master, a clean checkout, exact account/root, private plan ownership, and an explicit commit match. Native subprocess arguments are arrays; no credentials are passed or captured. Artifact code/vendor/bootstrap is never executed. Intent precedes candidate copy; readback verifies source stability and exact destination bytes. Failure leaves private intent/partial candidate for inspection rather than guessing recovery. Repeated invocation refuses existing intent/candidate; review these privately rather than deleting them automatically. Receipts bind commit, plan SHA, artifact tree digest and report pending gates. No migration helper is exposed over HTTP.

## Concrete migration blocker found in existing source

`RestoreService::restore()` calls `DisposableDatabaseGuard`, which accepts only loopback, APP_ENV=test, explicit disposable opt-in, and `architex_task..._<8hex>_test` schemas. The actual cPanel account uses the `archite4_` namespace. RestoreService also drops and recreates the disposable schema via the supplied app PDO account before applying migrations; creating a provider database alone does not prove those grants are usable. Do not substitute a guessed name, bypass the guard, import an arbitrary SQL dump, or claim a successful recovery test. This worker is explicitly scoped away from editing those owned services. Until a reviewed guard/service extension accommodates independently proven account-owned disposable names, automatic live DDL cannot be safely enabled.

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
