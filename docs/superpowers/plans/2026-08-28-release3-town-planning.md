# Release 3 Town Planning Tracker implementation plan

## Scope and production boundary

- Preserve the canonical `v1.html` Town Planning Tracker inside the existing shell.
- Replace its demo-only actions with authenticated, tenant-scoped PHP 8.3 APIs backed by MariaDB.
- Cover the canonical application lifecycle and registers: applications, documents, deadlines, participation, conditions, hearings/appeals, municipality profiles, fees, messages, and audit history.
- Treat municipal submission as a recorded human action. Never claim that Architex submitted to, paid, notified, or received approval from an external authority.

## Delivery sequence

- [x] Add failing integration tests for lifecycle validation, tenant isolation, stale-write protection, human-confirmed submission, immutable issued snapshots, and project-record/audit creation.
- [x] Add failing authenticated HTTP tests for CSRF, application creation, related-register entries, submission, listing, and cross-tenant denial.
- [x] Add a forward-only MariaDB migration for planning applications and related evidence registers.
- [x] Implement the planning domain service, exception, controller, routes, and kernel wiring.
- [x] Add a project/standalone governed workspace around the canonical tracker with live forms, portfolio summaries, history, and evidence exports.
- [x] Run focused tests, syntax checks, migration checks, and regressions.
- [x] Exercise the complete flow against the live MariaDB database through persistent Chrome DevTools, including reload persistence and responsive layout.
- [x] Record exact local evidence and remaining external release gates.
