# Material 3 Expressive — implementation design contract

## Non-negotiable brand preservation

Preserve exact palette literals: primary `#19B7B0`, deep `#167E79`, jade `#58C8BC`, mint `#BFE9E2`, aqua `#DFF5F2`, ink `#102033`, muted `#657287`, cobalt `#2563EB`, lavender `#8B5CF6`, coral `#FF6B6B`, amber `#FFB020`, success `#28a86b`, error `#d95747`. Material semantic aliases must reference these existing values; no replacement purple theme, automatic palette generation or silently normalised literals.

Keep `Inter, ui-sans-serif, system-ui` as the leading font stack. Preserve the existing embedded PNG logo byte-for-byte and its aspect ratio; do not redraw it, recolour it, substitute an icon or replace it with text. Preserve origami-inspired semantic icon family, fold geometry, existing tones and module meanings. Icon contact-sheet implementation is outside this document's ownership. Brand source: `app/tests/Frontend/material3-expressive-brand.test.cjs` and `app/views/v1-clone.php`; tests describe acceptance requirements, not execution evidence here.

## Expressive hierarchy without changing authority

Use destination → selected project → stage → workspace → source record as the information hierarchy. The six registry destinations and eight stage keys remain exact. Keep global and project menus, standalone mode and native/iframe entry points discoverable without claiming a scaffold is functional. Use consistent card, table, form, advisory-result, source-evidence, human-review and unavailable families. Prioritise current task, required inputs, next responsible reviewer and status over decorative metrics.

Support compact and wide layouts, keyboard traversal, visible focus, meaningful headings, labels and error associations, screen-reader status announcements, reduced-motion preference, non-colour status cues and stable asynchronous layouts. Motion may orient; it must not imply saving or approval. Do not hide critical errors inside transient toast-only messages. Preserve unsaved form data during recoverable failures without leaking it across tenant/project changes.

## Permission and presentation model

Saved profile roles (21), demo lenses (22) and membership roles (4) are distinct namespaces. Profile recommendations are not action allowlists. Demo project allowlists are explicit per lens and contain synthetic project keys, not production tenant grants. `PermissionService` controls membership capability; `ProductionToolRegistry::workspaceAccess` controls discovery; the source operation controls tenant, project appointment, professional registration and human approval. Viewer and unknown membership never become administrators through a profile label or lens.

Workspace read gates default to projects.read; bom, xa, engineering_calc and drawing_compliance_navigator require tools.use; admin_review requires verification.review. Write access depends on the source operation as well as discovery. Do not equate a write-capable membership with professional issue authority. Recommendations and menu badges must be derived from the returned scoped catalogue, not fixture counts.

## Evidence and output contract

Distinguish registry availability claim, original prototype, mounted enhanced module, source operation and verified live journey. Tutorial goals describe desired work; they are not implementation receipts. Source-linked action inventories in coverage.json and module-prd.md are static evidence, not tested browser availability. Record reference, source version, units, assumptions, author/reviewer and saved revision with advisory outputs. Do not invent product results, external market benchmarks, drawing parsing, certifications, payment settlements or municipal decisions.

Render authentication_required, needs_project, loading, ready-empty, ready-records, denied, stale-version, source-unavailable, provider-unavailable and error as distinct states. Read-back verification is required after writes. Changing organisation/project invalidates old pending requests and records. Confirm consequential actions; do not approve, issue or deploy by merely resolving an inbox item. Unsupported actions are visibly unavailable with an owner/reason, never silent no-ops.

## Integration boundaries

Payments and escrow are internal metadata/release governance, not money movement or custody. Remote desktop needs host agent, broker, viewer and allowlist; no session is available merely because governance exists. Municipal readiness and internal submission packs do not lodge an application or grant approval. NHBRC entries are internal evidence. CPD certificates/learning records are not accreditation without real professional-body evidence. AI provider failure leaves drafting unavailable; generated text is not a saved record, professional signature or authority action.

## Delivery and verification gates

The source refresh command is `node tools/v1-clone/material3-coverage.mjs`; validation is `node --test tools/v1-clone/coverage.test.cjs`. Source hashes are byte observations at refresh time, not claims of ownership over concurrent production-app changes. Exact profile/lens/membership/tool/stage keys are retained. Tests independently parse the PHP demo lens registry (including constant-backed project arrays), compare every lens/category/project field, and compare profile and membership keys to their PHP sources and tool keys to the public registry. All inventoried source hashes are checked against current bytes. Separate fixed SHA-256 assertions in coverage.test.cjs pin reference-002/003/004 so refreshing observational hashes cannot silently accept renderer-fixture drift. Isolated trusted renderer execution yields 668 candidate rows across 840 role/stage/version contexts; reference-001/002/003/004 contribute 144/134/135/255 rows. Actual datum-card handlers preserve version-specific role prioritisation, stage maps, common tools and 10/12-card ceilings rather than expanding privileges. These are renderer/source regressions, not live passes. The literal menu-call list is not exhaustive: generated templates, concrete candidate rows, the preview3 iframe entry and every role-recommendation operating-guide link are separate matrices. Unsupported v1-clone mounts must stay visibly unsupported even when a legacy app.php surface or API exists.

coverage.json is the machine acceptance matrix: exact roles/lenses/mappings, project/category allowlists, memberships, menus, module placements/actions, iframe/public source inventory and source hashes. role-guides.md supplies role-specific evidence/handoff instructions. module-prd.md supplies every registry tool's inspected controls and errors.

Required subsequent evidence: source-key drift check; every global/menu/direct/iframe route; all roles and lenses against all four memberships; all eight stages; relevant native write + exact readback + human gate; negative tenant/permission/professional tests; responsive/keyboard/reduced-motion/visual checks. Static source inspection does not satisfy these live gates. The delegated context reports port 8790 HTTP 503: live evidence is blocked, not passed. No commit, deployment or app implementation is claimed by these documents.
