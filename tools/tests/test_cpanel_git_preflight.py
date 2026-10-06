import hashlib
import json
from pathlib import Path
import sys
import unittest
from unittest.mock import patch
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import cpanel_git_prepare as g
from cpanel_client import CpanelError, CpanelConfig
from test_cpanel_git_prepare import Fake, SHA

class NativeFake(Fake):
    config = CpanelConfig('cp73.domains.co.za', 2083, 'archite4', '', g.HOME, g.HOME,
                          api_token='PRIVATE_SENTINEL_TOKEN_123456')
    def trash(self, path):
        assert path == g.TOKEN
        del self.files[path]
    def __init__(self, terminal='succeeded', receipt=True):
        super().__init__()
        self.rows = [{'repository_root':g.ROOT, 'name':'a777-production', 'type':'git',
                      'source_repository':{'remote_name':'origin','url':g.URL}}]
        self.terminal, self.receipt, self.polls = terminal, receipt, 0
    def mkdir(self,path,permissions):
        super().mkdir(path,permissions)
        self.modes[path] = int(permissions)
    def _uapi(self,module,function,params):
        if module != 'VersionControlDeployment': return super()._uapi(module,function,params)
        self.calls.append((module,function,params))
        if function == 'create':
            return {'repository_root':g.ROOT,'deploy_id':'13','task_id':'00000000/abc'}
        assert function == 'retrieve' and params == {}
        self.polls += 1
        if self.receipt:
            plan = self.files[g.PLAN]
            self.files[g.DEPLOY_ROOT+'/preflight-'+SHA+'-0123456789abcdef.json'] = json.dumps({
                'format':'a777-cpanel-native-preflight-v1','mode':'preflight','commit':SHA,
                'planSha256':hashlib.sha256(plan.encode()).hexdigest(),
                'status':'capability-inspection-refused' if self.terminal == 'failed' else 'inspected-not-authorized',
                'databaseDdlTouched':False,'liveFilesTouched':False,'mysqlInventoryAvailable':True,
                'blockingGate':'native_uapi_failed','password':'PRIVATE_SENTINEL','users':['PRIVATE_SENTINEL']})
        return [{'repository_root':g.ROOT,'deploy_id':13,'task_id':'00000000/abc',
                 'repository_state':{'identifier':SHA},'timestamps':{self.terminal:'1525289448.83553'}}]

class PreflightTests(unittest.TestCase):
    def test_runtime_pin_validation_before_calls(self):
        for value in ('a'*63, 'A'*64, 'a'*64+'\n', None, True):
            f = NativeFake()
            r = g.native_preflight(f, SHA, expected_runtime_config_sha256=value)
            self.assertEqual(r['error'], 'native_preflight_input_refused')
            self.assertFalse(f.calls)
    def test_explicit_runtime_pin_plan_and_roll(self):
        f = NativeFake()
        self.run_native(f)
        pin = 'a'*64
        g.preflight_plan(f, SHA, expected_runtime_config_sha256=pin)
        self.assertEqual(json.loads(f.files[g.PLAN])['expectedRuntimeConfigSha256'], pin)
        self.assertTrue(any('/plan-roll-' in path for path in f.files))
        # A changed plan cannot be rolled again without its own matched receipt.
        with self.assertRaises(CpanelError):
            g.preflight_plan(f, SHA, expected_runtime_config_sha256='b'*64)
    def test_runtime_pin_runner_wiring(self):
        f = NativeFake()
        with patch.object(g, 'clone', return_value={'status':'cloned_and_pulled','commit':SHA}):
            result = g.native_preflight(f, SHA, attempts=1, pause=0, expected_runtime_config_sha256='a'*64)
        self.assertEqual(result['status'], 'preflight_complete')
        self.assertEqual(json.loads(f.files[g.PLAN])['expectedRuntimeConfigSha256'], 'a'*64)
    def run_native(self,f,commit=SHA):
        with patch.object(g,'clone',return_value={'status':'cloned_and_pulled','commit':SHA}):
            return g.native_preflight(f,commit,attempts=2,pause=0)
    def test_complete_requires_terminal_and_receipt(self):
        f=NativeFake(); report=self.run_native(f)
        self.assertEqual(report['status'],'preflight_complete')
        self.assertEqual(report['deployment_last_commit'],SHA)
        self.assertFalse(report['runtime_deployed'])
        self.assertNotIn('PRIVATE_SENTINEL',json.dumps(report))
        self.assertEqual(f.modes[g.PLAN],600)
        self.assertEqual(f.modes[g.DEPLOY_ROOT],700)
        self.assertNotIn('artifactTreeSha256',json.loads(f.files[g.PLAN]))
        self.assertIn(('VersionControlDeployment','create',{'repository_root':g.ROOT}),f.calls)
    def test_queued_not_complete(self):
        f=NativeFake('queued'); r=self.run_native(f)
        self.assertEqual(r['deployment_status'],'queued')
        self.assertEqual(r['status'],'failed_or_incomplete'); self.assertEqual(f.polls,2)
    def test_failure_retains_safe_gates(self):
        r=self.run_native(NativeFake('failed'))
        self.assertEqual(r['native_preflight_status'],'capability-inspection-refused')
        self.assertTrue(r['reports'][0]['mysqlInventoryAvailable'])
    def test_no_receipt_no_success(self):
        r=self.run_native(NativeFake(receipt=False))
        self.assertEqual(r['native_preflight_status'],'receipt_unavailable')
        self.assertNotEqual(r['status'],'preflight_complete')
    def test_remote_head_mismatch_no_plan_or_create(self):
        f=NativeFake(); self.assertEqual(self.run_native(f,'b'*40)['error'],'native_preflight_pull_master_refused')
        self.assertNotIn(g.PLAN,f.files)
        self.assertFalse(any(m=='VersionControlDeployment' for m,fn,p in f.calls))
    def test_invalid_permissions_preserved_refused(self):
        f=NativeFake();f.dirs.add(g.DEPLOY_ROOT);f.modes[g.DEPLOY_ROOT]=755
        self.assertEqual(self.run_native(f)['error'],'native_preflight_private_plan_refused')
        self.assertEqual(f.modes[g.DEPLOY_ROOT],755)
    def test_operator_plan_not_overwritten(self):
        f=NativeFake();f.dirs.add(g.DEPLOY_ROOT);f.modes[g.DEPLOY_ROOT]=700
        f.files[g.PLAN]='{"mode":"deploy"}'
        self.run_native(f);self.assertEqual(f.files[g.PLAN],'{"mode":"deploy"}')
    def test_bad_pin_no_calls(self):
        f=NativeFake();r=g.native_preflight(f,SHA,'wrong',attempts=1,pause=0)
        self.assertEqual(r['error'],'native_preflight_input_refused');self.assertFalse(f.calls)
    def test_symlink_receipt_refused(self):
        f=NativeFake()
        with patch.object(g,'owned_mode',side_effect=CpanelError('PRIVATE_SENTINEL')):
            r=self.run_native(f)
        self.assertNotIn('PRIVATE_SENTINEL',json.dumps(r));self.assertEqual(r['status'],'failed')
    def test_old_receipt_excluded(self):
        f=NativeFake(receipt=False);g.preflight_plan(f,SHA)
        name='preflight-'+SHA+'-0123456789abcdef.json'
        f.files[g.DEPLOY_ROOT+'/'+name]='{}'
        self.assertEqual(self.run_native(f)['reports'],[])
    def test_workflow_binding_and_artifact_always(self):
        workflow=(Path(__file__).resolve().parents[2]/'.github/workflows/cpanel-git.yml').read_text()
        self.assertIn('EXPECTED_COMMIT: ${{ github.sha }}',workflow)
        self.assertIn('EXPECTED_RUNTIME_CONFIG_SHA256: ${{ inputs.expected_runtime_config_sha256 }}', workflow)
        self.assertIn('--expected-runtime-config-sha256 "$EXPECTED_RUNTIME_CONFIG_SHA256"', workflow)
        self.assertIn('refs/heads/master',workflow)
        self.assertIn('args+=(--expected-commit "$EXPECTED_COMMIT")',workflow)
        self.assertIn('if: ${{ always() }}',workflow)

if __name__=='__main__':unittest.main()
