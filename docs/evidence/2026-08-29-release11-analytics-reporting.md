# Release 11 local certification — Analytics & Reporting

Date: 2026-08-29
Scope: Pack 15, local staged-release gate
Runtime: PHP 8.3.32, MariaDB 11.4 at `127.0.0.1:43319`, persistent Chrome DevTools page at `http://127.0.0.1:8790/`

## Implemented scope

- Five source-backed KPIs: schedule variance, cost to complete, defect-liability remaining days, retention-release readiness and compliance-gap count.
- Organisation-scoped formula definitions with immutable versions; unsupported formula text is rejected so recorded expressions cannot diverge from the implemented calculator.
- Immutable KPI snapshots with formula version, as-of date, calculation inputs, source ProjectRecord IDs and SHA-256 evidence; every snapshot also creates a `kpi_metric` ProjectRecord.
- Admin, professional, client and contractor dashboards with versioned layouts and configurable 30–3600 second live refresh.
- Project alert rules with typed conditions, severity, recipient role, frequency throttling, Inbox events and explicit recipient/governance acknowledgement.
- ProjectRecords, Inbox and audit CSV/JSON exports with organisation/project/date scoping, download checksums and conservative recursive personal-field redaction.
- Retention-bounded latency, error-count and agent memory-boundary observability using whitelisted aggregate technical context only.
- A production Market Insights workspace covering the five KPIs, layouts, alert governance, acknowledgements, exports, formula history and platform health.

## Automated evidence

- Connected analytics scenario: `OK (1 test, 38 assertions)` in 03:02.026 against `architex_task13_facefeed_test`.
- Connected cross-tenant project denial: `OK (1 test, 1 assertion)` in 03:12.725 against the same explicitly disposable schema.
- Final frontend contract: `OK (1 test, 26 assertions)`.
- PHP syntax passed for the exception, service, controller, route registrar, kernel wiring and both test files.
- `node --check app/public/assets/js/shell.js` passed.
- `git diff --check` passed; only Git's informational LF-to-CRLF warnings were emitted.

## MariaDB and HTTP evidence

- Migration `0036_analytics_reporting.sql` applied to the bounded local development database; status is current with 0 pending.
- The authenticated browser created 5 formula versions, 5 KPI snapshots / `kpi_metric` ProjectRecords, 1 client layout, 1 alert rule, 1 alert event / Inbox item and 1 observability metric.
- The live analytics actions produced 12 `analytics.*` audit events.
- The live rule evaluated an existing `popia_breach` ProjectRecord where `regulatorNotificationRequired=true`; the resulting high-priority owner alert was explicitly acknowledged and retained `formalApprovalChanged=false`.
- The live latency metric is `18.7 ms`, retains only `{operation: "dashboard.refresh"}`, expires 2026-09-28 and contains no raw personal content.
- An authenticated CSV export returned HTTP 200, `text/csv`, a private no-store attachment name, SHA-256 `7376210501901b039fc87bdd0e4171dc42031842497c10539c273dd6e5874d7e`, 35,118 bytes and included the KPI records.

## Chrome DevTools evidence

- Persistent authenticated page ID 12 remains open on Market Insights / Analytics & Reporting for project `304dc7da987382b3861b5a80fbe27470`.
- Visible controls installed formulas, created KPI snapshots, saved a client dashboard, configured/evaluated the POPIA rule, acknowledged its alert and recorded redacted platform health.
- Exact 390 x 844 mobile emulation rendered all eight Analytics & Reporting section headings with `clientWidth=390`, `scrollWidth=390` and zero horizontal overflow.
- Mobile Lighthouse snapshot: Accessibility 100, Best Practices 100, SEO 100, Agentic Browsing 100; 31/31 audits passed.
- Restored 1440 x 900 desktop viewport rendered Analytics & Reporting with zero horizontal overflow.
- Desktop Lighthouse snapshot: Accessibility 100, Best Practices 100, SEO 100, Agentic Browsing 100; 31/31 audits passed.
- Final reload after the autocomplete repair showed zero console errors, warnings or issues and retained the acknowledged alert and platform-health metric from MariaDB.

## Preserved source and release boundaries

- Canonical `v1.html` was not edited. Its SHA-256 is rechecked before commit.
- Pack source material remains untracked and is not part of the deployable application commit.
- Analytics are advisory and cannot mutate formal approval state or source domain records.
- This is local staged-release certification only. No FTP upload, shared-host migration, target cron, remote backup rehearsal or production promotion is claimed.
