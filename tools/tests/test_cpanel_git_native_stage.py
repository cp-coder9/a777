import json
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch, Mock
import zipfile
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import cpanel_native_stage as n
from cpanel_client import CpanelError

SHA = 'a' * 40
PIN = '5206e3a1f9447e6834fc3e2dd7cd254ce5dd47af33406c0c83085c4b4f28d0b8'

class NativeArtifactTests(unittest.TestCase):
    def test_task_failure_retains_recovery_token_and_never_claims_deployment(self):
        import contextlib
        artifact = {'artifactTreeSha256': 'a'*64, 'artifactManifestSha256': n.digest(b'{}'), 'buildSha256': 'b'*64}
        with tempfile.TemporaryDirectory() as t, contextlib.ExitStack() as stack:
            root = Path(t); (root/'database/migrations').mkdir(parents=True)
            for i in range(8): (root/'database/migrations'/('%04d_test.sql'%i)).write_text('')
            stack.enter_context(patch.object(n, 'build', return_value=(root, artifact)))
            stack.enter_context(patch.object(n, 'archive_release'))
            for name in ('authorize','owned_mode','deployment_inventory_guard','private_chmod','provision_token'):
                stack.enter_context(patch.object(n.p, name))
            stack.enter_context(patch.object(n.p, 'inventory', return_value={}))
            stack.enter_context(patch.object(n.p, 'clone', return_value={'status':'cloned_and_pulled','commit':SHA}))
            stack.enter_context(patch.object(n.p, 'exists', return_value=False))
            stack.enter_context(patch.object(n.p, 'entries', return_value=[]))
            stack.enter_context(patch.object(n.p, 'read', return_value='{}'))
            stack.enter_context(patch.object(n, 'save_plan', return_value='c'*64))
            recovery = {'phase':'recovery_required','deployed':False,'maintenanceActive':True}
            stack.enter_context(patch.object(n, 'native_receipt', return_value=recovery))
            cleanup = stack.enter_context(patch.object(n.p, 'cleanup_token', return_value=True))
            client = Mock()
            def uapi(module, function, args):
                if function == 'create': return {'repository_root': n.p.ROOT, 'deploy_id': 42, 'task_id':'task/42'}
                return [{'repository_root':n.p.ROOT,'deploy_id':42,'task_id':'task/42','repository_state':{'identifier':SHA},'timestamps':{'failed':1}}]
            client._uapi.side_effect=uapi
            report=n.native_deploy(client, root, root, SHA, PIN, attempts=1, pause=0)
            self.assertTrue(report['tokenRetainedForRecovery']); self.assertFalse(report['tokenAbsenceVerified'])
            self.assertFalse(report['runtime_deployed']); cleanup.assert_not_called()
            recovery.update(phase='failed', maintenanceActive=False, sourcePrivilegesRevoked=False, sourceDatabaseDdlTouched=False,
                            sourcePrivilegeStateUnknown=False,operatorStateUnknown=False,operatorPrivilegesRevoked=True)
            report=n.native_deploy(client, root, root, SHA, PIN, attempts=1, pause=0)
            self.assertTrue(report['tokenAbsenceVerified']); self.assertFalse(report['runtime_deployed'])
            self.assertFalse(report['tokenRetainedForRecovery'])

    def test_wrong_commit_and_private_output(self):
        with self.assertRaises(CpanelError): n.commit('A' * 40)
        with self.assertRaises(CpanelError): n.build('.', './output', SHA)

    def test_deterministic_archive_and_actual_manifest_binding(self):
        # Synthetic release shape; real production build is independently exercised.
        with tempfile.TemporaryDirectory() as t:
            root = Path(t) / 'release'; root.mkdir()
            files = {'release.json': json.dumps({'sourceCommit': SHA, 'sourceDirty': False, 'environment': 'production'}),
                     'public/.htaccess': 'Deny from all', 'vendor/autoload.php': '<?php', 'bootstrap.php': '<?php'}
            files.update({'src/f%04d.php' % i: '<?php' for i in range(766)})
            for name, content in files.items():
                p = root / name; p.parent.mkdir(parents=True, exist_ok=True); p.write_text(content)
            (root / 'release-manifest.json').write_text(json.dumps({'format': 'architex-release-manifest-v1', 'files': n.inventory(root)}))
            a, b = Path(t) / 'a.zip', Path(t) / 'b.zip'
            n.archive_release(root, a, SHA); n.archive_release(root, b, SHA)
            self.assertEqual(a.read_bytes(), b.read_bytes())
            self.assertIn(SHA + '/public/.htaccess', zipfile.ZipFile(a).namelist())
            with self.assertRaises(CpanelError): n.validate_release(root, 'b' * 40)
            (root / 'vendor/autoload.php').write_text('tampered')
            with self.assertRaises(CpanelError): n.validate_release(root, SHA)

    def test_archive_traversal_and_symlink(self):
        for name, mode in [(SHA + '/../evil', 0o100600), (SHA + '/link', 0o120777), ('/absolute', 0o100600)]:
            with tempfile.TemporaryDirectory() as t:
                archive = Path(t) / 'bad.zip'
                with zipfile.ZipFile(archive, 'w') as z:
                    for i in range(766):
                        info = zipfile.ZipInfo(SHA + '/f' + str(i)); info.external_attr = 0o100600 << 16; z.writestr(info, b'x')
                    info = zipfile.ZipInfo(name); info.external_attr = mode << 16; z.writestr(info, b'x')
                with self.assertRaises(CpanelError): n.validate_archive(archive, SHA)

    def test_receipt_never_promotes_failure_and_drops_unknown_secrets(self):
        name = 'native-deploy-' + SHA + '-' + 'b' * 16 + '.json'
        data = {'format': 'a777-cpanel-native-deploy-v1', 'mode': 'native-deploy', 'commit': SHA,
                'planSha256': 'c' * 64, 'phase': 'recovery_required', 'deployed': False,
                'sourceMigrationCount': 8, 'password': 'must-not-export'}
        with patch.object(n.p, 'entries', return_value=[{'name': name}]), patch.object(n.p, 'owned_mode'), patch.object(n.p, 'read', side_effect=lambda *a: json.dumps(data)):
            self.assertNotIn('password', n.native_receipt(None, SHA, 'c' * 64, set()))
            data['deployed'] = True
            with self.assertRaises(CpanelError): n.native_receipt(None, SHA, 'c' * 64, set())
            data['deployed'] = False; data['planSha256'] = 'd' * 64
            with self.assertRaises(CpanelError): n.native_receipt(None, SHA, 'c' * 64, set())

    def test_invalid_pin_does_not_contact_provider(self):
        client = Mock()
        report = n.native_deploy(client, '.', '.', SHA, '1' * 64)
        self.assertEqual('failed', report['status'])
        client.assert_not_called(); self.assertEqual([], client.mock_calls)

    def test_complete_receipt_requires_resolved_operator_and_source_safety(self):
        name='native-deploy-'+SHA+'-'+'b'*16+'.json'
        data=dict(format='a777-cpanel-native-deploy-v1',mode='native-deploy',commit=SHA,planSha256='c'*64,
                  phase='complete',deployed=True,readinessPassed=True,artifactVerified=True,
                  maintenanceActive=False,maintenanceRetained=False,sourcePrivilegesRevoked=False,
                  sourcePrivilegeStateUnknown=False,operatorStateUnknown=False,operatorPrivilegesRevoked=True)
        with patch.object(n.p,'entries',return_value=[{'name':name}]),patch.object(n.p,'owned_mode'),patch.object(n.p,'read',side_effect=lambda *a:json.dumps(data)):
            self.assertTrue(n.native_receipt(None,SHA,'c'*64,set())['operatorPrivilegesRevoked'])
            for key in ('maintenanceActive','sourcePrivilegesRevoked','sourcePrivilegeStateUnknown','operatorStateUnknown'):
                data[key]=True
                with self.assertRaises(CpanelError): n.native_receipt(None,SHA,'c'*64,set())
                data[key]=False
            data['operatorPrivilegesRevoked']=False
            with self.assertRaises(CpanelError): n.native_receipt(None,SHA,'c'*64,set())

    def test_completed_rehearsal_requires_independent_inventory(self):
        old = {'mode': 'restore-rehearsal', 'account': 'archite4', 'repository': n.p.ROOT, 'expectedCommit': SHA}
        original = json.dumps(old)
        receipt = {'commit': SHA, 'planSha256': n.digest(original.encode()), 'isolatedRehearsalPassed': True,
                   'status': 'rehearsal-verified', 'lastCompletedStage': 'rehearsal-verified',
                   'sourceDatabaseDdlTouched': False, 'liveFilesTouched': False,
                   'targetDatabase': 'archite4_ar_' + 'a' * 16 + '_test'}
        client = Mock(); client._uapi.return_value = [{'database': receipt['targetDatabase']}]
        with patch.object(n.p, 'entries', return_value=[{'name': 'restore-rehearsal-' + SHA + '-' + 'b' * 16 + '.json'}]), patch.object(n.p, 'owned_mode'), patch.object(n.p, 'read', return_value=json.dumps(receipt)), patch.object(n.p, 'rehearsal_safe', return_value=True):
            self.assertEqual(n.digest(original.encode()), n.completed_rehearsal(client, old, original))
            client._uapi.return_value = []
            with self.assertRaises(CpanelError): n.completed_rehearsal(client, old, original)

if __name__ == '__main__': unittest.main()
