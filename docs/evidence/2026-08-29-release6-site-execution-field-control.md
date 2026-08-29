# Release 6 local evidence: site execution and field control

Date: 2026-08-29 (Africa/Johannesburg)

## Outcome

Release 6 is locally implemented and verified for PHP 8.3 and MariaDB. It is not shared-host staging certified or production promoted. Field records do not automatically grant EOT or variation entitlement, internal inspections do not imply statutory approval, and payment blockers are recommendations that never move money.

The supplied `v1.html` remained unchanged. Its SHA-256 after the release was `7DBFAFDCFA52644FD32FB7CB7D73891B78593CA0287C089240DB4439E0FA97BF`.

## Implemented controls

- Daily logs retain weather, labour, plant, deliveries, visitors, delays, safety notes and optional private project evidence.
- RFIs preserve open, overdue, responded and closed states with controlled professional responses and linked document references.
- Site instructions require authorised human issue, retain cost/time review flags, and are superseded rather than edited after issue.
- NCRs and snags require corrective or reinspection evidence before professional verification and closeout.
- Candidate inspection records require an identified professional supervisor before issue.
- Delay warnings and programme impacts are evidence-first review records and never grant contractual entitlement.
- Payment blockers remain finance recommendations and can clear only after the source field-control item is resolved.
- Tenant/project scoping, ProjectRecords, inbox events, audit events and advisory recommendations are enforced transactionally.

## Automated evidence

- Connected Release 6 and recovery suite: `OK (4 tests, 63 assertions)` in 20m18s. This covered two MariaDB service scenarios, authenticated/CSRF/tenant HTTP boundaries, and encrypted backup with isolated database/file restore.
- Frontend hardening suite: `OK (2 tests, 37 assertions)` for the unified site workspace, local date values and controlled malformed-API-response errors.
- JavaScript syntax passed for `shell.js` and `api-client.js`; PHP syntax had already passed for the Release 6 service, controller, routes, factory and tests.
- Migration `0031_site_execution_field_control.sql` is applied to the persistent local database: 31 applied and 0 pending.

## Chrome DevTools evidence

The persistent authenticated browser used `browser-owner@example.test`, organisation `9775135843504f4eb3f9656493770756`, and project `af189b7f799637ca7918b1a3d6858292` (`DevTools Verified Project`).

- The real Workspace Tools navigation opened Site Instructions and hydrated the unified MariaDB-backed workspace.
- A daily log was submitted through the visible form and remained after a full reload.
- An RFI was created, professionally responded to and closed. Its linked formal site instruction was drafted, human-confirmed for issue, and acknowledged.
- Direct MariaDB readback confirmed the submitted log, closed RFI, acknowledged instruction with variation flag, 7 ProjectRecords, 4 inbox events and 7 audit events.
- Local-time defaults were verified at Africa/Johannesburg values after the UTC-form bug was fixed.
- Desktop Lighthouse snapshot: Accessibility 100, Best Practices 100, SEO 100, Agentic Browsing 100; 31 audits passed and 0 failed.
- At 390x844 the workspace had 390px document width and 0px horizontal overflow. The existing compact canonical rail leaves a narrow content column and remains a visual constraint for later whole-shell refinement.
- Final console inspection reported no messages. Session, projects, inbox, tools and site-workspace requests all returned HTTP 200.

One transient local workspace refresh exceeded PHP's 30-second development-server limit and returned an HTML fatal body with HTTP 200. The record itself committed correctly; the next measured workspace request returned valid JSON in 3.886s and the condition did not recur. The API client now fails malformed successful responses with a controlled `invalid_api_response` error instead of a null dereference. Shared-host PHP limits and staging load behaviour remain explicit release gates.

## Separate staged-release gates

The following are deliberately not claimed by local evidence:

- final aggregate full-repository regression after Releases 7 and 8;
- shared-host MariaDB backup, migration and restore rehearsal;
- FTP upload and staged document-root activation;
- TLS, domain, PHP limits, writable private storage and control-panel cron freshness;
- SMTP registration verification and password-reset delivery;
- production data import, final promotion approval and production smoke/rollback proof.
