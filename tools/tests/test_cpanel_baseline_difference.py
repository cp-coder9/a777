import sys
import unittest
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import cpanel_ui_release as ui


class BaselineDifference(unittest.TestCase):
    def setUp(self):
        self.source = {'baselineSha256': ui.digest(b'original\n'), 'baselineBytes': 9}
        self.row = {'sha256': ui.digest(b'original\r\n'), 'bytes': 10}

    def test_manifest_failure_does_not_claim_actual_read(self):
        evidence = ui.baseline_difference('bootstrap.php', self.source, self.row)
        self.assertEqual(evidence['relativePath'], 'bootstrap.php')
        self.assertEqual(evidence['stage'], 'manifest_vs_baseline')
        self.assertFalse(evidence['actualRead'])
        self.assertIsNone(evidence['actualSha256'])
        self.assertFalse(evidence['manifestMatchesExpected'])
        self.assertNotIn('original', str(evidence))

    def test_actual_bytes_are_not_normalized(self):
        evidence = ui.baseline_difference('src/example.php', self.source, self.row, b'original\r\n')
        self.assertTrue(evidence['actualMatchesManifest'])
        self.assertNotEqual(evidence['actualSha256'], evidence['expectedSha256'])
        self.assertEqual(evidence['actualBytes'], 10)
        self.assertEqual(evidence['stage'], 'actual_vs_baseline')

    def test_inspection_preserves_both_failure_gates(self):
        import json
        from unittest.mock import patch
        from tools.tests.test_cpanel_ui_release import Contracts
        candidate = {'commit': 'b' * 40, 'inventorySha256': 'c' * 64, 'changes': [],
                     'files': {'app/bootstrap.php': self.source}}
        class Client:
            def account_home(self): return ui.HOME
            def _uapi(self, *args): return Contracts.hash_inventory()
        for mismatch in ('manifest', 'actual'):
            row = self.row if mismatch == 'manifest' else {
                'sha256': self.source['baselineSha256'], 'bytes': self.source['baselineBytes']}
            data = {ui.DOCROOT + '/' + name: ui.shim(name)
                    for name in ('index.php', 'app.php', 'legal.php')}
            data.update({ui.RUNTIME + '/config/runtime.php': b'PRIVATE-FIXTURE',
                ui.RUNTIME + '/release.json': json.dumps({'format': 'architex-release-v1',
                    'sourceCommit': ui.BASELINE, 'sourceDirty': False,
                    'environment': 'production', 'version': 'v1'}).encode(),
                ui.RUNTIME + '/release-manifest.json': json.dumps({
                    'format': 'architex-release-manifest-v1', 'version': 'v1',
                    'files': {'bootstrap.php': row}}).encode(),
                ui.RUNTIME + '/bootstrap.php': b'original\r\n'})
            reads = []
            def reader(client, path):
                reads.append(path)
                return data[path]
            with patch.object(ui, 'read_bytes', side_effect=reader):
                report = ui.inspect(Client(), candidate)
            self.assertEqual(report['errorCode'], 'live_manifest_baseline_mismatch'
                             if mismatch == 'manifest' else 'live_bytes_baseline_mismatch')
            self.assertFalse(report['inspectionComplete'])
            self.assertFalse(report['writesPerformed'])
            self.assertFalse(report['deploymentAuthorized'])
            self.assertEqual(report['firstBaselineDifference']['actualRead'], mismatch == 'actual')
            self.assertEqual(ui.RUNTIME + '/bootstrap.php' in reads, mismatch == 'actual')
            self.assertNotIn('PRIVATE-FIXTURE', str(report))

    def test_private_and_unapproved_paths_are_redacted(self):
        for path in ('config/runtime.php', 'config/example.php', '.env', 'src/.env',
                     'src/credentials.php', 'src/../config/runtime.php', 'tests/example.php',
                     'vendor/example.php', '/absolute.php'):
            with self.subTest(path=path):
                evidence = ui.baseline_difference(path, self.source, self.row)
                self.assertFalse(evidence['pathReported'])
                self.assertNotIn('relativePath', evidence)
                self.assertNotIn('expectedSha256', evidence)


if __name__ == '__main__':
    unittest.main()
