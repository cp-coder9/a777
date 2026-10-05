"""Regression contracts for independent radar review fixes; never deploy."""
import importlib
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch
import urllib.request
import deploy_pages
import generate_radar as radar
from capture_source_evidence import safe_source


class DeploymentSafetyTest(unittest.TestCase):
    def test_import_has_no_actions(self):
        with patch.object(subprocess, 'run') as run, patch.object(urllib.request, 'urlopen') as network, patch.object(Path, 'write_text') as text, patch.object(Path, 'write_bytes') as binary, patch('builtins.open') as opened:
            importlib.reload(deploy_pages)
            for action in [run, network, text, binary, opened]:
                action.assert_not_called()

    def test_missing_and_stale_artifacts_refuse_before_api(self):
        paths = ['docs/techmap/architex-techmap.html', 'docs/techmap/index.html', 'app/public/assets/techmap/index.html', 'docs/techmap/architex-radar.html']
        for invalid in paths:
            for state in ['missing', 'stale']:
                with self.subTest(path=invalid, state=state), tempfile.TemporaryDirectory(dir=os.environ.get('TMPDIR')) as directory:
                    root = Path(directory)
                    for name in paths:
                        if name == invalid and state == 'missing':
                            continue
                        dest = root / name
                        dest.parent.mkdir(parents=True, exist_ok=True)
                        dest.write_text('stale' if name == invalid else 'expected', encoding='utf-8')
                    with patch.object(deploy_pages, 'ROOT', root), patch.object(radar, 'render_document', return_value='expected'), patch.object(deploy_pages, 'api') as api:
                        with self.assertRaises((FileNotFoundError, RuntimeError)):
                            deploy_pages.main()
                        api.assert_not_called()

    def test_optional_lookup_only_suppresses_404(self):
        for status in [401, 403, 404, 500]:
            with self.subTest(status=status), patch.object(subprocess, 'run', return_value=subprocess.CompletedProcess([], 1, '', f'gh: error (HTTP {status})')):
                if status == 404:
                    self.assertIsNone(deploy_pages.api('test', allowed_error=True))
                else:
                    with self.assertRaises(RuntimeError):
                        deploy_pages.api('test', allowed_error=True)

    def test_existing_repository_marker_and_canonical_path_refuse_writes(self):
        import base64
        for full_name, owner, marker in [
            ('other/architex-technology-radar', 'user', '# Architex radar artifact-only publication\n'),
            ('user/architex-technology-radar', 'other', '# Architex radar artifact-only publication\n'),
            ('user/architex-technology-radar', 'user', 'unrelated publication'),
        ]:
            with self.subTest(full_name=full_name, owner=owner, marker=marker):
                responses = [{'login': 'user'}, {'full_name': full_name, 'owner': {'login': owner}},
                             {'content': base64.b64encode(marker.encode()).decode()}]
                with patch.object(deploy_pages, 'preflight', return_value=b'artifact'), patch.object(deploy_pages, 'api', side_effect=responses) as api:
                    with self.assertRaises(RuntimeError):
                        deploy_pages.main()
                    self.assertTrue(all(call.args[1:] == () for call in api.call_args_list), 'No remote writes on ownership refusal')

    def test_preflight_current_outputs(self):
        self.assertEqual(deploy_pages.preflight(), (radar.base.ROOT / 'docs/techmap/architex-radar.html').read_bytes())


class SourceSafetyTest(unittest.TestCase):
    def test_absolute_and_traversal_paths(self):
        for path in ['/app/file.php', 'C:/app/file.php', 'app/../../secret', 'app/.hidden/file.php', 'app\\file.php']:
            with self.subTest(path=path), self.assertRaises(ValueError):
                safe_source(radar.base.ROOT, path)

    def test_resolved_escape_even_without_symlink_privilege(self):
        root = radar.base.ROOT.resolve()
        with patch.object(Path, 'resolve', side_effect=[root, root.parent / 'outside.php']):
            with self.assertRaises(ValueError):
                safe_source(root, 'app/link.php')

    def test_real_symlink_escape(self):
        with tempfile.TemporaryDirectory(dir=os.environ.get('TMPDIR')) as directory:
            base = Path(directory)
            root = base / 'repo'
            (root / 'app').mkdir(parents=True)
            outside = base / 'outside.php'
            outside.write_text('fixture', encoding='utf-8')
            try:
                (root / 'app/link.php').symlink_to(outside)
            except OSError as error:
                if os.name == 'nt':
                    self.skipTest(f'Windows symlink privilege unavailable: {error}')
                raise
            with self.assertRaises(ValueError):
                safe_source(root, 'app/link.php')


    def test_resolved_alias_refuses_hidden_files_and_non_app_tree(self):
        root = radar.base.ROOT.resolve()
        for resolved in [root / '.env', root / '.private/secrets.php', root / 'app/.hidden/secrets.php', root / 'tools/unrelated.py']:
            with self.subTest(resolved=str(resolved)), patch.object(Path, 'resolve', side_effect=[root, resolved]), self.assertRaises(ValueError):
                safe_source(root, 'app/link.php')


class PlacementSafetyTest(unittest.TestCase):
    def test_unexpected_placement(self):
        tools, places, stages, _ = radar.base.load()
        places['unexpected-review-id'] = {'id': 'unexpected-review-id', 'stage': stages[0][0]}
        with self.assertRaisesRegex(ValueError, 'Unexpected route placement'):
            radar.base.validate(tools, places, stages)

    def test_duplicate_placements_and_stages(self):
        registry = json.loads((radar.base.ASSETS / 'tool-registry.json').read_text(encoding='utf-8'))
        for collection in ['tools', 'stages']:
            placement = json.loads((radar.base.ASSETS / 'route-placement.json').read_text(encoding='utf-8'))
            placement[collection].append(placement[collection][0])
            with self.subTest(collection=collection), tempfile.TemporaryDirectory(dir=os.environ.get('TMPDIR')) as directory:
                assets = Path(directory)
                (assets / 'tool-registry.json').write_text(json.dumps(registry), encoding='utf-8')
                (assets / 'route-placement.json').write_text(json.dumps(placement), encoding='utf-8')
                with patch.object(radar.base, 'ASSETS', assets), self.assertRaisesRegex(ValueError, 'Duplicate ids'):
                    radar.base.load()


if __name__ == '__main__':
    unittest.main()
