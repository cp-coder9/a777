from __future__ import annotations

import json
import tempfile
import unittest
from datetime import datetime, timezone
from pathlib import Path

from tools.deploy_php_ftps import deployment_token, probe_source, validate_release
from tools.repair_php_deployment import (
    DeploymentConfig,
    DeploymentFailure,
    DeploymentOrchestrator,
    HttpCheck,
)


ROOT = "/home/archite4/public_html"
DOCUMENT_ROOT = ROOT + "/php.architex.co.za"
TARGET_URL = "https://php.architex.co.za"
DEPLOYMENT_ID = "20260902-030000-deadbeef-0011223344556677"


class FakeCpanel:
    def __init__(self, shared_events: list[str], *, active_runtime: bool = True, public_root: bool = True) -> None:
        self.events = shared_events
        self.paths = {ROOT}
        if active_runtime:
            self.paths.add(ROOT + "/.a777-runtime-active")
        if public_root:
            self.paths.add(DOCUMENT_ROOT)

    def account_home(self) -> str:
        self.events.append("account-home")
        return "/home/archite4"

    def exists(self, path: str) -> bool:
        return path in self.paths

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
        label = "probe" if "preflight" in remote else "runtime"
        self.events.append("save:" + label)
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
    def __init__(self, shared_events: list[str], *, fail_live: bool = False) -> None:
        self.events = shared_events
        self.fail_live = fail_live

    def request(self, url: str) -> HttpCheck:
        path = url.removeprefix(TARGET_URL)
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
            return HttpCheck(url, url, 200, "text/html", b"A777 c6ec61c1d7b11ca9991277156a8a7227c764b7b4")
        status = 503 if self.fail_live and path == "/api/v1/readiness" else 200
        return HttpCheck(url, url, status, "application/json", b'{"ok":true}')


def make_release(root: Path) -> Path:
    release = root / "release"
    (release / "public").mkdir(parents=True)
    (release / "release.json").write_text(json.dumps({"sourceCommit": "c6ec61c1d7b11ca9991277156a8a7227c764b7b4"}))
    (release / "release-manifest.json").write_text(json.dumps({"format": "test"}))
    (release / "public" / "index.php").write_text("<?php")
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
    def test_preflight_happens_before_first_rename(self) -> None:
        with tempfile.TemporaryDirectory() as temporary:
            events: list[str] = []
            release = make_release(Path(temporary))
            remote = FakeCpanel(events)
            http = FakeHttp(events)

            result = deployment(release, remote, http).deploy()

        self.assertEqual("deployed", result.status)
        first_rename = next(index for index, event in enumerate(events) if event.startswith("rename:"))
        self.assertLess(events.index("save:probe"), events.index("get:preflight"))
        self.assertLess(events.index("get:preflight"), first_rename)

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
