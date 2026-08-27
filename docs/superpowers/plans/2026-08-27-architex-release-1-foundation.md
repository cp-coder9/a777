# Architex Release 1 Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver the first production-ready Architex release: the exact V1 shell backed by secure multi-tenant PHP 8.3 services and MariaDB persistence, packaged for FTP-only shared hosting.

**Architecture:** Build a framework-light modular PHP monolith with Composer PSR-4 loading, a small first-party HTTP kernel, PDO repositories, ordered SQL migrations, server-side sessions, and a decomposed V1 frontend. Shared identity, organisation, project, event, policy, file, and audit seams are completed before pack-specific modules.

**Tech Stack:** PHP 8.3, MariaDB/InnoDB/utf8mb4, Composer 2, PHPUnit 12.5, vanilla ES modules, CSS extracted from `v1.html`, control-panel cron, Playwright for browser and visual checks.

## Global Constraints

- `v1.html` is the canonical visual and interaction contract.
- Treat every pack requirement as `not_implemented` until current executed evidence passes.
- Production target is PHP 8.3 with MariaDB on FTP-only shared hosting.
- The platform is multi-tenant and supports public organisation/user registration.
- SMTP is mandatory before production registration can be enabled.
- Browser state never grants identity, role, organisation, project, approval, or professional authority.
- Every tenant-owned query is scoped by `organisation_id`; project-owned queries are also scoped by `project_id`.
- Issued/published records are immutable and corrected through superseding versions.
- Provider-backed actions fail closed when the provider is unavailable.
- Release claims require MariaDB, authentication, tenant isolation, persistence/reload, browser, artifact, backup, and restore evidence.

---

## File map

```text
composer.json                         Runtime and test dependency contract
phpunit.xml                           PHPUnit suites and environment
app/public/index.php                  HTTP entrypoint
app/public/.htaccess                  Apache front-controller and security rules
app/public/assets/                    V1-derived production CSS and JavaScript
app/src/Http/                         Request, response, routing, middleware, controllers
app/src/Platform/                     Identity, organisations, projects, records, events, audit
app/src/Infrastructure/               Config, PDO, migrations, mail, files, IDs, clock
app/config/                           Typed environment configuration
app/database/migrations/              Ordered MariaDB schema changes
app/cron/dispatch.php                 Locked scheduled-job runner
app/bin/                              Requirements, build, migration and verification commands
app/tests/                             Unit, HTTP, MariaDB and security tests
docs/requirements/                    Pack traceability ledger
docs/deployment/                      FTP deployment, cron, backup and restore runbooks
```

### Task 1: Canonical pack requirements ledger

**Files:**
- Create: `composer.json`
- Create: `phpunit.xml`
- Create: `app/bin/build-pack-ledger.php`
- Create: `app/src/Requirements/PackLedgerBuilder.php`
- Create: `app/tests/Requirements/PackLedgerBuilderTest.php`
- Create: `docs/requirements/pack-requirements-ledger.json`
- Create: `docs/requirements/pack-requirements-ledger.md`

**Interfaces:**
- Consumes: `packs/**/*.{md,txt,json,ts,tsx}` and Markdown/JSON/text entries inside `packs/*.zip`.
- Produces: `PackLedgerBuilder::build(string $packsDir): array` and deterministic JSON/Markdown ledgers whose entries begin with `status: not_implemented`.

- [ ] **Step 1: Create the Composer and PHPUnit test foundation**

```json
{
  "name": "architex/platform",
  "type": "project",
  "require": {"php": "^8.3", "ext-json": "*", "ext-pdo": "*", "ext-pdo_mysql": "*"},
  "require-dev": {"phpunit/phpunit": "^12.5"},
  "autoload": {"psr-4": {"Architex\\": "app/src/"}},
  "autoload-dev": {"psr-4": {"Architex\\Tests\\": "app/tests/"}},
  "scripts": {"test": "phpunit"},
  "config": {"sort-packages": true, "optimize-autoloader": true}
}
```

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php" colors="true" cacheDirectory=".phpunit.cache">
  <testsuites>
    <testsuite name="unit"><directory>app/tests/Unit</directory><directory>app/tests/Requirements</directory><directory>app/tests/Http</directory></testsuite>
    <testsuite name="integration"><directory>app/tests/Integration</directory></testsuite>
    <testsuite name="security"><directory>app/tests/Security</directory></testsuite>
  </testsuites>
</phpunit>
```

Run: `composer install && composer validate --strict`

Expected: dependencies install, `composer.lock` is created, and validation exits 0.

- [ ] **Step 2: Write the failing deterministic-ledger test**

```php
public function testEveryRequirementStartsNotImplementedAndDuplicatePackTenIsRetained(): void
{
    $ledger = (new PackLedgerBuilder())->build(dirname(__DIR__, 3) . '/packs');
    self::assertNotEmpty($ledger['sources']);
    self::assertContains('10. architex-site-execution-field-control-pack', array_column($ledger['sources'], 'pack'));
    self::assertNotEmpty($ledger['requirements']);
    self::assertSame(['not_implemented'], array_values(array_unique(array_column($ledger['requirements'], 'status'))));
    self::assertSame($ledger, (new PackLedgerBuilder())->build(dirname(__DIR__, 3) . '/packs'));
}
```

- [ ] **Step 3: Run the test and verify the class is missing**

Run: `composer test -- --filter PackLedgerBuilderTest`

Expected: FAIL because `Architex\Requirements\PackLedgerBuilder` does not exist.

- [ ] **Step 4: Implement deterministic source discovery and extraction**

```php
final class PackLedgerBuilder
{
    public function build(string $packsDir): array
    {
        $sources = $this->discoverSources($packsDir);
        $requirements = [];
        foreach ($sources as $source) {
            foreach ($this->extractStatements($source['content']) as $index => $statement) {
                $requirements[] = [
                    'id' => 'PACK-' . substr(hash('sha256', $source['relativePath'] . "\n" . $statement), 0, 16),
                    'pack' => $source['pack'],
                    'source' => $source['relativePath'],
                    'ordinal' => $index + 1,
                    'statement' => $statement,
                    'status' => 'not_implemented',
                    'release' => null,
                    'implementation' => [],
                    'evidence' => [],
                ];
            }
        }
        usort($requirements, fn(array $a, array $b): int => [$a['source'], $a['ordinal']] <=> [$b['source'], $b['ordinal']]);
        return ['schemaVersion' => 1, 'sources' => $this->withoutContent($sources), 'requirements' => $requirements];
    }
}
```

- [ ] **Step 5: Generate and validate both ledgers**

Run: `php app/bin/build-pack-ledger.php --packs=packs --json=docs/requirements/pack-requirements-ledger.json --markdown=docs/requirements/pack-requirements-ledger.md`

Expected: exit 0; summary prints source count, requirement count, and zero implemented requirements.

- [ ] **Step 6: Commit the ledger foundation**

```bash
git add composer.json composer.lock phpunit.xml app/bin/build-pack-ledger.php app/src/Requirements/PackLedgerBuilder.php app/tests/Requirements/PackLedgerBuilderTest.php docs/requirements
git commit -m "build: add canonical pack requirements ledger"
```

### Task 2: PHP application and HTTP kernel

**Files:**
- Modify: `composer.json`
- Modify: `phpunit.xml`
- Create: `app/src/Http/Request.php`
- Create: `app/src/Http/Response.php`
- Create: `app/src/Http/Router.php`
- Create: `app/src/Http/Kernel.php`
- Create: `app/src/Http/Controller/HealthController.php`
- Create: `app/public/index.php`
- Create: `app/public/.htaccess`
- Test: `app/tests/Http/KernelTest.php`

**Interfaces:**
- Consumes: PHP superglobals only in `Request::fromGlobals()`.
- Produces: `Kernel::handle(Request): Response`, route handlers returning the standard `{data,meta,error}` envelope.

- [ ] **Step 1: Extend Composer with named test suites**

```json
{
  "name": "architex/platform",
  "type": "project",
  "require": {"php": "^8.3", "ext-json": "*", "ext-pdo": "*", "ext-pdo_mysql": "*"},
  "require-dev": {"phpunit/phpunit": "^12.5"},
  "autoload": {"psr-4": {"Architex\\": "app/src/"}},
  "autoload-dev": {"psr-4": {"Architex\\Tests\\": "app/tests/"}},
  "scripts": {
    "test": "phpunit",
    "test:unit": "phpunit --testsuite unit",
    "test:integration": "phpunit --testsuite integration"
  },
  "config": {"sort-packages": true, "optimize-autoloader": true}
}
```

- [ ] **Step 2: Write failing kernel envelope tests**

```php
public function testHealthResponseUsesStableEnvelopeAndRequestId(): void
{
    $response = $this->kernel()->handle(Request::json('GET', '/api/v1/health'));
    self::assertSame(200, $response->status());
    self::assertSame('ok', $response->json()['data']['status']);
    self::assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $response->json()['meta']['requestId']);
    self::assertNull($response->json()['error']);
}
```

- [ ] **Step 3: Implement the request, response, router and kernel**

```php
final class Kernel
{
    public function __construct(private Router $router, private IdGenerator $ids) {}

    public function handle(Request $request): Response
    {
        $requestId = $this->ids->ulid();
        try {
            return $this->router->dispatch($request)->withMeta(['requestId' => $requestId]);
        } catch (HttpException $error) {
            return Response::error($error->status(), $error->codeName(), $error->getMessage(), $requestId, $error->details());
        } catch (Throwable) {
            return Response::error(500, 'internal_error', 'The request could not be completed.', $requestId);
        }
    }
}
```

- [ ] **Step 4: Run HTTP tests and syntax checks**

Run: `composer test -- --filter KernelTest && php -l app/public/index.php`

Expected: PASS and `No syntax errors detected`.

- [ ] **Step 5: Commit the HTTP kernel**

```bash
git add composer.json composer.lock phpunit.xml app/src/Http app/public
git commit -m "feat: add PHP HTTP kernel"
```

### Task 3: Typed configuration, MariaDB connection and migrations

**Files:**
- Create: `.env.example`
- Create: `app/src/Infrastructure/Config/AppConfig.php`
- Create: `app/src/Infrastructure/Database/ConnectionFactory.php`
- Create: `app/src/Infrastructure/Database/MigrationRunner.php`
- Create: `app/bin/migrate.php`
- Create: `app/bin/database-status.php`
- Create: `app/database/migrations/0001_platform_spine.sql`
- Test: `app/tests/Integration/Database/MigrationRunnerTest.php`
- Test: `app/tests/Unit/Infrastructure/AppConfigTest.php`

**Interfaces:**
- Produces: `AppConfig::fromEnvironment(array): AppConfig`, `ConnectionFactory::create(AppConfig): PDO`, `MigrationRunner::migrate(): MigrationReport`.

- [ ] **Step 1: Write failing credential-redaction and migration-idempotency tests**

```php
public function testInvalidDatabaseConfigurationNeverLeaksPassword(): void
{
    $this->expectExceptionMessage('DB_PORT must be an integer between 1 and 65535');
    AppConfig::fromEnvironment(['DB_PORT' => 'bad', 'DB_PASSWORD' => 'top-secret']);
}

public function testMigrationRunnerIsIdempotent(): void
{
    $runner = new MigrationRunner($this->pdo(), $this->migrationDirectory());
    self::assertGreaterThan(0, $runner->migrate()->appliedCount);
    self::assertSame(0, $runner->migrate()->appliedCount);
}
```

- [ ] **Step 2: Implement strict configuration and PDO creation**

```php
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config->dbHost, $config->dbPort, $config->dbName),
    $config->dbUser,
    $config->dbPassword,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]
);
```

- [ ] **Step 3: Implement checksum-locked migrations**

```sql
CREATE TABLE schema_migrations (
  version VARCHAR(64) PRIMARY KEY,
  checksum CHAR(64) NOT NULL,
  applied_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

The runner obtains `GET_LOCK('architex_schema_migration', 10)`, rejects changed checksums, wraps each compatible migration in a transaction, and always releases the lock.

- [ ] **Step 4: Verify against a disposable MariaDB schema**

Run: `php app/bin/migrate.php --env=test && php app/bin/database-status.php --env=test && composer test -- --testsuite integration --filter MigrationRunnerTest`

Expected: migration applied once, status reports current, integration test PASS.

- [ ] **Step 5: Commit database infrastructure**

```bash
git add .env.example app/src/Infrastructure app/bin app/database app/tests/Integration app/tests/Unit/Infrastructure
git commit -m "feat: add MariaDB migration infrastructure"
```

### Task 4: Users, organisations, legal acceptance and secure sessions

**Files:**
- Create: `app/database/migrations/0002_identity_and_tenancy.sql`
- Create: `app/src/Platform/Identity/RegistrationService.php`
- Create: `app/src/Platform/Identity/AuthenticationService.php`
- Create: `app/src/Platform/Identity/PasswordHasher.php`
- Create: `app/src/Platform/Identity/SessionService.php`
- Create: `app/src/Platform/Organisation/OrganisationRepository.php`
- Create: `app/src/Platform/Organisation/MembershipRepository.php`
- Create: `app/src/Platform/Legal/LegalAcceptanceService.php`
- Test: `app/tests/Integration/Identity/RegistrationServiceTest.php`
- Test: `app/tests/Integration/Identity/TenantIsolationTest.php`
- Test: `app/tests/Unit/Identity/PasswordHasherTest.php`

**Interfaces:**
- Produces: `RegistrationService::register(RegisterOrganisation): PendingRegistration`, `AuthenticationService::authenticate(string,string): AuthenticatedIdentity`, `SessionService::rotate(UserId): SessionToken`.

- [ ] **Step 1: Write failing registration and isolation tests**

```php
public function testRegistrationCreatesPendingUserOrganisationMembershipAndLegalEvidenceAtomically(): void
{
    $result = $this->service()->register(RegisterOrganisation::fromArray($this->validRegistration()));
    self::assertSame('pending_email_verification', $result->status->value);
    self::assertSame(1, $this->count('organisations'));
    self::assertSame(1, $this->count('organisation_memberships'));
    self::assertSame(4, $this->count('legal_acceptances'));
}

public function testRepositoryCannotReadAnotherOrganisationByIdentifier(): void
{
    self::assertNull($this->projects->find($this->organisationA, $this->projectOwnedByB));
}
```

- [ ] **Step 2: Create the identity and tenancy schema**

```sql
CREATE TABLE organisation_memberships (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  user_id BINARY(16) NOT NULL,
  status ENUM('pending','active','suspended','revoked') NOT NULL,
  created_at DATETIME(6) NOT NULL,
  UNIQUE KEY uq_membership (organisation_id, user_id),
  CONSTRAINT fk_membership_org FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  CONSTRAINT fk_membership_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Step 3: Implement transactional pending registration**

```php
return $this->transactions->run(function () use ($command): PendingRegistration {
    $user = $this->users->createPending($command->email, $this->passwords->hash($command->password));
    $organisation = $this->organisations->create($command->organisationName, $command->organisationSlug);
    $this->memberships->addOwner($organisation->id, $user->id);
    $this->legal->recordRequiredSignupAcceptances($user->id, $organisation->id, $command->acceptedNoticeVersions);
    $verification = $this->verificationTokens->issue($user->id, $this->clock->now()->modify('+30 minutes'));
    $this->events->append($organisation->id, $user->id, 'registration_requested', ['userId' => $user->id->toString()]);
    return new PendingRegistration($user->id, $organisation->id, $verification->plaintextToken, RegistrationStatus::PendingEmailVerification);
});
```

- [ ] **Step 4: Verify identity, expiry, replay and isolation cases**

Run: `composer test -- --filter '/RegistrationServiceTest|TenantIsolationTest|PasswordHasherTest/'`

Expected: PASS, including duplicate email/slug rollback, expired token rejection, token replay rejection, password rehash, session rotation, and cross-tenant negative tests.

- [ ] **Step 5: Commit identity and tenancy**

```bash
git add app/database/migrations/0002_identity_and_tenancy.sql app/src/Platform/Identity app/src/Platform/Organisation app/src/Platform/Legal app/tests
git commit -m "feat: add multi-tenant identity foundation"
```

### Task 5: Authentication HTTP, CSRF, permissions and rate limits

**Files:**
- Create: `app/src/Http/Middleware/SessionMiddleware.php`
- Create: `app/src/Http/Middleware/CsrfMiddleware.php`
- Create: `app/src/Http/Middleware/OrganisationContextMiddleware.php`
- Create: `app/src/Http/Middleware/PermissionMiddleware.php`
- Create: `app/src/Http/Middleware/RateLimitMiddleware.php`
- Create: `app/src/Http/Controller/AuthController.php`
- Create: `app/src/Http/Controller/RegistrationController.php`
- Create: `app/src/Platform/Authorization/PermissionService.php`
- Test: `app/tests/Http/AuthControllerTest.php`
- Test: `app/tests/Security/AuthorizationMatrixTest.php`

**Interfaces:**
- Produces: authenticated `RequestContext`, CSRF-protected commands, named permission decisions.

- [ ] **Step 1: Write failing negative-path HTTP tests**

```php
#[DataProvider('protectedRequestCases')]
public function testProtectedCommandsFailClosed(Request $request, int $expectedStatus, string $code): void
{
    $response = $this->kernel()->handle($request);
    self::assertSame($expectedStatus, $response->status());
    self::assertSame($code, $response->json()['error']['code']);
}
```

The provider covers anonymous 401, wrong tenant 404, missing permission 403, missing CSRF 419, stale version 409, unsupported media 415, and throttled registration 429.

- [ ] **Step 2: Implement middleware ordering**

```php
$pipeline = new MiddlewarePipeline([
    new SecurityHeadersMiddleware($config),
    new RequestBodyLimitMiddleware($config->maxJsonBytes),
    new SessionMiddleware($sessions),
    new OrganisationContextMiddleware($memberships),
    new CsrfMiddleware($csrf),
    new RateLimitMiddleware($rateLimits),
]);
```

- [ ] **Step 3: Register auth routes**

```php
$router->post('/api/v1/registrations', [$registration, 'create']);
$router->post('/api/v1/registrations/verify-email', [$registration, 'verifyEmail']);
$router->post('/api/v1/sessions', [$auth, 'login']);
$router->delete('/api/v1/session', [$auth, 'logout']);
$router->post('/api/v1/password-resets', [$auth, 'requestPasswordReset']);
$router->post('/api/v1/password-resets/complete', [$auth, 'completePasswordReset']);
$router->get('/api/v1/session', [$auth, 'current']);
```

- [ ] **Step 4: Run HTTP and authorization suites**

Run: `composer test -- --filter '/AuthControllerTest|AuthorizationMatrixTest/'`

Expected: PASS for success and all fail-closed cases.

- [ ] **Step 5: Commit the authentication boundary**

```bash
git add app/src/Http app/src/Platform/Authorization app/tests/Http app/tests/Security
git commit -m "feat: enforce authenticated tenant access"
```

### Task 6: Project Passport, lifecycle, records, inbox and audit

**Files:**
- Create: `app/database/migrations/0004_project_workflow_spine.sql`
- Create: `app/src/Platform/Project/ProjectPassportService.php`
- Create: `app/src/Platform/Project/LifecycleService.php`
- Create: `app/src/Platform/Records/ProjectRecordService.php`
- Create: `app/src/Platform/Workflow/EventBus.php`
- Create: `app/src/Platform/Inbox/InboxService.php`
- Create: `app/src/Platform/Audit/AuditService.php`
- Create: `app/src/Http/Controller/ProjectController.php`
- Test: `app/tests/Integration/Project/ProjectWorkflowTest.php`
- Test: `app/tests/Integration/Audit/AuditRedactionTest.php`

**Interfaces:**
- Produces: `ProjectPassportService::create(CreateProject): ProjectPassport`, `LifecycleService::transition(ProjectContext,ProjectPhase,int): Project`, `EventBus::append(WorkflowEvent): void`.

- [ ] **Step 1: Write a failing transactional workflow test**

```php
public function testLifecycleTransitionPersistsRecordInboxAndAuditInOneTransaction(): void
{
    $project = $this->projects->create($this->architectContext(), $this->validProject());
    $changed = $this->lifecycle->transition($this->architectContext($project->id), ProjectPhase::Appointment, $project->version);
    self::assertSame(ProjectPhase::Appointment, $changed->phase);
    self::assertSame(1, $this->countWhere('project_records', ['project_id' => $project->id->bytes()]));
    self::assertSame(1, $this->countWhere('inbox_items', ['project_id' => $project->id->bytes()]));
    self::assertSame(2, $this->countWhere('audit_events', ['project_id' => $project->id->bytes()]));
}
```

- [ ] **Step 2: Create tenant-bound project tables and indexes**

```sql
CREATE TABLE projects (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  project_code VARCHAR(64) NOT NULL,
  name VARCHAR(200) NOT NULL,
  phase VARCHAR(64) NOT NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  UNIQUE KEY uq_project_code (organisation_id, project_code),
  KEY idx_project_org_phase (organisation_id, phase),
  CONSTRAINT fk_project_org FOREIGN KEY (organisation_id) REFERENCES organisations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Step 3: Implement optimistic, transactional lifecycle transitions**

```php
$updated = $this->projects->transition($context->organisationId, $context->projectId, $fromVersion, $target);
if (!$updated) {
    throw new ConflictException('stale_project_version', 'The project changed; reload before retrying.');
}
$record = $this->records->append($context, 'project_phase_changed', $updated->toSnapshot());
$this->events->append(WorkflowEvent::projectPhaseChanged($context, $record));
$this->audit->append($context, 'project.phase.changed', $record->id, ['from' => $current->phase->value, 'to' => $target->value]);
```

- [ ] **Step 4: Verify persistence, rollback, conflict and audit redaction**

Run: `composer test -- --filter '/ProjectWorkflowTest|AuditRedactionTest/'`

Expected: PASS and no partial records after a forced transaction failure.

- [ ] **Step 5: Commit the project workflow spine**

```bash
git add app/database/migrations/0003_project_workflow_spine.sql app/src/Platform/Project app/src/Platform/Records app/src/Platform/Workflow app/src/Platform/Inbox app/src/Platform/Audit app/src/Http/Controller/ProjectController.php app/tests
git commit -m "feat: add project workflow spine"
```

### Task 7: Exact V1 shell and live Release 1 data

**Files:**
- Create: `app/bin/extract-v1-assets.php`
- Create: `app/public/assets/css/architex-v1.css`
- Create: `app/public/assets/js/api-client.js`
- Create: `app/public/assets/js/shell.js`
- Create: `app/public/assets/js/project-datum.js`
- Create: `app/public/assets/js/tool-registry.js`
- Create: `app/public/app.php`
- Create: `app/tests/Frontend/V1ExtractionTest.php`
- Create: `app/tests/Browser/release1.spec.ts`

**Interfaces:**
- Consumes: authenticated `/api/v1/session`, `/api/v1/projects`, `/api/v1/projects/{id}`, `/api/v1/inbox`, `/api/v1/tools`.
- Produces: V1-faithful shell with honest Release 1 data, stable loading/empty/error states, and no role override from browser state.

- [ ] **Step 1: Write failing extraction-fidelity tests**

```php
public function testExtractedShellRetainsCanonicalSelectorsAndToolIds(): void
{
    $html = file_get_contents(dirname(__DIR__, 3) . '/app/public/app.php');
    foreach (['os-rail', 'navigator', 'topbar', 'main', 'inspector'] as $selector) {
        self::assertStringContainsString($selector, $html);
    }
    $registry = json_decode(file_get_contents(dirname(__DIR__, 3) . '/app/public/assets/tool-registry.json'), true, flags: JSON_THROW_ON_ERROR);
    self::assertSame(47, count($registry['tools']));
    self::assertContains('project_passport', array_column($registry['tools'], 'id'));
}
```

- [ ] **Step 2: Extract canonical CSS and registries mechanically**

Run: `php app/bin/extract-v1-assets.php --source=v1.html --public=app/public`

Expected: prints canonical source hash, 47 tools, 13 prototype-live labels, 34 scaffold labels, and generated asset hashes.

- [ ] **Step 3: Replace authoritative prototype state with API state**

```javascript
export async function loadShellState(api) {
  const [session, projects, inbox, tools] = await Promise.all([
    api.get('/api/v1/session'),
    api.get('/api/v1/projects'),
    api.get('/api/v1/inbox'),
    api.get('/api/v1/tools')
  ]);
  return Object.freeze({ session, projects, inbox, tools, selectedProjectId: projects[0]?.id ?? null });
}
```

The role selector may change a demo viewing lens only inside an isolated demo account. Production navigation uses roles returned by `/api/v1/session`.

- [ ] **Step 4: Run browser persistence and visual gates**

Run: `npx playwright test app/tests/Browser/release1.spec.ts`

Expected: login succeeds; project creation persists after reload; cross-tenant URL returns 404; desktop/mobile screenshots match approved baselines within the configured pixel threshold.

- [ ] **Step 5: Commit the V1 shell conversion**

```bash
git add app/bin/extract-v1-assets.php app/public app/tests/Frontend app/tests/Browser
git commit -m "feat: connect exact V1 shell to production services"
```

### Task 8: Mail adapter, scheduled jobs and truthful readiness

**Files:**
- Create: `app/src/Infrastructure/Mail/Mailer.php`
- Create: `app/src/Infrastructure/Mail/CapturedMailer.php`
- Create: `app/src/Infrastructure/Mail/SmtpMailer.php`
- Create: `app/src/Platform/Jobs/JobDispatcher.php`
- Create: `app/cron/dispatch.php`
- Modify: `app/src/Http/Controller/HealthController.php`
- Test: `app/tests/Unit/Jobs/JobDispatcherTest.php`
- Test: `app/tests/Http/ReadinessControllerTest.php`

**Interfaces:**
- Produces: `Mailer::send(Message): DeliveryReceipt`, `JobDispatcher::run(int $limit): DispatchReport`, `/api/v1/readiness`.

- [ ] **Step 1: Write failing retry, lock and readiness tests**

```php
public function testUnavailableSmtpKeepsRegistrationJobRetryable(): void
{
    $report = $this->dispatcher(new FailingMailer())->run(10);
    self::assertSame(1, $report->retried);
    self::assertSame('retry', $this->jobState('send_verification_email'));
}

public function testReadinessFailsWhenDatabaseCannotBeQueried(): void
{
    $response = $this->kernelWithBrokenDatabase()->handle(Request::json('GET', '/api/v1/readiness'));
    self::assertSame(503, $response->status());
    self::assertSame('unavailable', $response->json()['data']['database']['status']);
}
```

- [ ] **Step 2: Implement transactional job claiming and bounded retries**

```sql
UPDATE scheduled_jobs
SET status='running', locked_by=:worker, locked_at=UTC_TIMESTAMP(6), attempts=attempts+1
WHERE id=(
  SELECT id FROM (
    SELECT id FROM scheduled_jobs
    WHERE status IN ('queued','retry') AND available_at<=UTC_TIMESTAMP(6)
    ORDER BY available_at,id LIMIT 1 FOR UPDATE SKIP LOCKED
  ) claimable
);
```

- [ ] **Step 3: Make registration fail closed without SMTP in production**

```php
if ($config->environment === 'production' && !$mailer->isConfigured()) {
    throw new ServiceUnavailableException('registration_email_unavailable', 'Registration is temporarily unavailable.');
}
```

- [ ] **Step 4: Run jobs and readiness tests**

Run: `composer test -- --filter '/JobDispatcherTest|ReadinessControllerTest/' && php app/cron/dispatch.php --once --limit=25`

Expected: tests PASS; cron reports claimed/succeeded/retried/dead counts and exits 0.

- [ ] **Step 5: Commit operations infrastructure**

```bash
git add app/src/Infrastructure/Mail app/src/Platform/Jobs app/cron app/src/Http/Controller/HealthController.php app/tests
git commit -m "feat: add reliable jobs and readiness checks"
```

### Task 9: Secure files, headers and audit-safe diagnostics

**Files:**
- Create: `app/database/migrations/0005_files_and_security.sql`
- Create: `app/src/Platform/Files/FileService.php`
- Create: `app/src/Http/Controller/FileController.php`
- Create: `app/src/Http/Middleware/SecurityHeadersMiddleware.php`
- Create: `app/src/Infrastructure/Logging/JsonLogger.php`
- Test: `app/tests/Security/FileSecurityTest.php`
- Test: `app/tests/Security/HeadersTest.php`

**Interfaces:**
- Produces: authorized upload/download, non-public randomized paths, structured credential-free logs.

- [ ] **Step 1: Write failing executable-upload and unauthorized-download tests**

```php
public function testPhpPayloadIsRejectedEvenWhenNamedAsAnImage(): void
{
    $upload = UploadedFile::fake('evidence.jpg', "<?php echo 'owned';", 'image/jpeg');
    $response = $this->postFile('/api/v1/projects/' . $this->projectA . '/files', $upload, $this->userA);
    self::assertSame(422, $response->status());
    self::assertSame('unsafe_file', $response->json()['error']['code']);
}

public function testAnotherTenantCannotDownloadByKnowingTheFileId(): void
{
    self::assertSame(404, $this->download($this->tenantAFile, $this->userB)->status());
}
```

- [ ] **Step 2: Implement MIME, size, storage and authorization controls**

```php
$detectedMime = (new finfo(FILEINFO_MIME_TYPE))->file($upload->temporaryPath());
if (!isset($this->allowedMimeTypes[$detectedMime]) || $upload->size() > $this->maxBytes) {
    throw new ValidationException('unsafe_file', 'The file type or size is not allowed.');
}
$storageName = bin2hex(random_bytes(24));
$path = $this->storage->put($context->organisationId, $storageName, $upload->temporaryPath());
```

- [ ] **Step 3: Add default security headers**

```php
return $response->withHeaders([
    'Content-Security-Policy' => "default-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'self'; frame-ancestors 'none'",
    'Referrer-Policy' => 'strict-origin-when-cross-origin',
    'X-Content-Type-Options' => 'nosniff',
    'X-Frame-Options' => 'DENY',
    'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
]);
```

- [ ] **Step 4: Run security tests**

Run: `composer test -- --testsuite security`

Expected: PASS for MIME spoofing, traversal, executable upload, cross-tenant access, CSP and secret-redaction cases.

- [ ] **Step 5: Commit files and security controls**

```bash
git add app/database/migrations/0004_files_and_security.sql app/src/Platform/Files app/src/Http/Controller/FileController.php app/src/Http/Middleware/SecurityHeadersMiddleware.php app/src/Infrastructure/Logging app/tests/Security
git commit -m "feat: secure files and HTTP responses"
```

### Task 10: FTP release artifact, migrations, backup and restore gate

**Files:**
- Create: `app/bin/build-release.php`
- Create: `app/bin/verify-release.php`
- Create: `app/bin/backup.php`
- Create: `app/bin/restore.php`
- Create: `app/public/maintenance.php`
- Create: `docs/deployment/shared-hosting-ftp.md`
- Create: `docs/deployment/cron.md`
- Create: `docs/deployment/backup-restore.md`
- Test: `app/tests/Release/ReleaseArtifactTest.php`

**Interfaces:**
- Produces: immutable `dist/architex-release-<version>/` with SHA-256 manifest, no secrets/dev dependencies, protected migration/preflight entrypoint, and restore-tested backup format.

- [ ] **Step 1: Write failing artifact-content tests**

```php
public function testArtifactContainsRuntimeAndExcludesSecretsAndTests(): void
{
    $release = $this->builder()->build('test-release');
    self::assertFileExists($release . '/public/index.php');
    self::assertFileExists($release . '/vendor/autoload.php');
    self::assertFileExists($release . '/release-manifest.json');
    self::assertFileDoesNotExist($release . '/.env');
    self::assertDirectoryDoesNotExist($release . '/tests');
    self::assertSame([], $this->scanForSecrets($release));
}
```

- [ ] **Step 2: Build a production dependency tree and integrity manifest**

Run: `composer install --no-dev --prefer-dist --optimize-autoloader --classmap-authoritative && php app/bin/build-release.php --version=release1-local`

Expected: immutable release directory and deterministic SHA-256 manifest.

- [ ] **Step 3: Verify clean install, migration, backup and isolated restore**

Run: `php app/bin/verify-release.php dist/architex-release-release1-local && php app/bin/backup.php --env=test --output=dist/release1-test-backup && php app/bin/restore.php --env=restore_test --input=dist/release1-test-backup --confirm-database=architex_restore_test`

Expected: artifact verification PASS; restore uses only the explicitly named `_test` database; record/file counts and checksums match.

- [ ] **Step 4: Run the full Release 1 gate**

Run: `composer install && composer validate --strict && composer audit && composer test && npx playwright test app/tests/Browser/release1.spec.ts && php app/bin/build-release.php --version=release1-candidate && php app/bin/verify-release.php dist/architex-release-release1-candidate`

Expected: all commands exit 0; browser evidence includes authentication, tenant isolation, MariaDB reload persistence, desktop/mobile parity and accessibility checks.

- [ ] **Step 5: Commit the deployment handoff**

```bash
git add app/bin app/public/maintenance.php app/tests/Release docs/deployment composer.lock
git commit -m "build: add FTP release and recovery gate"
```

## Release 1 completion evidence

Release 1 may be marked complete only when the following current artifacts exist and pass:

- generated all-pack requirements ledger with every unevidenced item still `not_implemented`;
- PHP unit, HTTP, security and MariaDB integration test reports;
- V1 desktop/mobile visual baselines and computed-layout assertions;
- authenticated Project Passport persistence after a full browser reload;
- explicit cross-tenant and role-denial evidence;
- database readiness that performs a real query and checks migration state;
- cron freshness and retry/dead-letter evidence;
- clean FTP artifact verification;
- backup plus isolated restore rehearsal report;
- deployment checklist listing SMTP, host database, TLS and document-root gates separately from local completion.
