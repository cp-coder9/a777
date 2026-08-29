# Packs 19 and 20 production design

## Decision

Implement the packs as two dependency-ordered releases on the existing PHP 8.3/MariaDB platform. Pack 19 adds a platform-wide, opt-in community layer beside project-authority messaging. Pack 20 adds governed drawing-compliance intelligence beside the current drawing checker and municipal-readiness workflows. Neither pack creates a second database, JavaScript-only persistence island, external GPT dependency, or automatic formal/professional action.

## Pack 19 architecture

`CommunityMessagingService` owns profile preferences, server-side audience resolution, rate limits, moderation, thread/reply visibility, reports, notifications and explicit draft conversion. MariaDB snapshots every resolved recipient so later profile changes cannot rewrite historical visibility. Selected-role posts to at most three cohorts may publish immediately; whole-community, admin-notice-by-non-admin and wider cohort posts remain pending until an owner/admin moderator approves them. The same authenticated APIs drive an embedded Community Hub and `/community-app` mobile shell. Community-to-workflow conversion creates an audited draft requiring final review and never sends, awards, instructs, appoints or pays automatically.

## Pack 20 architecture

`DrawingComplianceService` owns source-versioned checklist definitions, manual checklist snapshots, deterministic boundary-wall evaluation, drawing-set/package review, internal-reference review, SPLUMA/land-use screening and professional-review gates. The supplied POC values remain `illustrative_poc_until_verified`; no output may claim licensed authority, municipal approval or professional certification. Project-aware snapshots can create tenant-scoped ProjectRecords and inbox/readiness blockers only after a human saves the exact review. Standalone and project-aware UI use the same service and MariaDB records.

## Shared security and data flow

- Authentication and organisation context come from the existing kernel; every write is CSRF-protected by the current middleware.
- Cross-organisation community visibility is explicit and opt-in. The server computes recipients from active users, active organisations, active memberships and community preferences; clients never submit recipient IDs.
- Community replies require author, snapshotted recipient or moderator visibility. Reports and moderation actions are append-only audited events.
- Drawing-compliance records remain organisation/project scoped. Inputs are validated, JSON is bounded, and all evidence snapshots receive SHA-256 hashes.
- Inbox items may be platform-level for community notifications by allowing a nullable project ID; existing project notifications remain unchanged.

## Verification

Each release follows red-green-refactor with unit, connected MariaDB, HTTP authorization and frontend contract tests. Persistent Chrome DevTools proves both embedded and standalone/mobile surfaces, role leakage boundaries, moderation/conversion flows, MariaDB persistence, responsive geometry, console cleanliness and Lighthouse. Canonical `v1.html` and supplied `packs/` remain untouched and untracked.
