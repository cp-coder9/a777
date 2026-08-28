# Release 2 project communications — local evidence

Date: 2026-08-28
Scope: local PHP 8.3 / MariaDB implementation evidence only. This is not staging or production-host certification.

## Implemented boundary

- One MariaDB communication engine backs the desktop Project Message Centre and mobile Project Chat Applet.
- Project-scoped threads, participants, drafts, sent messages, read cursors, contextual record metadata and controlled conversions persist in migration `0016_project_communications.sql`.
- Drafts cannot send without explicit human confirmation. Message-to-action, RFI, site-instruction and approval conversions also require explicit human confirmation.
- Phase-aware capture types, project pulse, file focus and the explicitly labelled `Suggested prompts` surface are returned by the PHP API.
- Attachments use the existing private `stored_files` project boundary; no public object URL is introduced.
- Active project-team users are auto-added only when they already have active access to the lead organisation. Cross-organisation project professionals are not silently granted tenant access; the UI labels the self-only fallback `New private thread`.
- Voice notes are represented as governed uploaded records. No embedded recorder or transcription-provider claim is made.

## Automated evidence

- `node --check app/public/assets/js/shell.js` — passed.
- `php vendor/bin/phpunit app/tests/Frontend/CommunicationWorkspaceTest.php` — 1 test, 19 assertions, passed.
- `ProjectCommunicationServiceTest` against disposable MariaDB — 1 test, 21 assertions, passed, including cross-thread reply rejection and same-project linked-file enforcement.
- `CommunicationHttpTest` against disposable MariaDB — 1 test, 7 assertions, passed.
- `ProjectTeamServiceTest::testInvitesIndependentlyVerifiedProfessionalAndTheyAccept` — 1 test, 11 assertions, passed, including the cross-organisation communication-eligibility boundary.
- Unit suite — 113 tests, 317 assertions, passed.
- PHP syntax checks for service, controller, routes and integration tests — passed.
- `git diff --check` — passed; only existing Windows line-ending notices were emitted.

## Chrome DevTools MCP evidence

Persistent authenticated page: `http://127.0.0.1:8790/` as `browser-owner@example.test` in organisation `9775135843504f4eb3f9656493770756`, project `af189b7f799637ca7918b1a3d6858292`.

1. Opened Inbox & Collaboration and confirmed the real Project Message Centre, project/phase switchers, project pulse, structured conversion, `Suggested prompts`, file focus and shared composer.
2. Created thread `2d9510ee3f7aa80d810380ed084e573d`.
3. Explicitly approved and sent message `7b36e43d029d55a6b37021cf9e1fec34`.
4. Selected the sent message and explicitly approved conversion to action `75663ef691bf3b373ddcc56099ba5bbd`.
5. Confirmed the message changed to `converted`, the action appeared as open, and project pulse changed to one open action.
6. Reloaded the page and confirmed the thread, messages, action and pulse persisted.
7. Sent and selected `Client's revised scope needs review.` to prove apostrophes remain data and do not break or inject the click handler; the exact text populated the conversion title.
8. At 390 by 844, the mobile tabs rendered as a grid, chat panes were visible, AI tab selection isolated the AI pane, and document horizontal overflow was zero.
9. Restored 1440 by 900, reloaded, reopened the workspace and confirmed zero console errors or warnings.

## MariaDB readback

- 16 migrations applied, zero pending immediately after applying migration 0016.
- Thread: appointment / project group / project-team visibility.
- First message: sent / text / fee item / appointment / converted.
- Action: open, type `action`, linked to the source message.
- Audit events present: `communication.thread_created`, `communication.contextual_draft_created`, `communication.message_sent`, and `communication.message_converted`.

## Remaining release boundary

Cross-organisation project collaborators still require a dedicated project-scoped access context before they can read lead-organisation threads. This slice deliberately does not solve that by granting organisation-wide membership. Staging, FTP-host, SMTP and remote MariaDB certification remain separate future gates.
