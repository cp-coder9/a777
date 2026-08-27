# Release 2 appointment and kickoff evidence

Date: 2026-08-28
Scope: local source runtime only; this is not staging or shared-hosting certification.

## Implemented

- An accepted proposal is copied into an immutable appointment snapshot with its exact proposal SHA-256, client acceptance, scope, terms, fee snapshot, client, professional and company identity.
- Appointment creation atomically links the existing project, creates a Project Passport baseline record, seven readiness gates, a kickoff checklist, three inception/municipal-readiness tasks, six controlled document placeholders, inbox items, recommendations and audit evidence.
- Client acceptance remains distinct from the responsible professional's explicit confirmation.
- Kickoff remains blocked until proposal acceptance, client acceptance, professional confirmation, project name, municipality, property/erf reference, and scope/terms/fee snapshot gates all pass.
- The Appointment Letter Draft remains `requires_human_approval` until an owner/admin explicitly approves the exact appointment revision with a reason.
- Formal issue creates an issued `professional_appointment` project record and advances the project to the appointment phase.
- A newly accepted proposal version creates appointment revision 2 through a dedicated revision endpoint. The earlier appointment and proposal snapshot remain unchanged.
- No real PDF or e-signature provider is claimed; pack-required outputs remain explicit placeholders or ready-to-generate records.

## Chrome DevTools MCP proof

Runtime: `http://127.0.0.1:8790/`, PHP 8.3.32, MariaDB on the task-owned local instance.

Visible owner flow:

1. Opened the canonical Professional Fee Proposal workspace in the persistent owner tab.
2. Selected the previously accepted proposal with SHA-256 `6f7107f961e10cd188a9d6c9fd03ba577b7afeabf39fc8b8f534c9d4589ea5ee`.
3. Confirmed City of Tshwane, Gauteng, Erf 777, zoning context, SACAP and registration details.
4. Created appointment revision 1; Chrome displayed `blocked kickoff · 6/7 gates passed` and kept professional confirmation open.
5. Explicitly accepted professional responsibility; Chrome displayed `ready kickoff · 7/7 gates passed` with all seven individual gates green.
6. Confirmed the six controlled outputs, including `Appointment Letter Draft · requires human approval`.
7. Explicitly approved formal issue; Chrome then displayed `Appointment Letter Draft · issued` while retaining the same exact proposal hash.
8. At 390 x 844 the workspace remained single-column and reported `scrollWidth: 390` with no horizontal overflow. Long SHA-256 values were made wrap-safe after visual inspection.
9. Desktop was restored to 1440 x 900. Chrome reported no console errors or warnings and both owner and isolated recipient tabs remain open.

## Automated proof

- Appointment service and HTTP integration: 2 tests, 29 assertions, passing against disposable MariaDB.
- The service test covers immutable proposal hashes, seven gates, generated checklist/tasks/documents/recommendations, professional confirmation, human-approval fail-closed behavior, formal issue, project phase, issued project record, and appointment revision 2 without mutating revision 1.
- The HTTP test covers authenticated creation, professional confirmation, issue, and project-scoped kickoff retrieval.
- Appointment/proposal frontend regressions: 2 tests, 27 assertions, passing.
- Full unit suite: 112 tests, 315 assertions, passing.
- PHP syntax, JavaScript syntax, and `git diff --check` passed (line-ending notices only).
- Live MariaDB migration status: 14 applied, 0 pending.

## Remaining gate

Staged shared-hosting upload, remote MariaDB migration, HTTPS/session verification, outbound-email integration, and remote browser evidence remain intentionally unclaimed.
