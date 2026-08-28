# Release 4 Tender, RFQ, Procurement and Marketplace implementation plan

## Production boundary

- Keep `v1.html` unchanged and mount the workflow inside its existing RFQ Marketplace and procurement surfaces.
- Use PHP 8.3 and MariaDB only; keep the schema and deployment compatible with FTP-only shared hosting and control-panel cron.
- Treat matching and comparisons as advisory. Never auto-award, conceal exclusions or qualifications, or claim an invitation was emailed while SMTP is unavailable.
- Preserve equal material information: a material clarification may only be answered through an immutable addendum distributed to every active bidder.
- Require distinct client and responsible-professional approvals before creating an appointment placeholder. The placeholder is not a signed contract or appointment.
- Bind every write to its owning organisation and project. Cross-organisation marketplace discovery exposes only approved public-safe listing fields.

## Delivery sequence

- [x] Add migration `0028` for tenant-owned marketplace listings, procurement packages, immutable package revisions, matches, invitations and high-entropy access tokens, clarifications, addenda/distributions, quote revisions/returnables, comparisons, award recommendations, dual approvals and appointment placeholders.
- [x] Add failing MariaDB integration tests for tenant isolation, public-safe marketplace search, verification/supervision flags, method/type classification, package completeness, advisory matching and conflict disclosure.
- [x] Implement the procurement service for draft packages, controlled issue snapshots, marketplace matching and auditable bidder invitations without delivery-provider claims.
- [x] Add failing tests and implement token-scoped bidder questions and quote revisions, deadline/late-submission evidence, exact returnables, exclusions, qualifications and conflict declarations.
- [x] Enforce material-addendum equal distribution and immutable distribution evidence; never mutate an issued package or prior quote revision.
- [x] Add deterministic comparison snapshots covering price, lead time, completeness, exclusions, qualifications, risk and conflicts without hiding raw bidder facts.
- [x] Add award recommendation, separate client/professional approval gates and an appointment placeholder/ProjectRecord only after both approvals.
- [x] Route ProjectRecords, inbox actions, audit events and human-gated next-best actions for each material procurement event.
- [x] Add authenticated PHP API routes plus narrowly scoped external invitation-token routes, CSRF/rate-limit controls and generic failure responses.
- [x] Replace the RFQ Marketplace scaffold with project-aware listing, package, bidder, clarification, comparison and approval views while preserving the canonical shell.
- [x] Verify focused and regression suites, PHP/JS syntax, local migration compatibility, Chrome end-to-end flows across organisation and bidder contexts, reload persistence, responsive desktop layout, console/network health and direct MariaDB evidence. Full backup/restore and release regression remain aggregate certification gates.
- [x] Record exact local evidence and keep FTP staging, SMTP, remote MariaDB, TLS/domain/docroot, cron and production promotion as separate release gates.
