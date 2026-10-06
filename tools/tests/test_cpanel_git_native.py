"""Native SSH API fixtures follow official API 2 envelopes; no hosting calls."""
import contextlib
import io
import json
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch
from urllib.parse import parse_qs
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import cpanel_git_prepare as g
import cpanel_git as cli
from cpanel_client import CpanelClient, CpanelError, HttpResponse
from test_cpanel_git_prepare import Fake, HOST, RSA_PUBLIC, wire
import base64

class Transport:
    def __init__(self, data, success=1):
        self.data, self.success, self.calls = data, success, []
    def request(self, method, url, headers, body, timeout):
        self.calls.append((method, url, headers, body))
        params = parse_qs(body.decode(), keep_blank_values=True)
        return HttpResponse(200, {}, json.dumps({'cpanelresult': {
            'apiversion': 2, 'module': 'SSH', 'func': params['cpanel_jsonapi_func'][0],
            'data': self.data, 'event': {'result': self.success}}}).encode())

class NativeTests(unittest.TestCase):
    def prepare(self, f, p, expected=None):
        with patch.object(g, 'github_host_key', return_value=HOST):
            return g.prepare_key(f, p, expected)

    def test_official_genkey_envelope_and_body_only_post(self):
        t = Transport([{'reason': 'RAW_REASON', 'result': 1, 'warnings': 'empty passphrase allowed'}])
        c = CpanelClient(Fake.config, t)
        c.api2('SSH', 'genkey', {'bits':'4096','name':'a777-deploy-key','pass':'','type':'rsa'})
        method, url, headers, body = t.calls[0]
        self.assertEqual(method, 'POST')
        self.assertTrue(url.endswith('/json-api/cpanel'))
        self.assertNotIn('?', url)
        self.assertEqual(parse_qs(body.decode(), keep_blank_values=True)['pass'], [''])
        self.assertEqual(headers['Content-Type'], 'application/x-www-form-urlencoded')
        self.assertEqual(parse_qs(body.decode())['cpanel_jsonapi_module'], ['SSH'])

    def test_official_refusal_never_emits_provider_reason(self):
        for data, status in [([{'reason':'PRIVATE_SENTINEL', 'result':0}],1), ([],0)]:
            with self.assertRaises(CpanelError) as error:
                CpanelClient(Fake.config, Transport(data,status)).api2('SSH','genkey',{})
            self.assertNotIn('PRIVATE_SENTINEL',str(error.exception))

    def test_partial_pair_and_inventory_only_collision_no_generation(self):
        with tempfile.TemporaryDirectory() as d:
            for path in (g.KEY, g.KEY+'.pub'):
                f = Fake(); f.files[path] = RSA_PUBLIC
                with self.assertRaises(CpanelError): self.prepare(f,Path(d)/'pub')
                self.assertFalse(any(fn=='genkey' for m,fn,p in f.calls))
            f = Fake()
            with patch.object(f, 'api2', return_value=[{'file':g.KEY,'name':'a777-deploy-key','key':'a777-deploy-key','haspub':0}]):
                with self.assertRaises(CpanelError): self.prepare(f,Path(d)/'pub')

    def test_no_private_read_no_upload_no_authorization_no_regeneration(self):
        f = Fake()
        original = f._uapi
        def public_only(module, function, params):
            if function == 'get_file_content':
                self.assertNotEqual(params['file'], 'a777-deploy-key')
            return original(module,function,params)
        with tempfile.TemporaryDirectory() as d, patch.object(f, '_uapi', side_effect=public_only):
            p = Path(d)/'pub'
            report = self.prepare(f,p)
            self.prepare(f,p,report['public_key_fingerprint'])
        self.assertEqual(sum(fn=='genkey' for m,fn,p in f.calls),1)
        self.assertFalse(any(fn in ('authkey','importkey','delkey','fetchkey','upload_files') for m,fn,p in f.calls))
        self.assertEqual(report['key_generation'],'cpanel_native')
        self.assertIn('HostKeyAlgorithms ssh-ed25519',g.BLOCK)

    def test_capability_refusal_and_lost_generation_report_stages(self):
        with tempfile.TemporaryDirectory() as d:
            for function, stage, state in [('listkeys','SSH/listkeys','unknown'),('genkey','SSH/genkey','possibly_generated')]:
                f = Fake(); original = f.api2
                def refuse(module, fn, params):
                    if fn == function: raise CpanelError('RAW_PRIVATE_PROVIDER_REASON')
                    return original(module,fn,params)
                with patch.object(f,'api2',side_effect=refuse), self.assertRaises(g.NativeKeyFailure) as error:
                    self.prepare(f,Path(d)/'pub')
                self.assertEqual(error.exception.stage,stage)
                self.assertEqual(error.exception.state,state)
                self.assertNotIn('RAW_PRIVATE',str(error.exception))

    def test_generation_partial_completion_never_cleans_or_retries(self):
        f = Fake(); original = f.api2
        def lost_response(module,fn,params):
            data = original(module,fn,params)
            if fn=='genkey': raise OSError('PRIVATE_RESPONSE')
            return data
        with tempfile.TemporaryDirectory() as d:
            with patch.object(f,'api2',side_effect=lost_response), self.assertRaises(g.NativeKeyFailure):
                self.prepare(f,Path(d)/'pub')
            self.assertIn(g.KEY,f.files)
            with self.assertRaises(CpanelError): self.prepare(f,Path(d)/'pub')
        self.assertEqual(sum(fn=='genkey' for m,fn,p in f.calls),1)

    def test_config_failure_preserves_public_recovery_artifact(self):
        f = Fake()
        with tempfile.TemporaryDirectory() as d:
            p = Path(d)/'pub'
            with patch.object(f,'save_file',side_effect=OSError('PRIVATE')), self.assertRaises(g.NativeKeyFailure) as error:
                self.prepare(f,p)
            self.assertEqual(error.exception.stage,'SSH/config_readback')
            self.assertEqual(error.exception.state,'generated')
            self.assertEqual(p.read_text(),RSA_PUBLIC)
            self.assertIn(g.KEY,f.files)

    def test_cli_native_failure_public_only_report(self):
        f = Fake()
        with tempfile.TemporaryDirectory() as d:
            report_path = Path(d)/'report.json'
            argv = ['cpanel_git.py','prepare-key','--public-key',str(Path(d)/'pub'),'--report',str(report_path)]
            with patch.object(sys,'argv',argv), patch.object(cli,'CpanelClient',return_value=f), patch.object(f,'api2',side_effect=CpanelError('PRIVATE_PROVIDER_REASON')), patch.object(g,'github_host_key',return_value=HOST), contextlib.redirect_stdout(io.StringIO()) as out:
                self.assertEqual(cli.main(),1)
            report = json.loads(report_path.read_text())
            self.assertEqual(report['api_operation'],'SSH/listkeys')
            self.assertEqual(report['error'],'native_ssh_inventory_unavailable')
            self.assertNotIn('PRIVATE_PROVIDER_REASON',out.getvalue())
            self.assertNotIn('DO_NOT_LOG_TOKEN',out.getvalue())

    def test_incomplete_or_contaminated_public_readback_is_not_prepared(self):
        for public in (None, RSA_PUBLIC + '-----BEGIN PRIVATE KEY-----\n', HOST.split(' ',1)[1]):
            f = Fake(); original = f.api2
            def incomplete(module, fn, params):
                data = original(module,fn,params)
                if fn == 'genkey':
                    if public is None: del f.files[g.KEY+'.pub']
                    else: f.files[g.KEY+'.pub'] = public
                return data
            with tempfile.TemporaryDirectory() as d:
                p = Path(d)/'pub'
                with patch.object(f,'api2',side_effect=incomplete), self.assertRaises(g.NativeKeyFailure) as error:
                    self.prepare(f,p)
                self.assertEqual(error.exception.state,'generated')
                self.assertEqual(error.exception.stage,'SSH/public_readback')
                self.assertFalse(p.exists())
                self.assertIn(g.KEY,f.files)
                self.assertNotIn(g.BLOCK,f.files[g.SSH+'/config'])

    def test_duplicate_native_inventory_refuses_and_private_guard(self):
        f = Fake()
        row = {'file':g.KEY,'name':'a777-deploy-key','key':'a777-deploy-key','haspub':1}
        with patch.object(f,'api2',return_value=[row,row]), self.assertRaises(g.NativeKeyFailure):
            g.native_inventory(f)
        with self.assertRaises(CpanelError): g.read(f,g.KEY)
        self.assertFalse(f.calls)

    def test_encoded_algorithm_truncation_trailing_and_wrong_bits_refused(self):
        self.assertTrue(g.fingerprint(RSA_PUBLIC).startswith('SHA256:'))
        for key in ['ssh-ed25519 '+RSA_PUBLIC.split()[1], 'ssh-rsa '+HOST.split()[2], 'ssh-rsa '+base64.b64encode(wire(b'ssh-rsa')).decode(), RSA_PUBLIC.split()[0]+' '+base64.b64encode(base64.b64decode(RSA_PUBLIC.split()[1])+b'extra').decode()]:
            with self.assertRaises(CpanelError): g.fingerprint(key)
        with self.assertRaises(CpanelError): g.fingerprint(RSA_PUBLIC,required_rsa_bits=2048)

if __name__ == '__main__': unittest.main()
