import hashlib
import json
from pathlib import Path
import sys
import unittest
from unittest.mock import patch
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import cpanel_git_prepare as g
from cpanel_client import CpanelError
from test_cpanel_git_preflight import NativeFake, SHA


def outcome():
    return dict(format='a777-cpanel-restore-rehearsal-v1', mode='restore-rehearsal', commit=SHA,
        status='rehearsal-verified', sourceDatabaseDdlTouched=False, targetDatabaseDdlTouched=True,
        isolatedRehearsalPassed=True, databaseDdlTouched=True, liveFilesTouched=False,
        apacheConfigurationTouched=False, cronConfigurationTouched=False,
        targetDatabase='archite4_ar_0123456789abcdef_test', archiveSha256='a'*64,
        backupId='backup_123', rowCount=2, fileCount=1, tableCount=3)

class RehearsalFake(NativeFake):
    def _uapi(self, module, function, params):
        result = super()._uapi(module, function, params)
        if module == 'VersionControlDeployment' and function == 'retrieve':
            for name in list(self.files):
                if '/preflight-' in name: del self.files[name]
            data = outcome()
            data['planSha256'] = hashlib.sha256(self.files[g.PLAN].encode()).hexdigest()
            data['archivePassword'] = 'PRIVATE_SENTINEL_DO_NOT_EMIT'
            self.files[g.DEPLOY_ROOT+'/restore-rehearsal-'+SHA+'-0123456789abcdef.json'] = json.dumps(data)
        return result

class RehearsalTests(unittest.TestCase):
    def initial_failure(self):
        f=NativeFake()
        original_api=f._uapi
        def api(module,function,params):
            if module=='Mysql' and function=='list_databases': return [{'database':'archite4_dev_rel1'}]
            return original_api(module,function,params)
        f._uapi=api
        g.preflight_plan(f,SHA,expected_runtime_config_sha256='a'*64,mode='restore-rehearsal')
        old=f.files[g.PLAN]; digest=hashlib.sha256(old.encode()).hexdigest()
        data=dict(format='a777-cpanel-restore-rehearsal-v1',mode='restore-rehearsal',commit=SHA,
                  planSha256=digest,status='failed',sourceDatabaseDdlTouched=False,
                  targetDatabaseDdlTouched=False,databaseDdlTouched=False,isolatedRehearsalPassed=False,
                  liveFilesTouched=False,apacheConfigurationTouched=False,cronConfigurationTouched=False)
        path=g.DEPLOY_ROOT+'/restore-rehearsal-'+SHA+'-0123456789abcdef.json'
        f.files[path]=json.dumps(data); f.modes[path]=600
        return f,path

    def test_explicit_initial_retry_archives_old_plan_without_deleting_receipt(self):
        f,path=self.initial_failure(); old=f.files[g.PLAN]
        g.preflight_plan(f,'b'*40,expected_runtime_config_sha256='a'*64,mode='restore-rehearsal',retry_initial_rehearsal=True)
        self.assertEqual(json.loads(f.files[g.PLAN])['expectedCommit'],'b'*40)
        archive=g.DEPLOY_ROOT+'/plan-roll-'+hashlib.sha256(old.encode()).hexdigest()+'.json'
        self.assertIn(archive,f.files); self.assertIn(path,f.files)

    def test_initial_retry_refuses_any_target_effect_or_recovery_artifact(self):
        for changed in ('targetDatabaseDdlTouched','sourceDatabaseDdlTouched','liveFilesTouched'):
            f,path=self.initial_failure(); d=json.loads(f.files[path]); d[changed]=True; f.files[path]=json.dumps(d)
            with self.assertRaises(CpanelError):
                g.preflight_plan(f,'b'*40,expected_runtime_config_sha256='a'*64,mode='restore-rehearsal',retry_initial_rehearsal=True)

    def test_initial_retry_refuses_retained_recovery_directory(self):
        f,_=self.initial_failure()
        path=g.DEPLOY_ROOT+'/recovery-'+SHA+'-'+'a'*32
        f.dirs.add(path); f.modes[path]=700
        with self.assertRaises(CpanelError):
            g.preflight_plan(f,'b'*40,expected_runtime_config_sha256='a'*64,mode='restore-rehearsal',retry_initial_rehearsal=True)

    def test_initial_retry_refuses_provider_owned_target_inventory(self):
        f,_=self.initial_failure(); old_api=f._uapi
        def api(module,function,params):
            if module=='Mysql' and function=='list_databases':
                return [{'database':'archite4_ar_0123456789abcdef_test'}]
            return old_api(module,function,params)
        f._uapi=api
        with self.assertRaises(CpanelError):
            g.preflight_plan(f,'b'*40,expected_runtime_config_sha256='a'*64,mode='restore-rehearsal',retry_initial_rehearsal=True)

    def test_complete_actual_runner_contract_and_redaction(self):
        f = RehearsalFake()
        with patch.object(g, 'clone', return_value={'status':'cloned_and_pulled','commit':SHA}):
            report = g.native_preflight(f, SHA, pause=0, mode='restore-rehearsal', expected_runtime_config_sha256='a'*64)
        self.assertEqual(report['status'], 'restore_rehearsal_complete')
        self.assertTrue(report['tokenAbsenceVerified'])
        self.assertNotIn('PRIVATE_SENTINEL', json.dumps(report))
        self.assertNotIn(g.TOKEN, f.files)
        self.assertEqual(json.loads(f.files[g.PLAN])['mode'], 'restore-rehearsal')
        with self.assertRaises(CpanelError):
            g.preflight_plan(f, SHA, expected_runtime_config_sha256='a'*64, mode='restore-rehearsal')

    def test_source_and_live_envelope_fail_closed(self):
        self.assertTrue(g.rehearsal_safe(outcome()))
        for key in ('sourceDatabaseDdlTouched','liveFilesTouched','apacheConfigurationTouched','cronConfigurationTouched','migrationAuthorized','promotionAuthorized','liveDdlAuthorized'):
            data=outcome(); data[key]=True
            self.assertFalse(g.rehearsal_safe(data),key)
        for key in ('isolatedRehearsalPassed','targetDatabaseDdlTouched'):
            data=outcome(); data[key]=1
            self.assertFalse(g.rehearsal_safe(data))
        data=outcome(); data['targetDatabase']='archite4_dev_rel1'
        self.assertFalse(g.rehearsal_safe(data))

    def test_retained_receipt_preserves_boolean_storage_inspection_contract(self):
        f,path=self.initial_failure()
        data=json.loads(f.files[path]); data['storageCanonical']=True
        self.assertTrue(g.rehearsal_safe(data,True))
        data['storageCanonical']='PRIVATE_SENTINEL'
        self.assertFalse(g.rehearsal_safe(data,True))

    def test_failed_task_and_cleanup_failure_never_success(self):
        for terminal in ('failed','succeeded'):
            f=RehearsalFake(terminal=terminal)
            with patch.object(g,'clone',return_value={'status':'cloned_and_pulled','commit':SHA}), patch.object(g,'cleanup_token',return_value=False):
                report=g.native_preflight(f,SHA,pause=0,mode='restore-rehearsal',expected_runtime_config_sha256='a'*64)
            self.assertNotEqual(report['status'],'restore_rehearsal_complete')
            self.assertFalse(report['tokenAbsenceVerified'])

    def test_pin_required_before_remote_calls(self):
        f=NativeFake()
        report=g.native_preflight(f,SHA,mode='restore-rehearsal')
        self.assertEqual(report['status'],'failed')
        self.assertFalse(f.calls)

    def test_old_preflight_roll_needs_receipt(self):
        f=NativeFake()
        g.preflight_plan(f,SHA)
        with self.assertRaises(CpanelError):
            g.preflight_plan(f,SHA,expected_runtime_config_sha256='a'*64,mode='restore-rehearsal')
        with patch.object(g,'clone',return_value={'status':'cloned_and_pulled','commit':SHA}):
            self.assertEqual(g.native_preflight(f,SHA,pause=0)['status'],'preflight_complete')
        g.preflight_plan(f,SHA,expected_runtime_config_sha256='a'*64,mode='restore-rehearsal')
        self.assertTrue(any('/plan-roll-' in p for p in f.files))

    def test_dispatcher_no_live_automatic_mode(self):
        root=Path(__file__).resolve().parents[2]
        self.assertIn('--from-plan',(root/'.cpanel.yml').read_text())
        self.assertNotIn('--deploy',(root/'.cpanel.yml').read_text())
        text=(root/'tools/cpanel_git_deploy.php').read_text()
        self.assertIn("['preflight', 'restore-rehearsal']",text)
        self.assertIn('rehearsal-intent-',text)
        self.assertIn('explicit_plan_mode_required',text)
        self.assertNotIn("$plan['mode'] = 'preflight'",text)

if __name__ == '__main__': unittest.main()
