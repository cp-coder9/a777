# Architex Releases 2-3 Implementation Plan

**Goal:** Deliver the production professional-onboarding, appointment, document-control and compliance workflows defined by the supplied packs, without changing the canonical V1 visual shell.

**Constraints:** PHP 8.3, MariaDB, shared hosting, FTP-only deployment, control-panel cron, multi-tenancy, public registration, staged release, no simulated external providers, and no pack item credited without executed evidence.

## Dependency order

1. **Trust and directory foundation**
   - Persist professional directory profiles, verification evidence and review history.
   - Distinguish self-declared, document-uploaded, manually-reviewed and externally-verified badges.
   - Capture consent, purpose and retention for sensitive evidence; calculate expiry without pretending an external integration exists.
   - Expose tenant-scoped profile/submission/review APIs and a public-safe verified directory search.

2. **Teams and guided briefs**
   - Persist invitations, membership duties, project-team assignments and revocation history.
   - Add resumable, versioned guided briefs with project/property/scope/budget/timeline/consent sections.
   - Convert approved briefs into Project Passport records and auditable inbox events.

3. **Toolbox engine and fee calculators**
   - Add concrete tool definitions, validated run lifecycle, optional project assignment, history and immutable issued snapshots.
   - Implement versioned formula definitions for sliding-scale, percentage, stage, time, area and hybrid calculations.
   - Preserve original calculated fees, apply governed professional-fee-only discounts, and label outputs as professional decision support.

4. **Proposal, terms and acceptance**
   - Persist draft/revised/issued/superseded proposals, versioned terms and exact calculator snapshots.
   - Require discount reasons and keep statutory fees/reimbursables outside the default discount base.
   - Record separate client acceptance and professional responsibility confirmation.

5. **Appointment and kickoff**
   - Convert an accepted proposal atomically into an immutable appointment revision, linked project, passport baseline, kickoff checklist and first work-stage tasks.
   - Block readiness until the pack-required project, municipality, property, scope and terms facts exist.

6. **Meetings, contextual messages and forms**
   - Add project-scoped meeting records, decisions, actions and contextual message threads.
   - Add versioned form templates, drafts, approvals and audit history without claiming unsupported PDF/e-signature providers.

7. **Release 3 evidence layer**
   - Implement document/drawing registers, revisions, transmittals and immutable issue history first.
   - Build SpecForge, BIM/IFC metadata, calculators, compliance hub, municipal/planning/environmental/survey tools and evidence packs on that spine.
   - Require professional review for regulated or safety-significant outputs.

8. **Certification gates**
   - Unit/integration/security tests, full MariaDB migration run, exact V1 browser flows in Chrome DevTools, immutable FTP artifact, encrypted backup and guarded restore.
   - Record local proof separately from staged-host proof; do not promote without SMTP, TLS/domain/docroot, remote MariaDB, cron and uploaded-manifest verification.
