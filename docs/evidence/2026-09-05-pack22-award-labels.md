# Pack 22 award labels and long-field PDF checks

The course catalogue previously hardcoded hours for SACQSP and credits for
every other body. It now uses the stored professional-body rule's unitLabel.
Issued records use their immutable ruleSnapshot rather than reinterpret old
awards through a changed live rule. Manual compliance records are not displayed
as numeric credits; missing units explicitly remain pending confirmation.
This implements configured data presentation, not certification of body rules.

## Executed checks

- New JavaScript regression suite failed before implementation and passed after.
- Combined award-label/course-access suites: 9 tests passed, 0 failed.
- Node syntax check and git diff whitespace check passed.
- Connected CPDAssessmentServiceTest: 2 tests, 55 assertions passed in
  5m38s; process exited 0. Target was loopback port 43320, disposable database
  architex_task22_fa570001_test, guarded with APP_ENV=test and explicit opt-in.
  This rerun covers the accumulated certificate/privacy/PDF assertions. It does
  not constitute all-role or all-workflow E2E certification.
- Chrome DevTools MCP learner mobile reload: persisted course catalogue and
  record both show SACAP 0.1 credits for the real local fixture.
- Synthetic long-field PDF fixture: two pages, no parser repair, one link per
  page and all extracted text bounding boxes inside the pages. Both rendered
  pages inspected with no clipping/overlap. QA images remain temporary; no
  module-completion screenshots were captured.

The PDF still uses a generic award label and ASCII-only glyph handling.
Other professional-body UI fixtures and the remaining Pack 22 flows still need
live E2E verification. Pack 22 remains incomplete.
