# Public Access and V1 Completion Design

**Date:** 2026-09-01  
**Runtime:** PHP 8.3, MariaDB, shared hosting, FTP staged release

## Outcome

Architex opens on the exact public experience currently published at `test.architex.co.za`, uses its exact V8 sign-in and enrolment visual language, and then mounts the existing authenticated V1 workspace without replacing the production PHP/MariaDB boundary. The authenticated product is reconciled against `v1.html` and Packs 30-32 so every tool is either demonstrably functional, explicitly limited, or truthfully `integration_required`.

## Canonical references

- `test.architex.co.za/preview3.html` owns the public landing composition, copy, visual system, and public interactions.
- The hosted V8 authentication bundle owns the sign-in/enrolment composition and copy.
- `v1.html` owns the authenticated information architecture, tool placement, and workspace interaction intent.
- Packs 30-32 own lifecycle integration, legal/human-control requirements, and the Bird Flocks transition.
- Existing PHP services, MariaDB schemas, routes, security middleware, and tests remain authoritative for real capabilities.

The remote public assets are vendored locally so the shared-hosting release has no runtime dependency on the test site. Third-party secrets are never copied into browser code.

## Access-state architecture

One document host owns four explicit states:

1. `checking-session`: a non-data-bearing boot state while `/api/v1/session` is checked.
2. `public`: the exact local copy of the hosted landing is visible; the authenticated shell is inert and hidden.
3. `auth`: the V8 sign-in or public organisation enrolment panel is visible after the Bird Flocks transition.
4. `workspace`: a verified session hydrates the V1 shell from MariaDB and the public/auth surfaces are removed from navigation and accessibility trees.

Public proposal and supplier invitation routes retain their current token-specific paths and do not pass through the general landing.

## Landing and Bird Flocks transition

The landing markup, styles, imagery, animation timings, content, and responsive breakpoints are copied from the hosted canonical asset. Its sign-in and sign-up triggers are rebound to local controllers instead of remote routes.

Activation runs a restrained 2.4-second transition: the teal network settles, the bird separates into purposeful fragments, visual complexity clears, the white authentication surface emerges, and fragments reform as the small persistent Wingman bird. `prefers-reduced-motion` bypasses fragmentation and cross-fades directly. The animation cannot block form focus, back navigation, or session restoration.

## Authentication and enrolment

The V8 authentication surface uses the hosted two-column identity/form composition and exact core copy. It calls only existing first-party endpoints:

- `POST /api/v1/sessions`
- `POST /api/v1/registrations`
- `POST /api/v1/registrations/verify-email`
- `GET /api/v1/session`
- `DELETE /api/v1/session`

Public self-registration creates a new organisation and pending owner. The browser derives a valid organisation slug visibly and allows correction. Registration collects the current four required legal notice versions from a first-party notice catalogue, records explicit acceptance, and shows the real email-verification state. Professional profile information not accepted by the current registration contract is deferred until the verified workspace; it is not silently discarded or fabricated.

Authentication errors remain generic, CSRF/session handling remains server-owned, passwords are never persisted in browser storage, and the NVIDIA credential remains server-side only.

## Legal implementation

Pack 31 is labelled draft content pending South African attorney review. Public pages are added for Terms, Privacy, Disclaimer, AI Disclaimer, Payment/Escrow Terms, CPD Terms, Resource Sharing Terms, and Dispute Resolution Rules. Published notice bodies are versioned and checksum-bound. Signup acceptance continues to block registration unless all current required notices are accepted.

Sensitive workflow acceptance is a separate auditable record containing notice version, timestamp, user, organisation, role, project/workflow target, decision, IP, and device metadata. Existing domain-specific human-confirmation gates remain authoritative. No flow may auto-sign, auto-certify, auto-submit, auto-purchase, or auto-release funds.

## Tool reconciliation

The static registry is replaced by evidence-led readiness metadata. Each V1 tool receives:

- a stable tool ID and V1 placement;
- its actual frontend workspace function;
- authenticated route/service dependencies;
- MariaDB migration/table dependencies;
- focused test evidence;
- one status: `live`, `limited`, `integration_required`, or `unavailable`;
- a precise limitation statement when it is not live.

`live` requires an implemented user workflow, authenticated API, persisted MariaDB state where the workflow creates state, tenant/role controls, and passing focused tests. A decorative iframe or static sample is never `live`. External integrations without configured and verified providers remain `integration_required` even when a local control surface exists.

## Pack 30 product integration

The existing project, ProjectRecord, inbox, audit, document, procurement, finance, site, closeout, practice, trust, agent, and risk capabilities are presented through one lifecycle rather than disconnected modules. Navigation exposes Command Centre, Inbox, Projects, Toolboxes, CPD, Documents, Knowledge, Marketplace, Finance, Messages, and Settings/Admin according to role and project phase. Knowledge is a first-class destination with source-linked, citation-required records. Candidate-professional marketplace work preserves supervision requirements.

## Verification and staged release

Verification is layered and reported separately:

1. static contract and frontend unit tests;
2. PHP unit/HTTP tests;
3. connected disposable-MariaDB integration tests;
4. local browser flow for landing, sign-in, registration, legal pages, workspace hydration, responsive layout, console, and network;
5. exact-commit release artifact verification;
6. remote shared-hosting smoke tests after FTP staging.

A local pass is not remote certification. Provider-dependent capabilities remain unverified until their real provider is configured and exercised.
