# Release 8 local certification — Practice Management & Office Ops

Date: 2026-08-29  
Scope: Pack 12, local staged-release gate  
Runtime: PHP 8.3.32, MariaDB 11.4 at `127.0.0.1:43319`, persistent browser at `http://127.0.0.1:8790/`

## Implemented scope

- Tenant/project-scoped pipeline creation and explicit stage/outcome transitions with weighted forecasts.
- Assigned practice tasks with deadlines, SLA, priorities, assignee/governor-only state transitions, terminal-state immutability, ProjectRecords and audit evidence.
- Resource allocations with capacity comparison and over-allocation inbox routing.
- Billable/non-billable timesheets with author submission and owner/admin review gates.
- Candidate supervision logs with a separate registered-professional sign-off boundary and self-sign-off denial.
- Invoice-readiness snapshots derived from approved billable time; the workflow explicitly cannot create invoices or move money.
- Registration, CPD and PI reminders; members may record their own reminders while owners/admins may record another organisation member. No professional-body verification is claimed.
- Owner/admin-controlled, role-visible template creation and immutable revision chains with SHA-256 content evidence.
- MariaDB-backed workspace aggregation plus ProjectRecords, Inbox, audit events and review recommendations.

## Automated evidence

- Baseline Release 8 suite after migration 0033: `OK (4 tests, 51 assertions)` in 22:08.504. This included both Practice service tests, encrypted backup/restore rehearsal, and the frontend contract.
- Focused connected scenario after task-transition and authorization hardening: `OK (1 test, 35 assertions)` in 05:05.845.
- Final frontend workspace contract: `OK (1 test, 20 assertions)`.
- `php -l` passed for `PracticeManagementService.php`, `PracticeController.php`, and `PracticeRoutes.php`.
- `node --check app/public/assets/js/shell.js` passed.
- `git diff --check` passed; only Git's informational LF-to-CRLF warnings were emitted.

## MariaDB evidence

- Migration `0033_practice_management_office_ops.sql` was applied to the persistent local database with 1 applied, 0 pending, status current.
- Chrome-driven task transition persisted `Release 8 Browser Task` as `in_progress`, retained `completed_at IS NULL`, and appended `practice.task_transitioned`.
- Chrome-driven controlled template revision produced version 1 `superseded` and version 2 `active`; both stored 64-character SHA-256 values.
- Persistent local counts after browser proof: 7 Practice ProjectRecords, 5 Practice inbox items, 12 Practice audit events, including 1 task-transition audit event.
- The connected service scenario also proved unrelated-member task-transition denial, unrelated-member registration denial, member template-management denial, candidate self-sign-off denial, and cross-tenant project hiding.

## Chrome DevTools evidence

- Persistent authenticated page ID 12 remained open on Practice Management for project `304dc7da987382b3861b5a80fbe27470`.
- Rendered controls were observed for pipeline advance/won/lost/abandon, task start/block/complete/cancel, professional supervision sign-off, and controlled template revision.
- A real task transition was performed through the visible Start control and prompt; the refreshed workspace displayed `in progress`.
- Real HTTP template create/revise calls returned immutable versions 1 and 2 with 64-character checksums; the refreshed UI displayed v1 superseded and v2 active.
- Final console: zero errors, warnings or issues.
- Final Lighthouse snapshot, desktop: Accessibility 100, Best Practices 100, SEO 100, Agentic Browsing 100; 32/32 audits passed.
- Final Lighthouse snapshot, mobile: Accessibility 100, Best Practices 100, SEO 100, Agentic Browsing 100; 32/32 audits passed.

## Preserved source and boundaries

- Canonical `v1.html` was not edited. SHA-256 remained `7DBFAFDCFA52644FD32FB7CB7D73891B78593CA0287C089240DB4439E0FA97BF`.
- Pack source material remains untracked and is not part of the deployable application commit.
- This is local staged-release certification only. No FTP upload, shared-host migration, SMTP delivery, remote backup rehearsal or production promotion is claimed.
- SMTP remains intentionally fail-closed until real shared-host credentials are configured.
