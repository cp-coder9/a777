# Release 3 BIM / IFC metadata: local certification

**Date:** 2026-08-28  
**Scope:** Local PHP 8.3, MariaDB and canonical V1 browser evidence only  
**Release boundary:** This is not shared-hosting staging, FTP deployment, native IFC interpretation or production certification.

## Implemented capability

- Tenant- and project-scoped controlled model-source register.
- Safe private IFC exchange-file upload with MIME/extension checks, IFC STEP signature validation, byte-count verification and SHA-256 integrity checks.
- Exact source format, schema, revision, original filename, extraction mode, provider declaration, assumptions and responsible role.
- Append-only structured metadata revisions with optimistic source-version enforcement.
- Bounded canonical facts covering model, spatial, element, opening, fire, parking, site, schedule, note and title-block categories.
- Deterministic category counts, duplicate external-ID errors, missing source-reference warnings and professional-verification flags.
- Append-only approve, reject and flag decisions with exact project-role authorization, human confirmation and reason.
- Immutable SHA-256 issue snapshot, ProjectRecord and audit trail.
- Authenticated, CSRF-protected PHP API and canonical V1 BIM / IFC workspace.
- Honest provider boundary: native IFC/RVT/DWG interpretation, geometry extraction, automated quantities, clash detection and model synchronisation remain unavailable.

## Automated evidence

- Model domain suite: **3 tests, 28 assertions**, passed against the explicitly disposable MariaDB database.
- Authenticated HTTP suite: **1 test, 14 assertions**, passed through session, organisation, CSRF and permission middleware.
- Canonical frontend contract: **1 test, 17 assertions**, passed after reload-restoration coverage was added.
- Applicable file-security regression: **2 tests, 9 assertions**, passed for executable-content rejection and private PDF integrity/download behavior.
- `node --check app/public/assets/js/shell.js`: passed.
- PHP lint for the model service, exception, controller, routes, factory and tests: passed.
- `git diff --check`: no whitespace errors; repository line-ending warnings only.

The integration tests used only `architex_task13_facefeed_test`. They did not reset or mutate the bounded live development database.

## Migration evidence

Before migration, `database-status.php` reported **23 applied; 1 pending**, specifically `0024_bim_ifc_metadata`. The migration command applied one migration to the bounded local development database `architex_task7_live`. The final status was **current; 24 applied; 0 pending**.

## Persistent Chrome DevTools evidence

The existing authenticated owner session remained open at `http://127.0.0.1:8790/` in Chrome page 2. The selected project remained `304dc7da987382b3861b5a80fbe27470` (`SpecForge DevTools Project`), created by the browser owner with lead role `architect`.

The browser completed the visible canonical workflow:

- Opened live tool `bim_ifc` (`BIM / IFC Extraction`) in project orientation.
- Uploaded a real IFC STEP exchange payload through the multipart file endpoint.
- Registered source `66ab424ad6ccc298698ad3590126e86b`, **DevTools controlled coordination model**, schema `IFC4`, source revision `C01`.
- Visible source filename: `browser-coordination.ifc`.
- Visible source SHA-256: `4673b8ca7b928240d7b693da203caf0dabc1c41d87d244c2a12a86bfdfef7358`.
- Visible provenance: `human entered`; `nativeParsingAvailable false`; `externalProviderVerified false`.
- Created metadata revision `38ce1b4e3054ef1f783ac86dfa052187`, label `Metadata C01-a`, with four source-referenced facts.
- Deterministic validation showed door, level, space and title-block counts; no blocking errors; one verification-required fact (`DOOR-01`); zero missing source references.
- Approved under exact acting role `architect` with retained review reason.
- Issued version 4 after explicit professional confirmation.
- Visible issued SHA-256: `214e23c73788b1e1e404bcbcd34f1795438ba08f1d52d6ccb78b3a43a16d8ac1`.
- Visible ProjectRecord: `1588bd888450a2b0521555050da418f2`.
- Revision, review and issue controls became disabled after issue.

Cache-bypassing reload restored the exact source/issue hashes, revision label, professional role, review confirmation and reason, issue confirmation and reason, issued status and disabled controls from MariaDB. The reload exposed and verified a fix for the initially missing decision-form restoration.

Responsive evidence at 390 x 844:

- inner width, body width and document width were all 390 px.
- horizontal overflow was false.
- the workspace rendered as a block and the revision form used one 254 px column.
- the facts editor width was 254 px.

Desktop 1440 x 900 was restored and the issued BIM / IFC workspace was left open. The final reload made one model-list request and one model-detail request, both HTTP 200. Chrome reported no JavaScript errors or warnings. One existing canonical autofill issue for a transient form control without an id/name remains; the final DOM contained no unnamed input, select or textarea.

## Direct MariaDB and private-storage correlation

- Source status/version: `issued` / `4`.
- Stored and independently recomputed source-file SHA-256 both equal `4673b8ca7b928240d7b693da203caf0dabc1c41d87d244c2a12a86bfdfef7358`.
- The private stored file exists outside the public web root.
- Stored and independently recomputed issue snapshot SHA-256 both equal `214e23c73788b1e1e404bcbcd34f1795438ba08f1d52d6ccb78b3a43a16d8ac1`.
- Issued metadata revision: `38ce1b4e3054ef1f783ac86dfa052187`, revision number 1, label `Metadata C01-a`.
- Append-only decision: action `approve`, acting role `architect`, exact Chrome review reason retained.
- Issued ProjectRecord: `1588bd888450a2b0521555050da418f2`, module/type `model_metadata/model_metadata_snapshot`.
- Audit trail contains exactly one each of `file.uploaded`, `model.source.created`, `model.metadata.revision_created`, `model.metadata.approve` and `model.metadata.issued` for this workflow.

## Remaining release gate

This evidence certifies local behavior only. Shared-hosting staging still requires an FTP artifact deployment, staging MariaDB migration, host-specific PHP 8.3/environment configuration, staged authenticated browser/API/database checks, cron verification, rollback rehearsal and an approved promotion decision. Native IFC/Revit interpretation also remains unavailable until a real licensed parser/provider is integrated and separately certified.
