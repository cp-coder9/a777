import json
import sys
import unittest
from pathlib import Path
from unittest.mock import patch
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import cpanel_ui_release as ui


class ShimShape(unittest.TestCase):
    def test_stale_binding_revoked_before_account_and_domain_failures(self):
        for failure in ('account', 'domain'):
            class Client:
                _inspection_bound_runtime = ui.RUNTIME
                _inspection_active = True
                def account_home(self):
                    if failure == 'account': raise RuntimeError('secret')
                    return ui.HOME
                def _uapi(self, *args): raise RuntimeError('secret')
                def list_files(self, *args): raise AssertionError('transport reached')
            client = Client()
            ui.inspect(client, {})
            self.assertIsNone(client._inspection_bound_runtime)
            with self.assertRaises(ui.Refusal):
                ui.read_bytes(client, ui.RUNTIME + '/config/runtime.php')

    def test_oversize_observation_does_not_scan_or_hash(self):
        content = b'SECRET' * 1000
        with patch.object(ui, 'digest', side_effect=AssertionError('hash called')):
            self.assertEqual(ui.shim_shape(content, 'index.php'), {'oversize': True})
        self.assertEqual(ui.file_content_shape({'content': content.decode()}), {'oversize': True})
        with self.assertRaises(ui.Refusal): ui.parse_shim(b'x' * 1025, 'index.php')

    def test_public_read_bounds_before_encoding(self):
        path = ui.DOCROOT + '/index.php'
        class Client:
            def list_files(self, parent):
                part = path[len(parent) + 1:].split('/')[0]
                return [{'name': part, 'type': 'file' if parent + '/' + part == path else 'dir'}]
            def _uapi(self, *args): return {'content': 'SECRET' * 1000}
        client = Client()
        with self.assertRaises(ui.Refusal) as caught: ui.read_bytes(client, path)
        self.assertEqual(caught.exception.code, 'published_shim_oversize')
        self.assertEqual(client._inspection_shim_encoding, {'oversize': True})

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

    def test_literal_path_shape_is_diagnostic_only(self):
        component = b'12345678-1234-1234-1234-123456789abc'
        source = ui.shim('index.php').replace(ui.RUNTIME.encode(),
            (ui.HOME + '/.a777-runtime-private/').encode() + component)
        self.assertEqual(len(source), 146)
        shape = ui.shim_shape(source, 'index.php')
        self.assertEqual(shape['literalComponentType'], 'uuid36')
        self.assertEqual(shape['literalComponentLength'], 36)
        for flag in ('fixedPrivateParentPresent', 'expectedEntryMatched',
                     'rootInsideVerifiedAccount', 'producerWhitespaceEnvelopeMatched'):
            self.assertTrue(shape[flag])
        self.assertEqual(shape['format'], 'unknown')
        self.assertFalse(shape['accountScopedStructureMatched'])
        self.assertNotIn(component.decode(), json.dumps(shape))
        self.assertNotIn(ui.HOME, json.dumps(shape))
        with self.assertRaises(ui.Refusal): ui.parse_shim(source, 'index.php')
        for bad in (source + b'noise', source.replace(b'\n', b'\r\n'),
                    source.replace(b'/public/index.php', b'/public/app.php'),
                    source.replace(component, b'..'),
                    source.replace(component, b'x/y'),
                    source.replace(component, b'x' * 129)):
            observation = ui.shim_shape(bad, 'index.php')
            self.assertEqual(observation['literalComponentType'], 'unmatched')
            with self.assertRaises(ui.Refusal): ui.parse_shim(bad, 'index.php')
        other = source.replace(component, b'PRIVATE_SENTINEL')
        self.assertEqual(ui.shim_shape(other, 'index.php')['literalComponentType'], 'other')
        self.assertNotIn('PRIVATE_SENTINEL', json.dumps(ui.shim_shape(other, 'index.php')))
        self.assertEqual(ui.shim_shape(ui.shim('index.php'), 'index.php')['literalComponentType'], 'lowercasehex32')

    def test_current_runtime_discovery_and_three_entry_binding(self):
        root = ui.HOME + '/.a777-runtime-private/' + '1234567890abcdef' * 2
        sources = [ui.shim(name).replace(ui.RUNTIME.encode(), root.encode())
                   for name in ('index.php', 'app.php', 'legal.php')]
        for name, source in zip(('index.php', 'app.php', 'legal.php'), sources):
            self.assertEqual(ui.parse_shim(source, name), root)
            self.assertTrue(ui.shim_shape(source, name)['accountScopedStructureMatched'])
            self.assertFalse(ui.shim_shape(source, name)['historicalTemplateParity'])
            self.assertFalse(ui.shim_shape(source, name)['currentProducerProvenanceVerified'])
        class Client:
            def account_home(self): return ui.HOME
            def _uapi(self, *args): return {}
        with patch.object(ui, 'domain_roots', return_value=[ui.DOCROOT]), \
                patch.object(ui, 'read_bytes', side_effect=sources + [ui.Refusal('fixture_config_unavailable')]) as reader:
            report = ui.inspect(Client(), {})
        self.assertEqual(report['errorCode'], 'fixture_config_unavailable')
        self.assertTrue(report['runtimeRootBindingVerified'])
        self.assertEqual(report['activeRuntimeSha256'], ui.digest(root.encode()))
        self.assertEqual(reader.call_args.args[1], root + '/config/runtime.php')
        self.assertNotIn(root, json.dumps(report))

    def test_roots_must_match_and_be_outside_every_document_root(self):
        class Client:
            def account_home(self): return ui.HOME
            def _uapi(self, *args): return {}
        other = ui.HOME + '/.a777-runtime-private/' + 'a' * 32
        for sources, roots, code in (
                ([ui.shim('index.php'), ui.shim('app.php').replace(ui.RUNTIME.encode(), other.encode()), ui.shim('legal.php')],
                 [ui.DOCROOT], 'published_shim_roots_mismatch'),
                ([ui.shim(n) for n in ('index.php', 'app.php', 'legal.php')],
                 [ui.DOCROOT, ui.HOME], 'runtime_inside_document_root')):
            with patch.object(ui, 'domain_roots', return_value=roots), patch.object(ui, 'read_bytes', side_effect=sources) as reader:
                report = ui.inspect(Client(), {})
            self.assertEqual(report['errorCode'], code)
            self.assertEqual(reader.call_count, 3)
            self.assertNotIn('configSha256', report)
            self.assertIsNone(getattr(Client(), '_inspection_bound_runtime', None))

    def test_dynamic_grammar_rejects_unreviewed_paths(self):
        source = ui.shim('index.php')
        for root in (ui.HOME + '/.a777-runtime-private/' + 'A' * 32,
                     ui.HOME + '/.a777-runtime-private/' + 'a' * 33,
                     '/home/other/.a777-runtime-private/' + 'a' * 32,
                     ui.HOME + '/public_html/' + 'a' * 32,
                     ui.RUNTIME + '/..', ui.RUNTIME + '/suffix'):
            with self.assertRaises(ui.Refusal):
                ui.parse_shim(source.replace(ui.RUNTIME.encode(), root.encode()), 'index.php')

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
        self.assertEqual(report['shimStage'], 'structural_binding')
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
        self.assertEqual(report['shimStage'], 'structural_binding')
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
