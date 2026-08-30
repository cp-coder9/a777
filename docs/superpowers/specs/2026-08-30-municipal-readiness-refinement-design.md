# Municipal Readiness Refinement Design

**Release:** 24  
**Pack:** 28. architex-municipal-readiness-refinement  
**Target:** PHP 8.3, MariaDB, shared-hosting deployment by FTP

## Outcome

Extend the existing Municipal Submission Readiness module into a governed workflow that can issue an immutable, versioned **Architex-backed submission pack**. The workflow prepares professional-side evidence before formal lodgement. It never claims municipal approval, never fabricates a council integration, and never treats rules or AI output as professional certification.

## Existing foundation retained

Release 24 builds on the existing tenant-scoped implementation rather than replacing it:

- versioned, source-referenced municipal requirement matrices;
- eight-category readiness assessment and scoring;
- live Project Passport, controlled-document, drawing-register and ToolRun evidence;
- trigger-based professional routing and contextual inbox events;
- immutable assessment snapshots with SHA-256 integrity checks;
- ProjectRecord and audit writes;
- human approval before professional submission review.

Existing endpoints and response fields remain compatible.

## Pack requirements reconciled

The current v0.2 pack is authoritative where it extends the legacy v0.1 reference. Both versions require property and municipality context, application-type-aware checks, professional routing, blocker/warning/advisory findings, inbox output, ProjectRecords, source versions, human sign-off, and advisory wording. Version 0.2 additionally requires:

- title/property evidence in the required-document baseline;
- a resolved example and an issued state;
- scoring that accounts for blockers, warnings and unsigned required routes;
- immutable issued revisions rather than a mutable export placeholder;
- drawing and document indexes, resolution log, sign-off register, source register and audit manifest;
- explicit workflow states: draft, in review, blocked, ready for sign-off, issued and superseded.

## Domain boundary

### Property and application profile

Each project may have one current municipal profile containing:

- application type: building plan, minor works, consent use, departure/relaxation, rezoning, subdivision, consolidation, removal of restrictive title condition, or occupancy certificate;
- erf, farm, portion and sectional-title identifiers;
- address, municipality and zoning/land-use assumptions;
- overlay, servitude, heritage and environmental flags;
- ownership and agent-authorisation state;
- public-participation, notice and municipal-fee placeholder states.

Updates are tenant/project scoped and audited. Assessments copy the exact profile into their immutable snapshot.

### Findings

Assessment checks are materialised as findings when their result is not complete or not applicable. Every finding stores:

- severity: blocker, warning or advisory;
- category, title, explanation, source reference and responsible role;
- status: open, resolved, waived or signed off;
- resolution reason, resolver and timestamp;
- whether human approval is required.

Blockers may only be resolved by an authenticated organisation member with a reason. Waivers remain visible and do not silently convert statutory uncertainty into compliance.

### Professional routes and supervision

Triggered routes store the reason, severity, assignee, state and whether sign-off is required before issue. Candidate or non-externally-verified appointments require a supervising registered professional. A candidate cannot self-satisfy that route.

Route states are required, assigned and signed off. Sign-off records the exact assessment SHA, signer, role, declaration and timestamp.

### Pack issue

Only owner, admin or member roles may request issue, and the request must explicitly confirm human review. The service fails closed unless all of the following are true:

- the municipal matrix is verified;
- the assessment passed the existing professional-review approval gate;
- no open blocker remains;
- every required-before-issue route is signed off;
- candidate routes have a supervising professional sign-off;
- owner/agent authority and required controlled evidence are present;
- a principal professional is named and confirms responsibility.

Issue occurs in one MariaDB transaction. The service locks the assessment, calculates the next project pack version, persists the canonical manifest and SHA-256, issues a ProjectRecord, creates an audit event and marks the previous issued pack superseded. Issued manifest rows are never updated; a change requires a new assessment and new pack revision.

## Pack manifest standard

The canonical manifest contains:

1. cover: project, property, municipality, application type, pack version, issue date and principal professional;
2. readiness statement with the required statutory and AI/rules disclaimer;
3. project and property profile;
4. controlled-document index;
5. drawing index with revision, date, discipline and file hash when available;
6. checklist result summary;
7. blocker resolution and waiver log;
8. professional route and sign-off register;
9. source and municipal-rule version register;
10. audit manifest with assessment SHA, pack SHA and record identifiers.

The pack language may say “Architex-backed submission pack” and “professional-side readiness pack”. It must not say “Architex approved”, “municipality-approved”, “AI-certified compliant” or promise council approval.

## Persistence

Migration `0054_municipal_readiness_refinement.sql` adds:

- `municipal_project_profiles` — one current tenant/project-scoped profile;
- `municipal_readiness_findings` — assessment findings and resolution history;
- `municipal_professional_routes` — assignments, supervision requirements and sign-offs;
- `municipal_submission_packs` — immutable versioned manifest snapshots and hashes.

All identifiers are binary UUIDs, foreign keys remain tenant-linked through guarded service queries, JSON is canonicalised before hashing, and indexes support project history and issue gates.

## API

Authenticated API additions:

- `GET|PUT /api/v1/projects/{projectId}/municipal-profile`
- `GET /api/v1/submission-readiness/{assessmentId}/workflow`
- `POST /api/v1/submission-readiness/{assessmentId}/findings/{findingId}/resolve`
- `POST /api/v1/submission-readiness/{assessmentId}/routes/{routeId}/assign`
- `POST /api/v1/submission-readiness/{assessmentId}/routes/{routeId}/sign-off`
- `POST /api/v1/submission-readiness/{assessmentId}/packs/issue`
- `GET /api/v1/projects/{projectId}/municipal-submission-packs`
- `GET /api/v1/municipal-submission-packs/{packId}`

All writes use the existing CSRF middleware. Reads and writes confirm active membership and tenant/project ownership. Expected gate failures return stable 422/403/404/409 codes.

## Workspace

The canonical municipal workspace gains:

- editable property/application profile;
- dashboard status for municipality, application, score, blockers, warnings, advisories, route sign-offs, rule version and pack revision;
- finding resolution controls;
- route assignment and sign-off controls with supervision warnings;
- issue panel showing every failed gate;
- issued-pack history and manifest inspection.

The existing visual system and responsive layout are retained. Every form control has a programmatic name, status messages use live regions, and 390 px rendering must have no horizontal overflow.

## Verification

Tests proceed test-first and cover:

- all application/profile fields and validation;
- legacy blocked residential addition;
- title/authority, zoning, structural, civil, energy, fire, heritage and traffic triggers;
- public-assembly/fire routing;
- candidate supervision;
- finding resolution and tenant isolation;
- premature issue rejection for every gate;
- successful issue, canonical hash verification and previous-revision supersession;
- immutable issued rows and ProjectRecord/inbox/audit writes;
- authenticated HTTP and CSRF behaviour;
- workspace contract, browser functionality, accessibility and mobile overflow;
- all migrations on a disposable MariaDB database, complete PHP suite, syntax checks and staged artifact verification.

Production FTP upload and remote MariaDB migration remain separate staged-release actions; local certification does not claim remote deployment.
