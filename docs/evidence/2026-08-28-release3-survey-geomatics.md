# Release 3 survey and geomatics evidence

## Scope and sources

The canonical `v1.html` remains unchanged. **Survey & Geomatics** retains its supplied Datum UI/scaffold and now mounts a project-aware governed survey register. Coordinates, control points, deliverables, cadastral references, and fees are human-entered evidence. The implementation does not claim official coordinates, cadastral validity, Surveyor-General examination, approval, or registration.

Primary official source pointers used as external references:

- [Geomatics Profession Act 19 of 2013](https://www.gov.za/documents/geomatics-profession-act)
- [South African Geomatics Council](https://sagc.org.za/)
- [Land Survey Act 8 of 1997](https://csg.dlrrd.gov.za/Act8Of1997.pdf)
- [Chief Surveyor-General prescribed fees](https://csg.dlrrd.gov.za/fees.htm)
- [Surveyors-General offices notice](https://www.gov.za/documents/notices/land-survey-act-offices-chief-surveyor-general-surveyors-general-and-national)

## Implemented controls

- Forward-only migration `0027_survey_geomatics.sql` creates tenant- and project-scoped survey jobs, exact decimal observations/control points, deliverables, separated fees, and immutable issue snapshots.
- Authenticated, permission-checked, CSRF-protected PHP routes support create, list/read, observations, deliverables, fees, issue, and JSON/CSV/HTML exports.
- Every draft mutation uses an optimistic version; stale writes fail closed. Issued jobs are immutable.
- Issue requires two verified observations, one complete deliverable, persisted private/statutory fee separation, explicit human confirmation, an active `land_surveyor` project responsibility, a matching `land_surveyor` professional profile, and a current independently reviewed professional-registration record.
- The immutable issue creates an issued project record and audit event. Snapshot flags explicitly state that official coordinates and Surveyor-General approval are not claimed.
- Project bootstrap now exposes the authenticated participant's effective project discipline. The production shell consequently shows the Land Surveyor lens and its role-relevant tool instead of inheriting the project's architect lead role.

## Executed verification

- PHP 8.3 syntax checks passed for survey service/controller/routes/tests and the project-role integration; `node --check app/public/assets/js/shell.js`, `php -l app/public/app.php`, and `git diff --check` passed.
- Disposable MariaDB service suite: **2 tests, 13 assertions**, covering exact evidence, fee arithmetic, confirmation, professional authority, immutable SHA, stale writes, and tenant isolation.
- Disposable MariaDB HTTP workflow: **1 test, 13 assertions**, covering CSRF rejection, authenticated persistence, two observations, deliverable, fee split, professional issue, list/read, and checksum-bearing export.
- Participant-role regression: **1 test, 9 assertions**, proving an active project participant receives their own discipline as `effectiveRole`.
- Live database status: **27 applied / 0 pending**.

## Chrome DevTools MCP proof

Chrome remained open throughout. A separate isolated browser context authenticated the explicitly labelled local fixture `survey-browser-evidence@example.test`. Its local evidence records contain a `land_surveyor` professional profile, reviewed SAGC registration `GPrLS-DEVTOOLS-01`, active organisation membership, and active `land_surveyor` assignment to project `304dc7da987382b3861b5a80fbe27470`.

After the participant-role fix, Chrome rendered **Land Surveyor view** and exposed **Survey & Geomatics** through normal role-filtered navigation. The UI created job `6a15f939f195a5ecccf4bc4879b0ea48`, `SUR-2026-DEVTOOLS-01`, and persisted:

- `CP01`, control point, `1000.1000 / 2000.2000 / 125.4000`, verified, reference `FIELD-DEVTOOLS-01`;
- `CP02`, benchmark, `1100.5000 / 2100.7000 / 126.0000`, verified, reference `FIELD-DEVTOOLS-02`;
- complete `topographical_plan`, `Topographical Plan P01`, reference `TOPO-DEVTOOLS-P01`;
- private professional fee **R 5,570.00**, statutory fee **R 450.00**, total **R 6,020.00**.

In the persistent owner/architect Chrome context, submitting the exact confirmed issue through the same UI produced HTTP **403** on `/survey-jobs/6a15f939f195a5ecccf4bc4879b0ea48/issue`, proving organisation ownership does not confer land-surveyor authority. The reviewed land surveyor then issued the same record successfully:

- status `issued`, version `6`;
- immutable SHA-256 `b843786318ea7dda8d0943c0ac13abff9a8a6f260be5cca8a6757e68cd6ff3c8`;
- project record `c5ab8767bd9fd43bf1eb1d4d51f93854`;
- `surveyorGeneralApprovalClaimed=false` and no official-coordinate claim.

MariaDB contains six ordered audit events: job created, two observations created, deliverable created, fees estimated, and job issued. A hard cache-bypassing reload loaded the same issued version, evidence rows, fee totals, and full immutable SHA from MariaDB.

Chrome fetched all three exports and independently recomputed each SHA-256:

| Format | Status | Bytes | SHA-256 header and browser digest |
| --- | ---: | ---: | --- |
| JSON | 200 | 7,429 | `e61e2f63f07229681d74d3a29cf6936c6072a6ad3483432e9b94bc1836446d67` |
| CSV | 200 | 345 | `321802638f0616c173a2ff3e4bba4d4fbb49824cd7b7ef3de0d2e24b5741e426` |
| HTML | 200 | 782 | `e67b829adb6fda9fe82572f530e921582d589e9c323f95c6d98e56bdfeb55165` |

At 1440x900, the document, central workspace, and survey workspace each had zero horizontal overflow. At 500x844, the document, central workspace, survey workspace, active-job block, and summary card also each had zero horizontal overflow; no descendant in the survey workspace overflowed. Chrome was restored to 1440x900 and reported no console warnings, errors, or issues.

## Release boundary

This certifies the bounded local PHP 8.3 + MariaDB implementation only. Shared-host FTP staging, staging smoke tests, backup rehearsal, and production promotion remain later staged-release gates.
