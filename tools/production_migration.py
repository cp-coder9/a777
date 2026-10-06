"""Operator-only migration orchestration. No network or environment credentials on import.

A hosting adapter must perform the HostOperations contract; no generic cPanel cron
adapter exists because disabling web traffic alone cannot establish write quiescence.
Receipts are authenticated observations, not CLI booleans. This library never rolls
back DDL by renaming files, never deletes retained backups, and never promotes files.
"""
from __future__ import annotations

import abc
import os
from dataclasses import dataclass
from enum import Enum


class HelperDisposition(str, Enum):
    REMOVED = 'removed'
    RETAINED_RECOVERY = 'retained_recovery'


@dataclass(frozen=True)
class HelperRecoveryOutcome:
    disposition: HelperDisposition
    helper_path: str
    identity: dict
    recovery: dict

import hashlib
import hmac
import json
import re
import secrets
import subprocess
import time
from pathlib import Path


def canonical(value):
    return json.dumps(value, sort_keys=True, separators=(',', ':'), ensure_ascii=True).encode()


def tree_hash(directory: Path) -> str:
    files = sorted(directory.glob('*.sql'))
    if not files or directory.is_symlink():
        raise RuntimeError('Active migration tree unavailable')
    entries = {}
    for file in files:
        if file.is_symlink() or not file.is_file() or not re.fullmatch(r'\d{4}_[a-z0-9_]+\.sql', file.name):
            raise RuntimeError('Unsafe migration tree')
        entries[file.name] = hashlib.sha256(file.read_bytes()).hexdigest()
    return hashlib.sha256(canonical(entries)).hexdigest()


class HostOperations(abc.ABC):
    """Trusted authenticated exact-origin host implementation, NOT user input.

    Every returned envelope is HMAC-SHA256 over canonical payload using a fresh
    session key provided out-of-band to the authenticated one-time helper. Payload
    includes challenge, target (account/domain/documentRoot/runtimeRoot/database),
    stage and lease. Adapter must reject redirects and inspect real DB identity.
    It must preserve runtime.php bytes and restore only owned scheduler entries.
    Quiesce must install a web write gate, stop owned dispatch and cron producers,
    drain already-running workers/requests, and read all controls back. Lease must
    be fenced (not automatically reopen writes on expiry). Backup calls actual
    BackupService::create with existing host PDO/storage/password/APP_KEY; no dumps
    or synthetic manifests. DDL calls actual candidate MigrationRunner methods.
    Unknown control/process ownership MUST fail closed before backup/DDL.
    """
    @abc.abstractmethod
    def observe(self, stage, challenge, lease, binding):
        """Read identity, active tree, runtime hash, controls, or candidate readiness."""

    @abc.abstractmethod
    def quiesce(self, challenge):
        """Return signed lease, expiresAt, web/dispatch/cron observations + drainedAt."""

    @abc.abstractmethod
    def backup(self, challenge, lease):
        """Return signed archive SHA, backupId, bytes and retained remote path."""

    @abc.abstractmethod
    def download(self, remote_path, destination):
        """Authenticated transfer without deleting retained encrypted host archive."""

    @abc.abstractmethod
    def candidate(self, action, challenge, lease, binding):
        """Actual status/migrate report; persist binding server-side before DDL."""

    @abc.abstractmethod
    def cleanup_helpers(self):
        """Delete ephemeral helpers, read back exact paths; raise on unknown/present."""


class LocalRestore:
    """Runs existing guarded RestoreService locally. Secrets stay in subprocess env.

    Environment must be explicitly supplied by operator, never copied from hosting.
    The PHP bridge independently applies DisposableDatabaseGuard before restore.
    """
    def __init__(self, php: str, repository: Path, environment: dict[str, str], file_root: Path):
        self.php, self.repository = php, repository.resolve()
        self.environment, self.file_root = dict(environment), file_root.resolve()
        if (environment.get('APP_ENV') != 'test'
                or environment.get('ARCHITEX_ALLOW_DISPOSABLE_DB') != '1'
                or environment.get('DB_HOST') not in ('127.0.0.1', '::1')
                or not re.fullmatch(r'architex_task[a-z0-9]+_[a-f0-9]{8}_test', environment.get('DB_NAME', ''))):
            raise RuntimeError('Restore requires explicitly configured task-owned loopback target')
        if self.file_root.exists() and (self.file_root.is_symlink() or any(self.file_root.iterdir())):
            raise RuntimeError('Restore file root must be empty and not a link')

    def restore(self, archive, migrations):
        before = tree_hash(migrations)
        completed = subprocess.run([self.php, str(self.repository / 'tools' / 'production_restore.php'),
            str(archive.resolve()), str(migrations.resolve()), str(self.file_root)],
            env=self.environment, capture_output=True, text=True, check=False)
        if completed.returncode != 0:
            raise RuntimeError('Guarded local restore failed; retained backup required')
        try:
            report = json.loads(completed.stdout)
        except ValueError:
            raise RuntimeError('Restore returned no valid receipt') from None
        if tree_hash(migrations) != before or report.get('database') != self.environment['DB_NAME']:
            raise RuntimeError('Restore target/tree changed')
        return {**report, 'activeTreeSha256': before,
                'archiveSha256': hashlib.sha256(archive.read_bytes()).hexdigest()}


class MigrationPipeline:
    def __init__(self, host: HostOperations, restore: LocalRestore, target: dict,
                 receipt_key: bytes, retained_directory: Path, expected_build: dict):
        if len(receipt_key) != 32:
            raise RuntimeError('A fresh 32-byte authenticated helper session key is required')
        if set(target) != {'account', 'domain', 'documentRoot', 'runtimeRoot', 'database'} or not all(isinstance(v, str) and v for v in target.values()):
            raise RuntimeError('Exact target identity required')
        self.host, self.restore, self.target, self.key = host, restore, dict(target), receipt_key
        self.directory, self.expected_build = retained_directory, expected_build
        self.challenge, self.lease = secrets.token_hex(32), None
        self.events, self.binding = [], {}
        self.ddl_started = False

    def receipt(self, envelope, stage):
        if not isinstance(envelope, dict) or set(envelope) != {'payload', 'signature'}:
            raise RuntimeError('Authenticated receipt required')
        data = envelope['payload']
        expected = hmac.new(self.key, canonical(data), hashlib.sha256).hexdigest()
        if not isinstance(envelope['signature'], str) or not hmac.compare_digest(expected, envelope['signature']):
            raise RuntimeError('Receipt authentication failed')
        if data.get('challenge') != self.challenge or data.get('target') != self.target or data.get('stage') != stage:
            raise RuntimeError('Receipt target/challenge/stage mismatch')
        if self.lease is not None and data.get('lease') != self.lease:
            raise RuntimeError('Maintenance lease mismatch')
        for name, value in self.binding.items():
            if data.get(name) != value:
                raise RuntimeError('Recovery binding mismatch: ' + name)
        self.events.append(data)
        self.persist('in_progress')
        return data

    def persist(self, state):
        from tools.cpanel_host_operations import durable_private
        if any(p.is_symlink() for p in (self.directory, *self.directory.parents)):
            raise RuntimeError('Recovery metadata ancestry links refused')
        self.directory.mkdir(parents=True, exist_ok=True)
        if os.name == 'nt' and not getattr(self, '_metadata_protected', False):
            script = "$ErrorActionPreference='Stop';$p='" + str(self.directory).replace("'", "''") + "';$d=Get-Item -LiteralPath $p;$s=$d.GetAccessControl('Access');$s.SetAccessRuleProtection($true,$false);foreach($r in @($s.Access)){$s.RemoveAccessRuleSpecific($r)};$id=[System.Security.Principal.WindowsIdentity]::GetCurrent().User;$r=New-Object System.Security.AccessControl.FileSystemAccessRule($id,'FullControl','ContainerInherit,ObjectInherit','None','Allow');$s.AddAccessRule($r);$d.SetAccessControl($s);$v=$d.GetAccessControl('Access');if(!$v.AreAccessRulesProtected -or @($v.Access).Count -ne 1){throw 'Recovery ACL verification failed'}"
            subprocess.run(['powershell.exe', '-NoProfile', '-NonInteractive', '-Command', script], check=True, capture_output=True)
            self._metadata_protected = True
        path = self.directory / ('migration-' + self.challenge + '.json')
        durable_private(path, {'state': state, 'target': self.target, 'challenge': self.challenge,
            'lease': self.lease, 'binding': self.binding, 'ddlMayHaveCommitted': self.ddl_started,
            'databaseRollbackPerformed': False, 'events': self.events})

    def retained_helper(self):
        """Bridge the concrete adapter's deliberate retention without trusting error text.

        No local key metadata is read. Authenticated exact-path helper and persisted
        server ownership are independently read back; this is NOT cleanup success.
        """
        from tools.cpanel_host_operations import CpanelHostOperations, account_path
        host = self.host
        if not isinstance(host, CpanelHostOperations) or not getattr(host, 'recovery_owned', False):
            raise RuntimeError('Unexpected helper retention')
        path = account_path(host.path, self.target['account'])
        if not path.startswith(self.target['documentRoot'] + '/') or not host.client.exists(path):
            raise RuntimeError('Retained helper exact path unproven')
        local = host.recovery_file
        if local is None or not local.is_file() or any(p.is_symlink() for p in (local, *local.parents)):
            raise RuntimeError('Retained recovery metadata unavailable')
        # The concrete install verified the protected directory before recording ownership.
        if os.name != 'nt' and (local.stat().st_mode & 0o077 or local.parent.stat().st_mode & 0o077):
            raise RuntimeError('Retained recovery metadata is not private')
        def authenticated(envelope, stage):
            data = envelope['payload']
            signature = hmac.new(self.key, canonical(data), hashlib.sha256).hexdigest()
            if (not hmac.compare_digest(signature, envelope['signature']) or
                    data.get('target') != self.target or data.get('challenge') != self.challenge or
                    data.get('stage') != stage or data.get('lease') != self.lease):
                raise RuntimeError('Retained helper authentication/scope failed')
            return data
        identity = authenticated(host.observe('helper_identity', self.challenge, self.lease, {}), 'helper_identity')
        if not re.fullmatch('[a-f0-9]{64}', identity.get('helperSha256', '')):
            raise RuntimeError('Retained helper identity unavailable')
        import base64
        config = {'target': host.target, 'candidateRoot': host.candidate_root,
            'keySha256': hashlib.sha256(self.key).hexdigest(), 'cliBinary': host.cli_binary,
            'processModel': host.process_model, 'recoveryPaths': host.recovery_paths}
        source = Path(__file__).with_name('temporary_host_helper.php').read_text().replace(
            '/*OPERATOR_CONFIG*/', "$operator = json_decode(base64_decode('" +
            base64.b64encode(canonical(config)).decode() + "'), true, 32, JSON_THROW_ON_ERROR);")
        if identity['helperSha256'] != hashlib.sha256(source.encode()).hexdigest():
            raise RuntimeError('Retained helper byte identity mismatch')
        recovery = authenticated(host.observe('recovery_state', self.challenge, self.lease, {}), 'recovery_state')
        server = recovery.get('recovery', {})
        if self.lease is not None:
            fence = recovery.get('fence') or {}
            if (server.get('challenge') != self.challenge or server.get('lease') != self.lease or
                    fence.get('owner') != hashlib.sha256(self.lease.encode()).hexdigest() or
                    fence.get('fingerprint') != hashlib.sha256(canonical(self.target)).hexdigest()):
                raise RuntimeError('Retained recovery ownership mismatch')
        known = {**server.get('identity', {}), **server.get('backup', {}), **server.get('restoreAuthorization', {})}
        if any(known.get(k) != v for k, v in self.binding.items()):
            raise RuntimeError('Retained recovery binding mismatch')
        return HelperRecoveryOutcome(HelperDisposition.RETAINED_RECOVERY, path, identity, recovery)

    def check_lease(self):
        data = self.receipt(self.host.observe('quiescence', self.challenge, self.lease, self.binding), 'quiescence')
        if (not isinstance(data.get('expiresAt'), int) or data['expiresAt'] <= time.time()
                or data.get('web') != 'blocked' or data.get('dispatch') != 'stopped'
                or data.get('cron') != 'stopped' or not isinstance(data.get('drainedAt'), int)
                or data['drainedAt'] > time.time()):
            raise RuntimeError('Write quiescence unproven/expired')

    def run(self, active_migrations: Path, candidate_migrations: Path):
        state = 'recovery_required'
        primary = None
        try:
            identity = self.receipt(self.host.observe('identity', self.challenge, None, {}), 'identity')
            runtime = identity.get('runtimeSha256')
            if not isinstance(runtime, str) or not re.fullmatch('[a-f0-9]{64}', runtime):
                raise RuntimeError('Host runtime fingerprint unavailable')
            self.binding['runtimeSha256'] = runtime
            active_hash = tree_hash(active_migrations)
            if identity.get('activeTreeSha256') != active_hash:
                raise RuntimeError('Local restore migrations do not match active host tree')
            self.binding['activeTreeSha256'] = active_hash
            candidate_hash = tree_hash(candidate_migrations)
            if identity.get('candidateTreeSha256') != candidate_hash:
                raise RuntimeError('Candidate migration tree identity mismatch')
            self.binding['candidateTreeSha256'] = candidate_hash
            lease = self.receipt(self.host.quiesce(self.challenge), 'quiescence')
            if not isinstance(lease.get('lease'), str) or not re.fullmatch('[a-f0-9]{64}', lease['lease']):
                raise RuntimeError('Fenced maintenance lease required')
            self.lease = lease['lease']
            self.check_lease()
            backup = self.receipt(self.host.backup(self.challenge, self.lease), 'backup')
            for field in ('archiveSha256', 'backupId'):
                if not isinstance(backup.get(field), str) or not re.fullmatch('[a-f0-9]{64}', backup[field]):
                    raise RuntimeError('Backup integrity receipt unavailable')
            if not isinstance(backup.get('bytes'), int) or backup['bytes'] < 1 or not isinstance(backup.get('retainedPath'), str) or not backup['retainedPath']:
                raise RuntimeError('Retained backup receipt unavailable')
            archive = self.directory / (backup['archiveSha256'] + '.axbackup')
            if archive.exists():
                raise RuntimeError('Refusing overwrite of retained archive')
            self.host.download(backup['retainedPath'], archive)
            if archive.is_symlink() or archive.stat().st_size != backup['bytes'] or hashlib.sha256(archive.read_bytes()).hexdigest() != backup['archiveSha256']:
                raise RuntimeError('Transferred archive integrity mismatch')
            self.binding.update({name: backup[name] for name in ('archiveSha256', 'backupId')})
            result = self.restore.restore(archive, active_migrations)
            if any(result.get(k) != self.binding[k] for k in ('archiveSha256', 'backupId', 'activeTreeSha256')):
                raise RuntimeError('Restore recovery receipt mismatch')
            if (not isinstance(result.get('manifestSha256'), str)
                    or not re.fullmatch('[a-f0-9]{64}', result['manifestSha256'])
                    or any(type(result.get(k)) is not int or result[k] < 0 for k in ('rowCount', 'fileCount'))):
                raise RuntimeError('Restore manifest/count receipt unavailable')
            self.events.append({'stage': 'local_restore', **result})
            self.binding['restoreReceiptSha256'] = hashlib.sha256(canonical(result)).hexdigest()
            self.persist('recoverability_proven')
            self.check_lease()
            self.receipt(self.host.candidate('status', self.challenge, self.lease, self.binding), 'status')
            self.check_lease()
            self.ddl_started = True
            self.persist('ddl_started')  # write intent before any potentially committing DDL
            self.receipt(self.host.candidate('migrate', self.challenge, self.lease, self.binding), 'migrate')
            status = self.receipt(self.host.candidate('status', self.challenge, self.lease, self.binding), 'status')
            report = status.get('report')
            if not isinstance(report, dict) or report.get('pendingCount') != 0 or report.get('pendingVersions') != []:
                raise RuntimeError('Candidate migration status not current')
            self.check_lease()
            ready = self.receipt(self.host.observe('readiness', self.challenge, self.lease, self.binding), 'readiness')
            from tools.deploy_php_ftps import readiness_is_ready
            if not readiness_is_ready(ready.get('readiness')) or ready.get('build') != self.expected_build:
                raise RuntimeError('Candidate readiness/build identity failed')
            state = 'migration_verified_maintenance_retained'
        except BaseException as error:
            primary = error
        finally:
            try:
                outcome = self.host.cleanup_helpers()  # None means contractual exact absence readback.
                if outcome is not None:
                    raise RuntimeError('Unexpected cleanup return; absence unproven')
                self.events.append({'stage': 'helper_cleanup', 'result': 'absence_readback',
                                    'disposition': HelperDisposition.REMOVED.value})
            except BaseException as cleanup:
                try:
                    outcome = self.retained_helper()
                    if not isinstance(outcome, HelperRecoveryOutcome) or outcome.disposition is not HelperDisposition.RETAINED_RECOVERY:
                        raise RuntimeError('Invalid retained recovery outcome')
                    self.events.append({'stage': 'helper_cleanup', 'result': 'authenticated_retained_recovery',
                        'disposition': outcome.disposition.value, 'helperPath': outcome.helper_path,
                        'identityReceipt': outcome.identity, 'recoveryReceipt': outcome.recovery})
                    if primary is None:
                        state = 'migration_verified_recovery_helper_retained'
                except BaseException:
                    state = 'recovery_required'
                    self.events.append({'stage': 'helper_cleanup', 'result': 'unproven'})
                    if primary is None:
                        primary = RuntimeError('Helper removal/readback failed; maintenance and backup retained')
                    else:
                        primary.add_note('Helper removal/recovery readback also failed; ownership retained')
            try:
                self.persist(state)
            except BaseException as reporting:
                if primary is None:
                    primary = reporting
                else:
                    primary.add_note('Recovery report persistence also failed')
        if primary is not None:
            raise primary
        # No promotion or resumption capability is exported by this pipeline.
        return {'state': state, 'binding': dict(self.binding), 'lease': self.lease,
                'helperDisposition': self.events[-1]['disposition']}
