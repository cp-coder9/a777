# Release 3 Town Planning Tracker evidence — 2026-08-28

## Implemented production boundary

- The canonical `TOWN_PLANNING_PROTOTYPE.html` remains mounted unchanged in the Datum shell.
- The governed PHP 8.3/MariaDB workspace covers project and standalone applications plus document, deadline, participation, condition, hearing, appeal, municipality-profile, fee, and message registers.
- Submission requires explicit human confirmation, the recorded responsible role, at least one completed document, all required document/fee evidence complete, a reason, and an external authority reference.
- The immutable submission snapshot records SHA-256 evidence and a project record. Later register entries and sequential stage evidence are append-only activity and do not rewrite that submission snapshot.
- Architex explicitly reports `authorityAcceptanceClaimed=false` and `approvalClaimed=false`.

## Automated proof

- `TownPlanningServiceTest` and `TownPlanningHttpTest`: 3 tests, 40 assertions, green on disposable MariaDB (the final export-focused HTTP rerun was 1 test, 14 assertions).
- Covered CSRF rejection, tenant isolation, stale version rejection, incomplete evidence rejection, human confirmation, responsible-role checks, exact snapshot hash, project-record/audit persistence, post-submission evidence, and sequential stage progression.
- PHP syntax, `node --check app/public/assets/js/shell.js`, and `git diff --check`: green.
- Live migration result: 25 applied, 0 pending.

## Persistent Chrome DevTools proof

- Authenticated page: `http://127.0.0.1:8790/`, organisation `9775135843504f4eb3f9656493770756`, project `304dc7da987382b3861b5a80fbe27470`.
- Created `APP-2026-DEVTOOLS-01`, application ID `f0f6f472a99ae427b7c28c198541732a`.
- Recorded completed document `Signed rezoning application pack P01`.
- Recorded external handoff `TSH-DEVTOOLS-2026-01`; immutable SHA-256 `535ba9bd225681cdf58d6117d9fb0fee65c3ae2b532a585d0a671f297090b72b`; project record `fa39679e48f90f56519c9dbfd57c919c`.
- Added a later received municipality acknowledgement and advanced from submission to validation with reference `TSH-VALIDATION-DEVTOOLS-01`; version advanced to 5 while the submission SHA remained unchanged.
- Hard reload rehydrated 2 evidence entries and 5 lifecycle events from MariaDB. Browser authenticated GET returned 200 and the exact values above.
- Desktop 1440x900: body overflow 0. Mobile 390x844: block layout, 254 px field width, body overflow 0. Desktop was restored.
- Authenticated JSON, CSV, and HTML evidence exports are available with `X-Content-Sha256` and private/no-store delivery controls.
- DevTools console had no JavaScript error or warning. One canonical embedded form-field accessibility issue remains visible and predates the governed workspace.

## Release boundary

- This is local implementation and browser/database certification, not shared-hosting staging certification.
- FTP upload, control-panel environment values, staging migration/preflight, TLS, host mail configuration, and production promotion remain later release gates.
