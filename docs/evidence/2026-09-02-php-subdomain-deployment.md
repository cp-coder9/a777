# PHP subdomain staged deployment evidence

Date: 2026-09-02 (Africa/Johannesburg)

## Released artifact

- Host: `https://php.architex.co.za`
- Release: `php-20260902-4dbe9a9`
- Source commit: `4dbe9a93764d2774e1025d133027a58df19bec53`
- Deployment ID: `20260902-021854-702f3fd8-4a9dbc01e7ce81ad`
- Runtime: PHP 8.3 shared-hosting build
- Database: remote MariaDB preflight passed and migrations reported current
- Mail: SMTP readiness preflight passed
- Wingman: server-side NVIDIA configuration present; no key was exposed in evidence

## Fail-closed deployment proof

The release was uploaded and extracted into public and private candidates. The
private runtime probe passed PHP, database, migration, scheduled-job table, and
mail readiness checks before the first cutover rename. The live document root
and runtime were then activated with a retained prior runtime and public
rollback directory.

Live verification returned HTTP 200 for:

- `/`
- `/build-info.json`
- `/api/v1/health`
- `/api/v1/readiness`
- `/api/v1/legal-notices/signup`

The build metadata returned the exact source commit above, `php: 8.3`, and
`apiMode: php-shared-hosting`.

## Browser acceptance

Chrome DevTools MCP verified the deployed HTTPS origin, not a local server:

- The public Architex landing loaded from `/preview3.html` inside the production shell.
- Clicking `Sign in` opened the real `Welcome back` / `Enter Architex OS` form.
- Switching to `Create account` displayed public organisation and user registration,
  role selection, legal acceptance, email, and password controls.
- The signup legal-notice request returned HTTP 200.
- The persistent bird Wingman opened its guided panel and correctly required
  authentication for permission-aware guidance while signed out.
- No JavaScript exception was present. The only unauthenticated request error was
  the expected session-refresh HTTP 401 used to establish signed-out state.

## Executed checks

- Python deployment/client suite: 31 tests passed.
- PHP unit suite: 191 tests passed, 1,609 assertions.
- Focused public-access suite: 4 tests passed, 25 assertions.
- JavaScript syntax check: passed.
- Release manifest verification: 407 files passed.
- Obsolete A777 deployment candidates and archives were moved to cPanel Trash.
  The active runtime, current prior runtime, shared storage, live public root,
  and current rollback public directory were retained.

No credential, cPanel session identifier, API key, or database password is
recorded in this evidence.
