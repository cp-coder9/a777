"""Independent source-to-artifact contract for the self-contained capability map."""
from collections import Counter
from html.parser import HTMLParser
import json
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
ASSETS = ROOT / 'app' / 'public' / 'assets'
OUTPUTS = [ROOT / 'docs' / 'techmap' / 'architex-techmap.html',
           ROOT / 'docs' / 'techmap' / 'index.html',
           ASSETS / 'techmap' / 'index.html']


class Document(HTMLParser):
    def __init__(self, source):
        super().__init__(convert_charrefs=True)
        self.elements = []
        self.text = []
        self.feed(source)

    def handle_starttag(self, tag, attrs):
        self.elements.append((tag, dict(attrs)))

    def handle_data(self, data):
        self.text.append(data)

    def with_class(self, name):
        return [attrs for _, attrs in self.elements
                if name in attrs.get('class', '').split()]


class CapabilityMapTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.tools = json.loads((ASSETS / 'tool-registry.json').read_text(encoding='utf-8'))['tools']
        cls.placement = json.loads((ASSETS / 'route-placement.json').read_text(encoding='utf-8'))
        cls.source = OUTPUTS[0].read_text(encoding='utf-8')
        cls.doc = Document(cls.source)

    def test_outputs_are_identical(self):
        for path in OUTPUTS:
            with self.subTest(path=str(path)):
                self.assertEqual(OUTPUTS[0].read_bytes(), path.read_bytes())

    def test_exactly_one_map_node_per_source_tool(self):
        nodes = self.doc.with_class('node')
        expected = Counter(t['id'] for t in self.tools)
        actual = Counter(n.get('data-tool-id') for n in nodes)
        self.assertEqual(expected, actual)

    def test_exactly_one_list_row_per_source_tool(self):
        rows = self.doc.with_class('tool-row')
        self.assertEqual(Counter(t['id'] for t in self.tools),
                         Counter(n.get('data-tool-id') for n in rows))

    def test_full_names_and_source_evidence_retained(self):
        # Decode JSON escapes if full names live in the detail payload.
        text = ''.join(self.doc.text)
        for tool in self.tools:
            with self.subTest(tool=tool['id']):
                self.assertIn(tool['name'], text)
                self.assertIn(tool['evidence'], text)

    def test_concrete_disciplines_and_stages_retained(self):
        text = ''.join(self.doc.text)
        for group in set(t['group'] for t in self.tools):
            self.assertIn(group, text)
        for stage in self.placement['stages']:
            self.assertIn(stage['label'], text)
        self.assertIn('Platform-wide', text)

    def test_control_contract(self):
        ids = {attrs.get('id'): tag for tag, attrs in self.doc.elements if 'id' in attrs}
        for name in ['map-tab', 'list-tab', 'zoom-in', 'zoom-out', 'fit-width', 'fit-all', 'print-map']:
            with self.subTest(control=name):
                self.assertEqual('button', ids.get(name))
        self.assertEqual('input', ids.get('search'))
        self.assertEqual('dialog', ids.get('details'))
        self.assertIn('map-wrap', ids)
        self.assertIn('match-count', ids)

    def test_unique_html_ids(self):
        ids = [attrs['id'] for _, attrs in self.doc.elements if 'id' in attrs]
        self.assertEqual(len(ids), len(set(ids)))

    def test_self_contained_no_external_script_or_stylesheet(self):
        for tag, attrs in self.doc.elements:
            if tag == 'script':
                self.assertNotIn('src', attrs)
            if tag == 'link':
                self.assertNotEqual('stylesheet', attrs.get('rel'))


if __name__ == '__main__':
    unittest.main()
