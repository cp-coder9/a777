# Release 1 Task 7 browser evidence

- Runtime: PHP 8.3 development server at `http://127.0.0.1:8790/`, MariaDB at loopback port 43319.
- Browser surface: Chrome DevTools MCP, page 2, desktop and emulated `390x844` mobile viewport.
- Canonical source SHA-256: `7dbfafdcfa52644fd32fb7cb7d73891b78593ca0287c089240db4439e0fa97bf`.
- Registry: 45 canonical tools, comprising 11 prototype-live entries and 34 scaffolds.

## Executed flow

1. Anonymous shell loaded its CSS and four production JavaScript assets with HTTP 200.
2. `/api/v1/session` returned 401 and the shell showed an honest signed-out state with no demo project loaded.
3. A task-owned synthetic user signed in; `/api/v1/sessions` returned 200.
4. Scoped `/api/v1/projects`, `/api/v1/inbox`, and `/api/v1/tools` requests returned 200.
5. The first-project form created `DevTools Verified Project`; its identity and onboarding phase rendered from MariaDB.
6. A browser reload returned session, projects, inbox, and tools with HTTP 200 and retained the project.
7. The project selector contained only the persisted live project.
8. The production role lens and God Mode controls had computed `display: none`.
9. Prototype metrics were replaced with non-authoritative labels and a visible Release 1 data-scope notice.
10. Requests for an unknown project ID and a forged organisation header both returned 404.
11. Desktop and mobile screenshots were inspected through Chrome DevTools MCP; the mobile main workspace stayed within the 390-pixel visual viewport.

This is local browser evidence, not staged shared-hosting or production certification.
