# Release 10 local certification — Agent Orchestration Core

Date: 2026-08-29
Scope: Pack 14, local staged-release gate
Runtime: PHP 8.3.32, MariaDB 11.4 at `127.0.0.1:43319`, persistent Chrome DevTools page at `http://127.0.0.1:8790/`

## Implemented scope

- Tenant/user-anchored agent identities and user contexts plus a project coordination context.
- User-only memory boundaries with explicit allowed scopes, mandatory cross-user/cross-tenant exclusions and retention.
- Owner/admin-governed, versioned system policies with permission bypass and external dispatch forced off.
- Project event routes linked to a source module, target role and immutable policy version.
- Deterministic advisory recommendations with tenant, project, user, route, policy, source object and severity provenance.
- Contextual formal-message drafts with a separate human approval gate. Approval changes the state only to `approved_for_human_send`; no external dispatch occurs.
- Distinct-user approval, requester self-approval denial, terminal decision immutability, ProjectRecords, Inbox events, audit trail and monitoring records.
- A production Wingman workspace covering identity/context, policies, routes, events, recommendations, drafts, approvals, memory and monitoring.

## Automated evidence

- Connected orchestration scenario, including an immutable v1 route after policy v2 supersedes it: `OK (1 test, 27 assertions)` in 02:54.768 against `architex_task13_facefeed_test`.
- Connected cross-tenant workspace isolation: `OK (1 test, 1 assertion)` in 02:55.194 against the same explicitly disposable schema.
- Final frontend contract: `OK (1 test, 21 assertions)`.
- PHP syntax passed for the service, exception, controller, route registrar, kernel wiring and integration test.
- `node --check app/public/assets/js/shell.js` passed.
- `git diff --check` passed; only Git's informational LF-to-CRLF warnings were emitted.

## MariaDB and HTTP evidence

- Migration `0035_agent_orchestration_core.sql` applied to the bounded local development database; final status is current, 35 applied, 0 pending.
- Chrome created one identity/context/memory boundary, one active governance policy, one active event route, one recommendation, one message draft, one approval gate and one monitoring record.
- Those browser actions emitted 3 Agent Orchestration ProjectRecords, 2 Agent Inbox items and 6 Agent audit events.
- The persisted browser draft remains `pending_approval` with `external_dispatch_performed=0` and the gate remains pending.
- An authenticated requester self-approval attempt returned HTTP 403 `agent_self_approval_forbidden`.
- The connected test separately proved that a distinct owner can approve, producing `approved_for_human_send` while `externalDispatchPerformed=false`, and that the decision is immutable.

## Chrome DevTools evidence

- Persistent authenticated page ID 12 remains open on Wingman / Agent Orchestration for project `304dc7da987382b3861b5a80fbe27470`.
- The rendered accessibility tree and live forms exposed all required contexts, governance, routing, recommendation, draft, approval, memory and monitoring sections.
- The browser-created recommendation retained exact source object `056ab60868cc794747b3cede78c6b19f` and policy `363b04fedaa740b175ce446347c57915`.
- The requester-facing gate now hides approval controls and states that a different authorised user must decide.
- Exact 390 x 844 mobile emulation rendered all 13 Agent Orchestration headings with `clientWidth=390`, `scrollWidth=390` and zero horizontal overflow.
- Restored desktop viewport 1440 x 900 has zero horizontal overflow. Final console: zero errors, warnings or issues.
- Final Lighthouse snapshot, mobile: Accessibility 100, Best Practices 100, SEO 100, Agentic Browsing 100; 31/31 audits passed.
- Final Lighthouse snapshot, desktop: Accessibility 100, Best Practices 100, SEO 100, Agentic Browsing 100; 31/31 audits passed.
- A pre-final desktop audit exposed low contrast on transparent global-rail numeric metadata. The extracted production CSS now uses an opaque dark pill; the canonical V1 source was not changed.

## Preserved source and release boundaries

- Canonical `v1.html` was not edited. SHA-256 remains `7DBFAFDCFA52644FD32FB7CB7D73891B78593CA0287C089240DB4439E0FA97BF`.
- Pack source material remains untracked and is not part of the deployable application commit.
- This is local staged-release certification only. No hosted AI provider, external message dispatch, FTP upload, shared-host migration, SMTP delivery, remote backup rehearsal or production promotion is claimed.
- SMTP and all external agent actions remain intentionally fail-closed until real shared-host/provider configuration and separate release approval exist.
