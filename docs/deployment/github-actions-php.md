# GitHub Actions PHP deployment

Workflow: [Deploy PHP](https://github.com/cp-coder9/a777/actions/workflows/deploy-php.yml).
It is manual only: no push, pull-request, scheduled deployment or automatic DDL.

## Setup

1. Merge the reviewed workflow onto the default branch so GitHub exposes **Run workflow**. The publishing identity needs permission to write workflow files; arranging GitHub OAuth/workflow permission is a separate operator task.
2. Create the GitHub environment **production**, require an independent reviewer, restrict allowed deployment branches to the approved release branch/default branch, and prevent self-approval where supported.
3. Add the environment secret **A777_CPANEL_API_TOKEN**, using GitHub's secret UI or an encrypted secret API payload. Never paste it into YAML, a command argument, report or artifact. The token must belong to **archite4** on **cp73.domains.co.za:2083** and be permitted to use the required Variables and Fileman APIs. Token creation/secret encryption is a separate operator task. If the hosting provider disables API tokens, this workflow is blocked; do not substitute a password/session or broaden the target silently.
4. Confirm the deployment target is exactly `https://php.architex.co.za`, account home `/home/archite4`, document root `/home/archite4/public_html/php.architex.co.za`. These identities are fixed in the workflow, not dispatch inputs. Production network egress is restricted to the cPanel and site endpoints after checkout, tool setup and artifact download; GitHub runner/control-plane services are handled by harden-runner. Provider integration and this egress restriction still require a real Actions run to validate.

## Manual trigger

Open the workflow link, select **Run workflow**, choose the reviewed branch and leave **mode=inspect** for the first run. Approve the production environment gate. Inspection builds a release and performs read-only remote account/path/HTTP checks. `status=inspected` is not readiness certification: the existing inspector reports endpoint HTTP statuses, not all response semantics.

Only after reviewing inspection, migration readiness and external production prerequisites, run the same workflow with **mode=deploy**. It always inspects first; failed inspection prevents the deploy step. Concurrency is shared across branches and cancellation is disabled, avoiding interruption during cutover. Deployment runs only on the explicit `deploy` choice.

Equivalent manual dispatch (after publishing):

```text
gh workflow run deploy-php.yml --repo cp-coder9/a777 --ref <approved-branch> -f mode=inspect
gh workflow run deploy-php.yml --repo cp-coder9/a777 --ref <approved-branch> -f mode=deploy
```

## Build and evidence

The build job uses PHP 8.3, installs source Composer dependencies from `composer.lock` first, and invokes the actual `app/bin/build-release.php` application bootstrap. ReleaseBuilder separately installs locked production dependencies in the immutable release, requires clean Git provenance, and verifies its manifest. The workflow runs all deployment helper tests and `app/bin/verify-release.php` before uploading the complete release plus manifest, release metadata, source lock and verifier output. No dirty staging override or mock installer is used. Host runtime configuration is excluded from the release; artifacts are uploaded before the production job receives any credential. Production reports/runtime content are not artifact-uploaded.

The existing repair deployer copies the active private `config/runtime.php` content to the candidate, rather than generating replacement APP_KEY/database/storage settings. Its runtime-preservation tests cover exact content copying; provider get/save byte preservation still needs live verification. It stages candidates, checks PHP/database/migrations/mail preflight, switches paths, verifies live build identity/readiness, and reconciles rollback/cleanup failures. Do not confuse file rollback with database rollback.

## Fail-closed boundaries and current blockers

Pending database migrations correctly refuse candidate preflight. **The host migration must be performed first by an external guarded operator**, using verified target identity, a fenced web-write gate, scheduler suppression, in-flight drainage, a recoverable encrypted backup and guarded restore proof. This workflow neither applies DDL nor enables maintenance/quiescence bypasses; no boolean attestation or weakened preflight is acceptable. Review `php-shared-hosting-repair.md` and the guarded host-operation implementation before any separate migration operation.

Provider integration, token privileges, MariaDB migration parity, cron freshness, SMTP and public endpoint readiness remain external gates. Local helper tests and a verified local production release do not prove these. A provider WAF/challenge can return HTML with HTTP 200: that is not cPanel JSON, authenticated access or ready production. Resolve provider/WAF access through the operator; do not follow redirects with credentials, disable TLS verification or retry guessed hosts/accounts. A real Actions inspection/deployment has not been exercised by local setup alone.

The Python client supports optional `CpanelConfig.api_token` appended after existing positional fields and the header `Authorization: cpanel username:token`. `A777_CPANEL_API_TOKEN` is loaded from process environment and participates in redaction. Combining a token with password or either session field is rejected before network access. Existing password/session operation remains available to separately authorized local tooling, not this token-only workflow.
