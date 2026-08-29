# Release 16 — Drawing Compliance Intelligence

Date: 2026-08-29

Scope: Pack 20, `architex-sans-codified-toolbox-integration-brief-2026-06-11-RevF`

Runtime: PHP 8.3.32, MariaDB 11.4 at `127.0.0.1:43319`, persistent Chrome DevTools page 12 at `http://127.0.0.1:8790/`

## Delivered boundary

- One authenticated SA Council Drawing Compliance Navigator supports standalone and project-aware use.
- The source registry remains explicitly `illustrative_poc_until_verified`, version `1.6.0-RevF`; production reliance is false until licensed or official material is verified.
- Deterministic layers cover Part K boundary-wall demo rows, occupancy candidates, drawing-package completeness, internal cross-reference integrity, SPLUMA/land-use triggers and missing evidence.
- `null`, empty and absent inputs fail closed as `needs_input`; no dimension or approval is inferred.
- Saved reviews are immutable tenant-scoped MariaDB snapshots with SHA-256 and pending professional review.
- Project saves create a pending ProjectRecord, municipal-readiness workflow blocker and role-assigned inbox item. They do not change professional or municipal approval state.

## Automated evidence

- Final focused unit/frontend gate: `OK (4 tests, 48 assertions)`; the Lighthouse remediation frontend-only gate was `OK (1 test, 28 assertions)`.
- Connected disposable MariaDB gate: `OK (3 tests, 32 assertions)` in `10:05.373` against only `architex_task13_facefeed_test`.
- Connected coverage includes immutable persistence, tenant isolation, explicit project write-backs, source/checklist/save/read/export HTTP routes, export SHA-256 and toolbox registration.
- JavaScript syntax, PHP syntax and `git diff --check` passed.
- Development migration applied migrations `0042` and `0043`: 2 applied, 0 pending, current.

## Persistent Chrome evidence

Authenticated identity: `browser-owner@example.test`

Organisation: `9775135843504f4eb3f9656493770756`

Project: `304dc7da987382b3861b5a80fbe27470` (`SpecForge DevTools Project`)

Standalone review `0093c91c60ef9860481084818140cdd9`:

- source badge loaded `illustrative_poc_until_verified · 1.6.0-RevF`;
- A2 occupancy candidate and population 220;
- Part K result `watch`;
- missing drawing types and one unresolved cross-reference remained visible;
- place-of-worship and building-work triggers routed to the SPLUMA/land-use screen;
- snapshot SHA-256 `07afcd48096cce09dd5e8a379f557606072270443703389e2b14bc98072c8535`.

Project review `fd750c3cd6016214dca0f38a230a5a7a`:

- visible project entry prefilled the selected project and responsible role `architect`;
- snapshot SHA-256 `4579012b9dc5e4a8901fba8a290d3396520329f1a104e3ed5c46eef51648ca44`;
- direct database evidence found one ProjectRecord, one workflow event, one inbox item, and audit events for both browser-created reviews;
- professional review status remained `pending` and overall status remained `needs_input`.

Responsive geometry at 390 × 844:

- viewport and document width: 390 px;
- workspace/form/grid column width: 292 px;
- horizontal overflow: 0 px.

Chrome DevTools Lighthouse snapshot after remediation:

- Accessibility 100;
- Best Practices 100;
- SEO 100;
- Agentic Browsing 100;
- 30 passed, 0 failed;
- console warnings/errors: 0;
- session, projects, inbox, tools and drawing-compliance source requests: HTTP 200.

Screenshots:

- `docs/evidence/screenshots/release16-drawing-compliance-desktop.png`
- `docs/evidence/screenshots/release16-drawing-compliance-mobile.png`

## Source integrity and release boundary

- Canonical `v1.html` SHA-256: `7DBFAFDCFA52644FD32FB7CB7D73891B78593CA0287C089240DB4439E0FA97BF`.
- `v1.html` and `packs/` remain untouched, untracked and excluded from staging.
- This is local development and staged-release evidence. It is not remote shared-hosting, FTP, target-MariaDB, SMTP or production-domain certification.
