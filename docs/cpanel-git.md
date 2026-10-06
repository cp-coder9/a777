# cPanel Git discovery (read-only)

Run the manual **cPanel Git discovery** workflow with `mode=inspect`. It checks out
sources with persisted GitHub credentials disabled, runs Python 3.12 tests, and uses
production's existing encrypted `A777_CPANEL_API_TOKEN` secret against
`cp73.domains.co.za:2083`, user `archite4`. It verifies the provider-reported home
is exactly `/home/archite4` before querying Git metadata. No hosting calls are made
by unit tests. Local WAF rejection does not establish account Git availability;
use the GitHub runner's retained `cpanel-git-inspection` report.

The report allows only repository name/root/branch/deployable and HEAD/deployed
SHA-1; deployment task output is reduced to root/branch/deployed SHA. No source or
clone URLs, author/messages, private keys, task/log details or raw provider errors
are emitted. A successful empty inventory means retrieve is available, not that
clone/SSH/deploy is permitted. Failures remain `failed_or_unavailable`, not a
claim that the hosting package lacks Git. The CLI fails if Git retrieval is not
successful, but the artifact step runs even on failure. Unknown/outside roots are
observable in discovery, not mutation authority. Only explicitly selected preparation
and clone modes can mutate the dedicated paths documented below.

## Official UAPI contract for a separately authorized follow-on

HTTPS `/execute/<module>/<function>` using the existing token client:

- `VersionControl/retrieve`: optional `fields`, comma-separated. Discovery requests
  `name,repository_root,branch,deployable,last_update,last_deployment`.
- `VersionControl/create`: required `name`, `repository_root`, `type=git`.
  Optional `source_repository` is a JSON-encoded object with `remote_name` and
  `url` (e.g. origin and a credential-free SSH GitHub clone URL). Omitting it makes
  an empty repository. Cloning is asynchronous; a create response is not proof
  the clone finished.
- `VersionControl/update`: required `repository_root`; optional `branch`, `name`,
  JSON-encoded `source_repository` (e.g. `{"remote_name":"origin"}`). Specifying
  `branch` pulls remote changes. Type/root/source URL cannot be changed.
- `VersionControlDeployment/create`: required `repository_root` only. Deployment
  requires a checked-in valid top-level `.cpanel.yml`, at least one branch and a
  clean worktree. Documentation describes `--ff-only` pulls.
- `VersionControlDeployment/retrieve`: **no query parameters documented**;
  returns a list of task objects with repository state/timestamps. Do not invent
  a root or deploy_id filter. Read and correlate locally for future verification.

Official references (current specification; hosting version may differ):
- https://api.docs.cpanel.net/specifications/cpanel.openapi/repository-management/versioncontrol-retrieve.md
- https://api.docs.cpanel.net/specifications/cpanel.openapi/repository-management/versioncontrol-create.md
- https://api.docs.cpanel.net/specifications/cpanel.openapi/repository-management/versioncontrol-update.md
- https://api.docs.cpanel.net/specifications/cpanel.openapi/deployment-settings/versioncontroldeployment-create.md
- https://api.docs.cpanel.net/specifications/cpanel.openapi/deployment-settings/versioncontroldeployment-retrieve.md

## Authorized preparation and clone modes (never runtime deployment)

The workflow now accepts `prepare-key` and `clone` as explicit mutation authorizations.
`inspect` remains strictly read-only. Do not touch existing arc-1 at
`/home/archite4/public_html/architex.co.za/ai/repo` (observed branch NULL,
deployable 0). Discovery run 37498946419 established retrieve availability.

1. Dispatch `mode=prepare-key` with no fingerprint for first use. Dedicated
   Ed25519 private material exists only in a temporary 0700 runner directory and
   `/home/archite4/.ssh/a777-deploy-key` (0600), never Git/logs/artifacts.
2. Download **a777-github-read-only-deploy-key**, containing only
   `a777-deploy-key.pub`; register it as a **read-only** deploy key on private
   `cp-coder9/a777` (`read_only=true`; never enable write access). Retain its
   SHA256 fingerprint from **cpanel-git-inspection**.
3. Dispatch `mode=clone`, `expected_key_fingerprint=SHA256:...`. The new cPanel
   repository is `a777-production`, root `/home/archite4/repositories/a777`,
   source `git@a777-github:cp-coder9/a777.git`, remote `origin`. After asynchronous
   clone completion, update specifies `branch=master` to pull. Only read-back
   branch master plus actual HEAD SHA and no pending tasks yields
   `cloned_and_pulled`; `queued_or_incomplete` is NOT deployment or clone proof.
4. Existing-key prepare reruns require the same fingerprint and derive the public
   key from the existing private key to verify ownership. Partial/colliding keys
   refuse rather than overwrite. Runner workflow concurrency is serialized.

Local CLI equivalents (same existing cPanel token environment):
```
python tools/cpanel_git.py inspect --report report.json
python tools/cpanel_git.py prepare-key --public-key a777-deploy-key.pub --report report.json
python tools/cpanel_git.py clone --expected-key-fingerprint 'SHA256:...' --report report.json
```

SSH config/known_hosts contents are preserved as exact ASCII prefixes; non-ASCII,
symlink, alias, complex Include/Match or earlier wildcard-policy collisions refuse.
No StrictHostKeyChecking=no or ssh-keyscan trust is used. GitHub `/meta` over
verified HTTPS supplies the Ed25519 host key, checked against the official
`SHA256:+DiY3wvvV6TuJJhbpZisF/zLDA0zPMSvHdkr4UvCOqU` fingerprint.
The appended owned alias sets HostKeyAlias github.com and strict verification.
Fileman API 2 `fileop` performs chmod with account-relative `sourcefiles`,
`op=chmod`, `metadata=0600/0700`, `doubledecode=0`; list_files `nicemode`
is read back (not decimal stat `mode`). UAPI file reads explicitly disable HTML
encoding updates. Owned queue logs are summarized as allowlisted error categories;
raw task logs/provider errors never appear in reports.

References:
- https://api.docs.cpanel.net/cpanel-api-2/cpanel-api-2-modules-fileman/cpanel-api-2-functions-fileman-fileop
- https://api.docs.cpanel.net/specifications/cpanel.openapi/manage-files/fileman-get_file_content
- https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/githubs-ssh-key-fingerprints

## Next deployment hook remains separately gated

No `.cpanel.yml` deployment hook or VersionControlDeployment/create is added.
A future hook must build the locked production artifact from composer.lock with
production-only dependencies, verify the immutable release manifest and publish
through the existing guarded transport, preserving private runtime configuration
byte-for-byte and migration state. It must not run unguarded migrate/DDL.
The known **8 pending migrations remain pending**; preserve the existing migration
inspector. Backups, target identity, write fencing and recovery must be proven
before any separately authorized schema deployment. Existing deploy-php.yml is
unchanged. This worker performs no remote preparation, GitHub registration,
commit, push or workflow dispatch; the parent publishes and runs these modes.
