import importlib.util
from pathlib import Path
import subprocess
import tempfile
import unittest
import os
import sys
from unittest.mock import patch
sys.path.insert(0, str(Path(__file__).parents[1]))

spec = importlib.util.spec_from_file_location('profile_ui', Path(__file__).parents[1] / 'cpanel_ui_release.py')
ui = importlib.util.module_from_spec(spec)
spec.loader.exec_module(ui)


class WindowsProfileTest(unittest.TestCase):
    def test_git_children_have_no_inherited_credentials_or_configuration(self):
        real_run = subprocess.run
        observed = []
        def run(command, **kwargs):
            observed.append(kwargs.get('env', os.environ.copy()))
            return real_run(command, **kwargs)
        poisoned = {key: 'fixture-not-a-real-secret' for key in (
            'A777_CPANEL_API_TOKEN', 'A777_CPANEL_PASSWORD', 'A777_CPANEL_SESSION',
            'GITHUB_TOKEN', 'GH_TOKEN', 'SSH_AUTH_SOCK', 'UNLISTED_SECRET',
            'GIT_TRACE')}
        with patch.dict(os.environ, poisoned), patch.object(ui.subprocess, 'run', run):
            ui.git(Path(__file__).parents[2], 'rev-parse', 'HEAD')
        self.assertTrue(observed)
        for environment in observed:
            self.assertFalse(set(poisoned).intersection(environment))
            self.assertNotEqual(environment.get('HOME'), os.environ.get('HOME'))
            self.assertEqual(environment.get('GIT_CONFIG_NOSYSTEM'), '1')

    def test_windows_profile_inspection_refuses_manifest_and_remote_byte_changes(self):
        import json
        sys.path.insert(0, str(Path(__file__).parent))
        from test_cpanel_ui_release import Contracts
        expected = b'baseline\r\n'
        candidate = {'commit': 'b' * 40, 'inventorySha256': 'c' * 64,
            'changes': [], 'baselineSourceProfile': 'windows', 'baselineSourceCommit': ui.BASELINE,
            'files': {'app/src/a.php': {'baselineSha256': ui.digest(expected),
                'baselineBytes': len(expected)}}}
        class Client:
            def account_home(self): return ui.HOME
            def _uapi(self, *args): return Contracts().hash_inventory()
        for mode, error in (('match', None), ('manifest_hash', 'live_manifest_baseline_mismatch'),
                ('manifest_bytes', 'live_manifest_baseline_mismatch'),
                ('remote_changed', 'live_bytes_baseline_mismatch'),
                ('remote_lf', 'live_bytes_baseline_mismatch')):
            row = {'sha256': ui.digest(expected), 'bytes': len(expected)}
            if mode == 'manifest_hash': row['sha256'] = ui.digest(b'changed\r\n')
            if mode == 'manifest_bytes': row['bytes'] += 1
            remote = b'changed\r\n' if mode == 'remote_changed' else b'baseline\n' if mode == 'remote_lf' else expected
            bodies = {ui.DOCROOT + '/' + name: ui.shim(name) for name in ('index.php', 'app.php', 'legal.php')}
            bodies.update({ui.RUNTIME + '/config/runtime.php': b'fixture',
                ui.RUNTIME + '/release.json': json.dumps({'format': 'architex-release-v1',
                    'sourceCommit': ui.BASELINE, 'sourceDirty': False,
                    'environment': 'production', 'version': 'v1'}).encode(),
                ui.RUNTIME + '/release-manifest.json': json.dumps({'format': 'architex-release-manifest-v1',
                    'version': 'v1', 'files': {'src/a.php': row}}).encode(),
                ui.RUNTIME + '/src/a.php': remote})
            with self.subTest(mode=mode), patch.object(ui, 'read_bytes', side_effect=lambda client, path: bodies[path]):
                result = ui.inspect(Client(), candidate)
                self.assertEqual(result['errorCode'], error)
                self.assertEqual(result['inspectionComplete'], error is None)
                self.assertEqual(result['baselineSourceProfile'], 'windows')
                self.assertFalse(result['writesPerformed'])
                if error:
                    self.assertEqual(result['firstBaselineDifference']['expectedSha256'], ui.digest(expected))

    def test_actual_checkout_respects_attributes_and_binary(self):
        with tempfile.TemporaryDirectory(prefix='a777-profile-test-') as directory:
            root = Path(directory)
            def git(*args):
                subprocess.run(['git', '-C', str(root), *args], check=True, capture_output=True)
            git('init')
            git('config', 'user.email', 'fixture@example.invalid')
            git('config', 'user.name', 'Fixture')
            git('config', 'core.autocrlf', 'false')
            (root / '.gitattributes').write_bytes(b'*.bin -text\nlf.txt text eol=lf\ntext.txt filter=fixture\n')
            original = {'text.txt': b'original\n', 'lf.txt': b'forced\n', 'data.bin': b'\x00binary\n'}
            for name, body in original.items():
                (root / name).write_bytes(body)
            git('add', '.')
            git('commit', '-m', 'fixture')
            baseline = ui.BASELINE
            try:
                ui.BASELINE = subprocess.check_output(['git', '-C', str(root), 'rev-parse', 'HEAD']).decode().strip()
                marker = root / 'executed'
                hooks = root / 'hostile-hooks'
                hooks.mkdir()
                hook = hooks / 'post-checkout'
                hook.write_text('#!/bin/sh\nprintf executed > "' + marker.as_posix() + '"\n')
                hook.chmod(0o755)
                template = root / 'hostile-template'
                (template / 'hooks').mkdir(parents=True)
                (template / 'hooks' / 'post-checkout').write_bytes(hook.read_bytes())
                (template / 'hooks' / 'post-checkout').chmod(0o755)
                config = root / 'hostile-global'
                config.write_text('[core]\n hooksPath = ' + hooks.as_posix() +
                    '\n[init]\n templateDir = ' + template.as_posix() +
                    '\n[filter "fixture"]\n smudge = sh "' + hook.as_posix() + '"\n')
                git('config', 'filter.fixture.clean', 'sh "' + hook.as_posix() + '"')
                git('config', 'core.fsmonitor', 'sh "' + hook.as_posix() + '"')
                (root / 'text.txt').write_bytes(original['text.txt'])
                ui.git(root, 'status', '--porcelain')
                self.assertFalse(marker.exists(), 'repository filter/fsmonitor executed')
                with patch.dict(os.environ, {'GIT_CONFIG_GLOBAL': str(config),
                    'GIT_CONFIG_COUNT': '1', 'GIT_CONFIG_KEY_0': 'core.hooksPath',
                    'GIT_CONFIG_VALUE_0': str(hooks)}):
                    result = ui.windows_baseline_inventory(root, original)
                self.assertFalse(marker.exists(), 'inherited hook/filter executed')
            finally:
                ui.BASELINE = baseline
            expected = dict(original, **{'text.txt': b'original\r\n'})
            for name, body in expected.items():
                self.assertEqual(result[name], {'baselineSha256': ui.digest(body), 'baselineBytes': len(body)})
            self.assertNotEqual(result['text.txt']['baselineSha256'], ui.digest(b'changed\r\n'))
            self.assertNotEqual(result['text.txt']['baselineSha256'], ui.digest(original['text.txt']))


if __name__ == '__main__':
    unittest.main()
