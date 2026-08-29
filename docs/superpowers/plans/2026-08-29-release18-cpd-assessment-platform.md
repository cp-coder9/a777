# Release 18 — Pack 22 CPD Assessment Platform Implementation Plan

**Goal:** Deliver Pack 22 as a production PHP 8.3 and MariaDB CPD hub for shared hosting while preserving the V1 shell and treating accreditation, payment, professional-body compliance, scoring, and certificates as governed server-side facts.

**Source pack:** `packs/22. architex-cpd-assessment-platform-pack` (all supplied briefs, research, manifest, and reference TypeScript read in full on 2026-08-29).

**Release boundary:** Local implementation and certification first. Remote shared-hosting/FTP certification remains a separate staged-release gate and must not be inferred from local success.

## Task 1: Lock the domain and schema contracts

- Add failing migration/contract tests for professional profiles, body rule snapshots, versioned content and assessments, accreditation applications, entitlements, attempts, immutable records, certificates, provider events, payouts, analytics, and audit history.
- Add migration `0045_cpd_assessment_platform.sql` with tenant-safe indexes and foreign keys.
- Seed the eight researched professional-body rule profiles without presenting estimates as approved credits.

## Task 2: Implement the governed CPD domain service

- Add `CPDAssessmentService` and typed domain exceptions.
- Implement learner onboarding, content intake and permission status, AI-draft labelling, human review, assessment/course versioning, accreditation workflow, publishing gates, server-side scoring, manual-review gates, retry limits, immutable CPD records, certificate verification, analytics, and referral funnel capture.
- Enforce organisation isolation and role boundaries for learners, creators, reviewers, partners, and administrators using the existing membership roles plus explicit CPD capabilities.

## Task 3: Implement honest commercial workflows

- Store admin-controlled price, fee, and payout policies in integer cents.
- Require a paid/free/manual entitlement before an attempt can start.
- Support idempotent provider events only when a configured secret validates the event; return `integration_required` for unconfigured providers.
- Keep manual EFT/admin entitlement review explicit and audited. Never simulate provider settlement or money movement.

## Task 4: Expose authenticated PHP APIs

- Add CPD controller and routes for workspace, profiles, content, review, accreditation, courses, entitlements, attempts, manual review, certificates/verification, analytics, and settings.
- Apply session, organisation, CSRF, permission, rate-limit, and safe-error middleware consistently.
- Add HTTP tests for authentication, authorization, malformed input, conflict states, idempotency, and cross-tenant isolation.

## Task 5: Add the V1-native top-level CPD hub

- Add a top-level `CPD` destination rather than placing the platform inside the calculator/toolbox catalogue.
- Build learner catalogue/record, creator studio, reviewer/accreditation queue, commercial settings, analytics, and verification views in the existing shell ownership model.
- Preserve V1 typography, geometry, colour, responsive behaviour, accessible labels, keyboard focus, empty/loading/error states, and truthful integration copy.

## Task 6: Prove the complete local system

- Run schema policy checks, PHP syntax, focused unit/integration/HTTP/frontend tests, then the complete connected MariaDB suite.
- Use Chrome DevTools MCP against the exact local build for learner, creator, reviewer, partner, and administrator flows, including failure modes, persistence after reload, tenant isolation, certificate verification, mobile layout, console/network inspection, accessibility, and screenshots.

## Task 7: Commit and stage Release 18

- Commit implementation in reviewable slices without adding `packs/` or `v1.html`.
- Build an immutable release candidate from the exact commit, verify its manifest and ZIP checksum, and serve that candidate for a final browser check.
- Record local evidence separately from the later FTP upload and remote MariaDB/shared-host certification.
