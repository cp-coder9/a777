"""Complete radar geometry and source coverage contracts."""
import itertools
import json
import math
import os
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch
import generate_radar as radar
import radar_coverage
from capture_source_evidence import safe_source


class RadarGeometryTest(unittest.TestCase):
    def test_all_blips_are_separate(self):
        tools, _, _, _ = radar.base.load()
        radar.radar_svg(tools)
        self.assertEqual(set(tools), set(radar.NODE_COORDS))
        for (id1, p1), (id2, p2) in itertools.combinations(radar.NODE_COORDS.items(), 2):
            with self.subTest(pair=(id1, id2)):
                self.assertGreaterEqual(math.dist(p1, p2), 26, '13px blips must not overlap')


class RadarCoverageTest(unittest.TestCase):
    def test_captured_evidence_supports_checkout_without_pending_backend(self):
        tools, _, _, _ = radar.base.load()
        with tempfile.TemporaryDirectory(dir=os.environ.get('TMPDIR')) as directory:
            root = Path(directory)
            target = root / 'tools/techmap'
            target.mkdir(parents=True)
            for filename in ['coverage.json', 'source-evidence.json']:
                (target / filename).write_bytes((radar.base.ROOT / 'tools/techmap' / filename).read_bytes())
            coverage = radar_coverage.load_coverage(root, tools)
            self.assertEqual(len(tools), len(coverage['tools']))
            with self.assertRaises(ValueError):
                radar_coverage.load_coverage(root, tools, strict_sources=True)

    def test_source_paths_refuse_traversal_and_credentials(self):
        for invalid in ['../outside.py', 'E:/secret', 'app/.env', '.private/source.php', 'app/../file.php', 'app\\secret.php']:
            with self.subTest(path=invalid), self.assertRaises(ValueError):
                safe_source(radar.base.ROOT, invalid)

    def test_registry_duplicates_are_not_silently_overwritten(self):
        registry = json.loads((radar.base.ASSETS / 'tool-registry.json').read_text(encoding='utf-8'))
        registry['tools'].append(registry['tools'][0])
        placement = json.loads((radar.base.ASSETS / 'route-placement.json').read_text(encoding='utf-8'))
        with tempfile.TemporaryDirectory(dir=os.environ.get('TMPDIR')) as directory:
            assets = Path(directory)
            (assets / 'tool-registry.json').write_text(json.dumps(registry), encoding='utf-8')
            (assets / 'route-placement.json').write_text(json.dumps(placement), encoding='utf-8')
            with patch.object(radar.base, 'ASSETS', assets), self.assertRaises(ValueError):
                radar.base.load()

    def test_complete_source_manifest(self):
        tools, _, _, _ = radar.base.load()
        coverage = radar_coverage.load_coverage(radar.base.ROOT, tools)
        self.assertTrue(coverage['relationships'])
        self.assertTrue(coverage['groups'])
        for entry in coverage['tools']:
            self.assertTrue(entry['replacement']['text'])

    def test_all_information_layers_present(self):
        doc = radar.render_document()
        tools, _, _, _ = radar.base.load()
        self.assertEqual(len(tools), doc.count('class="coverage-card"'))
        for marker in ['coverage-tab', 'coverage-workflow', 'coverage-connections', 'coverage-groups',
                       'relationship-layer', 'connections-toggle', 'Usage note', 'Replacement evidence']:
            self.assertIn(marker, doc)
        self.assertIn('not automatic data exchange', doc)


if __name__ == '__main__':
    unittest.main()
