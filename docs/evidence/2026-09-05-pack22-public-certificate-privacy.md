# Pack 22 public certificate verification boundary

The public verification endpoint previously serialized the entire certificate
row, exposing tenant, learner, attempt and course identifiers plus the full
professional registration number. It now selects only award evidence and
serializes an explicit allowlist, protecting against future query expansion.
Authenticated certificate download and issuance are unchanged.

## Executed evidence

- New unit regression failed before implementation with the unwanted fields.
- CPD unit suite: 3 tests, 51 assertions, no failures or notices. Both valid
  and revoked certificates are covered; unexpected future columns are excluded.
- PHP syntax check passed for CPDAssessmentService.php.
- Full unit suite: 193 tests, 1631 assertions passed with no reported issues.
- Chrome DevTools MCP, local PHP/MariaDB site: credentials-omitted GET of the
  existing synthetic certificate returned 200 and valid=true. Exact keys:
  verificationCode, learnerName, professionalBody, approvedCategory,
  creditsAwarded, accreditationReference, providerName, issuedAt, valid.
- Existing learner verification form submitted the same code successfully and
  rendered the correct learner, body, credits and accreditation reference.
- Connected integration assertion updated for the new public contract; that
  database rebuild suite has not been rerun for this change.

The PDF course title and usable verification link still need implementation.
This is not complete Pack 22 certification; no completion screenshot taken.
