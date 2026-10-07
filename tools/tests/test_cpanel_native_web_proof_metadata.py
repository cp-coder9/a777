"""Explicitly synthetic metadata fixtures; no provider or live readiness proof."""
import json
from pathlib import Path
import sys
import unittest
from unittest.mock import Mock, patch
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import cpanel_native_stage as n
import cpanel_native_recover as r
from cpanel_client import CpanelError


class MetadataTests(unittest.TestCase):
    def fixture(self):
        return {'status': 503, 'webSapi': 'fpm-fcgi', 'probeId': 'SECRET_NONCE',
                'build': {'secret': 'SECRET_BUILD'}, 'rawConfig': 'SECRET_CONFIG',
                'body': json.dumps({'data': {'status': 'unavailable', 'components': {
                    'database': {'status': 'ready', 'password': 'SECRET_PASSWORD'},
                    'migrations': {'status': 'unavailable'}, 'jobs': {'status': 'ready'},
                    'mail': {'status': 'degraded'}, 'unknown': 'SECRET_COMPONENT'}},
                    'error': 'SECRET_ERROR'})}

    def test_503_retained_and_only_known_metadata_exported(self):
        result = n.web_proof_metadata(self.fixture())
        self.assertEqual(result['status'], 503)
        self.assertEqual(result['readinessStatus'], 'unavailable')
        self.assertEqual(result['components'], dict(database='ready', migrations='unavailable', jobs='ready', mail='degraded'))
        self.assertEqual(set(result), {'status', 'webSapi', 'readinessStatus', 'components', 'hasData', 'hasComponents', 'bodyClassifier'})
        self.assertNotIn('SECRET', json.dumps(result))
        self.assertTrue(result['hasData']); self.assertTrue(result['hasComponents'])

    def test_unknown_and_nonstring_values_cannot_escape(self):
        for value in ('SECRET', [], {}, 1, True, None):
            fixture = self.fixture()
            fixture['webSapi'] = value
            fixture['body'] = json.dumps({'data': {'status': value, 'components': {'database': {'status': value}}}})
            result = n.web_proof_metadata(fixture)
            self.assertIsNone(result['webSapi']); self.assertIsNone(result['readinessStatus'])
            self.assertIsNone(result['components']['database'])
        for value in (True, '503', None, 0, 600):
            with self.assertRaises(CpanelError): n.web_proof_metadata(dict(self.fixture(), status=value))

    def test_classification_does_not_claim_bootstrap_cause(self):
        for body in ('not json SECRET', '{}', '{"data":null}'):
            result = n.web_proof_metadata(dict(self.fixture(), body=body))
            self.assertEqual(result['bodyClassifier'], 'no_data')
            self.assertFalse(result['hasData']); self.assertFalse(result['hasComponents'])
        result = n.web_proof_metadata(dict(self.fixture(), body=json.dumps({'data': {'status': None, 'components': {}}})))
        self.assertEqual(result['bodyClassifier'], 'sanitized_empty_data')
        self.assertTrue(result['hasData']); self.assertTrue(result['hasComponents'])

    def test_all_matching_files_private_read_fixed_work_no_name_export(self):
        work = n.p.DEPLOY_ROOT + '/deployment-' + 'd'*32
        names = ['web-proof-'+'a'*48+'.json', 'web-proof-'+'b'*48+'.json']
        rows = [{'name': name} for name in names + ['web-proof.json', 'web-proof-'+'c'*47+'.json', 'runtime.php']]
        with patch.object(n.p, 'owned_mode') as mode, patch.object(n.p, 'entries', return_value=rows), patch.object(r, 'private_read', return_value=json.dumps(self.fixture())) as read:
            result = n.private_web_proofs(Mock(), work)
            mode.assert_called_once_with(unittest.mock.ANY, work, '0700', True)
            self.assertEqual([call.args[1] for call in read.call_args_list], [work+'/'+name for name in names])
            self.assertEqual(len(result), 2)
            for name in names: self.assertNotIn(name, json.dumps(result))

    def test_work_scope_refused_before_any_provider_read(self):
        client = Mock()
        for work in ('/home/other', n.p.DEPLOY_ROOT+'/deployment-'+'d'*32+'/..', n.p.DEPLOY_ROOT+'/deployment-'+'d'*31, None):
            with self.assertRaises(CpanelError): n.private_web_proofs(client, work)
        client.assert_not_called()
        self.assertEqual(client.mock_calls, [])

    def test_actual_private_read_requires_0600(self):
        work = n.p.DEPLOY_ROOT + '/deployment-' + 'd'*32
        name = 'web-proof-'+'a'*48+'.json'
        with patch.object(n.p, 'owned_mode') as mode, patch.object(n.p, 'entries', return_value=[{'name': name}]), patch.object(n.p, 'read', return_value=json.dumps(self.fixture())) as read:
            n.private_web_proofs(Mock(), work)
            self.assertEqual([call.args[1:] for call in mode.call_args_list], [(work, '0700', True), (work+'/'+name, '0600')])
            read.assert_called_once()

    def test_roots_are_fixed_metadata_only_no_posix_claim(self):
        with patch.object(n.p, 'owned_mode') as mode:
            result = n.private_root_metadata(Mock())
        self.assertEqual([call.args[1:] for call in mode.call_args_list], [(root, '0700', True) for label, root in n.PRIVATE_RUNTIME_ROOTS])
        self.assertEqual(result['origin'], 'providerFileman')
        self.assertEqual(result['canonical'], 'unknown'); self.assertFalse(result['nativePosixVerified'])
        with patch.object(n.p, 'owned_mode', side_effect=CpanelError('SECRET')):
            result = n.private_root_metadata(Mock())
        self.assertTrue(all(row == {'status': 'unknown', 'mode': None} for row in result['roots'].values()))
        self.assertNotIn('SECRET', json.dumps(result))


if __name__ == '__main__': unittest.main()
