# Release 21 — Platform Completeness and QC Control Plane

**Pack:** `25. architex-workflow-checklists`

**Target:** PHP 8.3 and MariaDB on shared hosting, deployable by FTP, preserving the canonical V1 shell and replacing the supplied placeholder language with a production tenant-scoped implementation.

## Implementation plan

- [x] Encode the complete 12-workstream master register, 21-point QC checklist, 10 build-comparison questions, 14 completeness sections and every supplied checklist item as an immutable PHP catalogue.
- [x] Add MariaDB tables for organisation-scoped sections, items, evidence links, QC reviews, shared FileManager attachments, role assignments and immutable sign-offs.
- [x] Seed each organisation idempotently from the canonical catalogue without reading pack files at runtime.
- [x] Map owners/admins to safe default governance capabilities and allow owners to assign developer, QA reviewer and viewer access without weakening organisation membership enforcement.
- [x] Implement optimistic item updates for implementation/QC state, owners, reviewers, route/commit references, blockers, next actions and acceptance criteria.
- [x] Implement append-only QC reviews and accountable sign-offs; accepted status must require a human confirmation reason and cannot be produced by AI.
- [x] Implement evidence links and FileManager-backed uploads using existing tenant/project-scoped stored files, with category allowlists and cross-tenant rejection.
- [x] Publish tenant-scoped Platform Completeness APIs with server-side read/develop/review/sign-off permission gates and route-contract tests.
- [x] Add a V1-native `/admin/platform-completeness` control centre under Settings with summary, filters, master register, checklist items, evidence, QC history and role-aware actions.
- [x] Preserve cellphone-first behavior, useful empty/loading/error states, exact status labels and explicit POPIA/audit boundaries.
- [x] Verify focused contracts, full suite, all 51 migrations, connected MariaDB persistence, optimistic conflicts, tenant isolation, cross-tenant file rejection and sign-off gates.
- [x] Certify owner, reviewer and viewer flows in persistent Chrome DevTools MCP at desktop and responsive mobile, with screenshots, console/network checks and Lighthouse. Chrome DevTools clamped the requested 390 px width to 500 px; the mobile breakpoint was active with zero overflow.
- [x] Commit Release 21 and build an independently extracted and verified FTP ZIP from its exact source commit.

## Authority model

- `platform_super_admin`: organisation owner; full role assignment, edit, review and sign-off.
- `product_owner`: organisation admin default; goals, acceptance criteria and sign-off.
- `developer`: update implementation status, code/route evidence, blockers and next actions.
- `qa_reviewer`: append QC reviews and update QC status.
- `viewer`: read-only.
- Every platform role still requires an active organisation membership. A completeness assignment never grants organisation or project membership.

## Truthfulness and evidence

- Historical pack text such as PR numbers, prior TypeScript claims and old QC blockers is stored as supplied historical context, not asserted as current fact.
- The system never marks an item accepted merely because tests or evidence links exist; an authorised human sign-off is required.
- AI may summarise or draft, but may not change implementation state, QC outcomes or sign-offs.
- Uploaded evidence remains in FileManager and is linked by ID; the module does not create a parallel file store.
