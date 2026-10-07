"""Exact synthetic API2 protocol fixtures; no host or credential access."""
import copy
import json
from pathlib import Path
import sys
import unittest
import urllib.parse
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import cpanel_docroot_refresh as d
from cpanel_client import CpanelConfig, HttpResponse
from test_cpanel_php_refresh import public


class Transport:
    def __init__(self):
        self.calls = []
        self.fail = None
        self.reads = 0
        self.drift = False
        self.classification = 'sub_domains'
        self.domain = {'domain': d.DOMAIN, 'documentroot': d.DOCROOT,
                       'user': 'archite4', 'homedir': d.HOME, 'ip': '192.0.2.1'}

    def request(self, method, url, headers, body, timeout):
        path = urllib.parse.urlsplit(url).path
        fields = urllib.parse.parse_qs(body.decode()) if body else {}
        function = fields.get('cpanel_jsonapi_func', [path.rsplit('/', 1)[-1]])[0]
        self.calls.append((method, url, fields))
        if path == '/json-api/cpanel':
            assert method == 'POST' and not urllib.parse.urlsplit(url).query
            assert fields['cpanel_jsonapi_module'] == ['SubDomain']
            assert fields['cpanel_jsonapi_user'] == ['archite4']
            assert fields['cpanel_jsonapi_apiversion'] == ['2']
            if function == 'listsubdomains':
                rows = [{'domain': d.DOMAIN, 'rootdomain': d.PARENT, 'subdomain': 'php',
                         'dir': d.DOCROOT, 'basedir': d.RELATIVE, 'reldir': 'home:' + d.RELATIVE}]
            elif function == 'changedocroot':
                assert fields['subdomain'] == ['php']
                assert fields['rootdomain'] == [d.PARENT]
                assert fields['dir'] == [d.RELATIVE]
                rows = [{'reason': '', 'result': 1, 'dir': d.DOCROOT, 'reldir': d.RELATIVE}]
            else:
                raise AssertionError(function)
            envelope = {'cpanelresult': {'module': 'SubDomain', 'func': function,
                        'apiversion': 2, 'event': {'result': 1}, 'data': rows}}
            if self.fail == function:
                envelope['cpanelresult']['event']['result'] = 0
                envelope['cpanelresult']['error'] = 'secret credential path https://private'
        else:
            if function == 'get_user_information':
                data = {'home': d.HOME}
            elif function == 'domains_data':
                self.reads += 1
                row = copy.deepcopy(self.domain)
                if self.drift and self.reads > 1:
                    row['ip'] = '192.0.2.2'
                data = {'main_domain': {'domain': d.PARENT}, 'sub_domains': [],
                        'addon_domains': [], 'parked_domains': []}
                data[self.classification] = [row]
            else:
                raise AssertionError(function)
            envelope = {'result': {'status': 0 if self.fail == function else 1,
                                   'errors': None, 'data': data}}
        return HttpResponse(200, {}, json.dumps(envelope).encode())


class Tests(unittest.TestCase):
    def run_fixture(self, transport, get=public):
        config = CpanelConfig('cp73.domains.co.za', 2083, 'archite4', '', d.HOME,
                              d.HOME, api_token='synthetic-only')
        return d.refresh(d.DocrootClient(config, transport), get)

    def test_exact_protocol_success_and_two_readbacks(self):
        t = Transport()
        r = self.run_fixture(t)
        self.assertTrue(r['setterPerformed'])
        self.assertTrue(r['apiReadbackVerified'])
        self.assertTrue(r['publicRestored'])
        self.assertTrue(r['deployed'])
        self.assertEqual(3, t.reads)
        self.assertEqual(1, sum(c[2].get('cpanel_jsonapi_func') == ['changedocroot'] for c in t.calls))

    def test_getter_refusal_stops_before_write(self):
        for function in ('domains_data', 'listsubdomains', 'get_user_information'):
            t = Transport(); t.fail = function
            r = self.run_fixture(t)
            self.assertFalse(r['setterAttempted'])
            self.assertFalse(any(c[2].get('cpanel_jsonapi_func') == ['changedocroot'] for c in t.calls))

    def test_addon_not_accepted_as_subdomain(self):
        t = Transport(); t.classification = 'addon_domains'
        self.assertFalse(self.run_fixture(t)['setterAttempted'])

    def test_wrong_literal_identity_refused(self):
        for field in ('documentroot', 'domain', 'user', 'homedir', 'ip'):
            t = Transport(); t.domain[field] = 'wrong'
            self.assertFalse(self.run_fixture(t)['setterAttempted'])

    def test_setter_failure_redacted(self):
        t = Transport(); t.fail = 'changedocroot'
        r = self.run_fixture(t)
        self.assertTrue(r['setterAttempted'])
        self.assertFalse(r['setterPerformed'])
        self.assertEqual(0, r['providerStatus'])
        self.assertNotIn('credential', json.dumps(r))
        self.assertNotIn('https:', json.dumps(r))

    def test_ip_drift_refuses_readback(self):
        t = Transport(); t.drift = True
        r = self.run_fixture(t)
        self.assertTrue(r['setterPerformed'])
        self.assertFalse(r['apiReadbackVerified'])
        self.assertFalse(r['publicRestored'])

    def test_waf_html_and_old_lease_not_restored(self):
        for status in (200, 503, 504):
            r = self.run_fixture(Transport(), lambda path: (status, b'<html>One moment old lease</html>'))
            self.assertTrue(r['apiReadbackVerified'])
            self.assertFalse(r['publicRestored'])
            self.assertFalse(r['deployed'])

    def test_unchanged_api2_but_wrong_build_fails(self):
        def get(path):
            if path == '/build-info.json':
                return 200, b'{"sourceCommit":"wrong","sourceDirty":false,"environment":"production"}'
            return public(path)
        self.assertFalse(self.run_fixture(Transport(), get)['publicRestored'])


if __name__ == '__main__':
    unittest.main()
