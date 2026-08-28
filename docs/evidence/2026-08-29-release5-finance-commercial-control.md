# Release 5 local evidence: finance and commercial control

Date: 2026-08-29 (Africa/Johannesburg)

## Outcome

Release 5 is locally implemented and verified for PHP 8.3 and MariaDB. It is not provider-live, shared-host staging certified, or production promoted. Architex does not hold funds, represent escrow custody, move money, or release payments automatically. With no contracted connector credentials, provider-bound workflows correctly stop at `provider_configuration_required`.

The supplied `v1.html` remained unchanged. Its SHA-256 after the release was `7DBFAFDCFA52644FD32FB7CB7D73891B78593CA0287C089240DB4439E0FA97BF`.

## Implemented controls

- Commercial baselines require a formally issued appointment source and retain immutable SHA-256 revisions.
- Payment schedules reconcile exactly to the current integer-cent contract sum.
- Variations use optimistic versions and separate quantity-surveyor, lead-professional and client decisions before incorporation changes the contract sum.
- Claims and evidence references are immutable. Certificates keep claimed, gross certified, retention, prior certified payments, deductions, dispute and net-payable values separate.
- Retention accrual, balances, completion evidence and separate release approvals are persisted without automatic release.
- Provider registry responses contain metadata only. Registration does not imply live configuration and secrets are never returned.
- Provider release requests remain dispute locked or configuration blocked until all human and external gates exist.
- Webhooks require a fresh timestamp and HMAC signature, are idempotent, validate reference/amount/currency, preserve rejected evidence, and handle processing, paid, failed and refunded states without unsigned mutation.
- Required ProjectRecords, inbox items and audit events are emitted transactionally, including exact `escrow_release_request`, `retention_release`, `variation_order`, `payment_schedule`, `payment_certificate` and `cashflow_forecast` record contracts.

## Automated evidence

- Release 5 focused suite: `OK (6 tests, 135 assertions)` in 13m31s. This covered three MariaDB service scenarios, authenticated/CSRF/tenant HTTP boundaries, and frontend contracts.
- Generated-column backup regression: initial red was MariaDB error 1906 for `project_team_members.active_slot`; the fixed restore omits `IS_GENERATED='ALWAYS'` fields and lets MariaDB recompute them. Green result: `OK (1 test, 5 assertions)`.
- PHP syntax and `git diff --check` passed for changed PHP, JavaScript/CSS contracts, tests and evidence slices.
- Provider negative coverage includes stale timestamp, invalid HMAC, mismatched amount rejection, valid paid reconciliation and duplicate replay.

## Chrome DevTools evidence

The persistent authenticated browser used `browser-owner@example.test` and project `af189b7f799637ca7918b1a3d6858292` (`DevTools Verified Project`). The project selector was repaired so MariaDB project options survive canonical-shell rerenders.

- Desktop 1440x900: Accessibility 100, Best Practices 100, SEO 100, Agentic Browsing 100; 33 audits passed, 0 failed.
- Mobile 390x844 on the finance workspace: Accessibility 100, Best Practices 100, SEO 100, Agentic Browsing 100; 33 audits passed, 0 failed.
- Clean desktop navigation reported no console errors, warnings or issues.
- Session, projects, inbox, tools, finance workspace and appointments requests all returned HTTP 200.
- Browser-created live data persisted after reload: R1,250,000 baseline, issued schedule, submitted variation with client approval, immutable R250,000 claim, and two cashflow snapshots.
- An organisation owner attempting a quantity-surveyor decision received HTTP 403. The authorised client decision returned HTTP 201. No fake QS or provider identity was seeded into the live evidence database.

## MariaDB and recovery evidence

- Live schema: 30 applied migrations through `0030_finance_reconciliation_and_closeout`, with no pending migration.
- Encrypted backup: `app/storage/backups/release5-local-20260829.axbackup`, 257,009 bytes, archive SHA-256 `84C803EE4FA005F1DE61E9B5352F9722E51A949A447A7BD7A84E3EBD8FEF00BB`.
- Authenticated manifest backup ID: `9c2812e0711856677abfea76038a5cb9515ace522ccd146c686cca9247df28b8`.
- Guarded restore target: loopback-only `architex_task13_facefeed_test`; restored 519 rows and 3 private files.
- Restored readback confirmed 30 migrations, 5 users, the active browser owner, `DevTools Verified Project`, one commercial baseline, one payment schedule, one variation, one claim and two cashflow snapshots.
- Restored private-file hashes exactly matched MariaDB: `459F4FD50E3248823C803558775C016FBAF0A9B43FFA5F89AF5DEE2D91A2B88A`, `4673B8CA7B928240D7B693DA203CAF0DABC1C41D87D244C2A12A86BFDFEF7358`, and `0AF15ACE7FE6CDD62F98D19CB4D2B96F7010343F294676460B1AF02CD6B212C5`.

## Separate staged-release gates

The following are deliberately not claimed by local evidence:

- a signed agreement and credentials for a registered third-party payment/escrow/trust/EFT provider;
- provider connector submission and real webhook traffic;
- shared-host MariaDB backup, migration and restore rehearsal;
- FTP upload and staged document-root activation;
- TLS, domain, PHP limits, writable private storage and control-panel cron freshness;
- SMTP registration verification and password-reset delivery;
- final aggregate regression after Releases 6–8 and production promotion approval.
