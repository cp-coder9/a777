"""Concrete operator transport. Never imports credentials or follows redirects.
Cron API contract: api.docs.cpanel.net/cpanel-api-2/cpanel-api-2-modules-cron.
This adapter deliberately has no promotion/resume method.
"""
from __future__ import annotations
import base64
import hashlib
import hmac
import json
import secrets
import os
import re
from collections import Counter
import urllib.parse


def account_path(path, account):
    if not isinstance(path, str) or not re.fullmatch(r'/home/[A-Za-z0-9_-]+(?:/[A-Za-z0-9_.-]+)*', path):
        raise RuntimeError('Canonical account scoped path required')
    if any(part in ('.', '..') for part in path.split('/')) or not path.startswith('/home/' + account + '/'):
        raise RuntimeError('Path escapes account')
    return path


def durable_private(path, value):
    path = Path(path)
    if any(p.is_symlink() for p in (path, *path.parents)):
        raise RuntimeError('Recovery path must not contain links')
    path.parent.mkdir(parents=True, exist_ok=True)
    os.chmod(path.parent, 0o700)
    temporary = path.with_name(path.name + '.' + secrets.token_hex(12))
    fd = os.open(temporary, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
    with os.fdopen(fd, 'wb') as output:
        output.write(canonical(value)); output.flush(); os.fsync(output.fileno())
    os.replace(temporary, path)
    os.chmod(path, 0o600)
    if os.name != 'nt':
        fd = os.open(path.parent, os.O_RDONLY)
        try: os.fsync(fd)
        finally: os.close(fd)


def cron_inventory(rows):
    fields = ('command', 'minute', 'hour', 'day', 'month', 'weekday')
    if any(any(f not in r for f in fields) for r in rows): raise RuntimeError('Complete command and schedule inventory required')
    return Counter(tuple(str(r[f]) for f in fields) for r in rows)

from pathlib import Path
from tools.cpanel_client import UrllibTransport
from tools.production_migration import HostOperations, canonical


class BoundedHelperTransport(UrllibTransport):
    """TLS verified, no redirect, bounded response reads (including error bodies)."""
    def request(self, method, url, headers, body, timeout):
        import urllib.error
        import urllib.request
        from tools.cpanel_client import RejectRedirects, HttpResponse
        limit = 1024**3
        opener = urllib.request.build_opener(urllib.request.HTTPSHandler(context=self.context), RejectRedirects())
        request = urllib.request.Request(url, data=body, headers=headers, method=method)
        try:
            response = opener.open(request, timeout=timeout)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            length = response.headers.get('Content-Length')
            if length is not None and (not length.isdigit() or int(length) > limit):
                raise RuntimeError('Helper response exceeds transfer bound')
            data = response.read(limit+1)
            if len(data) > limit: raise RuntimeError('Helper response exceeds transfer bound')
            return HttpResponse(response.status, dict(response.headers.items()), data)


class CpanelHostOperations(HostOperations):
    def __init__(self, client, origin, target, candidate_root, key, helper_path,
                 owned_cron, unrelated_cron=(), transport=None, cli_binary='/usr/local/bin/php', scheduler_journal=None, process_model=None, recovery_paths=None):
        u = urllib.parse.urlsplit(origin)
        if u.scheme != 'https' or not u.hostname or u.username or u.password or u.query or u.fragment or u.path not in ('', '/'):
            raise RuntimeError('Exact HTTPS origin required')
        if u.hostname != target['domain']:
            raise RuntimeError('Helper origin does not match exact target domain')
        if len(key) != 32 or not helper_path.startswith(target['documentRoot'] + '/'):
            raise RuntimeError('Invalid helper scope')
        for path in (helper_path, candidate_root, target['documentRoot'], target['runtimeRoot']):
            account_path(path, target['account'])
        self.client, self.origin, self.target = client, origin.rstrip('/'), dict(target)
        self.key, self.path, self.candidate_root = key, helper_path, candidate_root
        self.transport = transport or BoundedHelperTransport()
        self.owned, self.unrelated = set(owned_cron), set(unrelated_cron)
        if self.owned & self.unrelated: raise RuntimeError('Ambiguous scheduler ownership')
        self.suppressed, self.installed, self.archive = [], False, None
        self.lease = None
        self.cli_binary = cli_binary
        self.scheduler_journal = Path(scheduler_journal) if scheduler_journal else None
        self.process_model = process_model
        self.recovery_paths = recovery_paths
        self.recovery_file = self.scheduler_journal.with_name('operator-' + hashlib.sha256(key).hexdigest() + '.json') if self.scheduler_journal else None

    def install(self):
        if self.scheduler_journal is None: raise RuntimeError('Recovery directory required')
        directory = self.scheduler_journal.parent
        if any(p.is_symlink() for p in (directory, *directory.parents)): raise RuntimeError('Recovery ancestry links refused')
        directory.mkdir(parents=True,exist_ok=True)
        if os.name == 'nt':
            import getpass, subprocess, msvcrt
            # Replace the DACL atomically: no transient inherited/public grants.
            script = "$p=$args[0];$d=Get-Item -LiteralPath $p;$s=$d.GetAccessControl('Access');$s.SetAccessRuleProtection($true,$false);foreach($old in @($s.Access)){$s.RemoveAccessRuleSpecific($old)};$id=[System.Security.Principal.WindowsIdentity]::GetCurrent().User;$r=New-Object System.Security.AccessControl.FileSystemAccessRule($id,'FullControl','ContainerInherit,ObjectInherit','None','Allow');$s.AddAccessRule($r);$d.SetAccessControl($s)"
            subprocess.run(['powershell.exe','-NoProfile','-NonInteractive','-Command',
                "$ErrorActionPreference='Stop';$p='" + str(directory).replace("'", "''") + "';" + script.split(';',1)[1] + ";$v=$d.GetAccessControl('Access');if(!$v.AreAccessRulesProtected -or @($v.Access).Count -ne 1){throw 'Recovery ACL verification failed'}"],check=True,capture_output=True)
        else: os.chmod(directory,0o700)
        lock_path = directory / 'operator.lock'
        if lock_path.is_symlink(): raise RuntimeError('Operator lock link refused')
        self.operator_lock = open(lock_path,'a+b')
        self.operator_lock.seek(0)
        try:
            if os.name == 'nt': msvcrt.locking(self.operator_lock.fileno(),msvcrt.LK_NBLCK,1)
            else:
                import fcntl
                fcntl.flock(self.operator_lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
        except OSError:
            self.operator_lock.close()
            raise RuntimeError('Concurrent local operator') from None
        if not self.process_model: raise RuntimeError('Explicit provider process model required')
        if not self.recovery_paths or set(self.recovery_paths) != {'maintenanceDirectory','archiveDirectory'}:
            raise RuntimeError('Exact private server recovery paths required')
        for path in self.recovery_paths.values(): account_path(path,self.target['account'])
        if self.recovery_file.exists(): raise RuntimeError('Existing session must be recovered, not replaced')
        self.recovery_owned = True
        durable_private(self.recovery_file,
            {'target': self.target, 'candidateRoot': self.candidate_root, 'origin': self.origin,
             'helperPath': self.path, 'keyHex': self.key.hex(), 'keySha256': hashlib.sha256(self.key).hexdigest(),
             'schedulerJournal': str(self.scheduler_journal), 'serverPaths': self.recovery_paths, 'state': 'upload_intent'})
        if self.client.exists(self.path): raise RuntimeError('Helper collision')
        config = {'target': self.target, 'candidateRoot': self.candidate_root,
                  'keySha256': hashlib.sha256(self.key).hexdigest(), 'cliBinary': self.cli_binary,
                  'processModel': self.process_model, 'recoveryPaths': self.recovery_paths}
        source = (Path(__file__).with_name('temporary_host_helper.php').read_text()
                  .replace('/*OPERATOR_CONFIG*/', "$operator = json_decode(base64_decode('" +
                           base64.b64encode(canonical(config)).decode() + "'), true, 32, JSON_THROW_ON_ERROR);"))
        self.installed = True  # journal ownership before a possibly lost upload response
        self.client.save_file(self.path, source)
        receipt = self._call('helper_identity', '', None, {})
        if receipt['payload'].get('helperSha256') != hashlib.sha256(source.encode()).hexdigest():
            raise RuntimeError('Uploaded helper byte identity mismatch')

    def install_hooks(self, approved_bootstrap_sha256):
        sources = {}
        root = Path(__file__).resolve().parents[1] / 'app'
        for relative in ('bootstrap.php', 'src/Infrastructure/Deployment/MaintenanceFence.php',
                         'src/Infrastructure/Config/EnvironmentLoader.php'):
            sources[relative] = base64.b64encode((root / relative).read_bytes()).decode()
        return self._call('install_hooks', secrets.token_hex(32), None,
            {'approvedBootstrapSha256': approved_bootstrap_sha256, 'sources': sources})

    def reconcile_hooks(self):
        return self._call('reconcile_hooks', secrets.token_hex(32), self.lease, {})

    def restore_hooks(self):
        return self._call('restore_hooks', secrets.token_hex(32), self.lease, {})

    def _call(self, action, challenge, lease, binding):
        if not self.installed: raise RuntimeError('Helper not installed')
        body = canonical({'action': action, 'challenge': challenge, 'lease': lease, 'binding': binding})
        if getattr(self, 'recovery_owned', False):
            recovery = json.loads(self.recovery_file.read_text())
            recovery.update(state='operation_intent', operation=json.loads(body))
            durable_private(self.recovery_file,recovery)
        url = self.origin + '/' + self.path[len(self.target['documentRoot'])+1:]
        response = self.transport.request('POST', url, {'Content-Type': 'application/json',
            'X-A777-Operator-Key': self.key.hex(), 'Cache-Control': 'no-store'}, body, 300)
        if response.status != 200: raise RuntimeError('Authenticated helper operation refused')
        if action == 'download': return response
        try: envelope = json.loads(response.body)
        except ValueError: raise RuntimeError('Invalid helper receipt') from None
        if set(envelope) != {'payload', 'signature'} or not hmac.compare_digest(
                hmac.new(self.key, canonical(envelope['payload']), hashlib.sha256).hexdigest(), envelope['signature']):
            raise RuntimeError('Helper receipt authentication failed')
        payload=envelope['payload']
        if payload.get('target') != self.target or payload.get('stage') != action or payload.get('challenge') != challenge: raise RuntimeError('Helper receipt scope mismatch')
        if lease is not None and payload.get('lease') != lease: raise RuntimeError('Helper lease mismatch')
        if getattr(self, 'recovery_owned', False):
            recovery.update(state='receipt_received', receipt=envelope)
            durable_private(self.recovery_file,recovery)
        return envelope

    def cron(self, function, parameters=None):
        payload = self.client._request_json('GET', '/json-api/cpanel', {
            'cpanel_jsonapi_user': self.client.config.username,
            'cpanel_jsonapi_apiversion': '2', 'cpanel_jsonapi_module': 'Cron',
            'cpanel_jsonapi_func': function, **(parameters or {})})
        result = payload.get('cpanelresult', {})
        if result.get('event', {}).get('result') != 1 or not isinstance(result.get('data'), list):
            raise RuntimeError('Scheduler API contract failed')
        rows = result['data']
        if any(row.get('status') not in (None, 1) or row.get('result') not in (None, 1) for row in rows):
            raise RuntimeError('Scheduler operation failed')
        return rows

    def suppress_cron(self):
        if self.scheduler_journal and self.scheduler_journal.exists():
            recovery = json.loads(self.scheduler_journal.read_text())
            if recovery.get('target') != self.target: raise RuntimeError('Scheduler recovery target mismatch')
            self.suppressed = recovery['suppressed']
            if recovery.get('intent'):
                actual = [r for r in self.cron('listcron') if 'command' in r]
                if cron_inventory(actual) != cron_inventory(recovery['intent']['after']):
                    raise RuntimeError('Unresolved removal intent; operator reconciliation required')
                recovery['intent'] = None
                durable_private(self.scheduler_journal, recovery)
        expected_inventory = None
        while True:
            rows = [r for r in self.cron('listcron') if 'command' in r]
            current_inventory = cron_inventory(rows)
            if expected_inventory is not None and current_inventory != expected_inventory:
                raise RuntimeError('Concurrent complete scheduler inventory edit')
            if any(r['command'] not in self.owned | self.unrelated for r in rows):
                raise RuntimeError('Unknown scheduler ownership; no DDL permitted')
            owned = [r for r in rows if r['command'] in self.owned]
            if not owned:
                self.remaining_cron = rows
                return
            row = max(owned, key=lambda r: int(r['count']))
            if self.scheduler_journal is None or self.scheduler_journal.is_symlink():
                raise RuntimeError('Private persistent scheduler intent journal required')
            self.suppressed.append(dict(row))  # retain before potentially lost response
            cron_inventory(rows)
            journal = self.scheduler_journal
            expected = [dict(r) for r in rows]
            expected.remove(row)
            durable_private(journal, {'target': self.target, 'suppressed': self.suppressed,
                'restored': False, 'intent': {'row': row, 'before': rows, 'after': expected}})
            before = [r for r in self.cron('listcron') if 'command' in r]
            if before != rows: raise RuntimeError('Concurrent scheduler selected row edit')
            error = None
            try: self.cron('remove_line', {'line': str(row['count'])})
            except Exception as caught: error = caught
            after = [r for r in self.cron('listcron') if 'command' in r]
            if cron_inventory(after) != cron_inventory(expected):
                raise RuntimeError('Scheduler removal exact multiset readback failed') from error
            durable_private(journal, {'target': self.target, 'suppressed': self.suppressed,
                'restored': False, 'intent': None})
            self.remaining_cron = after
            expected_inventory = cron_inventory(after)

    def observe(self, stage, challenge, lease, binding):
        if stage == 'quiescence':
            rows = [r for r in self.cron('listcron') if 'command' in r]
            if any(r['command'] in self.owned or r['command'] not in self.unrelated for r in rows):
                raise RuntimeError('Scheduler suppression/ownership changed')
            if cron_inventory(rows) != cron_inventory(self.remaining_cron): raise RuntimeError('Retained scheduler inventory changed')
            response = self.transport.request('GET', self.origin + '/', {'Cache-Control':'no-store'}, None, 45)
            if response.status != 503 or response.body != b'Application maintenance in progress.': raise RuntimeError('Public web maintenance gate changed')
        return self._call(stage, challenge, lease, binding)

    def quiesce(self, challenge):
        self.suppress_cron()
        result = self._call('quiesce', challenge, None, {})
        self.lease = result['payload']['lease']
        response = self.transport.request('GET', self.origin + '/',
            {'Cache-Control': 'no-store'}, None, 45)
        if response.status != 503 or response.body != b'Application maintenance in progress.':
            raise RuntimeError('Real public web maintenance gate not observed')
        return self._call('quiescence', challenge, self.lease, {})

    def backup(self, challenge, lease):
        result = self._call('backup', challenge, lease, {})
        self.archive = result['payload']
        return result

    def download(self, remote_path, destination):
        if not self.archive or remote_path != self.archive['retainedPath']:
            raise RuntimeError('Unbound archive transfer')
        response = self._call('download', self.archive['challenge'], self.lease, {})
        data = response.body
        if len(data) != self.archive['bytes'] or len(data) > 1024**3 or hashlib.sha256(data).hexdigest() != self.archive['archiveSha256']:
            raise RuntimeError('Archive transfer integrity failed')
        with Path(destination).open('xb') as output: output.write(data)

    def candidate(self, action, challenge, lease, binding):
        if action not in ('status', 'migrate'): raise RuntimeError('Invalid candidate action')
        return self._call(action, challenge, lease, binding)

    def copy_active_tree(self, destination):
        import re
        from tools.production_migration import tree_hash
        data = self._call('active_tree', secrets.token_hex(32), None, {})['payload']
        destination = Path(destination)
        destination.mkdir(parents=True, exist_ok=False)
        for name, encoded in data['files'].items():
            if not re.fullmatch(r'\d{4}_[a-z0-9_]+\.sql', name):
                raise RuntimeError('Unsafe active SQL filename')
            (destination / name).write_bytes(base64.b64decode(encoded, validate=True))
        if tree_hash(destination) != data['activeTreeSha256']:
            raise RuntimeError('Copied active SQL tree mismatch')
        return destination

    def cleanup_helpers(self):
        if getattr(self, 'recovery_owned', False) or self.lease is not None or self.suppressed:
            raise RuntimeError('Recovery helper retained; absence cannot be claimed while ownership is active')
        if self.installed:
            if self.client.exists(self.path):
                self.client.trash(self.path)
            if self.client.exists(self.path): raise RuntimeError('Exact helper removal unproven')
            self.installed = False


def main(argv=None):
    """Explicit operator wiring; nothing contacts a host on import or --help."""
    import argparse
    import os
    from tools.cpanel_client import CpanelClient, CpanelConfig
    from tools.production_migration import MigrationPipeline, LocalRestore
    parser = argparse.ArgumentParser(description='Authenticated retained-maintenance migration operator')
    parser.add_argument('--operator-config', type=Path, required=True,
                        help='Nonsecret exact target/scheduler/local restore JSON')
    parser.add_argument('--execute', action='store_true', help='Explicitly authorize host changes')
    args = parser.parse_args(argv)
    if not args.execute: parser.error('--execute is required; no network operations performed')
    config = json.loads(args.operator_config.read_text())
    cp = config['cpanel']
    password = os.environ.get('A777_CPANEL_PASSWORD', '')
    client = CpanelClient(CpanelConfig(password=password, **cp))
    target = config['target']
    if client.config.username != target['account']:
        raise RuntimeError('Account identity mismatch')
    client.account_home()
    retained = Path(config['retainedDirectory']); retained.mkdir(parents=True, exist_ok=True)
    if any(p.is_symlink() for p in (retained, *retained.parents)): raise RuntimeError('Recovery directory links refused')
    if os.name == 'nt':
        import getpass
        import subprocess
        subprocess.run(['icacls', str(retained), '/inheritance:r', '/grant:r',
            getpass.getuser() + ':(OI)(CI)F'], check=True, capture_output=True)
    else: os.chmod(retained,0o700)
    key = secrets.token_bytes(32)
    helper = target['documentRoot'] + '/a777-operator-' + secrets.token_hex(24) + '.php'
    host = CpanelHostOperations(client, config['origin'], target, config['candidateRoot'],
        key, helper, config['ownedCron'], config['unrelatedCron'], cli_binary=config['cliBinary'],
        scheduler_journal=retained / ('scheduler-' + secrets.token_hex(16) + '.json'),
        process_model=config['processModel'], recovery_paths=config['recoveryPaths'])
    try:
        host.install()
        host.install_hooks(config['approvedActiveBootstrapSha256'])
        active = host.copy_active_tree(retained / ('active-sql-' + secrets.token_hex(16)))
        restore = LocalRestore(config['localPhp'], Path(config['localRepository']),
            {name: os.environ[name] for name in config['localEnvironmentNames']},
            Path(config['localFileRoot']))
        result = MigrationPipeline(host, restore, target, key, retained, config['expectedBuild']).run(
            active, Path(config['localCandidateMigrations']))
        print(json.dumps({'state': result['state']}))
    finally:
        # Retain authentication key/helper on every exit; removal is an explicit
        # recovery operation after lease and hook ownership review, never finally.
        pass
        # Scheduler entries and maintenance intentionally remain disabled. No
        # implicit restore after a failed backup, partial DDL or unknown readiness.


if __name__ == '__main__':
    try:
        main()
    except Exception:
        raise SystemExit('Operator operation failed; inspect retained recovery receipts. No automatic resume.')
