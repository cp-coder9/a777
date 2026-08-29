# Release 10 — Agent Orchestration Core Implementation Plan

## Production boundary

Implement Pack 14 as a deterministic, MariaDB-backed coordination layer in PHP 8.3. It may route events, create traceable recommendations, prepare contextual message drafts and request human decisions. It must never bypass RBAC, autonomously send a formal instruction, claim an external AI/provider action, or expose one user's memory/context to another user or tenant.

## Data and service design

1. Add migration `0035_agent_orchestration_core.sql` for agent identities, user/project contexts, governance policies, event routes, recommendations, contextual drafts, approval gates, memory boundaries and monitoring records.
2. Add one deep `AgentOrchestrationService` that owns tenant/project/user scoping, deterministic event routing, recommendation provenance, approval state transitions, memory projections, ProjectRecords, Inbox events and audit entries.
3. Persist immutable source/policy links on every recommendation and draft. Store all context as bounded structured JSON, never arbitrary cross-tenant memory.
4. Treat approved drafts as `approved_for_human_send`; no endpoint will transmit them. Expose `externalDispatchPerformed=false` and `permissionBypassAllowed=false` in the workspace contract.

## HTTP and authorization

1. Add project workspace, bootstrap, policy, route, event-routing, message-draft and approval-decision endpoints.
2. Require authenticated `projects.read` for workspace reads and `projects.write` for user-scoped coordination writes.
3. Require `verification.review` plus an owner/admin service-level role check for policy/route governance and approval decisions.
4. Require human confirmation for routed events, formal drafts and decisions; reject self-approval and terminal-state mutation.

## User experience

1. Replace the Wingman prototype with a project-scoped Agent Orchestration workspace while preserving the canonical V1 shell and visual language.
2. Show identity/context, active policies/routes, routed recommendations, contextual drafts, approval gates, bounded memory and monitoring.
3. Provide explicit forms for context bootstrap, governed routes, human-confirmed event routing and draft creation.
4. Keep every boundary visible: recommendations are advisory, drafts are unsent, governance is separate, and humans retain decision authority.

## Verification gates

1. Write frontend and connected MariaDB tests first and confirm the red baseline.
2. Prove user/tenant memory isolation, policy governance, traceable routing, self-approval denial, approved-but-unsent drafts, terminal immutability, ProjectRecords, Inbox and audit output.
3. Apply migration only to the bounded local development database, then prove status current.
4. Verify the live Wingman flow through persistent Chrome DevTools at desktop and exact 390 x 844 mobile widths; require clean console, zero horizontal overflow and Lighthouse evidence.
5. Preserve `v1.html` byte-for-byte, record evidence, stage only Release 10 files and commit the release checkpoint.
