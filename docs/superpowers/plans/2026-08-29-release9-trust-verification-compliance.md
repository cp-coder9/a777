# Release 9 — Trust, Verification & Compliance

## Objective

Implement Pack 13 as tenant/project-scoped PHP 8.3 and MariaDB workflows inside the existing Trust workspace while preserving the distinct verification grades and never fabricating an external provider check.

## Production boundary

- Professional bodies are constrained to SACAP, ECSA, SACQSP, SACLAP and SACPCMP.
- Company, insurance and contractor/supplier evidence keeps its real document type, reference, expiry and verification grade.
- Insurance coverage gaps are derived from stored cover and required thresholds; they create review risks and never imply suitability.
- External verification is impossible without an explicit provider and provider reference.
- POPIA processing activities, data-subject requests and breach handling store minimised workflow facts, ownership, dates and immutable audit evidence.
- Compliance gaps route to ProjectRecords, Inbox, audit and recommendations; no action contacts a regulator, professional body or insurer.

## Tasks

1. Add connected failing tests for structured compliance, provider-gated review, coverage risks, POPIA workflows and tenant isolation.
2. Add migration `0034_trust_verification_compliance.sql` with scoped evidence, review, POPIA, risk and recommendation tables.
3. Implement `TrustComplianceService`, controller/routes, validation, RBAC and unified output routing.
4. Extend the existing Trust workspace with real MariaDB forms, review controls, risk/blocker visibility and POPIA workflow lists.
5. Apply and test against disposable MariaDB, then migrate the persistent local database.
6. Exercise authenticated browser workflows with Chrome DevTools and verify rows, console, accessibility and mobile layout.
7. Record local evidence and commit only deployable implementation/tests/docs; leave `packs/` and `v1.html` untouched.

## Staged-release exit

Local certification is not shared-host certification. FTP staging, target MariaDB migration, SMTP, target cron, rollback rehearsal and production promotion remain later gates.
