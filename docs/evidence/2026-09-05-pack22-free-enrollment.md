# Pack 22 free enrollment: connected verification

Pack 22 remains incomplete. This evidence covers only free enrollment and the existing CPD lifecycle exercised by the focused suite.

The previously recorded RED run failed at the explicit assertion that the service expose zero-cost enrollment (1 test, 12 assertions). The implementation is now restored across service, controller, route and UI.

Free enrollment checks active membership and a live, zero-priced course. It locks the course row inside the transaction before checking existing entitlements. An existing active free entitlement is returned; conflicting entitlement states are rejected. New enrollment, zero-value purchase ledger and audit event commit together.

Executed locally on PHP 8.3.32 and loopback MariaDB port 43320 using disposable database `architex_task22_fa570001_test`:

- `CPDAssessmentServiceTest.php`: PASS, 2 tests, 32 assertions, 5m 32.533s, process exit 0.
- `CPDAssessmentWorkspaceTest.php` and `CPDRoutesContractTest.php`: PASS, 2 tests, 64 assertions.
- PHP service syntax and `git diff --check`: PASS.

The connected lifecycle proves active free entitlement, zero amount, repeated enrollment returning the same ID, and exactly one zero-gross/zero-fee/zero-net ledger entry. It also exercises the existing paid assessment, manual review and explicit certificate flow. Concurrent requests, negative free-enrollment cases, automatic certificates and complete role/browser certification remain to be tested or implemented. No remote deployment is certified by this evidence.

Usage correction: `get_goal.timeUsedSeconds` is cumulative goal runtime. Dividing it by five hours does not establish the account's rolling usage percentage or reset time. Earlier pauses based on that calculation were not supported by account usage evidence.
