# Pack 22 certificate PDF delivery

- Certificate download now reads the immutable professional-record activity
  title instead of omitting the course title or reading a mutable course name.
- Added assessment pass status and a clickable public verification link using
  the configured application URL. Existing membership/ownership guards remain.
- Extracted a renderer with conservative field wrapping, pagination, repeated
  header/footer and verification link on each page.

## Executed evidence

- Renderer test failed before implementation, then passed: fields, link URI
  annotation and multi-page output for a long field.
- CPD unit suite: 6 tests, 62 assertions passed.
- Full unit suite: 196 tests, 1642 assertions passed.
- Service and controller PHP syntax checks passed.
- Chrome DevTools MCP: existing learner downloaded the certificate, HTTP 200;
  payload contains saved course title, pass status and link annotation.
- Chrome partner session remained forbidden from downloading it: HTTP 403.
- Local QA render with PyMuPDF 1.28.2: one page, no PDF repair required,
  extracted link URI matches the configured local public verification route.
  Rendered page inspected: header, fields, verification code and footer are
  readable with no clipping or overlap in this fixture.
- Renderer tooling follows https://pymupdf.readthedocs.io/en/latest/recipes-images.html
  and is installed only under ignored local temporary storage, not PHP runtime.

## Remaining gates

The connected integration suite has new PDF assertions but has not been rerun
for this change. Non-ASCII glyph support, body-specific units, long-field visual
inspection, and the broader Pack 22 requirements remain. QA PDF/PNG files are
temporary inspection artifacts, not module-completion screenshots or a released
certificate. No remote deployment was performed.
