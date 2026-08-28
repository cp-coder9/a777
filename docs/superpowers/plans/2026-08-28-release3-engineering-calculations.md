# Release 3 Engineering Calculation Governance Plan

## Goal

Turn the canonical Engineer's Calculation Hub into a real tenant-scoped PHP 8.3 / MariaDB workflow without replacing its supplied calculator interface. Calculations remain advisory working evidence until a human accepts the stated professional boundary; issued runs become immutable project records with reproducible exports.

## Pack contract

- Treat every toolbox and BEP calculator pack requirement as unimplemented until demonstrated here.
- Register concrete, versioned tools rather than routing through a generic fallback.
- Support standalone and internal-project assignments, history, revisions, and JSON/CSV/HTML exports.
- Record exact inputs, formula version, assumptions, results, user role, timestamp, and SHA-256 issue hash.
- Never claim that a calculator certifies compliance or replaces competent professional judgement.
- Require explicit human professional confirmation for issue-eligible engineering results.
- Preserve the canonical `v1.html` visual workspace and connect its surrounding workflow controls to the backend.

## Scope

1. Add deterministic, validation-first services for:
   - simply-supported beam quick check;
   - Rational Method runoff;
   - voltage-drop check using user-supplied conductor resistance/reactance;
   - concrete quantity;
   - masonry quantity;
   - paint/tile quantity;
   - tender rate build-up;
   - labour productivity.
2. Register all eight as first-class toolbox definitions with their own routes and versioned schemas.
3. Strengthen the toolbox issue gate for tools that declare a responsible professional role.
4. Add a canonical-shell controlled-record panel, run form, history, immutable issue evidence, and export controls while leaving the embedded calculators intact.
5. Prove domain and HTTP behavior against disposable MariaDB, guard regressions, migrate the live local database, and verify browser -> API -> MariaDB persistence in Chrome DevTools.

## Verification gates

- [x] Domain tests cover correct results, units, boundary validation, and professional responsibility metadata.
- [x] Toolbox tests prove role access, standalone/project operation, failed issue without explicit confirmation, immutable issue hash, exports, history, revision, ProjectRecord, and audit events.
- [x] HTTP tests prove authenticated tenant isolation and concrete definitions/routes.
- [x] Frontend tests prove the canonical engineering workspace is retained and demo-only save behavior is gone.
- [x] Relevant regression suites, PHP syntax, JS syntax, and `git diff --check` pass.
- [x] The complete schema applies to disposable MariaDB and the live local database has zero pending migrations (no new migration was required for this slice).
- [x] Chrome DevTools proves a project calculation is saved, issued with confirmation, reloaded, exported, and persisted exactly in MariaDB with no browser errors.
- [x] Desktop and 390px mobile layouts have no horizontal overflow.
- [x] Evidence report records exact commands, IDs, hashes, browser requests, and the local/staging boundary.

## Explicit exclusions

- No claim of SANS compliance, structural adequacy, municipal approval, or professional certification.
- No proprietary standard tables or licensed design values are embedded.
- No staging/FTP release claim until the separate staged deployment gate is executed.
