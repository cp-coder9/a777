import contextlib
import io
import json
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import cpanel_git_prepare as g
from cpanel_client import CpanelConfig, CpanelError

HOST = 'github.com ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIOMqqnkVzrm0SdG6UOoqKLsabgH5C9okWi0dh2l9GKJl\n'
SHA = 'a' * 40

class Fake:
    config = CpanelConfig('cp73.domains.co.za', 2083, 'archite4', '', g.HOME, g.HOME, api_token='DO_NOT_LOG_TOKEN')
    def __init__(self):
        self.files = {g.SSH + '/config': 'Host other\r\n  User someone\r\n', g.SSH + '/known_hosts': 'other ssh-rsa abc\n'}
        self.dirs = {g.HOME, g.SSH, g.HOME + '/repositories'}
        self.modes = {p: 700 for p in self.dirs}
        self.calls = []
        self.rows = []
        self.pending = False
    def _uapi(self, module, function, params):
        self.calls.append((module, function, params))
        if module == 'Variables': return {'home': g.HOME}
        if function == 'list_files':
            directory = params['dir']
            def rows(paths, kind):
                return [{'file': p.rsplit('/',1)[1], 'type': kind, 'nicemode': self.modes.get(p, 600)} for p in paths if p.rsplit('/',1)[0] == directory]
            return {'files': rows(self.files, 'file'), 'dirs': rows(self.dirs, 'dir')}
        if function == 'get_file_content': return {'content': self.files[params['dir'] + '/' + params['file']]}
        if function == 'retrieve': return self.rows
        if function == 'create':
            self.rows = [{'repository_root': g.ROOT, 'name': 'a777-production', 'type': 'git', 'source_repository': json.loads(params['source_repository']), 'branch': None if self.pending else 'master', 'last_update': {'identifier': SHA}, 'tasks': [{'id':'123/abc','action':'create'}] if self.pending else []}]
            return self.rows[0]
        if function == 'update': return self.rows[0]
        raise AssertionError((module,function))
    def _api2(self, function, params):
        self.calls.append(('API2', function, params))
        self.modes[g.HOME + '/' + params['sourcefiles']] = int(params['metadata'])
    def mkdir(self, path, permissions): self.dirs.add(path)
    def upload_file(self, local, remote): self.files[remote] = local.read_text(encoding='ascii')
    def save_file(self, remote, content): self.files[remote] = content

class PreparationTests(unittest.TestCase):
    def prepare(self, fake, destination, expected=None):
        with patch.object(g, 'github_host_key', return_value=HOST):
            return g.prepare_key(fake, destination, expected)
    def test_real_key_upload_preservation_permissions_and_no_logs(self):
        f = Fake()
        old = dict(f.files)
        with tempfile.TemporaryDirectory() as d, contextlib.redirect_stdout(io.StringIO()) as output:
            public = Path(d)/'public.pub'
            report = self.prepare(f, public)
            self.assertTrue(public.read_text().startswith('ssh-ed25519 '))
            self.assertNotIn('PRIVATE', public.read_text())
            self.assertNotIn('PRIVATE', json.dumps(report) + output.getvalue())
            self.assertNotIn('DO_NOT_LOG_TOKEN', json.dumps(report) + output.getvalue())
            self.assertTrue(f.files[g.SSH+'/config'].startswith(old[g.SSH+'/config']))
            self.assertTrue(f.files[g.SSH+'/known_hosts'].startswith(old[g.SSH+'/known_hosts']))
            self.assertEqual(f.modes[g.KEY],600)
            self.assertEqual(f.modes[g.SSH],700)
            before = dict(f.files)
            self.prepare(f, public, report['public_key_fingerprint'])
            self.assertEqual(f.files,before)
            with self.assertRaises(CpanelError): self.prepare(f, public)
            with self.assertRaises(CpanelError): self.prepare(f, public,'SHA256:wrong')
            self.assertEqual(f.files,before)
            chmod = next(p for m,fn,p in f.calls if m=='API2' and p['sourcefiles']=='.ssh/a777-deploy-key')
            self.assertEqual(chmod, {'op':'chmod','sourcefiles':'.ssh/a777-deploy-key','doubledecode':'0','metadata':'0600'})
    def test_config_collisions(self):
        for original in ['Host *\n User other\n','Host a777-github\n','Include more.conf\n','Match all\n']:
            with self.assertRaises(CpanelError): g.append_config(original)
        self.assertEqual(g.append_config(g.BLOCK),g.BLOCK)
    def test_clone_exact_interfaces_and_no_other_repository_mutation(self):
        f = Fake()
        with tempfile.TemporaryDirectory() as d:
            r=self.prepare(f,Path(d)/'public.pub')
            report=g.clone(f,r['public_key_fingerprint'],attempts=1,pause=0)
        self.assertEqual(report['commit'],SHA)
        self.assertFalse(report['runtime_deployed'])
        create=next(p for m,fn,p in f.calls if fn=='create')
        self.assertEqual(create['repository_root'],g.ROOT)
        self.assertEqual(json.loads(create['source_repository']),{'remote_name':'origin','url':g.URL})
        update=next(p for m,fn,p in f.calls if fn=='update')
        self.assertEqual(update,{'repository_root':g.ROOT,'branch':'master'})
        self.assertFalse(any(m=='VersionControlDeployment' for m,fn,p in f.calls))
    def test_queue_is_not_clone_success(self):
        f=Fake(); f.pending=True
        with tempfile.TemporaryDirectory() as d:
            r=self.prepare(f,Path(d)/'public.pub')
            report=g.clone(f,r['public_key_fingerprint'],attempts=1,pause=0)
        self.assertEqual(report['status'],'queued_or_incomplete')
        self.assertNotIn('commit',report)
        self.assertFalse(any(fn=='update' for m,fn,p in f.calls))
    def test_identity_and_permission_containment(self):
        f=Fake()
        f.rows=[{'repository_root':g.ROOT,'name':'someone-else'}]
        with self.assertRaises(CpanelError): g.inventory(f)
        for path in [g.HOME+'/public_html/arc-1',g.KEY+'/../config']:
            with self.assertRaises(CpanelError): g.chmod(f,path,'0600')
    def test_authoritative_host_fingerprint(self):
        self.assertEqual(g.fingerprint(HOST.split(' ',1)[1]),g.PIN)

if __name__=='__main__': unittest.main()
