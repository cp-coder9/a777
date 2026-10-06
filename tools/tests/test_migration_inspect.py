import copy
import hashlib
import json
import tempfile
import unittest
from pathlib import Path
from types import SimpleNamespace
from tools.migration_inspect import inspect_migrations, migration_manifest, safe_report
from tools.repair_php_deployment import DeploymentConfig, DeploymentFailure
from tools.cpanel_client import HttpResponse

CAPS = {'processPrivilege','procReadable','procOpen','sodium','cliPhp83','zipAes256','backupPasswordConfigured','backupHmacKeyConfigured','privateStorage','storageWritable','providerInventoryEstablished','quiescenceEstablished','recoverableBackupEstablished'}

def report():
    return dict(databaseIdentity=dict(configDatabase='archite4_example', selectedDatabase='archite4_example', runtimeConfigSha256='a'*64, runtimeRoot='/home/archite4/public_html/.a777-runtime-active'), targetDatabaseMatches=True, ledgerExists=True, ledgerChecksumsMatch=True,
                pendingVersions=['0001_example'], pendingCount=1, capabilities={k: False for k in CAPS}, ddlAuthorized=False)

class InspectionTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.root = Path(self.tmp.name)
        (self.root/'database/migrations').mkdir(parents=True)
        (self.root/'database/migrations/0001_example.sql').write_text('-- architex:migration-mode=ddl\nSELECT 1;')
        self.manifest = migration_manifest(self.root/'database/migrations')

    def test_valid_report(self):
        self.assertEqual(safe_report(report(), self.manifest)['pendingCount'], 1)

    def test_no_arbitrary_strings(self):
        for bad in ['SECRET', '0001_secret', '../0001_example', '0001_example\n', '0001_example.sql', 1]:
            with self.subTest(bad=bad):
                value = report(); value['pendingVersions'] = [bad]
                with self.assertRaises(DeploymentFailure): safe_report(value, self.manifest)

    def test_no_duplicates(self):
        value = report(); value.update(pendingVersions=['0001_example']*2, pendingCount=2)
        with self.assertRaises(DeploymentFailure): safe_report(value, self.manifest)

    def test_count_and_booleans_strict(self):
        for key, value in [('pendingCount', True), ('pendingCount','1'), ('ddlAuthorized', True), ('targetDatabaseMatches',False)]:
            r = report(); r[key] = value
            with self.assertRaises(DeploymentFailure): safe_report(r,self.manifest)

    def test_mismatch_cannot_publish_ids(self):
        r = report(); r['ledgerChecksumsMatch'] = False
        with self.assertRaises(DeploymentFailure): safe_report(r,self.manifest)
        r.update(pendingVersions=[],pendingCount=None)
        self.assertEqual(safe_report(r,self.manifest)['pendingVersions'],[])

    def test_capability_cannot_establish_quiescence(self):
        r = report(); r['capabilities']['quiescenceEstablished'] = True
        with self.assertRaises(DeploymentFailure): safe_report(r,self.manifest)

    def test_manifest_rejects_duplicate_sequence(self):
        (self.root/'database/migrations/0001_other.sql').write_text('SELECT 1;')
        with self.assertRaises(DeploymentFailure): migration_manifest(self.root/'database/migrations')

    def execute(self, *, invalid=False, lost=False, foreign=False, cron=None, database=None, inventory=None, identity=None, account_home=None):
        class Remote:
            config = SimpleNamespace(username='archite4')
            source = None
            def account_home(self): return account_home or '/home/archite4'
            def _uapi(self,module,function,params):
                if module == 'Mysql':
                    return inventory if inventory is not None else [{'database':'archite4_example','users':['PRIVATE_DB_USER']},{'database':'archite4_other'}]
                return {'documentroot':'/home/archite4/public_html/php.architex.co.za'}
            def exists(self,path): return self.source is not None
            def save_file(self,path,source):
                self.source = source
                if lost: raise DeploymentFailure('Lost upload reply.')
            def _request_json(self,*args): return {'cpanelresult':{'event':{'result':1},'data':cron or []}}
        remote = Remote()
        class Transport:
            actions = []
            def request(self,method,url,headers,body,timeout):
                req = json.loads(body); self.actions.append(req['action'])
                if req['action'] == 'identity': value = {'sha256': 'foreign' if foreign else hashlib.sha256(remote.source.encode()).hexdigest()}
                elif req['action'] == 'cleanup': remote.source = None; value = {'removed':True}
                else:
                    value = report()
                    if identity is not None: value['databaseIdentity'] = identity
                    if invalid: value['pendingVersions'] = ['0001_SECRET']
                return HttpResponse(200,{},json.dumps({'challenge':req['challenge'],'result':value}).encode())
        transport = Transport()
        config = DeploymentConfig(self.root,'https://php.architex.co.za','/home/archite4/public_html/php.architex.co.za','/home/archite4', '20261006-120000-12345678-1234567890abcdef')
        try: return inspect_migrations(config,remote,database,transport),remote,transport
        finally: self.remote,self.transport = remote,transport

    def test_auto_exact_lookup_not_single_inventory_guess(self):
        result,_,_ = self.execute()
        self.assertEqual(result['databaseIdentity']['database'], 'archite4_example')
        self.assertTrue(result['databaseIdentity']['independentlyConfirmed'])
        self.assertNotIn('PRIVATE_DB_USER', json.dumps(result))
        self.assertFalse(result['ddlAuthorized'])
        self.assertEqual(result['providerEd25519InventoryEvidence'], 'unavailable')

    def test_config_pdo_mismatch_refuses(self):
        identity = report()['databaseIdentity']; identity['selectedDatabase'] = 'archite4_other'
        with self.assertRaises(DeploymentFailure): self.execute(identity=identity)
        self.assertIsNone(self.remote.source)

    def test_wrong_account_inventory_even_single_row_refuses(self):
        with self.assertRaises(DeploymentFailure): self.execute(inventory=[{'database':'otheraccount_only'}])
        self.assertIsNone(self.remote.source)

    def test_account_home_mismatch_refuses(self):
        with self.assertRaises(DeploymentFailure): self.execute(account_home='/home/otheraccount')
        self.assertIsNone(self.remote.source)

    def test_optional_operator_override_mismatch_refuses(self):
        with self.assertRaises(DeploymentFailure): self.execute(database='archite4_other')
        self.assertIsNone(self.remote.source)
        self.assertEqual(self.execute(database='archite4_example')[0]['pendingCount'], 1)

    def test_wrong_runtime_fingerprint_binding_refuses(self):
        identity = report()['databaseIdentity']; identity['runtimeRoot'] = '/home/other/runtime'
        with self.assertRaises(DeploymentFailure): self.execute(identity=identity)
        identity['runtimeRoot'] = '/home/archite4/public_html/.a777-runtime-active'
        identity['runtimeConfigSha256'] = 'PRIVATE_APP_KEY'
        with self.assertRaises(DeploymentFailure): self.execute(identity=identity)

    def test_secret_report_fields_refuse_without_echo(self):
        for field in ['dbUser','dbPassword','APP_KEY','runtimeConfig']:
            identity = report()['databaseIdentity']; identity[field] = 'PRIVATE_SECRET'
            with self.assertRaises(DeploymentFailure) as caught: self.execute(identity=identity)
            self.assertNotIn('PRIVATE_SECRET', str(caught.exception))
        value = report(); value['runtimeConfig'] = 'PRIVATE_SECRET'
        with self.assertRaises(DeploymentFailure) as caught: safe_report(value, self.manifest)
        self.assertNotIn('PRIVATE_SECRET', str(caught.exception))

    def test_cli_optional_override(self):
        from tools.repair_php_deployment import parse_args
        self.assertIsNone(parse_args(['migration-inspect','--release',str(self.root)]).expected_database)

    def test_owned_cleanup_verified(self):
        result,remote,http = self.execute()
        self.assertIsNone(remote.source)
        self.assertEqual(http.actions,['identity','inspect','identity','cleanup'])
        self.assertTrue(result['cronEntryOwnershipEstablished'])

    def test_invalid_report_still_cleans(self):
        with self.assertRaises(DeploymentFailure): self.execute(invalid=True)
        self.assertIsNone(self.remote.source)

    def test_lost_upload_response_still_cleans(self):
        with self.assertRaises(DeploymentFailure): self.execute(lost=True)
        self.assertIsNone(self.remote.source)

    def test_foreign_bytes_never_deleted(self):
        with self.assertRaises(DeploymentFailure): self.execute(foreign=True)
        self.assertIsNotNone(self.remote.source)
        self.assertNotIn('cleanup', self.transport.actions)

    def test_unknown_cron_never_claims_ownership_or_prints_command(self):
        row = dict(command='SECRET cron', minute='*',hour='*',day='*',month='*',weekday='*')
        result,_,_ = self.execute(cron=[row])
        self.assertFalse(result['cronEntryOwnershipEstablished'])
        self.assertNotIn('SECRET', json.dumps(result))

    def test_php_unauthenticated_refuses_before_runtime_load(self):
        import subprocess
        text = Path('tools/migration_inspect_helper.php').read_text().replace('/*INSPECT_CONFIG*/', "$inspection = ['keyHash'=>hash('sha256','expected-key'),'runtimeRoot'=>'/does-not-exist'];")
        path = self.root/'helper.php'; path.write_text(text)
        code = "$_SERVER['REQUEST_METHOD']='POST'; $_SERVER['HTTP_X_A777_OPERATOR_KEY']='wrong-key'; require " + json.dumps(str(path).replace('\\\\','/')) + ";"
        completed = subprocess.run(['php','-r',code],capture_output=True,text=True,check=True)
        self.assertEqual(completed.stdout, '{"status":"inspection_refused"}')
        self.assertEqual(completed.stderr, '')

    def test_helper_native_database_identity_contract(self):
        text = Path('tools/migration_inspect_helper.php').read_text()
        self.assertIn("$database = $pdo->query('SELECT DATABASE()')->fetchColumn()", text)
        self.assertIn('$database !== $config->dbName', text)
        self.assertIn("$inspection['database'] !== null && $database !== $inspection['database']", text)
        self.assertLess(text.index('$configFingerprint = hash_file'), text.index('EnvironmentLoader::apply'))
        self.assertIn("hash_equals($configFingerprint, hash_file('sha256', $root.'/config/runtime.php'))", text)
        self.assertIn("$pdo->exec('SET TRANSACTION READ ONLY')", text)

    def test_helper_auth_before_runtime_and_no_mutation_operator(self):
        text = Path('tools/migration_inspect_helper.php').read_text()
        self.assertLess(text.index('HTTP_X_A777_OPERATOR_KEY'),text.index('require_once'))
        for forbidden in ['->migrate(', '->status(', 'mkdir(', 'file_put_contents(', 'MaintenanceFence(', 'GET_LOCK', 'CREATE TABLE']:
            self.assertNotIn(forbidden, text.replace('// Snapshot only; no GET_LOCK, ledger creation, MigrationRunner.migrate or status.',''))
