# Pack 22 rendered role visibility correction

## Reproduced defect

Chrome DevTools MCP on the local PHP site at http://127.0.0.1:8810/:
the learner's five restricted forms had `hidden=true` but computed display
flex/grid and widths 264–292px at a 390px mobile viewport. The role manager
also rendered. Author CSS overrode the browser's default hidden styling.
Earlier attribute-only role checks did not establish actual invisibility.

## Fix and executed checks

- Added a CPD-scoped `[hidden] { display:none!important }` rule.
- Added reusable `tools/tests/cpd-role-visibility.browser.js` assertion.
  It failed before the CSS fix, identifying all six rendered hidden controls.
- Reloaded without cache and reopened CPD in Chrome. The learner assertion
  passed: six hidden elements have display none and zero dimensions;
  profile and verification forms remain visible at 390px.
- Desktop admin: all seven authorized forms retain nonzero widths.
- Desktop reviewer: content, assessment, course and commercial forms have
  zero width; profile, accreditation and verification remain visible.
- Desktop partner: content, assessment and commercial forms have zero width;
  profile, accreditation, course and verification remain visible.
- Course-access Node regression suite: 4 tests passed, 0 failed.
- `git diff --check`: passed (line-ending conversion warning only).

This is local rendered-visibility evidence, not full Pack 22 certification,
an all-role security audit, or remote deployment evidence. Creator visibility
and the broader remaining Pack 22 requirements still need verification.
No completion screenshots captured because the module remains incomplete.
