import hashlib
import hmac
import json
import tempfile
import time
import unittest
from pathlib import Path
from unittest.mock import patch

from tools.production_migration import HostOperations, LocalRestore, MigrationPipeline, canonical, tree_hash


# Controlled transport/service fixtures live ONLY in tests, never shipped runtime.
class HostFixture(HostOperations):
    def __init__(self, key, target, tree):
        self.key, self.target, self.tree = key, target, tree
        self.calls, self.fault, self.cleanup = [], None, False
        self.archive = b'controlled-contract-fixture-not-a-real-backup'
        self.lease = 'a' * 64
        self.runtime = 'b' * 64

    def envelope(self, stage, challenge, lease, binding):
        self.calls.append(stage)
        data = {'stage': stage, 'challenge': challenge, 'target': self.target,
                'lease': lease, 'runtimeSha256': self.runtime,
                'activeTreeSha256': self.tree, 'candidateTreeSha256': self.tree, **binding}
        if stage == 'quiescence':
            data.update(lease=self.lease, web='blocked', dispatch='stopped', cron='stopped',
                        drainedAt=int(time.time()), expiresAt=int(time.time()) + 600)
        if stage == 'backup':
            data.update(archiveSha256=hashlib.sha256(self.archive).hexdigest(), backupId='c'*64,
                        bytes=len(self.archive), retainedPath='/private/retained.axbackup')
        if stage in ('status', 'migrate'):
            data['report'] = {'appliedCount': 0, 'pendingCount': 0, 'appliedVersions': ['0001_fixture'], 'pendingVersions': []}
        if stage == 'readiness':
            data.update(build={'id': 'candidate'}, readiness={'status':'ready', 'components':
                {name:{'status':'ready'} for name in ('database','migrations','jobs','mail')}})
        if self.fault == stage:
            if stage == 'identity': data['target'] = {**self.target, 'database':'other'}
            elif stage == 'quiescence': data['cron'] = 'running'
            elif stage == 'backup': data['archiveSha256'] = 'd'*64
            elif stage == 'readiness': data['build'] = {'id':'wrong'}
            elif stage == 'migrate': raise RuntimeError('controlled partial DDL failure')
        return {'payload':data, 'signature':hmac.new(self.key,canonical(data),hashlib.sha256).hexdigest()}

    def observe(self, stage, challenge, lease, binding):
        self.binding = dict(binding)
        return self.envelope(stage, challenge, lease, binding)
    def quiesce(self, challenge):
        return self.envelope('quiescence', challenge, None, self.binding)
    def backup(self, challenge, lease):
        return self.envelope('backup', challenge, lease, self.binding)
    def download(self, path, destination):
        destination.write_bytes(self.archive)
    def candidate(self, action, challenge, lease, binding):
        return self.envelope(action, challenge, lease, binding)
    def cleanup_helpers(self):
        self.cleanup = True
        if self.fault == 'cleanup': raise RuntimeError('unknown remote listing')


class RestoreFixture:
    def __init__(self): self.called, self.fail = False, False
    def restore(self, archive, migrations):
        self.called = True
        if self.fail: raise RuntimeError('controlled restore refusal')
        return {'backupId':'c'*64, 'archiveSha256':hashlib.sha256(archive.read_bytes()).hexdigest(),
                'activeTreeSha256':tree_hash(migrations), 'database':'architex_taskfixture_12345678_test',
                'manifestSha256':'e'*64, 'rowCount':2, 'fileCount':1}


class ProductionMigrationContracts(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name)
        self.migrations = self.root/'migrations'; self.migrations.mkdir()
        (self.migrations/'0001_fixture.sql').write_text('-- architex:migration-mode=ddl\nCREATE TABLE fixture(id INT);')
        self.target = dict(account='owned', domain='example.test', documentRoot='/owned/public',runtimeRoot='/owned/runtime',database='owned_db')
        self.key = b'k'*32
        self.host = HostFixture(self.key,self.target,tree_hash(self.migrations))
        self.restore = RestoreFixture()
        self.pipeline = MigrationPipeline(self.host,self.restore,self.target,self.key,self.root/'retained',{'id':'candidate'})
    def tearDown(self): self.temp.cleanup()
    def journal(self): return json.loads(next((self.root/'retained').glob('migration-*.json')).read_text())
    def test_order_and_success_retains_maintenance_archive_and_report(self):
        result=self.pipeline.run(self.migrations, self.migrations)
        self.assertEqual(result['state'],'migration_verified_maintenance_retained')
        self.assertTrue(self.restore.called)
        self.assertTrue(self.host.cleanup)
        self.assertEqual(self.host.calls.count('migrate'),1)
        self.assertLess(self.host.calls.index('backup'),self.host.calls.index('migrate'))
        self.assertTrue(list((self.root/'retained').glob('*.axbackup')))
        self.assertFalse(self.journal()['databaseRollbackPerformed'])
    def test_wrong_target_never_quiesces_or_migrates(self):
        self.host.fault='identity'
        with self.assertRaisesRegex(RuntimeError,'target'): self.pipeline.run(self.migrations, self.migrations)
        self.assertEqual(self.host.calls,['identity']); self.assertTrue(self.host.cleanup)
    def test_owned_cron_running_blocks_backup_and_ddl(self):
        self.host.fault='quiescence'
        with self.assertRaisesRegex(RuntimeError,'quiescence'): self.pipeline.run(self.migrations, self.migrations)
        self.assertNotIn('backup',self.host.calls);self.assertNotIn('migrate',self.host.calls)
    def test_archive_transfer_tamper_blocks_restore_and_ddl(self):
        self.host.fault='backup'
        with self.assertRaisesRegex(RuntimeError,'integrity'): self.pipeline.run(self.migrations, self.migrations)
        self.assertFalse(self.restore.called);self.assertNotIn('migrate',self.host.calls)
    def test_restore_failure_blocks_even_candidate_status(self):
        self.restore.fail=True
        with self.assertRaisesRegex(RuntimeError,'restore refusal'): self.pipeline.run(self.migrations, self.migrations)
        self.assertNotIn('status',self.host.calls)
    def test_partial_ddl_retains_report_and_archive_no_database_undo_claim(self):
        self.host.fault='migrate'
        with self.assertRaisesRegex(RuntimeError,'partial DDL'): self.pipeline.run(self.migrations, self.migrations)
        report=self.journal()
        self.assertTrue(report['ddlMayHaveCommitted']); self.assertFalse(report['databaseRollbackPerformed'])
        self.assertEqual(report['state'],'recovery_required')
        self.assertTrue(list((self.root/'retained').glob('*.axbackup')))
    def test_wrong_build_blocks_success(self):
        self.host.fault='readiness'
        with self.assertRaisesRegex(RuntimeError,'identity'): self.pipeline.run(self.migrations, self.migrations)
        self.assertEqual(self.journal()['state'],'recovery_required')
    def test_cleanup_unknown_blocks_success(self):
        self.host.fault='cleanup'
        with self.assertRaisesRegex(RuntimeError,'readback'): self.pipeline.run(self.migrations, self.migrations)
        self.assertEqual(self.journal()['state'],'recovery_required')
    def test_boolean_attestation_cannot_be_receipt(self):
        with self.assertRaisesRegex(RuntimeError,'receipt'):self.pipeline.receipt({'backup_verified':True},'backup')
    def test_replayed_challenge_and_signature_tamper_rejected(self):
        envelope=self.host.envelope('identity','old',None,{})
        with self.assertRaisesRegex(RuntimeError,'challenge'):self.pipeline.receipt(envelope,'identity')
        envelope=self.host.envelope('identity',self.pipeline.challenge,None,{})
        envelope['payload']['target']={}
        with self.assertRaisesRegex(RuntimeError,'authentication'):self.pipeline.receipt(envelope,'identity')
    def test_missing_host_adapter_cannot_instantiate(self):
        with self.assertRaises(TypeError):HostOperations()
    def test_local_restore_production_target_rejected_before_subprocess(self):
        with patch('tools.production_migration.subprocess.run') as run:
            with self.assertRaisesRegex(RuntimeError,'loopback'):LocalRestore('php',self.root,{'APP_ENV':'production'},self.root/'files')
            run.assert_not_called()
    def test_success_requires_absence_disposition(self):
        self.pipeline.run(self.migrations, self.migrations)
        self.assertEqual(self.journal()['events'][-1]['disposition'], 'removed')

    def retained_fixture(self):
        from tools.cpanel_host_operations import CpanelHostOperations
        from unittest.mock import Mock
        host = object.__new__(CpanelHostOperations)
        host.recovery_owned = True
        host.path = '/home/owned/public/operator.php'
        self.target.update(documentRoot='/home/owned/public', runtimeRoot='/home/owned/runtime')
        self.pipeline.target = dict(self.target)
        host.target = dict(self.target)
        host.candidate_root = '/home/owned/candidate'
        host.cli_binary = '/usr/local/bin/php'
        host.process_model = {'fixture': True}
        host.recovery_paths = {'fixture': True}
        import base64
        config = {'target':host.target, 'candidateRoot':host.candidate_root,
            'keySha256':hashlib.sha256(self.key).hexdigest(), 'cliBinary':host.cli_binary,
            'processModel':host.process_model, 'recoveryPaths':host.recovery_paths}
        source = Path('tools/temporary_host_helper.php').read_text().replace('/*OPERATOR_CONFIG*/',
            "$operator = json_decode(base64_decode('" + base64.b64encode(canonical(config)).decode() +
            "'), true, 32, JSON_THROW_ON_ERROR);")
        helper_hash = hashlib.sha256(source.encode()).hexdigest()
        host.client = Mock(); host.client.exists.return_value = True
        host.recovery_file = self.root/'retained'/'operator.json'
        host.recovery_file.parent.mkdir(exist_ok=True)
        host.recovery_file.write_text('{}')  # no real secret fixture
        host.recovery_file.chmod(0o600); host.recovery_file.parent.chmod(0o700)
        def observe(stage, challenge, lease, binding):
            if stage not in ('helper_identity', 'recovery_state'):
                return self.host.observe(stage, challenge, lease, binding)
            data = {'stage':stage, 'challenge':challenge, 'target':self.target, 'lease':lease}
            if stage == 'helper_identity': data['helperSha256'] = helper_hash
            else:
                data['recovery'] = {'challenge':challenge, 'lease':lease, 'identity':dict(self.pipeline.binding)}
                data['fence'] = {'owner':hashlib.sha256(lease.encode()).hexdigest(),
                    'fingerprint':hashlib.sha256(canonical(self.target)).hexdigest()}
            return {'payload':data, 'signature':hmac.new(self.key,canonical(data),hashlib.sha256).hexdigest()}
        host.observe = observe
        host.quiesce = self.host.quiesce; host.backup = self.host.backup
        host.download = self.host.download; host.candidate = self.host.candidate
        self.pipeline.host = host
        return host

    def test_authenticated_retention_is_not_cleaned_success(self):
        self.retained_fixture()
        result = self.pipeline.run(self.migrations, self.migrations)
        self.assertEqual(result['state'], 'migration_verified_recovery_helper_retained')
        self.assertEqual(self.journal()['events'][-1]['disposition'], 'retained_recovery')
        self.assertNotIn('absence_readback', [e.get('result') for e in self.journal()['events']])

    def test_error_retained_recovery_preserves_original_failure(self):
        self.retained_fixture(); self.host.fault = 'migrate'
        with self.assertRaisesRegex(RuntimeError, 'partial DDL'):
            self.pipeline.run(self.migrations, self.migrations)
        self.assertEqual(self.journal()['state'], 'recovery_required')
        self.assertEqual(self.journal()['events'][-1]['disposition'], 'retained_recovery')

    def test_unexpected_retention_is_rejected(self):
        host = self.retained_fixture(); host.client.exists.return_value = False
        with self.assertRaisesRegex(RuntimeError, 'readback'):
            self.pipeline.run(self.migrations, self.migrations)
        self.assertEqual(self.journal()['events'][-1]['result'], 'unproven')

    def test_cleanup_lost_response_never_masks_original_exception(self):
        self.host.fault = 'migrate'
        with patch.object(self.host, 'cleanup_helpers', side_effect=TimeoutError('lost response')):
            with self.assertRaisesRegex(RuntimeError, 'partial DDL') as caught:
                self.pipeline.run(self.migrations, self.migrations)
        self.assertIn('also failed', caught.exception.__notes__[0])

    def test_report_failure_does_not_mask_original_exception(self):
        self.host.fault = 'migrate'
        original = self.pipeline.persist
        def persist(state):
            if state == 'recovery_required': raise OSError('report unavailable')
            return original(state)
        with patch.object(self.pipeline, 'persist', side_effect=persist):
            with self.assertRaisesRegex(RuntimeError, 'partial DDL') as caught:
                self.pipeline.run(self.migrations, self.migrations)
        self.assertIn('persistence also failed', caught.exception.__notes__[-1])

    def test_tree_change_rejected_before_quiescence(self):
        (self.migrations/'0001_fixture.sql').write_text('changed')
        with self.assertRaisesRegex(RuntimeError,'active host tree'):self.pipeline.run(self.migrations, self.migrations)
        self.assertNotIn('quiescence',self.host.calls)

if __name__ == '__main__':unittest.main()
