# Bounded runtime-config permission hardening

The default is metadata-only; only the explicit workflow boolean `secure_runtime_config_permissions: true` adds `--secure-runtime-config-permissions` for native-preflight. This authorization is not production deployment or DDL authorization.

The PHP operator already holds its exclusive deployment flock. Repair accepts only a canonical owned regular single-link file, with a canonical immediately enclosing owner-0700 directory and non-group/world-writable owned/root-owned ancestry. An opened read handle supplies inode/content identity. Before pathname chmod, identity is rechecked; afterward pathname and open-handle inode/device, mode 0600, link count, unchanged byte length and SHA-256 are checked along with the unchanged owner-0700 parent. Failure does not produce a successful permission receipt.

PHP has no portable fchmod API. This implementation deliberately uses pathname chmod, not invented descriptor support. The security boundary excludes root and malicious processes sharing the privileged cPanel UID: those can replace names despite owner-0700 and ignore the advisory operator lock, including a transient swap-and-restore attack. Pre/post checks detect persistent replacement but do not eliminate that same-UID race or undo side effects. Do not claim FD-anchored race freedom. Legitimate account operators must honor the deployment lock. If hostile same-UID isolation is required, this remains a blocker needing a separately verified descriptor-capable helper.

Permission receipts reference one exact known `runtime-permission-<16 hex>.json` private intent. Inventory acceptance, rollover and new report acceptance read that exact intent and compare commit, plan SHA, exact target path, device/inode/owner, before mode, unchanged bytes/hash and reported after-0600 proof. Boolean reports without this reference fail. These are authenticated private operator artifacts, not cryptographic protection against a malicious account owner. Python never reads active runtime config values to verify them; it compares the native metadata/hash observations in the intent and receipt. No real config, production database or hosting operation was performed in this review.

## Executed local evidence

- Python tools discovery: 201 tests passed (including 9 permission tests).
- PHP deploy fixture: 18 assertions passed.
- PHP read-only API fixture: 37 assertions passed.
- Writer fence PHP subprocess fixture passed.
- Deploy helper and Linux permission fixture PHP syntax lint passed; git diff --check passed.

A standalone Linux PHP fixture exercises unauthorized default, authorized byte-identical 0600 repair, hardlink refusal and symlink refusal under HOME, with an exclusive operator lock and cleanup. The Actions job runs it without hosting credentials before executing the selected mode. It is not yet execution evidence: local Windows cannot run Linux POSIX PHP and WSL failed with nested virtualization unsupported / mount access denied. Run the Actions fixture before claiming Linux chmod execution. Host validation of the exact config-parent owner/mode is still required; no parent directory is repaired automatically.
