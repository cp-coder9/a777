import hashlib
import hmac
import json
import pathlib
import shutil
import socket
import subprocess
import tempfile
import time
import unittest
import urllib.request
import urllib.error
from types import SimpleNamespace
from tools.cpanel_host_operations import CpanelHostOperations
from tools.cpanel_client import HttpResponse
from tools.production_migration import canonical

ROOT = pathlib.Path(__file__).resolve().parents[2]
TARGET = dict(account='fixture', domain='example.test', documentRoot='/home/fixture/public_html', runtimeRoot='/home/fixture/app', database='fixture_test')

class FakeClient:
    def __init__(self):
        self.config = SimpleNamespace(username='fixture')
        self.rows = [dict(command='owned-job', count=1),dict(command='unrelated-job', count=2)]
        for row in self.rows: row.update(minute='*',hour='*',day='*',month='*',weekday='*')
        self.calls = []
        self.present = True
    def _request_json(self, method, path, params):
        self.calls.append((method,path,params))
        if params['cpanel_jsonapi_func'] == 'remove_line':
            self.rows = [r for r in self.rows if r['count'] != int(params['line'])]
            data = [dict(status=1)]
        else: data = [*self.rows,dict(count=len(self.rows)+1)]
        return {'cpanelresult':{'event':{'result':1},'data':data}}
    def exists(self,path): return self.present
    def trash(self,path): self.present = False

class AdapterTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(dir=ROOT.parent)
        self.addCleanup(self.temp.cleanup)
    def adapter(self, client=None, transport=None):
        return CpanelHostOperations(client or FakeClient(),'https://example.test',TARGET,
            '/home/fixture/candidate',b'x'*32,'/home/fixture/public_html/helper.php',
            ['owned-job'], ['unrelated-job'], transport,
            scheduler_journal=pathlib.Path(self.temp.name)/'scheduler.json')
    def test_concrete_all_abstract_methods_implemented(self):
        self.assertEqual(CpanelHostOperations.__abstractmethods__, frozenset())
    def test_documented_cron_inventory_removal_and_readback(self):
        client = FakeClient(); adapter = self.adapter(client)
        adapter.suppress_cron()
        self.assertEqual([r['command'] for r in client.rows],['unrelated-job'])
        remove = [p for _,_,p in client.calls if p['cpanel_jsonapi_func']=='remove_line']
        self.assertEqual(remove[0]['line'],'1')
        self.assertEqual(remove[0]['cpanel_jsonapi_module'],'Cron')
        self.assertEqual(adapter.suppressed[0]['command'],'owned-job')
    def test_unknown_ownership_refuses_before_removal(self):
        client=FakeClient();client.rows.append(dict(command='unknown',count=3))
        with self.assertRaises(RuntimeError):self.adapter(client).suppress_cron()
        self.assertFalse(any(p['cpanel_jsonapi_func']=='remove_line' for _,_,p in client.calls))
    def test_redirect_refused_no_followup(self):
        class Transport:
            def __init__(self):self.calls=[]
            def request(self,*args):self.calls.append(args);return HttpResponse(302,{'Location':'https://attacker.test'},b'')
        transport=Transport();adapter=self.adapter(transport=transport);adapter.installed=True
        with self.assertRaises(RuntimeError):adapter.observe('identity','a'*64,None,{})
        self.assertEqual(len(transport.calls),1)
        self.assertNotIn('Authorization',transport.calls[0][2])
        self.assertNotIn('Cookie',transport.calls[0][2])
    def test_cleanup_reads_back_exact_absence(self):
        adapter=self.adapter();adapter.installed=True;adapter.cleanup_helpers()
        self.assertFalse(adapter.installed)
    def test_cleanup_unknown_is_not_success(self):
        client=FakeClient();client.trash=lambda path:None
        adapter=self.adapter(client);adapter.installed=True
        with self.assertRaises(RuntimeError):adapter.cleanup_helpers()

@unittest.skipUnless(shutil.which('php'),'PHP required')
class HelperHttpTests(unittest.TestCase):
    def test_real_php_authentication_precedes_runtime_load(self):
        with tempfile.TemporaryDirectory(dir=ROOT.parent) as tmp:
            root=pathlib.Path(tmp)
            key=b'z'*32
            private_temp = tempfile.TemporaryDirectory(dir=ROOT.parent)
            self.addCleanup(private_temp.cleanup)
            private = pathlib.Path(private_temp.name)
            runtime = root/'runtime'; (runtime/'config').mkdir(parents=True)
            original = b'<?php declare(strict_types=1); // approved old fixture bootstrap'
            (runtime/'bootstrap.php').write_bytes(original)
            import base64
            runtime_values = base64.b64encode(canonical({'APP_ENV':'production',
                'FILE_STORAGE_PATH':str(private).replace(chr(92),'/')})).decode()
            (runtime/'config/runtime.php').write_text("<?php return json_decode(base64_decode('"+runtime_values+"'),true);")
            runtime_bytes = (runtime/'config/runtime.php').read_bytes()
            # Linux fixtures must satisfy the real account-home containment gate.
            # GitHub runners live under /home/runner, not the production account.
            account = TARGET['account']
            if __import__('os').name != 'nt':
                self.assertEqual(root.parts[1], 'home')
                account = root.parts[2]
            config={'target':{**TARGET,'account':account,'documentRoot':str(root),'runtimeRoot':str(runtime).replace(chr(92),'/')},
                'candidateRoot':str(runtime).replace(chr(92),'/'),'keySha256':hashlib.sha256(key).hexdigest(),
                'recoveryPaths':{'maintenanceDirectory':str(private/'maintenance').replace(chr(92),'/'),
                                 'archiveDirectory':str(private.parent/'operator-recovery').replace(chr(92),'/')}}
            import base64
            source=(ROOT/'tools/temporary_host_helper.php').read_text().replace('/*OPERATOR_CONFIG*/',
                "$operator=json_decode(base64_decode('"+base64.b64encode(canonical(config)).decode()+"'),true);")
            path=root/'helper.php';path.write_text(source)
            with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
            server=subprocess.Popen([shutil.which('php'),'-S',f'127.0.0.1:{port}','-t',str(root)],stdout=subprocess.PIPE,stderr=subprocess.PIPE)
            try:
                url=f'http://127.0.0.1:{port}/helper.php'
                for _ in range(100):
                    try:
                        urllib.request.urlopen(url,timeout=.5)
                    except urllib.error.HTTPError as error:
                        self.assertEqual(error.code,409);error.close();break
                    except urllib.error.URLError:time.sleep(.02)
                else:self.fail('Owned server unavailable')
                req=urllib.request.Request(url,data=canonical({'action':'helper_identity','challenge':'fixture','lease':None,'binding':{}}),
                    headers={'X-A777-Operator-Key':key.hex()},method='POST')
                with urllib.request.urlopen(req) as response:envelope=json.load(response)
                self.assertEqual(envelope['payload']['helperSha256'],hashlib.sha256(path.read_bytes()).hexdigest())
                self.assertEqual(envelope['signature'],hmac.new(key,canonical(envelope['payload']),hashlib.sha256).hexdigest())
                self.assertFalse((root/'maintenance').exists())
                paths = ('bootstrap.php','src/Infrastructure/Deployment/MaintenanceFence.php',
                         'src/Infrastructure/Config/EnvironmentLoader.php')
                sources = {p:base64.b64encode((ROOT/'app'/p).read_bytes()).decode() for p in paths}
                # Last source invalid: all sources must be checked before first rename.
                broken={**sources,'bootstrap.php':base64.b64encode(b'not php').decode()}
                bad=urllib.request.Request(url,data=canonical({'action':'install_hooks','challenge':'fixture',
                    'lease':None,'binding':{'approvedBootstrapSha256':hashlib.sha256(original).hexdigest(),
                    'sources':broken}}),headers={'X-A777-Operator-Key':key.hex()},method='POST')
                try: urllib.request.urlopen(bad); self.fail('Invalid source accepted')
                except urllib.error.HTTPError as error: self.assertEqual(error.code,409);error.close()
                self.assertEqual((runtime/'bootstrap.php').read_bytes(),original)
                self.assertFalse((runtime/paths[1]).exists())
                request = urllib.request.Request(url,data=canonical({'action':'install_hooks','challenge':'fixture',
                    'lease':None,'binding':{'approvedBootstrapSha256':hashlib.sha256(original).hexdigest(),
                    'sources':sources}}),headers={'X-A777-Operator-Key':key.hex()},method='POST')
                with urllib.request.urlopen(request) as response: installed=json.load(response)
                for relative in paths:
                    self.assertEqual((runtime/relative).read_bytes(),(ROOT/'app'/relative).read_bytes())
                    self.assertEqual(installed['payload']['installedSha256'][relative],hashlib.sha256((ROOT/'app'/relative).read_bytes()).hexdigest())
                self.assertEqual((runtime/'config/runtime.php').read_bytes(),runtime_bytes)
                journal=json.loads(next((private/'maintenance').glob('owned-hooks-*.json')).read_text())
                self.assertEqual(base64.b64decode(journal['original']['bootstrap.php']),original)
                # Reconcile a rename whose observed journal update was lost.
                journal['observed'] = {}
                receipt=next((private/'maintenance').glob('owned-hooks-*.json'))
                receipt.write_bytes(canonical(journal))
                def hook_call(action):
                    req=urllib.request.Request(url,data=canonical({'action':action,'challenge':'fixture',
                        'lease':None,'binding':{}}),headers={'X-A777-Operator-Key':key.hex()},method='POST')
                    return urllib.request.urlopen(req)
                with hook_call('reconcile_hooks') as response:
                    reconciled=json.load(response)
                self.assertEqual(reconciled['payload']['hooks']['observed'],journal['installed'])
                (private/'maintenance/lease.json').write_text('{}')
                try: hook_call('restore_hooks'); self.fail('Active lease accepted restore')
                except urllib.error.HTTPError as error: error.close()
                self.assertEqual((runtime/'bootstrap.php').read_bytes(),(ROOT/'app/bootstrap.php').read_bytes())
                (private/'maintenance/lease.json').unlink()
                lock_worker=subprocess.Popen([shutil.which('php'),'-r',
                    "$f=fopen($argv[1],'c');flock($f,LOCK_EX);echo \"locked\\n\";fflush(STDOUT);fgets(STDIN);",
                    str(runtime/'.operator.lock')],stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
                try:
                    self.assertEqual(lock_worker.stdout.readline().strip(),b'locked')
                    try: hook_call('restore_hooks');self.fail('Concurrent operator accepted')
                    except urllib.error.HTTPError as error:self.assertEqual(error.code,409);error.close()
                finally: lock_worker.communicate(b'release\n',timeout=5)
                with hook_call('restore_hooks') as response: json.load(response)
                self.assertEqual((runtime/'bootstrap.php').read_bytes(),original)
            finally:
                server.terminate();server.communicate(timeout=5)

if __name__=='__main__':unittest.main()
