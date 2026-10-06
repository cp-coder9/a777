# Native cPanel preflight runner

Dispatch `.github/workflows/cpanel-git.yml` on **master**, mode `native-preflight`.
The runner binds `--expected-commit` to `github.sha` and pins the existing deploy
key to `SHA256:T+6DmXaw3OeXcygLPc9tkeSt70j8dvHnCDYOV7bCras`.
It requires the existing exact managed `/home/archite4/repositories/a777`
repository, pulls master through the existing verified SSH alias, and refuses
unless cPanel's completed update identifies the exact workflow commit.

## Required hook contract (parent-owned change before dispatch)

`tools/cpanel_git_deploy.php` must accept an omitted `artifactTreeSha256`
**only for effective preflight mode**. Validate `expectedCommit` independently;
keep the 64-hex artifact digest mandatory for stage-only/deploy. Do not invent a
digest, upload an artifact, or treat this as an artifact verification receipt.
The current hook's combined `plan_digest_invalid` check still requires that
field and must be adjusted before publishing this runner. Establish effective
invocation mode before conditional artifact validation, so `--preflight` cannot
lead into staging. This runner deliberately supplies mode `preflight` only.

The reviewed hook is CLI-only, checks Linux/PHP/account/repository and private
ownership, and persists `preflight-<commit>-<16hex>.json` under `.a777-deploy`.
No public migration endpoint or host worker is installed by this runner.

## Private plan policy

Create `/home/archite4/.a777-deploy` only if absent with mode 0700; verify the
returned inventory's directory type and permissions. Preserve existing modes:
unsafe permissions and links refuse instead of being silently repaired.
Create only `operator-plan.json`, chmod 0600 using existing Fileman API 2
`fileop`, then read back permissions and exact parsed target fields.
The native helper independently verifies UID ownership. Existing non-preflight
plans and different-commit plans refuse rather than replacing operator intent.
An operator must explicitly review/remove a stale plan before a later commit's
preflight; the runner never deletes it or an existing lock/candidate/recovery file.

## Official API contract

Verified against the official downloadable cPanel OpenAPI specification:
https://api.docs.cpanel.net/_bundle/specifications/cpanel.openapi.json?download

* `VersionControl/update`: `repository_root`, `branch=master` (existing helper).
* `VersionControlDeployment/create`: **only** `repository_root`.
  Data contains `deploy_id`, `task_id`, `repository_root`, `timestamps.queued`.
* `VersionControlDeployment/retrieve`: **no parameters**. Select exact root,
  deploy ID and Task Queue task ID. Poll documented `timestamps.succeeded`,
  `timestamps.failed`, `timestamps.canceled`; queued/active is not completion.
  Read only the selected record's `repository_state.identifier` as the
  deployment commit. No invented TaskQueue endpoint or status enum is used.
* Fileman `list_files` and `get_file_content` use the existing client contract.
  Retrieve only new exact commit-prefixed receipts, requiring 0600 regular files
  and matching format/mode/commit/plan SHA. No config, private key, database user
  inventory or provider response bodies are emitted. Provider task logs are not
  needed and are not retrieved by this mode.

The output artifact remains `cpanel-git-inspection`, retained even on failure.
It contains `deployment_last_commit`, actual task terminal/pending state, native
preflight status, report IDs, typed capability booleans/counts and a finite gate
allowlist. Receipt presence alone never proves queued tasks completed. Failed
native tasks may still provide safe capability evidence; absent receipts never
mean successful inspection. `preflight_complete` requires a succeeded task,
matching deployment commit and a fresh inspected-not-authorized receipt. It is
**not live deployment or DDL authorization**. No commit/push, remote secret,
public endpoint, arc-1 or active-runtime mutation occurs in runner plumbing.

## Tests

`python -m unittest discover -s tools/tests -p 'test_cpanel_git*.py'`

Transport fixtures exercise terminal versus queued states, safe evidence on
failure, missing/stale receipts, commit/pin refusals, private plan permissions,
operator-plan preservation and workflow SHA/artifact wiring. Fixtures are not
live cPanel proof. Parent must publish the hook contract adjustment and runner,
then dispatch and inspect the real retained artifact before reporting native
preflight completed on the host.
