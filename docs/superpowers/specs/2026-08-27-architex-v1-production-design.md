# Architex V1 Production Design

**Date:** 2026-08-27
**Status:** Approved for staged implementation
**Canonical UI:** `v1.html`
**Production target:** PHP 8.3 and MariaDB on FTP-only shared hosting with control-panel cron

## 1. Objective

Turn the supplied `v1.html` prototype into a production, multi-tenant Architex platform. Preserve the prototype's visual design and interaction model while replacing all demo data, client-side authority, fabricated metrics, local-only state, and scaffold behavior with authenticated PHP services and durable MariaDB persistence.

Every supplied pack is a source requirement and is considered unimplemented regardless of any completion label, validation report, checkbox, or previously claimed repository state.

## 2. Product principles

1. `v1.html` is the visual contract. Production work must preserve its layout, styling, navigation, naming, responsive states, and interaction patterns.
2. The browser is never an authority boundary. Authentication, roles, tenant membership, project access, state transitions, approvals, and audit attribution are derived and enforced by PHP.
3. MariaDB is the system of record. Browser storage may hold disposable presentation preferences only.
4. Every organisation-owned record has `organisation_id`; every project-owned record also has `project_id`.
5. Formal project, professional, statutory, contractual, financial, moderation, and agent actions require explicit server-side policy checks and human approval where the packs require it.
6. Issued or published records are immutable snapshots. Corrections create superseding versions.
7. External providers are represented by explicit adapters. Missing email, payment, AI, verification, signing, or municipal providers must fail closed and must not be simulated as production success.
8. Each staged release is independently deployable, recoverable, and verifiable.

## 3. Chosen architecture

Use a modular PHP 8.3 monolith with a versioned JSON API and a MariaDB persistence layer. Composer dependencies are installed and tested locally, then `vendor/` is uploaded through FTP with the release artifact.

### 3.1 Directory boundaries

```text
app/
  public/
    index.php                  HTTP front controller
    assets/                    versioned CSS, JS, icons and images
  src/
    Platform/                  shared identity, tenant, project and workflow spine
    Modules/                   pack-derived bounded modules
    Http/                      routing, middleware, controllers and API responses
    Infrastructure/            database, mail, files, clock, IDs and provider adapters
  config/                      environment-derived typed configuration
  database/
    migrations/                ordered MariaDB migrations
    seeds/                     local/test/demo seed definitions
  cron/                        queue, reminder, digest and integrity entrypoints
  storage/                     non-public uploads, exports, logs and deployment state
  tests/                       unit, integration, security and HTTP contract tests
  bin/                         local build, migration and verification utilities
```

Each module exposes application services and repository interfaces. HTTP controllers translate requests and responses but contain no workflow rules. MariaDB repositories implement persistence without leaking SQL into domain services.

### 3.2 Shared platform spine

Release 1 establishes reusable services for:

- users, email identities, credentials and sessions;
- organisations, memberships, invitations and role assignments;
- project identity, Project Passport, phases and participant scopes;
- role-aware navigation and tool access;
- project records and immutable issued snapshots;
- inbox items, workflow events, notifications and scheduled jobs;
- files, metadata, access-controlled downloads and exports;
- policy decisions, approval gates and review queues;
- legal-notice versioning and acceptance records;
- append-only audit events with sensitive-field redaction;
- health, readiness, migration status and operations diagnostics.

Feature modules must reuse these services rather than creating parallel identity, navigation, event, file, permission, or audit systems.

## 4. Canonical UI conversion

The rendered V1 experience is preserved while the implementation is decomposed:

- CSS is extracted without restyling and remains protected by visual-regression baselines.
- global tool metadata becomes a server-compatible tool registry with stable tool IDs;
- global JavaScript renderers become focused ES modules grouped by shell, project datum, tool host, inspector, meetings, engineering, and God Mode;
- embedded base64 or iframe-like tool payloads are replaced with first-party workspace views and API calls;
- every `scaffold` tool receives a real route, capability manifest, empty/loading/error states, and staged implementation status;
- every `live` prototype tool is still treated as unimplemented until its actual API, persistence, authorization, and workflow tests pass;
- generated metrics come from MariaDB projections and display an honest unavailable/empty state when no data exists;
- God Mode is a sandbox/read-only exploration surface and never bypasses permissions or exposes another tenant's records.

The original `v1.html` is retained as a reference fixture. Production code is compared against it at agreed desktop and mobile viewport baselines.

## 5. Identity, tenancy and authorization

### 5.1 Public registration

Public registration creates a pending user and organisation. Activation requires:

1. validated email address;
2. password meeting configured policy;
3. acceptance of the current Terms, Privacy Policy, platform disclaimer, and short signup privacy notice;
4. successful email-verification token exchange;
5. organisation slug/name uniqueness checks and abuse throttling.

SMTP is a production activation prerequisite. Until SMTP is configured, production public-registration endpoints return a service-unavailable error and do not create active accounts. Development uses a local captured-mail adapter.

Passwords use PHP's `password_hash()` with Argon2id when supported and an explicit bcrypt fallback. Verification, password-reset, and invitation tokens are stored only as hashes, expire, and are single-use. Sessions use rotated opaque IDs, secure HTTP-only SameSite cookies, server-side expiry, idle timeout, absolute timeout, and revocation records.

### 5.2 Tenant isolation

A user may belong to multiple organisations through memberships. Every request resolves one active organisation from the session and verifies membership before querying tenant data. Repository methods require an `OrganisationId`; project repositories additionally require an accessible `ProjectId`.

MariaDB foreign keys include organisation ownership. Unique indexes include `organisation_id` wherever names or external references are tenant-specific. Cross-tenant admin operations use separate platform permissions and emit privileged audit events.

### 5.3 Roles and permissions

Canonical user roles cover client/developer, architect, engineer, quantity surveyor, town planner, land surveyor, landscape architect, interior designer, construction project manager, contractor, subcontractor, supplier, freelancer, candidate professional, CPD officer, organisation administrator, moderator, finance administrator, verification reviewer, audit viewer, platform administrator, and super administrator.

Roles grant named permissions. Project participation further constrains access. Candidate/junior work retains supervision labels and routes regulated outputs to an eligible professional reviewer.

## 6. Data and workflow model

MariaDB uses InnoDB, `utf8mb4`, UTC timestamps, foreign keys, explicit indexes, and transactions for state changes spanning multiple records.

Core tables include:

- `users`, `user_emails`, `credentials`, `sessions`, `verification_tokens`, `password_reset_tokens`;
- `organisations`, `organisation_memberships`, `organisation_invitations`, `role_assignments`;
- `legal_notices`, `legal_acceptances`;
- `projects`, `project_passports`, `project_participants`, `project_phase_history`;
- `project_records`, `record_links`, `record_versions`;
- `workflow_events`, `inbox_items`, `notifications`, `scheduled_jobs`;
- `approval_requests`, `approval_decisions`, `review_queue_items`, `policy_decisions`;
- `files`, `file_versions`, `file_links`, `generated_exports`;
- `audit_events`, `security_events`, `idempotency_keys`;
- `tool_definitions`, `tool_role_permissions`, `tool_runs`, `tool_run_assignments`.

Module tables use the same binary UUID or ordered identifier convention and reference the shared spine. JSON columns are reserved for evolving metadata and snapshots; query-critical relationships and states remain typed columns.

### 6.1 State changes

Commands follow this flow:

```text
request -> session -> active organisation -> permission -> validation
        -> transaction -> domain transition -> project record/event/audit
        -> commit -> queued notification/export work -> JSON response
```

Issued proposals, drawings, transmittals, compliance results, instructions, certificates, payment records, handover packs, and other controlled outputs snapshot their source/version references. Published records are superseded rather than overwritten.

### 6.2 Concurrency and idempotency

Mutable aggregate records carry a version number. Updates require the last observed version; stale writes receive HTTP 409 with the current version. Create/issue/payment-provider commands accept idempotency keys so retries cannot duplicate formal records or financial events.

## 7. HTTP API and frontend data flow

The API lives under `/api/v1`. Responses use one envelope:

```json
{
  "data": {},
  "meta": { "requestId": "..." },
  "error": null
}
```

Validation, authorization, conflict, rate-limit, provider, and unexpected errors use stable machine codes and safe messages. Stack traces, SQL, paths, credentials, and provider secrets are never returned.

State-changing requests require CSRF protection, a same-origin session, JSON content type where applicable, and an `Origin`/`Referer` policy. File uploads use MIME sniffing, extension allowlists, size limits, randomized storage names, malware-adapter hooks, and non-public storage. Downloads re-authorize the current user for every request.

Frontend modules load data through a central API client that handles CSRF tokens, timeouts, request IDs, authentication expiry, field errors, retry-safe reads, and 409 conflict prompts. Optimistic UI is limited to reversible low-risk actions.

## 8. Background work and external providers

The control-panel cron invokes a locked PHP dispatcher at least once per minute. Jobs are claimed transactionally, have bounded retries, exponential backoff, dead-letter state, and visible operations history.

Provider interfaces cover:

- transactional email;
- payment/escrow events;
- AI generation and provenance;
- professional verification;
- electronic signing;
- municipal/statutory exchange;
- document conversion/OCR;
- malware scanning;
- optional object storage and SMS/push notification.

No adapter reports success without a real configured provider response. Webhooks verify signatures, record raw-event hashes, use idempotency keys, and separate provider state from Architex approval state.

## 9. Pack-derived staged releases

### Release 1: Production foundation

Exact V1 shell; registration; organisations; memberships; legal acceptance; sessions; RBAC; tenant isolation; Project Passport; lifecycle; project records; inbox/events; audit; uploads; tool registry; standalone tool-run foundation; admin identity controls; cron; health/readiness; backup and deployment tooling.

### Release 2: Professional onboarding and appointment

Verification workflow; directory; teams; guided briefs; fee calculators; proposal builder; versioned terms; acceptance; appointment conversion; kickoff; meetings; contextual messaging; forms; and standalone tool history/assignment.

### Release 3: Design, documents, planning and compliance

Document/drawing control; revisions; transmittals; SpecForge; BIM/IFC; engineering, FenCalc and XA calculators; compliance hub; municipal readiness/tracker; council navigator; planning; environmental/heritage; survey/geomatics; refuse calculation; evidence packs; professional review.

### Release 4: Tender, marketplace, finance and contracts

BoM/BoQ; take-off; tender packages; RFQs; catalogues; quote comparison; procurement; contract administration; approvals; invoices; valuations; escrow records; payment-provider state; reconciliation; disputes; platform-fee versions; and marketplace governance.

### Release 5: Construction and field control

Site diary; photos; attendance; plant; deliveries; ITP; safety; HIRA; permits; NCR; RFI; site instructions; snagging; delay/daywork evidence; subcontract packages; programme/drawdown links; payment blockers; mobile capture; controlled reports.

Pack 10 is functionally consolidated with Pack 9 but remains represented in the requirements ledger so no duplicated or divergent acceptance statement is lost.

### Release 6: Closeout, practice operations and intelligence

Practical completion; handover; warranties; as-builts; O&M; occupancy; defects liability; FM bridge; practice management; capacity; timesheets; WIP; debtors; insurance; market insights; analytics; reports; risk and early warning.

### Release 7: Governed agent platform

Wingman; user/project agents; specialist workflow agents; recommendations; provenance; policy-controlled tool invocation; approval queues; decisions; safe fallbacks; human approval; monitoring and usage controls.

### Release 8: Community, learning and safe demonstration

Community channels; audience resolution; moderation; CPD/learning; knowledge; digests; shared-backend mobile PWA; isolated per-user demo sandboxes; role switching; safe God Mode exploration.

## 10. Pack-to-production traceability

A generated ledger will enumerate every pack archive, extracted document, manifest, TypeScript service, declared object, state, role, action, route, output, guardrail, and acceptance criterion. Each requirement receives:

- stable requirement ID;
- source pack and source file;
- release and module owner;
- implementation location;
- automated test/evidence location;
- status derived from executed evidence only.

Words such as `done`, `implemented`, `live`, `validated`, and checked boxes in source packs do not set ledger status. All entries begin `not_implemented`.

## 11. Security and privacy baseline

- CSP, HSTS, clickjacking protection, MIME protection, referrer policy, secure cookies, CSRF, output encoding, prepared statements, and rate limits are enabled by default.
- Secrets live outside the public root and are never committed or included in diagnostics.
- Privileged actions require re-authentication where risk warrants it.
- Audit payloads redact secrets and sensitive provider data by default.
- POPIA workflows include consent/notice evidence, access/export requests, correction, deletion review, retention holds, breach records, and admin audit.
- Production logs use structured request IDs and exclude passwords, tokens, full document bodies, and payment credentials.
- Formal AI outputs identify model/provider, prompt/template version, sources, time, author/requester, review state, and corrections.

Legal text supplied in the packs is implemented as versioned draft content but must receive qualified South African legal review before public publication.

## 12. FTP-compatible deployment and recovery

Local build produces an immutable release directory containing PHP source, `vendor/`, built assets, migration files, integrity manifest, and version metadata. Runtime secrets and writable storage are excluded.

Deployment through FTP uses versioned release directories where the host allows them. A protected maintenance controller performs preflight checks, applies pending migrations under a database lock, warms metadata, and switches the active version. If symbolic links are unavailable, deployment uses a maintenance page, verified upload manifest, database migration, and small final front-controller switch.

Destructive or irreversible migrations require a verified backup and restore rehearsal. Backward-compatible expand/migrate/contract changes are preferred. Release verification includes database connectivity, migration status, writable storage, cron freshness, mail configuration, and a representative authenticated tenant-scoped query.

## 13. Error handling and operations

- Expected domain failures return actionable codes and field details.
- Unexpected failures receive a request ID, safe user message, structured server log, and alert/digest entry.
- Provider outages preserve local workflow state and clearly label pending/unavailable actions.
- Cron overlap is prevented with database advisory/lease locks.
- Health distinguishes process health from readiness; readiness queries MariaDB and checks migration state and writable storage.
- Backups cover MariaDB plus non-public file storage and are periodically restore-tested in an isolated environment.

## 14. Verification strategy

Each release requires:

1. unit tests for domain rules and state transitions;
2. MariaDB integration tests against a disposable `_test` database;
3. HTTP contract tests for authentication, CSRF, validation, idempotency and error envelopes;
4. tenant-isolation and permission matrix tests, including negative cases;
5. upload/download authorization and malicious-file tests;
6. cron retry, locking and dead-letter tests;
7. browser tests for registration, login, project workflow, persistence after reload and role-scoped navigation;
8. V1 visual regression at desktop and mobile viewports plus computed-layout checks for critical shell geometry;
9. accessibility checks for keyboard use, focus, names, contrast, dialogs and status messaging;
10. production artifact inspection, clean installation, migration, backup and restore rehearsal.

A build, static health response, prototype behavior, passing pack demo, or historical report is not production proof. Release status is based on current executed evidence.

## 15. Release 1 acceptance criteria

Release 1 is ready for a controlled shared-hosting deployment only when:

- V1 shell parity passes visual and computed-layout checks;
- public registration creates a pending tenant and verified activation works with a real SMTP test provider;
- login, logout, session rotation, password reset and revocation pass;
- users cannot read or mutate another organisation's records under any tested role;
- Project Passport and lifecycle changes persist through MariaDB and page reload;
- project events create scoped inbox and audit records transactionally;
- uploads cannot execute and downloads require current authorization;
- migration status, database readiness, storage readiness and cron freshness are truthful;
- backup and isolated restore rehearsal pass;
- the FTP release artifact can be deployed from a clean shared-hosting directory;
- no prototype-only scaffold or fabricated metric is labelled production-ready.

## 16. Deferred credentials and external certification

Implementation can proceed locally without production credentials. The following remain deployment gates rather than reasons to simulate success:

- SMTP credentials and verified sender/domain;
- shared-hosting MariaDB credentials and final database version/collation;
- production domain, TLS and document-root configuration;
- payment/escrow provider credentials for Release 4;
- AI provider and governance configuration for Release 7;
- any professional-body, municipal, signing, OCR, malware, SMS or push provider selected for later releases.
