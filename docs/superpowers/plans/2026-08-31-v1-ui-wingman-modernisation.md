# V1 UI and guided Wingman modernisation implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `executing-plans` to implement this plan task by task. This execution is explicitly inline; do not delegate to sub-agents.

**Goal:** Turn the complete authenticated V1 application into a coherent Datum Flightpath experience and add a persistent, permission-aware bird Wingman powered by NVIDIA Nemotron 3.5 Lightning.

**Architecture:** Preserve `app/public/app.php` as the canonical server-rendered shell and layer two last-loaded, focused browser modules over it: one owns route placement/page-family enhancement and one owns the persistent Wingman. Add a bounded PHP Wingman vertical slice—configuration, NVIDIA transport, domain service, MariaDB persistence, authenticated routes—through the existing `AuthenticationKernelFactory`. Never expose the NVIDIA key to the browser or persist it.

**Tech Stack:** PHP 8.3, MariaDB, vanilla JavaScript/CSS, PHPUnit 12, NVIDIA NIM chat-completions API, ext-curl, shared-hosting Apache/LiteSpeed, staged FTP ZIP.

## Global constraints

- [ ] Treat every reachable menu/tool route as unmodernised until it is present in the route-placement manifest and visibly exercises the shared page grammar.
- [ ] Use `v1.html` only as a visual reference; do not commit it or copy secrets from the workspace.
- [ ] Preserve authentication, organisation scope, CSRF, permission middleware and existing governed dialogs.
- [ ] Wingman proposes navigation and actions; it never silently navigates, mutates records, dispatches messages or bypasses human approval.
- [ ] Send only structured, permission-filtered context to NVIDIA. Never send raw DOM, credentials, cookies, CSRF tokens or unrestricted record bodies.
- [ ] Keep `NVIDIA_API_KEY` server-side and environment-only. Do not log, persist, bundle or commit it.
- [ ] Test each production change red-first, then green, then refactor; commit small coherent slices.
- [ ] Keep local proof, staged-artifact proof and remote shared-host certification explicitly separate.

---

## Task 1: Inventory every route and establish the Datum Flightpath contract

**Files:**

- Create: `app/public/assets/route-placement.json`
- Create: `app/tests/Frontend/FlightpathRoutePlacementTest.php`
- Modify: `app/public/assets/tool-registry.json` only if a reachable tool lacks stable metadata

**Steps:**

- [ ] Write a failing frontend contract test that discovers the existing global destinations and tool registry entries, then requires each stable route/tool key to appear exactly once in a placement manifest.
- [ ] Define the eight project stages—Brief, Appoint, Design, Comply, Procure, Build, Pay and Close-out—and the six global destinations—Command Centre, Projects, Workspace Tools, Inbox & Collaboration, Knowledge & CPD and Admin & Settings.
- [ ] Assign every route to one stage/global destination and one page family: `orient`, `find`, `create`, `review` or `administer`.
- [ ] Store human-readable labels, ordering, permissions and project-context requirements without duplicating runtime authorization logic.
- [ ] Run `php vendor/bin/phpunit app/tests/Frontend/FlightpathRoutePlacementTest.php` and commit the placement contract.

## Task 2: Build the shared V1 visual and form system

**Files:**

- Create: `app/public/assets/css/flightpath-ui.css`
- Create: `app/tests/Frontend/FlightpathVisualSystemTest.php`
- Modify: `app/public/app.php`

**Steps:**

- [ ] Write failing contracts for the approved ink, teal, aqua, tracing-wash, review-amber and paper tokens; typography; focus states; field/help/error anatomy; responsive widths and reduced motion.
- [ ] Implement scoped tokens and reusable primitives for page headers, workflow steps, action bars, cards, tables, filters, forms, empty/error/loading states and governed dialogs.
- [ ] Modernise text, number, date, search, select, textarea, checkbox, radio and file inputs with visible labels and accessible focus/error states.
- [ ] Load `flightpath-ui.css` after the existing styles so it can progressively enhance all workspaces without deleting feature-specific CSS.
- [ ] Prove no global rule hides controls, breaks dialogs or causes horizontal overflow at 390 x 844.
- [ ] Run focused frontend tests and commit the visual system.

## Task 3: Replace random navigation with lifecycle-first placement

**Files:**

- Create: `app/public/assets/js/flightpath-shell.js`
- Create: `app/tests/Frontend/FlightpathShellTest.php`
- Modify: `app/public/app.php`

**Steps:**

- [ ] Write failing contracts for a final `renderAll` enhancement, Datum Flightpath stage navigation, six-item global rail, contextual project navigation and page-family classes.
- [ ] Load and validate `route-placement.json`; fail visibly to the existing navigation if the manifest is unavailable or invalid.
- [ ] Enhance, rather than fork, the final shell renderer so current tool bindings and server data remain authoritative.
- [ ] Keep the active route, stage, project and permission state visible; collapse secondary destinations into ordered contextual sections.
- [ ] Apply one page-family class and consistent landmark structure to every rendered workspace.
- [ ] Add keyboard operation, focus restoration and responsive menu behaviour.
- [ ] Run focused frontend tests, JavaScript syntax checks and commit the navigation layer.

## Task 4: Add MariaDB Wingman persistence and retention

**Files:**

- Create: `app/database/migrations/0056_guided_wingman.sql`
- Create: `app/tests/Unit/Wingman/WingmanMigrationTest.php`
- Create: `app/tests/Integration/Wingman/WingmanSchemaTest.php`

**Steps:**

- [ ] Write failing schema contracts for conversations, messages, citations, suggestions, suggestion feedback, authorised knowledge chunks and audit records.
- [ ] Add organisation/user/project foreign keys, immutable provider/model provenance, status enums, full-text knowledge search, tenant indexes and 90-day default retention fields.
- [ ] Ensure assistant content and citations are persisted separately from secrets and transient provider request metadata.
- [ ] Rebuild all migrations on the guarded disposable MariaDB database and prove tenant/index/foreign-key behaviour.
- [ ] Commit the schema.

## Task 5: Implement the server-only NVIDIA Nemotron adapter

**Files:**

- Create: `app/src/Infrastructure/AI/WingmanConfig.php`
- Create: `app/src/Infrastructure/AI/NvidiaTransport.php`
- Create: `app/src/Infrastructure/AI/CurlNvidiaTransport.php`
- Create: `app/src/Infrastructure/AI/NvidiaNemotronClient.php`
- Create: `app/src/Infrastructure/AI/NvidiaNemotronException.php`
- Create: `app/tests/Unit/Infrastructure/AI/WingmanConfigTest.php`
- Create: `app/tests/Unit/Infrastructure/AI/NvidiaNemotronClientTest.php`
- Modify: `composer.json`
- Modify: `composer.lock`

**Steps:**

- [ ] Write failing tests for environment parsing, fixed provider/model/endpoint defaults, timeouts, bounded messages, structured tool calls, refusal-safe errors and redacted diagnostics.
- [ ] Require `ext-curl` and validate it during dependency/platform checks.
- [ ] Send authenticated requests only to `https://integrate.api.nvidia.com/v1/chat/completions` using `nvidia/nemotron-3.5-lightning-30b-a3b`.
- [ ] Inject the transport so tests use deterministic fakes and never consume the supplied key.
- [ ] Enforce connection/total timeouts, TLS verification, response-size limits, JSON validation and stable unavailable/rate-limit/timeout errors.
- [ ] Run unit tests and PHP syntax checks; commit the adapter.

## Task 6: Build permission-aware guided-copilot intelligence

**Files:**

- Create: `app/src/Platform/Wingman/WingmanService.php`
- Create: `app/src/Platform/Wingman/WingmanException.php`
- Create: `app/tests/Integration/Wingman/WingmanServiceTest.php`

**Steps:**

- [ ] Write failing connected tests for bootstrap context, conversation continuity, authorised knowledge search, project summary, record summary, navigation proposal, citations, feedback and tenant isolation.
- [ ] Query live MariaDB records through explicit allow-listed summaries and the current organisation/user/project scope.
- [ ] Implement four bounded tools: `search_authorised_knowledge`, `get_authorised_project_summary`, `get_authorised_record_summary` and `propose_navigation`.
- [ ] Permit at most one bounded tool follow-up per user message; reject unknown tools and unapproved routes.
- [ ] Persist user/assistant messages, model provenance, citations and suggestions transactionally; audit prompts at metadata level without storing secrets.
- [ ] Return honest `configuration_required`, `provider_unavailable`, `rate_limited`, `context_denied` and `no_authorised_evidence` states.
- [ ] Run connected tests and commit the domain service.

## Task 7: Expose the authenticated Wingman API

**Files:**

- Create: `app/src/Http/WingmanRoutes.php`
- Create: `app/src/Http/Controller/WingmanController.php`
- Create: `app/tests/Integration/Http/WingmanHttpTest.php`
- Modify: `app/src/Http/AuthenticationKernelFactory.php`

**Steps:**

- [ ] Write failing HTTP tests for bootstrap, send message, conversation read and suggestion feedback.
- [ ] Prove unauthenticated, missing organisation, CSRF, cross-tenant, permission, invalid route, oversized prompt and provider failure behaviour.
- [ ] Register the four approved endpoints through existing session, organisation, JSON, CSRF, rate-limit and permission middleware.
- [ ] Construct one Wingman dependency graph in `AuthenticationKernelFactory`; load `NVIDIA_API_KEY` from the server environment only.
- [ ] Map stable domain errors to 400, 403, 404, 409, 422, 429 and 503 without leaking provider bodies.
- [ ] Run HTTP and middleware regressions; commit the API.

## Task 8: Add the persistent bird Wingman guided-copilot UI

**Files:**

- Create: `app/public/assets/js/wingman.js`
- Create: `app/public/assets/css/wingman.css`
- Create: `app/public/assets/img/wingman-bird.svg`
- Create: `app/tests/Frontend/WingmanUiTest.php`
- Modify: `app/public/app.php`

**Steps:**

- [ ] Write failing contracts for a real bird mark, persistent mount, quiet/suggestion/expanded states, accessible controls, explicit navigation approval, citations and failure states.
- [ ] Mount the Wingman once outside page renderers; keep it above normal panels and below governed dialogs.
- [ ] Send structured route/project/selected-record context via the authenticated API client and render responses as text, citations and explicit suggestion cards.
- [ ] Require a user click before executing proposed navigation; never execute record mutations from model output.
- [ ] Preserve open/closed/conversation state across internal navigation while preventing cross-user or cross-organisation reuse.
- [ ] Make the compact bird usable at desktop and 390 x 844 with reduced-motion support.
- [ ] Run focused frontend tests and JavaScript syntax checks; commit the Wingman UI.

## Task 9: Pilot the full page grammar on the Job Ticket Marketplace

**Files:**

- Modify: `app/public/assets/js/job-ticket-marketplace.js`
- Modify: `app/public/assets/css/job-ticket-marketplace.css`
- Modify: `app/tests/Frontend/JobTicketMarketplaceWorkspaceTest.php`

**Steps:**

- [ ] Add failing assertions for orient/find/create/review/administer regions, workflow ordering, action hierarchy and responsive form/table placement.
- [ ] Recompose the marketplace into a clear discovery-to-execution workflow without changing its authenticated API contracts.
- [ ] Replace ad-hoc inline field layout with the shared input primitives and page headers.
- [ ] Verify client posting, quotation, appointment, supervision, timesheet, claim, deliverable and review paths remain reachable.
- [ ] Run existing Release 25 tests and commit the pilot.

## Task 10: Apply and audit the page system across every reachable route

**Files:**

- Modify: `app/public/assets/route-placement.json`
- Modify: `app/public/assets/js/flightpath-shell.js`
- Modify: `app/public/assets/css/flightpath-ui.css`
- Modify: `app/tests/Frontend/FlightpathRoutePlacementTest.php`
- Create: `app/tests/Frontend/FlightpathCoverageTest.php`

**Steps:**

- [ ] Add failing coverage assertions that compare actual global/tool destinations with the manifest and reject unmapped, duplicate or hidden-orphan routes.
- [ ] Audit each route family in the live browser and record layout defects before fixing shared primitives or narrowly scoped feature styles.
- [ ] Ensure every route has a visible title, lifecycle/global placement, primary action, contextual secondary navigation and meaningful empty/loading/error states.
- [ ] Verify menus retain their order and active state across project switching, refresh and responsive collapse.
- [ ] Verify Wingman persists across every route family and receives only the approved structured context.
- [ ] Run all frontend contracts and commit route-family completion only after zero unmapped routes remain.

## Task 11: Full local certification and staged shared-hosting release

**Files:**

- Create: `docs/evidence/2026-08-31-v1-ui-wingman-modernisation.md`
- Modify: staged-release scripts/manifests only where required by the existing release process

**Steps:**

- [ ] Run PHP syntax, Composer platform, JavaScript syntax, focused tests and the complete PHPUnit suite.
- [ ] Rebuild all 56 migrations on the guarded disposable MariaDB target and verify authenticated conversation persistence separately from static health.
- [ ] If the key can be injected without appearing in command output, process listings or files, run one bounded live Nemotron request and record only status/model/latency; otherwise leave provider certification pending and do not weaken secret handling.
- [ ] Use the persistent Chrome DevTools browser to exercise desktop and 390 x 844 navigation, inputs, the marketplace workflow and Wingman states; inspect console, network, keyboard focus, overflow and accessibility.
- [ ] Capture route-coverage, screenshot and database evidence with local-versus-remote boundaries.
- [ ] Build the staged shared-hosting ZIP from the exact certified commit, fresh-extract it, verify hashes/file counts/PHP 8.3/ext-curl requirements and include migration `0056`.
- [ ] Do not upload by FTP or mutate the remote MariaDB database without a separately approved backup, rehearsal and remote release gate.
- [ ] Rotate the exposed development NVIDIA key before any real staging or production use.
- [ ] Review every checkbox, commit certification documentation and report any remaining remote-only risks explicitly.

## Release boundary

This modernisation is locally complete only when every reachable route is manifest-covered, the guarded MariaDB lifecycle and authenticated browser workflow pass, Wingman failure states are honest, and the staged artifact is reproduced from the certified commit. FTP transfer, remote migration, remote provider configuration, backup/rehearsal, remote smoke tests and staged promotion remain uncertified until separately executed and approved.
