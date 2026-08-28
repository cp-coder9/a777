# Release 3 document transmittals and drawing-intelligence evidence

Date: 2026-08-28  
Scope: local development only  
Runtime: PHP 8.3.32, MariaDB 11.4, `http://127.0.0.1:8790/`  
Release boundary: this is not shared-hosting staging or production certification.

## Implemented boundary

- Transmittals select exact approved or issued controlled revision IDs with a matching issue purpose.
- Formal issue writes an immutable canonical JSON snapshot and SHA-256, one issued ProjectRecord, one audit event and one internal inbox/workflow event per named role.
- External email and external delivery are explicitly recorded as unavailable/not claimed.
- Drawing-intelligence candidates are either `human_entered` or future `provider_result` evidence. Human entries require null provider/confidence fields. Provider results require provider name, reference and numeric confidence.
- Candidates never update controlled document or drawing-register metadata. Accept/reject is a separate confirmed human review with reason, ProjectRecord and audit evidence.
- No OCR, PDF parsing, AI classification, digital signature or external delivery provider is claimed.

## Automated proof

Focused final gate:

```text
5 tests, 88 assertions
DocumentTransmittalServiceTest
DrawingIntelligenceServiceTest
DocumentEvidenceHttpTest
DocumentEvidenceWorkspaceTest
DocumentWorkspaceTest
```

Broader gate:

```text
Unit + Requirements + core HTTP + authenticated integration HTTP + Frontend + Security
149 tests, 683 assertions
Time: 17:01.560
Result: OK
```

Post-layout focused rerun:

```text
2 frontend tests, 39 assertions
Result: OK
```

Additional checks:

- PHP syntax: clean for new service, controller, route and tests.
- JavaScript: `node --check app/public/assets/js/shell.js` passed.
- `git diff --check`: clean apart from Windows LF-to-CRLF notices.
- Local migration status: 22 applied, 0 pending after applying `0022_document_transmittals_intelligence`.

## Chrome DevTools MCP proof

Persistent authenticated page: page 2, `browser-owner@example.test`, owner role.  
Organisation: `9775135843504f4eb3f9656493770756`.  
Project: `af189b7f799637ca7918b1a3d6858292`.

The canonical Documents & Drawings workspace was reloaded without cache and used through visible controls.

### Exact-revision transmittal

- Code: `TR-C02-001`
- ID: `68b6e7988c6d56539edcc74440b9284b`
- Purpose: `for_construction`
- Exact revision: C02, `357e8b18e5437864cb30c180a2b0e5c3`
- Recipient roles: `architect`, `contractor`
- Status: `issued`
- Snapshot SHA-256: `c783a45e1afdfa218eb2254bb3c42031fbb206a18f2004a033be137594c80352`
- UI states: one exact revision; internal inbox only; external delivery not claimed.

### Human-entered candidate

- ID: `9c01f76101699a8069b696dfee837ae7`
- Revision: A-301 / C02, `357e8b18e5437864cb30c180a2b0e5c3`
- Provenance: `human_entered`
- Confidence: unavailable/null
- Status: `accepted`
- Candidate SHA-256: `abd020c385f0a211675f299fef3816cdf30f1ff62a57ea8d03386c9e00323d4c`
- UI state: metadata applied `no`.

After a forced page reload, both records and exact hashes reappeared from the APIs. Chrome console warnings/errors: none.

Responsive checks:

- Mobile emulation: 390 x 844, evidence grid one column (`292px`), document width `390px`, zero horizontal overflow.
- Desktop restored: 1440 x 900. The shell main pane was 701px wide with both context panels open.
- A discovered 95px internal overflow was traced to fixed 330/430px document-grid minima and corrected to zero-minimum fractional tracks.
- Final desktop metrics: window overflow 0, main overflow 0, workspace overflow 0; document tracks `282.906px 360.094px`; evidence tracks `321.5px 321.5px`.
- The browser remains open persistently on the desktop Documents & Drawings workspace.

## MariaDB correlation

Direct bounded queries against `architex_task7_live` showed:

- transmittal snapshot checksum equals `SHA2(snapshot_payload, 256)`;
- candidate checksum equals `SHA2(candidate_payload, 256)`;
- exact transmittal revision is C02 `357e8b18e5437864cb30c180a2b0e5c3`;
- controlled source remains `DevTools construction floor plan`, C02, A-301, 1:50;
- one issued `document_transmittal` ProjectRecord;
- one approved `drawing_intelligence_review` ProjectRecord;
- two `document_transmittal_issued` internal inbox items;
- lifecycle audits: transmittal created/issued and candidate created/accepted, one each.

Local CLI output warned that server-certificate verification was disabled for this loopback MariaDB connection. This does not certify remote database transport or shared-hosting staging.

## Release status

This slice is locally verified. Shared-hosting staging, FTP package integrity, hosting control-panel configuration, remote MariaDB transport, backup/rehearsal, cron execution and staged rollout/rollback remain separate release gates.
