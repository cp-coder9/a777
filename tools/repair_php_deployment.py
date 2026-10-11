#!/usr/bin/env python3
"""Fail-closed staged deployment repair for A777 on cPanel shared hosting."""

from __future__ import annotations

import argparse
import json
import os
import posixpath
import re
import ssl
import sys
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
from dataclasses import asdict, dataclass, field
from pathlib import Path
from typing import Callable, Mapping, Protocol

if __package__ in (None, ""):
    sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from tools.cpanel_client import CpanelClient, CpanelConfig, redact_text
from tools.deploy_php_ftps import (
    build_runtime_config,
    build_transport_archive,
    deployment_token,
    probe_source,
    validate_release,
    readiness_is_ready,
)


DEPLOYMENT_ID_PATTERN = re.compile(r"^\d{8}-\d{6}-[0-9a-f]{8}-[0-9a-f]{16}$")
LIVE_JSON_PATHS = (
    "/api/v1/health",
    "/api/v1/readiness",
    "/api/v1/legal-notices/signup",
)


class DeploymentFailure(RuntimeError):
    """A deployment stopped or rolled back without exposing credentials."""


@dataclass(frozen=True)
class HttpCheck:
    url: str
    final_url: str
    status: int
    content_type: str
    body: bytes

    def json(self) -> dict[str, object]:
        try:
            payload = json.loads(self.body.decode("utf-8"))
        except (UnicodeDecodeError, json.JSONDecodeError) as error:
            raise DeploymentFailure(f"Endpoint {self.url} did not return valid JSON.") from error
        if not isinstance(payload, dict):
            raise DeploymentFailure(f"Endpoint {self.url} did not return a JSON object.")
        return payload


class HttpVerifier(Protocol):
    def request(self, url: str) -> HttpCheck:
        """Fetch one exact deployment endpoint without cross-host redirects."""


class SameHostRedirectHandler(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, request, fp, code, message, headers, new_url):  # type: ignore[no-untyped-def]
        source = urllib.parse.urlsplit(request.full_url)
        destination = urllib.parse.urlsplit(new_url)
        if (source.scheme, source.netloc) != (destination.scheme, destination.netloc):
            raise urllib.error.HTTPError(new_url, code, "Cross-host redirect refused", headers, fp)
        return super().redirect_request(request, fp, code, message, headers, new_url)


class UrllibHttpVerifier:
    def __init__(self, timeout: float = 45.0, ca_file: str | None = None) -> None:
        context = ssl.create_default_context(cafile=ca_file)
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPSHandler(context=context),
            SameHostRedirectHandler(),
        )
        self.timeout = timeout

    def request(self, url: str) -> HttpCheck:
        request = urllib.request.Request(url, headers={"User-Agent": "Architex-release-verifier/2"})
        try:
            with self.opener.open(request, timeout=self.timeout) as response:
                return HttpCheck(
                    url=url,
                    final_url=response.geturl(),
                    status=response.status,
                    content_type=response.headers.get_content_type(),
                    body=response.read(),
                )
        except urllib.error.HTTPError as error:
            return HttpCheck(
                url=url,
                final_url=error.geturl(),
                status=error.code,
                content_type=error.headers.get_content_type() if error.headers else "",
                body=error.read(),
            )


@dataclass(frozen=True)
class DeploymentConfig:
    release: Path
    target_url: str
    document_root: str
    account_home: str
    deployment_id: str | None = None

    def __post_init__(self) -> None:
        release = self.release.resolve()
        target_url = self.target_url.rstrip("/")
        parsed = urllib.parse.urlsplit(target_url)
        if parsed.scheme != "https" or not parsed.netloc or parsed.path:
            raise DeploymentFailure("Target URL must be an HTTPS origin without a path.")
        if not self.document_root.startswith("/") or posixpath.normpath(self.document_root) == "/":
            raise DeploymentFailure("Document root must be an absolute contained path.")
        identifier = self.deployment_id or deployment_token(release)
        if not DEPLOYMENT_ID_PATTERN.fullmatch(identifier):
            raise DeploymentFailure("Deployment ID does not match the required format.")
        object.__setattr__(self, "release", release)
        object.__setattr__(self, "target_url", target_url)
        object.__setattr__(self, "document_root", posixpath.normpath(self.document_root))
        object.__setattr__(self, "account_home", posixpath.normpath(self.account_home))
        object.__setattr__(self, "deployment_id", identifier)


@dataclass(frozen=True)
class DeploymentPaths:
    parent: str
    document_root: str
    active_runtime: str
    candidate_runtime: str
    prior_runtime: str
    storage: str
    candidate_public: str
    rollback_public: str
    archive: str
    probe: str

    @classmethod
    def for_id(cls, config: DeploymentConfig, deployment_id: str) -> "DeploymentPaths":
        if not DEPLOYMENT_ID_PATTERN.fullmatch(deployment_id):
            raise DeploymentFailure("Recovery deployment ID does not match the required format.")
        parent = posixpath.dirname(config.document_root)
        target_name = posixpath.basename(config.document_root)
        return cls(
            parent=parent,
            document_root=config.document_root,
            active_runtime=posixpath.join(parent, ".a777-runtime-active"),
            candidate_runtime=posixpath.join(parent, f".a777-runtime-candidate-{deployment_id}"),
            prior_runtime=posixpath.join(parent, f".a777-runtime-prior-{deployment_id}"),
            storage=posixpath.join(parent, ".a777-storage"),
            candidate_public=posixpath.join(parent, f"{target_name}.candidate-a777-{deployment_id}"),
            rollback_public=posixpath.join(parent, f"{target_name}.pre-a777-{deployment_id}"),
            archive=posixpath.join(parent, f".a777-package-{deployment_id}.zip"),
            probe=posixpath.join(config.document_root, f"__a777_preflight_{deployment_id}.php"),
        )

    def recovery_targets(self) -> dict[str, str]:
        return {
            "document_root": self.document_root,
            "rollback_public": self.rollback_public,
            "candidate_public": self.candidate_public,
            "active_runtime": self.active_runtime,
            "prior_runtime": self.prior_runtime,
            "candidate_runtime": self.candidate_runtime,
        }


@dataclass(frozen=True)
class DeploymentResult:
    status: str
    deployment_id: str
    release_commit: str = ""
    rollback_public: str = ""
    prior_runtime: str = ""
    endpoints: dict[str, int] = field(default_factory=dict)
    observed: dict[str, object] = field(default_factory=dict)
    remote_mutations: list[str] = field(default_factory=list)
    detail: str = ""

    def to_dict(self) -> dict[str, object]:
        return asdict(self)


@dataclass(frozen=True)
class RecoveryPlan:
    deployment_id: str
    paths: DeploymentPaths
    observed: dict[str, bool]

    def require_unambiguous(self) -> None:
        required = ("document_root", "rollback_public", "active_runtime", "prior_runtime")
        missing = [name for name in required if not self.observed.get(name)]
        collisions = [name for name in ("candidate_public", "candidate_runtime") if self.observed.get(name)]
        if missing or collisions:
            raise DeploymentFailure(
                "Recovery state is ambiguous; missing=" + ",".join(missing or ("none",))
                + "; collisions=" + ",".join(collisions or ("none",))
            )


ArchiveBuilder = Callable[[Path, Path, str, str, str], tuple[int, int]]
Sleeper = Callable[[float], None]


class DeploymentOrchestrator:
    def __init__(
        self,
        config: DeploymentConfig,
        remote: CpanelClient,
        http: HttpVerifier,
        *,
        runtime_content: bytes,
        archive_builder: ArchiveBuilder = build_transport_archive,
        sleeper: Sleeper = time.sleep,
        live_attempts: int = 6,
    ) -> None:
        self.config = config
        self.remote = remote
        self.http = http
        self.runtime_content = runtime_content
        self.archive_builder = archive_builder
        self.sleeper = sleeper
        self.live_attempts = live_attempts
        self.paths = DeploymentPaths.for_id(config, str(config.deployment_id))
        self.completed_renames: list[tuple[str, str]] = []
        self.endpoint_statuses: dict[str, int] = {}
        self.release_commit = ""
        self._temporary: tempfile.TemporaryDirectory[str] | None = None

    def deploy(self) -> DeploymentResult:
        stage = "verify_local_release"
        try:
            # Enumerated local stages; capture the failure before recovery runs.
            for stage, action in (
                ("verify_local_release", self._verify_local_release),
                ("verify_account_and_paths", self._verify_account_and_paths),
                ("stage_archive", self._stage_archive),
                ("write_private_runtime", self._write_private_runtime),
                ("preflight", self._run_preflight),
                ("cutover", self._cutover),
                ("refresh_routing", self._refresh_routing),
                ("verify_live", self._verify_live),
            ):
                action()
        except Exception as error:
            rollback_error: Exception | None = None
            if self.completed_renames:
                try:
                    self._rollback()
                except Exception as caught:
                    rollback_error = caught
            cleanup_error = None
            try:
                self._cleanup_temporary_files()
            except DeploymentFailure as caught:
                cleanup_error = caught
            detail = f"stage={stage} {error}"
            if rollback_error is not None:
                detail += f" Rollback also failed: {rollback_error}"
                detail += " Pending recovery renames: " + json.dumps(self.completed_renames)
            if cleanup_error is not None:
                detail += f" Cleanup also failed: {cleanup_error}"
            raise DeploymentFailure(detail) from error
        self._cleanup_temporary_files()
        return DeploymentResult(
            status="deployed",
            deployment_id=str(self.config.deployment_id),
            release_commit=self.release_commit,
            rollback_public=self.paths.rollback_public,
            prior_runtime=self.paths.prior_runtime,
            endpoints=dict(self.endpoint_statuses),
        )

    def _verify_local_release(self) -> None:
        validate_release(self.config.release)
        try:
            metadata = json.loads((self.config.release / "release.json").read_text(encoding="utf-8"))
        except (OSError, json.JSONDecodeError) as error:
            raise DeploymentFailure("Release metadata is not valid JSON.") from error
        commit = metadata.get("sourceCommit") if isinstance(metadata, dict) else None
        if not isinstance(commit, str) or not re.fullmatch(r"[0-9a-f]{7,64}", commit):
            raise DeploymentFailure("Release metadata does not contain a valid source commit.")
        self.release_commit = commit

    def _verify_account_and_paths(self) -> None:
        if self.remote.account_home() != self.config.account_home:
            raise DeploymentFailure("Authenticated cPanel account home does not match the deployment configuration.")
        expected_root = posixpath.join(self.config.account_home, "public_html")
        if self.paths.parent != expected_root:
            raise DeploymentFailure("Document root must be a direct child of the authenticated public_html root.")
        if not self.remote.exists(self.paths.document_root):
            raise DeploymentFailure("The configured document root does not exist for preflight.")
        collisions = (
            self.paths.candidate_runtime,
            self.paths.prior_runtime,
            self.paths.candidate_public,
            self.paths.rollback_public,
            self.paths.archive,
            self.paths.probe,
        )
        if any(self.remote.exists(path) for path in collisions):
            raise DeploymentFailure("A deployment candidate or rollback path already exists for this deployment ID.")
        if not self.remote.exists(self.paths.storage):
            self.remote.mkdir(self.paths.storage)

    def _stage_archive(self) -> None:
        self._temporary = tempfile.TemporaryDirectory(prefix="a777-cpanel-")
        local_archive = Path(self._temporary.name) / posixpath.basename(self.paths.archive)
        self.archive_builder(
            self.config.release,
            local_archive,
            posixpath.basename(self.paths.candidate_runtime),
            posixpath.basename(self.paths.candidate_public),
            posixpath.basename(self.paths.active_runtime),
        )
        self.remote.upload_file(local_archive, self.paths.archive)
        self.remote.extract_archive(self.paths.archive)
        if not self.remote.exists(self.paths.candidate_runtime) or not self.remote.exists(self.paths.candidate_public):
            raise DeploymentFailure("cPanel extraction did not create both release candidates.")

    def _write_private_runtime(self) -> None:
        target = posixpath.join(self.paths.candidate_runtime, "config", "runtime.php")
        data = self.remote._uapi("Fileman", "get_file_content", {
            "dir": posixpath.join(self.paths.active_runtime, "config"), "file": "runtime.php"})
        content = data.get("content") if isinstance(data, dict) else None
        if not isinstance(content, str) or not content.startswith("<?php"):
            raise DeploymentFailure("Existing private runtime configuration unavailable; refusing replacement.")
        self.remote.save_file(target, content)

    def _run_preflight(self) -> None:
        source = probe_source(posixpath.basename(self.paths.candidate_runtime)).decode("utf-8")
        self.remote.save_file(self.paths.probe, source)
        try:
            check = self.http.request(self.config.target_url + "/" + posixpath.basename(self.paths.probe))
            try:
                payload = check.json()
            except (DeploymentFailure, ValueError):
                payload = {}
            if not isinstance(payload, dict):
                payload = {}
            required_true = ("ok", "php", "database", "migrations", "mail")
            valid = check.status == 200 and all(payload.get(name) is True for name in required_true)
            valid = valid and type(payload.get("phpVersionId")) is int and payload["phpVersionId"] >= 80300
            if not valid:
                diagnostic = {"httpStatus": check.status} if type(check.status) is int else {}
                for name in ("ok", "php", "bootstrap", "config", "database", "migrations", "mail"):
                    if type(payload.get(name)) is bool:
                        diagnostic[name] = payload[name]
                for name in ("phpVersionId", "pendingCount"):
                    if type(payload.get(name)) is int and payload[name] >= 0:
                        diagnostic[name] = payload[name]
                reasons = {"ready", "bootstrap_failed", "config_failed", "database_failed", "migrations_failed",
                           "mail_failed", "php_unsupported", "migrations_pending", "mail_not_ready"}
                reason = payload.get("reason")
                diagnostic["reason"] = reason if isinstance(reason, str) and reason in reasons else "invalid_response"
                raise DeploymentFailure("Private runtime preflight did not confirm PHP 8.3, database, migrations, and mail readiness. "
                                        + json.dumps(diagnostic, sort_keys=True))
        finally:
            if self.remote.exists(self.paths.probe):
                self.remote.trash(self.paths.probe)
            if self.remote.exists(self.paths.probe):
                raise DeploymentFailure('Preflight helper cleanup unverified; refusing cutover.')

    def _rename(self, source: str, destination: str) -> None:
        self.completed_renames.append((source, destination))
        self.remote.rename(source, destination)

    def _cutover(self) -> None:
        if self.remote.exists(self.paths.active_runtime):
            self._rename(self.paths.active_runtime, self.paths.prior_runtime)
        self._rename(self.paths.candidate_runtime, self.paths.active_runtime)
        self._rename(self.paths.document_root, self.paths.rollback_public)
        self._rename(self.paths.candidate_public, self.paths.document_root)

    def _refresh_routing(self) -> None:
        shim = (
            "<?php\n\ndeclare(strict_types=1);\n\n"
            f"// A777 deployment {self.config.deployment_id}\n"
            f"require dirname(__DIR__) . '/{posixpath.basename(self.paths.active_runtime)}/public/index.php';\n"
        )
        rules = (self.config.release / "public" / ".htaccess").read_text(encoding="utf-8")
        rules = rules.rstrip() + f"\n# A777 deployment {self.config.deployment_id}\n"
        self.remote.save_file(posixpath.join(self.paths.document_root, "index.php"), shim)
        live_rules = posixpath.join(self.paths.document_root, ".htaccess")
        self.remote.trash(live_rules)
        self.remote.save_file(live_rules, rules)

    def _verify_live(self) -> None:
        last_error: DeploymentFailure | None = None
        for attempt in range(1, self.live_attempts + 1):
            try:
                self._verify_live_once(attempt)
                return
            except DeploymentFailure as error:
                last_error = error
                if attempt < self.live_attempts:
                    self.sleeper(2.0)
        assert last_error is not None
        raise last_error

    def _verify_live_once(self, attempt: int) -> None:
        cache_buster = urllib.parse.urlencode({
            "__a777_release": self.release_commit[:16],
            "attempt": str(attempt),
        })
        live_url = lambda path: self.config.target_url + path + "?" + cache_buster
        landing = self.http.request(live_url("/"))
        self.endpoint_statuses["/"] = landing.status
        if landing.status != 200 or "html" not in landing.content_type.lower():
            raise DeploymentFailure("Live landing page did not return HTML with HTTP 200.")
        build = self.http.request(live_url("/build-info.json"))
        self.endpoint_statuses["/build-info.json"] = build.status
        build_payload = build.json() if build.status == 200 and "json" in build.content_type.lower() else {}
        if (
            build_payload.get("format") != "architex-build-info-v1"
            or build_payload.get("sourceCommit") != self.release_commit
            or build_payload.get("php") != "8.3"
            or build_payload.get("apiMode") != "php-shared-hosting"
        ):
            raise DeploymentFailure("Live build metadata did not match the staged PHP release.")
        for path in LIVE_JSON_PATHS:
            check = self.http.request(live_url(path))
            self.endpoint_statuses[path] = check.status
            if check.status != 200 or "json" not in check.content_type.lower():
                raise DeploymentFailure(f"Live validation failed for {path} with HTTP {check.status}.")
            payload = check.json()
            if path == '/api/v1/readiness' and not readiness_is_ready(payload.get('data')):
                raise DeploymentFailure('Live readiness components are not ready.')

    def _rollback(self) -> None:
        while self.completed_renames:
            source, destination = self.completed_renames[-1]
            if self.remote.exists(destination) and not self.remote.exists(source):
                try:
                    self.remote.rename(destination, source)
                except Exception:
                    if not self.remote.exists(source) or self.remote.exists(destination):
                        raise DeploymentFailure("Rollback incomplete; retain journal and recovery paths.") from None
            elif not self.remote.exists(source) or self.remote.exists(destination):
                raise DeploymentFailure("Rollback paths ambiguous; retain journal and recovery paths.")
            if not self.remote.exists(source) or self.remote.exists(destination):
                raise DeploymentFailure("Rollback verification failed; retain recovery paths.")
            self.completed_renames.pop()

    def _cleanup_temporary_files(self) -> None:
        errors = []
        for path in (self.paths.probe, self.paths.archive):
            try:
                if self.remote.exists(path):
                    self.remote.trash(path)
                if self.remote.exists(path):
                    errors.append(path)
            except Exception:
                errors.append(path)
        if self._temporary is not None:
            self._temporary.cleanup()
            self._temporary = None
        if errors:
            raise DeploymentFailure("Temporary cleanup unverified; recovery required: " + ", ".join(errors))

    def failure_result(self, error: Exception, secrets: tuple[str, ...] = ()) -> DeploymentResult:
        return DeploymentResult(
            status="failed",
            deployment_id=str(self.config.deployment_id),
            release_commit=self.release_commit,
            observed={'pendingRecoveryRenames': list(self.completed_renames)},
            detail=redact_text(str(error), secrets),
        )

    def recovery_plan(self, deployment_id: str) -> RecoveryPlan:
        paths = DeploymentPaths.for_id(self.config, deployment_id)
        observed = {name: self.remote.exists(path) for name, path in paths.recovery_targets().items()}
        return RecoveryPlan(deployment_id=deployment_id, paths=paths, observed=observed)

    def apply_recovery(self, plan: RecoveryPlan) -> DeploymentResult:
        expected = DeploymentPaths.for_id(self.config, plan.deployment_id)
        if plan.paths != expected:
            raise DeploymentFailure("Recovery plan paths do not match the configured target root.")
        plan.require_unambiguous()
        try:
            self._rename(plan.paths.document_root, plan.paths.candidate_public)
            self._rename(plan.paths.rollback_public, plan.paths.document_root)
            self._rename(plan.paths.active_runtime, plan.paths.candidate_runtime)
            self._rename(plan.paths.prior_runtime, plan.paths.active_runtime)
        except Exception:
            self._rollback()
            raise DeploymentFailure('Recovery interrupted; initial paths restored and verified.') from None
        if not self.remote.exists(plan.paths.document_root) or not self.remote.exists(plan.paths.active_runtime):
            raise DeploymentFailure("Recovered deployment paths could not be verified.")
        return DeploymentResult(
            status="recovered",
            deployment_id=plan.deployment_id,
            rollback_public=plan.paths.rollback_public,
            prior_runtime=plan.paths.prior_runtime,
            remote_mutations=[
                "active public moved to candidate",
                "rollback public restored",
                "active runtime moved to candidate",
                "prior runtime restored",
            ],
        )


def parse_args(arguments: list[str] | None = None) -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Repair the A777 PHP deployment through cPanel APIs.")
    commands = parser.add_subparsers(dest="command", required=True)

    def add_target_options(command: argparse.ArgumentParser, *, require_release: bool) -> None:
        command.add_argument("--release", type=Path, required=require_release, default=Path.cwd())
        command.add_argument("--target-url", default="https://php.architex.co.za")
        command.add_argument("--document-root", default="/home/archite4/public_html/php.architex.co.za")
        command.add_argument("--account-home")
        command.add_argument("--ca-file")

    inspect = commands.add_parser("inspect", help="Perform read-only account, path, release, and endpoint checks.")
    add_target_options(inspect, require_release=True)
    inspect.add_argument("--deployment-id")

    migration_inspect = commands.add_parser("migration-inspect", help="Authenticated read-only migration and capability inspection.")
    add_target_options(migration_inspect, require_release=True)
    migration_inspect.add_argument("--deployment-id")
    migration_inspect.add_argument("--expected-database", default=None, help="Optional exact database assertion; otherwise discover through authenticated runtime and cPanel inventory.")

    deploy = commands.add_parser("deploy", help="Stage, preflight, atomically activate, and verify a release.")
    add_target_options(deploy, require_release=True)
    deploy.add_argument("--deployment-id")

    recover = commands.add_parser("recover", help="Plan recovery, or apply it with an explicit flag.")
    add_target_options(recover, require_release=False)
    recover.add_argument("--deployment-id", required=True)
    recover.add_argument("--apply-recovery", action="store_true")
    return parser.parse_args(arguments)


def _account_home(args: argparse.Namespace, environment: Mapping[str, str]) -> str:
    explicit = (args.account_home or environment.get("A777_CPANEL_ACCOUNT_HOME", "")).strip()
    if explicit:
        return posixpath.normpath(explicit)
    public_root = posixpath.dirname(posixpath.normpath(args.document_root))
    if posixpath.basename(public_root) != "public_html":
        raise DeploymentFailure("Account home cannot be derived from the document root.")
    return posixpath.dirname(public_root)


def _configured_remote(
    args: argparse.Namespace,
    environment: Mapping[str, str],
    account_home: str,
) -> CpanelClient:
    required = {
        "A777_CPANEL_HOST": environment.get("A777_CPANEL_HOST", "").strip(),
        "A777_CPANEL_USER": environment.get("A777_CPANEL_USER", "").strip(),
    }
    missing = [name for name, value in required.items() if not value]
    password = environment.get("A777_CPANEL_PASSWORD", "").strip()
    api_token = environment.get("A777_CPANEL_API_TOKEN", "").strip()
    session_id = environment.get("A777_CPANEL_SESSION", "").strip()
    session_cookie = environment.get("A777_CPANEL_SESSION_COOKIE", "").strip()
    if not password and not session_id and not api_token:
        missing.append("A777_CPANEL_API_TOKEN or A777_CPANEL_PASSWORD or A777_CPANEL_SESSION")
    if session_id and not session_cookie:
        missing.append("A777_CPANEL_SESSION_COOKIE")
    if missing:
        raise DeploymentFailure("Missing cPanel environment keys: " + ", ".join(missing))
    try:
        port = int(environment.get("A777_CPANEL_PORT", "2083"))
    except ValueError as error:
        raise DeploymentFailure("A777_CPANEL_PORT must be an integer.") from error
    return CpanelClient(CpanelConfig(
        host=required["A777_CPANEL_HOST"],
        port=port,
        username=required["A777_CPANEL_USER"],
        password=password,
        account_home=account_home,
        allowed_root=posixpath.dirname(posixpath.normpath(args.document_root)),
        session_id=session_id or None,
        session_cookie=session_cookie or None,
        ca_file=args.ca_file,
        api_token=api_token or None,
    ))


def _configuration(args: argparse.Namespace, account_home: str) -> DeploymentConfig:
    return DeploymentConfig(
        release=args.release,
        target_url=args.target_url,
        document_root=args.document_root,
        account_home=account_home,
        deployment_id=args.deployment_id,
    )


def _inspect(
    config: DeploymentConfig,
    remote: CpanelClient,
    http: HttpVerifier,
) -> DeploymentResult:
    validate_release(config.release)
    if remote.account_home() != config.account_home:
        raise DeploymentFailure("Authenticated cPanel account home does not match the deployment configuration.")
    entries = remote.list_files(posixpath.dirname(config.document_root))
    names = sorted(
        str(item.get("name") or posixpath.basename(str(item.get("fullpath", ""))))
        for item in entries
        if item.get("name") or item.get("fullpath")
    )
    stale = [name for name in names if ".candidate-a777-" in name or ".pre-a777-" in name or name.startswith(".a777-runtime-")]
    endpoints: dict[str, int] = {}
    for path in ("/", *LIVE_JSON_PATHS):
        endpoints[path] = http.request(config.target_url + path).status
    return DeploymentResult(
        status="inspected",
        deployment_id=str(config.deployment_id),
        endpoints=endpoints,
        observed={
            "accountHome": config.account_home,
            "documentRootExists": remote.exists(config.document_root),
            "staleDeploymentPaths": stale,
        },
    )


def run_cli(
    args: argparse.Namespace,
    *,
    environment: Mapping[str, str] | None = None,
    remote: CpanelClient | None = None,
    http: HttpVerifier | None = None,
) -> DeploymentResult:
    values = dict(os.environ if environment is None else environment)
    account_home = _account_home(args, values)
    config = _configuration(args, account_home)
    client = remote or _configured_remote(args, values, account_home)
    verifier = http or UrllibHttpVerifier(ca_file=args.ca_file)

    if args.command == "migration-inspect":
        from tools.migration_inspect import inspect_migrations
        report = inspect_migrations(config, client, args.expected_database or values.get("A777_EXPECTED_DATABASE") or None)
        return DeploymentResult(status="migration_inspected", deployment_id=str(config.deployment_id), observed=report)
    if args.command == "inspect":
        return _inspect(config, client, verifier)
    if args.command == "deploy":
        runtime = b""
        return DeploymentOrchestrator(config, client, verifier, runtime_content=runtime).deploy()
    if args.command == "recover":
        orchestrator = DeploymentOrchestrator(config, client, verifier, runtime_content=b"")
        plan = orchestrator.recovery_plan(str(args.deployment_id))
        if not args.apply_recovery:
            return DeploymentResult(
                status="recovery_planned",
                deployment_id=str(args.deployment_id),
                observed=dict(plan.observed),
            )
        return orchestrator.apply_recovery(plan)
    raise DeploymentFailure("Unsupported deployment command.")


def render_failure(error: Exception, secrets: tuple[str, ...] = ()) -> str:
    return json.dumps(
        {"status": "failed", "detail": redact_text(str(error), secrets)},
        separators=(",", ":"),
        sort_keys=True,
    )


def _environment_secrets(environment: Mapping[str, str]) -> tuple[str, ...]:
    secret_markers = ("PASSWORD", "PASS", "TOKEN", "SECRET", "APP_KEY")
    return tuple(
        value for name, value in environment.items()
        if value and any(marker in name.upper() for marker in secret_markers)
    )


def main(arguments: list[str] | None = None) -> int:
    environment = dict(os.environ)
    try:
        result = run_cli(parse_args(arguments), environment=environment)
    except Exception as error:
        print(render_failure(error, _environment_secrets(environment)), file=sys.stderr)
        return 1
    print(json.dumps(result.to_dict(), separators=(",", ":"), sort_keys=True))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
