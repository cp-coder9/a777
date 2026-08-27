# Release 2 guided brief UI and evidence certification

Date: 2026-08-28 (Africa/Johannesburg)

## Implemented scope

- Replaced the Project Passport scaffold with a live, resumable guided-brief workspace while preserving the canonical `v1.html` shell and visual language.
- Added private pre-project evidence storage for PDF, JPEG, PNG and DOCX files, including content/extension validation, 25 MB limit, safe names, SHA-256 integrity, explicit consent, declared processing purpose, retention date and audit event.
- Added authenticated list, multipart upload and private download routes with organisation/user isolation and `no-store` response policy.
- Persisted the latest rule-based interpretation in the brief list projection so submitted and converted briefs retain their explicit advisory context after reload.
- Added a standalone entry point for organisations that do not yet have a project.

## Executed local evidence

- MariaDB migration `0010_guided_brief_files.sql` applied to task-owned database `architex_task7_live` on `127.0.0.1:43319`.
- Database status after migration: current; 10 applied; 0 pending.
- Focused regression command covered request parsing, guided-brief workflow, private evidence service and authenticated HTTP upload/download.
- Result: 11 tests, 59 assertions, all passing in 03:51.841 on PHP 8.3.32.
- JavaScript syntax check and PHP syntax checks passed; `git diff --check` reported no whitespace errors.

## Chrome DevTools MCP certification

Persistent source runtime: `http://127.0.0.1:8790/`; authenticated owner `release1@example.test`; organisation `9775135843504f4eb3f9656493770756`.

Chrome exercised the visible production workspace to:

1. create and resume a guided brief;
2. save complete typed answers at step 7/version 2;
3. submit a browser-generated PDF through the actual multipart form with consent, purpose and retention metadata;
4. display the stored filename, category, byte size, retention date and SHA-256 prefix;
5. submit and lock the brief at step 8/version 4;
6. show the explicit advisory-only interpretation and limitations;
7. convert the brief into the selected project record at version 5;
8. render cleanly at 390 x 844 and return to 1440 x 900;
9. finish with no browser console warnings or errors.

Final Chrome/API readback for brief `771f36e7cac5f6f256acaa60a42a6de9`:

- status `converted`, version 5, advisory-only `true`;
- assigned project `af189b7f799637ca7918b1a3d6858292`;
- one private file, list HTTP 200;
- download HTTP 200, `Cache-Control: no-store`, 50 bytes;
- response SHA-256 exactly matched stored SHA-256: `7a6f7637be17a89e0d1a6ebb8ea0ae701b4da00727303ffe16e024a33a73b85c`.

Chrome DevTools file-path upload was not permitted by that MCP server's configured filesystem allow-list. The same real UI upload handler was therefore exercised with a browser-created `File` object through Chrome DevTools; server-side MIME detection, hashing, storage, listing and authenticated download all executed normally. The independent HTTP integration test also covered the multipart boundary end to end.

## Certification boundary

This is local source-runtime and task-owned MariaDB evidence. It is not remote shared-hosting, FTP, SMTP, TLS, cron or staging certification. Those gates remain part of the final staged-release program.
