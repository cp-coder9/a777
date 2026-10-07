"""Synthetic protocol fixtures only: no hosting credentials or network calls."""
import copy
import json
from pathlib import Path
import sys
import unittest
import urllib.parse
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from cpanel_client import CpanelConfig, HttpResponse
import cpanel_php_refresh as p


class Transport:
    def __init__(self):
        self.calls = []
        self.row = {'vhost': p.DOMAIN, 'account': 'archite4', 'homedir': p.HOME,
                    'documentroot': p.DOCROOT, 'version': 'ea-php83'}
        self.domain = {'domain': p.DOMAIN, 'user': 'archite4', 'homedir': p.HOME,
                       'documentroot': p.DOCROOT}
        self.setter = {'vhosts': [p.DOMAIN]}
        self.setter_fail = False
        self.after_version = None
        self.after_docroot = None
        self.envelope = 'wrapped'
        self.bad_errors = False
        self.version_reads = 0

    def request(self, method, url, headers, body, timeout):
        self.calls.append((method, url, body, headers))
        function = urllib.parse.urlsplit(url).path.rsplit('/', 1)[-1]
        if function == 'get_user_information':
            data = {'home': p.HOME}
        elif function == 'php_get_vhost_versions':
            self.version_reads += 1
            data = copy.deepcopy(self.row)
            if self.version_reads > 1 and self.after_version:
                data['version'] = self.after_version
        elif function == 'single_domain_data':
            data = copy.deepcopy(self.domain)
            if self.version_reads > 1 and self.after_docroot:
                data['documentroot'] = self.after_docroot
        elif function == 'php_set_vhost_versions':
            data = self.setter
        else:
            raise AssertionError('Unexpected operation')
        result = {'status': 1, 'errors': None, 'data': data}
        if self.setter_fail and function == 'php_set_vhost_versions':
            result = {'status': 0, 'errors': ['DO NOT LOG provider secret'], 'error_code': 417}
        if self.bad_errors:
            result['errors'] = 'invalid'
        payload = {'result': result} if self.envelope == 'wrapped' else result
        if self.envelope == 'mixed':
            payload = {'result': result, 'status': 1}
        return HttpResponse(200, {}, json.dumps(payload).encode())

    def writes(self):
        return [c for c in self.calls if 'php_set_vhost_versions' in c[1]]


def public(path):
    if path == '/build-info.json':
        return 200, json.dumps({'sourceCommit': p.RELEASE, 'sourceDirty': False, 'environment': 'production'}).encode()
    if path == '/api/v1/readiness':
        return 200, json.dumps({'data': {'status': 'ready', 'components': {
            k: {'status': 'ready'} for k in ('database', 'migrations', 'jobs', 'mail')}}}).encode()
    if path == '/api/v1/health':
        return 200, b'{"data":{"status":"ok"}}'
    if path == '/':
        return 401, b'<title>Sign in required</title><a href="/app">Open sign in</a>'
    if path == '/app':
        return 200, b'<title>Architex OS</title><section id="publicAccess"><script src="/assets/js/public-access.js">'
    raise AssertionError('Unexpected public path')


class RefreshTests(unittest.TestCase):
    def setUp(self):
        self.transport = Transport()
        self.client = p.RefreshClient(CpanelConfig('cp73.domains.co.za', 2083, 'archite4', '',
                                    p.HOME, p.HOME, api_token='fixture-token-not-real'), self.transport)

    def run_refresh(self, get=public):
        return p.refresh(self.client, get)

    def test_exact_same_version_and_target_form_post(self):
        result = self.run_refresh()
        self.assertTrue(result['deployed'])
        self.assertTrue(result['refreshPerformed'])
        calls = self.transport.writes()
        self.assertEqual(1, len(calls))
        method, url, body, headers = calls[0]
        self.assertEqual('POST', method)
        self.assertEqual('', urllib.parse.urlsplit(url).query)
        self.assertEqual({'vhost': [p.DOMAIN], 'version': ['ea-php83']}, urllib.parse.parse_qs(body.decode()))
        self.assertEqual('application/x-www-form-urlencoded', headers['Content-Type'])
        self.assertEqual(2, self.transport.version_reads)

    def test_alt_php83_literal_preserved(self):
        self.transport.row['version'] = 'alt-php83'
        self.assertTrue(self.run_refresh()['deployed'])
        self.assertIn(b'version=alt-php83', self.transport.writes()[0][2])

    def test_version_tokens_rejected_not_repaired(self):
        for value in ('ea-php82', 'ea-php84', 'inherit', ' EA-php83', 'ea-php83\n', 'custom-php83', None):
            with self.subTest(value=value):
                self.transport.calls.clear()
                self.transport.row['version'] = value
                self.assertFalse(self.run_refresh()['deployed'])
                self.assertEqual([], self.transport.writes())

    def test_wrong_vhost_or_account_or_root_never_writes(self):
        for key in ('vhost', 'account', 'homedir', 'documentroot'):
            with self.subTest(key=key):
                old = self.transport.row[key]
                self.transport.row[key] = 'wrong'
                self.assertFalse(self.run_refresh()['refreshPerformed'])
                self.assertEqual([], self.transport.writes())
                self.transport.row[key] = old

    def test_duplicate_and_nonmatching_rows_refused(self):
        self.transport.row = [copy.deepcopy(self.transport.row), copy.deepcopy(self.transport.row)]
        self.assertEqual('vhost_ambiguous', self.run_refresh()['errorCode'])
        self.assertEqual([], self.transport.writes())

    def test_domaininfo_wrong_before_write(self):
        self.transport.domain['documentroot'] = '/other'
        self.assertEqual('documentroot_mismatch', self.run_refresh()['errorCode'])
        self.assertEqual([], self.transport.writes())

    def test_wrong_account_config_before_network(self):
        client = p.RefreshClient(CpanelConfig('wrong.example', 2083, 'archite4', '', p.HOME,
                                p.HOME, api_token='fixture'), self.transport)
        self.assertFalse(p.refresh(client, public)['deployed'])
        self.assertEqual([], self.transport.calls)

    def test_actual_provider_failure_code_no_raw_error(self):
        self.transport.setter_fail = True
        result = self.run_refresh()
        self.assertFalse(result['refreshPerformed'])
        self.assertEqual(0, result['providerStatus'])
        self.assertEqual(417, result['providerErrorCode'])
        self.assertNotIn('secret', json.dumps(result))
        self.assertNotIn('fixture-token', json.dumps(result))

    def test_direct_envelope(self):
        self.transport.envelope = 'direct'
        self.assertTrue(self.run_refresh()['deployed'])

    def test_mixed_and_bad_errors_fail_closed(self):
        self.transport.envelope = 'mixed'
        self.assertEqual('provider_envelope_invalid', self.run_refresh()['errorCode'])
        self.transport.envelope = 'wrapped'
        self.transport.bad_errors = True
        self.assertEqual('provider_envelope_invalid', self.run_refresh()['errorCode'])
        self.assertEqual([], self.transport.writes())

    def test_setter_extra_target_refused(self):
        self.transport.setter['vhosts'].append('other.example')
        self.assertEqual('setter_targets_unverified', self.run_refresh()['errorCode'])

    def test_changed_readback_not_success(self):
        self.transport.after_version = 'ea-php82'
        result = self.run_refresh()
        self.assertFalse(result['refreshPerformed'])
        self.assertFalse(result['deployed'])

    def test_changed_documentroot_preserves_operation_not_deployment(self):
        self.transport.after_docroot = '/wrong'
        result = self.run_refresh()
        self.assertTrue(result['refreshPerformed'])
        self.assertFalse(result['deployed'])

    def test_waf_200_not_json_still_reports_verified_refresh(self):
        result = self.run_refresh(lambda path: (200, b'<html>challenge</html>'))
        self.assertTrue(result['refreshPerformed'])
        self.assertFalse(result['deployed'])
        self.assertEqual(5, len(result['httpStatus']))
        self.assertFalse(result['readinessPassed'])

    def test_readiness_503_does_not_rollback(self):
        result = self.run_refresh(lambda path: (503, b'old maintenance') if path == '/api/v1/readiness' else public(path))
        self.assertTrue(result['refreshPerformed'])
        self.assertFalse(result['deployed'])
        self.assertEqual(503, result['httpStatus']['readiness'])
        self.assertEqual(1, len(self.transport.writes()))

    def test_wrong_build_or_nonready_component_or_health(self):
        for target in ('/build-info.json', '/api/v1/readiness', '/api/v1/health'):
            with self.subTest(target=target):
                result = self.run_refresh(lambda path: (200, b'{"data":{"status":"not_ready"}}') if path == target else public(path))
                self.assertTrue(result['refreshPerformed'])
                self.assertFalse(result['deployed'])

    def test_partial_component_not_ready(self):
        def get(path):
            status, body = public(path)
            if path == '/api/v1/readiness':
                value = json.loads(body)
                value['data']['components']['mail']['status'] = 'unavailable'
                body = json.dumps(value).encode()
            return status, body
        self.assertFalse(self.run_refresh(get)['deployed'])

    def test_public_transport_failure_sanitized(self):
        def get(path):
            raise RuntimeError('secret and raw headers')
        result = self.run_refresh(get)
        self.assertTrue(result['refreshPerformed'])
        self.assertFalse(result['deployed'])
        self.assertEqual({k: None for k in ('build', 'readiness', 'health', 'root', 'login')}, result['httpStatus'])
        self.assertNotIn('secret', json.dumps(result))


if __name__ == '__main__':
    unittest.main()
