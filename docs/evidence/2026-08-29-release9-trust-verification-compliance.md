# Release 9 local certification — Trust, Verification & Compliance

Date: 2026-08-29
Scope: Pack 13, local staged-release gate
Runtime: PHP 8.3.32, MariaDB 11.4 at `127.0.0.1:43319`, persistent Chrome DevTools page at `http://127.0.0.1:8790/`

## Implemented scope

- Tenant-scoped professional verification with a South African professional-body allowlist, distinct evidence grades, private uploaded evidence, independent owner/admin review, expiry status and a cross-user organisation review queue.
- Structured professional, company, insurance and contractor/supplier compliance records with compatible subject/category enforcement, issuer/reference fields, private evidence, expiry and insurance coverage-gap calculation.
- External-verification status requires a real provider and provider reference; self-review is denied. Architex does not simulate or claim an external check.
- POPIA processing register with lawful basis, data subjects/categories, recipients, safeguards, ownership and retention.
- Data-subject request workflow for access, correction, deletion, objection and portability, with governed transitions and immutable terminal state.
- POPIA breach workflow with containment/closure controls. Required regulator notification must have a recorded timestamp before closure; Architex does not send the notification.
- Compliance risks, appointment blockers and recommendations backed by ProjectRecords, Inbox and audit events. A coverage-gap blocker cannot be narratively cleared and requires a distinct, effective replacement evidence item.
- MariaDB-backed workspace aggregation and rendered Settings workspace for the complete trust and compliance flow.

## Automated evidence

- Professional verification connected suite: `OK (3 tests, 19 assertions)` in 08:25.645 against the explicitly disposable database `architex_task13_facefeed_test`.
- Structured compliance/POPIA connected suite: `OK (2 tests, 34 assertions)` in 05:29.473 against the same disposable database.
- Final frontend workspace contract: `OK (1 test, 16 assertions)`.
- `php -l` passed for `VerificationService.php`, `TrustComplianceService.php`, both controllers, both route registrars and `AuthenticationKernelFactory.php`.
- `node --check app/public/assets/js/shell.js` passed.
- `git diff --check` passed; only Git's informational LF-to-CRLF warnings were emitted.

## MariaDB evidence

- Migration `0034_trust_verification_compliance.sql` is applied to the bounded local development database `architex_task7_live`.
- Final `database-status.php --env=development`: current, 34 applied, 0 pending.
- Chrome-driven records persisted structured insurance coverage gaps, current replacement coverage, a processing activity, a fulfilled data-subject request and a closed breach with its required notification timestamp.
- Narrative-only coverage-risk resolution returned HTTP 422. Resolution with a distinct current replacement evidence item succeeded and retained the original evidence chain.
- Security negatives returned the intended status codes: missing provider HTTP 422, self-review HTTP 403, anonymous workspace HTTP 401 and missing CSRF HTTP 419.
- A pre-hardening browser-only risk transition was explicitly rolled back in the bounded local database. The later replacement-evidence resolution remains as the valid proof record.

## Chrome DevTools evidence

- Persistent authenticated Chrome page ID 12 remains open on the Settings trust workspace for project `304dc7da987382b3861b5a80fbe27470`.
- The accessibility tree showed the professional-registration-only legacy form, populated organisation review queue, structured compliance evidence, POPIA processing register, data-subject request, breach workflow, compliance badges and risk/blocker sections.
- The review queue displayed another organisation member's SACAP document-uploaded record with separate Manual review and External evidence controls.
- Persisted browser records displayed coverage-gap and current badges, a fulfilled request, a closed breach, open blockers and a replacement-evidence-resolved blocker.
- Exact mobile emulation at 390 × 844 rendered all 14 trust workspace headings with `clientWidth=390`, `scrollWidth=390` and zero horizontal overflow.
- Restored desktop viewport was 1440 × 900 with zero horizontal overflow. Final reload-and-wait console: zero errors, warnings or issues.
- Final Lighthouse snapshot, desktop: Accessibility 100, Best Practices 100, SEO 100, Agentic Browsing 100; 31/31 audits passed.
- Final Lighthouse snapshot, mobile: Accessibility 100, Best Practices 100, SEO 100, Agentic Browsing 100; 31/31 audits passed.

## Preserved source and boundaries

- Canonical `v1.html` was not edited. SHA-256 remains `7DBFAFDCFA52644FD32FB7CB7D73891B78593CA0287C089240DB4439E0FA97BF`.
- Pack source material remains untracked and is not part of the deployable application commit.
- This is local staged-release certification only. No FTP upload, shared-host migration, SMTP delivery, remote backup rehearsal, professional-body/insurer/regulator integration or production promotion is claimed.
- SMTP remains intentionally fail-closed until real shared-host credentials are configured.
