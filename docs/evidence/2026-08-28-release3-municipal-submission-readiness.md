# Release 3 municipal submission readiness: local certification

**Date:** 2026-08-28  
**Scope:** Local PHP 8.3 and MariaDB implementation evidence only  
**Release boundary:** This is not shared-hosting staging, municipal lodgement, statutory approval or production certification.

## Governance boundary

The installed municipality matrix is `implementation-seed-v0.1` with status `review_required`. It is a generic implementation checklist, not an official City of Tshwane submission specification. It cannot be verified in place and cannot support professional-readiness approval. A sourced, immutable replacement version and explicit authorised review are required before the platform permits that approval.

The workflow never creates a municipal application number, council integration, submission event, statutory certificate or compliance opinion. Every assessment retains `municipalSubmissionCreated: false`, visible assumptions and an advisory-only boundary.

## Implemented capability

- Tenant-scoped, immutable municipality requirement versions with source, effective date, reviewer history and SHA-256 payload integrity.
- Review-required seed installation, owner/admin-only version governance and fail-closed verification.
- Immutable project assessment snapshots and separately persisted professional review records.
- Project Passport, latest Appointment, ProjectRecord, active project-team, controlled-document, drawing-register and compliance-ToolRun reads.
- Human-entered project scope triggers with low, medium and high complexity classification.
- Trigger-only routing for the full built-environment team, including explicit optional and not-currently-required decisions.
- Registered/supervising-professional warnings where stored verification evidence is insufficient.
- All eight required readiness categories, overall/category scores, blockers, missing documents, human sign-offs, confidence and assumptions.
- Six-part evidence pack, next-best actions, deduplicated role inbox items, workflow events, audit events and a pending-review ProjectRecord.
- Authenticated API and native canonical V1 dashboard.

## Automated evidence

- Municipal registry and readiness service: 4 tests, 51 assertions.
- Authenticated municipal HTTP workflow: 1 test, 20 assertions.
- Final combined MariaDB/HTTP rerun: 5 tests, 71 assertions.
- Municipal frontend contract: 1 test, 24 assertions.
- Broad Unit, Requirements, HTTP, Frontend and Security regression: 135 tests, 484 assertions, 3 environment-dependent skips.
- `node --check app/public/assets/js/shell.js`: passed.
- PHP syntax checks for all new and changed PHP files: passed.
- `git diff --check`: no whitespace errors; only repository line-ending warnings.

The disposable tests prove source verification and review-state gates without modifying the live development organisation's review-required source.

## Migration evidence

Migration `0021_municipal_submission_readiness.sql` was applied to the bounded local development database. The migration tools reported **21 applied, 0 pending**.

## Persistent Chrome DevTools evidence

The existing authenticated owner session remained open at `http://127.0.0.1:8790/` in Chrome page 2. The visible **Municipal Submission Readiness** tool was opened through the canonical V1 surface.

Visible actions and results:

- Clicked **Install review-required seed**.
- Matrix ID: `419c26d9207e45cc44ea4265d26e6941`.
- Municipality: `city-of-tshwane`.
- Matrix version/status: `implementation-seed-v0.1` / `review_required`.
- Matrix SHA-256: `aa8604a0df6fd93173f17bfb224afd9c053bc39f97f18ad9248b483cefb3c3be`.
- Clicked **Create immutable readiness assessment** with the visible default high-trigger facts.
- Saved assessment ID: `1fc78e5e9db1c56b6e693ce0cd329ac4`.
- Overall score: 12%.
- Complexity: high, with five triggers.
- Eight category cards rendered.
- Required: client, lead professional, architect, municipal coordinator, structural engineer, civil engineer, energy consultant, town planner and land surveyor.
- Not currently required: heritage practitioner, environmental practitioner and traffic engineer.
- Missing-document items: 8.
- Required sign-off groups: 2.
- Evidence-pack items: 6.
- Next-best actions: 4.
- Approval form absent because the selected matrix is review-required and the assessment is blocked.
- Visible boundary confirmed `municipalSubmissionCreated = false`.

Responsive and persistence checks:

- Desktop 1440 x 900: horizontal overflow `0`.
- Mobile emulation 390 x 844: one-column assessment form, eight categories, one history record, score 12%, horizontal overflow `0`.
- Desktop restored to 1440 x 900 and municipal workspace left open.
- After reload: one matrix, one assessment, score 12%, eight categories and horizontal overflow `0`.
- Console after reload: no warnings or errors.

## Direct MariaDB evidence

The bounded live development database contains:

- Matrix `419c26d9207e45cc44ea4265d26e6941`, status `review_required`, checksum `aa8604a0df6fd93173f17bfb224afd9c053bc39f97f18ad9248b483cefb3c3be`.
- Assessment `1fc78e5e9db1c56b6e693ce0cd329ac4`, complexity `high`, score `12`, professional-review-ready `false`.
- Assessment checksum `e12a0004d05b998bd6dab53d1116be43af24225ce00019bef27d04fd55ec3671`.
- Eight persisted categories and fourteen complete professional-routing decisions.
- `municipalSubmissionCreated` persisted as `false`.
- ProjectRecord `6f30998f4c00ae1e77dc81ca3fca326d`, type `municipal_submission_readiness`, status `pending_review`.
- Five open missing-professional tasks, reflecting existing live team evidence, plus one open lead-professional blocked-submission task.
- One `municipal_readiness.assessed` audit event.
- No professional readiness approval record was created in the live development organisation.

## Remaining release gate

This evidence certifies local implementation behavior only. Shared-hosting staging still requires FTP deployment, staging MariaDB migration, environment/secrets configuration, staged authenticated browser/API/database checks, and an approved promotion/rollback decision.
