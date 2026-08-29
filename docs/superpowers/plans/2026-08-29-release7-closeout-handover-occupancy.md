# Release 7 closeout, handover and occupancy implementation plan

## Production boundary

- Treat every Pack 11 requirement as undone. The supplied pack and canonical `v1.html` remain untouched.
- Build one tenant- and project-scoped MariaDB closeout spine that consumes, but never silently mutates, field-control, finance and document-control records.
- Architex occupation readiness is an internal evidence checklist only. It never represents a municipal occupation certificate, approval or permission to occupy.
- Practical/final completion issue requires authorised professional confirmation and an immutable snapshot. Open critical snags/NCRs and missing mandatory handover evidence remain blockers.
- Final-account readiness is a recommendation over recorded commercial state. Retention release remains in the Finance workflow and no closeout action moves money.
- Defects-liability reminders, recalls and expiry checks remain human-reviewed records with audit evidence.

## Delivery sequence

- [x] Read Pack 11 brief, implementation note, specification, manifest, TODO and starter implementation as undone requirements.
- [x] Add migration `0032` for practical completion, closeout defects, occupation-readiness checks, versioned handover packs/documents, final-account readiness, defects-liability periods/reports and recommendations.
- [x] Add failing connected MariaDB tests for upstream blockers, authority, issue immutability, client acceptance, handover completeness, statutory disclaimers, final-account/retention separation and defects-liability recall/closeout.
- [x] Implement transactional services that emit ProjectRecords, inbox events, audit events and advisory recommendations.
- [x] Add authenticated project-scoped PHP routes with CSRF, tenant isolation and professional-authority gates.
- [x] Add a project-aware Close-out workspace for readiness, handover evidence, occupation checks, final account and defects-liability tracking.
- [ ] Run focused PHP/frontend tests, syntax checks, migration and backup/restore regression; apply `0032` to the persistent local MariaDB.
- [ ] Verify authenticated create/transition/reload flows, responsive layout, accessibility, console/network health and direct MariaDB evidence in the persistent Chrome DevTools page.
- [ ] Record Release 7 local evidence while keeping shared-host staging and production promotion as separate gates.
