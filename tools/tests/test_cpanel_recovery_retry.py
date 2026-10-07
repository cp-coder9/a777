import json
import sys
from pathlib import Path
import unittest
from unittest.mock import Mock, patch
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import cpanel_native_recover as r
from cpanel_client import CpanelError
from test_cpanel_git_native_recover import RecoveryTests

class RetryTests(RecoveryTests):
    def test_failure_metadata_preserves_unknown_flags(self):
        data = dict(self.receipt, phase='failed', deployed=False, sourcePrivilegeStateUnknown=True, operatorStateUnknown=True, maintenanceRetained=True)
        name = 'native-recover-' + self.new + '-' + '0'*16 + '.json'
        with patch.object(r.p, 'entries', return_value=[{'name': name}, {'name': 'password-journal.json'}]), patch.object(r, 'private_read', return_value=json.dumps(data)) as read:
            result = r.recovery_metadata(Mock(), self.new, 'f'*64, self.sha, self.old, self.prior, set())
            self.assertTrue(result['sourcePrivilegeStateUnknown'])
            self.assertFalse(result['deployed'])
            read.assert_called_once()
            self.assertIsNone(r.recovery_metadata(Mock(), self.new, 'f'*64, self.sha, self.old, self.prior, {name}))
    def test_guard_rejects_unknown_newer_success_and_duplicate(self):
        good = {'repository_root': r.p.ROOT, 'repository_state': {'identifier': self.old}, 'deploy_id': '10', 'task_id': 'task/10', 'timestamps': {'failed': 100}}
        client = Mock()
        client._uapi.return_value = [good]
        self.assertEqual(r.failed_recovery_guard(client, self.old), good)
        for rows in ([], [dict(good, timestamps={})], [dict(good, timestamps={'succeeded':100})], [good, dict(good, deploy_id='11')], [good, dict(good, deploy_id='11', repository_state={'identifier':self.new}, timestamps={})], [good, dict(good, deploy_id='11', repository_state={'identifier':self.new}, timestamps={'failed':101})]):
            client._uapi.return_value = rows
            with self.subTest(rows=rows), self.assertRaises(CpanelError): r.failed_recovery_guard(client, self.old)
        client.save_file.assert_not_called()
    def test_pointer_cross_release_hash_and_private_fields_rejected(self):
        plan = {'mode':'native-recover', 'expectedRecoveryCommit':self.new, 'releaseCommit':self.old}
        shadow = dict(plan, expectedRecoveryCommit=self.old)
        raw = r.encoded(shadow)+'\n'
        pointer = {'mode':'native-recover','expectedRecoveryCommit':self.old,'planPath':r.p.DEPLOY_ROOT+'/recovery-plan-'+self.old+'.json','planSha256':r.digest(raw.encode('ascii'))}
        client = Mock()
        def read(_client, path):
            if path == r.POINTER: return json.dumps(pointer)
            if path == pointer['planPath']: return raw
            if path == r.p.PLAN: return 'original'
            return 'consumed'
        with patch.object(r, 'private_read', side_effect=read), patch.object(r, 'failed_recovery_guard'):
            r.validated_pointer(client, plan, 'original', 'marker', 'consumed')
            for bad in ({'planSha256':'0'*64}, {'planPath':'/different'}, {'password':'never-export'}):
                old = dict(pointer); pointer.update(bad)
                with self.assertRaises(CpanelError): r.validated_pointer(client, plan, 'original', 'marker', 'consumed')
                pointer.clear(); pointer.update(old)
            with self.assertRaises(CpanelError): r.validated_pointer(client, dict(plan, releaseCommit=self.new), 'original', 'marker', 'consumed')
        client.save_file.assert_not_called()
    def test_untriggered_exact_plan_resume_not_replay(self):
        client = Mock()
        row = {'repository_root':r.p.ROOT, 'repository_state':{'identifier':self.old}, 'deploy_id':'10', 'task_id':'task/10', 'timestamps':{'failed':100}}
        client._uapi.return_value = [row]
        plan = {'expectedRecoveryCommit':self.new, 'releaseCommit':'1'*40}
        pointer = {'expectedRecoveryCommit':self.new}
        with patch.object(r,'private_read',return_value=r.encoded(dict(plan,expectedRecoveryCommit=self.old))+'\n'):
            self.assertEqual(r.pointer_guard_commit(client,plan,pointer),self.old)
            client._uapi.return_value = [row, dict(row, deploy_id='11', repository_state={'identifier':self.new})]
            with self.assertRaises(CpanelError): r.pointer_guard_commit(client,plan,pointer)
        client.save_file.assert_not_called()

    def test_diag_fixed_fields_and_source_only(self):
        name = 'recoverydiag-'+self.new+'-'+'0'*16+'.json'
        good = {'file':'CpanelNativeRecovery.php', 'reasonCode':'operation_refused', 'line':10}
        with patch.object(r.p, 'entries', return_value=[{'name':name}]), patch.object(r, 'private_read', return_value=json.dumps(good)):
            self.assertEqual(r.recovery_diagnostics(Mock(),self.new,set()),[good])
        for data in (dict(good,file='/private/runtime.php'), dict(good,reasonCode='secret exception'), dict(good,password='secret'), dict(good,line=True)):
            with patch.object(r.p,'entries',return_value=[{'name':name}]), patch.object(r,'private_read',return_value=json.dumps(data)), self.assertRaises(CpanelError):
                r.recovery_diagnostics(Mock(),self.new,set())

if __name__ == '__main__': unittest.main()
