"""Authenticated, bounded read-only migration inspection; never runs migration gates."""
from __future__ import annotations
import base64
import hashlib
import json
import re
import secrets
import time
from pathlib import Path
from tools.cpanel_host_operations import BoundedHelperTransport, CpanelHostOperations, cron_inventory
from tools.repair_php_deployment import DeploymentFailure, DeploymentPaths

ID = re.compile(r'[0-9]{4}_[a-z0-9_]+')


def migration_manifest(directory):
    result, sequences = {}, set()
    for path in sorted(Path(directory).glob('*.sql')):
        if path.is_symlink() or not ID.fullmatch(path.stem) or path.stem[:4] in sequences:
            raise DeploymentFailure('Invalid canonical migration inventory.')
        sequences.add(path.stem[:4])
        result[path.stem] = hashlib.sha256(path.read_bytes()).hexdigest()
    if not result:
        raise DeploymentFailure('Empty migration inventory.')
    return result


def safe_report(value, manifest):
    expected = {'databaseIdentity', 'targetDatabaseMatches', 'ledgerExists', 'ledgerChecksumsMatch', 'pendingVersions', 'pendingCount', 'capabilities', 'ddlAuthorized'}
    if not isinstance(value, dict) or set(value) != expected:
        raise DeploymentFailure('Invalid inspection report.')
    identity = value['databaseIdentity']
    if not isinstance(identity, dict) or set(identity) != {'configDatabase','selectedDatabase','runtimeConfigSha256','runtimeRoot'}:
        raise DeploymentFailure('Invalid database identity.')
    if any(not isinstance(identity[k], str) or not re.fullmatch(r'[a-zA-Z0-9_]{1,64}', identity[k]) for k in ('configDatabase','selectedDatabase')) or identity['configDatabase'] != identity['selectedDatabase']:
        raise DeploymentFailure('Runtime configuration and PDO database mismatch.')
    if not isinstance(identity['runtimeConfigSha256'], str) or not re.fullmatch(r'[0-9a-f]{64}', identity['runtimeConfigSha256']) or not isinstance(identity['runtimeRoot'], str):
        raise DeploymentFailure('Invalid runtime fingerprint.')
    for field in ('targetDatabaseMatches', 'ledgerExists', 'ledgerChecksumsMatch', 'ddlAuthorized'):
        if type(value[field]) is not bool:
            raise DeploymentFailure('Invalid inspection booleans.')
    if not value['targetDatabaseMatches'] or value['ddlAuthorized']:
        raise DeploymentFailure('Inspection scope violation.')
    ids = value['pendingVersions']
    if not isinstance(ids, list) or any(not isinstance(i, str) or not ID.fullmatch(i) or i not in manifest for i in ids) or len(set(ids)) != len(ids) or ids != sorted(ids):
        raise DeploymentFailure('Invalid pending migration identifiers.')
    count = value['pendingCount']
    if value['ledgerChecksumsMatch']:
        if type(count) is not int or count != len(ids):
            raise DeploymentFailure('Invalid pending migration count.')
    elif count is not None or ids:
        raise DeploymentFailure('Unsafe mismatched ledger report.')
    keys = {'processPrivilege','procReadable','procOpen','sodium','cliPhp83','zipAes256','backupPasswordConfigured','backupHmacKeyConfigured','privateStorage','storageWritable','providerInventoryEstablished','quiescenceEstablished','recoverableBackupEstablished'}
    caps = value['capabilities']
    if not isinstance(caps, dict) or set(caps) != keys or any(type(v) is not bool for v in caps.values()):
        raise DeploymentFailure('Invalid capability report.')
    if any(caps[k] for k in ('providerInventoryEstablished','quiescenceEstablished','recoverableBackupEstablished')):
        raise DeploymentFailure('Inspection cannot authorize DDL.')
    return value


def inspect_migrations(config, client, database=None, transport=None):
    if database is not None and (not isinstance(database, str) or not re.fullmatch(r'[a-zA-Z0-9_]{1,64}', database)):
        raise DeploymentFailure('Invalid optional expected database.')
    if client.account_home() != config.account_home:
        raise DeploymentFailure('Account mismatch.')
    domain = config.target_url.split('://', 1)[1]
    info = client._uapi('DomainInfo', 'single_domain_data', {'domain': domain})
    if isinstance(info, dict) and isinstance(info.get('domain_data'), dict):
        info = info['domain_data']
    if not isinstance(info, dict) or info.get('documentroot') != config.document_root:
        raise DeploymentFailure('Exact domain document root not established.')
    manifest = migration_manifest(config.release / 'database/migrations')
    paths = DeploymentPaths.for_id(config, str(config.deployment_id))
    key, challenge = secrets.token_hex(32), secrets.token_hex(32)
    path = config.document_root + '/__a777_migration_inspect_' + secrets.token_hex(16) + '.php'
    settings = {'keyHash': hashlib.sha256(key.encode()).hexdigest(), 'expiresAt': int(time.time()) + 900,
                'runtimeRoot': paths.active_runtime, 'documentRoot': config.document_root, 'database': database, 'migrations': manifest}
    source = Path(__file__).with_name('migration_inspect_helper.php').read_text().replace('/*INSPECT_CONFIG*/', "$inspection = json_decode(base64_decode('" + base64.b64encode(json.dumps(settings).encode()).decode() + "'),true,32,JSON_THROW_ON_ERROR);")
    digest = hashlib.sha256(source.encode()).hexdigest()
    http = transport or BoundedHelperTransport()
    url = config.target_url + '/' + path.rsplit('/', 1)[1]
    def call(action):
        response = http.request('POST', url, {'X-A777-Operator-Key': key, 'Content-Type': 'application/json'}, json.dumps({'action': action, 'challenge': challenge, 'sha256': digest}).encode(), 90)
        if response.status != 200:
            raise DeploymentFailure('Authenticated migration inspection refused.')
        envelope = json.loads(response.body)
        if set(envelope) != {'challenge','result'} or envelope['challenge'] != challenge:
            raise DeploymentFailure('Inspection challenge mismatch.')
        return envelope['result']
    if client.exists(path):
        raise DeploymentFailure('Inspection helper collision.')
    attempted = False
    try:
        attempted = True  # ownership intent before upload, including lost replies
        client.save_file(path, source)
        if call('identity') != {'sha256': digest}:
            raise DeploymentFailure('Owned helper identity mismatch.')
        result = safe_report(call('inspect'), manifest)
        identity = result['databaseIdentity']
        observed = identity['selectedDatabase']
        if identity['runtimeRoot'] != paths.active_runtime or (database is not None and observed != database):
            raise DeploymentFailure('Runtime binding or optional database assertion mismatch.')
        # Official UAPI Mysql/list_databases is scoped to this authenticated account.
        # Match the observed name exactly; never choose an inventory candidate.
        try:
            inventory = client._uapi('Mysql', 'list_databases', {})
        except Exception:
            raise DeploymentFailure('Authenticated database inventory unavailable.') from None
        if not isinstance(inventory, list) or any(not isinstance(row, dict) or not isinstance(row.get('database'), str) for row in inventory):
            raise DeploymentFailure('Invalid account database inventory.')
        if sum(row['database'] == observed for row in inventory) != 1 or client.account_home() != config.account_home:
            raise DeploymentFailure('Observed database does not belong to the exact authenticated account.')
        result['databaseIdentity'] = {'database': observed, 'runtimeConfigSha256': identity['runtimeConfigSha256'], 'runtimeRoot': paths.active_runtime, 'accountHome': config.account_home, 'authenticatedAccount': client.config.username, 'targetUrl': config.target_url, 'documentRoot': config.document_root, 'inventorySource': 'UAPI Mysql/list_databases', 'independentlyConfirmed': True}
        result['providerEd25519InventoryEvidence'] = 'unavailable'
        # Read-only Cron API2 inventory. Never print commands, schedules or raw API data.
        adapter = object.__new__(CpanelHostOperations)
        adapter.client = client
        try:
            rows = adapter.cron('listcron')
            cron_inventory(rows)
            result['cronInventoryAvailable'] = True
            result['cronEntryOwnershipEstablished'] = not rows
        except Exception:
            result['cronInventoryAvailable'] = False
            result['cronEntryOwnershipEstablished'] = False
        return result
    finally:
        if attempted and client.exists(path):
            # Cleanup refuses foreign/replaced bytes; never trash an unverified path.
            if call('identity') != {'sha256': digest} or call('cleanup') != {'removed': True}:
                raise DeploymentFailure('Owned helper cleanup refused.')
        if attempted and client.exists(path):
            raise DeploymentFailure('Inspection helper absence not verified.')
