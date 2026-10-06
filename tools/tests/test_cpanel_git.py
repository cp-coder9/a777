import json
import sys
import unittest
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from cpanel_client import CpanelClient, CpanelConfig, CpanelError, HttpResponse
from cpanel_git import HOME, ROOT, FIELDS, inspect, repository, require_mutation_root

SHA = '2fd4e1c67a2d28fced849ee1bb76e7391b93eb12'

class Transport:
    def __init__(self, payloads):
        self.payloads = iter(payloads)
        self.requests = []
    def request(self, method, url, headers, body, timeout):
        self.requests.append((method, url, headers, body))
        return HttpResponse(200, {}, json.dumps(next(self.payloads)).encode())

def ok(data):
    return {'result': {'status': 1, 'data': data}}

def client(transport):
    return CpanelClient(CpanelConfig('cp73.domains.co.za', 2083, 'archite4', '', HOME, HOME, api_token='TOKEN'), transport)

class GitDiscoveryTests(unittest.TestCase):
    def test_official_shape_and_read_only(self):
        row = {'name': 'a777', 'repository_root': ROOT, 'branch': 'main', 'deployable': 1,
               'last_update': {'identifier': SHA, 'author': 'secret'},
               'last_deployment': {'repository_state': {'identifier': SHA}},
               'source_repository': {'url': 'https://user:TOKEN@private'},
               'clone_urls': {'read_write': ['secret']}, 'private_key': 'secret'}
        t = Transport([ok({'home': HOME}), ok([row]), ok([{'repository_root': ROOT, 'repository_state': {'branch': 'main', 'identifier': SHA}, 'log_path': 'secret'}])])
        report = inspect(client(t))
        self.assertEqual(report['git_capability'], 'retrieve_available')
        self.assertEqual(report['repositories'][0], {'name': 'a777', 'root': ROOT, 'branch': 'main', 'deployable': 1, 'last_update_sha': SHA, 'deploy_sha': SHA})
        self.assertNotIn('secret', json.dumps(report))
        self.assertNotIn('TOKEN', json.dumps(report))
        self.assertTrue(all(r[0] == 'GET' and r[3] is None for r in t.requests))
        self.assertIn('/execute/VersionControl/retrieve?fields=', t.requests[1][1])
        self.assertEqual(t.requests[0][2]['Authorization'], 'cpanel archite4:TOKEN')
        self.assertTrue(t.requests[2][1].endswith('/VersionControlDeployment/retrieve'))

    def test_home_exact_no_git_after_mismatch(self):
        for home in [HOME + '/', '/home/other', HOME + '/../other']:
            t = Transport([ok({'home': home})])
            self.assertEqual(inspect(client(t))['git_capability'], 'unknown')
            self.assertEqual(len(t.requests), 1)

    def test_unknown_roots_discovered_not_authorized(self):
        roots = ['/home/other/a777', HOME + '/repositories/../public_html', None]
        t = Transport([ok({'home': HOME}), ok([{'repository_root': root} for root in roots]), ok([])])
        self.assertEqual(len(inspect(client(t))['repositories']), 3)
        for root in roots + [ROOT + '/child', ROOT + '/../a777']:
            with self.assertRaises(CpanelError):
                require_mutation_root(root)
        self.assertEqual(require_mutation_root(ROOT), ROOT)

    def test_errors_and_invalid_contract_not_exposed(self):
        t = Transport([ok({'home': HOME}), {'result': {'status': 0, 'errors': ['TOKEN private key provider details']}}, ok({})])
        report = inspect(client(t))
        self.assertEqual(report['api_status']['VersionControl/retrieve'], 'failed_or_unavailable')
        self.assertEqual(report['api_status']['VersionControlDeployment/retrieve'], 'invalid_contract')
        self.assertNotIn('TOKEN', json.dumps(report))

    def test_allowlist_scrubs_credential_like_values(self):
        for text in ['TOKEN', 'https://a:b@host', '-----BEGIN PRIVATE KEY-----', 'bad\nline']:
            row = repository({'name': text, 'branch': text, 'repository_root': text, 'last_update': {'identifier': text}}, ('TOKEN',))
            self.assertTrue(all(value is None for value in row.values()))

    def test_empty_inventory_available_not_deploy_authority(self):
        t = Transport([ok({'home': HOME}), ok([]), ok([])])
        report = inspect(client(t))
        self.assertEqual(report['git_capability'], 'retrieve_available')
        self.assertEqual(report['repositories'], [])
        self.assertFalse(report['mutations_enabled'])

if __name__ == '__main__':
    unittest.main()
