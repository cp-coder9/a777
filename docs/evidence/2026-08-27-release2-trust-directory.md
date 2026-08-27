# Release 2 trust and professional directory slice — local evidence

## Pack contract implemented

- Professional directory profiles scoped to organisation and user.
- Verification grades remain distinct: self-declared, document-uploaded, manually-reviewed and externally-verified.
- Document-uploaded status requires a real active private file owned by the submitting tenant/user.
- Sensitive evidence requires consent, processing purpose and retention date.
- Expired credentials are labelled at read time and do not produce a current directory badge.
- Manual/external review requires a different authorised owner/admin; self-review is forbidden.
- External verification requires both a real provider and provider reference.
- Profile saves, submissions and reviews are transactional with audit events; review decisions also append immutable review history.
- Directory search exposes a constrained public-safe projection and never exposes POPIA purpose, retention evidence, private file identifiers or review notes.

## Executed automated checks

- Trust MariaDB integration: 3 tests, 15 assertions — pass.
- Combined trust, authorisation, request/query and V1 extraction regression: 17 tests, 63 assertions — pass.
- Request and V1 frontend focused regression: 7 tests, 37 assertions — pass.
- PHP syntax checks for trust service/controller/routes/request — pass.
- JavaScript syntax check for the Settings workflow — pass.
- Source database migration on task-owned MariaDB `127.0.0.1:43319`, database `architex_task7_live`: 1 applied, 0 pending, current at seven migrations.

## Chrome DevTools MCP evidence

Persistent source server: `http://127.0.0.1:8790/`.

- Authenticated session remained `release1@example.test`, owner of organisation `9775135843504f4eb3f9656493770756`.
- Profile save: HTTP 200.
- Private PDF evidence upload: HTTP 201, file `7f0cf83b76bfd54814bffc142917f346`, SHA-256 `459f4fd50e3248823c803558775c016fbaf0a9b43ffa5f89af5dee2d91a2b88a`.
- Document-backed professional registration submission: HTTP 201.
- Attempted external upgrade without provider evidence: HTTP 422 `external_provider_evidence_required`.
- The first exploratory self-review exposed a governance defect. The service was corrected to reject self-review, and only that task-created review/audit row was removed; the original evidence remains `document_uploaded`.
- Settings UI loaded the persisted profile, verification history and directory preview; saving returned `Saved and audited.`
- Desktop viewport returned to 1440×900 and remains open on the persistent server.
- Mobile 390×844 emulation: both trust cards collapse to one 272px column, with card bounds x=96 through x=368 inside the visible main bounds x=74 through x=390. The MCP screenshot command timed out; computed geometry and the accessibility snapshot passed, but no screenshot pass is claimed.

## Certification boundary

This is a locally implemented Release 2 slice, not completion of Release 2 and not staged-host certification. External professional-body verification is intentionally unavailable until a real provider is configured. Remote MariaDB, FTP artifact re-verification, SMTP, control-panel cron, TLS/domain/docroot and staged browser evidence remain separate promotion gates.
