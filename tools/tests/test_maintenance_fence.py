"""Real PHP subprocess/concurrency fixtures; no host or database connections."""
import json
import pathlib
import shutil
import subprocess
import tempfile
import time
import socket
import urllib.request
import urllib.error
import unittest

ROOT = pathlib.Path(__file__).resolve().parents[2]
PHP = shutil.which('php')
CLASS = ROOT / 'app/src/Infrastructure/Deployment/MaintenanceFence.php'

@unittest.skipUnless(PHP, 'PHP unavailable')
class MaintenanceFenceTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(dir=ROOT.parent)
        self.root = pathlib.Path(self.temp.name)
        self.fence = self.root / 'fence'
        self.fence.mkdir()
        self.token, self.fingerprint = 'a' * 64, 'b' * 64

    def tearDown(self):
        self.temp.cleanup()

    def program(self, body):
        return ('require ' + json.dumps(str(CLASS).replace('\\', '/')) + ';'
                '$f=new \\Architex\\Infrastructure\\Deployment\\MaintenanceFence('
                + json.dumps(str(self.fence).replace('\\', '/')) + ');' + body)

    def run_php(self, body):
        return subprocess.run([PHP, '-r', self.program(body)], capture_output=True, text=True)

    def stop(self, timeout=1):
        return self.run_php(f"echo json_encode($f->stop('{self.token}','{self.fingerprint}',{timeout}));")

    def test_stop_and_resume_owned(self):
        result = self.stop()
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(json.loads(result.stdout)['phase'], 'drained')
        self.assertNotEqual(self.run_php('$f->enter();').returncode, 0)
        wrong = self.run_php(f"$f->resume('{'c'*64}','{self.fingerprint}');")
        self.assertNotEqual(wrong.returncode, 0)
        self.assertTrue((self.fence / 'lease.json').exists())
        resumed = self.run_php(f"$f->resume('{self.token}','{self.fingerprint}');")
        self.assertEqual(resumed.returncode, 0, resumed.stderr)
        self.assertEqual(self.run_php('$f->enter();').returncode, 0)

    def test_shared_request_lifetime_and_timeout_retains_lease(self):
        worker = subprocess.Popen([PHP, '-r', self.program('$f->enter();echo "ready\\n";flush();usleep(800000);')],
                                  stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
        try:
            self.assertEqual(worker.stdout.readline().strip(), 'ready')
            self.assertNotEqual(self.stop(.05).returncode, 0)
            self.assertEqual(json.loads((self.fence/'lease.json').read_text())['phase'], 'draining')
            self.assertNotEqual(self.run_php('$f->enter();').returncode, 0)
            self.assertEqual(worker.wait(timeout=5), 0)
            self.assertEqual(self.stop().returncode, 0)
        finally:
            if worker.poll() is None: worker.kill()
            worker.communicate()

    def test_stop_really_waits_for_inflight_reader(self):
        worker = subprocess.Popen([PHP, '-r', self.program('$f->enter();echo "ready\\n";flush();usleep(600000);')],
                                  stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
        try:
            self.assertEqual(worker.stdout.readline().strip(), 'ready')
            start = time.monotonic()
            result = self.stop(3)
            self.assertEqual(result.returncode, 0, result.stderr)
            self.assertGreater(time.monotonic() - start, .35)
            self.assertEqual(worker.wait(timeout=5), 0)
        finally:
            if worker.poll() is None: worker.kill()
            worker.communicate()

    def test_corrupt_state_fails_closed(self):
        (self.fence / 'lease.json').write_text('broken')
        self.assertNotEqual(self.run_php('$f->enter();').returncode, 0)
        self.assertNotEqual(self.stop().returncode, 0)
        self.assertEqual((self.fence/'lease.json').read_text(), 'broken')

    def test_other_operator_cannot_replace_lease(self):
        self.assertEqual(self.stop().returncode, 0)
        before = (self.fence/'lease.json').read_bytes()
        self.assertNotEqual(self.run_php(f"$f->stop('{'d'*64}','{self.fingerprint}');").returncode, 0)
        self.assertEqual((self.fence/'lease.json').read_bytes(), before)

    def test_bootstrap_blocks_cli_before_autoload_or_configuration(self):
        app = self.root/'app'
        (app/'src/Infrastructure/Deployment').mkdir(parents=True)
        shutil.copy(CLASS, app/'src/Infrastructure/Deployment/MaintenanceFence.php')
        shutil.copy(ROOT/'app/bootstrap.php', app/'bootstrap.php')
        (app/'src/Infrastructure/Config').mkdir(parents=True)
        shutil.copy(ROOT/'app/src/Infrastructure/Config/EnvironmentLoader.php', app/'src/Infrastructure/Config/EnvironmentLoader.php')
        self.fence = app/'storage/maintenance'
        self.fence.mkdir(parents=True)
        self.assertEqual(self.stop().returncode, 0)
        result = subprocess.run([PHP, str(app/'bootstrap.php')], capture_output=True, text=True)
        self.assertEqual(result.returncode, 75)
        self.assertEqual(result.stderr.strip(), 'Application maintenance in progress.')
        self.assertNotIn('dependencies', result.stderr)

    def test_runtime_configuration_places_fence_outside_release(self):
        app = self.root/'external-app'
        (app/'src/Infrastructure/Deployment').mkdir(parents=True)
        (app/'src/Infrastructure/Config').mkdir(parents=True)
        (app/'config').mkdir()
        (app/'vendor').mkdir()
        storage = self.root/'private-files'; storage.mkdir()
        shutil.copy(CLASS, app/'src/Infrastructure/Deployment/MaintenanceFence.php')
        shutil.copy(ROOT/'app/src/Infrastructure/Config/EnvironmentLoader.php', app/'src/Infrastructure/Config/EnvironmentLoader.php')
        shutil.copy(ROOT/'app/bootstrap.php', app/'bootstrap.php')
        (app/'vendor/autoload.php').write_text('<?php')
        import base64, os
        encoded = base64.b64encode(json.dumps({'APP_ENV':'production',
            'FILE_STORAGE_PATH':str(storage).replace('\\\\','/')}).encode()).decode()
        (app/'config/runtime.php').write_text("<?php return json_decode(base64_decode('"+encoded+"'),true);")
        environment=dict(os.environ)
        environment.pop('FILE_STORAGE_PATH',None); environment.pop('APP_ENV',None)
        result=subprocess.run([PHP,str(app/'bootstrap.php')],env=environment,capture_output=True,text=True)
        self.assertEqual(result.returncode,0,result.stderr)
        self.assertTrue((storage/'maintenance/writers.lock').exists())
        self.assertFalse((app/'.maintenance').exists())
        self.assertFalse((app/'storage').exists())

    def test_web_bootstrap_returns_503_during_lease(self):
        app = self.root/'webapp'
        (app/'src/Infrastructure/Deployment').mkdir(parents=True)
        shutil.copy(CLASS, app/'src/Infrastructure/Deployment/MaintenanceFence.php')
        shutil.copy(ROOT/'app/bootstrap.php', app/'bootstrap.php')
        (app/'src/Infrastructure/Config').mkdir(parents=True)
        shutil.copy(ROOT/'app/src/Infrastructure/Config/EnvironmentLoader.php', app/'src/Infrastructure/Config/EnvironmentLoader.php')
        self.fence = app/'storage/maintenance'
        self.fence.mkdir(parents=True)
        self.assertEqual(self.stop().returncode, 0)
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        server = subprocess.Popen([PHP, '-S', f'127.0.0.1:{port}', '-t', str(app)],
                                  stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        try:
            response = None
            for _ in range(100):
                if server.poll() is not None: self.fail('Owned fixture server exited')
                try:
                    urllib.request.urlopen(f'http://127.0.0.1:{port}/bootstrap.php', timeout=1)
                    self.fail('Fenced request unexpectedly succeeded')
                except urllib.error.HTTPError as error:
                    response = error
                    break
                except urllib.error.URLError:
                    time.sleep(.02)
            self.assertIsNotNone(response)
            self.assertEqual(response.code, 503)
            self.assertEqual(response.headers['Cache-Control'], 'no-store')
            self.assertEqual(response.read(), b'Application maintenance in progress.')
            response.close()
        finally:
            server.terminate()
            server.communicate(timeout=5)

if __name__ == '__main__': unittest.main()
