# Release 13 local certification — standalone tool tiles core

Date: 2026-08-29
Scope: Pack 17 priority standalone tools and shared toolbox shell, local staged-release gate
Runtime: PHP 8.3.32, MariaDB 11.4 at `127.0.0.1:43319`, persistent Chrome DevTools page 12 at `http://127.0.0.1:8790/`

## Implemented scope

- Role-scoped Pack 17 tile catalogue with AI-guided / All tools modes, search, category filtering, honest production/project-only labels, per-tool run counts and recent history.
- Existing production fee, FenCalc XA, thermal, drawing-precheck and SANS checklist workflows are reachable as standalone tools.
- New deterministic BoQ / BoM takeoff and human-entered site diary services persist authenticated, tenant-scoped runs in MariaDB.
- Completed, non-issued runs can be assigned after creation to an Architex project or an external/offline reference. Every change is audited.
- Issued runs remain immutable. Assignment changes transactionally regenerate JSON, CSV and HTML evidence so exports cannot retain stale assignment data.
- Project-only Pack 17 tiles are explicitly labelled and route to the corresponding project workflow; no simulated backend or external integration was introduced.

## Automated evidence

- Frontend standalone catalogue contract: `OK (1 test, 27 assertions)`.
- Connected standalone utility scenario: `OK (1 test, 13 assertions)` in 03:48.932 against the explicitly disposable `architex_task13_facefeed_test` schema.
- Connected assignment, issued-run immutability and refreshed-export integrity scenario: `OK (1 test, 8 assertions)` in 03:17.438.
- Authenticated HTTP BoQ create-and-external-assignment scenario: `OK (1 test, 4 assertions)` in 03:03.910.
- `node --check app/public/assets/js/shell.js` passed; changed PHP source files passed `php -l`.

## MariaDB and HTTP evidence

- No migration was required. The live development schema and repository both contain 36 migrations through `0036_analytics_reporting`.
- Post-fix Chrome run `f59d6f11db1ae8f08edf4ecd1f028342` persisted once with title `Chrome No Resubmit Proof`, status `completed`, total `1100000` cents and external reference `NO-RESUBMIT-13`.
- The matching `tool_run.assignment_changed` audit count is exactly 1 and its stored target reference is `NO-RESUBMIT-13`.
- Refreshed exports are JSON SHA-256 `ec58a91ae3eb2a8b629d75c37b51a34c40f4c965af14f39e9d48852ce08ff6e9` (1,667 bytes), CSV `038a82804bcc4f6b79e1de5495fee10a813ec467afb767f2c3bcd24132473809` (956 bytes), and HTML `deda60b374d78985f04d72fd63ccd713c1321f1cd5052c8593bc59d2c76c6259` (2,431 bytes).
- Chrome downloaded all three exports with HTTP 200. A direct authenticated read of the regenerated JSON returned the external assignment, title, total and matching `X-Content-SHA256` header.

## Chrome DevTools evidence

- Persistent authenticated page ID 12 remains open on Workspace Tools for `browser-owner@example.test`.
- Visible browser flow covered tile loading, search (`thermal`), Compliance category filtering, BoQ calculation, MariaDB save, external assignment and all three evidence downloads.
- Browser discovery showed the BoQ tile count move from 2 to exactly 3 after one post-fix create-and-assign flow. Clicking Assign retained `Saved to MariaDB.` and did not resubmit the calculator.
- Desktop Lighthouse snapshot: Accessibility 100, Best Practices 100, SEO 100, Agentic Browsing 100; 33/33 applicable audits passed.
- Mobile Lighthouse snapshot at 390 x 844: Accessibility 100, Best Practices 100, SEO 100, Agentic Browsing 100; 33/33 applicable audits passed.
- Mobile geometry was `clientWidth=390`, `scrollWidth=390`, with no horizontal overflow and a single-column tile grid.
- Screenshot review caught and corrected collapsed-rail metadata that had been forced visible over the workspace; collapsed mode now reports `visibility=hidden` and `opacity=0` for every rail metadata label.
- Final desktop console inspection found zero errors, warnings or issues.
- Screenshots: `release13-standalone-tools-desktop.png` and `release13-standalone-tools-mobile.png` in this evidence directory.

## Preserved source and release boundaries

- Canonical `v1.html` was not edited; SHA-256 remains `7DBFAFDCFA52644FD32FB7CB7D73891B78593CA0287C089240DB4439E0FA97BF`.
- Pack source material remains untracked and is not included in the deployable application commit.
- BoQ values and diary facts are human-entered advisory records. They do not verify quantities, rates, tax, weather, attendance, progress, instructions or entitlement.
- This certifies the Pack 17 priority core locally. Remaining Pack 17 standalone workflows, Packs 19 and 20, SMTP, remote FTP staging, target backup rehearsal, control-panel cron and promotion remain separate gates.
