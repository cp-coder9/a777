# Release 13 — Standalone Tool Tiles plan

## Scope

Implement Pack 17 as a native extension of the governed PHP/MariaDB toolbox. Replace the supplied localStorage/sample-calculation approach with tenant-scoped server persistence, authenticated APIs, real calculators and integrity-checked exports. Keep the existing project-guided toolbox view available.

## Delivery sequence

1. Add failing connected tests for post-run external/internal assignment, immutable issued runs, the BoQ takeoff calculator, structured standalone site-diary records and role-authorized HTTP access.
2. Extend the existing toolbox engine with audited post-run assignment for completed runs only; do not mutate issued, failed or superseded evidence.
3. Add real `boq_takeoff` and `site_diary_entry` definitions with strict input validation and deterministic outputs. Reuse the existing fee, FenCalc and R-value definitions for the other three priority tools.
4. Replace the prototype Workspace Tool Registry screen with a two-mode experience: AI-guided/project workflow and searchable role-scoped standalone tiles.
5. Drive tile availability from the authenticated role and real backend definitions. Mark project-only or unavailable capabilities honestly; never route a tile to a fake result.
6. Add recent MariaDB run counts/history, external/offline assignment, internal project assignment and JSON/CSV/HTML download controls.
7. Certify through connected tests, authenticated HTTP, live MariaDB queries and persistent Chrome DevTools at desktop/mobile sizes.

## Guardrails

- Standalone runs belong to one tenant and one user; another user or organisation cannot read, assign or export them.
- Assignment accepts an internal project ID or human-entered external project/reference, but never implies that an external job exists in Architex.
- Issued snapshots and their hashes remain immutable.
- Site-diary outputs are structured human records, not verified weather, attendance, delivery or progress facts.
- The full 43-tool catalogue is a discovery contract. A tile is actionable only when backed by a real native workspace or production toolbox definition.
- Canonical `v1.html` and supplied `packs/` remain untouched and untracked.
