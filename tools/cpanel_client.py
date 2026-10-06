#!/usr/bin/env python3
"""Constrained cPanel file operations for A777 shared-hosting deployments."""

from __future__ import annotations

import base64
import json
import mimetypes
import posixpath
import re
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
    session_id: str | None = None
    session_cookie: str | None = None
    ca_file: str | None = None
    timeout: float = 45.0
    api_token: str | None = None

    @property
    def secrets(self) -> tuple[str, ...]:
        return (self.password, self.session_id or "", self.session_cookie or "", self.api_token or "")


class RejectRedirects(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, request, fp, code, message, headers, new_url):
        raise CpanelError("Deployment HTTP redirect refused.")


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
            opener = urllib.request.build_opener(urllib.request.HTTPSHandler(context=self.context), RejectRedirects())
            with opener.open(request, timeout=timeout) as response:
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
        if config.api_token and (config.password or config.session_id or config.session_cookie):
            raise CpanelError("API token cannot be combined with password or session authentication.")
        if config.api_token and any(c in config.api_token for c in ('\r', '\n')):
            raise CpanelError("Invalid cPanel API token.")
        self.transport = transport or UrllibTransport(config.ca_file)
        if config.api_token:
            self._authorization = f"cpanel {config.username}:{config.api_token}"
            self._path_prefix = ""
            self._session_cookie = None
        elif config.session_id is not None:
            if re.fullmatch(r"cpsess\d{6,20}", config.session_id) is None:
                raise CpanelError("Invalid cPanel session identifier.")
            if config.session_cookie is None or re.fullmatch(r"cpsession=[A-Za-z0-9%._~-]+", config.session_cookie) is None:
                raise CpanelError("Invalid or missing cPanel session cookie.")
            self._path_prefix = "/" + config.session_id
            self._authorization: str | None = None
            self._session_cookie = config.session_cookie
        else:
            if not config.password:
                raise CpanelError("A cPanel API token, password or session identifier is required.")
            token = base64.b64encode(f"{config.username}:{config.password}".encode("utf-8")).decode("ascii")
            self._authorization = f"Basic {token}"
            self._path_prefix = ""
            self._session_cookie = None

    def _url(self, path: str, params: Mapping[str, str] | None = None) -> str:
        query = urllib.parse.urlencode(params or {})
        base = f"https://{self.config.host}:{self.config.port}{self._path_prefix}{path}"
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
            "Accept": "application/json",
            "User-Agent": "Architex-cPanel-deployer/1",
            **dict(headers or {}),
        }
        if self._authorization is not None:
            request_headers["Authorization"] = self._authorization
        if self._session_cookie is not None:
            request_headers["Cookie"] = self._session_cookie
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
            raise CpanelError("cPanel transport or JSON contract failed.") from None
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
        wrapped = payload.get("result")
        result = wrapped if isinstance(wrapped, dict) else payload
        if not isinstance(result, dict) or result.get("status") != 1:
            detail = result.get("errors") if isinstance(result, dict) else "malformed result"
            raise CpanelError("cPanel UAPI operation failed.")
        return result.get("data")

    def _api2(self, function: str, params: Mapping[str, str]) -> object:
        return self.api2('Fileman', function, params)

    def api2(self, module: str, function: str, params: Mapping[str, str]) -> object:
        """API 2 form POST: parameters (including pass) never enter the URL."""
        if not re.fullmatch(r'[A-Za-z][A-Za-z0-9_]*', module) or not re.fullmatch(r'[A-Za-z][A-Za-z0-9_]*', function):
            raise CpanelError('Invalid API 2 operation.')
        params = {
            **params,
            "cpanel_jsonapi_user": self.config.username,
            "cpanel_jsonapi_apiversion": "2",
            "cpanel_jsonapi_module": module,
            "cpanel_jsonapi_func": function,
        }
        if module == 'Fileman':
            payload = self._request_json("GET", "/json-api/cpanel", params)
        else:
            payload = self._request_json("POST", "/json-api/cpanel", body=urllib.parse.urlencode(params).encode('utf-8'),
                                         headers={'Content-Type': 'application/x-www-form-urlencoded'})
        result = payload.get("cpanelresult")
        if not isinstance(result, dict) or not isinstance(result.get("event"), dict) or result["event"].get("result") != 1:
            raise CpanelError("cPanel SSH contract failed." if module == 'SSH' else "cPanel Fileman contract failed.")
        data = result.get("data", [])
        if isinstance(data, list):
            failed = [item for item in data if isinstance(item, dict) and "result" in item and item.get("result") not in (1, "1", True)]
            failed.extend(
                item for item in data
                if isinstance(item, dict) and (
                    bool(item.get("err"))
                    or any(marker in str(item.get("output", "")).lower() for marker in ("permission denied", "unable to process", "cannot create"))
                )
            )
            if failed:
                raise CpanelError("cPanel SSH operation failed." if module == 'SSH' else "cPanel Fileman operation failed.")
        return data

    def _account_relative(self, path: str) -> str:
        home = posixpath.normpath(self.config.account_home)
        normalized = posixpath.normpath(path)
        relative = posixpath.relpath(normalized, home)
        if relative == ".." or relative.startswith("../"):
            raise CpanelError("cPanel API 2 path is outside the account home.")
        return relative

    def _api2_fileop(self, op: str, source: str, destination: str = "") -> object:
        return self._api2("fileop", {
            "op": op,
            "sourcefiles": source,
            "destfiles": destination,
            "doubledecode": "0",
            "filelist": "1",
            "multiform": "1",
            "metadata": "undefined",
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
        data = self._uapi("Fileman", "list_files", {"dir": target, "show_hidden": "1"})
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
        body = urllib.parse.urlencode({
            "dir": posixpath.dirname(target),
            "file": posixpath.basename(target),
            "content": content,
        }).encode("utf-8")
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
        target = canonical_remote_path(self.config.allowed_root, archive)
        destination = "/" + self._account_relative(posixpath.dirname(target))
        self._api2_fileop("extract", target, destination)

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
        return any(
            str(item.get("name", "")) == name
            or posixpath.basename(str(item.get("fullpath", ""))) == name
            for item in self.list_files(parent)
        )
