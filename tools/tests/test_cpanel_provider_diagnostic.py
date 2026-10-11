import json
import sys
import unittest
from pathlib import Path
from unittest.mock import patch
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import cpanel_ui_release as ui
import test_cpanel_ui_release as contracts


class ProviderDiagnostics(unittest.TestCase):
    def test_envelope_refusals_are_value_free(self):
        secret = 'SECRET-PROVIDER-STRING'
        client = object.__new__(ui.InspectionClient)
        client._inspection_phase = 'runtimefile'
        client._inspection_relative = 'src/a.php'
        client._inspection_diagnostic_paths = frozenset({'src/a.php'})
        fixtures = [
            {'result': {'status': 0, 'errors': [secret]}},
            {'result': {'status': True, 'errors': None}},
            {'result': {'status': secret, 'errors': secret}},
            {'result': {'errors': []}},
            {'result': [secret]},
            {'result': {'status': 1, 'errors': [secret]}},
            {'result': {'status': 1, 'errors': {'message': secret}}},
        ]
        for payload in fixtures:
            with self.subTest(payload=fixtures.index(payload)):
                with patch.object(ui.CpanelClient, '_request_json', return_value=payload):
                    with self.assertRaises(ui.Refusal) as caught:
                        client._request_json('GET', '/execute/Fileman/get_file_content')
                error = caught.exception
                self.assertEqual(error.code, 'provider_envelope_unknown')
                self.assertEqual(error.diagnostic['method'], 'GET')
                self.assertEqual(error.diagnostic['relativeFile'], 'src/a.php')
                self.assertNotIn(secret, json.dumps(error.diagnostic))
                result = payload['result']
                status = result.get('status') if type(result) is dict else None
                self.assertEqual('status' in error.diagnostic, type(status) is int)
        for status in (1,):
            for errors in (None, []):
                payload = {'result': {'status': status, 'errors': errors}}
                with patch.object(ui.CpanelClient, '_request_json', return_value=payload):
                    self.assertIs(client._request_json('GET', '/execute/Fileman/get_file_content'), payload)

    def test_fixed_operations_and_path_redaction(self):
        for operation in ui.InspectionClient._READ_PATHS:
            for phase in ('shim', 'configmeta', 'manifest', 'runtimefile', 'publicstatic', 'configrepeat', 'SECRET'):
                diagnostic = ui.provider_diagnostic({'result': {'status': 0}}, operation, phase, 'SECRET')
                self.assertEqual(diagnostic['operation'], operation)
                self.assertNotIn('SECRET', json.dumps(diagnostic))
                self.assertNotIn('relativeFile', diagnostic)

    def test_completed_progress_survives_first_refusal(self):
        root = ui.RUNTIME
        content = b'checked bytes'
        sha = ui.digest(content)
        candidate = {'commit': 'b' * 40, 'inventorySha256': 'c' * 64, 'changes': [],
                     'files': {name: {'baselineSha256': sha, 'baselineBytes': len(content)}
                               for name in ('app/src/a.php', 'app/public/a.css', 'app/public/b.png')}}
        metadata = {'format': 'architex-release-v1', 'sourceCommit': ui.BASELINE,
                    'sourceDirty': False, 'environment': 'production', 'version': 'v1'}
        manifest = {'format': 'architex-release-manifest-v1', 'version': 'v1',
                    'files': {name[4:]: {'sha256': sha, 'bytes': len(content)} for name in candidate['files']}}
        class Client:
            def account_home(self): return ui.HOME
            def _uapi(self, module, function, params): return contracts.Contracts().hash_inventory()
        def reader(client, path):
            if path in {ui.DOCROOT + '/' + n for n in ('index.php', 'app.php', 'legal.php')}:
                return ui.shim(path.rsplit('/', 1)[1])
            if path == root + '/config/runtime.php': return b'SYNTHETIC-CONFIG-SECRET'
            if path == root + '/release.json': return json.dumps(metadata).encode()
            if path == root + '/release-manifest.json': return json.dumps(manifest).encode()
            if path == root + '/public/b.png':
                raise ui.Refusal('provider_envelope_unknown', diagnostic=ui.provider_diagnostic(
                    {'result': {'status': 0, 'errors': ['PROVIDER-SECRET']}},
                    '/execute/Fileman/get_file_content', client._inspection_phase,
                    client._inspection_relative, client._inspection_diagnostic_paths))
            return content
        with patch.object(ui, 'read_bytes', side_effect=reader):
            client = Client()
            report = ui.inspect(client, candidate)
        self.assertEqual(report['verifiedRuntimeFiles'], 2)
        self.assertEqual(report['verifiedStaticFiles'], 1)
        self.assertEqual(report['boundShimCount'], 3)
        self.assertEqual(report['errorCode'], 'provider_envelope_unknown')
        self.assertEqual(report['providerDiagnostic']['relativeFile'], 'public/b.png')
        self.assertFalse(report['inspectionComplete'])
        self.assertNotIn('firstBaselineDifference', report)
        self.assertNotIn('PROVIDER-SECRET', json.dumps(report))
        self.assertNotIn('SYNTHETIC-CONFIG-SECRET', json.dumps(report))
        self.assertIsNone(client._inspection_relative)


if __name__ == '__main__':
    unittest.main()
