# Existing public source: bounded recovery checkpoint

Default source policy still refuses any storage under the whole `/home/archite4/public_html`, not merely the application's document root. Preflight `sourceUnderPublicHtml` and `storageOutsidePublic` are observations, never authorization.

After independent parent review, the explicit workflow boolean `allow_existing_public_source_for_rehearsal` or CLI `--allow-existing-public-source-for-rehearsal` can set the private plan's `allowExistingPublicSourceForRehearsal: true`. This requires `restore-rehearsal`, the exact runtime configuration SHA-256, and the unchanged current runtime's exact `FILE_STORAGE_PATH`. It cannot select another source. The class's optional final boolean defaults to false; runtime pin and config equality precede its use.

This checkpoint preserves existing data in a private encrypted backup before separately fixing its placement. It does not create exposure or silently loosen production readiness. Existing owner directories remain limited to 0700/0750/0755, fresh safe ancestry, no symlinks, owner-0600 single-link regular files, and before/after opened-handle identity plus SHA checks. No source chmod, move, configuration change, HTTP exposure or source DDL is performed.

Recovery output remains strictly owner-0700/0600, outside the whole public tree and outside source, without exception. Successful evidence establishes only archive/isolated-target data recovery. Public-source receipts report `sourceStorageUnderPublicHtml=true` and `sourceStoragePrivacyUnproven=true`; `promotionAuthorized`, `migrationAuthorized`, and `liveDdlAuthorized` remain false.

Future activation must independently relocate or prove private storage. Source privacy, writer exclusion, migration and deployment gates remain unsolved. Initial retry retains the existing exact failed-receipt/zero-target-effects and provider inventory guard; an earlier `source_public_storage_refused` receipt is compatible, not permission to bypass the guard.

Linux source-storage fixtures run before workflow hosting credentials. Controlled scope reflection fixtures are synthetic and never create actual archite4 directories. Local Windows chmod does not prove POSIX privacy; Linux fixture completion and independent review are required before a host retry.
