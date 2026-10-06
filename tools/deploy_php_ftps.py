#!/usr/bin/env python3
"""Stage an Architex PHP release on shared hosting using explicit FTPS swaps.

Runtime credentials are read only from process environment.  They are never
written into the release artifact or printed by this program.
"""

from __future__ import annotations

import argparse
import base64
import ftplib
import hashlib
import io
import json
import os
import posixpath
import re
import secrets
import shutil
import ssl
import sys
import time
import urllib.request
import urllib.parse
import zipfile
from datetime import datetime, timezone
from pathlib import Path
from typing import Mapping

if __package__ in (None, ''):
    sys.path.insert(0, str(Path(__file__).resolve().parents[1]))


REQUIRED_RUNTIME_KEYS = (
    "A777_DB_HOST", "A777_DB_NAME", "A777_DB_USER", "A777_DB_PASSWORD",
    "A777_SMTP_HOST", "A777_SMTP_USERNAME", "A777_SMTP_PASSWORD",
    "A777_SMTP_FROM_EMAIL",
    "A777_APP_KEY", "A777_BACKUP_ARCHIVE_PASSWORD",
)


def fail(message: str) -> "None":
    raise RuntimeError(message)


def env_file_values(path: Path) -> dict[str, str]:
    values: dict[str, str] = {}
    for raw in path.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        values[key.strip()] = value.strip().strip('"').strip("'")
    return values


def remote_join(*parts: str) -> str:
    return posixpath.normpath(posixpath.join(*parts))


def validate_release(release: Path, target_environment: str = 'production') -> None:
    required = (
        release / "release.json",
        release / "release-manifest.json",
        release / "public" / "index.php",
        release / "public" / ".htaccess",
    )
    if not all(path.is_file() for path in required):
        fail("Release artifact is incomplete.")
    try:
        manifest = json.loads((release / 'release-manifest.json').read_text(encoding='utf-8'))
    except (OSError, ValueError):
        fail('Release manifest is invalid.')
    if manifest.get('format') != 'architex-release-manifest-v1' or not isinstance(manifest.get('files'), dict):
        fail('Release manifest is invalid.')
    actual = {}
    for path in release.rglob('*'):
        if path.is_symlink():
            fail('Release contains a forbidden symbolic link.')
        if not path.is_file():
            continue
        relative = path.relative_to(release).as_posix()
        if relative == 'release-manifest.json':
            continue
        if re.search(r'(^|/)(?:\.env(?:\.[^/]*)?|\.private|\.git|tests?|node_modules|packs|backups)(?:/|$)|(^|/)config/runtime\.php$|^storage/|^vendor/(?:phpunit|sebastian|mockery|phar-io|theseer)/', relative, re.I):
            fail('Release contains a forbidden path: ' + relative)
        actual[relative] = {'sha256': hashlib.sha256(path.read_bytes()).hexdigest(), 'bytes': path.stat().st_size}
    if actual != manifest['files']:
        fail('Release manifest does not match artifact bytes.')
    try:
        metadata = json.loads((release / 'release.json').read_text(encoding='utf-8'))
    except (OSError, ValueError):
        fail('Release provenance is invalid.')
    if (target_environment not in ('production', 'staging') or not isinstance(metadata, dict)
            or metadata.get('format') != 'architex-release-v1'
            or metadata.get('environment') != target_environment
            or not isinstance(metadata.get('sourceDirty'), bool)
            or re.fullmatch(r'[a-f0-9]{64}', str(metadata.get('sourceSnapshotSha256', ''))) is None):
        fail('Release provenance does not match the explicit target environment.')
    if target_environment == 'production' and (metadata['sourceDirty'] or re.fullmatch(r'[a-f0-9]{40}', str(metadata.get('sourceCommit', ''))) is None):
        fail('Production release provenance requires a clean exact source revision.')


def deployment_token(release: Path, now: datetime | None = None) -> str:
    instant = now or datetime.now(timezone.utc)
    stamp = instant.strftime("%Y%m%d-%H%M%S")
    suffix = hashlib.sha256(f"{release.resolve()}:{stamp}".encode("utf-8")).hexdigest()[:8]
    return f"{stamp}-{suffix}-{secrets.token_hex(8)}"


class Ftps:
    def __init__(self, host: str, port: int, username: str, password: str) -> None:
        self.ftp = ftplib.FTP_TLS(context=ssl.create_default_context(), timeout=45)
        self.ftp.connect(host, port)
        self.ftp.login(username, password)
        self.ftp.prot_p()

    def close(self) -> None:
        try:
            self.ftp.quit()
        except Exception:
            self.ftp.close()

    def exists(self, path: str) -> bool:
        parent, name = posixpath.dirname(path), posixpath.basename(path)
        return name in {posixpath.basename(item.rstrip("/")) for item in self.ftp.nlst(parent)}

    def mkdirs(self, path: str) -> None:
        cursor = "/" if path.startswith("/") else ""
        for segment in [part for part in path.split("/") if part]:
            cursor = remote_join(cursor, segment)
            try:
                self.ftp.mkd(cursor)
            except ftplib.error_perm as error:
                if not self.exists(cursor):
                    raise error

    def put_bytes(self, path: str, content: bytes) -> None:
        self.mkdirs(posixpath.dirname(path))
        self.ftp.storbinary(f"STOR {path}", io.BytesIO(content), blocksize=1024 * 128)

    def put_file(self, local: Path, remote: str) -> None:
        self.mkdirs(posixpath.dirname(remote))
        with local.open("rb") as handle:
            self.ftp.storbinary(f"STOR {remote}", handle, blocksize=1024 * 128)

    def rename(self, old: str, new: str) -> None:
        self.ftp.rename(old, new)

    def delete(self, path: str) -> None:
        if self.exists(path):
            self.ftp.delete(path)
        if self.exists(path):
            fail('Remote temporary file cleanup unverified; recovery required.')

    def read_bytes(self, path: str) -> bytes:
        buffer = io.BytesIO()
        self.ftp.retrbinary('RETR ' + path, buffer.write)
        return buffer.getvalue()


def upload_tree(client: Ftps, source: Path, destination: str, exclude: set[str] | None = None) -> int:
    count = 0
    excluded = exclude or set()
    for path in source.rglob("*"):
        relative = path.relative_to(source).as_posix()
        if any(relative == item or relative.startswith(item + "/") for item in excluded):
            continue
        target = remote_join(destination, relative)
        if path.is_dir():
            client.mkdirs(target)
        elif path.is_file():
            client.put_file(path, target)
            count += 1
    return count


def build_runtime_config(target_url: str, storage_path: str, environment: Mapping[str, str], target_environment: str = 'production') -> bytes:
    if target_environment not in ('production', 'staging'):
        fail('Invalid target environment.')
    required = {key: environment.get(key, "").strip() for key in REQUIRED_RUNTIME_KEYS}
    missing = [key for key, value in required.items() if not value]
    if missing:
        fail("Missing private runtime environment keys: " + ", ".join(missing))
    app_key = required['A777_APP_KEY']
    try:
        if len(base64.b64decode(app_key, validate=True)) != 32:
            fail('A777_APP_KEY must be the existing base64-encoded 32-byte application key.')
    except ValueError:
        fail('A777_APP_KEY must be the existing base64-encoded 32-byte application key.')
    if len(required['A777_BACKUP_ARCHIVE_PASSWORD']) < 20:
        fail('A777_BACKUP_ARCHIVE_PASSWORD must contain at least 20 characters.')
    values = {
        "APP_ENV": target_environment, "APP_DEBUG": "false", "APP_URL": target_url.rstrip("/"), "APP_KEY": app_key,
        "DB_HOST": required["A777_DB_HOST"], "DB_PORT": environment.get("A777_DB_PORT", "3306"),
        "DB_NAME": required["A777_DB_NAME"], "DB_USER": required["A777_DB_USER"], "DB_PASSWORD": required["A777_DB_PASSWORD"],
        "SMTP_HOST": required["A777_SMTP_HOST"], "SMTP_PORT": environment.get("A777_SMTP_PORT", "587"),
        "SMTP_ENCRYPTION": environment.get("A777_SMTP_ENCRYPTION", "starttls"),
        "SMTP_USERNAME": required["A777_SMTP_USERNAME"], "SMTP_PASSWORD": required["A777_SMTP_PASSWORD"],
        "SMTP_FROM_EMAIL": required["A777_SMTP_FROM_EMAIL"], "SMTP_FROM_NAME": "Architex",
        "NVIDIA_API_KEY": environment.get("A777_NVIDIA_API_KEY", "").strip(),
        "BACKUP_ARCHIVE_PASSWORD": environment.get("A777_BACKUP_ARCHIVE_PASSWORD", ""),
        "FILE_STORAGE_PATH": storage_path, "MAINTENANCE_ENABLED": "0", "MAINTENANCE_TOKEN": "",
        "MAINTENANCE_ALLOW_MIGRATIONS": "0", "LAST_VERIFIED_BACKUP_ID": "",
    }
    lines = ["<?php", "", "declare(strict_types=1);", "", "return ["]
    for key, value in values.items():
        escaped = value.replace("\\", "\\\\").replace("'", "\\'")
        lines.append(f"    '{key}' => '{escaped}',")
    lines.append("];\n")
    return "\n".join(lines).encode("utf-8")


def runtime_config(args: argparse.Namespace, storage_path: str) -> bytes:
    return build_runtime_config(args.target_url, storage_path, os.environ, args.environment)


def readiness_is_ready(data: object) -> bool:
    if not isinstance(data, dict) or data.get('status') != 'ready':
        return False
    components = data.get('components')
    return isinstance(components, dict) and all(
        isinstance(components.get(name), dict) and components[name].get('status') == 'ready'
        for name in ('database', 'migrations', 'jobs', 'mail'))


def probe_source(runtime_name: str) -> bytes:
    return f"""<?php
declare(strict_types=1);
ini_set('display_errors', '0');
use Architex\\Infrastructure\\Config\\AppConfig;
use Architex\\Infrastructure\\Database\\ConnectionFactory;
use Architex\\Infrastructure\\Database\\MigrationRunner;
use Architex\\Infrastructure\\Mail\\MailRuntime;
$result = ['ok' => false, 'php' => PHP_VERSION_ID >= 80300, 'phpVersionId' => PHP_VERSION_ID,
    'bootstrap' => false, 'config' => false, 'database' => false, 'migrations' => false, 'mail' => false,
    'reason' => 'bootstrap_failed'];
// Buffer bootstrap exits/fatals too: never bypass its maintenance/storage fence.
$bufferLevel = ob_get_level();
ob_start();
register_shutdown_function(static function () use (&$result, $bufferLevel): void {{
    while (ob_get_level() > $bufferLevel) ob_end_clean();
    http_response_code($result['ok'] ? 200 : 503);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($result);
}});
try {{
    require dirname(__DIR__) . '/{runtime_name}/bootstrap.php';
    $result['bootstrap'] = true;
    $result['reason'] = 'config_failed';
    $env = getenv();
    $env = is_array($env) ? $env : [];
    $config = AppConfig::fromEnvironment($env);
    $result['config'] = true;
    $result['reason'] = 'database_failed';
    $pdo = ConnectionFactory::create($config);
    $result['database'] = (int) $pdo->query('SELECT 1')->fetchColumn() === 1;
    if (!$result['database']) throw new RuntimeException();
    $result['reason'] = 'migrations_failed';
    $status = (new MigrationRunner($pdo, dirname(__DIR__) . '/{runtime_name}/database/migrations'))->status();
    // Accept array status and this repository's typed report via pendingCount.
    $pendingCount = is_array($status) ? ($status['pendingCount'] ?? null) : ($status->pendingCount ?? null);
    if (!is_int($pendingCount) || $pendingCount < 0) throw new RuntimeException();
    $result['pendingCount'] = $pendingCount;
    $result['migrations'] = $pendingCount === 0;
    $result['reason'] = 'mail_failed';
    $result['mail'] = MailRuntime::fromEnvironment($pdo, $env)->isReady();
    $result['ok'] = $result['php'] && $result['database'] && $result['migrations'] && $result['mail'];
    $result['reason'] = !$result['php'] ? 'php_unsupported' : (!$result['migrations'] ? 'migrations_pending' : (!$result['mail'] ? 'mail_not_ready' : 'ready'));
}} catch (Throwable) {{
    // Keep only the constant stage reason and completed booleans, never exception details.
    $result['ok'] = false;
}}
""".encode("utf-8")


def extractor_source(archive_name: str, secret: str) -> bytes:
    if re.fullmatch(r'[a-f0-9]{64}', secret) is None or re.fullmatch(r'[.a-zA-Z0-9_-]+\.zip', archive_name) is None:
        fail('Invalid extractor authentication or archive identifier.')
    return f"""<?php
declare(strict_types=1);
ini_set('display_errors', '0');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !hash_equals('{secret}', $_SERVER['HTTP_X_A777_DEPLOY'] ?? '')) {{ http_response_code(404); exit; }}
$archive = dirname(__DIR__) . '/{archive_name}';
try {{
    $zip = new ZipArchive();
    if ($zip->open($archive) !== true) throw new RuntimeException('archive_unavailable');
    if (!$zip->extractTo(dirname(__DIR__))) throw new RuntimeException('archive_extract_failed');
    $zip->close();
    if (!unlink($archive)) throw new RuntimeException('archive_cleanup_failed');
    header('Content-Type: application/json');
    echo '{{"ok":true}}';
}} catch (Throwable) {{ http_response_code(503); echo '{{"ok":false}}'; }}
""".encode("utf-8")


def build_transport_archive(release: Path, destination: Path, runtime_name: str, public_name: str, active_runtime_name: str) -> tuple[int, int]:
    excluded_public = {".htaccess", "index.php", "app.php", "legal.php", "maintenance.php"}
    files = 0
    with zipfile.ZipFile(destination, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for path in release.rglob("*"):
            if not path.is_file():
                continue
            archive.write(path, f"{runtime_name}/{path.relative_to(release).as_posix()}")
            files += 1
        public_root = release / "public"
        for path in public_root.rglob("*"):
            if not path.is_file() or path.name in excluded_public:
                continue
            archive.write(path, f"{public_name}/{path.relative_to(public_root).as_posix()}")
            files += 1
        shim = f"<?php\ndeclare(strict_types=1);\nrequire dirname(__DIR__) . '/{active_runtime_name}/public/index.php';\n"
        htaccess = "RewriteEngine On\nRewriteRule (^|/)\\.(?!well-known(?:/|$)) - [F,L]\nRewriteCond %{REQUEST_FILENAME} -f\nRewriteRule ^ - [L]\nRewriteRule ^ index.php [QSA,L]\n"
        archive.writestr(f"{public_name}/index.php", shim)
        archive.writestr(f"{public_name}/.htaccess", htaccess)
        files += 2
    return files, destination.stat().st_size


def deployment_opener(url: str):
    from tools.cpanel_client import RejectRedirects
    parsed = urllib.parse.urlsplit(url)
    if parsed.scheme != 'https' or not parsed.hostname or parsed.username or parsed.password:
        fail('Deployment verification requires HTTPS without URL credentials.')
    return urllib.request.build_opener(urllib.request.HTTPSHandler(context=ssl.create_default_context()), RejectRedirects())


def http_json(url: str, secret: str | None = None) -> tuple[int, dict[str, object]]:
    headers = {"User-Agent": "Architex-release-verifier/1"}
    if secret:
        headers['X-A777-Deploy'] = secret
    request = urllib.request.Request(url, headers=headers, data=b'' if secret else None, method='POST' if secret else 'GET')
    with deployment_opener(url).open(request, timeout=45) as response:
        payload = json.loads(response.read().decode("utf-8"))
        return response.status, payload if isinstance(payload, dict) else {}


def http_status(url: str) -> int:
    request = urllib.request.Request(url, headers={'User-Agent': 'Architex-release-verifier/1'})
    with deployment_opener(url).open(request, timeout=45) as response:
        response.read(1024)
        return response.status


def rollback_swap(client: Ftps, swaps: list[tuple[str, str, str, str, bool]]) -> None:
    errors = []
    for label, active, prior, candidate, originally_present in swaps:
        try:
            # Inspect remote state: a lost response may occur after the server renamed it.
            if client.exists(prior):
                if client.exists(active):
                    if client.exists(candidate):
                        fail('Candidate path unexpectedly occupied during rollback.')
                    client.rename(active, candidate)
                client.rename(prior, active)
            elif originally_present:
                if not client.exists(active):
                    fail('Original active path and rollback path are both absent.')
            elif client.exists(active):
                if client.exists(candidate):
                    fail('Candidate path unexpectedly occupied during rollback.')
                client.rename(active, candidate)
        except BaseException:
            errors.append(label)
    if errors:
        fail('Rollback incomplete for: ' + ', '.join(errors) + '. Retain candidate/prior paths for recovery.')


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--release", required=True, type=Path)
    parser.add_argument("--ftp-env", required=True, type=Path)
    parser.add_argument("--remote-parent", required=True)
    parser.add_argument("--target-name", default="test.architex.co.za")
    parser.add_argument("--target-url", required=True)
    parser.add_argument("--environment", choices=('production', 'staging'), default='production')
    args = parser.parse_args()
    release = args.release.resolve()
    deployment_opener(args.target_url)
    validate_release(release, args.environment)
    ftp_values = env_file_values(args.ftp_env)
    for key in ("FTP_HOST", "FTP_USER", "FTP_PASS"):
        if not ftp_values.get(key):
            fail(f"FTP environment is missing {key}.")
    stamp = time.strftime("%Y%m%d-%H%M%S", time.gmtime())
    suffix = hashlib.sha256((stamp + str(release)).encode()).hexdigest()[:8]
    token = f"{stamp}-{suffix}-{os.urandom(12).hex()}"
    parent = args.remote_parent.rstrip("/")
    candidate_runtime_name = f".a777-runtime-candidate-{token}"
    active_runtime_name = ".a777-runtime-active"
    prior_runtime_name = f".a777-runtime-prior-{token}"
    storage_name = ".a777-storage"
    candidate_public_name = f"{args.target_name}.candidate-a777-{token}"
    rollback_public_name = f"{args.target_name}.pre-a777-{token}"
    candidate_runtime = remote_join(parent, candidate_runtime_name)
    active_runtime = remote_join(parent, active_runtime_name)
    prior_runtime = remote_join(parent, prior_runtime_name)
    storage = remote_join(parent, storage_name)
    candidate_public = remote_join(parent, candidate_public_name)
    target_public = remote_join(parent, args.target_name)
    rollback_public = remote_join(parent, rollback_public_name)
    probe_name = f"__a777_preflight_{secrets.token_hex(16)}.php"
    probe_remote = remote_join(target_public, probe_name)
    extractor_name = f"__a777_extract_{secrets.token_hex(16)}.php"
    extractor_secret = secrets.token_hex(32)
    extractor_remote = remote_join(target_public, extractor_name)
    archive_name = f".a777-package-{token}.zip"
    archive_remote = remote_join(parent, archive_name)
    archive_local = release.parent / archive_name
    originally_runtime = originally_public = None
    private_runtime = None
    ftp_host = os.environ.get("A777_FTP_HOST", ftp_values["FTP_HOST"]).strip()
    if not ftp_host:
        fail("FTP host is unavailable.")
    client = Ftps(ftp_host, int(ftp_values.get("FTP_PORT", "21")), ftp_values["FTP_USER"], ftp_values["FTP_PASS"])
    try:
        if client.exists(candidate_runtime) or client.exists(candidate_public):
            fail("Candidate path already exists; retry the deployment.")
        originally_runtime = client.exists(active_runtime)
        originally_public = client.exists(target_public)
        private_runtime = client.read_bytes(remote_join(active_runtime, 'config', 'runtime.php'))
        if not private_runtime.startswith(b'<?php'):
            fail('Existing private runtime configuration unavailable; refusing replacement.')
        client.mkdirs(storage)
        package_files, package_bytes = build_transport_archive(release, archive_local, candidate_runtime_name, candidate_public_name, active_runtime_name)
        client.put_file(archive_local, archive_remote)
        client.put_bytes(extractor_remote, extractor_source(archive_name, extractor_secret))
        extraction_status, extraction = http_json(args.target_url.rstrip("/") + "/" + extractor_name, extractor_secret)
        client.delete(extractor_remote)
        if extraction_status != 200 or extraction.get("ok") is not True:
            fail("The host could not extract the staged release archive.")
        client.put_bytes(remote_join(candidate_runtime, "config", "runtime.php"), private_runtime)
        client.put_bytes(probe_remote, probe_source(candidate_runtime_name))
        probe_status, probe = http_json(args.target_url.rstrip("/") + "/" + probe_name)
        client.delete(probe_remote)
        if probe_status != 200 or probe.get("ok") is not True:
            fail("Private runtime preflight did not confirm database and mail readiness.")
        if client.exists(active_runtime):
            client.rename(active_runtime, prior_runtime)
        client.rename(candidate_runtime, active_runtime)
        if client.exists(target_public):
            client.rename(target_public, rollback_public)
        client.rename(candidate_public, target_public)
        expected_build = json.loads((release / 'public' / 'build-info.json').read_text())
        build_status, live_build = http_json(args.target_url.rstrip('/') + '/build-info.json')
        if build_status != 200 or live_build != expected_build:
            fail('Live build identity does not match promoted release.')
        checks = ("/", "/api/v1/health", "/api/v1/readiness", "/api/v1/legal-notices/signup")
        for path in checks:
            status = http_status(args.target_url.rstrip("/") + path)
            if status != 200:
                fail(f"Live validation failed for {path} with HTTP {status}.")
        readiness_status, readiness = http_json(args.target_url.rstrip('/') + '/api/v1/readiness')
        data = readiness.get('data')
        if readiness_status != 200 or not readiness_is_ready(data):
            fail('Live readiness did not confirm ready=true.')
        for path in (probe_remote, extractor_remote, archive_remote):
            client.delete(path)
        print(json.dumps({"status": "deployed", "packageFiles": package_files, "packageBytes": package_bytes, "rollbackPublic": rollback_public_name, "priorRuntime": prior_runtime_name}, separators=(",", ":")))
        return 0
    except BaseException as failure:
        cleanup_errors = []
        for path in (probe_remote, extractor_remote, archive_remote):
            try:
                client.delete(path)
            except BaseException:
                cleanup_errors.append('temporary HTTP helper')
        swaps = []
        if originally_public is not None:
            swaps.append(('public', target_public, rollback_public, candidate_public, originally_public))
        if originally_runtime is not None:
            swaps.append(('runtime', active_runtime, prior_runtime, candidate_runtime, originally_runtime))
        try:
            rollback_swap(client, swaps)
        except RuntimeError as rollback_error:
            raise rollback_error from failure
        if cleanup_errors:
            raise RuntimeError('Rollback restored active paths, but temporary HTTP helper cleanup failed.') from failure
        raise
    finally:
        archive_local.unlink(missing_ok=True)
        client.close()


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as error:
        print("Deployment failed; retain candidate/prior paths and inspect recovery state.", file=sys.stderr)
        raise SystemExit(1)
