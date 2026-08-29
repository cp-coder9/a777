# Release 15 local certification — Community Messaging

Date: 2026-08-29
Scope: Pack 19 shared Community Messaging backend, embedded Messages surface and standalone cellphone-first PWA
Runtime: PHP 8.3.32, MariaDB 11.4 at `127.0.0.1:43319`, persistent Chrome DevTools page 12 at `http://127.0.0.1:8790/`

## Implemented scope

- Added 18 governed community channels, public opt-in profiles, professional-role/region/verification audience targeting and server-resolved cross-organisation recipient snapshots.
- Role-restricted channel discovery and recipient resolution fail closed. Blocked senders, muted target cohorts, inactive identities, opted-out users, wrong roles, wrong regions and wrong verification states are excluded on the server.
- Added thread/reply persistence, tenant-scoped FileManager attachment references, direct mentions, search, saved threads, channel watch/mute state, rate limits and candidate-professional labels.
- Whole-community, admin-notice and broad multi-role posts remain undistributed until a recorded moderator decision. Reports, approve/reject/hide/restore decisions and removal trails are audited.
- Platform-wide moderation and channel-taxonomy powers require an explicit active `community_platform_moderators` allowlist record; organisation `owner`/`admin` membership alone never grants these powers.
- Added notification outbox priorities for direct mentions, replies, watched channels and daily/weekly digests, a Community Digest surface, and `app/bin/deliver-community-notifications.php` for shared-hosting control-panel cron.
- Informal community posts remain separate from project authority. Conversion creates only an audited workflow draft after explicit human confirmation and never creates an instruction, appointment, statutory approval, procurement award or payment approval.
- Added one shared MariaDB/API implementation behind `/messages/community` and the standalone `/community-app` PWA, including channel/thread deep links, manifest, install prompt, service worker and responsive app shell.
- Updated the V1 extractor so nested routes use root-absolute assets, preserves Releases 2–13 production extensions during regeneration, loads Pack 19 from its dedicated stylesheet and does not downgrade already-promoted `live` registry entries.

## Automated evidence

- Frontend/unit/extraction contracts: `OK (6 tests, 53 assertions)` in 00:00.035.
- Final combined connected suite: `OK (3 tests, 51 assertions)` in 10:02.620 against only `architex_task13_facefeed_test` (14 production-control/taxonomy assertions, 18 cross-organisation/moderation/conversion assertions and 19 authenticated HTTP assertions).
- The connected scenarios proved restricted channel discovery, search, save/watch state, moderation rejection, audit events, role/region/verification exclusion, pending whole-community distribution, moderator approval, replies, reports and human-confirmed draft conversion.
- `node --check` passed for `shell.js` and `community-sw.js`; changed PHP entrypoints and services passed `php -l`.
- `git diff --check` reported no whitespace errors, only the repository's expected LF-to-CRLF notices.

## MariaDB and HTTP evidence

- Live local migrations 0039 through 0041 applied successfully: migration status is current with 41 applied and zero pending.
- Chrome persisted the saved-thread state and watched Architects channel through full reloads.
- All shell, manifest, stylesheet, JavaScript, session, projects, inbox, tools, channels, feed, notifications and preference requests returned HTTP 200.
- Chrome's public owner capability response was `canModerate=false`, `canManageChannels=false`; the moderation control remained hidden.
- Direct community thread deep link `/community-app/thread/c498b8203420ada39fedd9a851ee6304` opened the persisted thread and reply without client-side synthetic state.

## Chrome DevTools evidence

- Persistent authenticated page ID 12 remains open for `browser-owner@example.test`.
- Chrome created and reloaded thread `c498b8203420ada39fedd9a851ee6304`, title `Chrome community coordination`, then persisted its reply, saved state and watched-channel preference.
- The standalone manifest resolves at `/community.webmanifest`; the active service worker is `/community-sw.js` with root scope and controls the reloaded page.
- Mobile geometry at 390 x 844 has `scrollWidth=390`, with zero horizontal overflow.
- Mobile Lighthouse: Accessibility 100, Best Practices 100, SEO 100, Agentic Browsing 100; 26/26 applicable audits passed.
- Desktop Lighthouse: Accessibility 100, Best Practices 100, SEO 100, Agentic Browsing 100; 32/32 applicable audits passed.
- Final browser console inspection found zero JavaScript errors or warnings. Chrome retained one label-association issue emitted by the legacy shell's transient pre-mount render; the final mounted Community DOM contains no unlabelled or unnamed `input`, `select` or `textarea`, and Lighthouse accessibility remains 100.
- Visually inspected desktop and mobile screenshots directly through the persistent Chrome DevTools session; no screenshot file is claimed as a release artifact.

## Preserved source and release boundaries

- Canonical `v1.html` was not edited; SHA-256 remains `7DBFAFDCFA52644FD32FB7CB7D73891B78593CA0287C089240DB4439E0FA97BF`.
- Pack inputs remain untracked and excluded from the application commit.
- No WhatsApp dependency, public unauthenticated forum, automatic professional advice, external push provider or external identity-verification claim is introduced.
- This certifies Pack 19 locally. Pack 20, aggregate gates, immutable FTP candidate rebuilding, SMTP, remote FTP staging, target backup rehearsal, control-panel cron and production promotion remain separate gates.
