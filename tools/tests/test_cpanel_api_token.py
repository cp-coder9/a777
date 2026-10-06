import unittest
from unittest.mock import patch
from tools.cpanel_client import CpanelClient, CpanelConfig, CpanelError, redact_text
from tools.tests.test_cpanel_client import FakeTransport, uapi_ok
from tools.repair_php_deployment import _configured_remote, _environment_secrets, parse_args, render_failure

class ApiTokenTest(unittest.TestCase):
    def config(self, **kwargs):
        return CpanelConfig('cpanel.example.test', 2083, 'archite4', '', '/home/archite4', '/home/archite4/public_html', api_token='synthetic-api-token', **kwargs)

    def test_token_header(self):
        transport = FakeTransport([uapi_ok({'home': '/home/archite4'})])
        CpanelClient(self.config(), transport).account_home()
        headers = transport.requests[0]['headers']
        self.assertEqual('cpanel archite4:synthetic-api-token', headers['Authorization'])
        self.assertNotIn('Cookie', headers)
        self.assertNotIn('cpsess', transport.requests[0]['url'])

    def test_token_redaction(self):
        self.assertEqual('[REDACTED]', redact_text('synthetic-api-token', self.config().secrets))
        env = {'A777_CPANEL_API_TOKEN': 'synthetic-api-token'}
        self.assertNotIn('synthetic-api-token', render_failure(Exception('synthetic-api-token'), _environment_secrets(env)))

    def test_ambiguous_auth_refused(self):
        from dataclasses import replace
        for config in (replace(self.config(), password='synthetic-password'), self.config(session_id='cpsess1234567890', session_cookie='cpsession=fixture'), self.config(session_cookie='cpsession=fixture')):
            with self.subTest(config=config), self.assertRaises(CpanelError):
                CpanelClient(config, FakeTransport())

    def test_environment_configured_remote(self):
        args = parse_args(['inspect', '--release', '.'])
        env = {'A777_CPANEL_HOST': 'cpanel.example.test', 'A777_CPANEL_USER': 'archite4', 'A777_CPANEL_API_TOKEN': 'synthetic-api-token'}
        with patch('tools.cpanel_client.UrllibTransport', return_value=FakeTransport()):
            remote = _configured_remote(args, env, '/home/archite4')
        self.assertEqual('synthetic-api-token', remote.config.api_token)
        self.assertEqual('', remote.config.password)

if __name__ == '__main__':
    unittest.main()
