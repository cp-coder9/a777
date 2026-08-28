# Release 3 Environmental, Heritage, and EIA implementation plan

## Source and authority boundary

- Use the DFFE national web-based environmental screening report as source evidence where an environmental-authorisation application is in scope; do not recreate or impersonate the government screening tool.
- Track site-sensitivity verification, listed-activity/EIA route opinions, specialist studies, public participation, EMPr obligations, monitoring, audits, and authority correspondence as human-entered evidence.
- Track NHRA section 38 / SAHRIS notification, heritage studies, consultation, comments, permits, and conditions without claiming SAHRA or provincial-authority action.
- Require source title, URL, retrieval date, external reference, responsible role, explicit human confirmation, and an immutable SHA-256 snapshot for professional determinations.

## Delivery sequence

- [x] Add failing MariaDB integration tests for environmental and heritage case evidence, professional determination, snapshot integrity, stale writes, and tenant isolation.
- [x] Add authenticated HTTP tests for CSRF, project scoping, evidence capture, and authority boundaries.
- [x] Add forward-only MariaDB tables for cases, evidence registers, decisions, and immutable determinations.
- [x] Implement PHP services, controllers, routes, permissions, audit events, project records, and evidence exports.
- [x] Replace both Environmental & Heritage and EIA scaffolds with one project-aware governed workspace while keeping their distinct navigation intent.
- [x] Run focused tests, syntax/diff checks, live migration, Chrome end-to-end persistence, exports, responsive checks, and console/network checks.
- [x] Record exact evidence and keep FTP staging/promotion as separate release gates.
