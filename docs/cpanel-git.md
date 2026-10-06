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
observable in discovery, not mutation authority. There are no mutation commands.

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

## Optional future setup plan — not executed or authorized here

Use a new private `/home/archite4/repositories/a777` checkout, never turn the live
document root into a repository. Confirm the GitHub repository is **PRIVATE** and
provision a **read-only deploy key** for the cPanel account; establish SSH host
trust without putting the private key or credentials in Git, workflow output or
artifacts. Inspect absence/ownership/containment before an explicitly authorized
clone. The helper's exact canonical root check is a lexical precondition, not
symlink/remote-filesystem verification or a mutation implementation.

Design and verify a release-root `/.cpanel.yml` (top-level `.cpanel.yml`, not the
server filesystem root) against the verified immutable release builder/manifest.
Keep production runtime credentials outside Git and preserve the existing private
configuration. Review deployment tasks, target containment, backups, quiescence,
and rollback separately before enabling any deployment. No `.cpanel.yml` is added
by this discovery change. Existing `deploy-php.yml` stays unchanged.

The existing remote **8 pending migrations remain pending**. Git clone/pull does
not solve the database state, run migrations, establish DDL safety or authorize
production schema changes. Retain the separate migration inspection and controlled
migration/rollback process.
