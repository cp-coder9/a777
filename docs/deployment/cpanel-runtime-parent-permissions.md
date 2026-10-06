# Explicit private config permission repair

The secureRuntimeConfigPermissions plan flag authorizes only the installed active
config/runtime.php (0600) and its exact config parent (0700). No public root or
other ancestor is chmodded. Without the flag, metadata inspection makes no changes.
An owner config parent such as 0755/0750 may be reduced to 0700 only when canonical,
non-symlink, without group/world write or special mode bits. Every ancestor through
/ must be a canonical directory, owned by root or the effective UID, without
group/world write. The immediate parent must belong to the effective UID.

Before mutation the receipt exposes runtimeConfigParentModeBefore,
runtimeConfigParentOwnerMatches, runtimeConfigCanonical and runtimeConfigAncestorSafe.
Refusals are constant and distinguish canonical, ancestor_path, ancestor_write,
ancestor_owner, parent_owner and parent_mode subgates (runtime_permission_*_refused).
The old ancestry gate combined these conditions plus an exact 0700 parent check;
a 0755 owner parent could therefore explain the old refusal, but the previous host
report did not prove which condition failed. The next host run must use the new
subgate, not assume that explanation.

The private intent journals commit/plan SHA, file identity/content SHA and parent
path/device/inode/UID/before mode/after 0700/repair intent before any chmod. Both
paths are checked again before mutation. Parent readback requires the original
inode and owner. File readback requires original inode/owner/size, 0600, and unchanged
SHA through both the held stream and pathname. Attempt flags for both paths feed
liveFilesTouched even on failure; partial repairs cannot authorize plan rollover.
Complete receipts must cross-bind the exact corresponding intent including all
parent fields. Existing 0700 parents remain valid file-only repairs.

This is bounded hardening under the existing PHP operator lock and an owned parent,
with non-hostile same-UID processes assumed. PHP pathname chmod and readback do not
provide race-proof descriptor-relative chmod against malicious same-UID attackers.
No fictitious alternate FD API is claimed. API token one-time cleanup remains
unchanged; databaseDdlTouched remains false and writer exclusion/promotion remains
unimplemented by this patch.

Validation: python -m unittest discover -s tools/tests -p 'test_*.py'. On an actual
Linux POSIX PHP host with a safe owner HOME: php tools/tests/cpanel_runtime_permissions_linux.php.
The synthetic fixture covers owner parent 0755 -> 0700 plus file 0644 -> 0600,
unchanged bytes, unsafe writable-parent refusal without chmod, hardlink/symlink
refusal, and a real directory inode swap triggered at test-only journal close.
The stream wrapper is fixture-only; production uses ordinary local PHP filesystem
operations. Windows lint/unit tests do not prove Linux mode behavior.
