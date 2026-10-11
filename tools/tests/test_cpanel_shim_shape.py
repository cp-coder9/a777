import json
import sys
import unittest
from pathlib import Path
from unittest.mock import patch
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import cpanel_ui_release as ui


class ShimShape(unittest.TestCase):
    def test_exact_and_noise_are_value_free_and_binding_unchanged(self):
        secret = b'NEVER_EXPORT_INJECTED_CONFIG_SECRET'
        for name in ('index.php', 'app.php', 'legal.php'):
            exact = ui.shim(name)
            shape = ui.shim_shape(exact, name)
            self.assertEqual(shape['format'], 'private_relocation_exact')
            self.assertEqual(shape['byteCount'], len(exact))
            self.assertEqual(shape['sha256'], ui.digest(exact))
            self.assertTrue(shape['knownPrivateRootPresent'])
            self.assertFalse(shape['oldActiveRootPresent'])
            for content in (exact + secret, exact.replace(b'\n', b'\r\n'), b'\xef\xbb\xbf' + exact,
                            exact.rstrip(b'\n'), exact.replace(b'0077', b'0000'),
                            exact.replace(ui.RUNTIME.encode(), b'/unknown/' + secret),
                            exact.replace(b'require ', b'include ')):
                observation = ui.shim_shape(content, name)
                self.assertEqual(observation['format'], 'unknown')
                self.assertNotIn(secret.decode(), json.dumps(observation))
                self.assertNotIn(ui.RUNTIME, json.dumps(observation))
                with self.assertRaises(ui.Refusal):
                    ui.parse_shim(content, name)

    def test_first_failure_observed_before_config(self):
        class Client:
            def account_home(self): return ui.HOME
            def _uapi(self, *args): return {}
        secret = b'NEVER_EXPORT_INJECTED_CONFIG_SECRET'
        with patch.object(ui, 'domain_roots', return_value=[]), patch.object(ui, 'domain_shape', return_value={}), \
                patch.object(ui, 'read_bytes', return_value=ui.shim('index.php') + secret) as reader:
            report = ui.inspect(Client(), {})
        self.assertEqual(report['errorCode'], 'published_shim_unknown')
        self.assertEqual(report['shimEntry'], 'index.php')
        self.assertEqual(report['shimStage'], 'producer_binding')
        self.assertEqual(report['shimShape']['format'], 'unknown')
        self.assertEqual(reader.call_count, 1)
        self.assertNotIn('configSha256', report)
        self.assertNotIn(secret.decode(), json.dumps(report))

    def test_real_php_producer_bytes(self):
        import shutil
        import subprocess
        php = shutil.which('php')
        if php is None:
            self.skipTest('PHP CLI unavailable')
        root = Path(__file__).resolve().parents[2]
        # Execute reviewed repository producers, NEVER remote shim source.
        for name in ('index.php', 'app.php', 'legal.php'):
            for method, expected_format in (('shim', 'private_relocation_exact'),
                                             ('legacyShim', 'private_legacy_exact')):
                code = ('require "tools/CpanelNativeDeployment.php"; '
                        'require "tools/CpanelPrivateRuntimeRelocation.php"; '
                        f'echo \\Architex\\Deployment\\CpanelPrivateRuntimeRelocation::{method}("{name}");')
                actual = subprocess.run([php, '-r', code], cwd=root, capture_output=True, check=True).stdout
                self.assertEqual(ui.shim_shape(actual, name)['format'], expected_format)
                if method == 'shim':
                    self.assertEqual(actual, ui.shim(name))
                else:
                    with self.assertRaises(ui.Refusal): ui.parse_shim(actual, name)
            code = ('require "tools/CpanelNativeDeployment.php"; '
                    f'echo \\Architex\\Deployment\\CpanelNativeDeployment::publicShim("{name}");')
            actual = subprocess.run([php, '-r', code], cwd=root, capture_output=True, check=True).stdout
            self.assertEqual(ui.shim_shape(actual, name)['format'], 'native_active_exact')
            with self.assertRaises(ui.Refusal): ui.parse_shim(actual, name)

    def test_fileman_byte_roundtrip_and_fixed_request(self):
        raw = bytes(range(256)) + b'\r\n\xef\xbb\xbf\n'
        path = ui.DOCROOT + '/index.php'
        class Client:
            def list_files(self, parent):
                part = path[len(parent) + 1:].split('/')[0]
                return [{'name': part, 'type': 'file' if parent + '/' + part == path else 'dir'}]
            def _uapi(self, module, function, params):
                self.request = (module, function, params)
                return {'content': raw.decode('latin-1'), 'path': 'SECRET_UNKNOWN_PATH',
                        'from_char': 'ISO-8859-1', 'to_char': 'UTF-8',
                        'from_charset': None, 'to_charset': None}
        client = Client()
        self.assertEqual(ui.read_bytes(client, path), raw)
        self.assertEqual(client.request, ('Fileman', 'get_file_content', {
            'dir': ui.DOCROOT, 'file': 'index.php', 'from_charset': 'ISO-8859-1',
            'to_charset': 'UTF-8', 'update_html_document_encoding': '0'}))
        self.assertTrue(client._inspection_shim_encoding['fromCharMatchesRequest'])
        self.assertTrue(client._inspection_shim_encoding['toCharMatchesRequest'])
        self.assertNotIn('SECRET_UNKNOWN_PATH', json.dumps(client._inspection_shim_encoding))

    def test_later_entry_failure_is_named_without_config_read(self):
        class Client:
            def account_home(self): return ui.HOME
            def _uapi(self, *args): return {}
        with patch.object(ui, 'domain_roots', return_value=[]), patch.object(ui, 'domain_shape', return_value={}), \
                patch.object(ui, 'read_bytes', side_effect=[ui.shim('index.php'), ui.shim('app.php') + b'noise']) as reader:
            report = ui.inspect(Client(), {})
        self.assertEqual(report['shimEntry'], 'app.php')
        self.assertEqual(report['shimStage'], 'producer_binding')
        self.assertEqual(report['errorCode'], 'published_shim_unknown')
        self.assertEqual(reader.call_count, 2)
        self.assertNotIn('configSha256', report)
        self.assertNotIn('shimBoundRuntime', report)

    def test_encoding_observation_never_exports_metadata_values(self):
        observation = ui.file_content_shape({'content': '\u00ff\x00\r\n', 'from_charset': 'SECRET',
            'to_charset': 'SECRET', 'encoding': 'SECRET', 'unknown': 'SECRET'})
        self.assertNotIn('SECRET', json.dumps(observation))
        self.assertTrue(observation['latin1Reversible'])
        self.assertEqual(observation['contentType'], 'str')
        self.assertFalse(ui.file_content_shape({'content': '\u0100'})['latin1Reversible'])
        self.assertEqual(ui.file_content_shape({'content': b'x'})['contentType'], 'other')


if __name__ == '__main__':
    unittest.main()
