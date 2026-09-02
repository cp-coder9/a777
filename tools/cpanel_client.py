#!/usr/bin/env python3
"""Constrained cPanel file operations for A777 shared-hosting deployments."""

from __future__ import annotations

import base64
import json
import mimetypes
import posixpath
import secrets
import ssl
import urllib.error
import urllib.parse
import urllib.request
from dataclasses import dataclass
from pathlib import Path
from typing import Mapping, Protocol


class CpanelError(RuntimeError):
    """A redacted cPanel transport or contract failure."""


@dataclass(frozen=True)
class HttpResponse:
    status: int
    headers: Mapping[str, str]
    body: bytes


class HttpTransport(Protocol):
    def request(
        self,
        method: str,
        url: str,
        headers: dict[str, str],
        body: bytes | None,
        timeout: float,
    ) -> HttpResponse:
        """Issue one HTTPS request."""


@dataclass(frozen=True)
class CpanelConfig:
    host: str
    port: int
    username: str
    password: str
    account_home: str
    allowed_root: str
    ca_file: str | None = None
    timeout: float = 45.0

    @property
    def secrets(self) -> tuple[str, ...]:
        return (self.password,)


class UrllibTransport:
    def __init__(self, ca_file: str | None = None) -> None:
        self.context = ssl.create_default_context(cafile=ca_file)

    def request(
        self,
        method: str,
        url: str,
        headers: dict[str, str],
        body: bytes | None,
        timeout: float,
    ) -> HttpResponse:
        request = urllib.request.Request(url, data=body, headers=headers, method=method)
        try:
            with urllib.request.urlopen(request, timeout=timeout, context=self.context) as response:
                return HttpResponse(
                    status=response.status,
                    headers=dict(response.headers.items()),
                    body=response.read(),
                )
        except urllib.error.HTTPError as error:
            return HttpResponse(
                status=error.code,
                headers=dict(error.headers.items()) if error.headers else {},
                body=error.read(),
            )


def redact_text(value: str, secret_values: tuple[str, ...]) -> str:
    redacted = value
    for secret_value in sorted({item for item in secret_values if item}, key=len, reverse=True):
        redacted = redacted.replace(secret_value, "[REDACTED]")
    return redacted


def canonical_remote_path(allowed_root: str, candidate: str) -> str:
    root = posixpath.normpath(allowed_root)
    if not root.startswith("/") or not candidate.startswith("/"):
        raise CpanelError("Remote paths must be absolute.")
    normalized = posixpath.normpath(candidate)
    if normalized != root and not normalized.startswith(root + "/"):
        raise CpanelError("Remote path is outside the allowed deployment root.")
    return normalized


class CpanelClient:
    def __init__(self, config: CpanelConfig, transport: HttpTransport | None = None) -> None:
        self.config = config
        self.transport = transport or UrllibTransport(config.ca_file)
        token = base64.b64encode(f"{config.username}:{config.password}".encode("utf-8")).decode("ascii")
        self._authorization = f"Basic {token}"

    def _url(self, path: str, params: Mapping[str, str] | None = None) -> str:
        query = urllib.parse.urlencode(params or {})
        base = f"https://{self.config.host}:{self.config.port}{path}"
        return f"{base}?{query}" if query else base

    def _request_json(
        self,
        method: str,
        path: str,
        params: Mapping[str, str] | None = None,
        body: bytes | None = None,
        headers: Mapping[str, str] | None = None,
    ) -> dict[str, object]:
        request_headers = {
            "Authorization": self._authorization,
            "Accept": "application/json",
            "User-Agent": "Architex-cPanel-deployer/1",
            **dict(headers or {}),
        }
        try:
            response = self.transport.request(
                method,
                self._url(path, params),
                request_headers,
                body,
                self.config.timeout,
            )
            payload = json.loads(response.body.decode("utf-8"))
        except (OSError, UnicodeDecodeError, json.JSONDecodeError) as error:
            message = redact_text(str(error), self.config.secrets)
            raise CpanelError(f"cPanel did not return valid JSON: {message}") from error
        if not isinstance(payload, dict):
            raise CpanelError("cPanel did not return a JSON object.")
        if response.status < 200 or response.status >= 300:
            raise CpanelError(f"cPanel returned HTTP {response.status}.")
        return payload

    def _uapi(
        self,
        module: str,
        function: str,
        params: Mapping[str, str] | None = None,
        body: bytes | None = None,
        headers: Mapping[str, str] | None = None,
    ) -> object:
        payload = self._request_json("POST" if body is not None else "GET", f"/execute/{module}/{function}", params, body, headers)
        result = payload.get("result")
        if not isinstance(result, dict) or result.get("status") != 1:
            detail = result.get("errors") if isinstance(result, dict) else "malformed result"
            raise CpanelError(redact_text(f"cPanel UAPI failure: {detail}", self.config.secrets))
        return result.get("data")

    def _api2(self, function: str, params: Mapping[str, str]) -> object:
        params = {
            "cpanel_jsonapi_user": self.config.username,
            "cpanel_jsonapi_apiversion": "2",
            "cpanel_jsonapi_module": "Fileman",
            "cpanel_jsonapi_func": function,
            **params,
        }
        payload = self._request_json("GET", "/json-api/cpanel", params)
        result = payload.get("cpanelresult")
        if not isinstance(result, dict) or not isinstance(result.get("event"), dict) or result["event"].get("result") != 1:
            raise CpanelError(redact_text(f"cPanel Fileman failure: {result}", self.config.secrets))
        return result.get("data", [])

    def _api2_fileop(self, op: str, source: str, destination: str = "") -> object:
        return self._api2("fileop", {
            "op": op,
            "sourcefiles": source,
            "destfiles": destination,
            "doubledecode": "1",
        })

    def account_home(self) -> str:
        data = self._uapi("Variables", "get_user_information", {"name": "home"})
        actual = data.get("home") if isinstance(data, dict) else None
        if not isinstance(actual, str) or not actual.startswith("/"):
            raise CpanelError("cPanel did not report a valid account home directory.")
        expected = posixpath.normpath(self.config.account_home)
        normalized = posixpath.normpath(actual)
        if normalized != expected:
            raise CpanelError("cPanel account home does not match the configured deployment account.")
        return normalized

    def list_files(self, path: str) -> list[dict[str, object]]:
        target = canonical_remote_path(self.config.allowed_root, path)
        data = self._uapi("Fileman", "list_files", {"dir": target, "include_hidden": "1"})
        if not isinstance(data, list):
            raise CpanelError("cPanel list_files returned an invalid data contract.")
        return [item for item in data if isinstance(item, dict)]

    def upload_file(self, local: Path, remote: str) -> None:
        target = canonical_remote_path(self.config.allowed_root, remote)
        boundary = "----a777-" + secrets.token_hex(16)
        name = posixpath.basename(target)
        directory = posixpath.dirname(target)
        content_type = mimetypes.guess_type(name)[0] or "application/octet-stream"
        body = b"".join((
            f"--{boundary}\r\nContent-Disposition: form-data; name=\"dir\"\r\n\r\n{directory}\r\n".encode(),
            f"--{boundary}\r\nContent-Disposition: form-data; name=\"file-1\"; filename=\"{name}\"\r\nContent-Type: {content_type}\r\n\r\n".encode(),
            local.read_bytes(),
            f"\r\n--{boundary}--\r\n".encode(),
        ))
        self._uapi(
            "Fileman",
            "upload_files",
            body=body,
            headers={"Content-Type": f"multipart/form-data; boundary={boundary}"},
        )

    def save_file(self, remote: str, content: str) -> None:
        target = canonical_remote_path(self.config.allowed_root, remote)
        body = urllib.parse.urlencode({"file": target, "content": content}).encode("utf-8")
        self._uapi(
            "Fileman",
            "save_file_content",
            body=body,
            headers={"Content-Type": "application/x-www-form-urlencoded"},
        )

    def mkdir(self, path: str, permissions: str = "0755") -> None:
        target = canonical_remote_path(self.config.allowed_root, path)
        if target == posixpath.normpath(self.config.allowed_root):
            return
        self._api2("mkdir", {
            "path": posixpath.dirname(target),
            "name": posixpath.basename(target),
            "permissions": permissions,
        })

    def extract_archive(self, archive: str) -> None:
        self._api2_fileop("extract", canonical_remote_path(self.config.allowed_root, archive))

    def rename(self, source: str, destination: str) -> None:
        safe_source = canonical_remote_path(self.config.allowed_root, source)
        safe_destination = canonical_remote_path(self.config.allowed_root, destination)
        self._api2_fileop("rename", safe_source, safe_destination)

    def trash(self, path: str) -> None:
        target = canonical_remote_path(self.config.allowed_root, path)
        if target == posixpath.normpath(self.config.allowed_root):
            raise CpanelError("Refusing to trash the allowed deployment root.")
        self._api2_fileop("trash", target)

    def exists(self, path: str) -> bool:
        target = canonical_remote_path(self.config.allowed_root, path)
        parent = posixpath.dirname(target)
        name = posixpath.basename(target)
        return any(str(item.get("name", "")) == name for item in self.list_files(parent))
