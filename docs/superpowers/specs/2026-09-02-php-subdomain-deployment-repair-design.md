# PHP Subdomain Deployment Repair Design

## Objective

Provide a repeatable, fail-closed local deployment command that activates the verified A777 PHP 8.3 release on `php.architex.co.za` through cPanel without SSH. The command must bypass the existing FTPS account's legacy chroot, keep the PHP runtime and credentials outside the document root, validate MariaDB and SMTP connectivity before cutover, and restore the previous site automatically when live validation fails.

## Confirmed constraints

- The project source and deployment tooling remain under `E:\a777`.
- The target is shared hosting with PHP 8.3, MariaDB, cPanel, and no SSH or terminal access on the server.
- The cPanel document root is `/home/archite4/public_html/php.architex.co.za`.
- The available FTPS account is chrooted beneath a legacy site directory and therefore cannot safely perform the account-level directory swap.
- The release is staged: the previous document root must be preserved until post-cutover checks pass.
- Credentials are process environment inputs. They must never be committed, echoed, added to an archive, or placed in the public document root.
- The release artifact must pass `app/bin/verify-release.php` before any remote mutation.

## Considered approaches

### 1. cPanel API deployment orchestrator (selected)

Extend the existing deployment tooling with a cPanel client that uploads, extracts, writes, renames, lists, and removes files relative to the authenticated account home. This directly addresses the observed FTPS chroot mismatch and supports an atomic staged cutover.

### 2. Change the FTPS account home

Reconfigure the hosting account so the current FTPS credentials can reach account-level `public_html`, then reuse `tools/deploy_php_ftps.py`. This changes server access boundaries, may expose unrelated hosted sites, and depends on hosting-provider permissions that are not part of an application deployment.

### 3. Temporary public PHP installer

Upload an installer into the subdomain document root and let PHP extract and rearrange the release. This requires the broken vhost to execute the installer before it can repair itself and exposes a high-value temporary endpoint. It is not selected.

## Architecture

The repair consists of three focused Python modules:

1. `tools/cpanel_client.py` implements authenticated HTTPS calls to cPanel UAPI and the narrowly required cPanel API 2 `Fileman::fileop` operation. API 2 is used only because cPanel documents no UAPI equivalent for archive extraction and file renames.
2. `tools/repair_php_deployment.py` owns deployment state, release packaging, preflight, cutover, rollback, and cleanup. It imports packaging and runtime-config helpers from `tools/deploy_php_ftps.py` rather than duplicating them.
3. `tools/tests/test_repair_php_deployment.py` uses a fake cPanel transport and fake HTTP verifier to prove ordering, path containment, redaction, cutover, and rollback without touching the live host.

The cPanel client exposes semantic operations rather than raw endpoint construction to the orchestrator: `account_home()`, `list_files()`, `upload_file()`, `save_file()`, `extract_archive()`, `rename()`, and `remove()`. Every mutating operation accepts an absolute server path, normalizes it, and rejects targets outside `/home/archite4/public_html`.

## Configuration contract

The command reads these cPanel values from environment variables:

- `A777_CPANEL_HOST`
- `A777_CPANEL_PORT` (default `2083`)
- `A777_CPANEL_USER`
- `A777_CPANEL_PASSWORD`
- `A777_CPANEL_ACCOUNT_HOME` (expected `/home/archite4`)

It reuses the existing private runtime variables for MariaDB and SMTP. Command-line arguments provide the verified release directory, target URL, and target document root. Passwords and generated application keys are represented as redacted values in exceptions and logs.

TLS certificate verification is enabled by default. A `--ca-file` option permits an explicit certificate-authority bundle; there is no insecure TLS switch.

## Deployment flow

1. Validate local release structure and run the release verifier.
2. Authenticate to cPanel and confirm the reported account home is exactly `/home/archite4`.
3. Confirm the target document root is `/home/archite4/public_html/php.architex.co.za` and all candidate, active, storage, and rollback paths remain under `/home/archite4/public_html`.
4. Build a transport archive containing a private runtime candidate and a public candidate whose `index.php` points only to the eventual active runtime name.
5. Upload the archive through cPanel into `/home/archite4/public_html` and extract it there.
6. Write `config/runtime.php` directly into the private runtime candidate using cPanel's file API. Create the private storage directory if absent.
7. Save a random-name preflight probe into the current subdomain document root. The probe loads the candidate runtime and returns only boolean PHP, database, migration, and mail readiness fields.
8. Request the probe over HTTPS. A non-JSON response, unexpected redirect, non-200 response, failed database connection, incomplete migration set, unavailable mail runtime, or PHP version below 8.3 aborts the deployment before cutover.
9. Rename the existing active runtime to a timestamped prior runtime, rename the runtime candidate to active, rename the existing document root to a timestamped rollback root, and rename the public candidate to the document-root name.
10. Validate `/`, `/api/v1/health`, `/api/v1/readiness`, and `/api/v1/legal-notices/signup` over HTTPS. Validate that the landing response identifies the A777 PHP release and that readiness reports database, migrations, jobs, and mail ready.
11. On success, retain the rollback root and prior runtime for manual recovery and remove only the transport archive and temporary probe.
12. On failure after any rename, reverse completed renames in strict reverse order, verify the restored root responds, remove temporary probes and archives, and exit non-zero.

## Idempotency and recovery

Every run uses a UTC timestamp plus random suffix for candidate and rollback names. The script refuses to reuse an existing candidate name. It records completed transitions in memory and only reverses transitions that occurred during the current invocation.

If the process is interrupted after a rename, a `--recover <deployment-id>` mode inspects the exact candidate, active, prior, target, and rollback paths. It prints the proposed recovery action first and requires `--apply-recovery` to mutate remote state. Recovery never discovers targets through wildcards.

## Error handling and security

- Authentication failures stop before uploads.
- cPanel responses must be JSON and report success at both HTTP and API-result levels.
- Every remote path is canonicalized and checked against the allowed account subtree.
- The script never follows redirects for preflight or health checks to another host.
- Temporary public probes use cryptographically random names and are removed in `finally` blocks.
- Runtime configuration is written directly to the private candidate and is never included in the transport archive.
- Console output contains deployment identifiers, paths, byte counts, status codes, and readiness booleans only.
- Existing directories are renamed rather than deleted. Cleanup never recursively deletes a directory containing an unrecognized deployment identifier.

## Verification

Automated tests cover:

- cPanel authentication and API-result validation;
- path containment and traversal rejection;
- transport-archive layout;
- preflight-before-cutover ordering;
- successful atomic cutover;
- rollback after each individual rename and after each live health failure;
- secret redaction from output and exceptions;
- cleanup of probes and archives;
- recovery dry-run and explicit application.

The live acceptance gate requires:

- PHP runtime reports version 8.3 or newer;
- MariaDB connection succeeds and all release migrations are present;
- SMTP runtime configuration is ready;
- landing page loads the A777 PHP UI;
- all four public verification endpoints return the expected JSON contract;
- a fresh browser session can open registration and sign-in without a refresh-request loop.

## Operational output

Successful execution prints one JSON summary containing the deployment ID, release commit, active runtime path, public root, retained rollback paths, and verified endpoint statuses. A failed execution prints one redacted JSON error with the failed phase and whether rollback was completed.

## External reference

cPanel's official `Fileman::fileop` documentation defines the required extract and rename operations and states that no UAPI equivalent exists: <https://api.docs.cpanel.net/cpanel-api-2/cpanel-api-2-modules-fileman/cpanel-api-2-functions-fileman-fileop>.
