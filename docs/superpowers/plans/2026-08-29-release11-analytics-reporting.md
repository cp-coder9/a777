# Release 11 — Analytics & Reporting implementation plan

## Scope

Implement Pack 15 as a tenant-isolated PHP 8.3 / MariaDB module on shared-hosting-safe infrastructure. Treat every pack item as unimplemented. Preserve `v1.html` byte-for-byte and expose the production workspace through the existing `market_insights` tool.

## Delivery sequence

1. Add failing connected and frontend contract tests for all five KPIs, immutable formula versions and snapshots, role dashboards/layouts, throttled acknowledgement-required alerts, scoped CSV/JSON exports, redacted observability, ProjectRecords, Inbox and audit evidence.
2. Add a MariaDB migration for formula versions, immutable KPI snapshots, dashboard layouts, alert rules/events and retention-bounded observability metrics.
3. Implement a tenant/project-scoped analytics service, HTTP controller/routes and permission wiring. Analytics remain advisory and cannot mutate approvals or source domain records.
4. Replace the `market_insights` scaffold with a role-aware live workspace for KPIs, layout, alert governance, acknowledgements, exports and platform health.
5. Run connected MariaDB, isolation, frontend, syntax and diff gates; apply the migration locally; verify the authenticated workspace through persistent Chrome DevTools at desktop and mobile widths; capture DB/browser evidence.
6. Commit only deployable application, test, plan and evidence files. Never stage `packs/` or `v1.html`.

## Guardrails

- KPI formula changes create new versions; historical snapshots and source provenance are immutable.
- Alerts are per-project, throttled, create Inbox work, and require explicit acknowledgement; they never approve or block a formal workflow by themselves.
- Exports are organisation/project/date scoped and apply a conservative POPIA redaction pass to structured payloads.
- Observability stores aggregates and bounded technical context only, never raw personal content; retention is explicit.
- No external analytics provider, background daemon, queue service, SMTP delivery or target-host capability is claimed.
