# V1 UI and Guided Wingman Modernisation — Local Certification

Date: 2026-08-31

Certified source commit: `2d8e496392b5a53686f321ec2f79896dda8e3f44`

Candidate: `dist/architex-release-staged-20260831-2d8e496`

FTP archive: `dist/architex-release-staged-20260831-2d8e496.zip`

## Implemented scope

- Datum Flightpath placement covers 11 global routes and 49 registry/runtime tools exactly once across eight lifecycle stages, six global destinations and five page families.
- The shared V1 visual system supplies the approved ink, teal, aqua, tracing-wash, review-amber and paper tokens; consistent page, form, table, state and dialog primitives; responsive behavior; and reduced motion.
- The final shell renderer now owns lifecycle-first navigation, grouped global destinations, page-family treatment, focus restoration, route titles, primary guidance, contextual navigation labels and shared empty/loading/error state treatment.
- The guided Wingman uses a persistent original bird mark and quiet, suggestion and expanded states. It sends only structured route, project and selected-record identifiers through the authenticated API client. Model output is rendered as text and navigation executes only after a user click.
- The backend fixes the provider to NVIDIA's chat-completions endpoint and `nvidia/nemotron-3.5-lightning-30b-a3b`, requires `ext-curl`, keeps the key server-side, applies bounded timeouts/response limits and exposes stable failure states.
- MariaDB migration `0056_guided_wingman.sql` provides private conversations, messages, citations, explicit suggestions/feedback, authorised knowledge chunks and audit records with 90-day default retention.
- The Job Ticket Marketplace now follows an Orient, Find, Create, Review/Execute and Administer workflow while retaining its existing governed API actions.

## Executed local evidence

- Composer validation: passed.
- Composer platform requirements: passed on PHP 8.3.32, including `ext-curl`, `ext-pdo_mysql`, `ext-openssl` and `ext-zip`.
- Composer security audit: no vulnerability advisories.
- PHP syntax: every PHP file under `app` passed.
- JavaScript syntax: every public JavaScript module passed.
- Frontend contracts: 58 tests, 1,265 assertions, zero skips/failures/errors.
- Complete configured PHPUnit run: 357 tests, 1,623 assertions, 159 integration tests skipped because the disposable-DB opt-in was intentionally absent from that process.
- Release contracts: 4 tests, 19 assertions, one backup/restore test skipped because its separate environment gate was absent.
- Static runtime checks: `/` and the Flightpath CSS, Wingman CSS/JS/bird asset and route manifest returned HTTP 200 from the persistent PHP server on port 8809.

## Connected MariaDB evidence

The guarded database suite used only the loopback MariaDB 11.4 instance on port 43320 and the task-owned database `architex_task0_a8312026_test`.

- All 56 migrations rebuilt successfully.
- Wingman schema, full-text index and default retention passed.
- Private conversation persistence, citations, bounded tool follow-up, authorised project/record summaries, explicit navigation suggestions, feedback, tenant isolation and HTTP behavior passed: 7 tests, 47 assertions.
- The disposable database and temporary loopback test user were removed after the run; the database existence check returned zero.

This is live database and persistence evidence, separate from the static HTTP asset checks above.

## Staged shared-hosting artifact

- Immutable builder verification: 389 manifest files.
- Fresh ZIP extraction verification: passed independently against the same manifest.
- Extracted physical files: 390 including `release-manifest.json`.
- Archive bytes: 1,275,524.
- Archive SHA-256: `3D30524F9BEC15E5878D2F71BA6880296572782A8DAADAEF5283E04BC9CD1D32`.
- Metadata records PHP 8.3, `php-shared-hosting` and source commit `2d8e496392b5a53686f321ec2f79896dda8e3f44`.
- Production Composer metadata requires `ext-curl`.
- Migration `0056_guided_wingman.sql` is present.
- No credential-like NVIDIA API token is present in the candidate.

## Pending gates and release boundary

- Chrome DevTools MCP returned `Transport closed` on repeated `list_pages` attempts. No current desktop/mobile screenshot, console, network, focus, overflow, Lighthouse or accessibility pass is claimed.
- `NVIDIA_API_KEY` was not present in the process environment. The key supplied in chat was not copied into a command, file, process argument or artifact, so a live Nemotron request remains pending.
- The exposed development NVIDIA key must be rotated before staging or production configuration.
- FTP upload, remote MariaDB backup, restore rehearsal, remote migration, server-side key configuration, cron/SMTP checks, remote authenticated smoke tests and staged promotion were not performed.
- Remote upload or database mutation requires the separately approved backup/rehearsal and remote release gate described in the implementation plan.

The implementation and reproducible local FTP candidate are complete. Remote shared-hosting certification remains intentionally separate and pending.
