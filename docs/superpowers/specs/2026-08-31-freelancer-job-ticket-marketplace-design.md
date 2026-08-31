# Freelancer / supervised professional job-ticket marketplace design

## Decision

Implement Pack 29 as a production PHP 8.3 and MariaDB module that deepens the existing procurement marketplace rather than replacing it. A job ticket is the atomic, project-aware work opportunity; existing governed RFQ packages remain the formal tendering path. Classification and matching are deterministic advisory aids. Appointment, supervision, external issue, payment and closeout remain explicit human-controlled transitions.

## Supplied pack interpretation

Every Pack 29 file was read in full, including the v0.2 brief, manifest, implementation note, validation report, module specification, package metadata and all TypeScript reference sources. The TypeScript demo establishes the target lifecycle, not an acceptable authorization or persistence implementation.

The production contract includes:

- client, registered-BEP, contractor and subcontractor posting authority;
- public and direct tickets with privacy-safe discovery;
- deterministic classification, explanation, confidence and controlled human override;
- fit recommendations based on published marketplace evidence, discipline, service area, verification and capacity;
- quotations, poster selection and a single accepted holder;
- holder-only recursive expansion into a JV/work-package tree;
- equal-information public clarifications and private direct invitations;
- supervised freelancer profiles across every supplied discipline;
- engagement-specific, discipline-compatible, immutable supervision routes;
- timesheets, claims, payment evidence, deliverable revisions and supervisor sign-off;
- closeout and reviews only after the governed completion gate;
- project records, inbox events and audit events at material transitions.

## Trust and claims boundary

- “AI” is presented as advisory deterministic classification; no external model call or autonomous decision is claimed.
- A classifier override stores the original and replacement classification, reason and actor.
- Matching never exposes private profile evidence and never auto-awards work.
- A freelancer never becomes an independent ticket holder. Acceptance creates a supervised engagement and requires an eligible registered supervisor.
- A supervisor must be verified, discipline-compatible, different from the freelancer, and a participant authorised for the relevant project/ticket.
- Once an engagement begins, the supervision route cannot be replaced. Exceptional change requires closing/cancelling the engagement and creating a replacement engagement with an audit trail.
- A deliverable cannot become externally issuable until its current immutable revision is approved by the locked supervisor.
- “Paid” means payment evidence was recorded by an authorised organisation user with a reference and date. The platform does not claim bank, escrow or EFT execution.
- Reviews are allowed only once per reviewer/engagement after completion; no seed, synthetic or self-review is accepted.

## Data model

Migration `0055_freelancer_job_ticket_marketplace.sql` adds organisation-scoped binary identifiers and foreign keys for:

1. `job_tickets` — poster, optional project, parent, invitation mode, budget/deadline, advisory classification snapshot, lifecycle and optimistic version.
2. `job_ticket_direct_invites` — private recipient allow-list and response state.
3. `job_ticket_classification_revisions` — immutable automatic/manual classification history.
4. `job_ticket_clarifications` — public equal-information notices or direct-ticket private notices.
5. `job_ticket_quotations` — bidder organisation/user, role, scope, price, status and immutable submission hash.
6. `job_ticket_expansions` — parent/child role edge created only by the current holder.
7. `freelancer_profiles` — user-owned supervised profile, discipline, registration evidence references, availability and public-safe fields.
8. `freelancer_supervision_routes` — ticket engagement, freelancer, supervisor, discipline rule, assignment and lock state.
9. `freelancer_engagements` — accepted freelancer quotation, holder organisation, scope, rate/currency and lifecycle.
10. `freelancer_timesheets` plus `freelancer_timesheet_entries` — submitted period and immutable dated work entries.
11. `freelancer_claims` plus `freelancer_claim_timesheets` — claim totals, approval and recorded-payment evidence.
12. `freelancer_deliverables` plus `freelancer_deliverable_revisions` — stable deliverable identity and immutable file-manifest revisions.
13. `freelancer_deliverable_signoffs` — supervisor decision against an exact revision hash.
14. `freelancer_performance_reviews` — one completed-engagement review per reviewer.

Indexes cover public discovery, direct-recipient lookup, organisation/project trees, holder work, supervisor queues and freelancer execution queues. Cross-organisation references are validated in service transactions rather than inferred from identifiers supplied by the browser.

## Domain policy

`JobTicketMarketplacePolicy` owns the deep, framework-independent rules:

- taxonomy and keyword scoring;
- poster, bidder and expansion role capabilities;
- invitation validation and visibility;
- monetary, date and lifecycle validation;
- advisory match scoring with reasons and deterministic tie-breaking;
- the complete freelancer-to-supervisor compatibility map;
- canonical hashes for classifications, quotations, timesheets, claims and deliverable revisions;
- completion, external-issue and review gates.

Unknown taxonomies, disciplines, roles or states fail closed. Classification confidence is evidence-derived and never described as professional or statutory certainty.

## Service boundaries

`JobTicketMarketplaceService` is the single transactional application service. It reuses the existing PDO graph, `ProjectRecordService`, `InboxService` and `AuditService`.

Core operations:

- create/list/read/classify/override/clarify tickets;
- discover public/direct/recommended/active tickets;
- submit/list/withdraw/select quotations;
- expand and read an arbitrary-depth tree with cycle/depth protection;
- create/update/publish freelancer profiles;
- assign and lock supervision while accepting freelancer work;
- submit/review timesheets;
- submit/approve/reject/record-payment claims;
- submit/revise/sign off deliverables and expose external-issue readiness;
- complete engagements/tickets and record a performance review.

Every write obtains row locks as needed, checks organisation/user authority from persisted membership/profile data, uses optimistic versions where a human edits mutable state, and appends material evidence in the same transaction.

## Authenticated HTTP API

Routes use the existing session, CSRF, rate-limit and governance middleware. The surface is grouped beneath `/api/v1/job-tickets`, `/api/v1/freelancers` and `/api/v1/freelancer-engagements`:

- tickets: create, discovery/list, detail/tree, classification override, clarification;
- quotations: submit, list, withdraw and accept;
- expansions: create from an assigned parent;
- freelancer profile: mine/upsert/publish and public-safe lookup;
- engagements: detail, supervision assignment/lock, timesheets, claims, deliverables and completion;
- reviews: create only after completion.

Domain errors map consistently to 403, 404, 409 and 422 without revealing direct invitations or cross-tenant entities.

## Canonical V1 workspace

The existing RFQ Marketplace remains unchanged. A dedicated Job Ticket Marketplace workspace is added to the canonical shell and tool registry with:

- role-aware ticket composer and visible platform-fee disclosure;
- public/direct/recommended/active discovery filters;
- classification card with reasons and explicit advisory/manual-override language;
- quotation comparison and human selection;
- recursive JV tree and holder-only expansion form;
- freelancer profile and supervision-gate cards;
- engagement execution tabs for timesheets, claims, payment evidence and deliverables;
- supervisor review queue and external-issue readiness;
- closeout/review controls and complete audit-facing status history.

All controls have accessible names, live status feedback, keyboard operation and a 390 x 844 no-horizontal-overflow layout.

## Verification and staged release

Implementation is test-first in dependency order: policy, schema, service, HTTP, workspace, then release evidence. Certification requires focused and full PHP suites, all migrations on a guarded disposable MariaDB database, authenticated client-to-BEP-to-freelancer browser execution, cross-tenant/direct-privacy failures, supervisor-gated deliverable issue, recorded-payment wording, desktop/mobile inspection, console/network inspection, Lighthouse, exact-commit artifact build, fresh ZIP verification and Pack 29 relocation to `packs/Completed` with its source ZIP retained in `packs/ZIP`.

FTP upload, production credentials, remote migration, production payment integration and staged-host promotion remain separate remote gates.
