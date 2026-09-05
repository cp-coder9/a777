# Pack 22 certificate URL wiring

## Defect and change

Certificate serialization generated an unregistered `/cpd/verify/` path, and
the HTTP factory omitted the controller's configured verification origin.
The factory now receives MailRuntime's validated APP_URL from the application
entrypoint and passes it to CPDController. CertificateVerificationUrl targets
the registered public JSON verification endpoint and preserves an installation
path. Without an origin it returns a same-origin API path rather than null.

## Executed verification

- New URL tests observed failing before implementation, then passed.
- CPD unit suite: 5 tests, 55 assertions passed.
- HTTP kernel suite: 17 tests, 82 assertions passed.
- CPD route contract: 1 test, 24 assertions passed.
- PHP syntax checks passed for index.php and AuthenticationKernelFactory.php.
- Chrome DevTools MCP in the existing local learner session: idempotent
  certificate issuance returned 201 with a configured http://127.0.0.1:8810
  verification URL. Following the returned URL with credentials omitted
  returned 200 and valid=true for the existing synthetic certificate.
- `git diff --check` passed with line-ending conversion warnings only.

This fixes the API certificate URL, not PDF delivery or a human-readable
public verification page. PDF link/title/layout work and connected suite
reruns remain. Pack 22 is incomplete; no module-completion screenshots taken.
