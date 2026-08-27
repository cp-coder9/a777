# Release 2 professional proposal and acceptance evidence

Date: 2026-08-28
Scope: local source runtime only; this is not staging or shared-hosting certification.

## Implemented

- An owner can create an organisation- and project-scoped proposal from an exact issued professional-fee calculation.
- The proposal snapshots the fee output, assumptions, exclusions, selected draft terms, custom terms, client identity, validity period, and notes.
- Issue requires separate responsible-professional and legal-review confirmations plus a reason.
- Each issued version has an immutable SHA-256 audit hash and a one-time plaintext acceptance URL; MariaDB retains only the token hash.
- The public capability route exposes only the exact issued proposal and records the acceptor name, explicit confirmation, timestamp, IP context, user agent, version, and hash.
- Issued or accepted proposals can be revised into a new draft version. Issuing a replacement supersedes the prior version and revokes its pending acceptance token.
- Public acceptance is intentionally available without a session or CSRF token only at the exact high-entropy capability route; ordinary authenticated unsafe routes remain CSRF protected.
- SMTP is not represented as available. The owner UI instructs users to copy the one-time URL through an approved channel.

## Chrome DevTools MCP proof

Runtime: `http://127.0.0.1:8790/`, PHP 8.3.32, MariaDB on the task-owned local instance.

Visible owner and isolated-recipient flow:

1. Built `Professional Fee Proposal - DevTools Verified Project` from the issued R57,900 fee calculation.
2. Added two assumptions, two exclusions, standard and architectural draft terms, and one custom project term.
3. Confirmed both issue gates and issued version 1.
4. Owner view displayed SHA-256 `6f7107f961e10cd188a9d6c9fd03ba577b7afeabf39fc8b8f534c9d4589ea5ee` and the one-time acceptance URL.
5. Opened the URL in an isolated Chrome context and verified the exact title, version, R57,900 total, assumptions, exclusions, terms, hash, and external-signature-formality warning.
6. Accepted as `Evidence Client`; the isolated page then displayed `This proposal is accepted.`
7. Refreshed the owner view and verified the accepted status and same audit hash.
8. Chrome showed no error or warning console messages on either owner or recipient page.
9. At 390 x 844 the public page rendered within a 362 px card, wrapped the full audit hash, and reported `scrollWidth: 390` with no horizontal overflow. A browser-discovered grid cascade defect was reproduced, regression-tested, fixed, and reverified before recording this evidence.
10. Both pages remain open persistently at 1440 x 900.

## Automated proof

- `ProposalServiceTest`: 1 test, 20 assertions, passing against disposable MariaDB, including acceptance and version-2 supersession behavior.
- `ProposalHttpTest`: 1 test, 9 assertions, passing against disposable MariaDB across owner issue, public accept, and revision endpoints.
- `MiddlewarePipelineTest`: 4 tests, 16 assertions, passing, including the narrow public-token CSRF exception and retained protection for authenticated unsafe routes.
- `ProposalWorkspaceTest`: 1 test, 14 assertions, passing, including public production-control and mobile-layout regressions.

## Remaining gate

Staged shared-hosting upload, real hosting MariaDB migration, HTTPS/session verification, outbound-email integration, and remote browser evidence remain intentionally unclaimed.
