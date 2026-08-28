# Release 4 procurement marketplace local evidence

## Delivered boundary

The canonical `v1.html` remains unchanged at SHA-256 `7dbfafdcfa52644fd32fb7cb7d73891b78593ca0287c089240db4439e0fa97bf`. Its RFQ Marketplace identity and shared Datum OS shell now open a persistent PHP 8.3 and MariaDB workspace. Marketplace matching and quote comparison are advisory; no automatic award, contract, signed appointment, statutory threshold, or email delivery is claimed.

Implemented scope includes public-safe marketplace listings, controlled project packages, immutable issue revisions, one-time invitation tokens stored only as SHA-256, bidder acceptance, material clarifications, equal-information addenda, immutable quote revisions, returnables, exclusions, qualifications, conflict and lateness facts, comparison snapshots, award recommendations, distinct client/professional decisions, ProjectRecords, inbox/audit evidence, and an explicitly unsigned appointment placeholder.

## Executed tests

- Frontend suite: `16 tests, 278 assertions` passed.
- Procurement UI contract: `1 test, 26 assertions` passed after the delayed scaffold-loader ownership regression was added.
- Procurement service MariaDB test: red at `procurement_professional_authority_not_verified`, then green at `1 test, 43 assertions` after accepting independently reviewed professional registration evidence from the professional's own practice tenant.
- JavaScript syntax, PHP syntax, and `git diff --check` passed.
- Local migration runner applied `0028`: `applied: 1; pending: 0; status: current`.

## Chrome DevTools evidence

Persistent Chrome contexts remained open for the owner, two public bidder tokens, and the independent engineer. The browser exercised:

1. a controlled tender document through draft, review, approval, and issue;
2. two independently published specialist-consultant listings;
3. package `R4-RFQ-001`, issued at SHA-256 `ff552b2923989e7f3dbe3d5b0835275bdb0d34b620321b3b60a87de937cdf26c`;
4. two invitations accepted in isolated public bidder contexts;
5. one material clarification and addendum `1`, visible to both bidders with the same SHA-256 `788dffbf292cdb45b6325aaef132e4f332485d74d1996a6337d143912bb3a8f5`;
6. two quotes preserving a higher complete conflict-free offer and a lower offer with one missing returnable plus a declared conflict;
7. an advisory comparison and human award recommendation;
8. client approval by `80c7420...` and professional engineer approval by distinct user `2af5f29b...`;
9. placeholder state `appointment_pending_formalisation`, SHA-256 `69696c70131d14a9575aa929e968df0b8b3c66ca65e57ad2b87be5cc7ba4886d`, explicitly shown as not a contract or signed appointment;
10. cache-bypassing reload persistence and a stable workspace after 2.5 seconds, proving the legacy delayed sample loader no longer replaces it.

All eight observed post-reload session/project/procurement fetches returned HTTP `200`, and a final Chrome console check found no errors, warnings, or issues. Chrome Lighthouse snapshot scores were Accessibility `96`, Best Practices `100`, and Agentic Browsing `100`. The remaining accessibility findings are inherited shell colour-contrast and global-navigation accessible-name issues; the procurement form-label failure was fixed. SMTP remains unavailable, so invitation links were copied and no email-delivery claim was made.

## Direct MariaDB readback

The live local database reports one issued package, two invitations, one equal-information addendum with two distributions, two quotes, one comparison, two distinct approvals, and one appointment placeholder. No remote/staging MariaDB mutation or release claim is included in this evidence.

## Remaining release gates

This is local Release 4 procurement certification only. Aggregate regression, backup/restore, load/recovery, immutable FTP bundle creation, shared-host staging MariaDB migration, TLS/domain/docroot/cron checks, SMTP configuration, staging browser smoke, and explicit production promotion remain separate gates.
