# Pack 22 UTF-8 certificate renderer

Replaced the ASCII-replacement writer with tFPDF 1.33 and its embedded DejaVu
Sans Condensed font. The existing title, award snapshot, verification link,
pagination and green/neutral presentation remain. This adds mbstring/zlib as
explicit production requirements; the FTP runbook lists the font/package gate.

Official integration references:
- https://www.fpdf.org/en/script/script92.php
- https://packagist.org/packages/setasign/tfpdf

## Executed evidence

- New Unicode regression failed with the old renderer, then passed with an
  embedded font and UTF-16BE text for `Zoë Šimek – Αθήνα`.
- Full unit suite before the subsequent cache-path correction: 198 tests,
  1650 assertions passed. Final focused CPD suite: 8 tests, 70 assertions passed.
- Connected CPDAssessmentServiceTest rerun after Unicode encoding updates:
  2 tests, 56 assertions passed in 7m25s. It used only loopback MariaDB port
  43320 and the guarded disposable `architex_task22_fa570001_test` database.
- Complete release suite with the same explicit guard: 4 tests, 24 assertions,
  0 skipped (11m11s). This includes encrypted backup and isolated restoration;
  the normal unguarded run skips that destructive-test path by design.
- Composer strict validation and local non-dev platform requirements passed.
- Composer audit: no reported vulnerability advisories.
- Long-field Unicode fixture rendered through PyMuPDF: two pages, no repair,
  Unicode text extracted exactly, one verification link per page, all text
  bounding boxes inside the pages. Both images visually inspected.
- Chrome authenticated learner download: HTTP 200, PDF 1.4, embedded FontFile2,
  14960 bytes for the existing local certificate fixture.
- Cache relocation fault injection: temporarily set the generated local font
  cache path to a nonexistent directory. Unicode test failed before the path
  correction and passed after it. The original generated cache was restored
  in a finally block. Production renderer now resolves the installed font path.
- Immutable release candidate `release22-unicode-final`: built locally from
  source commit `0dfac4b`, manifest verification passed with 456 files and the
  production TTF is present (680264 bytes). A separate ignored QA copy rendered
  Unicode text (14197 bytes) and survived the same intentionally invalid cache
  path. The untouched candidate re-verified afterwards. `composer
  check-platform-reqs --no-dev` passed locally for PHP 8.3, including mbstring
  and zlib.

## Limits

Tested accented Latin and Greek font coverage, not every Unicode script or
complex-script shaping. Actual remote PHP extensions and packaged-candidate
rendering remain deployment gates.
Library/font licensing files must remain in the production vendor package.
No remote deployment or module-completion screenshots; Pack 22 is incomplete.
