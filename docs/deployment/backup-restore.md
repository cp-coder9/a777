# Encrypted backup and isolated restore rehearsal

Architex backups contain a repeatable-read MariaDB snapshot plus every active private stored file. Each archive entry is AES-256 encrypted by libzip, SHA-256 checked, and covered by an HMAC-authenticated manifest. Store `BACKUP_ARCHIVE_PASSWORD` separately from the stable base64 `APP_KEY`; losing either prevents recovery.

## Create and download a backup

Run from an approved local/admin execution context or a protected control-panel scheduled task:

```text
php app/bin/backup.php --env=production --output=/private/backups/architex-YYYYMMDD-HHMM
```

The command returns the immutable `.axbackup` path, backup id, database row count, and private-file count. Download it over the host's encrypted file-transfer channel, retain the JSON result, and remove expired server copies according to policy. Never store backups beneath `public/`.

## Mandatory isolated rehearsal

The bundled restore command deliberately refuses production targets. It requires all of the following:

- `ARCHITEX_ALLOW_DISPOSABLE_DB=1`;
- `APP_ENV=test` (the CLI alias `--env=restore_test` maps to test);
- loopback database host;
- a database name matching `architex_task<digits>_<8 hex>_test`;
- `--confirm-database` exactly equal to `DB_NAME`;
- a new or empty private file restore directory.

Example in a local rehearsal environment:

```text
php app/bin/restore.php --env=restore_test --input=dist/release1-test.axbackup --confirm-database=architex_task10_deadbeef_test --file-root=app/storage/tmp/rehearsal-files
```

Before database mutation, restore validates the password, HMAC, manifest, canonical table/file entry bindings, every entry hash/size, and the archived migration checksums against the supplied active migration tree. The legacy disposable path then creates the guarded rehearsal schema; the separately provisioned account-owned path requires an issued capability and an already empty target and never creates or drops databases. Exact table-set comparison occurs after applying migrations. Both paths validate foreign-key relationships and restore/hash-check files before committing imported rows.

On import or file failure, the row transaction rolls back. Migration DDL is not transactional: failed schema changes and private file staging are retained for operator inspection, not automatically deleted and not described as a complete rollback. Preserve the encrypted source archive and recovery evidence. Clean up only independently confirmed task-owned targets after checking that no restore process remains active. Native fixture/reflection tests do not establish authenticated cPanel provisioning or production recovery readiness.

After restore, compare reported counts, authenticate a restored test user, read a tenant-scoped project, and download a restored file. Record the archive backup id, release commit, date, row/file totals, and verifier results. Production recovery is a separately approved incident procedure: restore first to an isolated database, validate it, then arrange the final production cutover with the host/database administrator. Never bypass the disposable guard by editing the restore script.
