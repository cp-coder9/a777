# Target-scoped database writer fence: authoritative contract findings

Status: research only; **not an implemented or validated deployment fence**. Do not replace the existing writer gate with this document. No production mutation was performed.

Authorized deployment target: `archite4_dev_rel1`. Authorization is not proof of effective writer exclusion.

## Verified official UAPI contracts

- `Mysql/get_privileges_on_database`: required `user`, `database`; success example has `result.data` equal to an array of privilege strings (for example `["CREATE"]`).
  https://api.docs.cpanel.net/openapi/cpanel/operation/Mysql-get_privileges_on_database/
- `Mysql/set_privileges_on_database`: required `user`, `database`; `privileges` defaults to `ALL PRIVILEGES`. Documented input is `ALL PRIVILEGES` or a comma-separated list of **one or more** individual privileges. Replaces rather than adds to the existing list. Success example has `result.data: null`.
  https://api.docs.cpanel.net/specifications/cpanel.openapi/user-management/set_privileges_on_database
- `Mysql/revoke_access_to_database`: the documented revocation operation. Required `user`, `database`. Description: revokes a MySQL database user's privileges. Success example has `result.data: null`.
  https://api.docs.cpanel.net/specifications/cpanel.openapi/user-management/revoke_access_to_database

**Do not implement revoke by sending `privileges=""`.** The official setter contract does not establish empty-string revocation; omission defaults to ALL PRIVILEGES. Use the explicit revocation endpoint after separately verifying its availability on the authenticated account. No assertion is made that this host lacks the endpoint.

The official examples above show CLI envelopes (`result.status`, `result.data`). The existing read-only HTTPS transport expects the HTTPS envelope (`status`, `data`). Do not silently assume the response shape is identical; verify the actual HTTPS shape and allow null mutation data explicitly.

## Unverified prerequisites / remaining work

1. Complete authoritative create-user/create-database/delete-user/delete-database contracts and a separately reviewed HTTPS mutation transport. No guessed fields or assumed POST compatibility with GET-documented APIs.
2. On-host account/SSL/token-path identity and actual availability of `revoke_access_to_database`; never issue production mutation solely to discover capability.
3. Private, durable ownership journal with intent-before-effect and exact readback reconciliation of lost replies, original app-user grants, operator credentials, target/runtime hash and account binding.
4. Existing exact-docroot Apache 503 gate must be enforced and HTTP-probed before revocation. Do not alter parent hooks, runtime configuration or another owner's restore component.
5. Native disposable MariaDB proof of own-user `KILL CONNECTION`, matching both user and exact selected database, with observer retained and unknown/different-database sessions untouched. No GLOBAL privileges, wildcard grants or unobserved writer identities may invalidate exclusion.
6. Pre-operator inventory must prove the app user is the sole target grant user. Post-grant inventory must prove the expected app/operator set. Operator may access only source and capability-owned restore target.
7. Fresh app PDO must demonstrably fail real source-table access after revoke; source database open sessions must be drained using the retained app observer. Existing connections cannot be assumed to lose cached access.
8. Actual private readiness/recovery receipt binding before restoring the exact original privilege list; retain maintenance on every partial failure. Explicit recovery restoration is not deployment settlement. Never automatically drop an operator or source database.

No component tests or native grant/revoke/KILL proofs were executed for this proposed strategy. The prior independent inventory evidence supplied by the caller is context, not a new proof made by this document.
