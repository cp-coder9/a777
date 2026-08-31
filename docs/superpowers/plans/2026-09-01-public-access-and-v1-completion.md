# Public Access and V1 Completion Implementation Plan

**Goal:** Replace the signed-out shell with the canonical hosted landing/auth experience, reconcile all V1 tools against real production evidence, and implement Packs 30-32 without weakening the PHP/MariaDB security boundary.

## 1. Baseline and evidence ledger

- [ ] Snapshot remote landing/auth assets and record checksums/source URLs.
- [ ] Parse `v1.html`, the static registry, shell workspace bindings, routes, services, migrations, and tests into a capability ledger.
- [ ] Mark every tool `live`, `limited`, `integration_required`, or `unavailable` from executed evidence only.
- [ ] Add a contract test preventing `scaffold` and unsupported `live` claims from returning.

## 2. Public landing and access controller

- [ ] Add failing frontend tests for signed-out landing, local asset ownership, access-state isolation, and sign-in/sign-up trigger rebinding.
- [ ] Vendor the exact canonical landing and required assets locally.
- [ ] Add a public-access host that mounts the landing while keeping authenticated workspace markup inert.
- [ ] Preserve token-specific public proposal and supplier flows.

## 3. V8 authentication and registration

- [ ] Add failing HTTP/frontend tests for the V8 sign-in/enrolment composition and real endpoint payloads.
- [ ] Implement the exact V8 auth layout, mode switch, loading/error/success states, slug handling, and accessible focus flow.
- [ ] Fetch current legal notice versions from a first-party catalogue; never hard-code acceptance versions in UI logic.
- [ ] Connect sign-in, public organisation registration, email-verification handoff, session restore, and sign-out.

## 4. Bird Flocks transition

- [ ] Add timing, reduced-motion, interruption, and focus-safety tests.
- [ ] Implement the 2.4-second SVG/CSS/vanilla-JS transition and persistent small bird handoff.
- [ ] Verify desktop and mobile animation bounds without portal/game effects.

## 5. Pack 31 legal controls

- [ ] Add versioned draft legal pages and a public notice catalogue endpoint.
- [ ] Add migration/service/routes for workflow-specific accept/cancel audit records.
- [ ] Connect gates to drawing issue, compliance issue, contract signing, payment/escrow, purchasing, CPD certification, municipal reliance, BoQ/BoM approval, snag close, dispute escalation, and admin override where those actions exist.
- [ ] Add role-visibility and no-automation tests.

## 6. Pack 30 unified product gaps

- [ ] Promote Knowledge to a first-class source-linked workspace.
- [ ] Unify Project Passport/lifecycle summaries and next-best actions across records rather than duplicating module state.
- [ ] Complete remaining true V1 gaps found by the ledger in dependency order, one bounded tool slice at a time.
- [ ] Preserve `integration_required` for remote desktop, payment/escrow movement, municipal/provider verification, and other unconfigured external systems.

## 7. Registry and navigation truthfulness

- [ ] Replace stale scaffold labels with evidence-backed readiness and limitation metadata.
- [ ] Align the V1 menu to Command Centre, Inbox, Projects, Toolboxes, CPD, Documents, Knowledge, Marketplace, Finance, Messages, and Settings/Admin.
- [ ] Apply role and phase visibility without hiding direct authorised deep links.
- [ ] Remove legacy prototype metrics and scaffold copy from live workspaces.

## 8. Verification, commits, and staged release

- [ ] Run focused PHP/frontend tests after every slice.
- [ ] Run full PHP test suite and all migrations on the guarded disposable MariaDB database.
- [ ] Verify signed-out landing, auth, registration, legal, authenticated workspace, representative tools, Wingman, responsive layouts, console, and network in the browser.
- [ ] Build and verify an exact-commit release artifact without `packs/`, `v1.html`, secrets, or test-only state.
- [ ] Commit each independently reversible release slice.
- [ ] Produce local certification evidence; perform remote certification only after FTP staging is explicitly executed.
