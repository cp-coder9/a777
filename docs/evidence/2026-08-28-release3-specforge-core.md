# Release 3 SpecForge core: local certification

**Date:** 2026-08-28  
**Scope:** Local PHP 8.3, MariaDB and canonical V1 browser evidence only  
**Release boundary:** This is not shared-hosting staging, FTP deployment, external distribution or production certification.

## Implemented capability

- Tenant- and project-scoped versioned specification workspaces.
- Human-entered controlled sections and product records with source references, cost allowance, estimate, lead time and responsibility roles.
- Optimistic workspace version enforcement and immutable issued state.
- Append-only approve, reject and flag decisions with exact acting-role authorization.
- Derived over-budget, long-lead, client-decision and approval risk.
- Explicit professional confirmation and issue reason.
- SHA-256-bound issue snapshot, controlled specification document revision, ProjectRecord and audit trail.
- Authenticated, CSRF-protected PHP API and canonical V1 SpecForge workspace.
- Honest provider boundaries: no AI drawing interpretation, supplier ordering, RFQ, payment, PDF generation, digital signature or external delivery is claimed.

## Automated evidence

- Focused frontend, domain and authenticated HTTP run: **5 tests, 67 assertions**, PHP 8.3.32, MariaDB, all passed.
- The suite covered tenant isolation, stale versions, role denial, immutable issue, CSRF and persisted browser contracts.
- 'node --check app/public/assets/js/shell.js': passed.
- 'git diff --check': no whitespace errors; repository line-ending warnings only.

The integration tests used the explicitly disposable 'architex_task13_facefeed_test' database. They did not use or reset the bounded live development database.

## Migration evidence

Migration '0023_specforge_core.sql' is applied to the bounded local development database. 'database-status.php' reported **current; 23 applied; 0 pending**.

## Persistent Chrome DevTools evidence

The existing authenticated owner session remained open at 'http://127.0.0.1:8790/' in Chrome page 2.

Authorization was proved fail-closed first:

- Existing project 'af189b7f799637ca7918b1a3d6858292' was not created by the browser user and had no active architect team assignment for that user.
- The visible **Approve** action sent the exact 'architect' acting role and received HTTP '403' with 'specforge_decision_role_denied'.
- No decision or issue was created for that unauthorized attempt.

The same browser then created architect-led project '304dc7da987382b3861b5a80fbe27470' and completed the authorized workflow:

- Workspace 'f8463a46a7ea59d0b5aa547a107b76f0', **Controlled interior specification**, revision 'C01', purpose 'for_construction'.
- Section '09 · Internal finishes'.
- Product 'FIN-WT-002 · Large format porcelain wall tile'.
- Allowance R10,000; estimate R12,500; delta R2,500; lead time 70 days.
- Product status changed from 'draft' to 'approved' under acting role 'architect'.
- Exact issue was confirmed by the responsible professional with a retained reason.
- Workspace advanced to version 5 and status 'issued'.
- Visible SHA-256: 'f3941f5e4169e44c8dcd74a1f118cf63308f48f8e105444c039c5f89cf2fcf9b'.
- Visible boundary remained 'externalDeliveryClaimed false'.
- Draft mutation controls became disabled.

Reload and responsive checks:

- A cache-bypassing reload restored version 5, the approved product, exact SHA-256 and immutable disabled controls from MariaDB.
- The issued professional role, confirmation and exact issue reason restored after reload.
- Mobile emulation at 390 x 844 had document width 390, horizontal overflow 'false', and a one-column 254 px section form grid.
- Desktop 1440 x 900 was restored and the issued SpecForge workspace was left open.
- Chrome reported no JavaScript errors or warnings. One browser autofill issue from the canonical project datum range control was recorded for later accessibility cleanup.

The browser run also exposed and verified fixes for stale optimistic-version submit closures, lingering progress messages and issued-reason restoration.

## Direct MariaDB correlation

- Workspace status/version: 'issued' / '5'.
- Stored and independently recomputed snapshot SHA-256 both equal 'f3941f5e4169e44c8dcd74a1f118cf63308f48f8e105444c039c5f89cf2fcf9b'.
- Approved item: 'dc93ff2b350989de830f86ce09330d41'.
- Append-only approval decision: action 'approve', acting role 'architect'.
- Controlled document: '321f69e00a64168b9f05a15b9c5b80b6', type 'specification'.
- Issued document revision: 'ad1a96b8302fadc8ed03afc96fdd60bd', revision 'C01', with the same audit hash.
- Issued ProjectRecord: 'd7417247c273aef24c4f7bffa1230181', module/type 'specforge/specification', with the same snapshot hash.
- Audit trail includes 'specforge.workspace_created', 'specforge.section_created', 'specforge.item_created', 'specforge.item_approve' and 'specforge.workspace_issued'.

## Remaining release gate

This evidence certifies local behavior only. Shared-hosting staging still requires an FTP artifact deployment, staging MariaDB migration, host-specific PHP 8.3/environment configuration, staged authenticated browser/API/database checks, cron verification, rollback rehearsal and an approved promotion decision.
