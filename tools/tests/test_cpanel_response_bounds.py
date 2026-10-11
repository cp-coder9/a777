"""Offline regressions for bounded urllib cPanel JSON reads."""
import io
from pathlib import Path
import sys
import unittest
from unittest.mock import patch
import urllib.error

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from cpanel_client import CpanelClient, CpanelConfig, CpanelError, UrllibTransport


class ReadSpy(io.BytesIO):
    status = 200

    def __init__(self, body, headers=None):
        super().__init__(body)
        self.headers = headers or {}
        self.read_sizes = []

    def read(self, size=-1):
        self.read_sizes.append(size)
        if size < 0:
            raise AssertionError('unbounded response read')
        return super().read(size)


class ResponseBoundsTests(unittest.TestCase):
    def request(self, stream, *, error=False, client=False):
        transport = UrllibTransport()
        transport.MAX_JSON_RESPONSE_BYTES = 16
        opener = unittest.mock.Mock()
        if error:
            opener.open.side_effect = urllib.error.HTTPError(
                'https://example.invalid', 500, 'PRIVATE_PROVIDER_REASON', stream.headers, stream)
        else:
            opener.open.return_value = stream
        with patch('cpanel_client.urllib.request.build_opener', return_value=opener):
            if client:
                config = CpanelConfig('example.invalid', 2083, 'test', 'secret', '/home/test', '/home/test')
                return CpanelClient(config, transport)._request_json('GET', '/execute/Test/read')
            return transport.request('GET', 'https://example.invalid', {}, None, 1)

    def test_default_limit_is_32_mib(self):
        self.assertEqual(UrllibTransport.MAX_JSON_RESPONSE_BYTES, 32 * 1024 * 1024)

    def test_oversize_refusal_is_secret_safe_before_json_parse(self):
        for error in (False, True):
            with self.subTest(error=error):
                stream = ReadSpy(b'PRIVATE_BODY_SECRET' * 10)
                with patch('cpanel_client.json.loads') as decode:
                    with self.assertRaises(CpanelError) as caught:
                        self.request(stream, error=error, client=True)
                    decode.assert_not_called()
                self.assertEqual(str(caught.exception), 'cPanel response limit exceeded.')
                self.assertIsNone(caught.exception.__cause__)
                self.assertTrue(caught.exception.__suppress_context__)
                self.assertEqual(stream.read_sizes, [17])
                self.assertNotIn('PRIVATE', str(caught.exception))
                traceback = caught.exception.__traceback__
                while traceback:
                    if traceback.tb_frame.f_code.co_name == '_read_bounded':
                        self.assertNotIn('body', traceback.tb_frame.f_locals)
                    traceback = traceback.tb_next

    def test_content_length_cannot_bypass_stream_limit(self):
        for length in (None, '1', 'garbage', '-1', '999999999999999999999'):
            for error in (False, True):
                with self.subTest(length=length, error=error):
                    headers = {} if length is None else {'Content-Length': length}
                    stream = ReadSpy(b'x' * 17, headers)
                    with self.assertRaisesRegex(CpanelError, '^cPanel response limit exceeded\\.$'):
                        self.request(stream, error=error, client=True)
                    self.assertEqual(stream.read_sizes, [17])

    def test_exact_limit_json_is_accepted_and_parsed(self):
        transport = UrllibTransport()
        transport.MAX_JSON_RESPONSE_BYTES = 16
        stream = ReadSpy(b'{"ok":true}' + b' ' * 5)
        opener = unittest.mock.Mock()
        opener.open.return_value = stream
        config = CpanelConfig('example.invalid', 2083, 'test', 'secret', '/home/test', '/home/test')
        client = CpanelClient(config, transport)
        with patch('cpanel_client.urllib.request.build_opener', return_value=opener):
            self.assertEqual(client._request_json('GET', '/execute/Test/read'), {'ok': True})
        self.assertEqual(stream.read_sizes, [17])

    def test_exact_limit_http_error_remains_generic(self):
        stream = ReadSpy(b'{"ok":false}' + b' ' * 4)
        response = self.request(stream, error=True)
        self.assertEqual(len(response.body), 16)
        self.assertEqual(stream.read_sizes, [17])
        self.assertTrue(stream.closed)
        stream = ReadSpy(b'{"ok":false}' + b' ' * 4)
        with self.assertRaises(CpanelError) as caught:
            self.request(stream, error=True, client=True)
        self.assertEqual(str(caught.exception), 'cPanel returned HTTP 500.')
        self.assertNotIn('PRIVATE', str(caught.exception))

    def test_error_read_is_bounded_before_return(self):
        stream = ReadSpy(b'{"ok":false}')
        response = self.request(stream, error=True)
        self.assertEqual(response.status, 500)
        self.assertEqual(response.body, b'{"ok":false}')
        self.assertEqual(stream.read_sizes, [17])

    def test_success_read_is_bounded_before_return(self):
        stream = ReadSpy(b'{"ok":true}')
        self.assertEqual(self.request(stream).body, b'{"ok":true}')
        self.assertEqual(stream.read_sizes, [17])


if __name__ == '__main__':
    unittest.main()
