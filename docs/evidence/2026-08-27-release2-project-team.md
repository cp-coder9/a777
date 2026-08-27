# Release 2 project-team invitations — local evidence

Date: 2026-08-27 (Africa/Johannesburg)

## Implemented contract

- Migration `0009_project_teams.sql` stores tenant/project scope, discipline, project role, verification snapshot and invited/active/declined/removed lifecycle timestamps without deleting assignment history.
- Only active project organisation owners, admins or members can list, invite or remove team records.
- Candidates must have a current `manually_reviewed` or `externally_verified` professional-registration record when invited and again when accepting.
- A candidate may see and accept only invitations addressed to their authenticated user ID. Acceptance does not create an organisation membership.
- Invitation, acceptance and removal create MariaDB audit events and role-routed inbox items.
- The canonical V1 shell now provides the project Team Workspace, verified-directory selector, incoming invitation queue, current team list and removal workflow.

## Executed tests

Against disposable MariaDB `architex_task13_facefeed_test` on loopback port 43319:

```text
PHP 8.3.32 / PHPUnit 12.5.34
7 tests, 40 assertions
OK
Time: 03:06.995
```

The run combined professional-directory projection, team service and team HTTP tests. It covers verification eligibility, cross-tenant denial, invitee-only acceptance, CSRF, identity-only invitation access, active list projection and preserved removal history.

After tightening management from all organisation members to owner/admin or the project creator and making re-invitations append-only, the final focused team suite was rerun: 3 tests, 21 assertions, OK in 02:11.878. This explicitly proves that an unrelated ordinary member cannot manage the project team and that a removed row retains its reason when a replacement invitation is created.

## Live MariaDB and Chrome DevTools proof

- Source runtime: `http://127.0.0.1:8790/`; task-owned MariaDB: `127.0.0.1:43319`, database `architex_task7_live`.
- Migration result: 1 applied, 0 pending, schema current at nine migrations.
- Owner organisation `9775135843504f4eb3f9656493770756`; project `af189b7f799637ca7918b1a3d6858292`.
- Local browser fixture professional `2af5f29ba25e11f1947574d4353763c3` has only their own organisation membership and an independently manually reviewed ECSA record.
- Owner browser context created invitation `4495bfff1318a99abebb14be5afc2233` with HTTP 201.
- Isolated invitee Chrome context authenticated separately, listed exactly that invitation with HTTP 200, accepted it with HTTP 200 and then returned an empty pending queue.
- Owner context listed the structural assignment as active and received `project_team_invitation_accepted` in the live inbox.
- The UI itself was then used to create and accept a second `civil` assignment across the two isolated contexts; owner refresh showed both assignments active.
- Chrome console after the production-shell reload had no errors or warnings. The Team Workspace was inspected at 1440x900 and 390x844; a selector overflow found on the first mobile pass was fixed and the second render fit the card.
- The source owner page remains open in Chrome at the local URL; a separate isolated invitee page remains available for continued invitation testing.

## Boundary

This is local implementation and browser certification of the invitation/assignment slice. It is not all of Release 2 and is not staged-host certification. Duties/responsibility matrices, guided-brief UI/evidence attachments, calculators, proposals, terms, acceptance, appointment and kickoff remain subsequent plan items. Remote MariaDB, FTP artifact re-verification, SMTP, TLS/domain/docroot and control-panel cron remain external promotion gates.
