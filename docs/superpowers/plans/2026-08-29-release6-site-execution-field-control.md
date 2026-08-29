# Release 6 site execution and field control implementation plan

## Production boundary

- Packs 9 and 10 are independently re-evaluated. Their 27-file payloads are identical except for `IMPLEMENTATION_TODO.md`; both supplied pack directories remain untouched, and their combined requirements are implemented once under `site_execution_field_control`.
- All data is tenant- and project-scoped in MariaDB. Field evidence uses the existing private file store and records optional location metadata without exposing files publicly.
- Formal site instructions require an authorised professional or principal-agent confirmation. Issued instructions are immutable; a later instruction supersedes rather than silently edits one.
- Delay/EOT and programme-impact records are evidence and recommendations only. They never grant contractual entitlement automatically.
- Internal inspections never imply municipal or statutory approval. Candidate or assistant inspection records require identified professional supervision before issue.
- Open NCRs, critical snags and failed/follow-up inspections can recommend payment blockers, but cannot move money or silently alter a certificate.
- Preserve canonical `v1.html` byte-for-byte and attach the project-aware Site Execution workspace through the production shell lifecycle.

## Delivery sequence

- [x] Compare every Pack 9 and Pack 10 file by relative path and SHA-256, then read their brief, implementation note, module specification, manifest and TODOs as undone requirements.
- [x] Add migration `0031` for daily logs, field evidence, RFIs/responses, site instructions, NCRs/corrective actions, snags, inspections/checklists, delay warnings, programme impacts, payment blockers and recommendations.
- [x] Add failing MariaDB service tests for tenant isolation, evidence ownership, every specified state machine, immutable issue/supersession, supervision/authority guardrails and blocker lifecycle.
- [x] Implement the connected service scenario: daily log → evidence → RFI → response → site instruction → variation flag → NCR → snag → inspection → delay warning → programme impact → payment blocker.
- [x] Emit ProjectRecords, inbox events, audit events and advisory recommendations transactionally at controlled workflow boundaries.
- [x] Add authenticated project-scoped PHP routes with CSRF and existing project read/write permission middleware.
- [x] Add HTTP tests for authentication, tenant isolation, CSRF, generic failures and the principal-agent/supervision issue gates.
- [x] Replace Site Diary, RFIs & Instructions and Quality & Snags placeholders with a project-aware workspace that hydrates from MariaDB and exposes valid human-confirmed transitions.
- [x] Run focused PHP tests, syntax checks, migration and backup/restore regression, then apply `0031` to the persistent local MariaDB.
- [x] Use the persistent Chrome DevTools page for authenticated create/transition/reload proof, responsive and accessibility audits, console/network inspection and direct MariaDB readback.
- [x] Record Release 6 local evidence while retaining shared-host MariaDB, FTP staging, TLS/domain/docroot, cron, SMTP and production promotion as separate gates.
- [ ] Run the final aggregate full-repository regression after Releases 6–8 are implemented.
