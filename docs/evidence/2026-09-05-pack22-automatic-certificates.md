# Pack 22 automatic certificate issuance

Local verification only. Pack 22 and the broader packs 21-32 goal remain incomplete.

The connected regression first failed as expected with zero certificates after a passing human review (1 test, 21 assertions, 5m 48.036s).

The service now issues the certificate and CPD record inside the transaction that records either an automatically scored pass or a human-reviewed pass. The common issuance routine locks the attempt, reuses an existing certificate, retains course/profile eligibility checks, and records the actual actor in the audit event. Human review now locks and reloads the attempt inside its transaction. The explicit certificate endpoint uses the same routine for idempotent retrieval/issuance. The passed-attempt UI links to My CPD record.

Verification on PHP 8.3.32, loopback MariaDB port 43320, disposable database `architex_task22_fa570001_test`:

- Connected `CPDAssessmentServiceTest`: PASS, 2 tests, 41 assertions, 6m 05.746s.
- Existing UI/route contracts: PASS, 2 tests, 64 assertions.
- PHP and JavaScript syntax: PASS.

Connected assertions cover reviewed-pass issuance, automatically scored issuance, certificate/record counts, repeated explicit issuance without duplicates, and review rollback when course suspension prevents certificate eligibility. The existing paid/free enrollment and certificate PDF/verification lifecycle also passes.

Remaining certification includes all-role browser workflows, concurrency tests, broader eligibility/privacy review, historical passing attempts without certificates, professional-body unit rules, verification-link UX, and the rest of Pack 22 requirements. No completed-module screenshot or deployment claim is made here.
