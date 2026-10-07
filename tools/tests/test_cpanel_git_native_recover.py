import copy
import json
from pathlib import Path
import sys
import unittest
from unittest.mock import patch, Mock
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import cpanel_native_recover as r
from cpanel_client import CpanelError

class RecoveryTests(unittest.TestCase):
    def setUp(self):
        self.old = 'a'*40
        self.new = 'b'*40
        self.sha = 'c'*64
        self.prior = {'deploymentId':'d'*32, 'backupId':'backup_fixture', **{k:'e'*64 for k in r.HASH_FIELDS}}
        self.receipt = {'format':'a777-cpanel-native-recover-v1', 'mode':'native-recover', 'commit':self.new, 'expectedRecoveryCommit':self.new, 'releaseCommit':self.old, 'oldPlanSha256':self.sha, 'planSha256':'f'*64, 'recoveredDeploymentId':self.prior['deploymentId'], 'phase':'complete', 'sourceAppliedPriorCount':8, **{k:True for k in ('deployed','sourceAlreadyCurrent','appPrivilegesRestored','operatorPrivilegesRevoked','appConfigDeltaVerified','readinessPassed','artifactVerified')}, **{k:False for k in ('sourceMigrationsReplayed','maintenanceActive','maintenanceRetained','operatorStateUnknown','sourcePrivilegeStateUnknown')}, **{k:self.prior[k] for k in (*r.HASH_FIELDS,'backupId')}}
    def check_receipt(self, data):
        with patch.object(r.p,'entries',return_value=[{'name':'native-recover-'+self.new+'-'+'0'*16+'.json'}]), patch.object(r,'private_read',return_value=json.dumps(data)):
            return r.recovery_receipt(Mock(),self.new,'f'*64,self.sha,self.old,self.prior,set())
    def test_distinct_old_release_new_recovery(self):
        self.assertEqual(self.check_receipt(self.receipt)['releaseCommit'],self.old)
    def test_missing_and_unknown_rejected(self):
        for key in self.receipt:
            data = copy.deepcopy(self.receipt); del data[key]
            with self.subTest(key=key), self.assertRaises(CpanelError): self.check_receipt(data)
        with self.assertRaises(CpanelError): self.check_receipt(dict(self.receipt,password='never-export'))
    def test_unsafe_states_rejected(self):
        for key,value in [('sourceMigrationsReplayed',True),('maintenanceActive',True),('appPrivilegesRestored',False),('sourceAppliedPriorCount',True),('releaseCommit',self.new),('oldPlanSha256','0'*64),('operatorStateUnknown',True),('buildSha256','0'*64)]:
            data=dict(self.receipt); data[key]=value
            with self.subTest(key=key), self.assertRaises(CpanelError): self.check_receipt(data)
    def test_token_hash_match_and_owner_mode(self):
        client=Mock(); client.config.api_token='t'*32
        with patch.object(r,'private_read',return_value='t'*32), patch.object(r.p,'owned_mode') as owned:
            r.retained_token(client); owned.assert_called_with(client,r.p.TOKEN,'0600')
        with patch.object(r,'private_read',return_value='x'*32), self.assertRaises(CpanelError): r.retained_token(client)
    def test_failure_never_writes_or_removes_token(self):
        client=Mock()
        with patch.object(r,'retained_token'), patch.object(r,'commit',side_effect=CpanelError('refused')):
            report=r.native_recover(client,Path.cwd(),self.new)
        self.assertFalse(report['runtime_deployed']); self.assertTrue(report['tokenRetainedForRecovery'])
        client.save_file.assert_not_called(); client.trash.assert_not_called(); client._uapi.assert_not_called()
    def test_no_build_or_source_runner_calls(self):
        import inspect
        code=inspect.getsource(r.native_recover)
        for forbidden in ('provision_token(', 'save_plan(', 'archive_release(', 'build(', 'migrate(', 'SourceSqlMigrationRunner'):
            self.assertNotIn(forbidden,code)

if __name__ == '__main__': unittest.main()
