"""Offline regressions: safe operation identity survives recovery."""
import json
import tempfile
import unittest
from pathlib import Path

from tools.cpanel_client import CpanelError, HttpResponse
from tools.repair_php_deployment import DeploymentFailure, render_failure
from tools.tests.test_cpanel_client import FakeTransport, configured_client, response
from tools.tests.test_repair_php_deployment import (
    FakeCpanel, FakeHttp, deployment, make_release, DOCUMENT_ROOT, ROOT,
)


class FailureObservabilityTest(unittest.TestCase):
    def test_uapi_failure_identifies_only_fixed_operation_and_integer_status(self):
        for wrapped in (True, False):
            for status in (0, None, 'provider-secret', {'secret': 'provider-secret'}):
                with self.subTest(wrapped=wrapped, status_type=type(status).__name__):
                    result = {'status': status, 'errors': ['unknown-runtime-secret'],
                              'data': {'content': 'unknown-runtime-secret'}}
                    client = configured_client(FakeTransport([response({'result': result} if wrapped else result)]))
                    with self.assertRaises(CpanelError) as caught:
                        client._uapi('Fileman', 'get_file_content', {'file': 'private-name'})
                    text = str(caught.exception)
                    self.assertIn('operation=Fileman/get_file_content', text)
                    self.assertIn('uapiStatus=' + ('0' if status == 0 else 'unknown'), text)
                    for forbidden in ('provider-secret', 'unknown-runtime-secret', 'private-name'):
                        self.assertNotIn(forbidden, text)

    def test_unknown_operation_is_not_reflected(self):
        client = configured_client(FakeTransport([response({'status': 0})]))
        with self.assertRaises(CpanelError) as caught:
            client._uapi('secret-module', 'secret-function')
        self.assertIn('operation=unknown', str(caught.exception))
        self.assertNotIn('secret-', str(caught.exception))

    def test_transport_failure_keeps_safe_operation_identity(self):
        client = configured_client(FakeTransport([HttpResponse(200, {}, b'unknown-runtime-secret')]))
        with self.assertRaises(CpanelError) as caught:
            client.list_files(ROOT)
        self.assertIn('operation=Fileman/list_files', str(caught.exception))
        self.assertIn('JSON contract', str(caught.exception))
        self.assertNotIn('unknown-runtime-secret', str(caught.exception))

    def test_original_stage_and_operation_survive_rollback_and_cleanup(self):
        events = []
        client = configured_client(FakeTransport([response({'status': 0, 'errors': ['unknown-runtime-secret']})]))

        class Fault(FakeCpanel):
            def save_file(self, remote, content):
                if remote.endswith('/index.php'):
                    client.save_file(remote, content)
                super().save_file(remote, content)

        with tempfile.TemporaryDirectory() as temporary:
            remote = Fault(events)
            owner = deployment(make_release(Path(temporary)), remote, FakeHttp(events))
            with self.assertRaises(DeploymentFailure) as caught:
                owner.deploy()
            encoded = render_failure(caught.exception)
            self.assertIn('stage=refresh_routing', encoded)
            self.assertIn('operation=Fileman/save_file_content', encoded)
            self.assertNotIn('unknown-runtime-secret', encoded)
            self.assertEqual('failed', json.loads(encoded)['status'])
            self.assertEqual([], owner.completed_renames)
            self.assertIn(DOCUMENT_ROOT, remote.paths)
            self.assertIn(ROOT + '/.a777-runtime-active', remote.paths)
            self.assertNotIn(owner.paths.archive, remote.paths)
            self.assertNotIn(owner.paths.probe, remote.paths)
            self.assertTrue(any(event.startswith('trash:.a777-package-') for event in events))


if __name__ == '__main__':
    unittest.main()
