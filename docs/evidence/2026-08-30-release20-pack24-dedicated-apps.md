# Release 20 — Pack 24 Dedicated Apps Unified Evidence

## Certified source and FTP artifact

- Source commit: `8e913e88013db801cb7612fdfa56568f04a92866` on `master`.
- Candidate: `dist/architex-release-staged-20260830-8e913e8`.
- `release.json`: PHP 8.3, `php-shared-hosting`, built `2026-08-30T01:45:26+00:00`.
- Manifest verification: passed, 342 files.
- FTP ZIP: `dist/architex-release-staged-20260830-8e913e8.zip`.
- ZIP bytes: `1123251`.
- ZIP SHA-256: `4F91AA506825575EFAED440851717494D16BA793E4F2AC59090E5BB750403756`.
- Independent `Expand-Archive` extraction and `verify-release.php`: passed; 343 extracted files including the manifest; temporary verification directory removed.
- The release package contains both bounded shared-hosting cron entrypoints: `bin/deliver-community-notifications.php` and `bin/deliver-dedicated-app-notifications.php`.

## Implemented production boundary

- All 12 supplied capabilities are represented by the canonical PHP registry with runtime kind, priority, source anchors, governed outputs, offline/device requirements, MVP capabilities and sensitive gates.
- Installation credentials are returned once, persisted only as SHA-256 hashes, scoped to one tenant/device/app and rejected after revocation or disablement.
- Workflow events are tenant/project scoped and idempotent. Their declared outputs now create real `project_records` and `inbox_items` linked through migration 0050, rather than reporting routing intentions only.
- FileManager outputs require at least one active tenant/project-owned `stored_files` reference and fail closed for missing or foreign evidence.
- Approval, signature, payment release, municipal submission, professional certification and closeout acceptance remain human gated; `aiMayExecute` is always false.
- Offline batches use optimistic base versions and retain visible conflict records; stale clients never silently overwrite server state.
- The bounded CLI dispatcher sends only evidenced in-app notifications. Unconfigured email, push, SMS and WhatsApp providers remain `integration_required`.
- Native desktop agents, Revit/CAD plugins, signed remote-desktop enforcement, signature/payment providers and other unavailable binaries remain explicitly `integration_required`; registration is not online proof.
- The manifest/service worker provide installable PWA shell behavior while bypassing all `/api/` requests and non-GET requests.

## Automated verification

- Full suite: 303 tests, 1204 assertions, 142 DB-dependent tests skipped in the non-DB run, exit 0.
- Dedicated connected MariaDB suite: 2 tests, 26 assertions, exit 0 after all 50 migrations.
- Connected assertions cover credential secrecy, heartbeat, event idempotency, real record/inbox links, false-confirmation rejection, approval transition, bounded dispatch, unsupported-provider fail-closed behavior, tenant isolation, applied sync, stale conflict and foreign FileManager rejection.
- Release artifact contract: 3 tests, 19 assertions, including both notification cron scripts.
- PHP syntax, JavaScript syntax and `git diff --check`: passed.
- Persistent browser database migration status: current, 50 applied, 0 pending.

## Persistent Chrome DevTools MCP certification

Final candidate URL: `http://127.0.0.1:8802/app`, retained on selected page 17 at 1920 × 1080.

- Build API returned exact commit `8e913e88013db801cb7612fdfa56568f04a92866` and PHP 8.3 shared-hosting mode.
- Dedicated Apps rendered 12 cards and the truthful `api_backed` / `integration_required` boundary.
- Owner registration used the exact candidate commit; the corrected UI visibly retained the 43-character one-time credential after its post-registration refresh. It was then used and redacted.
- Client heartbeat returned 200, `online`, audit writer ready, FileManager writer ready and the exact candidate commit.
- Sensitive event creation returned 201 with `requires_human`, `aiMayExecute=false`, project record `fde11f69762f07b97974a66bae586ac7` and inbox item `6f6aed83fa6a1fd9fe0bafc421a3a123`.
- Replaying the same external event returned the same ID. False confirmation returned 409 `dedicated_app_human_confirmation_required`; accountable confirmation returned 200 `completed`.
- Independent operating-snapshot GET returned the linked project record as `approved`, source `dedicated_apps`, `humanApprovalConfirmed=true`, and the linked inbox item as high priority, approval gated and `aiMayExecute=false`.
- Packaged CLI dispatch reported `sent=2`, `remaining=0`; an immediate second run reported `sent=0`, `remaining=0`.
- Final hydrated UI: 12 cards, 4 installations, 4 workflow events, 2 retained offline conflicts and zero desktop horizontal overflow.
- Desktop screenshot: [release20-pack24-dedicated-apps-desktop.png](release20-pack24-dedicated-apps-desktop.png).
- Mobile screenshot: [release20-pack24-dedicated-apps-mobile.png](release20-pack24-dedicated-apps-mobile.png).
- Mobile 390 × 844: 12 cards and zero horizontal overflow.
- Lighthouse mobile snapshot: Accessibility 100, Best Practices 100, SEO 100, Agentic Browsing 100; 30 passed, 0 failed. Reports are in `docs/evidence/release20-lighthouse/`.
- Final network sample: 14 fetch/XHR requests, all HTTP 200, including session, projects, inbox, tools, demo data, all four dedicated-app reads, build info and service worker.
- Console: no JavaScript errors or warnings. Chrome reported three generic form-field issues from the broader shell; Lighthouse accessibility remained 100.

## Truthful release status

Pack 24's PHP/MariaDB integration spine, governed APIs, shared operational outputs, bounded cron dispatch, PWA boundary, V1 control centre and FTP artifact are complete. Third-party providers and signed native binaries are not represented as available until independently supplied and verified.
