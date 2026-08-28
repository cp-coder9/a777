# Release 5 finance, payment, escrow and commercial control implementation plan

## Production boundary

- Architex never holds client funds and is not represented as a payment, escrow, wallet, trust-account, EFT/card, payout or regulated financial-services provider.
- Money movement is possible only through a separately configured, trusted and registered third-party provider connector. With no provider configured, release requests remain `provider_configuration_required`.
- Claimed, certified, retained, previously certified, net payable, approved-for-provider-request and provider-confirmed-paid amounts remain separate integer-cent facts.
- A disputed claim or certificate locks release. No automatic certification, variation approval, provider request, retention release or contract-sum change is allowed.
- Variations and payment certificates use immutable revisions/supersession; provider events are signature-verified, idempotent and append-only.
- A commercial baseline requires a formally issued professional appointment or later controlled contract source. A procurement appointment placeholder alone is never treated as a signed contract.
- Preserve `v1.html` unchanged and connect the existing Payments & Escrow, Valuations, Contract Administration and finance surfaces inside the canonical shell.

## Delivery sequence

- [ ] Add migration `0029` for tenant/project-owned commercial baselines and revisions, schedules/milestones, variations and approvals, claims and evidence, certificate revisions, retention ledger/releases, provider registry/configuration, release requests/approvals, provider webhook events and cashflow snapshots.
- [ ] Add failing MariaDB service tests for source eligibility, integer-cent arithmetic, optimistic revisions, tenant/project isolation, approved-variation baseline updates, certificate history, previous-payment deductions, retention balance and dispute locks.
- [ ] Implement commercial baseline creation from immutable appointment evidence, payment schedules, cashflow forecasts and ProjectRecord/inbox/audit outputs.
- [ ] Implement variation `draft → submitted → under_review → approved/rejected → incorporated` with distinct QS/professional/client approvals before changing the contract sum.
- [ ] Implement claimant-scoped immutable claims, evidence references, review/dispute state and controlled certificate revisions with gross, retention, previous payments, disputes and net payable separated.
- [ ] Implement retention accrual/balance/release scheduling; retention release requires its own human approvals and provider-mediated payment request.
- [ ] Implement an administrator-controlled provider registry without secrets in database responses; inactive/unconfigured providers fail closed.
- [ ] Implement release requests that require client and lead-professional approval, remain blocked on disputes, and stop at `provider_configuration_required` while no live connector exists.
- [ ] Implement provider webhook verification, replay/idempotency protection, reference/amount/currency matching, paid/failed/refunded event handling and reconciliation evidence. No unsigned or mismatched event may mutate status.
- [ ] Add authenticated PHP routes, CSRF/permission/rate controls, generic failures and a narrowly isolated webhook route.
- [ ] Replace finance scaffolds with project-aware baseline, schedule, variation, claim, certificate, retention, provider-boundary, release and cashflow views.
- [ ] Run focused and regression tests, PHP/JS syntax, migration/backup/restore compatibility, Chrome multi-role flow, reload persistence, responsive and accessibility checks, console/network checks and direct MariaDB readback.
- [ ] Record local evidence while keeping real provider credentials/agreement, staging MariaDB, FTP deployment, TLS/domain/docroot, cron, SMTP and production promotion as separate release gates.
