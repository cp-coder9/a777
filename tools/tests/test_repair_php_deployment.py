from __future__ import annotations

import json
import hashlib
import base64
import tempfile
import unittest
import urllib.parse
from datetime import datetime, timezone
from pathlib import Path

from tools.deploy_php_ftps import build_runtime_config, deployment_token, probe_source, validate_release
from tools.repair_php_deployment import (
    DeploymentConfig,
    DeploymentFailure,
    DeploymentOrchestrator,
    HttpCheck,
    parse_args,
    render_failure,
    run_cli,
)


ROOT = "/home/archite4/public_html"
DOCUMENT_ROOT = ROOT + "/php.architex.co.za"
TARGET_URL = "https://php.architex.co.za"
DEPLOYMENT_ID = "20260902-030000-deadbeef-0011223344556677"


class FakeCpanel:
    def __init__(self, shared_events: list[str], *, active_runtime: bool = True, public_root: bool = True) -> None:
        self.events = shared_events
        self.saved_content: dict[str, str] = {}
        self.paths = {ROOT}
        if active_runtime:
            self.paths.add(ROOT + "/.a777-runtime-active")
        if public_root:
            self.paths.add(DOCUMENT_ROOT)

    def account_home(self) -> str:
        self.events.append("account-home")
        return "/home/archite4"

    def _uapi(self, module, function, params):
        return {'content': "<?php return ['APP_KEY'=>'existing', 'DB_NAME'=>'existing-db', 'FILE_STORAGE_PATH'=>'existing-storage'];"}

    def exists(self, path: str) -> bool:
        return path in self.paths

    def list_files(self, path: str) -> list[dict[str, object]]:
        prefix = path.rstrip("/") + "/"
        names = {
            candidate[len(prefix):].split("/", 1)[0]
            for candidate in self.paths
            if candidate.startswith(prefix) and candidate != path
        }
        return [{"name": name} for name in sorted(names)]

    def mkdir(self, path: str, permissions: str = "0755") -> None:
        self.events.append("mkdir:" + Path(path).name)
        self.paths.add(path)

    def upload_file(self, local: Path, remote: str) -> None:
        self.events.append("upload:archive")
        self.paths.add(remote)

    def extract_archive(self, archive: str) -> None:
        self.events.append("extract:archive")
        token = archive.removeprefix(ROOT + "/.a777-package-").removesuffix(".zip")
        self.paths.add(ROOT + "/.a777-runtime-candidate-" + token)
        self.paths.add(ROOT + "/php.architex.co.za.candidate-a777-" + token)

    def save_file(self, remote: str, content: str) -> None:
        label = (
            "probe" if "preflight" in remote
            else "routing" if remote.endswith("/.htaccess")
            else "entrypoint" if remote.endswith("/index.php")
            else "runtime"
        )
        self.events.append("save:" + label)
        self.saved_content[remote] = content
        self.paths.add(remote)

    def rename(self, source: str, destination: str) -> None:
        self.events.append(f"rename:{Path(source).name}->{Path(destination).name}")
        if source not in self.paths:
            raise RuntimeError("missing rename source: " + source)
        self.paths.remove(source)
        self.paths.add(destination)

    def trash(self, path: str) -> None:
        self.events.append("trash:" + Path(path).name)
        self.paths.discard(path)


class FakeHttp:
    def __init__(self, shared_events: list[str], *, fail_live: bool = False, transient_health_failures: int = 0) -> None:
        self.events = shared_events
        self.fail_live = fail_live
        self.transient_health_failures = transient_health_failures
        self.urls: list[str] = []

    def request(self, url: str) -> HttpCheck:
        self.urls.append(url)
        path = urllib.parse.urlsplit(url).path
        if "preflight" in path:
            self.events.append("get:preflight")
            return HttpCheck(
                url=url,
                final_url=url,
                status=200,
                content_type="application/json",
                body=json.dumps({
                    "ok": True,
                    "php": True,
                    "phpVersionId": 80300,
                    "database": True,
                    "migrations": True,
                    "mail": True,
                }).encode(),
            )
        self.events.append("get:" + (path or "/"))
        if path == "/":
            return HttpCheck(url, url, 200, "text/html", b"Architex professional operating system")
        if path == "/build-info.json":
            return HttpCheck(url, url, 200, "application/json", json.dumps({
                "format": "architex-build-info-v1",
                "sourceCommit": "c6ec61c1d7b11ca9991277156a8a7227c764b7b4",
                "php": "8.3",
                "apiMode": "php-shared-hosting",
            }).encode())
        if path == "/api/v1/health" and self.transient_health_failures > 0:
            self.transient_health_failures -= 1
            return HttpCheck(url, url, 404, "application/json", b'{"error":"not_found"}')
        status = 503 if self.fail_live and path == "/api/v1/readiness" else 200
        return HttpCheck(url, url, status, "application/json", b'{"ok":true,"data":{"status":"ready","components":{"database":{"status":"ready"},"migrations":{"status":"ready"},"jobs":{"status":"ready"},"mail":{"status":"ready"}}}}')


def make_release(root: Path) -> Path:
    release = root / "release"
    (release / "public").mkdir(parents=True)
    (release / "release.json").write_text(json.dumps({"format":"architex-release-v1","environment":"production","sourceDirty":False,"sourceSnapshotSha256":"b"*64,"sourceCommit": "c6ec61c1d7b11ca9991277156a8a7227c764b7b4"}))
    (release / "public" / "index.php").write_text("<?php")
    (release / "public" / ".htaccess").write_text("RewriteEngine On\nRewriteRule ^ index.php [QSA,L]\n")
    files={p.relative_to(release).as_posix():{'sha256':hashlib.sha256(p.read_bytes()).hexdigest(),'bytes':p.stat().st_size} for p in release.rglob('*') if p.is_file()}
    (release / 'release-manifest.json').write_text(json.dumps({'format':'architex-release-manifest-v1','files':files}))
    return release


def archive_builder(release: Path, destination: Path, runtime: str, public: str, active: str) -> tuple[int, int]:
    destination.write_bytes(b"PK-test")
    return 3, destination.stat().st_size


def deployment(release: Path, remote: FakeCpanel, http: FakeHttp) -> DeploymentOrchestrator:
    config = DeploymentConfig(
        release=release,
        target_url=TARGET_URL,
        document_root=DOCUMENT_ROOT,
        account_home="/home/archite4",
        deployment_id=DEPLOYMENT_ID,
    )
    return DeploymentOrchestrator(
        config,
        remote,
        http,
        runtime_content=b"<?php return [];",
        archive_builder=archive_builder,
        sleeper=lambda _seconds: None,
    )


class ReleaseHelperTest(unittest.TestCase):
    def test_release_validation_requires_manifest_and_public_entrypoint(self) -> None:
        with tempfile.TemporaryDirectory() as temporary:
            release = make_release(Path(temporary))
            validate_release(release)
            (release / "release-manifest.json").unlink()
            with self.assertRaisesRegex(RuntimeError, "incomplete"):
                validate_release(release)

    def test_deployment_token_is_scoped_to_release_and_time(self) -> None:
        with tempfile.TemporaryDirectory() as temporary:
            release = make_release(Path(temporary))
            token = deployment_token(release, datetime(2026, 9, 2, 3, 0, tzinfo=timezone.utc))
        self.assertRegex(token, r"^20260902-030000-[0-9a-f]{8}-[0-9a-f]{16}$")

    def test_preflight_probe_checks_php_database_migrations_and_mail(self) -> None:
        source = probe_source(".a777-runtime-candidate-token").decode("utf-8")
        for marker in ("PHP_VERSION_ID", "MigrationRunner", "'database'", "'migrations'", "'mail'"):
            with self.subTest(marker=marker):
                self.assertIn(marker, source)


class DeploymentOrchestratorTest(unittest.TestCase):
    def test_each_rename_before_and_after_effect_rolls_back(self):
        for fail_at in range(1, 5):
            for after in (False, True):
                with self.subTest(rename=fail_at, after=after), tempfile.TemporaryDirectory() as temporary:
                    class Fault(FakeCpanel):
                        calls = 0
                        def rename(self, source, destination):
                            self.calls += 1
                            if self.calls == fail_at and not after:
                                raise RuntimeError('lost response')
                            super().rename(source, destination)
                            if self.calls == fail_at and after:
                                raise RuntimeError('lost response')
                    remote = Fault([])
                    owner = deployment(make_release(Path(temporary)), remote, FakeHttp([]))
                    with self.assertRaises(DeploymentFailure):
                        owner.deploy()
                    self.assertIn(DOCUMENT_ROOT, remote.paths)
                    self.assertIn(ROOT + '/.a777-runtime-active', remote.paths)
                    self.assertNotIn(owner.paths.prior_runtime, remote.paths)
                    self.assertNotIn(owner.paths.rollback_public, remote.paths)
                    self.assertEqual([], owner.completed_renames)

    def test_cleanup_failure_cannot_report_success(self):
        class Fault(FakeCpanel):
            def rename(self, source, destination):
                raise RuntimeError('persistent unavailable')
        with tempfile.TemporaryDirectory() as temporary:
            remote = Fault([])
            owner = deployment(make_release(Path(temporary)), remote, FakeHttp([]))
            remote.paths.discard(DOCUMENT_ROOT)
            remote.paths.add(owner.paths.rollback_public)
            owner.completed_renames.append((DOCUMENT_ROOT, owner.paths.rollback_public))
            with self.assertRaisesRegex(DeploymentFailure, 'retain journal'):
                owner._rollback()
            self.assertEqual([(DOCUMENT_ROOT, owner.paths.rollback_public)], owner.completed_renames)

    def test_cleanup_failure_reports_recovery_required(self):
        class Fault(FakeCpanel):
            def trash(self, path):
                if path.endswith('.zip'):
                    return
                super().trash(path)
        with tempfile.TemporaryDirectory() as temporary:
            with self.assertRaisesRegex(DeploymentFailure, 'cleanup unverified'):
                deployment(make_release(Path(temporary)), Fault([]), FakeHttp([])).deploy()

    def test_wrong_readiness_json_rolls_back(self):
        class Wrong(FakeHttp):
            def request(self, url):
                check = super().request(url)
                if '/api/v1/readiness' in url:
                    return HttpCheck(url, url, 200, 'application/json', b'{"ok":true}')
                return check
        with tempfile.TemporaryDirectory() as temporary:
            remote = FakeCpanel([])
            with self.assertRaisesRegex(DeploymentFailure, 'not ready'):
                deployment(make_release(Path(temporary)), remote, Wrong([])).deploy()
            self.assertIn(DOCUMENT_ROOT, remote.paths)

    def test_transient_live_routing_failure_is_retried(self) -> None:
        with tempfile.TemporaryDirectory() as temporary:
            events: list[str] = []
            release = make_release(Path(temporary))
            remote = FakeCpanel(events)
            http = FakeHttp(events, transient_health_failures=2)

            result = deployment(release, remote, http).deploy()

        self.assertEqual("deployed", result.status)
        self.assertEqual(3, sum(1 for url in http.urls if "/api/v1/health?" in url))

    def test_preflight_happens_before_first_rename(self) -> None:
        with tempfile.TemporaryDirectory() as temporary:
            events: list[str] = []
            release = make_release(Path(temporary))
            remote = FakeCpanel(events)
            http = FakeHttp(events)

            result = deployment(release, remote, http).deploy()

        self.assertEqual("deployed", result.status)
        first_rename = next(index for index, event in enumerate(events) if event.startswith("rename:"))
        final_rename = max(index for index, event in enumerate(events) if event.startswith("rename:"))
        self.assertLess(events.index("save:probe"), events.index("get:preflight"))
        self.assertLess(events.index("get:preflight"), first_rename)
        self.assertGreater(events.index("save:routing"), final_rename)
        self.assertGreater(events.index("save:entrypoint"), final_rename)
        routing_trash = events.index("trash:.htaccess")
        self.assertGreater(routing_trash, final_rename)
        self.assertLess(routing_trash, events.index("save:routing"))
        self.assertLess(events.index("save:routing"), events.index("get:/"))
        self.assertIn(DEPLOYMENT_ID, remote.saved_content[DOCUMENT_ROOT + "/.htaccess"])
        self.assertIn(DEPLOYMENT_ID, remote.saved_content[DOCUMENT_ROOT + "/index.php"])
        self.assertIn("/.a777-runtime-active/public/index.php", remote.saved_content[DOCUMENT_ROOT + "/index.php"])
        live_urls = [url for url in http.urls if "preflight" not in url]
        self.assertTrue(all("__a777_release=c6ec61c1d7b11ca" in url for url in live_urls))

    def test_live_failure_restores_public_and_runtime_in_reverse_order(self) -> None:
        with tempfile.TemporaryDirectory() as temporary:
            events: list[str] = []
            release = make_release(Path(temporary))
            remote = FakeCpanel(events)
            http = FakeHttp(events, fail_live=True)

            with self.assertRaises(DeploymentFailure):
                deployment(release, remote, http).deploy()

        self.assertIn(DOCUMENT_ROOT, remote.paths)
        self.assertIn(ROOT + "/.a777-runtime-active", remote.paths)
        probe_paths = [path for path in remote.paths if "preflight" in path]
        self.assertEqual([], probe_paths)
        forward = events.index("rename:php.architex.co.za.candidate-a777-" + DEPLOYMENT_ID + "->php.architex.co.za")
        backward = events.index("rename:php.architex.co.za->php.architex.co.za.candidate-a777-" + DEPLOYMENT_ID, forward)
        self.assertGreater(backward, forward)


class DeploymentCliTest(unittest.TestCase):
    def test_runtime_config_includes_optional_nvidia_key_for_wingman(self) -> None:
        environment = {
            "A777_DB_HOST": "database.example.test",
            "A777_DB_NAME": "architex",
            "A777_DB_USER": "architex",
            "A777_DB_PASSWORD": "database-secret",
            "A777_SMTP_HOST": "mail.example.test",
            "A777_SMTP_USERNAME": "mailer@example.test",
            "A777_SMTP_PASSWORD": "mail-secret",
            "A777_SMTP_FROM_EMAIL": "mailer@example.test",
            "A777_NVIDIA_API_KEY": "nvidia-secret",
            "A777_APP_KEY": base64.b64encode(b'x'*32).decode(),
            "A777_BACKUP_ARCHIVE_PASSWORD": "unit-backup-password-only",
        }

        runtime = build_runtime_config(TARGET_URL, ROOT + "/.a777-storage", environment).decode("utf-8")

        self.assertIn("'NVIDIA_API_KEY' => 'nvidia-secret'", runtime)

    def test_inspect_parses_without_private_runtime_variables(self) -> None:
        with tempfile.TemporaryDirectory() as temporary:
            release = make_release(Path(temporary))
            args = parse_args([
                "inspect",
                "--release", str(release),
                "--target-url", TARGET_URL,
                "--document-root", DOCUMENT_ROOT,
            ])
        self.assertEqual("inspect", args.command)

    def test_inspect_is_read_only_and_reports_current_endpoint_state(self) -> None:
        with tempfile.TemporaryDirectory() as temporary:
            release = make_release(Path(temporary))
            remote = FakeCpanel([])
            args = parse_args([
                "inspect",
                "--release", str(release),
                "--target-url", TARGET_URL,
                "--document-root", DOCUMENT_ROOT,
                "--deployment-id", DEPLOYMENT_ID,
            ])

            result = run_cli(args, environment={}, remote=remote, http=FakeHttp([]))

        self.assertEqual("inspected", result.status)
        self.assertTrue(result.observed["documentRootExists"])
        self.assertEqual(200, result.endpoints["/api/v1/health"])
        self.assertFalse(any(event.startswith(("rename:", "save:", "upload:", "trash:")) for event in remote.events))

    def test_deploy_preserves_private_runtime_without_environment_variables(self) -> None:
        with tempfile.TemporaryDirectory() as temporary:
            release = make_release(Path(temporary))
            args = parse_args([
                "deploy",
                "--release", str(release),
                "--target-url", TARGET_URL,
                "--document-root", DOCUMENT_ROOT,
                "--deployment-id", DEPLOYMENT_ID,
            ])
            remote = FakeCpanel([])
            result = run_cli(args, environment={"A777_APP_KEY": "wrong-but-wellformed"}, remote=remote, http=FakeHttp([]))
            self.assertEqual('deployed', result.status)
            self.assertIn("'APP_KEY'=>'existing'", next(v for k,v in remote.saved_content.items() if k.endswith('config/runtime.php')))

    def test_failure_output_is_one_redacted_json_object(self) -> None:
        output = render_failure(DeploymentFailure("password=secret-value"), secrets=("secret-value",))
        payload = json.loads(output)
        self.assertEqual("failed", payload["status"])
        self.assertNotIn("secret-value", output)
        self.assertNotIn("\n", output)

    def test_recover_is_dry_run_without_apply_recovery(self) -> None:
        remote = FakeCpanel([])
        args = parse_args([
            "recover",
            "--deployment-id", DEPLOYMENT_ID,
            "--target-url", TARGET_URL,
            "--document-root", DOCUMENT_ROOT,
        ])

        result = run_cli(args, environment={}, remote=remote, http=FakeHttp([]))

        self.assertEqual("recovery_planned", result.status)
        self.assertEqual([], result.remote_mutations)
        self.assertFalse(any(event.startswith("rename:") for event in remote.events))


class DeploymentStateMachineAdditionalTest(unittest.TestCase):
    def test_failed_preflight_never_renames(self) -> None:
        class FailedPreflight(FakeHttp):
            def request(self, url: str) -> HttpCheck:
                check = super().request(url)
                if "preflight" in url:
                    return HttpCheck(url, url, 503, "application/json", b'{"ok":false}')
                return check

        with tempfile.TemporaryDirectory() as temporary:
            events: list[str] = []
            release = make_release(Path(temporary))
            with self.assertRaises(DeploymentFailure):
                deployment(release, FakeCpanel(events), FailedPreflight(events)).deploy()
        self.assertFalse(any(event.startswith("rename:") for event in events))

    def test_failure_result_redacts_all_configured_secrets(self) -> None:
        with tempfile.TemporaryDirectory() as temporary:
            events: list[str] = []
            orchestrator = deployment(make_release(Path(temporary)), FakeCpanel(events), FakeHttp(events))
            result = orchestrator.failure_result(RuntimeError("password=pw-secret"), secrets=("pw-secret",))
        encoded = json.dumps(result.to_dict())
        self.assertEqual("failed", result.status)
        self.assertNotIn("pw-secret", encoded)
        self.assertIn("[REDACTED]", encoded)

    def test_recovery_plan_is_exact_and_apply_restores_retained_paths(self) -> None:
        with tempfile.TemporaryDirectory() as temporary:
            events: list[str] = []
            release = make_release(Path(temporary))
            remote = FakeCpanel(events)
            orchestrator = deployment(release, remote, FakeHttp(events))
            orchestrator.deploy()
            plan = orchestrator.recovery_plan(DEPLOYMENT_ID)

            self.assertTrue(plan.observed["rollback_public"])
            result = orchestrator.apply_recovery(plan)

        self.assertEqual("recovered", result.status)
        self.assertIn(DOCUMENT_ROOT, remote.paths)
        self.assertIn(ROOT + "/.a777-runtime-active", remote.paths)


if __name__ == "__main__":
    unittest.main()
