# Release 2 professional fee calculator UI evidence

Date: 2026-08-28  
Scope: local source runtime only; this is not staging or shared-hosting certification.

## Implemented

- Canonical `v1.html` shell now opens a production-backed Professional Fee Calculator workspace.
- Owner creates an organisation-scoped source version and percentage-of-cost formula in draft state, then explicitly verifies both.
- Only verified source/formula pairs are selectable.
- Calculation captures project value, professional-fee discount and reason, disbursements, statutory fees, VAT choice, assignment, and issue reason.
- Issued runs persist an immutable input/output snapshot, source/formula version, user, timestamp, and SHA-256 audit hash in MariaDB.
- JSON, CSV, and HTML evidence exports download through an authenticated tenant-scoped fetch.
- Advisory copy states that the internal schedule is not a statutory tariff and requires responsible-professional review.

## Chrome DevTools MCP proof

Runtime: `http://127.0.0.1:8790/`, PHP 8.3.32, MariaDB on the task-owned local instance.

Visible owner flow:

1. Created and human-verified Architect schedule `1.0.0` at 5% with VAT 15%.
2. Calculated against project value R1,000,000.
3. Applied a 10% professional-fee discount with an explicit reason.
4. Added R1,000 disbursements and R5,000 statutory fees outside the discount base.
5. Assigned the run to the selected internal project and issued it with a professional reason.
6. Browser displayed calculated fee R50,000, fee after discount R45,000, and total including VAT R57,900.
7. Browser displayed persisted SHA-256 `84a9685a088196be125de6de84a68b1ae79277a24ed5cf9680089a6a53d04bd5`.
8. Export discovery returned JSON, CSV, and HTML controls; the authenticated JSON download returned HTTP 200.
9. At 390 x 844 the workspace remained single-column and Chrome reported `scrollWidth: 390` with no horizontal overflow. Desktop viewport was restored to 1440 x 900.

## Automated proof

- `ToolboxHttpTest`: 1 test, 14 assertions, passing against disposable MariaDB.
- The HTTP test covers authenticated source/definition creation and verification, issued run creation, R57,900 output, 64-character audit hash, three export formats, binary download, and response-content SHA-256 integrity.
- JavaScript syntax, PHP syntax, and `git diff --check` passed (line-ending notices only).

## Remaining gate

Staged shared-hosting upload, real hosting MariaDB migration, HTTPS/session verification, and remote browser evidence remain intentionally unclaimed.
