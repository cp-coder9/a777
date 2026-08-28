# Release 3 Survey & Geomatics implementation plan

## Source and professional boundary

- Use official Geomatics Profession Act, SAGC, Land Survey Act, and Chief Surveyor-General references as versioned source pointers; do not reproduce restricted cadastral systems or claim Surveyor-General examination, approval, registration, or official coordinates.
- Treat coordinates, control points, cadastral references, beacons, servitudes, diagrams, outputs, and fees as human-entered evidence with explicit provenance.
- Require current independently reviewed professional registration and an active `land_surveyor` project responsibility before an immutable survey issue can be created.
- Keep private professional fees separate from statutory / Surveyor-General product fees and preserve the exact source, retrieval date, assumptions, and calculation inputs.

## Delivery sequence

- [ ] Add failing MariaDB integration tests for survey briefs, control points, deliverables, fee separation, professional issue, stale writes, and tenant isolation.
- [ ] Add authenticated HTTP tests for CSRF, project scope, evidence capture, authority boundaries, and exports.
- [ ] Add forward-only MariaDB tables for survey jobs, observations/control points, deliverables, and immutable issue snapshots.
- [ ] Implement PHP services, controllers, routes, permissions, audit events, project records, and JSON/CSV/HTML exports.
- [ ] Replace the Survey & Geomatics scaffold with a project-aware governed workspace without changing the canonical supplied interface.
- [ ] Run focused tests, syntax/diff checks, live migration, Chrome end-to-end persistence, fail-closed and success roles, exports, responsive checks, and console/network checks.
- [ ] Record exact local evidence and keep FTP staging/promotion as separate release gates.
