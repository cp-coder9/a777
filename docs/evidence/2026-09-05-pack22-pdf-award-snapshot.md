# Pack 22 certificate award snapshot

Certificate PDFs now read unit labels from the immutable professional record's
rule_snapshot alongside its activity title. The renderer displays hours,
points or credits without guessing; manual compliance is not presented as a
numeric award and absent units remain pending confirmation.

## Executed evidence

- New award-field regression failed before implementation, then passed.
- CPD unit suite: 7 tests, 68 assertions passed. Covers points, hours, credits,
  manual compliance and missing rule metadata.
- Service PHP syntax check passed.
- Chrome DevTools MCP: reopened learner context, authenticated certificate
  download returned 200 with `CPD award: 0.1 credits` from the saved record.
- Download rendered locally: one page, no parser repair, award text extracted
  correctly and visually inspected without clipping or overlap.
- Added a connected integration assertion that changes the live rule after
  issuance and still expects the original certificate unit. This additional
  assertion has not yet been executed against MariaDB.

No production body-rule claims or remote changes. Temporary QA artifacts are
not module-completion screenshots. Unicode rendering and broader Pack 22
requirements remain incomplete.
