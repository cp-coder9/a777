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

1. Dispatch `mode=prepare-key` with no fingerprint only if the exact owned key
   paths are absent. cPanel SSH Management generates RSA4096 **on the host** via
   API 2 `SSH/genkey`; private bytes are never uploaded, retrieved, logged or
   present on the runner. Only `/home/archite4/.ssh/a777-deploy-key` holds the
   private key (0600). GitHub host trust remains independently pinned Ed25519.
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
4. Existing-key prepare reruns require the same public fingerprint, a matching
   native SSH metadata inventory and both exact owned files. Only the public
   file is retrieved; private/public cryptographic pairing is not proven by an
   API metadata inventory. No existing private key is exported to prove pairing.
   Partial/colliding keys refuse rather than overwrite; nothing is deleted.
   Runner workflow concurrency is serialized. Do not automatically clean up a
   key left by an earlier failed upload or generation.

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

## Native SSH Management contract and safe partial completion

The implementation uses cPanel API 2 because the official SSH documentation
states there is no equivalent UAPI function. Official `SSH/genkey` documents
`bits` (positive integer >=1024), required `name`, `pass`, and `type` (`rsa` or
`dsa`, not Ed25519). We send `bits=4096`, `name=a777-deploy-key`, `pass=` (empty),
`type=rsa` in an HTTPS form **POST** to `/json-api/cpanel`, with API 2 routing
fields in the body. Empty passphrases are explicitly allowed in the documented
`warnings` return (added in cPanel 82), although discouraged for general usage.
Here it permits noninteractive outbound Git access using a GitHub read-only
repository deploy key; it does not grant inbound SSH or GitHub write access.
No `authkey`, `importkey`, private `fetchkey`, or private Fileman upload is used.

`SSH/listkeys` is metadata-only; inspect both key types with no `pub` or `types`
filter so partial keys are not hidden. Compare exact `file` and owned `name/key`
against Fileman inventories; unrelated keys are not changed. Public bytes are
retrieved only through documented UAPI `Fileman/get_file_content` for
`a777-deploy-key.pub`. There is no `SSH/fetchpubkey` page in the current official
API 2 catalog (that URL returns 404), so no invented endpoint is used.

Success envelopes contain `cpanelresult.event.result=1`; `genkey` additionally
returns `data=[{result:1, reason:..., warnings:...}]`. Provider reasons/warnings
are never emitted. The public key parser validates the encoded SSH algorithm,
field lengths and canonical RSA mpints, and requires 4096 modulus bits for a
new native key. RSA authentication does not change the independently pinned
Ed25519 GitHub **host** key or strict host verification.

Safe failure reports identify `api_operation` (`SSH/listkeys`, `SSH/genkey`,
`SSH/public_readback`, `SSH/config_readback`) and `native_key_state` (`unknown`,
`possibly_generated`, `generated`, `existing`). An unavailable/refused SSH call
is not automatically evidence of a disabled hosting feature: report the exact
unavailable operation without guessing the provider reason. A genkey lost
response is `possibly_generated`, never automatic retry or cleanup. A generated
pair followed by config failure remains `generated`; only a validated public
recovery artifact may be retained, even on that failed run. The mode remains
failed until permissions/config/known_hosts readbacks finish. Do not register a
recovery artifact as proof that SSH preparation succeeded. The API has no atomic
create-if-absent parameter; serialized workflow plus immediate inventories guards
observed collisions, but cannot fence a concurrent out-of-band account operator.

Official parameter and response sources:
- https://api.docs.cpanel.net/cpanel-api-2/cpanel-api-2-modules-ssh/cpanel-api-2-functions-ssh-genkey
- https://api.docs.cpanel.net/cpanel-api-2/cpanel-api-2-modules-ssh/cpanel-api-2-functions-ssh-listkeys
- https://api.docs.cpanel.net/specifications/cpanel.openapi/manage-files/fileman-get_file_content

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
