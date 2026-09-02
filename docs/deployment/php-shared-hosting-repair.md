# PHP shared-hosting deployment repair

This runbook deploys the verified A777 PHP 8.3 release to `php.architex.co.za` through cPanel HTTPS APIs. It does not require SSH and does not use the restricted FTP account. The deployer confines every remote filesystem operation to `/home/archite4/public_html`.

## Safety contract

- Run `inspect` before `deploy`.
- Keep credentials only in the current process environment. Do not put them in command arguments, release archives, evidence files, or Git.
- TLS certificate verification is mandatory. Use `--ca-file` only for a trusted CA bundle.
- The candidate must prove PHP 8.3, MariaDB connectivity, current migrations, and mail readiness before the first rename.
- A failed live check reverses completed renames in reverse order.
- Successful deployment retains the old public root and runtime under deployment-ID-specific rollback names.
- `recover` is read-only unless `--apply-recovery` is supplied.

## Required environment variables

All commands that connect to cPanel require:

```text
A777_CPANEL_HOST
A777_CPANEL_PORT              optional; defaults to 2083
A777_CPANEL_USER
A777_CPANEL_PASSWORD          interactive-password authentication
A777_CPANEL_SESSION           ephemeral cpsess fallback; use instead of password
A777_CPANEL_SESSION_COOKIE    required with the cpsess fallback
A777_CPANEL_ACCOUNT_HOME      optional when derivable from the document root
```

`deploy` additionally requires:

```text
A777_DB_HOST
A777_DB_PORT                  optional; defaults to 3306
A777_DB_NAME
A777_DB_USER
A777_DB_PASSWORD
A777_SMTP_HOST
A777_SMTP_PORT                optional; defaults to 587
A777_SMTP_ENCRYPTION          optional; defaults to starttls
A777_SMTP_USERNAME
A777_SMTP_PASSWORD
A777_SMTP_FROM_EMAIL
A777_APP_KEY                  recommended; generated for this run if absent
A777_BACKUP_ARCHIVE_PASSWORD  optional
```

Set these variables through a local secret manager or the current PowerShell process without echoing their values.

Prefer a revocable cPanel API credential where the host enables it. A cPanel team user may be denied the API-token feature; in that case, `A777_CPANEL_SESSION` accepts only the current validated `cpsess` identifier and `A777_CPANEL_SESSION_COOKIE` accepts only the matching `cpsession` cookie from an already authenticated browser session. Both are short-lived and must never be saved in a file or report.

## Read-only inspection

```powershell
python tools\repair_php_deployment.py inspect `
  --release dist\architex-release-test-20260902-c6ec61c `
  --target-url https://php.architex.co.za `
  --document-root /home/archite4/public_html/php.architex.co.za
```

Successful JSON has `status: inspected`, the endpoint status codes, whether the exact document root exists, and any retained or stale A777 deployment paths. Authentication failure, a mismatched account home, or an unexpected document root is a hard stop.

## Staged deployment

```powershell
python tools\repair_php_deployment.py deploy `
  --release dist\architex-release-test-20260902-c6ec61c `
  --target-url https://php.architex.co.za `
  --document-root /home/archite4/public_html/php.architex.co.za
```

The command validates the release, uploads and extracts a uniquely named archive, writes the private runtime configuration directly through cPanel, runs the isolated preflight, performs staged renames, and verifies:

```text
/
/api/v1/health
/api/v1/readiness
/api/v1/legal-notices/signup
```

Successful JSON has `status: deployed`, `deployment_id`, `release_commit`, endpoint statuses, `rollback_public`, and `prior_runtime`. Save the deployment ID and retained paths in the release evidence without copying credentials or cookies.

## Recovery

Always inspect the recovery plan first:

```powershell
python tools\repair_php_deployment.py recover `
  --deployment-id <deployment-id> `
  --target-url https://php.architex.co.za `
  --document-root /home/archite4/public_html/php.architex.co.za
```

The dry run returns `status: recovery_planned` and the exact observed active, rollback, prior, and candidate paths. It performs no rename.

Apply only when the plan is unambiguous:

```powershell
python tools\repair_php_deployment.py recover `
  --deployment-id <deployment-id> `
  --target-url https://php.architex.co.za `
  --document-root /home/archite4/public_html/php.architex.co.za `
  --apply-recovery
```

Application is rejected if either retained prior path is missing or either candidate destination already exists. On success, the command verifies that the restored public root and runtime exist and returns `status: recovered`.

## Failure output

Failures emit one compact JSON object on stderr with `status: failed`. Known secret values are redacted. A preflight failure performs no cutover; a post-cutover verification failure attempts automatic reverse-order rollback and reports any rollback failure in the same redacted object.
