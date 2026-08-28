# Release 3 Refuse Area Calculator implementation plan

## Source and approval boundary

- Use official national and municipal waste-law guidance only as versioned source pointers; do not claim that a locally entered rate is current until an organisation governor verifies that exact rule payload.
- Treat waste-generation rates, collection intervals, bin specifications, circulation allowances, municipal requirements, and project quantities as human-entered evidence with a source, version, effective date, and checksum.
- Keep every result advisory: it is a pre-check and evidence aid, not municipal approval, waste-service confirmation, or professional certification.
- Allow immutable issue only from a verified non-seed rule version with explicit professional confirmation and independently reviewed authority.

## Delivery sequence

- [ ] Add failing MariaDB integration tests for refuse rule governance, deterministic capacity/area calculations, checklist findings, fail-closed inputs, formal issue, exports, records, inbox actions, audit, and tenant isolation.
- [ ] Extend the governed compliance registry with a distinct `municipal_refuse` schema and an unverified implementation seed that can never be formally issued.
- [ ] Implement and register the Refuse Area Calculator with source snapshots, action cards, calculation trace, capacity/area verdicts, and conservative limitations.
- [ ] Replace the Refuse Area Calculator scaffold with a project-aware workspace without changing the canonical supplied interface.
- [ ] Run focused tests, syntax/diff checks, live seed creation, Chrome end-to-end persistence, fail-closed and successful paths, exports, responsive checks, and console/network checks.
- [ ] Record exact local evidence and keep FTP staging/promotion as separate release gates.
