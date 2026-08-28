# Release 3 Engineering Calculation Hub — local production evidence

Date: 2026-08-28

Runtime: PHP 8.3.32, MariaDB 11.4, Chrome DevTools MCP

Scope: local implementation and certification only; shared-host staging and production promotion are not certified here.

## Delivered contract

- Preserved the canonical Engineer's Calculation Hub iframe, its 17 supplied calculator views, navigation, styling and original interaction surface.
- Replaced its demo-only save/review behavior with a tenant-scoped controlled-record panel backed by the existing toolbox run spine.
- Registered eight concrete versioned tools with distinct routes:
  - `beam_quick_check`
  - `rational_runoff_calculator`
  - `voltage_drop_calculator`
  - `concrete_quantity_calculator`
  - `masonry_quantity_calculator`
  - `finishes_quantity_calculator`
  - `tender_rate_build_up_calculator`
  - `labour_productivity_calculator`
- Each output records calculator and formula versions, calculation class, discipline, source/basis, assumptions, results, risk flags, next action, limitations, responsible role and explicit non-certification flags.
- Standalone and project assignment, history, immutable issue, superseding revision, ProjectRecord write-back, audit events, and JSON/CSV/HTML exports use the common production toolbox engine.
- An engineering issue requires all of the following:
  - exact human confirmation and responsibility statement;
  - confirmation role equal to the calculator responsibility role;
  - an organisation-scoped professional profile with a compatible profession;
  - a current independently reviewed `professional_registration` or contractor verification, as appropriate;
  - an issue/revision reason.
- The verification identifier, grade, kind, profile profession, registration reference and review timestamp are embedded in the issued output and therefore covered by the issue SHA-256 hash.
- Advisory saves remain possible without representing professional issue or certification.

## Formula boundaries and primary references

- Beam screening uses the simply-supported elastic UDL/midspan-point-load equations with every load, section property, modulus and user screening limit supplied explicitly. The public formula basis is corroborated by [NASA Technical Reports Server report 19880009564](https://ntrs.nasa.gov/api/citations/19880009564/downloads/19880009564.pdf).
- Rational runoff uses the SI Rational Method conversion and exposes its uniform-rainfall, time-of-concentration, complete-contribution and small-catchment assumptions. The primary basis is [FHWA Hydraulic Design Series 4, section 2.5](https://www.fhwa.dot.gov/engineering/hydraulics/pubs/08090/hds4_608.pdf).
- Voltage drop uses first-principles AC arithmetic with user-supplied resistance/reactance. It embeds no cable table, conductor selection, protection rule or proprietary standard value.
- Quantity, rate and productivity tools use transparent geometry/coverage/cost arithmetic with user-supplied product, commercial and productivity values.
- No result claims SANS compliance, structural adequacy, municipal approval, professional certification or suitability for construction.

## Automated verification

Final executed results:

- `EngineeringCalculatorServiceTest.php`: 12 tests, 106 assertions — pass.
- `EngineeringWorkspaceTest.php`: 1 test, 22 assertions — pass.
- `ToolboxEngineTest.php --filter testEngineeringIssueRequiresExactHumanProfessionalConfirmation`: 1 test, 9 assertions — pass against disposable MariaDB.
- `EngineeringToolboxHttpTest.php`: 1 test, 21 assertions — pass against disposable MariaDB.
- `ProductionComplianceToolsTest.php`: 1 test, 17 assertions — pass against disposable MariaDB, proving the engineering-only authority gate did not regress the shared XA/compliance issue path.
- PHP syntax checks passed for the new service, exception, registry, engine, controller, factory and canonical app shell.
- `node --check app/public/assets/js/shell.js`: pass.
- `git diff --check`: pass.
- Live local migration command: `Database migrations applied: 0; pending: 0; status: current` (24 existing migrations; this slice needed no schema change).

The disposable database used the explicitly permitted loopback namespace `architex_task13_facefeed_test`; the test harness dropped it after each case. The live local database was never used as a disposable target.

## Chrome DevTools browser-to-database proof

Authenticated browser owner: `browser-owner@example.test`

Organisation: `9775135843504f4eb3f9656493770756`

Project: `304dc7da987382b3861b5a80fbe27470` (`SpecForge DevTools Project`)

The persistent page 2 at `http://127.0.0.1:8790/` was kept open throughout.

1. Opened the canonical Engineer's Calculation Hub from the project datum.
2. Confirmed the canonical iframe title `Architex — Engineer's Calculation Hub`, its supplied beam view, and all eight production tool options in the appended governed panel.
3. Saved an advisory Rational Runoff calculation at 85 mm/h. Browser result: 212.5 L/s; MariaDB status: `completed`; no issue hash.
4. Issued and revised early evidence to exercise immutable history and export controls. The UI exposed JSON, CSV and HTML downloads and retained superseded hashes.
5. Created a civil-engineer profile and self-declared registration record through authenticated browser HTTP, then used a different active organisation owner to record the independent manual review through the production trust service. Verification: `480906d4265613e166ab1c633fd9b73a`, expires 2027-08-28.
6. Reissued the exact controlled calculation through the visible revision form under the strengthened authority gate:
   - run: `d0928c9c5287b5eb9f0c5c316975b45c`
   - rainfall intensity: 92 mm/h
   - peak flow: 230 L/s / 0.23 m3/s
   - status: `issued`
   - SHA-256: `de9d7bb7047fafb319ef69ba4de802a2424e968198335831389af99c04aa86c5`
   - supersedes: `f9f7204141f9e57c46e73ef7eb07dc83`
   - authority grade: `manually_reviewed`
   - authority profession: `civil_engineer`
7. Reloaded with cache disabled, reopened the hub, and fetched history through authenticated browser HTTP. The exact run, input, output, authority record, hash and supersession persisted.
8. Direct MariaDB readback matched the browser response and found three integrity-checked export rows (`json,csv,html`) for the current run.
9. The issue chain created ProjectRecords and the expected `tool_run.completed`, `tool_run.issued`, and `tool_run.superseded` audit events.
10. Desktop at 1440×900 had zero horizontal overflow. Mobile at 390×844 used one 254px input column, a block workspace layout and zero horizontal overflow; the desktop viewport was restored afterward.
11. A final clean navigation and canonical calculator tab change produced no JavaScript error or warning. Chrome reported accessibility issues inherited from the supplied embedded calculator (one unnamed field and unlabeled controls); the new governed form itself has named controls and visible labels.

## Local/staging boundary

This proves the local PHP/MariaDB/browser story. It does not prove the target shared host, FTP contents, target MariaDB credentials/migrations, TLS/domain behavior, cron freshness, writable private storage, encrypted backup and restore rehearsal, or staged promotion. Those remain release gates and must not be inferred from this report.
