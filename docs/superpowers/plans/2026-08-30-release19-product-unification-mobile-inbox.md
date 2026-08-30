# Release 19 — Product Unification, Mobile Inbox and Release Gate

**Pack:** `23. architex-product-unification-mobile-inbox-release-gate-pack`

**Target:** PHP 8.3 and MariaDB on shared hosting, deployable by FTP, preserving the canonical V1 shell.

**Release boundary:** Implement and certify locally first. Remote FTP, target MariaDB, SMTP, TLS and production promotion remain separately gated.

## Implementation plan

- [x] Add MariaDB release-gate provenance and review tables without duplicating existing project records, inbox items or audit events.
- [x] Implement one project-operating-system service that adapts real project records and role-routed inbox facts into canonical decisions, risks, plain-language client cards and CPD status.
- [x] Extend the inbox lifecycle with authenticated, tenant-scoped read/resolve/dismiss transitions requiring human confirmation for sensitive decisions.
- [x] Publish an explicit API-capability registry whose statuses match the implemented PHP routes and keep provider, municipal, professional and money-moving boundaries fail-closed.
- [x] Add authenticated operating snapshot, mobile inbox, capability and release-gate endpoints plus public build provenance.
- [x] Emit `public/build-info.json` from every immutable release artifact using the exact source commit, version, PHP/API mode and build timestamp.
- [x] Replace the scaffolded Action Centre/global inbox with a responsive V1-native Decision Centre using live MariaDB projections, role-aware labels, risk categories, client summaries and human confirmation controls.
- [x] Add CPD profile/certificate reminders without generalising professional-body credit rules or merging CPD into professional toolboxes.
- [x] Verify focused contracts, connected disposable MariaDB workflows, tenant isolation, stale-deployment failure, provider boundaries and immutable release metadata.
- [x] Certify desktop and 390 px mobile flows with persistent Chrome DevTools MCP, screenshots, console/network checks and Lighthouse.
- [x] Commit Release 19 implementation and build an independently verified FTP ZIP from an exact commit.

## Canonical status contract

Every projected decision uses one of `advisory`, `awaiting_human`, `blocked`, `ready` or `completed`. Every sensitive action records a human gate; AI may draft or recommend but can never execute, approve, sign, submit, certify, pay or release funds.

## Truthfulness rules

- Existing MariaDB records are the source; empty modules remain empty and are not populated with illustrative production claims.
- Architex records payment and payout review evidence but does not hold or move funds.
- A release gate cannot pass without exact commit/build provenance, database migration readiness, route smoke evidence and truthful API/mail/provider status.
- Stale or missing deployment provenance fails the review gate.
- CPD status is body-specific professional-record support, not statutory certification.
