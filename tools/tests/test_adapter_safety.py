import pathlib
import tempfile
import unittest
from tools.tests.test_cpanel_host_operations import AdapterTests, FakeClient, TARGET
from tools.cpanel_host_operations import CpanelHostOperations

class SafetyTests(AdapterTests):
    def test_wrong_row_deleted_is_not_count_success(self):
        client=FakeClient()
        original=client._request_json
        def request(method,path,params):
            if params['cpanel_jsonapi_func']=='remove_line':
                client.rows=[r for r in client.rows if r['command']!='unrelated-job']
                return {'cpanelresult':{'event':{'result':1},'data':[{'status':1}]}}
            return original(method,path,params)
        client._request_json=request
        with self.assertRaises(RuntimeError): self.adapter(client).suppress_cron()
    def test_traversal_helper_rejected(self):
        with self.assertRaises(RuntimeError):
            CpanelHostOperations(FakeClient(),'https://example.test',TARGET,'/home/fixture/candidate',b'x'*32,
                '/home/fixture/public_html/../escape.php',[],[])
    def test_active_lease_retains_helper(self):
        adapter=self.adapter(); adapter.installed=True;adapter.lease='a'*64
        with self.assertRaises(RuntimeError): adapter.cleanup_helpers()
        self.assertTrue(adapter.installed)

    def test_lost_remove_response_reconciles_exact_effect(self):
        client=FakeClient(); original=client._request_json
        def request(method,path,params):
            result=original(method,path,params)
            if params['cpanel_jsonapi_func']=='remove_line': raise OSError('lost response')
            return result
        client._request_json=request
        adapter=self.adapter(client); adapter.suppress_cron()
        self.assertEqual([r['command'] for r in client.rows],['unrelated-job'])
        import json
        self.assertIsNone(json.loads(adapter.scheduler_journal.read_text())['intent'])

    def test_concurrent_schedule_edit_is_not_count_success(self):
        client=FakeClient(); original=client._request_json
        def request(method,path,params):
            result=original(method,path,params)
            if params['cpanel_jsonapi_func']=='remove_line': client.rows[0]['minute']='17'
            return result
        client._request_json=request
        with self.assertRaises(RuntimeError): self.adapter(client).suppress_cron()

    def test_crash_upload_retains_key_and_helper_ownership(self):
        import json
        client=FakeClient();client.present=False
        def upload(path,source): client.present=True; raise OSError('upload response lost')
        client.save_file=upload
        adapter=self.adapter(client)
        adapter.process_model={'inventoryPath':'/home/fixture/proof.json','publicKey':'fixture'}
        adapter.recovery_paths={'maintenanceDirectory':'/home/fixture/storage/maintenance',
                                'archiveDirectory':'/home/fixture/operator-recovery'}
        with self.assertRaises(OSError): adapter.install()
        self.addCleanup(adapter.operator_lock.close)
        with self.assertRaises(RuntimeError): adapter.cleanup_helpers()
        self.assertTrue(client.present)
        recovery=json.loads(adapter.recovery_file.read_text())
        self.assertEqual(recovery['keyHex'],(b'x'*32).hex())
        self.assertEqual(recovery['helperPath'],adapter.path)

    def test_concrete_quiesce_receipt_accepted_by_pipeline(self):
        import hashlib,hmac,json,time
        from tools.cpanel_client import HttpResponse
        from tools.production_migration import MigrationPipeline,canonical
        class Transport:
            def request(self,method,url,headers,body,timeout):
                if method=='GET': return HttpResponse(503,{},b'Application maintenance in progress.')
                request=json.loads(body)
                payload={'stage':request['action'],'challenge':request['challenge'],'target':TARGET,
                    'lease':'a'*64,'web':'blocked','dispatch':'stopped','cron':'stopped',
                    'drainedAt':int(time.time()),'expiresAt':int(time.time())+100}
                return HttpResponse(200,{},canonical({'payload':payload,'signature':hmac.new(b'x'*32,canonical(payload),hashlib.sha256).hexdigest()}))
        adapter=self.adapter(transport=Transport());adapter.installed=True
        pipeline=MigrationPipeline(adapter,None,TARGET,b'x'*32,pathlib.Path(self.temp.name),{})
        data=pipeline.receipt(adapter.quiesce(pipeline.challenge),'quiescence')
        self.assertEqual(data['lease'],'a'*64)

if __name__=='__main__': unittest.main()
