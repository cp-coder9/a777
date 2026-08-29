# Release 7 local evidence: closeout, handover and occupancy

Date: 2026-08-29 (Africa/Johannesburg)

## Outcome

Release 7 is locally implemented and verified for PHP 8.3 and MariaDB. It is not shared-host staging certified or production promoted. Architex does not grant municipal occupation approval, issue completion without authorised human confirmation, move money, or release retention from this module.

The supplied `v1.html` remained unchanged at SHA-256 `7DBFAFDCFA52644FD32FB7CB7D73891B78593CA0287C089240DB4439E0FA97BF`.

## Implemented controls

- Practical-completion assessment consumes critical snag/NCR state and explicit checklist evidence.
- Professional issue is authority-gated and stores an immutable SHA-256 snapshot; client acceptance is a separate audited transition.
- Handover packs are versioned and cannot issue until verified as-built, warranty, O&M, keys/access and compliance evidence categories exist.
- Occupation readiness stores municipal certificate evidence, insurance transition and utilities checks while hard-coding `represents_municipal_approval=false`.
- Final-account assessment reads commercial state and records recommendations with `moves_money=false`; retention remains in Finance.
- Defects-liability periods support defect reporting, contractor recall, corrective evidence, professional verification and post-expiry closeout recommendations.
- ProjectRecords use platform status envelopes while retaining exact closeout states in payloads. Inbox, audit and recommendation outputs are transactional.

## Automated evidence

- Final Release 7 suite: `OK (4 tests, 46 assertions)` in 13m20s. This covered the connected MariaDB workflow, cross-tenant hiding, encrypted backup/isolated restore and frontend contracts.
- Earlier connected red/green proof finished at `OK (1 test, 27 assertions)` after exposing and fixing retry-safe DDL ordering and ProjectRecord status normalisation.
- PHP syntax passed for service, exception, controller, routes and kernel factory. JavaScript syntax, frontend test and `git diff --check` passed.
- Migration `0032_closeout_handover_occupancy.sql` was applied to the persistent local database with 1 applied and 0 pending.

## Chrome DevTools evidence

- The persistent authenticated browser opened FM Bridge from the real Workspace Tools registry.
- Visible controls created a readiness assessment, issued it with professional confirmation, and recorded separate client acceptance.
- A handover pack remained correctly incomplete with the five missing mandatory categories shown.
- Final-account readiness correctly blocked on `commercial_baseline_missing` and retained `moves_money=false`.
- Direct MariaDB readback confirmed `client_accepted`, `represents_municipal_approval=0`, a 64-character snapshot hash, handover version 1, and four closeout ProjectRecords.
- Browser negative checks returned anonymous `401`, missing-CSRF `419`, and hidden-project `404`.
- Final desktop Lighthouse snapshot scored 100 for Accessibility, Best Practices, SEO and Agentic Browsing; 31 passed and 0 failed.
- At 390x844, the Closeout workspace reported 390px document/client width and 0px horizontal overflow. The browser was restored to 1440x900 and left open on Closeout & Handover.
- Final functional flow inspection reported no console messages; all observed success-path requests returned 200/201.

## Separate staged-release gates

- remaining Pack 12–15 implementation and final aggregate repository regression;
- shared-host MariaDB backup, migration and restore rehearsal;
- FTP upload and staged document-root activation;
- TLS, domain, PHP limits, writable private storage and control-panel cron freshness;
- SMTP registration verification and password-reset delivery;
- production import, promotion approval, smoke test and rollback proof.
