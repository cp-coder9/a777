# Release 21 — Pack 25 Platform Completeness Evidence

## Certified source and FTP artifact

- Application source commit: `d3db3a07457ebe72cf97b1dee715897985c912b3` on `master`.
- Candidate: `dist/architex-release-staged-20260830-d3db3a0`.
- Build API: version `staged-20260830-d3db3a0`, PHP 8.3, `php-shared-hosting`, exact source commit, reviewable artifact.
- Manifest verification: passed, 348 release files.
- FTP ZIP: `dist/architex-release-staged-20260830-d3db3a0.zip`.
- ZIP bytes: `1142737`.
- ZIP SHA-256: `19A3E21C3D555F06891CDB7B3D941FE4158FFF891F9BC34002BC8BF8A94EF3C2`.
- Independent `Expand-Archive` extraction and `verify-release.php`: passed; 349 extracted files including release metadata. The temporary verification directory was removed after validation.

## Supplied pack coverage

All files in `25. architex-workflow-checklists` were treated as unimplemented requirements and read before implementation:

- `01-architex-pack-summary-qc-tracking-checklist.docx` and its Markdown source.
- `02-architex-platform-completeness-implementation-placeholder-pack.docx` and its Markdown source.
- `platform-completeness-placeholder-pack.json`.

The production catalogue contains all 12 master workstreams, 21 QC criteria, 10 build-comparison questions, 14 completeness sections and 95 checklist items. Supplied historical completion and PR language is retained only as historical context; it is never accepted as current evidence.

## Implemented production boundary

- Migration 0051 persists tenant-scoped workstreams, sections, checklist items, evidence, shared-file links, QC reviews, role assignments and immutable item/workstream sign-offs in MariaDB.
- Catalogue seeding is deterministic and idempotent. Runtime behavior does not depend on pack files being present on shared hosting.
- Server-side roles are `platform_super_admin`, `product_owner`, `developer`, `qa_reviewer` and `viewer`, and every capability still requires active organisation membership.
- Developer edits use optimistic expected versions. Stale writes return HTTP 409 and cannot silently overwrite another user's work.
- Evidence categories are allowlisted. FileManager links must reference an active `stored_files` record belonging to the same organisation and project; foreign files fail closed.
- QC is append-only. Accepted state requires passing QC plus an accountable owner/admin confirmation and reason. False confirmation returns HTTP 409. Sign-offs contain immutable SHA-256 snapshots.
- AI may summarise or draft only; it cannot change implementation state, QC outcomes or sign-offs.
- The V1-native `/admin/platform-completeness` control centre provides the full register, filters, summaries, role-aware actions, evidence, QC history and sign-off history without creating a second visual shell.
- Production pages now expose `data-app-mode="production"`; the demo sandbox does not probe demo-only endpoints outside demo mode.

## Automated verification

- Full non-DB suite: 307 tests, 1,240 assertions, 143 DB-dependent tests explicitly skipped, exit 0.
- Focused demo-sandbox and completeness frontend regression: 2 tests, 73 assertions, exit 0.
- Connected MariaDB Pack 25 suite: 1 test, 25 assertions, exit 0 after all 51 migrations.
- Connected coverage includes two-tenant isolation, exact/idempotent catalogue seeding, role enforcement, optimistic conflicts, evidence, FileManager cross-tenant rejection, QC, false-confirmation rejection and immutable item/workstream sign-offs.
- Persistent browser database: 51 applied migrations, 0 pending.
- PHP syntax, JavaScript syntax, release verification and `git diff --check`: passed.

## Persistent Chrome DevTools MCP certification

The exact candidate remains open at `http://127.0.0.1:8804/admin/platform-completeness` in isolated page 21.

- Owner authentication retained the direct deep link and rendered the platform-super-admin control surface.
- Hydrated UI counts: 95 checklist items, 14 sections, 12 workstreams, 1 accepted item/workstream, 1 passing QC review and 2 immutable sign-offs.
- Owner API exercise on the connected source runtime proved HTTP 200 updates, HTTP 409 stale-version rejection, HTTP 201 evidence/QC/sign-offs, and HTTP 409 when `humanConfirmed` was false.
- QA isolation rendered 95 items with QC-review capability only. Viewer isolation rendered 95 items with no edit, review or sign-off controls.
- The exact candidate build API returned source commit `d3db3a07457ebe72cf97b1dee715897985c912b3`, PHP 8.3 and shared-hosting mode.
- Final post-login reload made six fetch/XHR requests: session, projects, inbox, tools and two completeness reads. All returned HTTP 200. No console warnings or errors were present, and no production request targeted `/api/v1/demo/sandbox/ensure`.
- Desktop and responsive mobile layouts both had zero horizontal overflow. Chrome DevTools reported a 500 px minimum inner width when 390 px was requested; the `max-width: 760px` mobile breakpoint was active.
- Desktop screenshot: [release21-pack25-platform-completeness-desktop.png](release21-pack25-platform-completeness-desktop.png).
- Responsive screenshot: [release21-pack25-platform-completeness-mobile.png](release21-pack25-platform-completeness-mobile.png).
- Exact-candidate Lighthouse mobile audit: Accessibility 100, Best Practices 100, SEO 100, Agentic Browsing 100; 45 passed, 0 failed. Reports are in `docs/evidence/release21-lighthouse-final/`.

## Truthful release status

Pack 25 is complete as a production control plane and release artifact. The single accepted browser fixture proves the acceptance mechanism; it does not claim that the other 94 checklist items or 11 workstreams are implemented. Those records correctly remain `not_started` until their own current evidence, passing QC and authorised human sign-off exist.
