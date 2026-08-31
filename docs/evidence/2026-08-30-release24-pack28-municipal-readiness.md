# Release 24 / Pack 28 municipal readiness refinement evidence

## Certified scope

- Functional source commit: `e7a36b4fb55cf69dcd1f83d9dfab24288270dafa`.
- Pack read completely: current v0.2 plus the bundled legacy v0.1.
- Runtime: PHP 8.3.32, MariaDB 11.4 on the reserved loopback port 43320, authenticated V1 workspace on `http://127.0.0.1:8809/app`.
- Boundary: local staged-release certification only. No FTP upload, remote shared-host migration, production secrets, remote smoke test or promotion was performed.

## Implemented contract

- Project municipal profiles cover municipality, application type, property and ownership inputs with versioned canonical hashes.
- Refined assessments materialise blockers, warnings, advisories and required professional routes without claiming municipal approval or statutory compliance.
- Workflow transitions are fail closed across draft, in-review, blocked, ready, issued and superseded states.
- Professional assignment and sign-off enforce lead-professional and architect gates, including candidate-supervisor requirements.
- Issued Architex-backed submission packs contain canonical document/drawing indexes, immutable manifest snapshots and SHA-256 hashes; subsequent evidence requires a new assessment and pack revision.
- Pack issue writes the project record, inbox item and audit event transactionally.

## Automated verification

- Complete connected suite: **328 tests, 3311 assertions, 0 failures, 0 errors, 0 skips**, completed in 18:53.365 after rebuilding all 54 migrations on the guarded disposable database `architex_task24_a7770001_test`.
- Final Pack 28 focused rerun on the exact source: **9 tests, 114 assertions**, completed in 03:29.814 against the guarded disposable MariaDB target.
- Earlier focused layers remained green: municipal unit and schema 5 tests/40 assertions, connected lifecycle 2 tests/20 assertions, authenticated HTTP/service 3 tests/55 assertions, frontend static contract 8 tests/114 assertions, and action binding 1 test/34 assertions.
- Extracted artifact syntax: **288 PHP files, 0 lint failures**; `public/assets/js/shell.js` passed `node --check`.
- `git diff --check` passed.

## Authenticated browser and database proof

The persistent Chrome DevTools MCP page remained authenticated as `release23-owner@example.test` in organisation `33a3dbb784f49db6e8b734377d1790a6`, project `37b1ec2404967efa5b0a6d0803c744cf`.

The real workspace completed this lifecycle:

1. Saved Johannesburg building-plan profile v1 for Erf QC23 with ownership confirmed.
2. Installed the review-required rule seed and verified drawing matrix `release24-browser-verified-1` against its exact source boundary.
3. Created assessment `9a7731e77e5c899db5db1f8c3cd78be2`: 100%, low complexity, zero open findings and ready for professional review.
4. Recorded professional review, assigned and signed both required routes (`b620f2c99f088104f9de9cb68d0f99da` and `578710cc53ded28f15a32160ddbd7038`).
5. Issued immutable pack `b6a9c7fe0c1b4fc9da9d87cd52ab28af` v1 for Release 24 Principal Architect / PrArch LOCAL-2401.

Retained DevTools network evidence reports assessment creation `201`, professional approval `200`, both route assignments `200`, both sign-offs `200`, and pack issue `201`. Final console inspection contained no errors or warnings.

MariaDB contained one profile, one assessment, zero findings, two signed routes, one pack, one pack project record, one pack inbox item and one pack audit event. The stored manifest hash `1f8de63fe486cb7d1043fe474265995e3cf82e1c5760bc1e345f5534f9763a95` exactly matched `SHA2(manifest_snapshot, 256)`; its indexes contained five controlled documents and four registered drawings.

## Responsive and accessibility evidence

- Mobile 390 x 844: viewport, document and body widths all 390px; no horizontal overflow and no hidden workspace elements unexpectedly visible.
- Desktop 1440 x 1000: document width 1440px with no horizontal overflow.
- Lighthouse authenticated snapshot: Accessibility 100, Best Practices 100, SEO 100 and Agentic Browsing 100. All 30 automatically scored audits passed; 11 manual audits were not scored.
- Screenshots: `release24-municipal-readiness-mobile.png` and `release24-municipal-readiness-desktop.png`.
- Lighthouse report: `release24-lighthouse/report.html` and `release24-lighthouse/report.json`.

## Staged shared-hosting artifact

- Directory: `dist/architex-release-staged-20260830-e7a36b4`.
- ZIP: `dist/architex-release-staged-20260830-e7a36b4.zip`.
- ZIP size: **1,203,225 bytes**.
- ZIP SHA-256: `717d46951fca7e5979e95cab6baa0afb22b19fed1cacc665fa38141d1c6cb84f`.
- Release verifier: **364 payload files passed** in the built directory and again in fresh extraction `dist/ftp-redownload-verification-e7a36b4`.
- Directory counts are 365 files including `release-manifest.json` in both built and freshly extracted copies.
- The artifact contains migration `0054_municipal_readiness_refinement.sql`, PHP application/runtime code and production dependencies. Local secrets and development-only test/evidence sources are excluded by the release builder.

## Remote boundary

This evidence certifies a local staged artifact suitable for the approved FTP workflow. Shared-host upload, remote MariaDB backup/rehearsal, remote migration execution, remote authenticated smoke tests and staged promotion remain pending and must not be represented as complete.
