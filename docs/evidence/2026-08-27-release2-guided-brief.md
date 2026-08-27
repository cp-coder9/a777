# Release 2 guided brief lifecycle — local evidence

## Implemented contract

- Plain-language, resumable brief answers for intent, outcome, existing plans/work, urgency, budget comfort and support needs.
- Optimistic version checks reject stale saves; submitted/converted records are locked.
- Submission validates completeness and appends a versioned, immutable interpretation record.
- Interpretation is deterministic rule-based guidance with explicit `advisoryOnly` and professional-verification limitations; it does not pretend to be an AI, municipal or statutory decision.
- Conversion atomically links the brief to a tenant project and issues an approved `project_brief` ProjectRecord containing the exact answer and interpretation snapshot.
- Create, save, submit and conversion actions are tenant-scoped, transactional and audited.

## Executed checks

- MariaDB guided-brief integration: 3 tests, 16 assertions — pass in 04:02.479.
- Covers resumable submission, professional/approval/risk routing, incomplete/stale/cross-tenant denial, ProjectRecord conversion and post-conversion immutability.
- PHP syntax: service, controller, routes and integration test — pass.
- Task-owned source database `architex_task7_live`: one migration applied, zero pending, current at eight migrations.

## Chrome DevTools MCP

Against persistent `http://127.0.0.1:8790/` using the authenticated owner session:

- create `d145b71fd34742b367a588f3b80ea26c`: HTTP 201, version 1;
- save six guided steps: HTTP 200, version 2;
- submit: HTTP 200, version 3, advisory routing returned architect, quantity surveyor and conditional town-planner review plus three transparent risk flags;
- assign to project `af189b7f799637ca7918b1a3d6858292`: HTTP 200, converted version 4;
- immutable approved ProjectRecord: `e52e54fc7aaba9433d19800c98365728`;
- list/reload: HTTP 200 with the converted brief and persisted answers.

## Boundary

This completes the backend/API guided-brief lifecycle, not all Release 2 work and not remote stage certification. Evidence attachments, client-facing wizard UI, invitation/team workflows, proposals and appointment conversion remain subsequent slices.
