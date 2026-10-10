import importlib.util
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('runner', Path(__file__).with_name('material3-native-acceptance-runner.py'))
assert spec is not None and spec.loader is not None
r = importlib.util.module_from_spec(spec)
spec.loader.exec_module(r)
SCRATCH = Path('E:/Hermes/home/cache/scratch')
# All synthetic fixtures and inherited subprocess temp paths stay on E:.
for key in ('TEMP', 'TMP', 'TMPDIR'):
    __import__('os').environ[key] = str(SCRATCH)
tempfile.tempdir = str(SCRATCH)

class SourceTests(unittest.TestCase):
    def test_reparse_ancestor_is_excluded_before_read(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            p = root/'app/public/index.php'
            p.parent.mkdir(parents=True)
            p.write_text('fixture')
            original = Path.is_junction
            def junction(path):
                return path == root/'app' or original(path)
            with patch.object(r, 'ROOT', root), patch.object(Path, 'is_junction', junction), patch.object(Path, 'read_bytes', side_effect=AssertionError('outside approved roots')):
                r.source_hash()

    def test_sensitive_files_never_read_and_frontend_changes_detected(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            for name in ['app/config/runtime.php', 'app/public/.env.local', 'app/src/auth.private.json', 'app/src/credentials.json', 'app/public/index.php']:
                p = root/name
                p.parent.mkdir(parents=True, exist_ok=True)
                p.write_text('fixture')
            original = Path.read_bytes
            def guarded(p):
                if p.name != 'index.php':
                    raise AssertionError('Sensitive read')
                return original(p)
            with patch.object(r, 'ROOT', root), patch.object(Path, 'read_bytes', guarded):
                before = r.source_hash()
                (root/'app/config/runtime.php').write_text('changed secret')
                self.assertEqual(before, r.source_hash())
                (root/'app/public/index.php').write_text('changed frontend')
                self.assertNotEqual(before, r.source_hash())

class PredicateTests(unittest.TestCase):
    def test_native_predicates_require_actual_true(self):
        good = dict(receiptVerified=True, nativeMigrationCurrent=True, datadirVerified=True)
        r.require_native_verification(good)
        for key in good:
            for value in (False, None, 1, 'true'):
                bad = dict(good, **{key: value})
                with self.subTest(key=key, value=value), self.assertRaises(RuntimeError):
                    r.require_native_verification(bad)
            bad = dict(good)
            del bad[key]
            with self.assertRaises(RuntimeError):
                r.require_native_verification(bad)

class CleanupTests(unittest.TestCase):
    def test_timeout_continues_closes_and_publishes_failure(self):
        from unittest.mock import Mock
        import subprocess
        with tempfile.TemporaryDirectory() as tmp:
            db, php = Mock(pid=101, returncode=0), Mock(pid=102, returncode=0)
            db.poll.side_effect = [None, None, None, 0]
            db.wait.side_effect = [subprocess.TimeoutExpired('wait', 1), 0]
            php.poll.side_effect = [None, None, 0]
            handle = Mock()
            receipt = {'dependencies': [{'pid':101, 'port':59946}], 'createdProcessesCleaned': []}
            with patch.object(r.subprocess, 'run', side_effect=subprocess.TimeoutExpired('shutdown', 1)), patch.object(r, 'source_hash', side_effect=TimeoutError()):
                rc = r.finalize([db, php], [handle], receipt, Path(tmp), 'before', 0)
            self.assertEqual(rc, 1)
            handle.close.assert_called_once()
            php.terminate.assert_called_once()
            db.terminate.assert_called_once()
            self.assertEqual(len(receipt['createdProcessesCleaned']), 2)
            self.assertTrue(receipt['createdProcessesCleaned'][1]['gracefulTimeout'])
            self.assertEqual(receipt['sourceHashError'], 'TimeoutError')
            self.assertEqual(__import__('json').loads((Path(tmp)/'lifecycle.json').read_text())['exitCode'], 1)

    def test_shutdown_rejection_and_wait_timeout_are_failures(self):
        from unittest.mock import Mock
        import subprocess
        for reject in (True, False):
            with self.subTest(reject=reject), tempfile.TemporaryDirectory() as tmp:
                db = Mock(pid=101, returncode=0)
                db.poll.side_effect = [None, None, 0]
                db.wait.side_effect = [0] if reject else [subprocess.TimeoutExpired('wait', 60), 0]
                receipt = {'dependencies': [{'pid':101, 'port':59946, 'created':True}], 'createdProcessesCleaned': []}
                with patch.object(r.subprocess, 'run', return_value=Mock(returncode=1 if reject else 0)), patch.object(r, 'source_hash', return_value='before'):
                    self.assertEqual(r.finalize([db], [], receipt, Path(tmp), 'before', 0), 1)
                self.assertFalse(receipt['createdProcessesCleaned'][0]['gracefulSucceeded'])
                self.assertTrue(receipt['createdProcessesCleaned'][0]['cleaned'])

    def test_kill_failure_does_not_skip_other_handles_or_receipt(self):
        from unittest.mock import Mock
        with tempfile.TemporaryDirectory() as tmp:
            child = Mock(pid=104, returncode=None)
            child.poll.return_value = None
            child.terminate.side_effect = OSError()
            child.kill.side_effect = OSError()
            bad, good = Mock(), Mock()
            bad.close.side_effect = OSError()
            receipt = {'dependencies': [], 'createdProcessesCleaned': []}
            with patch.object(r, 'source_hash', return_value='before'):
                self.assertEqual(r.finalize([child], [bad, good], receipt, Path(tmp), 'before', 0), 1)
            good.close.assert_called_once()
            self.assertTrue(receipt['createdProcessesCleaned'][0]['stillAlive'])
            self.assertTrue((Path(tmp)/'lifecycle.json').is_file())

    def test_initial_hash_failure_main_publishes_receipt(self):
        with tempfile.TemporaryDirectory() as tmp:
            with patch.object(r, 'WORK', Path(tmp)), patch.object(r, 'source_hash', side_effect=TimeoutError()), patch('sys.argv', ['runner', '--smoke']):
                self.assertEqual(r.main(), 1)
            receipts = list(Path(tmp).glob('supervisor-*/lifecycle.json'))
            self.assertEqual(len(receipts), 1)
            self.assertEqual(__import__('json').loads(receipts[0].read_text())['sourceHashError'], 'TimeoutError')

    def test_false_bootstrap_blocks_harness_and_publishes_receipt(self):
        import json
        from unittest.mock import Mock
        with tempfile.TemporaryDirectory() as tmp:
            work = Path(tmp)
            data = work/'data'
            (data/'architex_taskm37aa281_0bf7a858_test').mkdir(parents=True)
            (work/'state.json').write_text(json.dumps({'schema':'architex_taskm37aa281_0bf7a858_test', 'dbPort':59946}))
            (work/'receipt.json').write_text(json.dumps({'migrationCount':69, 'pending':0, 'datadirVerified':True}))
            listeners = [{'LocalPort':p, 'LocalAddress':'127.0.0.1', 'OwningProcess':p} for p in (59946,59947)]
            procs = [{'ProcessId':p} for p in (59946,59947)]
            with patch.object(r, 'WORK', work), patch.object(r, 'DATA', data), patch.object(r, 'source_hash', return_value='unchanged'), patch.object(r, 'listeners', return_value=listeners), patch.object(r, 'processes', return_value=procs), patch.object(r, 'owned', return_value=True), patch.object(r, 'run_json', side_effect=[{'migrationCount':69}, {'receiptVerified':1, 'nativeMigrationCurrent':True, 'datadirVerified':True}]), patch.object(r.subprocess, 'Popen') as launch, patch('sys.argv', ['runner', '--smoke']):
                self.assertEqual(r.main(), 1)
                launch.assert_not_called()
            receipt = json.loads(next(work.glob('supervisor-*/lifecycle.json')).read_text())
            self.assertEqual(receipt['collectorFailure'], 'Native verification predicates rejected')
            self.assertEqual(receipt['createdProcessesCleaned'], [])

if __name__ == '__main__':
    unittest.main()
