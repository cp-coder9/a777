# Release 2 integrated forms evidence — 2026-08-28

## Implemented boundary

- PHP 8.3 authenticated API and MariaDB persistence for verified template discovery, project-aware drafts, immutable revisions, review, approval, issue and export retrieval.
- Human confirmation is required separately for approval and issue.
- Issued revisions create checksum-protected HTML and JSON evidence only.
- PDF generation and digital/e-signature remain explicitly unavailable until real providers are configured.

## Automated verification

- Frontend contract: `1 test, 16 assertions`.
- Form service and authenticated HTTP MariaDB workflows: `2 tests, 30 assertions`.
- Unit/requirements/HTTP suite: `113 tests, 317 assertions`.
- JavaScript and changed PHP syntax checks passed.
- Live development database migration state: `18 applied, 0 pending`.

## Chrome DevTools MCP evidence

Persistent authenticated page: `http://127.0.0.1:8790/`.

Project `af189b7f799637ca7918b1a3d6858292` completed the visible workflow:

1. Loaded three verified template versions in the Template Library.
2. Auto-filled project and responsible-professional fields.
3. Created revision 1, then created revision 2 with municipality changed to `City of Johannesburg` and a recorded reason.
4. Submitted revision 2 for review with a human reason.
5. Approved the exact revision through prompt plus confirmation.
6. Issued the exact approved revision through a separate prompt plus confirmation.
7. Reloaded the page and recovered two revisions, issued state, corrected value and two exports.
8. Verified 390 × 844 mobile viewport at zero horizontal overflow, then restored 1440 × 900.
9. Chrome console contained no errors or warnings.

Browser-visible identifiers and checksums:

- superseded revision: `7e54e1dc0877cde650fdd5fd2c5e2bb3`
- issued revision: `81702e448ec7b7a1c181fe7929fad7c8`
- issue SHA-256: `e24e121d8bba8af531d467ae6b0699a50bcf58037eda271a9d187f83dd50d5a8`
- HTML checksum: `cbcc685cc4b8e9043e110dd67d08273484b039ac1bdca74dcc4ef47d9ddb96ce`
- JSON checksum: `1e6e661fa1db98a3cbd929bed6f2f97bdcf3af5698b9d34db182b25d15626c12`

## Direct MariaDB readback

- Revision 1: `superseded`, municipality `City of Tshwane`.
- Revision 2: `issued`, municipality `City of Johannesburg`, non-null issue hash.
- Export rows: HTML (834 bytes) and JSON (2068 bytes), each with its own SHA-256 checksum.
- Audit sequence: `form.draft_created`, `form.revised`, `form.submitted_for_review`, `form.approved`, `form.issued`.

This is local implementation evidence. It is not staging or production hosting certification.
