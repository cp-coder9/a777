# Release 22 — Pack 26 Supplier Procurement Integration Evidence

## Certified source and FTP artifact

- Application source commit: `f1d5b58b3cce58e3630ac44298e646be9dd1173c` on `master`.
- Production implementation commits: `36cf74af954c9a02542e519adb58de1711e5bb94`, `1cf590802e71fb3fe9dffa15b9b75a8b857d800a`, `f3b2d5f4ab587d703d356a436ac924040dc68df3`, and `f1d5b58b3cce58e3630ac44298e646be9dd1173c`.
- Candidate: `dist/architex-release-staged-20260830-f1d5b58`.
- Build API: `staged-20260830-f1d5b58`, PHP 8.3, `php-shared-hosting`, exact source commit, reviewable artifact.
- Manifest verification: passed, 354 release files.
- FTP ZIP: `dist/architex-release-staged-20260830-f1d5b58.zip`.
- ZIP bytes: `1169438`.
- ZIP SHA-256: `1FF7060408B8B075602A5CF16E937FE2B15C98C8222C7E735C5A1FA9B6F2A0E2`.
- Independent `Expand-Archive` and `verify-release.php`: passed; 355 extracted files including release metadata.

## Supplied pack coverage and production boundary

All nine files supplied in Pack 26 were read as unimplemented requirements before implementation. Migration 0052 adds 17 tenant/project-scoped MariaDB lifecycle tables for suppliers, immutable catalogues and baselines, packages, advisory matches, revocable hashed RFQs, quote revisions, substitutions, awards, purchase orders, deliveries, site decisions, invoices and payment recommendations.

- Capability Levels 0 and 1 are locally operational. Levels 2–6 remain truthful `integration_required` states until real provider endpoints and credentials are configured and proven.
- Matching scores and risks are deterministic advice. Baselines, substitutions, awards, purchase orders, supplier acceptance, site acceptance and payment handoff retain explicit human gates.
- RFQ tokens are 256-bit, shown once, stored only as SHA-256, expire, can be revoked before award and can be reissued without deleting history.
- Purchase-order acceptance is available only to the scoped winning supplier token. Delivery is rejected until supplier acceptance is persisted.
- Purchase-order totals derive from the latest immutable supplier quote, not mutable catalogue prices.
- Delivery/site/payment lifecycle state speaks back to the procurement package. Blocked and release-requested recommendations are both retained.
- Payment state is a provider handoff with `providerStatus=integration_required` and `movesMoney=false`; Architex does not claim to hold or transfer funds.
- Governed changes write ProjectRecord, Inbox and audit events and validate linked FileManager evidence within the same organisation and project.

## Automated verification

- Full suite: 311 tests, 1,297 assertions, 144 explicitly database-gated skips, exit 0.
- Focused unit/migration/route/frontend/accessibility suite: 5 tests, 93 assertions, exit 0.
- Connected MariaDB Pack 26 suite after all 52 migrations: 1 test, 48 assertions, exit 0.
- Connected coverage proves tenant isolation, foreign-file rejection, catalogue idempotency, approved-baseline enforcement, RFQ revocation/reissue, immutable quote totals, quote-derived award/PO, supplier PO acceptance, pre-acceptance delivery rejection, blocked payment before site acceptance, release-requested handoff after acceptance and read-only viewer denial.
- PHP and JavaScript syntax checks, `git diff --check`, source manifest verification and independent ZIP extraction verification passed.

## Persistent Chrome DevTools MCP certification

The exact candidate remains running at port 8808 and open in isolated Chrome page 27 on the direct supplier-procurement deep link.

- Owner exact-candidate authentication preserved the deep link, returned production mode and hydrated persisted MariaDB counts: 1 supplier, 1 catalogue, 2 approved baselines, 2 packages, 2 quotes, 2 orders, 1 site decision and 2 payment handoffs.
- Browser API exercise proved a fresh R87.50 quote-derived purchase order, winning-supplier portal exposure and explicit supplier acceptance with HTTP 200.
- A separate unauthenticated supplier context loaded the scoped RFQ and submitted an immutable quote revision without session leakage.
- Operator/member context exposed operational controls but no approval controls. Dedicated viewer context displayed persisted data, hid all three mutation forms, showed only Refresh and returned HTTP 403 for a direct mutation attempt.
- Exact-candidate authenticated reload plus build provenance made nine fetch/XHR requests; every response was HTTP 200, with no console warnings or errors and no demo-only requests.
- Completed packages no longer expose stale match, RFQ, award, duplicate package, duplicate invoice or duplicate site-decision actions.
- Desktop and responsive layouts both have zero horizontal overflow. Chrome DevTools emulated the requested 390 by 844 mobile viewport with the `max-width:760px` breakpoint active.
- Desktop screenshot: [release22-pack26-supplier-procurement-desktop.png](release22-pack26-supplier-procurement-desktop.png).
- Responsive screenshot: [release22-pack26-supplier-procurement-mobile.png](release22-pack26-supplier-procurement-mobile.png).
- Authenticated exact-candidate Chrome DevTools Lighthouse snapshot: Accessibility 100, Best Practices 100, SEO 100 and Agentic Browsing 100, with 31 passed and zero failed audits. Reports are in `release22-lighthouse/`.

## Truthful release status

Pack 26 is complete for the PHP 8.3/MariaDB shared-hosting boundary and can be uploaded by FTP from the certified ZIP. External ERP, EDI, live stock/price, order, logistics, invoice and payment providers are not fabricated and remain `integration_required` until configured and independently verified.
