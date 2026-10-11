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
        row = {'domain': ui.DOMAIN, 'servername': ui.DOMAIN, 'documentroot': ui.DOCROOT, 'homedir': ui.HOME, 'user': 'archite4'}
        data = {'main_domain': dict(row, domain='architex.co.za', servername='architex.co.za', documentroot=ui.HOME+'/public_html'),
                'sub_domains': [row], 'addon_domains': [], 'parked_domains': []}
        self.assertEqual(len(ui.domain_roots(data)), 2)
        for root in (ui.RUNTIME, ui.HOME, ui.RUNTIME+'/public', ui.HOME+'/x/../public_html'):
            bad = dict(data, addon_domains=[dict(row, domain='other.test', documentroot=root)])
            with self.assertRaises(ui.Refusal): ui.domain_roots(bad)
        with self.assertRaises(ui.Refusal): ui.domain_roots(dict(data, parked_domains=None))

    @staticmethod
    def hash_inventory():
        # Synthetic fixture shaped by official format=hash and the existing
        # native-stage servername producer; NOT a captured hosting response.
        return {'main_domain': {'servername': 'architex.co.za',
                    'documentroot': ui.HOME + '/public_html'},
                'sub_domains': {ui.DOMAIN: {'servername': ui.DOMAIN,
                    'documentroot': ui.DOCROOT, 'user': 'archite4'}},
                'addon_domains': {'other.example': {'servername': 'other.example',
                    'documentroot': ui.HOME + '/other'}}, 'parked_domains': {}}

    @classmethod
    def parked_string_inventory(cls):
        # Synthetic literal values, NOT provider payload. Reproduces observed
        # main DICT1 / addon DICT1 / sub DICT4 / parked other1, top keys4.
        data = cls.hash_inventory()
        data['main_domain']['serveralias'] = 'parked.example www.parked.example'
        data['parked_domains'] = ['parked.example']
        for index in range(3):
            name = f'sub{index}.example'
            data['sub_domains'][name] = {'servername': name,
                'documentroot': ui.HOME + f'/public_html/sub{index}'}
        return data

    def test_documented_parked_string_main_binding(self):
        import json
        data = self.parked_string_inventory()
        metadata = {}
        roots = ui.domain_roots(data, authenticated_home=ui.HOME, metadata=metadata)
        self.assertEqual(roots, sorted([ui.HOME + '/public_html', ui.DOCROOT,
            ui.HOME + '/other'] + [ui.HOME + f'/public_html/sub{i}' for i in range(3)]))
        self.assertEqual(metadata, {'rowCount': 6, 'missingUserCount': 5,
            'missingHomeCount': 6, 'parkedAliasCount': 1, 'mainBoundParkedAliasCount': 1})
        self.assertNotIn('parked.example', json.dumps(metadata))
        self.assertEqual(ui.domain_shape(data)['groupRowTypeCounts']['parked_domains'],
                         {'dict': 0, 'list': 0, 'other': 1})

    def test_parked_binding_rejects_wildcard_main_claim(self):
        data = self.parked_string_inventory()
        data['main_domain']['serveralias'] += ' parked.*'
        with self.assertRaises(ui.Refusal):
            ui.domain_roots(data, authenticated_home=ui.HOME)

    def test_parked_strings_fail_closed_before_file_reads(self):
        from unittest.mock import patch
        cases = []
        for value in ('unmatched.example', '', 'bad..example', '*.example',
                      'parked.example ', 'https://parked.example', '-bad.example',
                      'bad_.example', 'x' * 64 + '.example', ui.DOMAIN,
                      'architex.co.za', 'other.example', 'sub0.example'):
            bad = self.parked_string_inventory()
            bad['parked_domains'] = [value]
            cases.append(bad)
        bad = self.parked_string_inventory()
        bad['parked_domains'] = ['parked.example'] * 20001
        cases.append(bad)
        bad = self.parked_string_inventory()
        bad['parked_domains'] *= 2
        cases.append(bad)
        bad = self.parked_string_inventory()
        bad['parked_domains'] = ['parked.example', 'PARKED.EXAMPLE']
        cases.append(bad)
        for field, value in (('documentroot', None), ('documentroot', '/home/foreign/site'),
                ('documentroot', ui.RUNTIME), ('homedir', '/home/foreign'), ('user', 'foreign'),
                ('serveralias', '*.example'), ('serveralias', ''), ('servername', None)):
            bad = self.parked_string_inventory()
            bad['main_domain'][field] = value
            cases.append(bad)
        bad = self.parked_string_inventory()
        bad['main_domain']['domain'] = bad['main_domain'].pop('servername')
        cases.append(bad)
        for group in ('sub_domains', 'addon_domains'):
            bad = self.parked_string_inventory()
            next(iter(bad[group].values()))['serveralias'] = 'parked.example'
            cases.append(bad)
            bad = self.parked_string_inventory()
            next(iter(bad[group].values()))['documentroot'] = ui.RUNTIME
            cases.append(bad)
        bad = self.parked_string_inventory()
        bad['parked_domains'] = {'parked.example': 'parked.example'}
        cases.append(bad)  # Only the documented string array is authorized.
        for index, data in enumerate(cases):
            class Client:
                def account_home(self): return ui.HOME
                def _uapi(self, *args): return data
            with self.subTest(index=index), patch.object(ui, 'read_bytes') as reader:
                report = ui.inspect(Client(), {})
                self.assertIsNotNone(report['errorCode'])
                self.assertFalse(report['inspectionComplete'])
                self.assertFalse(report['nativeCanonicalVerified'])
                self.assertFalse(report['nativeOwnershipVerified'])
                reader.assert_not_called()

    def test_parked_binding_normalizes_dns_case_without_double_claim(self):
        data = self.parked_string_inventory()
        data['parked_domains'] = ['PARKED.EXAMPLE']
        data['main_domain']['serveralias'] = 'PARKED.EXAMPLE www.parked.example'
        self.assertEqual(len(ui.domain_roots(data, authenticated_home=ui.HOME)), 6)

    def test_parked_dict_rows_keep_independent_root_validation(self):
        data = self.hash_inventory()
        data['parked_domains'] = {'parked.example': {'servername': 'parked.example',
            'documentroot': ui.HOME + '/public_html/parked'}}
        self.assertIn(ui.HOME + '/public_html/parked',
                      ui.domain_roots(data, authenticated_home=ui.HOME))
        data['parked_domains']['parked.example']['documentroot'] = ui.RUNTIME
        with self.assertRaises(ui.Refusal):
            ui.domain_roots(data, authenticated_home=ui.HOME)

    def test_parked_binding_reaches_first_shim_read_only(self):
        from unittest.mock import ANY, patch
        data = self.parked_string_inventory()
        class Client:
            def account_home(self): return ui.HOME
            def _uapi(self, *args): return data
        with patch.object(ui, 'read_bytes', side_effect=ui.Refusal('stop_at_first_shim')) as reader:
            report = ui.inspect(Client(), {})
        self.assertEqual(report['errorCode'], 'stop_at_first_shim')
        reader.assert_called_once_with(ANY, ui.DOCROOT + '/index.php')
        self.assertEqual(report['domainAccountMetadata']['mainBoundParkedAliasCount'], 1)
        self.assertFalse(report['nativeCanonicalVerified'])
        self.assertFalse(report['nativeOwnershipVerified'])

    def test_documented_hash_retains_every_root_and_unknown_metadata(self):
        data = self.hash_inventory()
        metadata = {}
        self.assertEqual(ui.domain_roots(data, authenticated_home=ui.HOME, metadata=metadata),
                         sorted([ui.HOME+'/public_html', ui.DOCROOT, ui.HOME+'/other']))
        self.assertEqual(metadata, {'rowCount': 3, 'missingUserCount': 2, 'missingHomeCount': 3})
        with self.assertRaises(ui.Refusal): ui.domain_roots(data)
        with self.assertRaises(ui.Refusal): ui.domain_roots(data, authenticated_home='/home/other')

    @classmethod
    def documented_addon_inventory(cls):
        # Official domains_data example: addon domain is NOT its servername.
        # Account/root adapted locally; synthetic, not a hosting response.
        data = cls.hash_inventory()
        data['addon_domains'] = {'exampledomain.com': {
            'domain': 'exampledomain.com', 'servername': 'subdomain.example.com',
            'serveralias': 'exampledomain.com www.exampledomain.com www.subdomain.example.com',
            'documentroot': ui.HOME + '/public_html/subdomain', 'type': 'addon_domain'}}
        return data

    def test_documented_addon_identity_retains_root_and_metadata(self):
        for as_list in (False, True):
            data = self.documented_addon_inventory()
            with self.subTest(as_list=as_list):
                if as_list:
                    data['addon_domains'] = list(data['addon_domains'].values())
                metadata = {}
                self.assertEqual(ui.domain_roots(data, authenticated_home=ui.HOME, metadata=metadata),
                    sorted([ui.HOME + '/public_html', ui.DOCROOT, ui.HOME + '/public_html/subdomain']))
                self.assertEqual(metadata, {'rowCount': 3, 'missingUserCount': 2, 'missingHomeCount': 3})

    def test_target_requires_explicit_consistent_canonical_identity(self):
        from copy import deepcopy
        cases = []
        bad = self.hash_inventory()
        del bad['sub_domains'][ui.DOMAIN]['servername']
        bad['sub_domains'][ui.DOMAIN]['domain'] = ui.DOMAIN
        cases.append(bad)
        bad = self.hash_inventory()
        target = bad['sub_domains'].pop(ui.DOMAIN)
        bad['addon_domains'] = {'foreign.example': dict(target, domain='foreign.example')}
        cases.append(bad)
        bad = self.hash_inventory()
        bad['parked_domains'] = bad['sub_domains']; bad['sub_domains'] = {}
        cases.append(bad)
        for bad in cases:
            with self.subTest(case=deepcopy(bad)), self.assertRaises(ui.Refusal):
                ui.domain_roots(bad, authenticated_home=ui.HOME)

    def test_addon_aliases_cannot_claim_other_rows(self):
        from copy import deepcopy
        base = self.documented_addon_inventory()
        cases = []
        for alias in ('architex.co.za', 'ARCHITEX.CO.ZA', '*.co.za'):
            bad = deepcopy(base)
            bad['addon_domains']['exampledomain.com']['serveralias'] = alias
            cases.append(bad)
        bad = deepcopy(base)
        bad['main_domain']['serveralias'] = 'exampledomain.com'
        cases.append(bad)
        bad = deepcopy(base)
        bad['main_domain']['serveralias'] = 'shared.example'
        bad['addon_domains']['exampledomain.com']['serveralias'] = 'shared.example'
        cases.append(bad)
        for bad in cases:
            with self.subTest(case=bad), self.assertRaises(ui.Refusal):
                ui.domain_roots(bad, authenticated_home=ui.HOME)

    def test_documented_addon_fail_closed_and_all_roots_checked(self):
        from copy import deepcopy
        base = self.documented_addon_inventory()
        mutations = [('domain', 'bad..example'), ('domain', None), ('domain', '*.example'),
            ('servername', 'bad..example'), ('servername', 'EXAMPLE.com'), ('servername', None),
            ('documentroot', '/home/foreign/public_html'), ('documentroot', ui.HOME),
            ('documentroot', ui.RUNTIME), ('documentroot', ui.RUNTIME + '/public'),
            ('documentroot', ui.HOME + '/.a777-runtime-private'),
            ('homedir', '/home/foreign'), ('user', 'foreign'),
            ('domain', ui.DOMAIN), ('servername', ui.DOMAIN),
            ('domain', 'architex.co.za'), ('servername', 'architex.co.za')]
        for field, value in mutations:
            bad = deepcopy(base)
            row = bad['addon_domains']['exampledomain.com']
            row[field] = value
            # Identity mutations use the matching map key to exercise the
            # actual collision/hostname contract, not merely a key mismatch.
            if field == 'domain' and isinstance(value, str):
                bad['addon_domains'] = {value: row}
            with self.subTest(field=field, value=value), self.assertRaises(ui.Refusal):
                ui.domain_roots(bad, authenticated_home=ui.HOME)
        for key in ('subdomain.example.com', 'wrong.example', 7, None):
            bad = deepcopy(base)
            row = bad['addon_domains'].pop('exampledomain.com')
            bad['addon_domains'][key] = row
            with self.subTest(key=key), self.assertRaises(ui.Refusal):
                ui.domain_roots(bad, authenticated_home=ui.HOME)
        for group in ('main_domain', 'sub_domains'):
            bad = deepcopy(base)
            row = bad['addon_domains'].pop('exampledomain.com')
            if group == 'main_domain':
                bad[group] = row
            else:
                bad[group]['exampledomain.com'] = row
            with self.subTest(group=group), self.assertRaises(ui.Refusal):
                ui.domain_roots(bad, authenticated_home=ui.HOME)
        with self.assertRaises(ui.Refusal):
            ui.domain_roots(base)
        with self.assertRaises(ui.Refusal):
            ui.domain_roots(base, authenticated_home='/home/foreign')

    def test_hash_inventory_fail_closed(self):
        from copy import deepcopy
        base = self.hash_inventory()
        cases = []
        for group in ('main_domain', 'sub_domains', 'addon_domains', 'parked_domains'):
            bad = deepcopy(base); del bad[group]; cases.append(bad)
            bad = deepcopy(base); bad[group] = None; cases.append(bad)
        for field, value in [('user', 'other'), ('homedir', '/home/other'),
                             ('user', None), ('homedir', None),
                             ('domain', 'conflicting.example'), ('servername', 'wrong.example'),
                             ('documentroot', '/home/other/public_html'),
                             ('documentroot', ui.RUNTIME), ('documentroot', ui.RUNTIME+'/public'),
                             ('documentroot', ui.HOME), ('documentroot', ui.HOME+'/a/../b'),
                             ('serveralias', ui.DOMAIN),
                             ('serveralias', ui.DOMAIN.upper()),
                             ('serveralias', '*.architex.co.za')]:
            bad = deepcopy(base); bad['addon_domains']['other.example'][field] = value; cases.append(bad)
        for value in ('opaque', [base['main_domain']], {'nested': base['main_domain']}, {}, 7):
            bad = deepcopy(base); bad['addon_domains']['other.example'] = value; cases.append(bad)
        bad = deepcopy(base); bad['unknown_domains'] = {}; cases.append(bad)
        bad = deepcopy(base); bad['parked_domains'] = [dict(base['sub_domains'][ui.DOMAIN])]; cases.append(bad)
        for bad in cases:
            with self.subTest(case=bad), self.assertRaises(ui.Refusal):
                ui.domain_roots(bad, authenticated_home=ui.HOME)

    def test_hash_inventory_bounds_and_list_compatibility(self):
        data = self.hash_inventory()
        data['sub_domains'] = list(data['sub_domains'].values())
        data['addon_domains'] = list(data['addon_domains'].values())
        self.assertEqual(len(ui.domain_roots(data, authenticated_home=ui.HOME)), 3)
        for group, value in [('sub_domains', [data['sub_domains'][0]] * 20001),
                             ('main_domain', {}), ('addon_domains', []),
                             ('parked_domains', {'other.example': {
                                 'servername': 'other.example',
                                 'documentroot': ui.HOME+'/.a777-runtime-private'}})]:
            bad = self.hash_inventory()
            bad[group] = value
            if group == 'addon_domains':
                bad['sub_domains'] = []  # exact target cannot disappear
            with self.subTest(group=group), self.assertRaises(ui.Refusal):
                ui.domain_roots(bad, authenticated_home=ui.HOME)

    def test_inspection_hash_account_binding_before_file_reads(self):
        from unittest.mock import patch
        data = self.hash_inventory()
        class Client:
            def account_home(self): return ui.HOME
            def _uapi(self, *args): return data
        with patch.object(ui, 'read_bytes', side_effect=ui.Refusal('stop_after_inventory')) as reader:
            report = ui.inspect(Client(), {})
            self.assertEqual(report['errorCode'], 'stop_after_inventory')
            self.assertEqual(report['domainAccountMetadata']['missingHomeCount'], 3)
            self.assertEqual(reader.call_count, 1)
            data['addon_domains']['other.example']['user'] = 'foreign'
            reader.reset_mock()
            report = ui.inspect(Client(), {})
            self.assertEqual(report['errorCode'], 'domain_account_unknown')
            reader.assert_not_called()

    def test_domain_shape_is_literal_schema_not_provider_values(self):
        import json
        secret = 'TOKEN-PASSWORD-domain.private-config-provider-error'
        data = {'main_domain': {key: secret for key in
                    ('servername', 'domain', 'documentroot', 'user', 'homedir')},
                'sub_domains': {secret: {secret: [secret]}},
                'addon_domains': None, secret: {'domains': [secret]}}
        shape = ui.domain_shape(data)
        self.assertEqual(set(shape), {'topLevelType', 'groups', 'mainFieldPresence',
                                     'maxDepth', 'typeCounts', 'traversalBounded',
                                     'topLevelKeyCount', 'topLevelKeyCountBounded',
                                     'requiredGroupsCount', 'additionalTopLevelTypeCounts',
                                     'groupRowTypeCounts'})
        self.assertEqual(set(shape['groups']),
                         {'main_domain', 'sub_domains', 'addon_domains', 'parked_domains'})
        self.assertEqual(set(shape['mainFieldPresence']),
                         {'servername', 'domain', 'documentroot', 'user', 'homedir'})
        self.assertEqual(shape['groups']['addon_domains'],
                         {'present': True, 'type': 'other', 'count': 0, 'countBounded': False})
        self.assertFalse(shape['groups']['parked_domains']['present'])
        self.assertEqual(shape['maxDepth'], 4)
        self.assertNotIn(secret, json.dumps(shape))
        def check(value):
            if type(value) is dict:
                for child in value.values(): check(child)
            else:
                self.assertTrue(type(value) in (int, bool) or
                                type(value) is str and value in ('dict', 'list', 'other'))
        check(shape)
        for value, expected in (([], 'list'), (None, 'other'), (secret, 'other'), (7, 'other')):
            self.assertEqual(ui.domain_shape(value)['topLevelType'], expected)

    def test_domain_shape_predicate_counts_never_export_extra_keys(self):
        import json
        secret = 'SECRET-provider-key-value'
        data = self.hash_inventory()
        data[secret] = [secret]
        data['parked_domains'] = [secret, {}, []]
        shape = ui.domain_shape(data)
        self.assertEqual(shape['topLevelKeyCount'], 5)
        self.assertEqual(shape['requiredGroupsCount'], 4)
        self.assertEqual(shape['additionalTopLevelTypeCounts'], {'dict': 0, 'list': 1, 'other': 0})
        self.assertEqual(shape['groupRowTypeCounts']['parked_domains'], {'dict': 1, 'list': 1, 'other': 1})
        self.assertEqual(shape['groupRowTypeCounts']['main_domain'], {'dict': 1, 'list': 0, 'other': 0})
        self.assertNotIn(secret, json.dumps(shape))
        data['parked_domains'] = [secret] * 20001
        shape = ui.domain_shape(data)
        self.assertEqual(shape['groupRowTypeCounts']['parked_domains']['other'], 20000)
        self.assertTrue(shape['groups']['parked_domains']['countBounded'])

    def test_domain_shape_bounded_depth_counts_and_cycles(self):
        shape = ui.domain_shape({'sub_domains': [None] * 20001})
        self.assertEqual(shape['groups']['sub_domains']['count'], 20000)
        self.assertTrue(shape['groups']['sub_domains']['countBounded'])
        self.assertEqual(sum(shape['typeCounts'].values()), 20000)
        self.assertTrue(shape['traversalBounded'])
        cycle = {}; cycle['secret-key'] = cycle
        shape = ui.domain_shape(cycle)
        self.assertEqual(shape['maxDepth'], 8)
        self.assertTrue(shape['traversalBounded'])
        self.assertEqual(shape['typeCounts']['dict'], 9)

    def test_inventory_refusal_pins_first_fixed_predicate_before_files(self):
        from unittest.mock import patch
        from copy import deepcopy
        base = self.hash_inventory()
        cases = [(None, 'domain_inventory_keys'),
                 (dict(base, opaque_metadata=[]), 'domain_inventory_keys'),
                 (dict(base, main_domain=[]), 'domain_inventory_main'),
                 (dict(base, sub_domains=None), 'domain_inventory_group'),
                 (dict(base, parked_domains=[7]), 'domain_inventory_row'),
                 (dict(base, sub_domains=[{}] * 20000, addon_domains=[{}]), 'domain_inventory_total')]
        for data, predicate in cases:
            class Client:
                def account_home(self): return ui.HOME
                def _uapi(self, *args): return data
            with self.subTest(predicate=predicate), patch.object(ui, 'read_bytes') as reader:
                report = ui.inspect(Client(), {})
                self.assertEqual(report['errorCode'], 'domain_inventory_unknown')
                self.assertEqual(report.get('domainInventoryPredicate'), predicate)
                self.assertFalse(report['inspectionComplete'])
                reader.assert_not_called()
        # A row-type fault must not preempt an earlier semantic row refusal.
        data = deepcopy(base)
        data['main_domain']['servername'] = None
        data['parked_domains'] = ['private-alias']
        class SemanticClient:
            def account_home(self): return ui.HOME
            def _uapi(self, *args): return data
        report = ui.inspect(SemanticClient(), {})
        self.assertEqual(report['errorCode'], 'domain_identity_unknown')
        self.assertNotIn('domainInventoryPredicate', report)

    def test_failed_inventory_keeps_shape_and_old_refusal_before_files(self):
        import json
        from unittest.mock import patch
        calls = []
        secret = 'PRIVATE-TOKEN-domain-config-error'
        data = {'domains': {secret: {'main_domain': {'servername': secret}}}}
        class Client:
            def account_home(self): return ui.HOME
            def _uapi(self, *args):
                calls.append(args)
                return data
        with patch.object(ui, 'read_bytes') as reader:
            report = ui.inspect(Client(), {})
            reader.assert_not_called()
        self.assertEqual(calls, [('DomainInfo', 'domains_data',
                                 {'format': 'hash', 'hide_temporary_domains': '0'})])
        self.assertEqual(report['domainShape'], ui.domain_shape(data))
        self.assertEqual(report['errorCode'], 'domain_inventory_unknown')
        self.assertFalse(report['inspectionComplete'])
        self.assertFalse(report['writesPerformed'])
        self.assertNotIn(secret, json.dumps(report))
        class Failed(Client):
            def _uapi(self, *args): raise RuntimeError(secret)
        report = ui.inspect(Failed(), {})
        self.assertEqual(report['errorCode'], 'provider_or_contract_failed')
        self.assertNotIn('domainShape', report)
        self.assertNotIn(secret, json.dumps(report))

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
        row = {'domain': ui.DOMAIN, 'servername': ui.DOMAIN, 'documentroot': ui.DOCROOT, 'homedir': ui.HOME, 'user': 'archite4'}
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
