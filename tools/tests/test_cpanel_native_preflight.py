"""Synthetic protocol tests, not live authenticated provider evidence."""
import copy
from pathlib import Path
import sys
import unittest
from unittest.mock import Mock, patch

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT))
sys.path.insert(0, str(ROOT / 'tools'))
from tools import cpanel_native_preflight as p
import cpanel_native_stage as n

SHA = 'a' * 40
PIN = '5206e3a1f9447e6834fc3e2dd7cd254ce5dd47af33406c0c83085c4b4f28d0b8'


class SourcePreflightTests(unittest.TestCase):
    def setUp(self):
        self.callback = p.readonly_source_preflight(ROOT)
        self.client = Mock()
        self.report = {
            'databaseIdentity': {
                'database': p.DATABASE, 'runtimeConfigSha256': PIN,
                'runtimeRoot': p.RUNTIME_ROOT, 'accountHome': p.HOME,
                'authenticatedAccount': 'archite4', 'targetUrl': p.TARGET_URL,
                'documentRoot': p.DOCUMENT_ROOT,
                'inventorySource': 'UAPI Mysql/list_databases', 'independentlyConfirmed': True,
            },
            'targetDatabaseMatches': True, 'ledgerExists': True,
            'ledgerChecksumsMatch': True, 'ddlAuthorized': False,
            'pendingCount': 8,
            'pendingVersions': sorted(p.migration_manifest(ROOT / 'app/database/migrations'))[-8:],
        }

    def git(self, args, **kwargs):
        return {'--show-toplevel': str(ROOT).encode(), 'HEAD': SHA.encode(),
                '--untracked-files=all': b''}[args[-1]]

    def test_valid_synthetic_protocol_uses_manifest_not_artifact(self):
        with patch.object(p.subprocess, 'check_output', side_effect=self.git), patch.object(p, 'inspect_migrations', return_value=self.report) as inspect:
            proof = self.callback(self.client, SHA, PIN)
        self.assertEqual(proof, {'commit': SHA, 'runtimeConfigSha256': PIN,
            'sourceDatabase': p.DATABASE, 'readOnly': True,
            'sourceDatabaseDdlTouched': False, 'pendingMigrationCount': 8})
        config = inspect.call_args.args[0]
        self.assertEqual(config.release, ROOT / 'app')
        self.assertEqual(inspect.call_args.kwargs, {'database': p.DATABASE})

    def test_identity_ledger_pending_and_ddl_refusals(self):
        mutations = [
            ('identity', 'runtimeConfigSha256', '0' * 64),
            ('identity', 'database', 'archite4_wrong'),
            ('identity', 'runtimeRoot', '/wrong'),
            ('identity', 'accountHome', '/wrong'),
            ('identity', 'independentlyConfirmed', False),
            ('report', 'pendingVersions', self.report['pendingVersions'][:-1] + ['0001_platform_spine']),
            ('report', 'pendingCount', True), ('report', 'ddlAuthorized', True),
            ('report', 'ledgerChecksumsMatch', False), ('report', 'ledgerExists', False),
        ]
        for location, key, value in mutations:
            with self.subTest(key=key):
                report = copy.deepcopy(self.report)
                (report['databaseIdentity'] if location == 'identity' else report)[key] = value
                with patch.object(p.subprocess, 'check_output', side_effect=self.git), patch.object(p, 'inspect_migrations', return_value=report):
                    with self.assertRaises(p.DeploymentFailure):
                        self.callback(self.client, SHA, PIN)
                    with patch.object(n.p, 'authorize'), patch.object(n.p, 'owned_mode'), patch.object(n.p, 'read', return_value='{}'), patch.object(n, 'safe_retry_plan'), patch.object(n, 'build') as build, patch.object(n.p, 'provision_token') as grant:
                        native = n.native_deploy(self.client, ROOT, ROOT, SHA, PIN,
                            retry_safe_native_deploy=True, readonly_source_preflight=self.callback)
                    self.assertEqual(native['failureStage'], 'independent_readonly_source_preflight')
                    build.assert_not_called()
                    grant.assert_not_called()
                    self.client._uapi.assert_not_called()

    def test_helper_failure_propagates_without_proof_or_native_effects(self):
        with patch.object(p.subprocess, 'check_output', side_effect=self.git), patch.object(p, 'inspect_migrations', side_effect=p.DeploymentFailure('helper cleanup/absence failed')):
            with self.assertRaises(p.DeploymentFailure):
                self.callback(self.client, SHA, PIN)
            with patch.object(n.p, 'authorize'), patch.object(n.p, 'owned_mode'), patch.object(n.p, 'read', return_value='{}'), patch.object(n, 'safe_retry_plan'), patch.object(n, 'build') as build, patch.object(n.p, 'provision_token') as grant:
                report = n.native_deploy(self.client, ROOT, ROOT, SHA, PIN,
                    retry_safe_native_deploy=True, readonly_source_preflight=self.callback)
            self.assertEqual(report['failureStage'], 'independent_readonly_source_preflight')
            build.assert_not_called()
            grant.assert_not_called()
            self.client._uapi.assert_not_called()

    def test_cli_wires_only_retry_and_status_never_inspects(self):
        import contextlib
        import io
        import cpanel_git as cli
        sentinel = Mock()
        for mode, retry in [('native-deploy', True), ('native-deploy', False), ('native-status', False)]:
            argv = ['cpanel_git.py', mode, '--expected-commit', SHA,
                    '--expected-runtime-config-sha256', PIN]
            if retry: argv.append('--retry-safe-native-deploy')
            with self.subTest(mode=mode, retry=retry), patch.object(sys, 'argv', argv), patch.dict(cli.os.environ, {'RUNNER_TEMP': str(ROOT.parent)}), patch.object(cli, 'CpanelConfig'), patch.object(cli, 'CpanelClient'), patch.object(p, 'readonly_source_preflight', return_value=sentinel) as factory, patch.object(n, 'native_deploy', return_value={'status': 'native_deploy_complete'}) as deploy, patch.object(n, 'native_status', return_value={'status': 'native_status_complete'}) as status, contextlib.redirect_stdout(io.StringIO()):
                self.assertEqual(cli.main(), 0)
                if mode == 'native-status':
                    factory.assert_not_called(); deploy.assert_not_called(); status.assert_called_once()
                else:
                    self.assertEqual(deploy.call_args.kwargs['retry_safe_native_deploy'], retry)
                    self.assertIs(deploy.call_args.kwargs['readonly_source_preflight'], sentinel if retry else None)
                    if retry: factory.assert_called_once_with(ROOT)
                    else: factory.assert_not_called()

    def test_dirty_or_wrong_checkout_refused_before_helper(self):
        for bad in ('dirty', 'wrong-head', 'wrong-root'):
            def git(args, **kwargs):
                if bad == 'dirty' and args[-1] == '--untracked-files=all': return b' M file'
                if bad == 'wrong-head' and args[-1] == 'HEAD': return b'b' * 40
                if bad == 'wrong-root' and args[-1] == '--show-toplevel': return str(ROOT.parent).encode()
                return self.git(args, **kwargs)
            with self.subTest(bad=bad), patch.object(p.subprocess, 'check_output', side_effect=git), patch.object(p, 'inspect_migrations') as inspect:
                with self.assertRaises(p.DeploymentFailure): self.callback(self.client, SHA, PIN)
                inspect.assert_not_called()
