# Release 18 — CPD Assessment Platform

Date: 2026-08-29

Scope: Pack 22, `22. architex-cpd-assessment-platform-pack`

Runtime: PHP 8.3.32, MariaDB 11.4 at `127.0.0.1:43320`, persistent demo server `http://127.0.0.1:8792/`

## Delivered boundary

- A top-level CPD Learning Hub preserves the canonical V1 rail, navigator, main canvas and inspector.
- Publicly registered organisation users can maintain a consented professional profile for SACAP, ECSA, SACQSP, SACPCMP, SACPLAN, SACLAP, SAGC or SACPVP.
- Rights-cleared source content, AI-assistance disclosure, assessment authoring, human review and accreditation evidence are separate governed stages.
- API accreditation and external payment gateways fail closed with `integration_required` until a real connector is configured.
- Live course publication requires a human-accredited application with approved category, credits, reference and validity dates.
- Manual EFT access requires administrator evidence review. Commercial settings are organisation-scoped and each granted entitlement snapshots gross value, platform fee and content-owner net value into MariaDB.
- Payout rows are review-only records with `moves_money=false`; Architex does not simulate settlement or move funds.
- Choice answers are scored server-side, answer keys are absent from learner workspace responses, text answers require human review, and passed accredited attempts issue immutable professional records and verifiable certificates.
- Authenticated certificate PDFs are generated server-side with private/no-store response headers and a SHA-256 digest.
- Referral events record source, campaign, event, course and explicit POPIA-consent state without third-party tracking fabrication.

## Automated evidence

- Focused migration, route and frontend contracts: 3 tests, 83 assertions, zero skips, failures or errors.
- Connected disposable MariaDB lifecycle: 2 tests, 26 assertions, zero skips, failures or errors in 02:42.
- The connected lifecycle covered eight professional-body rules, profile consent, owned content, AI-assisted draft disclosure, human assessment review, accreditation, course publication, commercial settings, purchase ledger, pending non-money-moving payout, learner answer-key suppression, server scoring, manual review, certificate issue, PDF generation, public verification, referral capture and audit evidence.
- JavaScript syntax, PHP syntax and `git diff --check` passed.
- Browser database migration status: current at 47 applied, 0 pending.

## Chrome DevTools MCP evidence

Chrome DevTools MCP ran through the existing persistent Chrome profile and retained the demo and packaged-candidate pages.

- Complete browser lifecycle produced live course `e0853f77d1dc986b5f65424248380b13`, attempt `c712d57ca2802f7e9c85e29be7a303ce` and certificate `ca6deef65d59620959eca976e4cd4a28`.
- The attempt moved from `manual_review_required` to `passed`; the professional record awarded 0.1 SACAP credits against `CPD-BROWSER-2026-001`.
- Public certificate verification returned valid; an unknown certificate returned HTTP 404.
- An unconfigured accreditation API request returned HTTP 503 with `cpd_accreditation_integration_required`.
- A second authenticated organisation received an empty CPD workspace, proving tenant isolation for courses, content, attempts and records.
- The new commercial form persisted a 12.5% fee, R2.50 minimum fee and pending-payout-record setting in MariaDB.
- Catalogue hydration recorded exactly one referral event for the browser session.
- Authenticated PDF download returned HTTP 200, `application/pdf`, attachment metadata, SHA-256 `eb7b36c96fa58faa127713f961479b87f1ca1dbd62eb83d855879e819ea9db15`, 1,046 bytes and `%PDF-1.4`.
- Desktop width was 1,440 px with zero horizontal overflow. Mobile width was 390 px with zero horizontal overflow.
- Settled CPD Lighthouse snapshot: Accessibility 100, Best Practices 100, SEO 100, Agentic Browsing 100; 31 passed, 0 failed.
- Chrome console contained no warnings or errors after the final workflow.

Reports and inspected screenshots:

- `docs/evidence/lighthouse/release18-pack22-cpd-final/report.html`
- `docs/evidence/release18-pack22-cpd-final/report.json`
- `docs/evidence/release18-pack22-cpd-final-desktop.png`
- `docs/evidence/release18-pack22-cpd-mobile.png`

## Release boundary

This is local implementation, connected MariaDB and persistent-browser evidence for Pack 22. It does not claim FTP upload, target-host MariaDB migration, SMTP readiness, staging-domain certification or production promotion. Those remain gated on the shared-host credentials, backup/rehearsal and staged-release controls already documented for the programme.

`v1.html` and `packs/` remained untouched, untracked and excluded from commits.
