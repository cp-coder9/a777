# Release 3 compliance calculators: local certification

**Date:** 2026-08-28  
**Scope:** Local PHP 8.3 and MariaDB implementation evidence only  
**Release boundary:** This is not shared-hosting staging or production certification.

## Safety and reliance boundary

The supplied numerical tables are installed as the immutable source version `implementation-seed-v0.1` with status `review_required`. They are suitable for implementation review and advisory pre-checks only. The application does not present them as official SANS text, municipal approval, professional signoff, or legal compliance.

Formal issue is fail-closed until an organisation administrator creates a distinct immutable source version, records its source reference and checksum, verifies it, and a professional explicitly confirms issue. The implementation seed cannot be promoted to `verified`.

## Implemented capability

- Governed, tenant-scoped compliance-rule versions with immutable payloads and SHA-256 checksums.
- Owner/administrator-only source creation and verification.
- Fenestration XA calculator with per-element and aggregate findings.
- R-value thermal-assembly calculator.
- SANS forms and evidence checklist builder.
- Drawing metadata compliance pre-check.
- Governed ToolRun history, evidence exports, assignments, revisions, audit events, action cards, and verified-source issue gating.
- Native canonical V1 workspace; no iframe or copied standalone pack shell.

## Automated evidence

- Rule registry integration: 2 tests, 20 assertions.
- Calculator service integration: 2 tests, 54 assertions.
- Production compliance tools: 1 test, 17 assertions.
- Toolbox and calculator focused regression: 4 tests, 69 assertions.
- Compliance HTTP workflow: 1 test, 20 assertions.
- Frontend compliance workspace contract: 1 test, 19 assertions.
- Applicable unit, requirement, and HTTP suite: 113 tests, 317 assertions.
- `node --check app/public/assets/js/shell.js`: passed.
- PHP syntax checks for all new and changed PHP files: passed.
- `git diff --check`: no whitespace errors; only repository line-ending warnings.

The production-tool and HTTP integration tests use the disposable test database to prove that a newly created verified source can issue a run and persist its immutable evidence safely. No verified source was fabricated in the live development organisation.

## Migration evidence

Migration `0020_compliance_rule_versions.sql` was applied to the bounded local development database. The migration runner reported **20 applied, 0 pending**.

## Persistent Chrome DevTools evidence

The authenticated owner session remained open at `http://127.0.0.1:8790/` in the persistent Chrome page. The native **SANS 10400-XA Energy** workspace was opened through the visible V1 tool surface.

Installed review source:

- Rule version ID: `69d661f85885f60c14a9ebb67588d51a`
- Version: `implementation-seed-v0.1`
- Status: `review_required`
- SHA-256: `564fc60bb8640392cc146af59ffb123d57590778250009850cc8d8aa66cfa26f`
- Formal-issue controls: disabled for all four tools.

Visible executions through the real HTTP API:

| Tool | Verdict | Evidence |
|---|---|---|
| Fenestration XA | `fail` | 3 findings: U-value, effective SHGC and glazing-ratio limits exceeded |
| R-value thermal assembly | `pass` | Assembly R-value passed the configured advisory target |
| SANS forms checklist | `watch` | A, A19 and XA selected; 12 evidence items; competent-person warning |
| Drawing metadata pre-check | `fail` | 5 findings: north point, professional/signature block, dimensions, room labels and unresolved A-999 reference |

Responsive and persistence checks:

- Mobile emulation at 390 x 844: workspace present, four forms, four history records, horizontal overflow `0`.
- Desktop restored to 1440 x 900 and workspace left open.
- After reload: one source, four saved runs, every source selector retained the installed source, every formal-issue control remained disabled, horizontal overflow `0`.
- Console after reload: no warnings or errors.

## Direct MariaDB evidence

The bounded local database contains the immutable rule version above and these four live development ToolRuns:

| Run ID | Definition | Status | Verdict | Source status | Issue eligible |
|---|---|---|---|---|---|
| `463f7bb2c47fd7e4f39752ba8c9062bd` | `fenestration_xa_calculator` | `completed` | `fail` | `review_required` | `false` |
| `19321990b147b6e1b0ecc4a0bea307ab` | `r_value_thermal_calculator` | `completed` | `pass` | `review_required` | `false` |
| `438a7d453fc5cfcdb9787adac73a0ddb` | `sans_forms_checklist_builder` | `completed` | `watch` | `review_required` | `false` |
| `fd5eb58824567c32a161574ac8d59fdc` | `drawing_compliance_precheck` | `completed` | `fail` | `review_required` | `false` |

Direct counts:

- Evidence exports for the four runs: 12.
- `tool_run.completed` audit events: 4.
- Compliance ToolRuns formally issued in the live development database: 0.

An unrelated earlier non-compliance ToolRun is issued in the same development database; it is not compliance evidence and is deliberately excluded from the certification claim.

## Remaining release gate

This evidence certifies the local implementation and bounded local database behavior. Shared-hosting staging still requires FTP deployment, staging MariaDB migration, environment/secrets configuration, staged browser/API/database checks, and an approved promotion/rollback decision.
