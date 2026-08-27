# Release 1 Task 9 security evidence — 2026-08-27

## Scope

- Tenant-scoped project file upload and download.
- Private, randomised file storage outside the public document root.
- Server-detected MIME, extension, size, post-write SHA-256, and path-boundary validation.
- Audit record on successful upload without persisting file contents or credentials.
- Restrictive HTTP security headers and JSON structured logging with recursive credential redaction.

The document packs were treated as source requirements, not completed implementations. This slice supplies the FileManager-style binary reference layer they require; document registers, revisions, issue sheets, and professional sign-off workflows remain later release work.

## Executed checks

- Task-owned live MariaDB migration: 1 applied, 0 pending.
- Security suite: 12 tests, 33 assertions — pass.
- Unit/HTTP suite: 111 tests, 307 assertions — pass.
- Authenticated HTTP integration regression: 1 test, 16 assertions — pass.
- Composer strict validation and diff whitespace checks — pass.

## Chrome DevTools MCP verification

Persistent PHP server: `http://127.0.0.1:8790/`.

- Uploaded `DevTools Evidence.pdf` as real browser `multipart/form-data` to project `af189b7f799637ca7918b1a3d6858292`: HTTP 201.
- Stored-file id: `15926ed6e9e7b6b6d661ba470b19f12a`; detected MIME `application/pdf`; size 34 bytes; SHA-256 `0af15ace7fe6cdd62f98d19cb4d2b96f7010343f294676460b1af02cd6b212c5`.
- Authenticated download: HTTP 200 with exact PDF bytes, `Content-Type: application/pdf`, attachment disposition, and matching SHA-256 header.
- Forged organisation scope: HTTP 404.
- PHP content named `spoof.jpg` and labelled by the browser as `image/jpeg`: HTTP 422 `unsafe_file` after server-side content detection.
- Live health response included CSP, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, and restrictive permissions policy.

## Certification boundary

This is local security evidence on the task-owned MariaDB and storage tree. Shared-hosting filesystem permissions, PHP upload limits, web-server deny rules, malware scanning, backup inclusion, and staged download behavior remain Task 10 deployment gates.
