from __future__ import annotations

import base64
import json
import tempfile
import unittest
from pathlib import Path
from urllib.parse import parse_qs, urlparse

from tools.cpanel_client import (
    CpanelClient,
    CpanelConfig,
    CpanelError,
    HttpResponse,
    canonical_remote_path,
    redact_text,
)


class FakeTransport:
    def __init__(self, responses: list[HttpResponse] | None = None) -> None:
        self.responses = list(responses or [])
        self.requests: list[dict[str, object]] = []

    def request(
        self,
        method: str,
        url: str,
        headers: dict[str, str],
        body: bytes | None,
        timeout: float,
    ) -> HttpResponse:
        self.requests.append({
            "method": method,
            "url": url,
            "headers": headers,
            "body": body,
            "timeout": timeout,
        })
        if not self.responses:
            raise AssertionError("No fake response was configured")
        return self.responses.pop(0)


def response(payload: dict[str, object], status: int = 200) -> HttpResponse:
    return HttpResponse(status=status, headers={"Content-Type": "application/json"}, body=json.dumps(payload).encode())


def uapi_ok(data: object = None) -> HttpResponse:
    return response({"result": {"status": 1, "errors": None, "data": data}})


def api2_ok(data: object = None) -> HttpResponse:
    return response({"cpanelresult": {"event": {"result": 1}, "data": data or []}})


def configured_client(transport: FakeTransport, password: str = "panel-secret") -> CpanelClient:
    return CpanelClient(
        CpanelConfig(
            host="cpanel.example.test",
            port=2083,
            username="archite4",
            password=password,
            account_home="/home/archite4",
            allowed_root="/home/archite4/public_html",
        ),
        transport=transport,
    )


class CanonicalRemotePathTest(unittest.TestCase):
    def test_accepts_exact_root_and_descendants(self) -> None:
        root = "/home/archite4/public_html"
        self.assertEqual(root, canonical_remote_path(root, root))
        self.assertEqual(
            root + "/php.architex.co.za/index.php",
            canonical_remote_path(root, root + "/php.architex.co.za/index.php"),
        )

    def test_rejects_relative_paths_and_parent_escape(self) -> None:
        root = "/home/archite4/public_html"
        for value in ("public_html/file", "/home/archite4/public_html/../etc", "/home/archite4/public_html2/file"):
            with self.subTest(value=value), self.assertRaises(CpanelError):
                canonical_remote_path(root, value)


class RedactionTest(unittest.TestCase):
    def test_redacts_non_empty_secrets(self) -> None:
        self.assertEqual(
            "password=[REDACTED] token=[REDACTED]",
            redact_text("password=alpha token=beta", ("alpha", "beta", "")),
        )


class CpanelClientTest(unittest.TestCase):
    def test_redirects_reject_cross_origin_downgrade_and_same_origin(self):
        import urllib.request
        from tools.cpanel_client import RejectRedirects
        request = urllib.request.Request('https://account.example:2083/path', headers={'Authorization':'Basic fixture', 'Cookie':'fixture'})
        for target in ('https://evil.example/path', 'http://account.example:2083/path', 'https://account.example:443/path', 'https://account.example:2083/other'):
            with self.subTest(target=target), self.assertRaises(CpanelError):
                RejectRedirects().redirect_request(request, None, 302, 'redirect', {}, target)

    def test_provider_error_does_not_expose_unknown_runtime_secrets(self):
        client = configured_client(FakeTransport([response({'result': {'status':0, 'errors':['DB_PASSWORD=unknown-runtime-secret']}})]))
        with self.assertRaises(CpanelError) as caught:
            client.list_files('/home/archite4/public_html')
        self.assertNotIn('unknown-runtime-secret', str(caught.exception))

    def test_session_auth_uses_validated_cpsess_prefix_without_authorization_header(self) -> None:
        transport = FakeTransport([response({"status": 1, "errors": None, "data": {"home": "/home/archite4"}})])
        client = CpanelClient(
            CpanelConfig(
                host="cpanel.example.test",
                port=2083,
                username="greg",
                password="",
                account_home="/home/archite4",
                allowed_root="/home/archite4/public_html",
                session_id="cpsess1234567890",
                session_cookie="cpsession=greg%40example.test%3Aopaque",
            ),
            transport=transport,
        )

        client.account_home()

        request = transport.requests[0]
        self.assertEqual("/cpsess1234567890/execute/Variables/get_user_information", urlparse(str(request["url"])).path)
        self.assertNotIn("Authorization", request["headers"])
        self.assertEqual("cpsession=greg%40example.test%3Aopaque", request["headers"]["Cookie"])
        with self.assertRaisesRegex(CpanelError, "session"):
            CpanelClient(CpanelConfig(
                host="cpanel.example.test", port=2083, username="greg", password="",
                account_home="/home/archite4", allowed_root="/home/archite4/public_html",
                session_id="../escape",
                session_cookie="cpsession=opaque",
            ))
        with self.assertRaisesRegex(CpanelError, "cookie"):
            CpanelClient(CpanelConfig(
                host="cpanel.example.test", port=2083, username="greg", password="",
                account_home="/home/archite4", allowed_root="/home/archite4/public_html",
                session_id="cpsess1234567890",
            ))

    def test_account_home_is_read_from_cpanel_and_must_match_configuration(self) -> None:
        transport = FakeTransport([
            uapi_ok({"home": "/home/archite4"}),
            uapi_ok({"home": "/home/someone-else"}),
        ])
        client = configured_client(transport)

        self.assertEqual("/home/archite4", client.account_home())
        with self.assertRaisesRegex(CpanelError, "does not match"):
            client.account_home()

        parsed = urlparse(str(transport.requests[0]["url"]))
        self.assertEqual("/execute/Variables/get_user_information", parsed.path)
        self.assertEqual(["home"], parse_qs(parsed.query)["name"])

    def test_list_files_uses_basic_auth_and_validates_uapi(self) -> None:
        transport = FakeTransport([uapi_ok([{"name": "index.php"}])])
        client = configured_client(transport)

        files = client.list_files("/home/archite4/public_html/php.architex.co.za")

        self.assertEqual([{"name": "index.php"}], files)
        request = transport.requests[0]
        self.assertEqual("GET", request["method"])
        expected = base64.b64encode(b"archite4:panel-secret").decode("ascii")
        self.assertEqual(f"Basic {expected}", request["headers"]["Authorization"])
        parsed = urlparse(str(request["url"]))
        self.assertEqual("/execute/Fileman/list_files", parsed.path)
        self.assertEqual(
            ["/home/archite4/public_html/php.architex.co.za"],
            parse_qs(parsed.query)["dir"],
        )
        self.assertEqual(["1"], parse_qs(parsed.query)["show_hidden"])

    def test_failed_uapi_result_raises_without_secret(self) -> None:
        transport = FakeTransport([response({"result": {"status": 0, "errors": ["denied panel-secret"]}})])
        client = configured_client(transport)

        with self.assertRaises(CpanelError) as caught:
            client.list_files("/home/archite4/public_html")

        self.assertIn("operation failed", str(caught.exception))
        self.assertNotIn("panel-secret", str(caught.exception))

    def test_rejects_non_json_response(self) -> None:
        transport = FakeTransport([HttpResponse(200, {"Content-Type": "text/html"}, b"<html>login</html>")])
        client = configured_client(transport)

        with self.assertRaisesRegex(CpanelError, "JSON contract"):
            client.list_files("/home/archite4/public_html")

    def test_upload_uses_multipart_and_target_filename(self) -> None:
        transport = FakeTransport([uapi_ok()])
        client = configured_client(transport)
        with tempfile.TemporaryDirectory() as temporary:
            source = Path(temporary) / "source.zip"
            source.write_bytes(b"PK\x03\x04test")

            client.upload_file(source, "/home/archite4/public_html/release.zip")

        request = transport.requests[0]
        self.assertEqual("POST", request["method"])
        content_type = str(request["headers"]["Content-Type"])
        self.assertTrue(content_type.startswith("multipart/form-data; boundary="))
        body = bytes(request["body"])
        self.assertIn(b'name="dir"', body)
        self.assertIn(b"/home/archite4/public_html", body)
        self.assertIn(b'filename="release.zip"', body)
        self.assertIn(b"PK\x03\x04test", body)

    def test_save_file_posts_form_content_without_putting_it_in_url(self) -> None:
        transport = FakeTransport([uapi_ok()])
        client = configured_client(transport)

        client.save_file(
            "/home/archite4/public_html/php.architex.co.za/.a777-runtime.php",
            "<?php return ['password' => 'database-secret'];",
        )

        request = transport.requests[0]
        self.assertEqual("POST", request["method"])
        self.assertEqual("application/x-www-form-urlencoded", request["headers"]["Content-Type"])
        self.assertNotIn("database-secret", str(request["url"]))
        form = parse_qs(bytes(request["body"]).decode("utf-8"))
        self.assertEqual(
            ["/home/archite4/public_html/php.architex.co.za"],
            form["dir"],
        )
        self.assertEqual(
            [".a777-runtime.php"],
            form["file"],
        )
        self.assertEqual(["<?php return ['password' => 'database-secret'];"], form["content"])

    def test_mkdir_uses_fileman_api2_with_parent_and_basename(self) -> None:
        transport = FakeTransport([api2_ok()])
        client = configured_client(transport)

        client.mkdir("/home/archite4/public_html/.a777-staging")

        query = parse_qs(urlparse(str(transport.requests[0]["url"])).query)
        self.assertEqual(["mkdir"], query["cpanel_jsonapi_func"])
        self.assertEqual(["/home/archite4/public_html"], query["path"])
        self.assertEqual([".a777-staging"], query["name"])
        self.assertEqual(["0755"], query["permissions"])

    def test_trash_uses_fileop_after_path_containment_check(self) -> None:
        transport = FakeTransport([api2_ok()])
        client = configured_client(transport)

        client.trash("/home/archite4/public_html/old-release")

        query = parse_qs(urlparse(str(transport.requests[0]["url"])).query)
        self.assertEqual(["trash"], query["op"])
        self.assertEqual(["/home/archite4/public_html/old-release"], query["sourcefiles"])
        with self.assertRaises(CpanelError):
            client.trash("/home/archite4/private")

    def test_extract_and_rename_use_fileop(self) -> None:
        transport = FakeTransport([api2_ok(), api2_ok()])
        client = configured_client(transport)

        client.extract_archive("/home/archite4/public_html/release.zip")
        client.rename(
            "/home/archite4/public_html/php.candidate",
            "/home/archite4/public_html/php.architex.co.za",
        )

        first = parse_qs(urlparse(str(transport.requests[0]["url"])).query)
        second = parse_qs(urlparse(str(transport.requests[1]["url"])).query)
        self.assertEqual(["extract"], first["op"])
        self.assertEqual(["rename"], second["op"])
        self.assertEqual(["/home/archite4/public_html/release.zip"], first["sourcefiles"])
        self.assertEqual(["/public_html"], first["destfiles"])
        self.assertEqual(["0"], first["doubledecode"])
        self.assertEqual(["1"], first["filelist"])
        self.assertEqual(["1"], first["multiform"])
        self.assertEqual(
            ["/home/archite4/public_html/php.architex.co.za"],
            second["destfiles"],
        )

    def test_fileop_rejects_failed_data_row_even_when_event_succeeds(self) -> None:
        transport = FakeTransport([api2_ok([{"result": 0, "err": "extract failed"}])])
        client = configured_client(transport)

        with self.assertRaisesRegex(CpanelError, "operation failed"):
            client.extract_archive("/home/archite4/public_html/release.zip")

    def test_exists_matches_exact_basename(self) -> None:
        transport = FakeTransport([uapi_ok([{"name": "php.architex.co.za", "type": "dir"}])])
        client = configured_client(transport)
        self.assertTrue(client.exists("/home/archite4/public_html/php.architex.co.za"))

    def test_exists_matches_current_uapi_fullpath_contract(self) -> None:
        transport = FakeTransport([uapi_ok([{
            "fullpath": "/home/archite4/public_html/php.architex.co.za",
            "type": "dir",
        }])])
        client = configured_client(transport)
        self.assertTrue(client.exists("/home/archite4/public_html/php.architex.co.za"))


if __name__ == "__main__":
    unittest.main()
