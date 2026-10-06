import base64
import hashlib
import json
import unittest
from unittest.mock import patch
from test_cpanel_git_preflight import NativeFake, g, SHA
from cpanel_client import CpanelError, CpanelClient


class TokenLifecycleTests(unittest.TestCase):
    def run_native(self, f):
        with patch.object(g, 'clone', return_value={'status': 'cloned_and_pulled', 'commit': SHA}):
            report = g.native_preflight(f, SHA, attempts=1, pause=0)
        self.assertNotIn('PRIVATE_SENTINEL', json.dumps(report))
        return report

    def old_plan(self, f, transport=False):
        g.preflight_plan(f, SHA)
        old = json.loads(f.files[g.PLAN])
        old['expectedCommit'] = 'b' * 40
        if not transport:
            del old['readOnlyUapiTransport']
        original = json.dumps(old, indent=2) + '\n'
        f.files[g.PLAN] = original
        name = g.DEPLOY_ROOT + '/preflight-' + old['expectedCommit'] + '-0123456789abcdef.json'
        f.files[name] = json.dumps({'format': 'a777-cpanel-native-preflight-v1', 'mode': 'preflight',
            'commit': old['expectedCommit'], 'planSha256': hashlib.sha256(original.encode()).hexdigest(),
            'status': 'capability-inspection-refused', 'databaseDdlTouched': False, 'liveFilesTouched': False})
        return original, name

    def test_success_private_upload_and_finally_absence(self):
        f = NativeFake()
        original = f._uapi
        def api(module, function, params):
            if module == 'VersionControlDeployment' and function == 'create':
                self.assertEqual(f.files[g.TOKEN], f.config.api_token)
                self.assertEqual(f.modes[g.TOKEN], 600)
                self.assertEqual(f.modes[g.DEPLOY_ROOT], 700)
            return original(module, function, params)
        with patch.object(f, '_uapi', side_effect=api):
            r = self.run_native(f)
        self.assertEqual(r['status'], 'preflight_complete')
        self.assertTrue(r['tokenAbsenceVerified'])
        self.assertNotIn(g.TOKEN, f.files)
        self.assertEqual(json.loads(f.files[g.PLAN])['readOnlyUapiTransport'], 'native-cli-then-private-token-https')

    def test_save_is_form_post_not_query(self):
        class Wire:
            config = NativeFake.config
            def _uapi(self, module, function, params=None, body=None, headers=None):
                self.args = module, function, params, body, headers
        wire = Wire()
        CpanelClient.save_file(wire, g.TOKEN, wire.config.api_token)
        module, function, params, body, headers = wire.args
        self.assertEqual((module, function, params), ('Fileman', 'save_file_content', None))
        self.assertIn(wire.config.api_token.encode(), body)
        self.assertEqual(headers['Content-Type'], 'application/x-www-form-urlencoded')

    def test_roll_old_and_new_plans_with_exact_archived_bytes(self):
        for transport in (False, True):
            with self.subTest(transport=transport):
                f = NativeFake(); original, _ = self.old_plan(f, transport)
                r = self.run_native(f)
                self.assertEqual(r['status'], 'preflight_complete')
                digest = hashlib.sha256(original.encode()).hexdigest()
                path = g.DEPLOY_ROOT + '/plan-roll-' + digest + '.json'
                intent = json.loads(f.files[path])
                self.assertEqual(base64.b64decode(intent['originalBytesBase64']), original.encode())
                self.assertEqual(intent['originalSha256'], digest)
                self.assertEqual(f.modes[path], 600)

    def test_bad_receipts_do_not_roll(self):
        for field, value in [('planSha256', '0' * 64), ('commit', 'c' * 40),
                             ('databaseDdlTouched', True), ('liveFilesTouched', True),
                             ('mode', 'deploy'), ('status', 'PRIVATE_SENTINEL')]:
            with self.subTest(field=field):
                f = NativeFake(); original, path = self.old_plan(f)
                receipt = json.loads(f.files[path]); receipt[field] = value
                f.files[path] = json.dumps(receipt)
                r = self.run_native(f)
                self.assertEqual(r['error'], 'native_preflight_private_plan_refused')
                self.assertEqual(f.files[g.PLAN], original)
                self.assertNotIn(g.TOKEN, f.files)

    def test_plan_scope_and_extra_fields_refused(self):
        for field, value in [('mode', 'deploy'), ('mode', 'stage-only'), ('account', 'other'),
                             ('activeRoot', '/home/other'), ('repository', '/home/other'),
                             ('documentRoot', '/home/other'), ('unknown', True)]:
            f = NativeFake(); original, _ = self.old_plan(f)
            plan = json.loads(original); plan[field] = value
            f.files[g.PLAN] = json.dumps(plan); before = f.files[g.PLAN]
            self.assertEqual(self.run_native(f)['error'], 'native_preflight_private_plan_refused')
            self.assertEqual(f.files[g.PLAN], before)

    def test_collisions_unknown_reports_leases_and_stale_token(self):
        for name in ('maintenance', 'lease.json', 'receipt-deploy.json', 'preflight-' + SHA + '-fedcba9876543210.json', 'cpanel-token'):
            f = NativeFake(); g.preflight_plan(f, SHA)
            path = g.DEPLOY_ROOT + '/' + name; f.files[path] = 'PRIVATE_SENTINEL_FOREIGN'
            self.assertEqual(self.run_native(f)['error'], 'native_preflight_private_plan_refused')
            self.assertEqual(f.files[path], 'PRIVATE_SENTINEL_FOREIGN')

    def test_lost_upload_response_reconciles_owned_only(self):
        for foreign in (False, True):
            f = NativeFake(); original = f.save_file
            def save(path, content):
                original(path, 'PRIVATE_SENTINEL_FOREIGN' if path == g.TOKEN and foreign else content)
                if path == g.TOKEN: raise RuntimeError('PRIVATE_SENTINEL_PROVIDER')
            with patch.object(f, 'save_file', side_effect=save): r = self.run_native(f)
            if foreign:
                self.assertEqual(f.files[g.TOKEN], 'PRIVATE_SENTINEL_FOREIGN')
                self.assertEqual(r['cleanup'], 'cleanupfailed')
                self.assertFalse(r['tokenAbsenceVerified'])
            else:
                self.assertEqual(r['status'], 'preflight_complete')
                self.assertTrue(r['tokenAbsenceVerified'])

    def test_failure_finally_and_cleanup_failure_preserve_primary_error(self):
        for cleanup_fail in (False, True):
            f = NativeFake(); original = f._uapi
            def api(module, function, params):
                if module == 'VersionControlDeployment': raise RuntimeError('PRIVATE_SENTINEL_PROVIDER')
                return original(module, function, params)
            def trash(path):
                if cleanup_fail: raise RuntimeError('PRIVATE_SENTINEL_PROVIDER')
                del f.files[path]
            with patch.object(f, '_uapi', side_effect=api), patch.object(f, 'trash', side_effect=trash):
                r = self.run_native(f)
            self.assertEqual(r['error'], 'native_preflight_native_create_refused')
            self.assertEqual(r['tokenAbsenceVerified'], not cleanup_fail)
            if cleanup_fail: self.assertEqual(r['cleanup'], 'cleanupfailed')

    def test_changed_token_or_permissions_never_trashed(self):
        for mutation in ('content', 'filemode', 'rootmode'):
            f = NativeFake(); original = f._uapi
            def api(module, function, params):
                result = original(module, function, params)
                if module == 'VersionControlDeployment' and function == 'retrieve':
                    if mutation == 'content': f.files[g.TOKEN] = 'PRIVATE_SENTINEL_FOREIGN'
                    elif mutation == 'filemode': f.modes[g.TOKEN] = 644
                    else: f.modes[g.DEPLOY_ROOT] = 755
                return result
            with patch.object(f, '_uapi', side_effect=api): r = self.run_native(f)
            self.assertIn(g.TOKEN, f.files)
            self.assertEqual(r['cleanup'], 'cleanupfailed')
            self.assertNotEqual(r['status'], 'preflight_complete')

    def test_lost_trash_response_verifies_absence(self):
        f = NativeFake()
        def trash(path):
            del f.files[path]
            raise RuntimeError('PRIVATE_SENTINEL_PROVIDER')
        with patch.object(f, 'trash', side_effect=trash): r = self.run_native(f)
        self.assertTrue(r['tokenAbsenceVerified'])
        self.assertEqual(r['status'], 'preflight_complete')

    def test_chmod_capability_required_before_token_upload(self):
        f = NativeFake(); g.preflight_plan(f, SHA)
        with patch.object(f, '_api2', side_effect=RuntimeError('PRIVATE_SENTINEL_PROVIDER')):
            r = self.run_native(f)
        self.assertEqual(r['error'], 'native_preflight_private_plan_refused')
        self.assertNotIn(g.TOKEN, f.files)

    def test_same_commit_legacy_plan_requires_matching_receipt(self):
        f = NativeFake(); original, path = self.old_plan(f)
        old = json.loads(original); old['expectedCommit'] = SHA
        f.files[g.PLAN] = json.dumps(old)
        self.assertEqual(self.run_native(f)['error'], 'native_preflight_private_plan_refused')

    def test_symlink_token_cleanup_is_unknown_not_foreign_delete(self):
        f = NativeFake(); original = g.entries
        def inventory(client, path):
            rows = original(client, path)
            if path == g.DEPLOY_ROOT and g.TOKEN in f.files and f.polls:
                rows = [dict(row, type='link') if row['name'] == 'cpanel-token' else row for row in rows]
            return rows
        with patch.object(g, 'entries', side_effect=inventory):
            r = self.run_native(f)
        self.assertEqual(r['cleanup'], 'cleanupfailed')
        self.assertIn(g.TOKEN, f.files)

    def test_all_uapi_failure_reports_are_redacted(self):
        for function in ('get_user_information', 'retrieve', 'create', 'list_files', 'get_file_content'):
            f = NativeFake(); original = f._uapi
            def api(module, fn, params):
                if fn == function: raise RuntimeError('PRIVATE_SENTINEL_PROVIDER')
                return original(module, fn, params)
            with patch.object(f, '_uapi', side_effect=api):
                r = self.run_native(f)
            self.assertNotEqual(r['status'], 'preflight_complete')

    def test_diagnostic_allowlist_rejects_secret_values(self):
        f = NativeFake(); plan_sha = g.preflight_plan(f, SHA)
        path = g.DEPLOY_ROOT + '/preflight-' + SHA + '-0123456789abcdef.json'
        data = {'format': 'a777-cpanel-native-preflight-v1', 'mode': 'preflight', 'commit': SHA,
                'planSha256': plan_sha, 'status': 'inspected-not-authorized',
                'databaseDdlTouched': False, 'liveFilesTouched': False,
                'mysqlDatabaseTransport': 'authenticated-https', 'mysqlUserTransport': 'PRIVATE_SENTINEL',
                'mysqlDatabaseTransportCli': {'status': 'failed', 'exitCode': 37, 'stderrClass': 'permission_denied',
                     'absoluteExecutablePresent': True, 'absoluteExecutableUsable': False, 'pathExecutableUsable': False,
                     'stderr': 'PRIVATE_SENTINEL'},
                'mysqlUserTransportCli': {'status': 'PRIVATE_SENTINEL', 'exitCode': 'PRIVATE_SENTINEL',
                     'stderrClass': 'PRIVATE_SENTINEL', 'absoluteExecutablePresent': 'PRIVATE_SENTINEL'}}
        for host_class in ('tcp-loopback', 'local-socket', 'refused', 'PRIVATE_SENTINEL'):
            data['databaseHostClass'] = host_class
            data['databaseConfigParsed'] = True
            data['databaseHostLocal'] = 'PRIVATE_SENTINEL'
            data['pdoUserMatches'] = False
            data['dbUser'] = 'PRIVATE_SENTINEL'
            data['dbPassword'] = 'PRIVATE_SENTINEL'
            f.files[path] = json.dumps(data)
            clean = g.receipts(f, SHA, plan_sha, set())[0]
            self.assertNotIn('PRIVATE_SENTINEL', json.dumps(clean))
            self.assertTrue(clean['databaseConfigParsed'])
            self.assertFalse(clean['pdoUserMatches'])
            self.assertNotIn('databaseHostLocal', clean)
            if host_class == 'PRIVATE_SENTINEL':
                self.assertNotIn('databaseHostClass', clean)
            else:
                self.assertEqual(clean['databaseHostClass'], host_class)
        f.files[path] = json.dumps(data)
        safe = g.receipts(f, SHA, plan_sha, set())[0]
        self.assertNotIn('PRIVATE_SENTINEL', json.dumps(safe))
        self.assertEqual(safe['mysqlDatabaseTransportCli']['exitCode'], 37)
        self.assertEqual(safe['mysqlUserTransportCli'], {})
        self.assertNotIn('mysqlUserTransport', safe)


if __name__ == '__main__': unittest.main()
