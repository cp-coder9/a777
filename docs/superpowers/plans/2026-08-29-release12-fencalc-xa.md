# Release 12 — FenCalc XA completion plan

## Scope

Complete Pack 16 inside the native PHP/MariaDB compliance toolbox. Preserve the V1 shell and the existing versioned-rule, audit, issue and evidence-export controls; do not ship the pack's ungoverned static prototype as a parallel source of truth.

## Delivery sequence

1. Add failing calculator and frontend contracts for 1–10 storeys, multiple typed fenestration elements, all orientations/frame types, shading, room aggregation, optional SANS 10400-N lighting/ventilation checks, east/west distribution warnings, per-element/per-storey/overall summaries and print controls.
2. Publish a new review-required implementation seed containing the Pack 16 rule inputs. Existing verified rule versions and issued runs remain immutable.
3. Deepen `ComplianceCalculatorService` output with structured individual checks, room aggregates, summary percentages, total glazing/floor ratio and traceable orientation-distribution evidence.
4. Replace the single-element Fenestration form with a native multi-row editor, example loader, storey controls, address/edition/N inputs, detailed results and a print-safe report view.
5. Run connected MariaDB, fail-closed, HTTP/frontend/syntax/diff gates; verify a persisted run and export through persistent Chrome DevTools at desktop/mobile sizes.
6. Commit only application, test, plan and evidence files. Never stage `packs/` or modify `v1.html`.

## Guardrails

- Supplied table values remain `review_required` until an owner/admin records an independent official/licensed-source review on a separately created version.
- Shading factors, room areas and openable areas are human-entered; geometry is never inferred.
- Results are advisory evidence only, not municipal approval, competent-person declaration or automatic SANS sign-off.
- Existing rule versions, tool runs, issued evidence and hashes are never rewritten.
