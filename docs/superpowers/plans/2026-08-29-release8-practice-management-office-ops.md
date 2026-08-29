# Release 8 — Practice Management & Professional Office Ops

## Objective

Implement Pack 12 as real PHP 8.3 and MariaDB functionality inside the existing `practice` workspace while preserving `v1.html` byte-for-byte.

## Production boundary

- Every record is organisation scoped and membership checked.
- Project-linked records are restricted to projects in the active organisation.
- Timesheets and invoice-readiness results are auditable; readiness never creates an invoice or moves money.
- Candidate work cannot be signed off by the candidate; owner/admin or an assigned registered professional must review it.
- Registration and PI dates are reminders only, never verification guarantees.
- Templates use immutable versions; a new version supersedes the prior editable version.

## Tasks

1. Add connected failing tests for pipeline, tasks, resourcing, time, supervision, invoice readiness, registration reminders, templates, tenant isolation and the production shell.
2. Add migration `0033_practice_management_office_ops.sql` with tenant/project/user foreign keys and query indexes.
3. Implement `PracticeManagementService`, HTTP controller/routes, validation, RBAC, ProjectRecords, inbox items, audits and recommendations.
4. Replace the `practice` scaffold with one responsive MariaDB-backed workspace and preserve shell-owned navigator/inspector context.
5. Run focused and aggregate PHP suites, syntax checks and migration against disposable MariaDB.
6. Apply the migration to the local live database, exercise real authenticated browser workflows through Chrome DevTools, verify database rows and run desktop/mobile accessibility and overflow gates.
7. Record local evidence and commit only implementation/evidence files; never stage `packs/` or `v1.html`.

## Staged-release exit

This release can be locally certified after all checks pass. FTP staging, real shared-host MariaDB validation, SMTP configuration, rollback rehearsal and production promotion remain separate external gates.
