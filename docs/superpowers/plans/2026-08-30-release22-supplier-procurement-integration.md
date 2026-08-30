# Release 22 — Supplier Procurement Integration

**Pack:** `26. architex-supplier-procurement-integration-pack`

**Target:** PHP 8.3 and MariaDB on FTP-only shared hosting, preserving the canonical V1 shell and treating every supplied pack capability as unimplemented until verified here.

## Production design

- Extend the existing governed procurement module; do not create a parallel tender, audit, inbox, file, site, finance or project-record system.
- Support supplier capability levels 0 and 1 as complete local workflows: manual portal data and CSV catalogue import. Levels 2–6 are explicit connector states and must report `integration_required` until real provider credentials/endpoints are configured and verified.
- Model immutable approved BoM/BoQ/specification baseline revisions with project/document/FileManager references. Package generation must fail unless a human-approved current baseline exists.
- Version supplier catalogues and snapshot price, stock, lead time, compliance standards and source evidence at quote/award time.
- Keep matching and quote comparison advisory. AI may normalise, score, flag risk and draft recommendations; it cannot approve substitutions, awards, purchase orders, delivery acceptance or payment release.
- Use organisation and project scoping on every query. Supplier portal access uses revocable, hashed, expiring one-time credentials; no plaintext token is persisted.
- Preserve commercial truth: claimed, quoted, ordered, invoiced, certified/recommended and provider-paid amounts remain distinct. Architex emits a finance handoff only and never claims to hold or transfer funds.

## Implementation plan

- [x] Add migration 0052 for supplier profiles/connectors, catalogue revisions/items, approved procurement baselines/lines, integration packages/lines, match snapshots, RFQs/quotes, substitution and award decisions, purchase orders, deliveries, site acceptance, invoices and payment-release recommendations.
- [x] Add explicit foreign keys, tenant/project indexes, idempotency keys, optimistic versions and immutable SHA-256 snapshots.
- [x] Implement a tenant-scoped supplier procurement service with strict value validation, membership/capacity checks and transaction boundaries.
- [x] Implement supplier registration and Level 0/1 catalogue entry/import while failing closed for unconfigured Level 2–6 connectors.
- [x] Implement draft baseline creation, FileManager/document evidence validation and accountable human baseline approval.
- [x] Build procurement packages only from the approved immutable baseline; never mutate source lines silently.
- [x] Implement deterministic catalogue normalisation, unit/standard/stock/region matching, programme dates and advisory risk scoring.
- [x] Implement RFQ issue, supplier quote revision submission and quote-normalisation snapshots with complete price/stock/lead-time evidence.
- [x] Implement substitution review, award approval and purchase-order issue as separate human-confirmed gates.
- [x] Implement dispatch/delivery tracking, site receipt and accountable site acceptance/rejection/partial decisions with critical blocker enforcement.
- [x] Implement invoice capture and finance payment-release recommendation handoff; rejected/disputed/blocked deliveries must fail closed.
- [x] Write real ProjectRecords, inbox items and audit events for governed lifecycle changes and link FileManager evidence instead of creating another file store.
- [x] Publish authenticated project APIs plus the minimum scoped supplier-portal credential flow, with rate limiting and non-enumerating errors.
- [x] Add a V1-native supplier procurement workspace under the existing procurement area with baseline, catalogue, matching, RFQ/quote, award/PO, delivery/site and payment panels.
- [x] Add unit, migration, route, frontend and connected MariaDB tests covering the happy path, every human gate, optimistic conflicts, idempotency, tenant isolation and foreign-file rejection.
- [x] Run the full test suite, all 52 migrations, PHP/JavaScript syntax checks and release-manifest verification.
- [x] Certify owner/procurement operator/supplier/read-only flows in persistent Chrome DevTools at desktop and responsive mobile, with network, console, screenshots and Lighthouse evidence.
- [x] Commit Release 22, build and independently extract/verify an exact-source FTP ZIP, document truthful evidence, and move Pack 26 to `packs/Completed`.

## Acceptance boundary

Release 22 is complete only when the real MariaDB-backed lifecycle can demonstrate approved baseline → supplier catalogue → advisory match → RFQ/quote → substitution/award approval → PO → delivery/site acceptance → blocked or allowed payment recommendation, including persisted speak-back records and cross-tenant rejection. External ERP/EDI, stock, order, delivery, invoice and payment providers remain `integration_required` unless independently configured and proven.
