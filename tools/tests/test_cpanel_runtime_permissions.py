import sys
from pathlib import Path
import unittest
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import cpanel_git_prepare as g
from test_cpanel_git_preflight import NativeFake, SHA

class PermissionReceiptTests(unittest.TestCase):
    def proof(self):
        return dict(databaseDdlTouched=False, liveFilesTouched=True,
                    runtimeConfigPermissionsTouched=True, privateConfigPermissionsChanged=True,
                    runtimeConfigRegular=True, runtimeConfigNotSymlink=True,
                    runtimeConfigOwnerMatches=True, runtimeConfigPrivate600=True,
                    runtimeConfigModeBefore='0644', runtimeConfigModeAfter='0600',
                    configContentUnchangedSHA='a'*64, runtimeConfigBytesUnchanged=20)
    def test_proved_permission_only(self):
        self.assertTrue(g.permission_only_safe(self.proof()))
    def test_each_missing_proof_refused(self):
        for key in self.proof():
            d=self.proof(); del d[key]
            if key != 'runtimeConfigModeBefore':
                self.assertFalse(g.permission_only_safe(d), key)
    def test_ddl_and_incomplete_repair_refused(self):
        for key in ('databaseDdlTouched','runtimeConfigOwnerMatches','runtimeConfigNotSymlink'):
            d=self.proof(); d[key]=not d[key]
            self.assertFalse(g.permission_only_safe(d))
    def bound(self):
        import json
        f=NativeFake(); g.preflight_plan(f,SHA)
        d=self.proof(); d.update(commit=SHA, planSha256='b'*64,
            runtimePermissionIntent='runtime-permission-0123456789abcdef.json',
            runtimePermissionDevice=1, runtimePermissionInode=2, runtimePermissionOwner=3)
        intent=dict(format='a777-runtime-permission-intent-v1', operation='private0600',
            commit=SHA, planSha256='b'*64, path=g.HOME+'/public_html/.a777-runtime-active/config/runtime.php',
            device=1,inode=2,owner=3,bytes=20,beforeMode='0644',sha256='a'*64)
        f.files[g.DEPLOY_ROOT+'/'+d['runtimePermissionIntent']]=json.dumps(intent)
        f.modes[g.DEPLOY_ROOT+'/'+d['runtimePermissionIntent']]=600
        return f,d,intent
    def test_exact_private_intent_binding(self):
        f,d,_=self.bound(); g.permission_intent_guard(f,d)
    def test_forged_boolean_without_intent_refused(self):
        from cpanel_client import CpanelError
        with self.assertRaises(CpanelError): g.permission_intent_guard(NativeFake(),self.proof())
    def test_each_crossbound_field_refused(self):
        import json
        from cpanel_client import CpanelError
        for key in ('commit','planSha256','path','device','inode','owner','bytes','beforeMode','sha256'):
            f,d,intent=self.bound(); intent[key]='wrong'
            f.files[g.DEPLOY_ROOT+'/'+d['runtimePermissionIntent']]=json.dumps(intent)
            with self.assertRaises(CpanelError,msg=key): g.permission_intent_guard(f,d)
    def test_unknown_intent_name_refused(self):
        from cpanel_client import CpanelError
        f,d,_=self.bound(); d['runtimePermissionIntent']='../operator-plan.json'
        with self.assertRaises(CpanelError): g.permission_intent_guard(f,d)
    def test_plan_default_readonly(self):
        f=NativeFake(); g.preflight_plan(f,SHA)
        self.assertNotIn('secureRuntimeConfigPermissions', f.files[g.PLAN])
    def test_plan_explicit_optin(self):
        f=NativeFake(); g.preflight_plan(f,SHA,True)
        self.assertIn('"secureRuntimeConfigPermissions":true', f.files[g.PLAN])

if __name__=='__main__': unittest.main()
