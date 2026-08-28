# Release 3 document control evidence — 2026-08-28

## Implemented boundary

- PHP 8.3 authenticated API and MariaDB persistence for controlled documents, drawing-register metadata, draft editing, human review, professional approval, formal issue and linked revision supersession.
- Issued revisions are immutable and receive a SHA-256 audit hash.
- ProjectRecord evidence and actionable inbox events are created from governed lifecycle transitions.
- Municipal, tender, construction and closeout readiness are structured workflow checks only.
- OCR, PDF parsing, AI classification, digital signatures and municipal-provider integrations are explicitly unavailable; no automated drawing-content or regulatory determination is claimed.

## Automated verification

- Frontend workspace contract: `1 test, 16 assertions`.
- Document service, readiness and authenticated HTTP MariaDB workflows: `3 tests, 36 assertions`.
- Unit/requirements/HTTP suite: `113 tests, 317 assertions`.
- JavaScript and changed PHP syntax checks passed.
- Live development database migration state: `19 applied, 0 pending`.

## Chrome DevTools MCP evidence

Persistent authenticated page: `http://127.0.0.1:8790/`.

Project `af189b7f799637ca7918b1a3d6858292` completed the visible workflow:

1. Created drawing C01 and edited its draft metadata, including scale from `1:100` to `1:50`.
2. Submitted, reviewed, approved and issued C01 for construction through separate human confirmations.
3. Created linked revision C02, superseding C01 and retaining the explicit historical construction warning.
4. Submitted, approved and issued C02 for construction through the visible governed workflow.
5. Reloaded the page and recovered C01 as superseded and C02 as the current issued revision.
6. Verified the document and drawing registers, revision history, issue hash and readiness warnings after reload.
7. Verified 390 × 844 mobile viewport at zero horizontal overflow, then restored 1440 × 900.
8. At 1440 × 900 the reloaded document workspace had zero horizontal overflow.
9. Chrome console contained no errors or warnings.

Browser-visible identifiers and hashes:

- C01 revision: `6babb1224fe04edfa6af733370162ed2`
- C01 issue SHA-256: `81f6cffe0f31daffde46c3ffeb26ccbc661f5bf4a12ce03ab37dbee8690724ea`
- C02 revision: `357e8b18e5437864cb30c180a2b0e5c3`
- C02 previous revision: `6babb1224fe04edfa6af733370162ed2`
- C02 issue SHA-256: `2faf00011227023a7ac4c3e68b4f9275aea18decc68def83f338bb8604de47c6`

## Direct MariaDB readback

- C01: `superseded`, issue purpose `for_construction`.
- C02: `issued`, issue purpose `for_construction`, linked to C01.
- The newest `technical_drawings` ProjectRecord stores `for_construction` in both the top-level and nested drawing evidence, nested drawing status `issued`, and the exact C02 audit hash.
- Inbox evidence counts: two `document_review_required`, two `drawing_revision_uploaded`, and one `superseded_construction_drawing` event.
- Historical C01 evidence remains immutable; its pre-fix nested snapshot is retained rather than rewritten. The C02 issue proves the normalized current behavior.

This is local implementation evidence. It is not staging or production hosting certification.
