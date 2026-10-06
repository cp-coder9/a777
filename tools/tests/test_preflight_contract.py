import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

from tools.deploy_php_ftps import probe_source
from tools.tests.test_repair_php_deployment import FakeCpanel, deployment, make_release
from tools.repair_php_deployment import HttpCheck, DeploymentFailure


class ProbeContractTest(unittest.TestCase):
    def test_generated_probe_uses_pending_count_not_method(self):
        source = probe_source('runtime').decode()
        self.assertNotIn('->isCurrent()', source)
        self.assertIn('pendingCount', source)

    def run_probe(self, scenario):
        php = shutil.which('php')
        self.assertIsNotNone(php, 'PHP required for executable probe regression')
        with tempfile.TemporaryDirectory(dir=os.environ.get('TMPDIR')) as temp:
            root = Path(temp)
            (root / 'public').mkdir()
            (root / 'runtime').mkdir()
            fixture = Path(__file__).with_name('fixtures') / 'preflight-bootstrap.php'
            (root / 'runtime' / 'bootstrap.php').write_bytes(fixture.read_bytes())
            probe = root / 'public' / 'probe.php'
            probe.write_bytes(probe_source('runtime'))
            lint = subprocess.run([php, '-l', str(probe)], capture_output=True, text=True)
            self.assertEqual(lint.returncode, 0, lint.stderr)
            report = Path(__file__).resolve().parents[2] / 'app/src/Infrastructure/Database/MigrationReport.php'
            result = subprocess.run([php, str(probe)], env={**os.environ, 'PROBE_SCENARIO': scenario, 'FIXTURE_REPORT_PATH': str(report)}, capture_output=True, text=True)
            self.assertEqual(result.returncode, 0, result.stderr)
            self.assertNotIn('SECRET', result.stdout)
            return json.loads(result.stdout)

    def test_array_status_current(self):
        self.assertTrue(self.run_probe('array')['ok'])

    def test_actual_report_current(self):
        self.assertTrue(self.run_probe('report')['ok'])

    def test_pending_migrations_fail_closed(self):
        payload = self.run_probe('pending')
        self.assertFalse(payload['ok'])
        self.assertEqual(payload['reason'], 'migrations_pending')

    def test_mail_not_ready_and_completed_stages(self):
        payload = self.run_probe('mail_not_ready')
        self.assertFalse(payload['ok'])
        self.assertEqual(payload['reason'], 'mail_not_ready')
        self.assertTrue(payload['database'])
        self.assertTrue(payload['migrations'])
        self.assertFalse(payload['mail'])

    def test_stage_exception_diagnostics_redacted(self):
        for stage in ('bootstrap', 'config', 'database', 'migrations', 'mail'):
            with self.subTest(stage=stage):
                payload = self.run_probe(stage)
                self.assertFalse(payload['ok'])
                self.assertEqual(payload['reason'], stage + '_failed')

    def test_bootstrap_exit_remains_closed(self):
        payload = self.run_probe('exit')
        self.assertFalse(payload['ok'])
        self.assertEqual(payload['reason'], 'bootstrap_failed')

    def test_malformed_status_fail_closed(self):
        for scenario in ('missing', 'string', 'negative'):
            with self.subTest(scenario=scenario):
                self.assertFalse(self.run_probe(scenario)['ok'])

    def test_repair_failure_whitelists_diagnostics(self):
        for body in ({'ok': False, 'database': True, 'phpVersionId': True, 'reason': 'SECRET', 'password': 'SECRET'}, {'ok': False, 'reason': 'migrations_pending', 'pendingCount': 2}, 'SECRET'):
            with self.subTest(body=body), tempfile.TemporaryDirectory(dir=os.environ.get('TMPDIR')) as temp:
                remote = FakeCpanel([])
                class Http:
                    def request(self, url):
                        raw = body.encode() if isinstance(body, str) else json.dumps(body).encode()
                        return HttpCheck(url, url, 503, 'application/json', raw)
                runner = deployment(make_release(Path(temp)), remote, Http())
                with self.assertRaises(DeploymentFailure) as caught:
                    runner._run_preflight()
                message = str(caught.exception)
                self.assertIn('httpStatus', message)
                self.assertNotIn('SECRET', message)
                self.assertNotIn('phpVersionId', message)
                self.assertFalse(remote.exists(runner.paths.probe))
                if isinstance(body, dict) and body.get('reason') == 'migrations_pending':
                    self.assertIn('migrations_pending', message)
