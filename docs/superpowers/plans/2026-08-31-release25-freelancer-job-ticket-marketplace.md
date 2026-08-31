# Release 25 freelancer / supervised professional job-ticket marketplace plan

**Design:** `docs/superpowers/specs/2026-08-31-freelancer-job-ticket-marketplace-design.md`  
**Pack:** `packs/29. architex-freelancer-candidate-professional-pack` (all supplied files read in full)  
**Execution:** inline, test-first, sequential commits

## 1. Production domain policy

- [x] Add failing unit tests for role capabilities, ticket validation, deterministic classification, manual override provenance, direct visibility, matching, supervision compatibility and canonical hashes.
- [x] Implement `JobTicketMarketplacePolicy` with the complete Pack 29 taxonomy and supervision map.
- [x] Prove unknown roles/disciplines/states and misleading payment/AI/statutory claims fail closed.
- [x] Run focused tests and PHP syntax checks.
- [x] Commit the policy contract.

## 2. MariaDB persistence

- [x] Add migration `0055_freelancer_job_ticket_marketplace.sql` for tickets, direct invites, classification revisions, clarifications, quotations, expansions, profiles, supervision, engagements, timesheets, claims, deliverables, sign-offs and reviews.
- [x] Add schema tests for foreign keys, public/direct discovery indexes, uniqueness, immutable hash fields and optimistic versions.
- [x] Rebuild a guarded disposable database through all 55 migrations and run existing migration regressions.
- [x] Commit the schema.

## 3. Ticket and marketplace lifecycle

- [x] Add connected tests for public/direct creation, privacy-safe discovery, advisory recommendation and tenant boundaries.
- [x] Implement ticket creation, automatic classification history, human override and equal-information clarification.
- [x] Add quotation submit/withdraw/list/select with visibility, bidder-role, poster-authority and single-holder gates.
- [x] Add holder-only recursive expansion and stable tree reads with depth/cycle protection.
- [x] Write project records, inbox events and audit events transactionally.
- [x] Commit the ticket lifecycle.

## 4. Freelancer supervision and execution

- [x] Add connected tests for profile publication, eligible supervisor selection, route locking and freelancer acceptance.
- [x] Implement supervised profile and engagement creation without independent freelancer appointments.
- [x] Add timesheet submit/review and claim submit/approve/reject/record-payment with explicit payment-evidence wording.
- [x] Add immutable deliverable revisions and exact-revision supervisor sign-off; reject external issue before approval.
- [x] Add governed completion and completed-engagement-only reviews.
- [x] Prove tenant isolation, self-supervision rejection, route immutability and review uniqueness.
- [x] Commit the freelancer execution lifecycle.

## 5. Authenticated API

- [x] Add HTTP tests for ticket, discovery, quotation, expansion, profile, supervision, timesheet, claim, deliverable, completion and review routes.
- [x] Prove CSRF, role, direct-privacy, cross-tenant and stale-version failures.
- [x] Implement controller methods and routes with existing authentication/read/write/govern middleware.
- [x] Wire the service through `AuthenticationKernelFactory` without a second dependency graph.
- [x] Map stable domain errors to 403, 404, 409 and 422.
- [x] Commit the API surface.

## 6. Canonical V1 workspace

- [x] Add failing frontend contract tests for the composer, discovery lanes, classification, quotations, JV tree, profile, supervision and execution controls.
- [x] Add a dedicated live tool route while preserving the existing governed RFQ marketplace.
- [x] Bind the complete authenticated lifecycle with accessible controls and live status output.
- [x] Add responsive CSS for 390 x 844 without horizontal overflow.
- [x] Exercise client posting, BEP acceptance/expansion and supervised freelancer execution through the real UI against MariaDB.
- [x] Commit the workspace.

## 7. Release certification

- [x] Run PHP/JavaScript syntax, focused Release 25 tests and the complete suite.
- [x] Rebuild all migrations on a reserved disposable MariaDB target and record exact tests/assertions/skips.
- [x] Verify public/direct privacy, full ticket tree, locked supervision, payment-evidence wording and deliverable sign-off in MariaDB.
- [x] Inspect desktop and 390 x 844 views through the persistent Chrome DevTools MCP session, including console, network, accessibility and horizontal overflow.
- [x] Capture screenshots and Lighthouse evidence.
- [x] Build and fresh-extract the staged shared-hosting ZIP from the exact certified functional commit.
- [x] Verify release manifest, PHP syntax, file counts and SHA-256.
- [x] Write Release 25 evidence with local-versus-remote boundaries.
- [x] Move Pack 29 into `packs/Completed` outside Git and retain its source ZIP under `packs/ZIP`.
- [x] Check every plan item and commit certification documentation.

## Release boundary

Release 25 is locally complete only when its exact source commit, guarded MariaDB lifecycle, authenticated browser workflow and staged FTP artifact all pass. FTP transfer, shared-host backup/rehearsal, remote migration, production payment-provider integration, remote smoke tests and staged promotion remain explicitly uncertified until separately performed.
