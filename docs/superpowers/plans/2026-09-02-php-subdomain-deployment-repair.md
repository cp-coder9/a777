# PHP Subdomain Deployment Repair Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add and run a fail-closed cPanel deployment command that activates the verified A777 PHP release on `php.architex.co.za` without SSH.

**Architecture:** A small standard-library cPanel client owns authenticated HTTP and filesystem operations. A separate deployment orchestrator owns release verification, staging, preflight, atomic renames, live verification, rollback, recovery, and redacted output. Existing archive and runtime-configuration helpers remain in `tools/deploy_php_ftps.py`.

**Tech Stack:** Python 3.11+ standard library, cPanel UAPI, cPanel API 2 `Fileman`, PHP 8.3, MariaDB, PHPUnit release verifier.

## Global Constraints

- Work only in `E:\a777`.
- Do not use SSH or a remote terminal.
- Do not store or print cPanel, MariaDB, SMTP, FTP, or NVIDIA credentials.
- Restrict all remote mutations to `/home/archite4/public_html`.
- Preserve the previous public root and private runtime for staged rollback.
- Do not modify or stage user-owned `backups/`, `packs/`, `v1.html`, or `tools/__pycache__/`.
- Use inline execution; do not dispatch subagents.

---

### Task 1: Authenticated cPanel filesystem client

**Files:**
- Create: `tools/cpanel_client.py`
- Create: `tools/tests/test_cpanel_client.py`

**Interfaces:**
- Consumes: cPanel host, port, username, password, expected account home, and optional CA file.
- Produces: `CpanelClient`, `CpanelError`, `canonical_remote_path()`, `redact_text()`.
- `CpanelClient` methods: `account_home()`, `list_files(path)`, `upload_file(local, remote)`, `save_file(remote, content)`, `mkdir(path)`, `extract_archive(archive)`, `rename(source, destination)`, `trash(path)`, `exists(path)`.

- [ ] **Step 1: Write path-containment, authentication, redaction, and API-result tests**

```python
class CpanelClientTest(unittest.TestCase):
    def test_canonical_path_rejects_escape(self):
        with self.assertRaises(CpanelError):
            canonical_remote_path('/home/archite4/public_html', '/home/archite4/public_html/../etc')

    def test_failed_api_result_raises_without_secret(self):
        transport = FakeTransport({'result': {'status': 0, 'errors': ['denied']}})
        client = configured_client(transport=transport, password='do-not-print')
        with self.assertRaisesRegex(CpanelError, 'denied') as caught:
            client.list_files('/home/archite4/public_html')
        self.assertNotIn('do-not-print', str(caught.exception))

    def test_upload_uses_multipart_and_basic_auth(self):
        client = configured_client(transport=FakeTransport(ok_uapi()))
        client.upload_file(FIXTURE, '/home/archite4/public_html/package.zip')
        request = client.transport.requests[-1]
        self.assertIn('multipart/form-data; boundary=', request.headers['Content-Type'])
        self.assertEqual('Basic ', request.headers['Authorization'][:6])
```

- [ ] **Step 2: Run the focused tests and confirm they fail**

Run: `python -m unittest tools.tests.test_cpanel_client -v`

Expected: import failure for `tools.cpanel_client`.

- [ ] **Step 3: Implement the standard-library cPanel client**

```python
class CpanelClient:
    def __init__(self, config: CpanelConfig, transport: HttpTransport | None = None) -> None:
        self.config = config
        self.transport = transport or UrllibTransport(config)

    def _uapi(self, module: str, function: str, params: Mapping[str, str]) -> object:
        payload = self.transport.request_json('GET', f'/execute/{module}/{function}', params=params)
        result = payload.get('result', {})
        if result.get('status') != 1:
            raise CpanelError(redact_text(str(result.get('errors') or 'cPanel UAPI failure'), self.config.secrets))
        return result.get('data')

    def _api2_fileop(self, op: str, source: str, destination: str = '', metadata: str = '') -> object:
        params = {'cpanel_jsonapi_user': self.config.username, 'cpanel_jsonapi_apiversion': '2',
                  'cpanel_jsonapi_module': 'Fileman', 'cpanel_jsonapi_func': 'fileop',
                  'op': op, 'sourcefiles': source, 'destfiles': destination,
                  'doubledecode': '1', 'metadata': metadata}
        payload = self.transport.request_json('GET', '/json-api/cpanel', params=params)
        result = payload.get('cpanelresult', {})
        if result.get('event', {}).get('result') != 1:
            raise CpanelError(redact_text(str(result), self.config.secrets))
        return result.get('data', [])

    def upload_file(self, local: Path, remote: str) -> None:
        target = canonical_remote_path(self.config.allowed_root, remote)
        self.transport.upload('/execute/Fileman/upload_files', Path(target).parent.as_posix(), local, Path(target).name)

    def save_file(self, remote: str, content: bytes) -> None:
        target = canonical_remote_path(self.config.allowed_root, remote)
        self._uapi('Fileman', 'save_file_content', {'file': target, 'content': content.decode('utf-8')})

    def extract_archive(self, archive: str) -> None:
        self._api2_fileop('extract', canonical_remote_path(self.config.allowed_root, archive))

    def rename(self, source: str, destination: str) -> None:
        self._api2_fileop('rename', canonical_remote_path(self.config.allowed_root, source),
                          canonical_remote_path(self.config.allowed_root, destination))
```

Use HTTPS with certificate verification, Basic authentication, explicit 45-second timeouts, multipart upload for binary archives, UAPI JSON validation, and API 2 only for operations without a UAPI equivalent. Reject every path outside the configured allowed root before network I/O.

- [ ] **Step 4: Run the focused tests**

Run: `python -m unittest tools.tests.test_cpanel_client -v`

Expected: all cPanel client tests pass.

- [ ] **Step 5: Commit the client slice**

```powershell
git add -- tools/cpanel_client.py tools/tests/test_cpanel_client.py
git commit -m "feat: add constrained cPanel filesystem client"
```

### Task 2: Test-first deployment state machine

**Files:**
- Create: `tools/repair_php_deployment.py`
- Create: `tools/tests/test_repair_php_deployment.py`
- Modify: `tools/deploy_php_ftps.py`

**Interfaces:**
- Consumes: `CpanelClient`, `build_transport_archive()`, `runtime_config()`, a verified release path, and an injected HTTP verifier.
- Produces: `DeploymentConfig`, `DeploymentPaths`, `DeploymentResult`, `DeploymentOrchestrator.deploy()`, `DeploymentOrchestrator.recovery_plan()`.

- [ ] **Step 1: Write state-machine ordering and rollback tests**

```python
class DeploymentOrchestratorTest(unittest.TestCase):
    def test_preflight_happens_before_first_rename(self):
        remote = FakeCpanel()
        http = FakeHttp(preflight=ready_preflight(), live=ready_live())
        deployment(remote, http).deploy()
        self.assertLess(remote.events.index('save:probe'), http.events.index('get:preflight'))
        self.assertLess(http.events.index('get:preflight'), remote.events.index('rename:runtime-candidate'))

    def test_live_failure_restores_public_and_runtime(self):
        remote = FakeCpanel(active_runtime=True, public_root=True)
        http = FakeHttp(preflight=ready_preflight(), live={'/api/v1/readiness': (503, {})})
        with self.assertRaises(DeploymentFailure):
            deployment(remote, http).deploy()
        self.assertEqual(remote.current_public, 'original-public')
        self.assertEqual(remote.current_runtime, 'original-runtime')
        self.assertNotIn('probe', remote.public_files)

    def test_output_redacts_all_secrets(self):
        result = deployment(FakeCpanel(), FakeHttp()).failure_result(RuntimeError('pw-secret'))
        self.assertNotIn('pw-secret', json.dumps(result.to_dict()))
```

- [ ] **Step 2: Run the focused tests and confirm they fail**

Run: `python -m unittest tools.tests.test_repair_php_deployment -v`

Expected: import failure for `tools.repair_php_deployment`.

- [ ] **Step 3: Refactor shared archive helpers without changing FTPS behavior**

Move no network logic. Add focused pure helpers to `tools/deploy_php_ftps.py`:

```python
def validate_release(release: Path) -> None:
    required = (release / 'release.json', release / 'release-manifest.json', release / 'public' / 'index.php')
    if not all(path.is_file() for path in required):
        raise RuntimeError('Release artifact is incomplete.')

def deployment_token(release: Path, now: datetime | None = None) -> str:
    instant = now or datetime.now(timezone.utc)
    stamp = instant.strftime('%Y%m%d-%H%M%S')
    suffix = hashlib.sha256(f'{release.resolve()}:{stamp}'.encode()).hexdigest()[:8]
    return f'{stamp}-{suffix}-{secrets.token_hex(8)}'

def build_runtime_config(target_url: str, storage_path: str, environment: Mapping[str, str]) -> bytes:
    namespace = argparse.Namespace(target_url=target_url)
    previous = os.environ.copy()
    try:
        os.environ.update(environment)
        return runtime_config(namespace, storage_path)
    finally:
        os.environ.clear()
        os.environ.update(previous)
```

Keep `runtime_config(args, storage_path)` as a compatibility wrapper so the existing FTPS command remains valid.

- [ ] **Step 4: Implement the deployment state machine**

```python
class DeploymentOrchestrator:
    def deploy(self) -> DeploymentResult:
        self._verify_local_release()
        self._verify_account_and_paths()
        self._stage_archive()
        self._write_private_runtime()
        self._run_preflight()
        try:
            self._cutover()
            self._verify_live()
        except Exception:
            self._rollback()
            raise
        finally:
            self._cleanup_temporary_files()
        return self._success_result()
```

Track each completed rename individually. Roll back in reverse order. Require preflight JSON fields `ok`, `php`, `database`, `migrations`, and `mail` to be true, with `phpVersionId >= 80300`. For the landing page, require status 200 and either the release commit or the A777 build marker. Do not follow cross-host redirects.

- [ ] **Step 5: Add recovery planning and explicit recovery application**

```python
def recovery_plan(self, deployment_id: str) -> RecoveryPlan:
    paths = DeploymentPaths.for_id(self.config, deployment_id)
    observed = {name: self.remote.exists(path) for name, path in paths.recovery_targets().items()}
    return RecoveryPlan(deployment_id=deployment_id, paths=paths, observed=observed)

def apply_recovery(self, plan: RecoveryPlan) -> DeploymentResult:
    plan.require_unambiguous()
    for source, destination in plan.reverse_renames():
        self.remote.rename(source, destination)
    self._verify_restored_root()
    return DeploymentResult.recovered(plan.deployment_id, plan.paths)
```

Dry-run is the default. `apply_recovery()` accepts only the exact paths derived from the supplied deployment ID and the configured target root.

- [ ] **Step 6: Run both deployment test modules**

Run: `python -m unittest tools.tests.test_cpanel_client tools.tests.test_repair_php_deployment -v`

Expected: all tests pass.

- [ ] **Step 7: Commit the orchestrator slice**

```powershell
git add -- tools/deploy_php_ftps.py tools/repair_php_deployment.py tools/tests/test_repair_php_deployment.py
git commit -m "feat: add staged PHP deployment repair"
```

### Task 3: CLI, local release verification, and dry-run evidence

**Files:**
- Modify: `tools/repair_php_deployment.py`
- Modify: `tools/tests/test_repair_php_deployment.py`
- Create: `docs/deployment/php-shared-hosting-repair.md`

**Interfaces:**
- Produces CLI modes `deploy`, `recover`, and `inspect`.
- `inspect` is read-only and verifies account home, document root, candidate collisions, and current endpoint state.

- [ ] **Step 1: Add CLI parsing and output tests**

```python
def test_inspect_requires_no_runtime_secrets(self):
    args = parse_args(['inspect', '--release', str(RELEASE), '--target-url', TARGET_URL,
                       '--document-root', DOCUMENT_ROOT])
    self.assertEqual('inspect', args.command)

def test_deploy_requires_all_private_variables(self):
    with self.assertRaisesRegex(DeploymentFailure, 'Missing private runtime environment keys'):
        run_cli(parse_args(deploy_args()), environment=cpanel_only_environment())

def test_failure_output_is_one_redacted_json_object(self):
    output = render_failure(DeploymentFailure('password=secret-value'), secrets=('secret-value',))
    self.assertEqual('failed', json.loads(output)['status'])
    self.assertNotIn('secret-value', output)

def test_recover_is_dry_run_without_apply_recovery(self):
    result = run_cli(parse_args(['recover', '--deployment-id', '20260902-030000-deadbeef-0011223344556677']),
                     remote=FakeCpanel())
    self.assertEqual('recovery_planned', result.status)
    self.assertEqual([], result.remote_mutations)
```

- [ ] **Step 2: Implement the CLI contract**

```text
python tools/repair_php_deployment.py inspect --release <path> --target-url https://php.architex.co.za --document-root /home/archite4/public_html/php.architex.co.za
python tools/repair_php_deployment.py deploy --release <path> --target-url https://php.architex.co.za --document-root /home/archite4/public_html/php.architex.co.za
python tools/repair_php_deployment.py recover --deployment-id <id> [--apply-recovery]
```

Read secrets only from environment. Print one JSON object on stdout and one redacted JSON object on stderr for failures.

- [ ] **Step 3: Document configuration and rollback**

Document required environment names, the cPanel API requirement, exact inspect/deploy commands, successful output fields, retained rollback paths, and recovery commands. Do not include credential examples containing realistic values.

- [ ] **Step 4: Run local verification**

Run:

```powershell
python -m unittest discover -s tools/tests -v
php app/bin/verify-release.php dist/architex-release-test-20260902-c6ec61c
python -m compileall -q tools/cpanel_client.py tools/repair_php_deployment.py tools/deploy_php_ftps.py
git diff --check
```

Expected: tests pass, release verification passes, Python compilation succeeds, and `git diff --check` emits no output.

- [ ] **Step 5: Run authenticated read-only inspection**

Set cPanel variables in the current process without echoing them, then run `inspect`. Expected JSON confirms the account home, exact document root, PHP subdomain status, and whether stale candidate paths exist. Authentication failure is a hard stop; do not attempt deployment with guessed credentials.

- [ ] **Step 6: Commit the CLI and documentation slice**

```powershell
git add -- tools/repair_php_deployment.py tools/tests/test_repair_php_deployment.py docs/deployment/php-shared-hosting-repair.md
git commit -m "docs: add PHP deployment repair runbook"
```

### Task 4: Authenticated deployment and browser acceptance

**Files:**
- Create: `docs/evidence/2026-09-02-php-subdomain-deployment.md`

**Interfaces:**
- Consumes the verified local release and current process environment.
- Produces a live staged deployment with retained rollback paths and evidence.

- [ ] **Step 1: Run the authenticated deploy command**

Run the documented `deploy` command once. Expected output has `status: deployed`, the release commit, deployment ID, retained rollback path, active runtime path, and verified endpoint statuses. If authentication or preflight fails, stop without cutover and record the exact redacted failure phase.

- [ ] **Step 2: Verify API contracts independently**

Request `/api/v1/health`, `/api/v1/readiness`, and `/api/v1/legal-notices/signup` in separate HTTP calls. Confirm status 200, JSON content type, the expected release marker, migration readiness, mail readiness, and four signup notices.

- [ ] **Step 3: Verify the public browser flow**

Open `https://php.architex.co.za/` in Chrome. Confirm the A777 landing page renders, registration and sign-in routes open, static assets return 200, there are no refresh-request loops, and no credentials or server paths appear in page source or browser logs.

- [ ] **Step 4: Record evidence and commit**

Record the deployment ID, release commit, UTC time, commands, status codes, readiness fields, retained rollback paths, and browser findings. Exclude credentials, cookies, and tokens.

```powershell
git add -- docs/evidence/2026-09-02-php-subdomain-deployment.md
git commit -m "docs: certify PHP subdomain deployment"
```
