# Release 15 Community Messaging Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use test-driven-development and execute inline; this workspace does not use subagents for this release.

**Goal:** Deliver Pack 19 as a real cross-organisation, opt-in, role-targeted community service with embedded and phone-first surfaces.

**Architecture:** Add a focused community schema/service/controller/routes stack beside project communication. Snapshot server-resolved recipients, keep community content informal, and make formal conversion an explicitly approved draft.

**Tech Stack:** PHP 8.3, MariaDB 11.4, vanilla JavaScript/CSS, PHPUnit 12, Chrome DevTools MCP.

## Global Constraints

- Shared hosting and FTP deployment; no Node runtime or SSH dependency in production.
- One MariaDB backend shared by embedded and `/community-app` surfaces.
- No WhatsApp or external messaging dependency.
- No recipient IDs accepted from the client for cohort broadcasts.
- Whole-community posts require moderation unless created by an owner/admin moderator.
- Canonical `v1.html` and `packs/` remain untouched and untracked.

### Task 1: Community schema and audience isolation

**Files:** Create `app/database/migrations/0037_community_messaging.sql`, `app/src/Platform/Community/CommunityMessagingService.php`, `app/src/Platform/Community/CommunityException.php`; test `app/tests/Integration/Community/CommunityMessagingServiceTest.php`.

- [ ] Write a connected failing test that seeds architect, quantity-surveyor and supplier users in different organisations; only opted-in targeted roles may see the thread.
- [ ] Run the test and confirm failure because the schema/service is absent.
- [ ] Add channel taxonomy, preferences, threads, recipient snapshots, replies, reports, moderation events, notifications and conversion tables plus nullable project inbox support.
- [ ] Implement strict server-side audience resolution, moderation state and thread visibility; rerun to green.

### Task 2: Replies, reports, moderation, notifications and conversion

**Files:** Modify `app/src/Platform/Community/CommunityMessagingService.php`; test `app/tests/Integration/Community/CommunityGovernanceTest.php`.

- [ ] Write failing tests for recipient-only replies, non-recipient denial, report audit, moderator approval, digest notifications, rate limiting and `humanConfirmed` conversion drafts.
- [ ] Run and verify the expected missing-method/behavior failures.
- [ ] Implement the minimal governed operations and audit writes; rerun both connected suites to green.

### Task 3: Authenticated API

**Files:** Create `app/src/Http/Controller/CommunityController.php`, `app/src/Http/CommunityRoutes.php`; modify `app/src/Http/AuthenticationKernelFactory.php`; test `app/tests/Http/CommunityRoutesTest.php` and `app/tests/Integration/Http/CommunityHttpTest.php`.

- [ ] Write failing route/security tests for preferences, channels, feed, create, reply, report, approve and convert endpoints.
- [ ] Register permission-wrapped routes and stable error responses.
- [ ] Run unit HTTP and connected authenticated scenarios to green.

### Task 4: Embedded and standalone mobile UI

**Files:** Modify `app/public/index.php`, `app/public/assets/js/shell.js`, `app/public/assets/css/architex-v1.css`; create `app/tests/Frontend/CommunityMessagingWorkspaceTest.php`.

- [ ] Write a failing frontend contract for Community Hub, audience controls, guardrail copy, moderation queue and `/community-app` shared-service boot.
- [ ] Add Project/Community message modes and the phone-first shell without duplicating backend logic.
- [ ] Bind profile setup, targeted post, feed, reply, report, moderator approval and conversion forms to the authenticated API; run frontend tests and JavaScript syntax checks to green.

### Task 5: Release certification

**Files:** Create `docs/evidence/2026-08-29-release15-community-messaging.md` and desktop/mobile screenshots.

- [ ] Run focused and connected suites against only `architex_task13_facefeed_test`.
- [ ] Apply migration 0037 to the local live development schema and verify exact rows/audit events with read-only MariaDB queries.
- [ ] Use persistent Chrome page 12 to prove targeted-role isolation, reply, moderation, conversion, embedded/standalone parity, mobile geometry, Lighthouse and clean console.
- [ ] Recheck canonical SHA-256, stage only release files, and commit.
