# Failed restore rehearsal diagnostics

A failed snapshot/rehearsal cannot count as the production backup review, an isolated restore proof, a writer fence, or authorization for source DDL or promotion.

The diagnostic component preserves existing account, canonical-path, owner, 0700/0600, committed-class and runtime-pin guards. It never repairs repository permissions or changes the Git command. A repository-stage `private_directory_required` together with observed `repositoryMode=0755`, `repositoryOwner=account` and `repositoryCanonical=canonical` is evidence of a mode guard rejection. No such host evidence has yet been collected locally.

Receipts expose only fixed stage/reason/function constants, the component's fixed basename and positive throw line, metadata modes and account/other ownership classification. Foreign exceptions are `unclassified`; their messages, paths, SQL, credentials, arguments and source text are never emitted. `gitCommandAvailability` observes the existing `git --version` command; `fixedGitBinaryAvailability` separately observes the fixed cPanel binary file/executable metadata without changing execution.

Creation and privilege grant are encapsulated together inside `provisionManaged`; a failure inside it is conservatively reported as `create`, with target DDL marked touched. The subsequent `grant` stage identifies post-provision connection/setup, not proof of the provider's internal failing operation.

A retained rehearsal plan remains non-replayable by default. Any separately implemented explicit `--retry-initial-rehearsal` / workflow `retry_initial_rehearsal` authorization must require a different commit and bound prior failed receipt with source DDL false, target DDL false, isolated rehearsal false, and no recovery database created. Missing proof must refuse. Preserve original intent bytes in a private 0600 archival record and verify read-back; never delete unknown recovery directories, journals or passwords. Default workflow authorization must be false. Local diagnostic tests do not authorize that retry or establish host root cause.
