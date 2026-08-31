# Architex V1 UI and Wingman Modernisation Design

**Status:** Approved design direction; written specification awaiting final user review
**Date:** 2026-08-31
**Runtime:** PHP 8.3, MariaDB, shared hosting, FTP staged releases
**Reference:** `v1.html` is the visual and structural source of truth
**AI provider:** NVIDIA NIM API Catalog
**Model:** `nvidia/nemotron-3.5-lightning-30b-a3b`

## 1. Subject, audience, and single job

Architex is a built-environment operating system for South African clients, architects, engineers, quantity surveyors, planners, contractors, suppliers, administrators, and supervised freelancers.

The modernised interface has one job: show an authorised user where they are in the project lifecycle, what needs attention, and the next governed action they can take.

The redesign must retain all implemented production functionality while replacing accumulated page-by-page layouts with one coherent workflow system. It is not a cosmetic reskin.

## 2. Approved direction

The approved visual and interaction direction is **Datum Flightpath**:

- a quiet global rail for operating-system-level destinations;
- a contextual project navigator organised by lifecycle stage;
- a visible eight-stage flightpath: Brief, Appoint, Design, Comply, Procure, Build, Pay, Close-out;
- one dominant current-work surface per page;
- evidence and authority shown beside the decision they support;
- a persistent bird Wingman that observes authorised context, suggests next steps, and navigates only after user confirmation.

This direction was selected over a dashboard-heavy Project Cockpit and a wizard-heavy Guided Workbench. It best preserves the supplied V1 identity while remaining efficient for expert users and understandable for new users.

## 3. Design identity

### 3.1 Token system

| Token | Value | Purpose |
|---|---:|---|
| Ink | `#102033` | Primary text, decisive actions, controlled status |
| Datum Teal | `#0F817B` | Global rail, active workflow position, Wingman |
| Marker Aqua | `#19B7B0` | Focus, selection, guidance, progress |
| Tracing Wash | `#EAF8F6` | Context panels, guidance, quiet selected states |
| Review Amber | `#FFB020` | Review-required and caution states |
| Paper | `#FFFFFF` | Work surfaces and records |

Existing V1 tone colours may remain for discipline-specific status, but the six tokens above own shell structure and interaction state. Functional pages must consume shared custom properties rather than hard-code substitute palettes.

### 3.2 Typography

- Display and page titles: `Bahnschrift SemiCondensed`, with `Arial Narrow` and `sans-serif` fallbacks.
- Body and controls: `Aptos`, with `Segoe UI` and `sans-serif` fallbacks.
- Identifiers, evidence hashes, stages, and technical metadata: `Consolas`, with `monospace` fallback.

The condensed display face reflects drawing titles and document registers. It is used sparingly. Body text remains calm and highly legible.

### 3.3 Signature element

The supplied Architex bird logo becomes the persistent Wingman. The implementation must use the real bird asset already embedded in the shell, not a generic sparkle, robot, chat bubble, or replacement AI-generated mark.

Motion is reserved for one Wingman transition: a short perch/open movement when a new contextual suggestion becomes available. `prefers-reduced-motion` removes it. No ambient animation is permitted.

## 4. Information architecture

### 4.1 Global rail

The global rail contains no project-stage duplicates. Its destinations are:

1. Command Centre
2. Projects
3. Workspace Tools
4. Inbox and Collaboration
5. Knowledge and CPD
6. Administration and Settings

Finance, documents, compliance, procurement, meetings, and delivery tools appear in the contextual project navigator at the lifecycle stages where they are used. Global search may find them, but they do not compete as permanent top-level destinations.

### 4.2 Contextual project navigator

The contextual navigator owns:

- the active organisation and project;
- the active lifecycle stage;
- stage-relevant workspaces grouped by user-recognisable outcomes;
- evidence and control destinations: Reviews, Project Records, and Activity;
- role-filtered visibility and capability badges;
- counts only when they represent real persisted records.

Changing roles may filter visibility, but it may not reorder the lifecycle or create a different product architecture for each role.

### 4.3 Lifecycle placement

| Stage | Primary outcomes |
|---|---|
| Brief | Guided brief, project passport, early planning, initial fees |
| Appoint | Directory, proposals, appointments, contracts, team setup |
| Design | Drawings, specifications, coordination, calculations, job tickets |
| Comply | Planning, municipal readiness, XA, forms, statutory reviews |
| Procure | BoQ/BoM, RFQs, supplier catalogue, tender evaluation |
| Build | Site diary, safety, ITPs, instructions, NCRs, snags |
| Pay | Claims, valuations, certificates, retention, provider evidence |
| Close-out | Handover, completion, final records, CPD, lessons and feedback |

Every route must be recorded in a migration manifest with exactly one primary placement, any valid secondary entry points, its page family, and its role permissions. The modernisation is not complete while any reachable production route lacks this mapping.

## 5. Page system

Every page answers three questions in order:

1. **Orient:** where am I and what is this page for?
2. **Do:** what is the current task and primary action?
3. **Verify:** what evidence, authority, status, and next gate apply?

### 5.1 Five page families

1. **Orient** — command centre and project overviews: priorities, stage, blockers, next actions.
2. **Find** — registers and libraries: search, filters, saved views, one dominant result list.
3. **Create** — forms and wizards: purpose, details, evidence, review, with a live summary.
4. **Review** — source evidence beside decision controls, provenance, and an explicit human gate.
5. **Administer** — settings grouped by organisation, people, integrations, security, and records.

Specialist workspaces may add domain-specific content but must declare one of these families and follow its shell grammar.

### 5.2 Page anatomy

Every page contains, in this order:

- breadcrumb and organisation/project scope;
- page title and plain-language purpose;
- one primary action, with secondary actions visually subordinate;
- stage or task progress where a real sequence exists;
- one main work surface;
- a contextual summary or evidence rail when the current task needs it;
- next-gate wording that explains what unlocks the following step.

Tabs are used only for genuinely alternative views of the same object. Category labels, status chips, and section headings must not be implemented as inert tabs or buttons.

### 5.3 Form controls

- Labels remain visible above controls; placeholders never replace labels.
- Inputs, selects, textareas, date controls, and comboboxes use shared height, radius, border, focus, disabled, read-only, error, and success tokens.
- Helper text explains format or consequence. Error text states the exact correction.
- Required status is both visible and programmatically exposed.
- Related fields are grouped under task-language headings, not database or API terminology.
- Advanced and infrequent fields use progressive disclosure.
- Destructive, approval, issue, publish, payment, and external communication actions remain separated from ordinary save actions.
- Long forms become a real sequence only when the order encodes workflow. Arbitrary numbered decoration is prohibited.

## 6. Persistent guided Wingman

### 6.1 Interaction states

Wingman is one persistent component mounted outside page renderers so route changes cannot destroy its conversation or selected context.

1. **Quiet presence:** the bird remains fixed above ordinary shell panels with an accessible “Open Wingman” label.
2. **Contextual suggestion:** a compact perch explains what was noticed, why it appeared, and offers Dismiss or Show me.
3. **Expanded knowledge panel:** conversation, authorised context label, source citations, and navigation suggestions appear together.

The trigger remains available across desktop and mobile. It sits above normal shell panels but below security, confirmation, and legal-consent dialogs so it cannot obscure a governed decision.

### 6.2 Guided authority

Wingman may:

- read the route, page type, visible validation codes, role, organisation, project, stage, and authorised record summaries;
- explain the current page or a validation failure;
- search authorised Architex product knowledge and project evidence;
- suggest a next step with linked sources;
- open or highlight a page, tab, record, or field only after the user chooses the suggestion;
- draft plain text that is explicitly labelled as an AI draft.

Wingman may never:

- bypass permissions or cross tenant boundaries;
- approve, certify, publish, issue, pay, sign, or communicate externally;
- silently mutate a record;
- automatically navigate without a user choice;
- invent a project fact, statutory conclusion, provider result, or professional opinion;
- present an unavailable NVIDIA response as a successful answer.

### 6.3 Context contract

The browser sends a structured, allow-listed context object rather than raw page HTML:

```json
{
  "route": "/toolbox/standalone/freelancer-job-ticket-marketplace",
  "pageFamily": "create",
  "projectId": null,
  "stage": "Design",
  "recordType": "job_ticket",
  "recordId": "optional-32-character-id",
  "validationCodes": ["job_ticket_deadline_required"],
  "message": "What is blocking the next step?"
}
```

PHP re-authorises every identifier and rebuilds trusted context from services and MariaDB. Browser-supplied role, organisation, titles, status, amounts, and authority claims are never trusted.

## 7. NVIDIA Nemotron integration

### 7.1 Provider contract

The fixed provider request is:

- base URL: `https://integrate.api.nvidia.com/v1`;
- endpoint: `POST /chat/completions`;
- model: `nvidia/nemotron-3.5-lightning-30b-a3b`;
- authentication: `Authorization: Bearer ${NVIDIA_API_KEY}`;
- modality: text input and text output;
- recommended sampling baseline: temperature `1.0`, top-p `0.95`;
- thinking enabled within a bounded reasoning budget;
- internal reasoning content is never shown to users or persisted.

The current NVIDIA model page documents a 1M-token context window and an example maximum of 16,384 generated tokens. Architex uses substantially smaller per-request budgets and does not treat the provider maximum as a target.

Official references:

- <https://build.nvidia.com/nvidia/nemotron-3.5-lightning-30b-a3b>
- <https://docs.api.nvidia.com/nim/reference/llm-apis>

### 7.2 Shared-hosting adapter

`NvidiaNemotronClient` is a PHP adapter using HTTPS through `ext-curl`. Release verification must fail if the deployment lacks `ext-curl` while Wingman is configured as enabled.

Configuration remains in the server environment or protected runtime configuration outside the public document root:

- `NVIDIA_API_KEY`
- `NVIDIA_API_BASE_URL`, fixed by default to the official integration URL
- `NVIDIA_MODEL`, fixed by default to the approved model
- `WINGMAN_ENABLED`
- `WINGMAN_TIMEOUT_SECONDS`
- `WINGMAN_MAX_INPUT_CHARS`
- `WINGMAN_MAX_OUTPUT_TOKENS`
- `WINGMAN_REASONING_BUDGET`

API keys are never stored in MariaDB, returned by health endpoints, included in logs, embedded in release artifacts, or sent to the browser.

### 7.3 Tool gateway

Nemotron receives only these tool definitions:

- `search_authorised_knowledge(query, sourceTypes, limit)`
- `get_authorised_project_summary(projectId)`
- `get_authorised_record_summary(recordType, recordId)`
- `propose_navigation(route, targetId, reason)`

The model never executes tools directly. PHP validates the requested tool name and arguments, re-authorises the subject, executes a bounded read, and may perform one follow-up model request with the tool result. The result returned to the browser contains a navigation proposal, never an executed mutation.

## 8. Knowledge and persistence

### 8.1 Knowledge sources

Wingman can use:

- curated Architex workflow help and terminology;
- registered route and tool descriptions;
- lifecycle and page-family guidance;
- the signed-in user’s authorised project summaries;
- authorised Project Records, action summaries, document metadata, meeting outcomes, and validation findings.

Project content is queried live through existing permission-aware services. It is not copied into a cross-tenant AI index.

Curated product knowledge is versioned in MariaDB with source title, source type, source reference, content checksum, published status, and timestamps. Search uses MariaDB full-text indexing for the first production release. A vector database, external embedding service, and background ingestion worker are not required.

### 8.2 Conversation records

MariaDB stores:

- tenant-scoped conversation and message identifiers;
- user message and final user-visible assistant answer;
- provider and model identifiers;
- context classification, source references, and citation links;
- navigation suggestions and whether the user accepted or dismissed them;
- provider outcome, latency, token metadata when returned, and stable failure class;
- audit events for conversation start, provider call outcome, suggestion, and accepted navigation.

Provider reasoning content and secrets are never persisted. Default conversation retention is 90 days and may be reduced by organisation policy. Deletion must preserve the minimum audit event required to prove that an AI interaction occurred without retaining message content.

## 9. HTTP surface

The authenticated API surface is:

- `GET /api/v1/wingman/bootstrap?route={route}&projectId={optionalId}`
- `POST /api/v1/wingman/messages`
- `GET /api/v1/wingman/conversations/{conversationId}`
- `POST /api/v1/wingman/suggestions/{suggestionId}/feedback`

Reads require an authenticated active membership. Writes require CSRF protection and existing read/write/govern middleware as appropriate. Rate limits apply per user and organisation. All record and project identifiers are re-authorised server-side.

Responses use stable states:

- `ready`
- `provider_configuration_required`
- `provider_unavailable`
- `no_authorised_source`
- `answer_available`
- `navigation_proposed`
- `request_rejected`

## 10. Failure and safety behavior

| Condition | User-facing behavior | Persistence |
|---|---|---|
| API key absent | “Wingman knowledge service is not configured.” Local route guidance remains available. | Configuration failure audit only |
| Timeout, quota, or provider 5xx | “Wingman is temporarily unavailable.” Preserve the user message as a retryable draft. | Stable provider failure class, no fabricated answer |
| Malformed provider response | “Wingman returned an unusable response.” | Response checksum and failure class, excluding unsafe raw output |
| No authorised evidence | “I could not find evidence in your accessible project records.” | Query and zero-result source boundary |
| Cross-tenant or inaccessible identifier | Generic not-found or permission-denied response | Security audit without leaking record existence |
| Mutation, approval, or external-send request | Explain the human-controlled workflow and propose the governed page | Rejected capability and suggested route |

Wingman suggestions are advisory. Professional, statutory, financial, approval, and issue boundaries already implemented elsewhere remain authoritative.

## 11. Accessibility and responsive behavior

- All interactive targets are at least 44 CSS pixels on touch layouts.
- Keyboard users can open, dismiss, expand, traverse sources, accept navigation, and return focus to the originating control.
- Suggestions announce through `aria-live="polite"`; they never interrupt typing or a screen-reader workflow.
- The bird has text alternatives and never relies on shape or colour alone.
- Focus is visible against Paper, Tracing Wash, and Datum Teal.
- Desktop, tablet, and 390 x 844 mobile layouts must have no horizontal overflow.
- On mobile, the contextual navigator becomes a drawer and Wingman becomes a bottom sheet while the bird trigger remains persistent.
- Reduced-motion preferences disable Wingman transitions and smooth guided scrolling.

## 12. Implementation structure

The implementation should deepen existing seams instead of adding another application shell:

- retain `app/public/app.php` as the canonical authenticated shell document;
- retain `app/public/assets/css/architex-v1.css` as the base V1 source;
- add focused design-system and Flightpath modules rather than continuing monolithic overrides;
- mount one Wingman component from the shell, not from individual workspace renderers;
- use `app/public/assets/js/api-client.js` for authenticated HTTP calls;
- integrate backend services through `AuthenticationKernelFactory` and the existing middleware graph;
- preserve specialist workspace JavaScript and CSS while migrating their layout into shared page-family primitives.

The page migration manifest is the completeness authority. Historical claims, screenshots, or the existence of a shared stylesheet are not evidence that a route has been modernised.

## 13. Staged implementation and release

### Stage 1: Foundation and pilot

- shared tokens, controls, shell, lifecycle navigator, page-family primitives;
- route placement manifest;
- persistent Wingman shell with local guidance states;
- Job Ticket Exchange migrated as the first Create/Review pilot;
- desktop and mobile browser certification.

### Stage 2: Production Wingman

- MariaDB schema and services;
- NVIDIA client, knowledge retrieval, tool gateway, API routes, audit and failure states;
- real endpoint smoke test only when a dedicated test API key is configured;
- no production key or remote mutation during local certification.

### Stage 3: Site-wide page-family migration

- Orient pages;
- Find/register pages;
- Create/form pages;
- Review/issue pages;
- Administer/settings pages.

Each route is migrated, tested, browser-reviewed, and checked in the manifest before the next route family is declared complete. Existing functionality must remain reachable throughout the staged rollout.

### Stage 4: Complete staged-release certification

- every reachable route mapped and visually certified;
- full PHP, MariaDB, frontend, security, and release suites;
- authenticated desktop and 390 x 844 browser evidence;
- navigation, keyboard, contrast, overflow, console, and failed-request gates;
- live NVIDIA smoke evidence separated from deterministic adapter tests;
- exact-commit shared-hosting artifact built, independently extracted, verified, and hashed;
- FTP upload, remote migration, target-host smoke tests, and promotion remain separate remote gates.

## 14. Test strategy

### Unit

- lifecycle placement and role filtering;
- page-family manifests and token contracts;
- context minimisation and prompt construction;
- tool allow-list and argument validation;
- NVIDIA request construction and response parsing;
- stable provider failure mapping;
- navigation proposal validation.

### MariaDB integration

- tenant-isolated conversations, sources, citations, suggestions, feedback, retention, and audit events;
- authorised live project retrieval without cross-tenant copying;
- knowledge full-text search and published-version filtering;
- deletion that removes message content while retaining minimum audit evidence.

### HTTP

- authentication, CSRF, membership, roles, identifier validation, rate limiting, timeouts, and malformed provider responses;
- provider secret never appears in any response or log fixture;
- mutation requests fail closed.

### Frontend contracts

- lifecycle ordering and exactly one active primary placement per route;
- shared control states and accessible names;
- one persistent Wingman mount;
- quiet, suggestion, expanded, unavailable, and mobile states;
- guided navigation requires a user event;
- no inert tabs or misleading buttons.

### Browser

- authenticated lifecycle navigation and form completion;
- persistent Wingman across route changes and hard reload;
- source disclosure and guided focus placement;
- keyboard and screen-reader semantics;
- 1920 desktop and 390 x 844 mobile geometry;
- no horizontal overflow, console errors, or failed application requests;
- Lighthouse accessibility, best-practices, SEO, and agentic-browsing evidence.

## 15. Non-goals

- No autonomous record mutations or approvals.
- No model selector or multi-provider routing in this release.
- No self-hosted NIM deployment in the shared-hosting release.
- No image, audio, video, or drawing interpretation through this text-only model.
- No vector database or external embedding service.
- No redesign that discards the supplied V1 identity or replaces existing production workflows with mock data.
- No claim of complete modernisation until the route manifest and browser evidence cover every reachable production page.

## 16. Acceptance criteria

The programme is complete only when:

1. all reachable production pages have one lifecycle placement and page-family classification;
2. menus expose coherent project workflows rather than accumulated feature lists;
3. shared form controls and page anatomy are used across every classified route;
4. the real bird Wingman remains persistent and accessible across the authenticated shell;
5. guided navigation always requires a user choice;
6. NVIDIA Nemotron calls originate only from PHP with server-held credentials;
7. knowledge and citations remain tenant-scoped and permission-aware;
8. provider failures are honest and do not fabricate knowledge;
9. all automated, MariaDB, browser, accessibility, and staged-artifact gates pass;
10. local staged certification remains explicitly separate from FTP transfer and remote production promotion.
