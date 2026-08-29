# Release 14 local certification — remaining standalone tool tiles

Date: 2026-08-29
Scope: Pack 17 Phase 5 remaining tool runners, audit/export behaviour and role-scoped tile UX
Runtime: PHP 8.3.32, MariaDB 11.4 at `127.0.0.1:43319`, persistent Chrome DevTools page 12 at `http://127.0.0.1:8790/`

## Implemented scope

- Implemented authenticated, tenant-scoped MariaDB runners for all 34 remaining Pack 17 registry entries. Together with the seven already-live Pack 17 tools, this covers all 41 IDs actually present in `standaloneToolRegistry.ts`; the pack manifest's stated total of 43 does not match its supplied registry source.
- Each remaining tool has an explicit input schema, deterministic versioned output, human-entered/advisory boundary, SHA-256 record checksum, run history and JSON/CSV/HTML evidence exports.
- Money calculations use integer cents and percentage configuration uses basis points. Workforce/freelancer timesheets, soft-cost estimates, tenders, payment claims, quotes, catalogue prices and tariff/rate records therefore avoid floating-point currency persistence.
- All supplied tiles now open a standalone runner without active project context. Cards include readable descriptions, tags, search/category metadata and run counts.
- The 12 registry entries marked `canAssignToProject: false` do not expose assignment actions. Assignable entries retain audited Architex-project and external/offline assignment paths.
- Generic record outputs cannot be issued automatically and remain explicit human-entered drafts requiring authorised review.

## Automated evidence

- Frontend and unit contracts: `OK (6 tests, 192 assertions)` in 00:00.020.
- Connected remaining-tools scenario: `OK (1 test, 11 assertions)` in 03:05.973 against only the explicitly disposable `architex_task13_facefeed_test` schema.
- The connected scenario proved 35 owner-visible definitions in an isolated registry (fee calculator plus 34 remaining records), RFI and workforce persistence, integer-cent payroll calculation, external assignment, three exports and fail-closed issue attempts.
- `node --check app/public/assets/js/shell.js` passed. `StandaloneRecordToolService.php`, `ProductionToolRegistry.php` and `AuthenticationKernelFactory.php` passed `php -l`.
- `git diff --check` reported no whitespace errors (only the repository's expected LF-to-CRLF notices).

## MariaDB and HTTP evidence

- Chrome created run `9d015d965598cc5528f8902e425d8b95` once for `rfi_generator`, reference `RFI-CHROME-34`, status `completed`, record checksum `985506d1d17fd033aaddd3049d66be1b6ccf4219be4242a4270f596e724a8897`.
- An authenticated post-run assignment returned HTTP 200 and linked the run to project `304dc7da987382b3861b5a80fbe27470`, `SpecForge DevTools Project`.
- MariaDB contains one `tool_run.completed` event and one later `tool_run.assignment_changed` event for the run. The latter records the exact from/to assignment and the Chrome evidence note.
- Export discovery returned HTTP 200. All three download requests returned HTTP 200 with integrity headers.
- Stored export hashes are JSON `3e876c79c9cc39c76a0fff83c4822b2ca89c507f7dbd57880239491454b3b5b6` (1,819 bytes), CSV `cf56c0eb3f3835ccf2eaf78c12503a68ea9fc44058934b0a56c0e0cbd1689375` (1,246 bytes), and HTML `89306e19a873d1d257f36db7bfa670fae93f518e63454799b07443f64555b0e8` (2,601 bytes).

## Chrome DevTools evidence

- Persistent authenticated page ID 12 remains open for `browser-owner@example.test`.
- The browser definition endpoint returned 50 total production definitions: one fee, five compliance, eight engineering, two Pack 17 priority utilities and 34 remaining record tools.
- The owner/architect lens displayed 19 role-authorized cards, no backend-unavailable cards, readable descriptions and no project-context redirects.
- Browser flow covered RFI form generation from the production input schema, MariaDB save, post-run Architex-project assignment and all three evidence downloads.
- Non-assignable CPD, resource-centre and admin-governance cards displayed no assignment button; the RFI card and its recent run retained assignment controls.
- Mobile geometry at 390 x 844 was `clientWidth=390`, `scrollWidth=390`, with no horizontal overflow and 19 loaded cards.
- Mobile Lighthouse: Accessibility 100, Best Practices 100, SEO 100, Agentic Browsing 100; 33/33 applicable audits passed.
- Desktop Lighthouse: Accessibility 100, Best Practices 100, SEO 100, Agentic Browsing 100; 33/33 applicable audits passed after increasing the default aqua-button text contrast.
- Final desktop console inspection found zero errors, warnings or issues.
- Visually inspected screenshots: `release14-standalone-tools-desktop.png` and `release14-standalone-tools-mobile.png` in this evidence directory.

## Preserved source and release boundaries

- Canonical `v1.html` was not edited; SHA-256 remains `7DBFAFDCFA52644FD32FB7CB7D73891B78593CA0287C089240DB4439E0FA97BF`.
- Pack inputs remain untracked and excluded from the application commit.
- These schema-driven record/calculation runners do not claim live external payroll, municipal, CAD/BIM, AI review, identity verification, payment-provider or professional-body integrations.
- This certifies Pack 17 locally. Packs 19 and 20, aggregate gates, immutable FTP candidate rebuilding, SMTP, remote FTP staging, target backup rehearsal, control-panel cron and production promotion remain separate gates.
