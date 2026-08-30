# Nuanced QC Control — Production Design

## Decision and boundary

Pack 27 will become a dedicated, tenant-scoped Nuanced QC module. It will integrate with the existing Platform Completeness control plane without storing its 35-category run model inside the narrower completeness-review table. Every supplied pack file is treated as an unimplemented requirement.

The production boundary is PHP 8.3, MariaDB, shared hosting and FTP deployment. QC evidence and recommendations are decision support; they do not constitute legal advice, professional certification, municipal approval or automated acceptance. Authenticated people remain accountable for review, risk acceptance and sign-off. Agent names such as Amy or Hymie may be recorded as assistance metadata, but never replace the authenticated human actor.

## Alternatives considered

1. **Dedicated Nuanced QC module linked to existing controls — selected.** This preserves the full run/finding/retest/sign-off model, supports organisation and project scopes, and gives the workflow a coherent API and UI while reusing FileManager, ProjectRecord, inbox and audit services.
2. **Extend Platform Completeness QC reviews.** This would reuse more existing code but cannot naturally represent arbitrary PR, repo, live-site, feature, implementation-pack and site-audit targets or one result for each of 35 categories.
3. **Embed QC fields in every feature.** This would make speak-back local but duplicate state machines, permissions and evidence rules and make cross-pack reporting unreliable.

## Roles and authority

- Organisation owner maps to `platform_super_admin`; organisation admin maps to `product_owner`.
- Owners/admins may assign `qc_reviewer`, `developer`, `professional_reviewer`, `site_auditor` or `viewer` within their organisation.
- Review roles may create runs, record category results, findings, evidence and retests according to their scope.
- Developers may respond to findings and complete assigned fixes but may not sign off their own implementation.
- Only owner/admin roles may accept a run, request changes or explicitly accept blocker risk.
- Risk acceptance requires human confirmation, a reason and an optional expiry/review date. It never deletes or hides the blocker.
- Every route enforces the current organisation membership and project visibility server-side. UI hiding is supplementary only.

## Persistence model

Migration 0053 will add:

- `qc_control_definitions`: organisation-scoped, versioned rows for each of the 35 mandatory categories.
- `qc_run_records`: title, scope type, target, optional project, reviewer, source promises, technical and pack-goal summaries, recommendation, lifecycle status and optimistic version.
- `qc_run_scope_items`: source packs/briefs, routes, files, URLs, PR/repo targets and cross-pack workflow references.
- `qc_category_results`: append-only revisions per run/category with pass, fail, partial, not-applicable and exception-state reasons; the highest revision is current.
- `qc_gap_findings`: severity, evidence type/reference, affected pack/workflow, cross-pack impact, required fix, retest steps, owner and lifecycle status.
- `qc_evidence_links`: validated FileManager links plus bounded external evidence references where a stored file is not applicable.
- `qc_retest_tasks`: accountable assignee, due date, expected test, result, evidence and optimistic version.
- `qc_risk_entries`: blocker/high real-world risks and explicit risk-acceptance metadata.
- `qc_signoffs`: immutable SHA-256 snapshots for accepted, accepted-with-notes, changes-requested, block-merge and block-deploy decisions.
- `qc_role_assignments`: organisation-scoped review roles.

Foreign keys, organisation/project indexes, idempotency keys and optimistic versions will be explicit. Category-result revisions, evidence and sign-offs are append-only. Finding and retest states use optimistic transitions with before/after audit evidence, never silent replacement.

## Run lifecycle and rules

1. Create a run for `pr`, `repo`, `live_site`, `feature`, `implementation_pack` or `site_audit` and attach at least one source promise/scope item.
2. Capture normal technical verification and a pack-goal comparison.
3. Record a result for every mandatory category. `not_applicable`, `deferred`, `manually_satisfied` and `external_system_handled` require a reason and accountable reviewer.
4. Record findings with exact evidence, required fix and retest steps. Blocker/high findings cannot be saved without evidence and retest instructions.
5. Generate retest tasks and risk entries for blocker/high findings. Project-scoped blocker/high findings also create high-priority inbox items.
6. Produce a deterministic recommendation: blocker means block; high means changes requested; incomplete categories mean continue QC; otherwise accept or accept with notes.
7. Retest findings without erasing the original evidence. A passed retest closes the finding through an audited state transition.
8. Sign-off snapshots the current run, categories, findings, retests and risk decisions. Acceptance fails closed while an unaccepted blocker remains or any category is unreviewed.

## Speak-back integrations

- A project-scoped run creates a ProjectRecord at creation and appends governed lifecycle summaries for finding, retest and sign-off changes.
- Blocker/high findings create inbox items with priority and an action target.
- Blocker/high findings create linked QC risk entries, preserving status even when risk is accepted.
- FileManager evidence is validated against the same organisation and, for project runs, the same project.
- Every governed mutation appends an AuditEvent containing the human actor, before/after state where relevant, source version and remaining blockers.
- Cross-pack scope items and finding impacts provide the report's speak-back matrix without pretending an unavailable external connector is live.

## API and V1 workspace

Authenticated routes will cover definition/workspace reads, run creation, run detail, technical summary, category results, findings, evidence links, retests, risk acceptance, sign-off and role assignment. Project routes require project membership; admin routes remain organisation-scoped.

The V1-native workspace at `/admin/qc-controls/nuanced-gap-test` will provide:

- run creation and scope/source-promise capture;
- a 35-category completion matrix;
- severity-ranked findings with evidence and retest state;
- cross-pack speak-back and risk panels;
- deterministic recommendation and blocker explanation;
- human-only sign-off controls shown according to persisted authority;
- responsive desktop/mobile layouts with no horizontal overflow.

Deep links at `/admin/qc-runs/{runId}` and `/projects/{projectId}/qc-runs/{runId}` will load the same governed workspace in the appropriate scope.

## Error handling and safety

Malformed identifiers and cross-tenant objects return non-enumerating not-found responses. Missing authority returns 403. Version conflicts, incomplete category coverage, open blockers, missing evidence and missing human confirmation return deterministic conflict/validation errors. Multi-record writes use MariaDB transactions. No endpoint invokes external systems, merges code, deploys code or signs off automatically.

## Verification and release acceptance

- Unit tests prove the 35-category registry, severity ordering, recommendation and sign-off gates.
- Migration and route tests prove schema assets and authenticated route contracts.
- Connected MariaDB tests prove tenant/project isolation, role separation, self-review prevention, FileManager scope validation, optimistic conflicts, blocker/high requirements, risk acceptance, retest history and immutable sign-off.
- Integration tests prove ProjectRecord, inbox and audit speak-back.
- Browser certification covers owner, reviewer, developer and viewer contexts plus responsive mobile, console/network evidence and an authenticated Lighthouse snapshot.
- The full suite, all 53 migrations, syntax checks, release manifest, ZIP creation and independent FTP-style extraction must pass before Pack 27 moves to `packs/Completed`.

## Autonomous assumptions

The user's `$plow-ahead Approved` instruction authorises routine design choices and continuation without repeated approval prompts. The selected design deliberately avoids external AI/agent execution, remote services and production mutation; those would require separate credentials and release authority.
