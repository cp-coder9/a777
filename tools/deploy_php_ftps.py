#!/usr/bin/env python3
"""Stage an Architex PHP release on shared hosting using explicit FTPS swaps.

Runtime credentials are read only from process environment.  They are never
written into the release artifact or printed by this program.
"""

from __future__ import annotations

import argparse
import ftplib
import hashlib
import io
import json
import os
import posixpath
import re
import shutil
import ssl
import sys
import time
import urllib.request
import zipfile
from pathlib import Path


REQUIRED_RUNTIME_KEYS = (
    "A777_DB_HOST", "A777_DB_NAME", "A777_DB_USER", "A777_DB_PASSWORD",
    "A777_SMTP_HOST", "A777_SMTP_USERNAME", "A777_SMTP_PASSWORD",
    "A777_SMTP_FROM_EMAIL",
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
        try:
            return name in {posixpath.basename(item.rstrip("/")) for item in self.ftp.nlst(parent)}
        except ftplib.error_perm:
            return False

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
        try:
            self.ftp.delete(path)
        except ftplib.error_perm:
            pass


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


def runtime_config(args: argparse.Namespace, storage_path: str) -> bytes:
    required = {key: os.environ.get(key, "").strip() for key in REQUIRED_RUNTIME_KEYS}
    missing = [key for key, value in required.items() if not value]
    if missing:
        fail("Missing private runtime environment keys: " + ", ".join(missing))
    app_key = os.environ.get("A777_APP_KEY", "").strip()
    if not app_key:
        app_key = hashlib.sha256(os.urandom(64)).digest().hex()
        app_key = __import__("base64").b64encode(bytes.fromhex(app_key)).decode("ascii")
    values = {
        "APP_ENV": "production", "APP_DEBUG": "false", "APP_URL": args.target_url.rstrip("/"), "APP_KEY": app_key,
        "DB_HOST": required["A777_DB_HOST"], "DB_PORT": os.environ.get("A777_DB_PORT", "3306"),
        "DB_NAME": required["A777_DB_NAME"], "DB_USER": required["A777_DB_USER"], "DB_PASSWORD": required["A777_DB_PASSWORD"],
        "SMTP_HOST": required["A777_SMTP_HOST"], "SMTP_PORT": os.environ.get("A777_SMTP_PORT", "587"),
        "SMTP_ENCRYPTION": os.environ.get("A777_SMTP_ENCRYPTION", "starttls"),
        "SMTP_USERNAME": required["A777_SMTP_USERNAME"], "SMTP_PASSWORD": required["A777_SMTP_PASSWORD"],
        "SMTP_FROM_EMAIL": required["A777_SMTP_FROM_EMAIL"], "SMTP_FROM_NAME": "Architex",
        "BACKUP_ARCHIVE_PASSWORD": os.environ.get("A777_BACKUP_ARCHIVE_PASSWORD", ""),
        "FILE_STORAGE_PATH": storage_path, "MAINTENANCE_ENABLED": "0", "MAINTENANCE_TOKEN": "",
        "MAINTENANCE_ALLOW_MIGRATIONS": "0", "LAST_VERIFIED_BACKUP_ID": "",
    }
    lines = ["<?php", "", "declare(strict_types=1);", "", "return ["]
    for key, value in values.items():
        escaped = value.replace("\\", "\\\\").replace("'", "\\'")
        lines.append(f"    '{key}' => '{escaped}',")
    lines.append("];\n")
    return "\n".join(lines).encode("utf-8")


def probe_source(runtime_name: str) -> bytes:
    return f"""<?php
declare(strict_types=1);
require dirname(__DIR__) . '/{runtime_name}/bootstrap.php';
use Architex\\Infrastructure\\Config\\AppConfig;
use Architex\\Infrastructure\\Database\\ConnectionFactory;
use Architex\\Infrastructure\\Mail\\MailRuntime;
try {{
    $env = getenv();
    $env = is_array($env) ? $env : [];
    $pdo = ConnectionFactory::create(AppConfig::fromEnvironment($env));
    $db = (int) $pdo->query('SELECT 1')->fetchColumn() === 1;
    $mail = MailRuntime::fromEnvironment($pdo, $env)->isReady();
    header('Content-Type: application/json');
    echo json_encode(['ok' => $db && $mail, 'database' => $db, 'mail' => $mail], JSON_THROW_ON_ERROR);
}} catch (Throwable) {{ http_response_code(503); echo '{{"ok":false}}'; }}
""".encode("utf-8")


def extractor_source(archive_name: str) -> bytes:
    return f"""<?php
declare(strict_types=1);
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


def http_json(url: str) -> tuple[int, dict[str, object]]:
    request = urllib.request.Request(url, headers={"User-Agent": "Architex-release-verifier/1"})
    with urllib.request.urlopen(request, timeout=45, context=ssl.create_default_context()) as response:
        payload = json.loads(response.read().decode("utf-8"))
        return response.status, payload if isinstance(payload, dict) else {}


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--release", required=True, type=Path)
    parser.add_argument("--ftp-env", required=True, type=Path)
    parser.add_argument("--remote-parent", required=True)
    parser.add_argument("--target-name", default="test.architex.co.za")
    parser.add_argument("--target-url", required=True)
    args = parser.parse_args()
    release = args.release.resolve()
    if not (release / "release.json").is_file() or not (release / "public" / "index.php").is_file():
        fail("Release artifact is incomplete.")
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
    probe_name = f"__a777_preflight_{suffix}.php"
    probe_remote = remote_join(target_public, probe_name)
    extractor_name = f"__a777_extract_{suffix}.php"
    extractor_remote = remote_join(target_public, extractor_name)
    archive_name = f".a777-package-{token}.zip"
    archive_remote = remote_join(parent, archive_name)
    archive_local = release.parent / archive_name
    moved_runtime = False
    moved_public = False
    ftp_host = os.environ.get("A777_FTP_HOST", ftp_values["FTP_HOST"]).strip()
    if not ftp_host:
        fail("FTP host is unavailable.")
    client = Ftps(ftp_host, int(ftp_values.get("FTP_PORT", "21")), ftp_values["FTP_USER"], ftp_values["FTP_PASS"])
    try:
        if client.exists(candidate_runtime) or client.exists(candidate_public):
            fail("Candidate path already exists; retry the deployment.")
        client.mkdirs(storage)
        package_files, package_bytes = build_transport_archive(release, archive_local, candidate_runtime_name, candidate_public_name, active_runtime_name)
        client.put_file(archive_local, archive_remote)
        client.put_bytes(extractor_remote, extractor_source(archive_name))
        extraction_status, extraction = http_json(args.target_url.rstrip("/") + "/" + extractor_name)
        client.delete(extractor_remote)
        if extraction_status != 200 or extraction.get("ok") is not True:
            fail("The host could not extract the staged release archive.")
        client.put_bytes(remote_join(candidate_runtime, "config", "runtime.php"), runtime_config(args, storage))
        client.put_bytes(probe_remote, probe_source(candidate_runtime_name))
        probe_status, probe = http_json(args.target_url.rstrip("/") + "/" + probe_name)
        client.delete(probe_remote)
        if probe_status != 200 or probe.get("ok") is not True:
            fail("Private runtime preflight did not confirm database and mail readiness.")
        if client.exists(active_runtime):
            client.rename(active_runtime, prior_runtime)
            moved_runtime = True
        client.rename(candidate_runtime, active_runtime)
        if client.exists(target_public):
            client.rename(target_public, rollback_public)
            moved_public = True
        client.rename(candidate_public, target_public)
        checks = ("/", "/api/v1/health", "/api/v1/readiness", "/api/v1/legal-notices/signup")
        for path in checks:
            status, _ = http_json(args.target_url.rstrip("/") + path)
            if status != 200:
                fail(f"Live validation failed for {path} with HTTP {status}.")
        print(json.dumps({"status": "deployed", "packageFiles": package_files, "packageBytes": package_bytes, "rollbackPublic": rollback_public_name, "priorRuntime": prior_runtime_name}, separators=(",", ":")))
        return 0
    except Exception:
        try:
            client.delete(probe_remote)
            client.delete(extractor_remote)
            if client.exists(target_public) and moved_public and client.exists(rollback_public):
                client.rename(target_public, candidate_public)
                client.rename(rollback_public, target_public)
            if client.exists(active_runtime) and moved_runtime and client.exists(prior_runtime):
                client.rename(active_runtime, candidate_runtime)
                client.rename(prior_runtime, active_runtime)
        except Exception:
            pass
        raise
    finally:
        archive_local.unlink(missing_ok=True)
        client.close()


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as error:
        print(f"Deployment failed: {error}", file=sys.stderr)
        raise SystemExit(1)
