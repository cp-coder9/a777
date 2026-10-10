# Material 3 Expressive — consolidated verification report

## Status and evidence boundary

**Incomplete; not deployed. No complete save/readback role workflow is closed as a live PASS.** The completed tests below establish their individual boundaries, not release readiness, production persistence or complete all-menu acceptance.

### Latest bounded native acceptance

The parent independently parsed `browser-proof-2026-10-10T22-06-27-935Z/progress.jsonl` under the isolated runtime workspace: **84 unique profile/membership checks, 84 PASS, zero FAIL/UNSUPPORTED**. Supervisor receipt `supervisor-20261010T220600Z/lifecycle.json` records exit 0, no collector/fatal error and unchanged candidate. This is real native HTTP authentication using disposable synthetic sessions, with no API mocks. It verifies 21 profile presentations across four memberships, session identity and Command Centre rendering; it does not establish every role's business writes, reload/readback, professional authority, or normal production login.

The subsequent core batch is running and has no final receipt yet. Its actual progress manifest declares **433 expected checks**, not the previously described 417 complementary partition: `--batch core` includes all non-profile checks, including the 16 legacy-tool checks. Do not double-count those checks if a targeted tail batch is later run. Only completed, source-compatible manifests can be reconciled; in-progress totals are not release acceptance.

Candidate: `E:/a777/.worktrees/material3expressive`, branch `Material3expressive`. This report consolidates existing parent receipts and their TAP logs without rerunning suites. Receipts are historical observations of the candidate at their recorded completion times; they do not bind every subsequent uncommitted edit to a final reviewed artifact. `execution-handoff.md` retains earlier evidence and unresolved work; newer completed receipts supersede only the corresponding earlier test-result claims.

## Completed parent verification

Each row is **parent-verified**, with `completed: true` in the durable JSON receipt and matching completed TAP totals. All rows have zero cancelled and zero skipped tests. Receipt and TAP basenames below live under `E:/Hermes/home/cache/scratch/`.

| Suite / receipt stem | Test files or scope | Tests | Pass | Fail | Exit | Finished (UTC) | Evidence class |
|---|---|---:|---:|---:|---:|---|---|
| `material3-parent-foundation` | `material3-expressive-brand.test.cjs`, `material3-expressive-visual.test.cjs` | 14 | 14 | 0 | 0 | 2026-10-09T13:00:05.825558+00:00 | Browser fixture / computed styling and shell interaction; not live role acceptance |
| `material3-parent-role-dashboards` | `material3-expressive-role-dashboards.test.cjs` | 6 | 6 | 0 | 0 | 2026-10-09T13:25:03.001142+00:00 | Synthetic transport/model and real-shell fixture |
| `material3-parent-projects-final` | `material3-expressive-project-workspaces.test.cjs` | 13 | 13 | 0 | 0 | 2026-10-09T13:05:01.707289+00:00 | Synthetic browser/transport fixture |
| `material3-parent-calc-tech` | `material3-expressive-calculator-contracts.test.cjs`, `material3-expressive-technical-workspaces.test.cjs`, `material3-expressive-runtime-host.test.cjs` | 23 | 23 | 0 | 0 | 2026-10-09T13:39:02.888870+00:00 | Actual-adapter synthetic transport; browser fixtures and migration-receipt gate contract |
| `material3-parent-commercial` | `material3-expressive-commercial-workspaces.test.cjs` | 12 | 12 | 0 | 0 | 2026-10-09T13:39:59.308705+00:00 | Synthetic browser/native-renderer fixture |
| `material3-parent-embedded` | `material3-expressive-embedded-workspaces.test.cjs` | 7 | 7 | 0 | 0 | 2026-10-09T13:43:48.254495+00:00 | Actual embedded source documents in browser fixtures; not live persisted workflows |
| `material3-parent-production-legacy` | Eight files listed below | 35 | 33 | 2 | 1 | 2026-10-09T13:38:47.742681+00:00 | Legacy synthetic browser/adapter contracts plus isolated-renderer/source coverage |
| `material3-parent-tablet-gap-final` | `material3-expressive-brand.test.cjs`, `material3-expressive-visual.test.cjs` | 23 | 23 | 0 | 0 | 2026-10-09T13:53:48+00:00 | Browser fixture: tablet role-lens visibility/hit/Tab, 1600px constrained datum grid, wide/mobile regression |
| `material3-parent-bom-preparation-green` | BoQ/document-frame preparation integration plus affected suites | 34 | 34 | 0 | 0 | 2026-10-09T14:19:31+00:00 | Isolated-host-driven fix verification (the live BoQ mount defect below) |
| `material3-parent-bom-export-contract` | BoQ advisory export contract | 5 | 5 | 0 | 0 | 2026-10-09T14:24:02+00:00 | Adapter contract regression after the preparation fix |
| `material3-parent-role-dashboards-scope-red` | `material3-expressive-role-dashboards.test.cjs` | 7 | 6 | 1 | 1 | 2026-10-09T14:29:27+00:00 | Intentional RED reproducing the membership-scope cache defect |
| `material3-parent-role-dashboards-scope-green` | `material3-expressive-role-dashboards.test.cjs` | 7 | 7 | 0 | 0 | 2026-10-09T14:32:30+00:00 | GREEN after the shared scope-key fix |
| `material3-parent-embedded-determinism` | `material3-expressive-embedded-workspaces.test.cjs` | 9 | 9 | 0 | 0 | 2026-10-09T14:32:25+00:00 | Determinism fix for the order-dependent legacy case |
| `material3-parent-role-dashboards-scope` | Command Centre plus related suites | 119 | 119 | 0 | 0 | 2026-10-09T14:52:33+00:00 | Wider combined fixture run after the scope fix |
| `material3-parent-release-regression` | All 20 requested frontend/legacy/generator/source-coverage files listed in the receipt | 168 | 168 | 0 | 0 | 2026-10-09T18:17:02.929983+00:00 | Complete parent serial release regression; 1188.03s, zero cancelled/skipped |

For each stem, the exact evidence files are `<stem>-receipt.json` and `<stem>.tap`. These are grouped process totals, not invented per-file totals. The production/legacy group comprises:

- `app/tests/Frontend/production-role-navigation.test.cjs`
- `app/tests/Frontend/production-permission-controls.test.cjs`
- `app/tests/Frontend/production-primary-registry.test.cjs`
- `app/tests/Frontend/production-review-readback.test.cjs`
- `app/tests/Frontend/production-toolbox-download.test.cjs`
- `app/tests/Frontend/production-native-workflows.test.cjs`
- `app/tests/Frontend/production-full-shell-navigation.test.cjs`
- `tools/v1-clone/coverage.test.cjs`

The foundation TAP specifically completes wide-datum hit-target/layout and unrelated-focus preservation tests. The project TAP completes selector disposal/reinstallation ownership and advisory BoQ PDF disclosure tests. The calculator group includes nineteen calculator-contract checks, one migration-receipt gate check and three technical-workspace checks; a passing gate-contract fixture is not itself a newly executed migration.

## Two failures in the completed production/legacy receipt

1. **Stale BoQ PDF-format assertion** — `production-toolbox-download.test.cjs:8`, “BoQ artifact discovery stays bound to the saved selected-project run.” The test expected rejection matching `/format|unsupported/i`; the actual rejection was `Error: Invalid advisory artifact metadata.` The old expectation treated PDF as unsupported instead of exercising supported PDF with valid metadata. The parent reports that assertion is being corrected. The recorded group still has a failure; the separate project PDF-disclosure PASS does not replace a completed passing rerun of this group.
2. **Coverage source-hash pin drift** — `tools/v1-clone/coverage.test.cjs:8`, “independent PHP lenses include exact categories and project allowlists; all hashes match.” For `app/public/assets/v1-clone/js/runtime.js`, the recorded actual SHA-256 was `967dd2526a3c8674233dc0c9d2cac52df903a45aa3c7d8f309102f10e240d771`, while the recorded expected pin was `d99cb40e272558af18e6bd7785d311013959b70ee4004fb11873011984427a8f`. This is stale generated source-hash evidence after source changes, not a live role-workflow failure. At report preparation, `coverage.json` already contains the recorded actual hash; that file state is not a completed final passing coverage rerun. Final hash refresh and source-test verification remain required after edits settle.

## Subagent claims, independent review and older parent evidence

These must not be added to the completed table as independent new coverage or presented as live acceptance.

| Slice | Durable claim / observation | Independent-review boundary |
|---|---|---|
| Icons | After the review findings were fixed, the parent ran `app/tests/Frontend/material3-expressive-icons.test.cjs` to a clean completed exit: **15 passed, 0 failed, 0 skipped, 0 cancelled** (20.6s), covering exhaustive 49-production/47-clone/267-tab/135-key mapping and provenance, all 188 canonical conversions, isolated two-build determinism, checked-in freshness, refusal of unsafe SVG and nested viewports, preserved compound `fill-rule` inheritance, validation-before-publication and caught-error rollback. | The later independent code-quality re-review returned **APPROVED** with no critical/important/minor findings and its own 15-pass run. This supersedes the earlier `REQUEST_CHANGES` verdict quoted in the first draft of this report. Approval covers the compiler/publication contracts and assembled-data coverage, not live-browser acceptance of every menu. Multi-file atomicity is explicitly excluded by the publication contract. |
| Embedded | Subagent implementation/browser claims concern scoped BOM, safety and XA frames and active legacy workspaces, preserving original typography/palette/scripts and denied/read-only controls. | The delegated consolidation context identifies these claims as independently reviewed; this report has no separate final reviewer verdict artifact. The parent 7-pass completed receipt above independently verifies the listed fixture boundary, not complete live workflows. |
| Public access | The later registration stale-success/reset isolation run was independently executed by the parent: **17 passed, 0 failed, 0 skipped, 0 cancelled, 205s, completed exit**. Parent candidate-scoped PHP regression: **10 passed, 91 assertions** with candidate ReflectionClass identities. Spec review of the POST-await slice returned **PASS**, and the final code-quality review returned **APPROVED** with its own 17-pass rerun and no critical/important findings. | The earlier 16-pass wrapper exited 130 and is not a POSIX-clean receipt; the superseding 17-pass run is the evidence of record. `material3-access-parent.tap` (the aborted redirected run) contains only `stdin is not a tty` and does not substantiate any total. Already-sent POSTs are not claimed to have been cancelled, and sign-in `/sessions` success still reloads unconditionally with no separate defect established. |
| Calculator / project / commercial | Calculator: all eleven form contracts repaired without adapter changes; parent, spec and quality reviewers each verified **22 passed, 0 failed, 0 skipped**. Project: scope isolation, stale create/edit completion and controller teardown fixed; after the retained-selector fix the parent ran **13 passed, 0 failed, 0 skipped**; scope/teardown spec review PASS and selector code-quality re-review APPROVED. Commercial: nine-module styling spec review PASS and code-quality review APPROVED (12/12). Embedded theming: parent **7 passed** but one legacy case is order-dependent and is being made deterministic. | Calculator and commercial hold spec **and** quality approval. Project holds spec approval plus an APPROVED quality re-review after the final selector fix. These statuses still do not grant whole-application integration approval, and every one of them is fixture/synthetic-transport evidence rather than live persisted role acceptance. |

Synthetic access fixtures prove stale-form isolation and current-form behavior under simulated transport; they do not prove production registration or authenticated live access. Already-sent POSTs are not claimed to have been cancelled.

## Inventory, not live acceptance

`docs/material3-expressive/coverage.json` explicitly describes its basis as “Registry and source inspection, not execution receipts.” Its parsed counts are **21 profiles, 22 lenses, 4 memberships, 49 tools and 8 stages**. It also records 668 generated-menu acceptance candidates and 840 renderer contexts. These are inventory/context counts, not completed live journeys.

The handoff's partial menu snapshots (33 PASS / 13 UNSUPPORTED of 517 expected entries, and 4 PASS / 47 UNSUPPORTED of 508 expected entries) belong to different incomplete runs/revisions. They must not be combined or treated as complete acceptance. No profile workflow or lens visibility acceptance row is closed as live PASS.

## Live isolated-host evidence versus production read-only observations

### Live BoQ parser-completion defect and fix

The parent reproduced a real failure on direct `?workspace=bom&project=...` navigation: the iframe showed `data-v1-clone-adapter="error"` and a visible “Document frame was not prepared before srcdoc.” toast, although its themed BoQ fields had rendered. The fix worker instrumented the entry path and established the root cause: the original `loadToolFrame` 100ms polling loop mounted as soon as body children existed, while `document.readyState` was still `loading`; the boot function existed but the prepared-frame flag was not yet set. It appeared after parsing completed. Original injection, `<head>`/`</body>`, scripts and theme were intact.

`modules/document-tools.js` now waits for the prepared document’s `DOMContentLoaded` when necessary, rejects detached/replaced documents, and retains the strict bootstrap flag requirement—no readiness bypass. Delayed-stylesheet tests through both direct navigation and `openTool` reproduced RED and passed after the fix. The 34-test affected run and 5-test export run have completed receipts in the table. The parent independently re-ran the actual HTTP probe: **adapter ready, no preparation-error toast, theme present**. The initial stray 404 was identified as `/favicon.ico`, unrelated to the mount; a subsequent full non-2xx response sweep returned zero failures. Live history was empty, so no live BoQ save/readback or export-byte retrieval is claimed by this evidence. Spec review returned **PASS** and code-quality review **APPROVED**, with no Critical or Important finding in the production ownership path. The reviewer retained two nonblocking lifecycle hardening suggestions: the pre-mount parser wait is not cancellable by controller `dispose()` while its frame stays connected, and explicit detach/replacement-during-wait tests should be added. A probe confirmed that artificial connected-frame disposal followed by `DOMContentLoaded` can still read; normal runtime ownership disposes resolved handles, and detached/replaced documents reject before reads. Approval does not claim this synthetic teardown path is cancelled.

**Live isolated-host, synthetic test data:** The handoff records isolated MariaDB schema `architex_taskm37aa281_0bf7a858_test`, loopback port `59946`, scratch datadir `E:/Hermes/home/cache/scratch/material3-runtime/data`, with parent verification of 69 migrations, zero pending and a completion receipt. A synthetic HTTP project workflow observed create 201, missing-CSRF 419, viewer 403, cross-organisation 404 and reload/readback. The parent independently read back exact database row `85e4ce446699c513f4b6226e718efc59` through a guarded scratch bootstrap. This is a real HTTP/database transition on an isolated host using synthetic data, not production or all-role acceptance. The earlier subagent-owned HTTP server was terminated on interruption; the parent subsequently restored both the exact owned database and the HTTP host at `127.0.0.1:59947`, verified the migration receipt, normal 401 unauthenticated gate, and live authenticated iframe probes. Those later probes—not the old handoff snapshot—establish test-host availability at their observation times.

**Production/read-only deployment prerequisites:** The parent-provided read-only observations are:

- Production build-info `sourceCommit`: `6f21a24c6fc7bca06b14d4d48d352d12135a2f9f`.
- Health and readiness returned HTTP 200; readiness reported ready.
- No `app/src` or migration diff from that commit to HEAD was observed. This comparison does not certify all uncommitted changes or a final artifact.
- Existing **Deploy PHP** workflow supports `inspect` and `deploy` modes.
- `A777_CPANEL_API_TOKEN` is configured; no secret value is included here.

These prerequisite observations come from the parent's delegated evidence context, not a new production probe performed for this report. Health/readiness and configured deployment transport establish availability/prerequisites only. **Nothing has been deployed as part of this overhaul.** They do not prove candidate public delivery, authenticated access or workflow persistence.

## Remaining work before any release claim

### Latest whole-suite regression result

The in-app M3 inspection-dialog lifecycle slice subsequently received **SPEC PASS** and **code-quality APPROVED** with no Critical or Important findings. The parent and independent spec reviewer each completed **38 passed, 0 failed, 0 skipped** across the dedicated dialog, original native-workflow, technical and embedded suites. Valid source-frame ancestry now controls focus return; lifecycle aborts do not refocus obsolete documents, and every post-write UI continuation is guarded. The reviewer-confirmed nonblocking frame `load` callback leak is now fixed and re-approved: retained callback pairs are removed by guarded, idempotent `finish()` for success/cancel/abort, and the parent independently verified seven focused tests with **3 adds, 3 removes, zero outstanding listeners/dialogs** and preserved normal focus restoration. No accepted server write is represented as cancelled.

The parent's complete 19-file serial regression run (`material3-parent-full-regression-receipt.json`) completed with **161 tests, 160 passed, 1 failed, zero cancelled/skipped, exit 1**, in 687.73 seconds. The one recorded failure was the icon generator's independent checked-in freshness assertion: `icons/coverage-report.json` had an old source-coverage fingerprint after the new dialog/helper files landed. This is not a green release run and is not waived. Regenerate the canonical generated icon outputs after implementation freezes, independently verify two isolated builds and checked-in freshness, refresh the source coverage pins, then rerun the exact final candidate. The full run is also historical: concurrent worker edits were active while it executed, so it does not bind the final candidate even for its passing cases.

The subsequent native HTTP acceptance run `browser-proof-2026-10-09T17-13-17-818Z` exited **0**, but its report records a **fatal bootstrap timeout**, **0 PASS**, and **508 unexecuted/unverified placeholders** labelled `UNSUPPORTED`. That exit does not establish success; the 508 rows are not evidence that 508 features are unavailable or tested. Session expiry/preflight is being repaired, including a nonzero fatal exit. The exact same source snapshot must be rerun with authenticated synthetic sessions after repair; no role/menu acceptance is closed from this failed attempt.

After the last app edits and canonical artifact/source coverage refresh, the parent's exact 20-file serial regression completed **168/168, exit 0, zero failures/cancellations/skips**. This supersedes the historical 160/161 freshness failure for regression readiness; it does not supersede the separate failed live acceptance attempt. The profile-reuse preflight defect is now spec-approved (strict native identity/organisation/membership/profile checks remain) and its fresh-context quality review is in progress. Final independent whole-diff integration/security review is also in progress. No production deployment is claimed.

### Dedicated native demo-lens evidence

`demo-lens-acceptance.json` records **22/22 native service, real HTTP and original legacy `/demo` selector portfolios PASS** on one separate synthetic test tenant. Evidence includes 185 allowed project-detail responses, 22 outside-lens/foreign-tenant denials, persisted role selections and canonical persona mappings, HTTP401 authentication/419 CSRF gates, non-reliance/non-accreditation labels, final `admin` reload and zero browser page errors. The parent independently read the actual MariaDB assignment rows and compared all 22 registry → catalog project-code → database-UUID sets against the report: **22 sets, 185 assignments, final `admin` 12 visible projects and foreign-project denial corroborated**. The parent did not independently replay all 22 browser selections; that UI layer is the worker's real-browser proof, now in spec review. This proof created only its own dedicated tenant/user in the already guarded disposable schema; existing acceptance fixture files/tenant remained unchanged. It is not normal production login, professional permission/verification or CPD accreditation.

1. Complete live all-menu and per-profile/lens acceptance on the healthy isolated candidate, with actual session, membership, organisation and project scope, screenshots/console evidence, refusal paths, exact save/readback/reload and appropriate database persistence evidence. Keep unavailable external/provider execution separate from internal workflow capability.
2. Finish the BoQ assertion correction and retain a completed rerun receipt; refresh coverage source hashes after source changes settle and verify all four coverage/source tests on that final revision. Preserve the historical failing receipt rather than relabeling it.
3. Resolve or confirm closure of icon compiler review findings and obtain final review verdicts for access, role dashboards, project lifecycle and embedded integration where outstanding. Finish relevant JS/PHP regressions and independent final integration/security review, bound to the exact artifact.
4. Deploy only the exact reviewed artifact through the authorized existing workflow once acceptance and prerequisites succeed. Deployment itself remains undone. Then independently verify the exact public build/assets, readiness, login and supported workflows; an upload success alone is insufficient.

This consolidation created only this report. It did not rerun long suites, modify application code/configuration, mutate a database or deploy.
