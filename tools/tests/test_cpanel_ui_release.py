import sys
import unittest
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import cpanel_ui_release as ui


class Contracts(unittest.TestCase):
    def test_exact_producer_shims(self):
        for name in ('index.php', 'app.php', 'legal.php'):
            self.assertEqual(ui.parse_shim(ui.shim(name), name), ui.RUNTIME)
            for bad in (ui.shim(name) + b'<?php evil();', ui.shim(name).replace(b'0077', b'0000'),
                        ui.shim(name).replace(b'/public/', b'/public/../'),
                        ui.shim(name).replace(b'7eee8', b'8eee8'), ui.shim(name).replace(b'require ', b'include ')):
                with self.assertRaises(ui.Refusal): ui.parse_shim(bad, name)
        with self.assertRaises(ui.Refusal): ui.parse_shim(ui.shim('index.php'), 'app.php')

    def test_full_domain_inventory(self):
        row = {'domain': ui.DOMAIN, 'documentroot': ui.DOCROOT, 'homedir': ui.HOME, 'user': 'archite4'}
        data = {'main_domain': dict(row, domain='architex.co.za', documentroot=ui.HOME+'/public_html'),
                'sub_domains': [row], 'addon_domains': [], 'parked_domains': []}
        self.assertEqual(len(ui.domain_roots(data)), 2)
        for root in (ui.RUNTIME, ui.HOME, ui.RUNTIME+'/public', ui.HOME+'/x/../public_html'):
            bad = dict(data, addon_domains=[dict(row, domain='other.test', documentroot=root)])
            with self.assertRaises(ui.Refusal): ui.domain_roots(bad)
        with self.assertRaises(ui.Refusal): ui.domain_roots(dict(data, parked_domains=None))

    def test_manifest_rejects_secret_and_arbitrary_paths(self):
        good = {'format': 'architex-release-manifest-v1', 'version': 'v1', 'files':
                {'src/a.php': {'sha256': 'a'*64, 'bytes': 5}}}
        self.assertEqual(len(ui.manifest(good)), 1)
        for name in ('../config/runtime.php', '/etc/passwd', 'config/runtime.php', 'src/../a', 'src/evil\\name'):
            with self.assertRaises(ui.Refusal): ui.manifest(dict(good, files={name: {'sha256': 'a'*64, 'bytes': 5}}))
        with self.assertRaises(ui.Refusal): ui.manifest(dict(good, files={'src/a.php': {'sha256':'a'*64, 'bytes':True}}))

    def test_partial_failure_never_exports_exception(self):
        class Bad:
            def account_home(self): raise RuntimeError('PASSWORD-secret raw provider body')
        result = ui.inspect(Bad(), {})
        self.assertNotIn('secret', str(result))
        self.assertFalse(result['nativeCanonicalVerified'])
        self.assertFalse(result['deployed'])
        self.assertEqual(result['errorCode'], 'provider_or_contract_failed')

    def test_real_client_masks_provider_failure_and_blocks_mutations(self):
        import json
        from cpanel_client import CpanelConfig, HttpResponse
        class Transport:
            def request(self, method, url, headers, body, timeout):
                return HttpResponse(200, {}, json.dumps({'status':0, 'errors':['PASSWORD-secret']}).encode())
        client = ui.InspectionClient(CpanelConfig(host='cp73.domains.co.za', port=2083,
            username='archite4', password='', account_home=ui.HOME, allowed_root=ui.HOME, api_token='TOKEN-secret'), Transport())
        report = ui.inspect(client, {})
        self.assertNotIn('secret', str(report))
        self.assertFalse(report['inspectionComplete'])
        for operation in (('Fileman', 'save_file_content'), ('Mysql', 'create_database')):
            with self.assertRaises(ui.Refusal): client._uapi(*operation)
        with self.assertRaises(ui.Refusal): client._request_json('POST', '/execute/Fileman/upload_files', body=b'anything')

    def test_direct_transport_mutation_get_refused_before_http(self):
        from unittest.mock import Mock
        from cpanel_client import CpanelConfig, HttpResponse
        transport = Mock()
        transport.request.return_value = HttpResponse(200, {}, b'{"status":1,"data":{}}')
        client = ui.InspectionClient(CpanelConfig(host='example.invalid', port=2083,
            username='archite4', password='', account_home=ui.HOME,
            allowed_root=ui.HOME, api_token='synthetic'), transport)
        with self.assertRaises(ui.Refusal) as refusal:
            client._request_json('GET', '/execute/Fileman/delete_files', {'files': 'victim'})
        self.assertEqual(refusal.exception.code, 'operation_not_read_only')
        transport.request.assert_not_called()

    def test_transport_rejects_ambiguous_paths_methods_bodies_and_params(self):
        from unittest.mock import Mock
        from cpanel_client import CpanelConfig, HttpResponse
        transport = Mock()
        transport.request.return_value = HttpResponse(200, {}, b'{"status":1,"data":{}}')
        client = ui.InspectionClient(CpanelConfig(host='example.invalid', port=2083,
            username='archite4', password='', account_home=ui.HOME,
            allowed_root=ui.HOME, api_token='synthetic'), transport)
        path = '/execute/Fileman/list_files'
        paths = [path + suffix for suffix in ('/', '/extra', '?function=delete_files', '#fragment', '\x00')]
        paths += ['/execute/Fileman%2flist_files', '/execute/Fileman/%6cist_files',
                  '/execute/Fileman%252flist_files', '/execute/Fileman/../Fileman/list_files',
                  '/execute//Fileman/list_files', '/execute/fileman/list_files',
                  '/execute/Fileman\\list_files', '/json-api/cpanel', None, ['bad']]
        cases = [('GET', bad, None, None) for bad in paths]
        cases += [(method, path, None, None) for method in ('POST', 'HEAD', 'DELETE', 'get', 'GET ')]
        cases += [('GET', path, None, body) for body in (b'', b'payload')]
        cases += [('GET', path, params, None) for params in ('function=delete_files',
                   [('function', 'delete_files')], [], 1)]
        for method, target, params, body in cases:
            with self.subTest(method=method, path=target, params=params, body=body):
                with self.assertRaises(ui.Refusal) as refusal:
                    client._request_json(method, target, params, body)
                self.assertEqual(refusal.exception.code, 'operation_not_read_only')
                transport.request.assert_not_called()

    def test_four_approved_operations_reach_transport_with_dict_params(self):
        import json
        from urllib.parse import urlsplit, parse_qs
        from cpanel_client import CpanelConfig, HttpResponse
        calls = []
        class Transport:
            def request(self, method, url, headers, body, timeout):
                calls.append((method, url, body))
                return HttpResponse(200, {}, json.dumps({'result': {
                    'status': 1, 'errors': [], 'data': {'read': True}}}).encode())
        client = ui.InspectionClient(CpanelConfig(host='example.invalid', port=2083,
            username='archite4', password='', account_home=ui.HOME,
            allowed_root=ui.HOME, api_token='synthetic'), Transport())
        operations = [('Variables', 'get_user_information'), ('DomainInfo', 'domains_data'),
                      ('Fileman', 'list_files'), ('Fileman', 'get_file_content')]
        params = {'dir': ui.HOME, 'file': 'a?b&c', 'format': 'hash'}
        for module, function in operations:
            self.assertEqual(client._uapi(module, function, params), {'read': True})
            method, url, body = calls[-1]
            self.assertEqual((method, body), ('GET', None))
            self.assertEqual(urlsplit(url).path, '/execute/' + module + '/' + function)
            self.assertEqual(parse_qs(urlsplit(url).query), {k: [v] for k, v in params.items()})
        for params in (None, {}):
            client._uapi('Variables', 'get_user_information', params)
        self.assertEqual(len(calls), 6)

    def test_reported_links_refused(self):
        class Link:
            def list_files(self, parent):
                return [{'name': '.a777-runtime-private', 'type': 'link'}]
        with self.assertRaises(ui.Refusal):
            ui.read_bytes(Link(), ui.RUNTIME + '/src/a.php')

    def test_complete_inspection_and_corruption(self):
        import json
        from unittest.mock import patch
        body = b'<?php baseline;\n'
        expected = ui.digest(body)
        candidate = {'commit': 'b'*40, 'inventorySha256': 'c'*64,
                     'changes': ['app/public/theme.css', 'app/views/app.php'],
                     'files': {'app/src/a.php': {'sha256': expected, 'bytes': len(body), 'baselineSha256': expected}}}
        row = {'domain': ui.DOMAIN, 'documentroot': ui.DOCROOT, 'homedir': ui.HOME, 'user': 'archite4'}
        class Client:
            def account_home(self): return ui.HOME
            def _uapi(self, module, function, params):
                return {'main_domain': row, 'sub_domains': [], 'addon_domains': [], 'parked_domains': []}
        files = {ui.DOCROOT + '/' + n: ui.shim(n) for n in ('index.php', 'app.php', 'legal.php')}
        files.update({ui.RUNTIME + '/config/runtime.php': b'PRIVATE-CONFIG',
                      ui.RUNTIME + '/src/a.php': body,
                      ui.RUNTIME + '/release.json': json.dumps({'format':'architex-release-v1', 'sourceCommit':ui.BASELINE,
                          'sourceDirty':False, 'environment':'production', 'version':'v1'}).encode(),
                      ui.RUNTIME + '/release-manifest.json': json.dumps({'format':'architex-release-manifest-v1',
                          'version':'v1', 'files':{'src/a.php':{'sha256':expected, 'bytes':len(body)}}}).encode()})
        with patch.object(ui, 'read_bytes', side_effect=lambda c,p: files[p]):
            report = ui.inspect(Client(), candidate)
            self.assertTrue(report['inspectionComplete'])
            self.assertFalse(report['nativeCanonicalVerified'])
            self.assertNotIn('PRIVATE-CONFIG', str(report))
            files[ui.RUNTIME + '/src/a.php'] = b'changed'
            report = ui.inspect(Client(), candidate)
            self.assertEqual(report['errorCode'], 'live_bytes_baseline_mismatch')
            self.assertFalse(report['inspectionComplete'])

    def test_reader_is_scoped_and_reversible(self):
        class Fake:
            def list_files(self, parent):
                target = ui.RUNTIME + '/src/a.php'
                rest = target[len(parent) + 1:]
                return [{'name': rest.split('/')[0], 'type': 'dir' if '/' in rest else 'file'}]
            def _uapi(self, module, function, params):
                self.params = params
                return {'content': '\u0000\u00ff\r\n'}
        c = Fake()
        self.assertEqual(ui.read_bytes(c, ui.RUNTIME+'/src/a.php'), b'\x00\xff\r\n')
        self.assertEqual(c.params['from_charset'], 'ISO-8859-1')
        for path in (ui.HOME+'/other/file', ui.RUNTIME+'/src/../config/runtime.php'):
            with self.assertRaises(ui.Refusal): ui.read_bytes(c, path)


if __name__ == '__main__': unittest.main()
