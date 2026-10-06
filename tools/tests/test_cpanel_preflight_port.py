"""Real ConnectionFactory loopback port routing, no database or provider calls."""
from pathlib import Path
import shutil
import socket
import subprocess
import threading
import unittest


class FactoryPortTest(unittest.TestCase):
    def test_configured_port_reaches_owned_listener(self):
        root = Path(__file__).resolve().parents[2]
        php = shutil.which('php')
        self.assertIsNotNone(php)
        with socket.socket() as listener:
            listener.bind(('127.0.0.1', 0))
            listener.listen(1)
            listener.settimeout(10)
            port = listener.getsockname()[1]
            accepted = []
            def observe():
                try:
                    conn, _ = listener.accept()
                    with conn:
                        accepted.append(True)
                        # Close before a MySQL greeting: no credentials are exchanged.
                except TimeoutError:
                    pass
            worker = threading.Thread(target=observe)
            worker.start()
            code = r'''
require $argv[1] . '/app/src/Infrastructure/Config/AppConfig.php';
require $argv[1] . '/app/src/Infrastructure/Database/ConnectionFactory.php';
$c = \Architex\Infrastructure\Config\AppConfig::fromEnvironment([
'APP_ENV'=>'development','DB_HOST'=>'127.0.0.1','DB_PORT'=>$argv[2],
'DB_NAME'=>'archite4_dev_rel1','DB_USER'=>'fixture','DB_PASSWORD'=>'']);
try { \Architex\Infrastructure\Database\ConnectionFactory::create($c); exit(2); }
catch (RuntimeException $e) { exit($e->getMessage() === 'Database connection failed.' ? 0 : 3); }
'''
            result = subprocess.run([php, '-r', code, root.as_posix(), str(port)], capture_output=True, timeout=15)
            worker.join(11)
            self.assertEqual(result.returncode, 0, 'factory failure must be sanitized')
            self.assertEqual(accepted, [True], 'actual configured port must be reached')
            self.assertEqual(result.stdout, b'')


if __name__ == '__main__':
    unittest.main()
