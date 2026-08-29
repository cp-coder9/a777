# Release 12 local certification — FenCalc XA

Date: 2026-08-29
Scope: Pack 16, local staged-release gate
Runtime: PHP 8.3.32, MariaDB 11.4 at `127.0.0.1:43319`, persistent Chrome DevTools page at `http://127.0.0.1:8790/`

## Implemented scope

- Native governed FenCalc XA workspace for SANS 10400-XA:2021 advisory evidence; the supplied static prototype was treated as requirements material and was not made authoritative.
- Public-project fields for name, reference, address, climate zone 1–6, building type, 1–10 storeys, floor area, fixed 2021 edition and optional SANS 10400-N room checks.
- Repeatable fenestration, roof-light, door, curtain-wall and skylight elements with description, storey, room/use, orientation, frame, glazed/openable/wall area, U-value, SHGC and human-entered shading.
- Source-bound U-value, raw and adjusted SHGC, glazing-ratio, room lighting/ventilation and east/west-distribution checks.
- Per-element, per-room, per-storey and overall summaries, percentages, action findings and a print-specific advisory report.
- Review-required source version `implementation-seed-v0.2`; it is checksum protected and cannot be formally issued until a responsible professional verifies an official or licensed source.
- Existing thermal, forms and drawing compliance calculators remain backward compatible with the expanded project payload.

## Automated evidence

- Connected FenCalc scenario: `OK (1 test, 20 assertions)` in 03:03.403 against `architex_task13_facefeed_test`.
- Connected four-calculator regression: `OK (1 test, 49 assertions)` in 03:02.619 against the same explicitly disposable schema.
- Connected rule-registry scenario: `OK (1 test, 13 assertions)` in 03:03.992.
- Authenticated compliance HTTP scenario: `OK (1 test, 20 assertions)` in 03:02.327.
- Final frontend contract: `OK (1 test, 28 assertions)`.
- The connected test rejects storey 11, inconsistent room data, invalid values and unsupported element inputs fail closed.
- `node --check app/public/assets/js/shell.js` passed.

## MariaDB and HTTP evidence

- No schema migration was required; the staged schema remains current through migration `0036`.
- The authenticated browser installed source version `implementation-seed-v0.2` with SHA-256 `86450e44069467c7f5b2751a07c4c2dc1d423d9b42a6bdc851adcca4c751cb78`.
- The browser-created run `eae38497a5e2c213da2ab17d13fc16a7` persisted as completed for project `304dc7da987382b3861b5a80fbe27470`, with 8 elements, 53 m² total glazed area and 26.5% overall glazing.
- The advisory run has no issue hash, as expected for a review-required source; formal issuance remains unavailable.
- Authenticated history returned JSON, CSV and HTML export options. The downloaded HTML report returned HTTP 200 `text/html`, contained FenCalc evidence, measured 36,314 bytes and had SHA-256 `364f4523a69826bdd97c67bc32cdf7fe67446700b1b55ddf0932dbe5d4b44165`.

## Chrome DevTools evidence

- Persistent authenticated page ID 12 remains open on SANS 10400-XA / FenCalc XA for `browser-owner@example.test` and the SpecForge DevTools Project.
- Visible controls loaded an eight-element, two-storey example and ran it against source `implementation-seed-v0.2`; the rendered report showed element, room, storey, overall glazing and orientation findings plus the professional-signoff boundary.
- Exact 390 x 844 mobile emulation rendered eight element editors with `clientWidth=390`, `scrollWidth=390` and zero horizontal overflow.
- Mobile Lighthouse snapshot: Accessibility 100, Best Practices 100, SEO 100, Agentic Browsing 100; 32/32 audits passed.
- Restored 1440 x 900 desktop viewport rendered eight element editors with `clientWidth=1440`, `scrollWidth=1440` and zero horizontal overflow.
- Desktop Lighthouse snapshot: Accessibility 100, Best Practices 100, SEO 100, Agentic Browsing 100; 32/32 audits passed.
- Final console inspection found zero errors, warnings or issues. A shared datum zoom slider exposed by the full-shell mobile audit now has an accessible name.

## Preserved source and release boundaries

- Canonical `v1.html` was not edited. Its SHA-256 is rechecked before commit.
- Pack source material remains untracked and is not part of the deployable application commit.
- Seed values, shading, areas and room classifications remain human-entered advisory inputs; no geometry, municipal approval or professional sign-off is inferred.
- This is local staged-release certification only. No FTP upload, shared-host migration, target cron, remote backup rehearsal or production promotion is claimed.
