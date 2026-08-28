# Release 3 environmental, heritage, and EIA evidence

## Scope and sources

The canonical `v1.html` remains unchanged. Both **EIA Workspace** and **Environmental & Heritage** retain their supplied Datum UI/scaffold and now mount the same project-aware governed evidence panel. The implementation records external evidence and professional route determinations; it does not recreate a government screening tool or claim authority submission, acceptance, permits, authorisation, or approval.

Source boundaries were checked against primary official material:

- [DFFE Environmental Geographical Information Systems](https://www.dffe.gov.za/egis) describes the national web-based environmental screening tool and its screening report.
- [DFFE screening-tool Gazette notice](https://www.dffe.gov.za/sites/default/files/legislations/nema107of1998_web-based-environmental-screeningtool_gn42561.pdf) is retained as the official legal-source pointer rather than copied into application logic.
- [SAHRA Development Applications Unit](https://www.sahra.org.za/development-applications-unit/) and [SAHRA minimum standards for heritage reports](https://www.sahra.org.za/minimum-standards-for-heritage-reports-for-public-review/) define the external heritage submission/review context represented only as evidence references.

## Implemented controls

- Forward-only migration `0026_environmental_heritage_cases.sql` creates tenant- and project-scoped cases and evidence registers.
- Authenticated, permission-checked, CSRF-protected PHP routes support create, list, read, evidence, determination, and JSON/CSV/HTML exports.
- Evidence writes use optimistic versions; stale writes fail closed.
- A determination requires explicit human confirmation, the case's responsible discipline, an active matching project responsibility, and a current independently reviewed professional-registration record whose reviewer is not the subject.
- Environmental determinations require both a screening report and site-sensitivity verification. Heritage determinations require both an NID and heritage-impact assessment.
- Determination snapshots are SHA-256 protected, create an issued project record, and become immutable. All responses explicitly set `authorityDecisionClaimed` and `authorisationClaimed` to false.

## Executed verification

- PHP 8.3 syntax checks passed for service, controller, routes, and both focused tests; `node --check app/public/assets/js/shell.js` and `git diff --check` passed.
- Disposable MariaDB service test: `testEnvironmentalAndHeritageEvidenceRequiresProfessionalDetermination` passed with 13 assertions, including stale-write rejection, missing human confirmation, missing professional authority, snapshot hash, project record, and heritage evidence.
- Disposable MariaDB isolation test: `testTenantIsolationAndEvidenceGateFailClosed` passed with 2 assertions.
- Disposable MariaDB HTTP test passed with 12 assertions, including missing-CSRF rejection, authenticated persistence, authority-boundary flags, list/read persistence, and checksum-bearing export.
- Local live database migration advanced from 25 applied / 1 pending to **26 applied / 0 pending**.

## Chrome DevTools MCP proof

Chrome remained open throughout. In the existing owner session, EIA Workspace created case `764a3df66cc955ed522730dd3a5ae801`, `ENV-2026-DEVTOOLS-01`, then persisted:

- `screening_report`, reference `SCREEN-DEVTOOLS-01`, status `complete`;
- `site_sensitivity_verification`, reference `SSV-DEVTOOLS-01`, status `complete`.

The architect owner then attempted the exact human-confirmed `basic_assessment` determination. Chrome Network recorded HTTP **403** with `regulatory_determination_role_denied`, proving organisation ownership does not confer environmental professional authority.

A separate isolated Chrome context authenticated the explicitly labelled local evidence practitioner `env-browser-evidence@example.test`, whose fixture has a current independently reviewed `DEVTOOLS-EAP-01` registration and active `environmental_practitioner` project assignment. The same browser workflow issued:

- status `determined`, version `4`;
- route `basic_assessment`;
- immutable SHA-256 `7f9e26db71f6c875a272916a0b977385427830cd3b71f2b5a81f7d4757bebdf0`;
- project record `7ed98b0f12a0db219ab4462f9eeb5fdb`, module `environmental_heritage`, type `regulatory_determination`, status `issued`;
- audit events `regulatory.case.created` and `regulatory.case.determined`, both with `authorityDecisionClaimed=false`.

After an ignore-cache hard reload, opening the distinct **Environmental & Heritage** route loaded the same case, two evidence rows, route, and full snapshot hash from MariaDB.

Chrome fetched all three exports and independently recomputed each SHA-256:

| Format | Status | Bytes | SHA-256 header and computed digest |
| --- | ---: | ---: | --- |
| JSON | 200 | 5,033 | `2803005eedc64510dc13ce22a8c19e3bcccb23fbc392b42cc23ce04da00d0a33` |
| CSV | 200 | 539 | `9ec2b5e89a7153ceb7978b3b06584e92e75bccd17dfd6cd566cfbe3ada02e303` |
| HTML | 200 | 701 | `bc6c5ca6764940d0e6d905730b292462831dec9a4d223d91309b7f64736473a1` |

At 1440×900 the governed workspace had zero horizontal overflow. At the browser's 500×844 minimum viewport, the responsive block layout had zero horizontal overflow, a 387 px workspace, and a 349 px form input. The page was restored to 1440×900. Chrome reported no console warnings or errors.

## Release boundary

This certifies the bounded local PHP 8.3 + MariaDB implementation only. Shared-host FTP staging, staging smoke tests, backup rehearsal, and production promotion remain later staged-release gates.
