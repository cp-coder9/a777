# Release 3 refuse area calculator evidence

## Scope and source boundary

The canonical `v1.html` remains unchanged. **Refuse Area Calculator** retains its supplied Datum UI/scaffold and now mounts a governed PHP 8.3 + MariaDB workspace for waste-generation volume, bin allocation, floor-area allowance, facilities, evidence history, and report export.

The implementation is an advisory pre-check. It does not claim municipal approval, collection-service confirmation, legal certification, or statutory compliance. Rates, bin specifications, collection intervals, contingency, and circulation allowances are versioned human-controlled data. The supplied seed is explicitly `review_required` and can never be verified or formally issued.

Primary official source pointers used to define the reliance boundary and evidence fields:

- [National Environmental Management: Waste Act 59 of 2008](https://www.gov.za/documents/national-environmental-management-waste-act)
- [National norms and standards for storage of waste](https://www.gov.za/documents/national-environmental-management-waste-act-national-norms-and-standards-storage-waste-0)
- [DFFE compendium of the Waste Act](https://www.dffe.gov.za/sites/default/files/docs/compandiumofnemwa_act59of2008.pdf)
- [City of Cape Town guidelines for refuse storage areas](https://resource.capetown.gov.za/documentcentre/Documents/Procedures%2C%20guidelines%20and%20regulations/General%20-%20Guidelines%20for%20Refuse%20Storage%20Areas.pdf)
- [City of Tshwane promulgated by-laws](https://www.tshwane.gov.za/?page_id=7469)

No numerical seed rate is represented as a current official municipal schedule. Production reliance requires an exact municipality-specific transcription, source reference, effective date, checksum, organisation-governor verification, and responsible professional review.

## Implemented controls

- A distinct `municipal_refuse` rule schema validates source-controlled generation rates, bin capacities/footprints, collection interval, contingency, circulation allowance, and required facilities.
- Deterministic calculation records base and design litres, required bin count, net bin footprint, circulation-adjusted required area, proposed-area surplus/shortfall, facility verdicts, assumptions, and action cards.
- Invalid use classes, bins, incomplete rule payloads, missing/non-boolean facilities, and collection intervals outside 1–7 days fail closed.
- The production Tool Registry exposes `refuse_area_calculator`; ToolRun persistence provides assignment, history, immutable issue snapshots, SHA-256 audit hashes, JSON/CSV/HTML exports, ProjectRecord write-back, inbox actions for findings, and audit events.
- Formal issue requires a verified non-seed source, explicit issue reason, exact professional confirmation, an `architect` or `civil_engineer` professional profile, and a current independently reviewed professional-registration record.
- Municipal Readiness now reads refuse ToolRuns and includes `refuse_precheck` in new requirement-matrix seeds. Only an issued run with a verified source and preserved professional authority can satisfy that evidence check; advisory runs remain review-required.
- The project and standalone Refuse workspace exposes source installation/creation/verification, source-derived use and bin options, project assignment, facilities, professional confirmation, results, history, and evidence exports without replacing the supplied shell.

## Automated verification

- Core refuse calculation: **1 test, 17 assertions**.
- Authenticated refuse HTTP workflow (within the refreshed governed-source/readiness set): includes **20 assertions**, adding wrong-profession denial to the earlier 18-assertion pass.
- Refuse and existing compliance frontend contracts: **2 tests, 37 assertions**. This includes Engineer-role discovery and Design/Comply stage reachability for the civil-engineer issue path.
- Refreshed HTTP, municipal seed-v0.2, and readiness handoff set: **3 tests, 43 assertions**.
- Municipal Readiness missing-to-issued-refuse transition: **1 test, 9 assertions**.
- PHP syntax checks, `node --check app/public/assets/js/shell.js`, and `git diff --check`: passed.

An initial six-case cross-module run completed five cases successfully; its only failure was the already-running PHPUnit process retaining the pre-bump `implementation-seed-v0.1` expectation after the implementation had correctly moved the changed payload to v0.2. The refreshed affected set above passed, and the strengthened readiness transition test then passed independently.

The disposable HTTP test proves seed governance, candidate verification, definition discovery, rejected unconfirmed issue, verified architect authority, immutable issue, project record, two inbox action cards, three audit events, and all three export formats.

## Persistent Chrome DevTools MCP proof

Chrome page 2 remained open throughout at `http://127.0.0.1:8790/`. The authenticated owner opened **Refuse Area Calculator** inside project `304dc7da987382b3861b5a80fbe27470` (`SpecForge DevTools Project`) without replacing the canonical scaffold.

The browser first installed the unverified seed and created project-linked advisory run `16645b3c8cb76f4ca645b449111ea914`. Formal issue remained disabled. With 12 dwellings, 120 L/dwelling/day, seven days, 10% contingency, 1,100 L bins, a 10 m2 proposed area, and drainage not recorded, the visible result was:

- base volume: 10,080 L;
- design volume: 11,088 L;
- required bins: 11;
- required area: 12.38 m2;
- action: increase area by at least 2.38 m2;
- action: confirm and record a compliant drainage arrangement;
- status `completed`, source `review_required`, no issue hash.

For successful-path browser evidence, the owner created and separately verified `devtools-reviewed-local-refuse-1`. Its source text prominently states **LOCAL BROWSER EVIDENCE ONLY**, and that the rates remain local test assumptions rather than a claimed municipal schedule. This local evidence version is not a production source approval.

With 13 m2 proposed area and all five facilities recorded, Chrome issued run `05c13b2e7ac7f01c4093eaeb1b35d63f`:

- verdict `pass`, 11 bins, 12.38 m2 required area, 0 action findings;
- source status `verified`, `issueEligible=true`, `municipalApproval=false`;
- professional authority: `civil_engineer`, independently reviewed local registration `LOCAL-DEVTOOLS-CIVIL-01`;
- immutable SHA-256 `1435694ee4e7fa73de49839c24290fa3f268e23c98bd6870cb368c72dd5ed8c0`.

Chrome fetched the run and export metadata through the authenticated API:

| Format | Bytes | SHA-256 |
| --- | ---: | --- |
| JSON | 5,929 | `c75c4bc9b7203528d938a2a4a0d8928adbd4e0ab54c8a731aad116bac7e11acf` |
| CSV | 4,183 | `5758afaee30922ee2cb563bff6a37c9b307d785c4ad445943cc76bdc0040b216` |
| HTML | 7,673 | `4a0e1d29bbe06f26aeee38ffb6b38e7537a04d9ba34faa2251675ea0d5942fc4` |

Direct MariaDB reads confirmed the seed and verified source checksums, issued ToolRun, civil-professional authority, issued ProjectRecord, six export rows across the two runs, and the ordered `compliance_rule.created`, `compliance_rule.verified`, and `tool_run.issued` audit events.

A hard cache-bypassing reload restored both run-history rows and the issued hash from MariaDB. The final navigation review also confirmed Refuse is discoverable by the Engineer role and is present in both Design and Comply stage maps. Chrome recorded only HTTP 200/201 responses for the exercised session and reported no console warnings, errors, or issues. At 500x844, document, main, and Refuse workspace horizontal overflow were all zero; the visible responsive workspace was inspected, and Chrome was restored to 1440x900.

## Release boundary

This certifies the bounded local implementation only. Shared-host FTP staging, staging MariaDB configuration/migrations, real municipality-specific source approval, SMTP configuration, backup/rollback rehearsal, staging smoke tests, and production promotion remain separate staged-release gates.
