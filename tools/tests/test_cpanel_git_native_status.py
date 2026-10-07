import json
from pathlib import Path
import sys
import unittest
from unittest.mock import Mock, patch
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
import cpanel_native_stage as n
from cpanel_client import CpanelError

SHA='a'*40
class StatusTests(unittest.TestCase):
    def safe(self):
        return dict(phase='failed',lastCompletedStage='staged',deployed=False,
                    sourceDatabaseDdlTouched=False,sourceMutationDdlStarted=False,
                    maintenanceActive=False,maintenanceRetained=False,sourcePrivilegesRevoked=False,
                    sourcePrivilegeStateUnknown=False,operatorStateUnknown=False,operatorPrivilegesRevoked=True)
    def test_all_safety_fields_explicit_and_typed(self):
        base=self.safe(); self.assertTrue(n.safe_failed_native(base))
        for key in base:
            if type(base[key]) is bool:
                for value in (None,0,1,'false',not base[key]):
                    with self.subTest(key=key,value=value):
                        self.assertFalse(n.safe_failed_native(dict(base,**{key:value})))
                altered=base.copy(); del altered[key]; self.assertFalse(n.safe_failed_native(altered))
        for stage in (None,'sourceDDL','promoted','deployed','unknown'):
            self.assertFalse(n.safe_failed_native(dict(base,lastCompletedStage=stage)))
    def test_status_only_read_requests_and_exact_old_bytes(self):
        plan=json.dumps(dict(mode='native-deploy',account='archite4',repository=n.p.ROOT,expectedCommit=SHA))+'\n'
        receipt=dict(self.safe(),format='a777-cpanel-native-deploy-v1',mode='native-deploy',commit=SHA,planSha256=n.digest(plan.encode()),failureStage='source-grants-snapshot',failureCode='original_grants',failureType='RuntimeException',failureLine=501,password='NEVER')
        client=Mock()
        client.config=Mock(host='cp73.domains.co.za',port=2083,username='archite4',account_home=n.p.HOME,allowed_root=n.p.HOME,api_token='fixture')
        def api(module,function,args):
            if (module,function)==('Variables','get_user_information'): return {'home':n.p.HOME}
            if (module,function)==('VersionControlDeployment','retrieve'): return [dict(repository_root=n.p.ROOT,repository_state={'identifier':SHA},task_id='task/13',timestamps={'failed':1})]
            if (module,function)==('Fileman','get_file_content'): return {'content':plan if args['file']=='operator-plan.json' else json.dumps(receipt)}
            if (module,function)==('Fileman','list_files'): return [{'name':'native-deploy-'+SHA+'-'+'b'*16+'.json'}]
            self.fail('Mutation or unreviewed request')
        client._uapi.side_effect=api
        with patch.object(n.p,'owned_mode'):
            report=n.native_status(client)
        self.assertEqual(report['planSha256'],n.digest(plan.encode()))
        self.assertEqual(report['receipt']['failureCode'],'original_grants')
        self.assertNotIn('password',report['receipt'])
        self.assertEqual(report['independentNativeDeployment']['status'],'failed')
        self.assertEqual([c[0] for c in client.mock_calls if c[0]!='_uapi'],[])
    def test_consumed_marker_matches_exact_safe_failed_receipt(self):
        marker='native-consumed-'+'c'*64+'.jsonl'
        receipt_name='native-deploy-'+SHA+'-'+'b'*16+'.json'
        receipt=dict(self.safe(),format='a777-cpanel-native-deploy-v1',mode='native-deploy',commit=SHA,
                     planSha256='c'*64,deploymentId='d'*32)
        rows=[{'name':marker},{'name':receipt_name}]
        def read(client,path):
            return json.dumps({'deploymentId':'d'*32,'commit':SHA}) if path.endswith(marker) else json.dumps(receipt)
        with patch.object(n.p,'entries',return_value=rows),patch.object(n.p,'owned_mode'),patch.object(n.p,'read',side_effect=read):
            n.p.deployment_inventory_guard(None)
            for key,value in (('deploymentId','e'*32),('commit','b'*40),('planSha256','e'*64),('sourceMutationDdlStarted',True),('operatorStateUnknown',True)):
                old=receipt[key]; receipt[key]=value
                with self.subTest(key=key),self.assertRaises(CpanelError): n.p.deployment_inventory_guard(None)
                receipt[key]=old

    def test_retry_same_commit_or_bad_task_refused(self):
        old={'mode':'native-deploy','expectedCommit':SHA}; raw=json.dumps(old)
        with self.assertRaises(CpanelError): n.safe_retry_plan(Mock(),old,raw,SHA)
        report={'commit':SHA,'planSha256':n.digest(raw.encode()),'receipt':self.safe(),'independentNativeDeployment':{'status':'succeeded'}}
        with patch.object(n,'native_status',return_value=report):
            with self.assertRaises(CpanelError): n.safe_retry_plan(Mock(),old,raw,'b'*40)

if __name__=='__main__': unittest.main()
