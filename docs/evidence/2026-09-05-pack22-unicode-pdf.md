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

## Limits

Tested accented Latin and Greek font coverage, not every Unicode script or
complex-script shaping. Connected integration assertions have been adapted to
the Unicode PDF encoding but have not been rerun in this turn. Actual remote
PHP extensions and packaged-candidate rendering remain deployment gates.
Library/font licensing files must remain in the production vendor package.
No remote deployment or module-completion screenshots; Pack 22 is incomplete.
