# Pack 21 Production Demo Sandbox Design

## Decision

Implement Pack 21 as a server-governed demo mode in the existing PHP 8.3 application, using the same release artifact and a separate MariaDB database for the demo deployment. The source pack's Firebase, React/Vite, Vercel and client-side authorization examples are requirements evidence only and are not runtime dependencies.

The demo deployment must provide persistent per-user sandboxes, 12 deterministic synthetic projects, 22 role lenses, role-filtered navigation and project views, an audited factory reset, and real access to implemented Architex workflows. Demo data must never enter the production database, production analytics, production community audiences, real payment providers, real mail delivery or formal professional/statutory workflows.

## Deployment boundary

- One immutable FTP artifact serves both production and demo deployments.
- Production sets `APP_MODE=production`; demo sets `APP_MODE=demo`.
- `APP_MODE` is validated fail-closed and defaults to `production` when absent.
- The demo document root points to the same artifact layout as production.
- The demo deployment uses separate MariaDB credentials and a separate database.
- Demo routes return HTTP 404 in production mode.
- Demo mode disables analytics, external notifications, provider webhooks and outbound formal delivery.
- The demo hostname and database are hosting configuration. Local certification does not claim that `demo.architex.co.za` exists until remote staging proves it.

## Sandbox lifecycle

Every verified demo user owns exactly one active sandbox generation.

On first authenticated demo access, `DemoSandboxService::ensure()` adopts the user's active organisation as generation 1, marks it as a demo sandbox, and seeds it transactionally. Subsequent access returns the same sandbox and does not overwrite user changes.

Factory reset does not attempt a fragile multi-table delete. It performs one transaction that:

1. marks the current sandbox generation `superseded`;
2. revokes the user's membership in that generation;
3. closes the old organisation;
4. creates a new active organisation and owner membership;
5. seeds a new deterministic generation;
6. records the reset reason, prior and replacement organisation IDs, seed version and actor;
7. returns the replacement organisation so the browser can refresh its session context.

The old generation remains inaccessible through normal membership and can be removed later by a retention job. Reset requires CSRF protection, the exact confirmation phrase `RESET DEMO SANDBOX`, a reason, and a rate limit. It never affects another user's generation.

## MariaDB model

Migration `0044_demo_sandbox.sql` introduces:

- `demo_sandboxes`: owner user, organisation, generation, active role lens, seed version, seed hash, lifecycle status and timestamps;
- `demo_personas`: 22 synthetic role profiles scoped to one sandbox;
- `demo_project_role_assignments`: role-to-project visibility for the 12 seeded projects;
- `demo_seed_items`: integrity-hashed synthetic submissions, messages, compliance examples and learning records that do not yet have an authoritative production module;
- `demo_sandbox_events`: append-only ensure, role-change, seed, reset and isolation audit events.

Seeded projects use the existing `projects`, `project_records`, `workflow_events` and `inbox_items` tables so implemented platform modules operate against real tenant-scoped project IDs. Demo-only illustrative content lives in `demo_seed_items` and is never represented as an issued, approved, verified, accredited or authoritative production record.

Every table is organisation- and sandbox-scoped. Every returned ID is checked against the authenticated user's active sandbox. Seed payloads use canonical JSON and SHA-256 integrity hashes.

## Deterministic seed contract

`DemoSeedCatalog` is immutable, versioned and contains no random values or wall-clock-dependent status calculation. Version `pack21-v1` provides:

- 12 projects spanning enquiry, shortlist, appointment, concept, design development, tender, construction and closeout;
- 22 synthetic role personas;
- project-role assignments matching each discipline's involvement;
- representative submissions and drawing indexes for the applicable project stages;
- project communication examples;
- advisory compliance examples labelled `illustrative_demo_not_for_reliance`;
- six synthetic learning articles, four assessments, three learning modules and five example certificates labelled `not_accredited_demo_record`.

The source pack's fake registration numbers and claims about standards, fees, accreditation and compliance are not published as verified facts. All dates are derived from a fixed catalog epoch and seed version. Re-running the same seed version for a new generation produces the same semantic payload and seed hash.

## Roles and authority

Role lenses change presentation, navigation, toolbox discovery and project filtering. They never mutate the user's real identity or grant authority over non-demo data.

The 22 required lenses are:

1. client
2. project_manager
3. architect
4. architectural_technologist
5. candidate_architect
6. engineer_structural
7. engineer_civil
8. engineer_electrical
9. engineer_mechanical
10. engineer_fire
11. quantity_surveyor
12. contractor
13. subcontractor
14. supplier
15. energy_consultant
16. town_planner
17. environmental_consultant
18. accessibility_consultant
19. bep
20. freelancer
21. cpd_officer
22. admin

`DemoRoleLensRegistry` maps each lens to the canonical V1 label, focus text, tool categories and assigned project keys. The selected lens is validated and stored in MariaDB. Clients cannot submit recipient IDs, project IDs or capabilities to broaden the lens.

Existing organisation authorization remains enforced from the authenticated owner membership. The role lens is an educational view over the user's own isolated sandbox, not an alternate authentication role. Tests cover both the 22 role lenses and the platform membership roles `owner`, `admin`, `member` and `viewer` to prove that production authorization remains unchanged.

## HTTP contract

All routes require authentication and an active organisation. All writes require CSRF protection.

- `POST /api/v1/demo/sandbox/ensure`: create or return the active sandbox and deterministic seed summary.
- `GET /api/v1/demo/sandbox`: return active generation, role lens, counts, capabilities, disclaimers and seed integrity.
- `PUT /api/v1/demo/sandbox/role`: validate and persist one of the 22 role lenses; return its project/tool projection.
- `GET /api/v1/demo/sandbox/projects`: return only projects assigned to the active role lens.
- `GET /api/v1/demo/sandbox/projects/{id}`: return a project overview only when the active role lens is assigned to it.
- `GET /api/v1/demo/sandbox/learning`: return the synthetic learning projection for applicable lenses with non-accreditation boundaries.
- `POST /api/v1/demo/sandbox/reset`: require the confirmation phrase and reason, supersede the generation, seed the replacement, and return the new organisation context.

Responses include `demoMode: true`, the sandbox generation, seed version and explicit reliance boundaries. Production mode returns 404 for every route.

## V1 interface design

The canonical `v1.html` remains the visual authority and is not edited. Generated application assets preserve the existing four-column shell, rail, navigator, main workspace and inspector.

The Pack 21 interface adds only production extensions:

- an amber V1 status rail at the bottom stating `DEMO SANDBOX`, active lens and generation;
- a topbar role-lens control using the existing compact button, menu, focus and typography tokens;
- a Demo Hub main workspace that upgrades the existing God Mode scaffold with live MariaDB state;
- role-filtered project cards using existing panel, status, metric and button components;
- a reset panel in the inspector with destructive-action copy, confirmation phrase and reason;
- clear `Synthetic demo data` and `Not for professional or statutory reliance` labels.

No new font, design system, page shell, gradient language or generic dashboard template is introduced. Existing V1 teal, cobalt, lavender, amber, neutral surfaces, radii, spacing, focus rings and responsive breakpoints remain authoritative. The amber demo rail is the single Pack 21 signature element because it communicates environment state rather than decoration.

On mobile, the rail remains readable without horizontal overflow, the role menu becomes a bounded sheet, cards collapse to one column, and the reset confirmation remains fully keyboard accessible. Reduced-motion preferences disable nonessential transitions.

Production mode hides every demo control and contains no seeded demo project state.

## Data flow

1. The authenticated browser loads the normal session.
2. In demo mode it calls `POST /demo/sandbox/ensure` with CSRF protection.
3. The service returns or creates the user's active sandbox generation.
4. The browser selects the returned organisation context and loads the V1 shell normally.
5. The Demo Hub reads role-filtered projections from the demo API while real implemented tools continue to use existing tenant-scoped APIs.
6. User actions persist in the separate demo database and survive reload and later login.
7. A role change persists server-side and refreshes navigation, project and toolbox projections.
8. Reset supersedes the generation and reloads the shell against the replacement organisation.

## Failure handling

- Seed failure rolls back the entire first-generation transaction.
- Reset failure leaves the original generation active and accessible.
- Unknown roles, cross-sandbox project IDs and stale generation IDs fail closed.
- Duplicate ensure requests return the existing active generation.
- Concurrent reset requests permit only one replacement generation.
- Disabled demo mode returns 404 without revealing route existence.
- Missing demo database configuration fails readiness and prevents demo startup.
- The UI presents actionable status messages and never silently falls back to prototype data.

## Testing and evidence

Implementation follows red-green-refactor. No production method is added without a test that first fails for the expected missing behavior.

### Automated tests

- Unit tests: role registry, deterministic catalog, validation, hash stability and reliance labels.
- Connected MariaDB tests: ensure idempotency, persistence, two-user isolation, cross-sandbox denial, role persistence, reset replacement, rollback and audit history.
- HTTP tests: production 404, demo authentication, CSRF, all 22 roles, invalid roles, project filtering, learning boundaries, reset confirmation and rate limits.
- Authorization regression: `owner`, `admin`, `member` and `viewer` retain their existing production permission matrix.
- Frontend contract tests: V1 shell ownership, production invisibility, 22 labels, accessible controls, mobile structure and no prototype fallback.
- Release tests: migration from zero, immutable FTP artifact, manifest verification and packaged runtime.

The complete PHPUnit suite runs with the disposable MariaDB integration flag enabled. Pack 21 certification requires zero failures, zero errors and zero skipped Pack 21 tests. A skipped or unavailable test is not represented as a pass.

### Chrome DevTools MCP E2E

Chrome DevTools MCP is mandatory and the browser remains open on the packaged candidate after certification.

The E2E matrix proves:

- first login and deterministic seed;
- every one of the 22 role lenses, expected role label, project visibility and tool grouping;
- persistence after role change, project update and reload;
- isolation using two separate authenticated browser contexts;
- cross-sandbox project denial;
- factory reset and replacement generation;
- production-mode absence of demo controls;
- desktop and 390-by-844 mobile geometry;
- keyboard operation and visible focus;
- zero browser console errors or warnings caused by Pack 21;
- successful session, sandbox, project, toolbox and reset network responses;
- Lighthouse accessibility and best-practices checks without critical failures.

Screenshots are stored as:

- `docs/evidence/screenshots/release17-pack21-demo-desktop.png`
- `docs/evidence/screenshots/release17-pack21-demo-mobile.png`
- `docs/evidence/screenshots/release17-pack21-demo-role-matrix.png`
- `docs/evidence/screenshots/release17-pack21-demo-reset.png`

## Completion gate

Pack 21 is complete only when:

- the migration, services, routes and V1 extension are committed;
- the 12-project and 22-role deterministic seed contract is proven;
- user changes persist and two-user isolation is proven against MariaDB;
- reset replaces only the requesting user's sandbox generation;
- production mode exposes no demo route, control or seed data;
- every required automated and all-role E2E test passes;
- Chrome DevTools evidence, responsive screenshots and console/network evidence are stored;
- the exact FTP candidate passes manifest and packaged-runtime verification;
- the evidence document distinguishes local certification from remote shared-hosting promotion.

Remote DNS, FTP upload, target MariaDB credentials, SMTP, control-panel cron and production promotion remain separate external gates and are never fabricated.

