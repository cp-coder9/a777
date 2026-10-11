# Final independent Material 3 Expressive integration/code review

## Verdict

**APPROVED — code integration/security review scope. Release remains pending live workflow acceptance; not deployed.**

No Critical or Important introduced defect was established in the inspected overhaul. This is not an assertion that all role workflows work, that all menu entries have passed native acceptance, or that production has received this candidate.

## Candidate and scope

- Repository: `E:/a777/.worktrees/material3expressive`; baseline HEAD `c342fecef29c1f280b6e64cafec1ce6951d95519` with uncommitted overhaul.
- Exact public live-source identifier supplied by parent: `6f21a24c6fc7bca06b14d4d48d352d12135a2f9f`. Independent `git diff` found no `app/src` or `app/database/migrations` differences between that identifier and HEAD, and no working-tree differences in those paths.
- Inspected actual tracked diff, untracked frontend assets/helper, tests, documentation inventories and proof/generation tools, not only delegated review summaries. No project/ancestor `AGENTS.md` was found by repository and ancestor discovery.
- Production configuration, `.env`, `.private` and credential files were excluded. No application edits, configuration changes, database writes, commits, GitHub posting or deployment were performed by this review.
- The changed/untracked frontend snapshot comprised **335 files**. Reproducible SHA-256 fingerprint: `c71fac973e6ee07c31c12b87bfdae626e23bb911653535a85ff9667aa8ba82ee`. Construction: sort the union of `git diff --name-only` and `git ls-files --others --exclude-standard app/public app/tests tools/v1-clone`; retain existing files under `app/public/` or `app/views/`; concatenate UTF-8 relative path, NUL and binary SHA-256 of each file, then hash the concatenation. This pins inspected frontend bytes, not a deployment package or a complete receipt-time snapshot. Subsequent source edits require reconsideration.

## Critical

None established.

## Important

None established in the introduced code integration. The acceptance gaps below are release gates, not fabricated application bugs.

## Minor

1. **`app/public/assets/v1-clone/js/modules/document-tools.js:165-168` — pre-mount parser wait lifecycle hardening.** Waiting for `DOMContentLoaded` correctly preserves the strict prepared-frame requirement and rejects a detached/replaced document before reads. A still-connected frame's pending parser wait is not cancellable by controller disposal. This is the previously disclosed nonblocking artificial lifecycle case; normal runtime disposes resolved frame handles. Follow-up: cancellation tied to mount ownership, and explicit disposal/detach/replacement-during-wait regressions. It is not evidence of a production authorization bypass.
2. **`app/tests/Frontend/production-native-workflows.test.cjs:105` — trailing whitespace.** Independent `git diff --check` returned exit 2 for the whitespace-only added line. Remove when the parent next edits this test. No runtime or security consequence; no application/test edits were made by this reviewer.

## Cross-integration checks

- **Load order/cascade:** `app/views/v1-clone.php:71-76` loads the dialog helper before site-tools, runtime after native modules/adapters, shared M3 styles after legacy layers, and role-dashboard CSS last. Family styles remain scoped to native workspaces. Embedded geometry CSS does not replace native typography, palette, logos or business handlers; it excludes hidden input geometry and does not enable controls.
- **Real iframe preparation:** `js/runtime.js:302-320` restricts preparation to the actual selected encoded source, parses inertly rather than matching a literal head-close inside script text, preserves document/doctype serialization and appends an absolute same-origin stylesheet. `:322-340` retains native adaptation, moves the theme after the late native skin and guards stale mount resolution. It introduces no new untrusted HTML feed, sandbox relaxation or CSP bypass. The existing trusted-source scripts remain intentionally executable when the prepared document is mounted; inert parsing is not claimed to sanitize arbitrary user HTML.
- **Document boot:** `modules/document-tools.js:162-171` waits for the actual prepared document's parser rather than merely body children; keeps the strict `__v1DocumentFrame` flag, detached/replaced document check, scoped refresh generation and exact artifact action contracts. BoQ PDF copy now reflects the actual supported server format without claiming certification or a browser-generated PDF.
- **Project ownership:** `modules/projects.js:27-47`, `:69-138` and `:290-322` capture authorized identity/organisation/membership and edit-project scope, recheck after awaits, suppress stale completion and restore only selectors owned by that controller. Old controller disposal does not clear a new controller's active ownership.
- **Inspection ownership:** `modules/site-tools.js:87-111` captures generation/session/organisation/project/memberships, checks every asynchronous draft/confirmation/write/refresh continuation, and never interprets internal inspection drafting as external permission to work. `modules/m3-dialog.js:6-8,25-41` validates source-frame ancestry, aborts invalid invokers and removes frame-load, signal, observer and pagehide resources on settlement. The formerly reported settled-dialog listener leak is fixed in the inspected source, not retained as an open bug.
- **Calculators:** `modules/calc-workspace.js:59-141` enforces authenticated project/read-only/rule-source availability, validates structured and finite numeric inputs, guards cancelled/switched/stale displays and requires advisory output. Actual adapter contracts and exact completed readback remain the authority; input defaults are not represented as actual project evidence. Cancelling a display does not claim to cancel an already-sent server write.
- **Dashboard truth and privileges:** `modules/shell-data.js:8-23,26-45,57-90` separates profile guidance from membership authority, filters recommendations using discovery permission, includes membership changes in the shared scope signature, uses text nodes for record-derived labels and does not infer attention/payment/completion totals. CSS/profile labels do not grant write, review, platform authority or permission to review one's own evidence. Production backend policies were not changed.
- **Public access:** `public-access.js:162-213` uses per-form legal-notice caching, current-form checks before registration POST and after settlement, and guards stale success/error/reset/finally mutations. Unsupported recovery/trusted-device/security promises are disclosed instead of silently represented as implemented. An already-sent registration or sign-in POST is not claimed to be cancellable.
- **SVG/XSS/path/download boundaries:** compiler `build_architex_icons.py:40-105,166-175,258-300` refuses executable/external/nested-viewport SVG, validates canonical identifiers and source containment, preserves approved geometry/fill semantics and validates outputs before publication. Publication is per-file atomic with caught-error rollback, explicitly not cross-file/crash atomic. Independent executable/external-reference signature scan of **300 changed/untracked SVG files** returned no unsafe matches. New record labels/dialog text use escaping or text nodes. Existing selected-run/export metadata and actual-byte checksum download gates remain intact; independent focused tests below verify refusal paths. No credential signatures were found in the inspected changed/untracked text artifacts; this is a scoped signature scan, not an exhaustive secrets guarantee.
- **Proof tooling:** scripts are test-only and fixed to loopback/scratch resources, not production bootstrap tooling. Native runtime provisioning checks `DisposableDatabaseGuard`, candidate class identities and exact owned datadir before database creation/migrations (`material3-runtime-proof.php:23-36`). Demo proof creates a separate synthetic identity/tenant, refuses replay and uses parameterized writes. Its scratch bootstrap dependency is an environment prerequisite, not a portable production entry point. Browser proof keeps session material outside Git, checks identity/organisation/membership/profile before reused-context boot (`material3-browser-menu-proof.cjs:38-51,264-275`), records unexecuted rows as unverified failures and sets nonzero fatal/unverified/drift exits (`:279-287`). Native-menu lens placeholders explicitly remain unverified; they are not all-role acceptance.

## Verification actually observed

### Parent completed release regression

Read the durable receipt `E:/Hermes/home/cache/scratch/material3-parent-release-regression-receipt.json` and actual TAP footer `material3-parent-release-regression.tap`:

- All **20 files** listed in that receipt completed.
- **168 tests; 168 passed; 0 failed; 0 cancelled; 0 skipped; exit 0.**
- Receipt elapsed time **1188.03 seconds**; TAP duration **1187836.8838 ms**.
- This is the current clean regression evidence. Older BoQ assertion, coverage-hash and icon freshness failures are historical and superseded for regression readiness, not current blocking failures.
- No second long full regression was launched by this reviewer.

### Independent bounded checks during this review

- `node --test tools/v1-clone/coverage.test.cjs`: **4 passed, 0 failed/cancelled/skipped**, completed exit 0; current source hashes, exact registry/lens allowlists, actual renderer ordering and documentation anchors verified.
- `node --test app/tests/Frontend/production-toolbox-download.test.cjs`: **5 passed, 0 failed/cancelled/skipped**, completed exit 0; actual-byte integrity, selected-project run binding, supported-PDF metadata refusal, unsupported-format refusal, encoded/chunked responses and denied scope/unrelated paths verified.
- `node tools/v1-clone/material3-browser-menu-proof.cjs --preflight-regression`: completed exit 0; reused-context transport regression reports **2 accepted accounts, 4 rejected mismatches**, and missing session rejected before boot. Explicitly synthetic transport evidence, not native all-menu acceptance.
- Syntax checks: **45 changed/untracked JS/CJS/MJS/PHP code files**, using `node --check` or `php -l`, zero errors.
- `git diff --check`: only the Minor whitespace finding above.

## Exact outstanding acceptance gates

1. **Native all-menu/role workflow receipt:** the parent's current 517-entry native-menu run and fresh-context preflight quality review were still pending in delegated context. Do not close them from the 168-test regression or synthetic preflight regression. Require the completed report, explicit failure/unsupported/unverified classification, correct authenticated identity/membership/profile/project scope, unchanged served/source bytes, screenshots/console evidence and exact supported write/readback/reload persistence receipts. No result is invented here.
2. **Separate demo lens boundary:** actual `demo-lens-acceptance.json` currently records **22 lenses, 185 assignments, native service/HTTP/UI PASS**. Parent supplied corroboration of 22 exact registry/catalog/database assignment sets. This is separate-tenant demo selector/portfolio evidence, not native production-mode all-menu acceptance or complete professional workflows; this reviewer did not replay its browser journeys.
3. **Release artifact/deployment/public verification:** freeze/package the reviewed source only after the remaining acceptance gates close; reconcile evidence and fingerprints if anything changes. Deployment and independent public build/assets, readiness, login and supported workflow verification remain undone. Existing production health or an unchanged backend cannot establish delivery of these frontend bytes.
4. **Documentation status reconciliation:** `verification-report.md` is being updated concurrently and preserves historical failing receipts. Use its latest clean 168-test result, this final review and subsequent real acceptance receipts to supersede stale pending-work text; do not delete/relabel the historical failures or convert inventory/fixture evidence into live PASS claims.

**Final decision: APPROVED for the inspected code integration. Not overall complete; release pending live workflows and deployment verification.**
