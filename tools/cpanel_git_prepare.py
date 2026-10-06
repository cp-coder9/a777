"""Explicit Git preparation and private-token native read-only preflight orchestration."""
import base64
import fnmatch
import hashlib
import json
import os
import re
import time
from urllib.request import Request, build_opener, HTTPSHandler
from cpanel_client import CpanelError, RejectRedirects

HOME = '/home/archite4'
ROOT = HOME + '/repositories/a777'
SSH = HOME + '/.ssh'
KEY = SSH + '/a777-deploy-key'
URL = 'git@a777-github:cp-coder9/a777.git'
PIN = 'SHA256:+DiY3wvvV6TuJJhbpZisF/zLDA0zPMSvHdkr4UvCOqU'
BLOCK = ('# BEGIN a777 owned GitHub alias\nHost a777-github\n'
         '  HostName github.com\n  User git\n  IdentityFile ' + KEY + '\n'
         '  IdentitiesOnly yes\n  StrictHostKeyChecking yes\n'
         '  HostKeyAlias github.com\n  HostKeyAlgorithms ssh-ed25519\n'
         '  UserKnownHostsFile ' + SSH + '/known_hosts\n'
         '# END a777 owned GitHub alias\n')


def fingerprint(public, required_rsa_bits=None):
    if not isinstance(public, str) or not public.isascii() or len(public.strip().splitlines()) != 1 or any(ord(c) < 32 and c not in '\n\r\t' for c in public) or 'PRIVATE KEY' in public:
        raise CpanelError('Invalid owned public key.')
    parts = public.strip().split()
    if len(parts) < 2 or parts[0] not in ('ssh-ed25519', 'ssh-rsa'):
        raise CpanelError('Invalid owned public key.')
    try:
        raw = base64.b64decode(parts[1], validate=True)
        offset = 0
        def field():
            nonlocal offset
            if offset + 4 > len(raw):
                raise ValueError()
            size = int.from_bytes(raw[offset:offset + 4], 'big')
            offset += 4
            if not size or offset + size > len(raw):
                raise ValueError()
            value = raw[offset:offset + size]
            offset += size
            return value
        if field().decode('ascii') != parts[0]:
            raise ValueError()
        if parts[0] == 'ssh-ed25519':
            if len(field()) != 32:
                raise ValueError()
        else:
            numbers = [field(), field()]
            for number in numbers:
                if number[0] & 128 or (len(number) > 1 and number[0] == 0 and not number[1] & 128):
                    raise ValueError()
            exponent, modulus = [int.from_bytes(n, 'big') for n in numbers]
            if exponent < 3 or exponent % 2 == 0 or modulus.bit_length() < 2048 or modulus % 2 == 0:
                raise ValueError()
        if required_rsa_bits and (parts[0] != 'ssh-rsa' or modulus.bit_length() != required_rsa_bits):
            raise ValueError()
        if offset != len(raw):
            raise ValueError()
    except Exception:
        raise CpanelError('Invalid owned public key.') from None
    return 'SHA256:' + base64.b64encode(hashlib.sha256(raw).digest()).decode().rstrip('=')


def github_host_key():
    # No cPanel/GitHub credentials are attached to this independent HTTPS request.
    opener = build_opener(HTTPSHandler(), RejectRedirects())
    with opener.open(Request('https://api.github.com/meta', headers={'Accept': 'application/vnd.github+json', 'User-Agent': 'a777-git-preparation'}), timeout=30) as response:
        keys = json.load(response)['ssh_keys']
    for key in keys:
        if key.startswith('ssh-ed25519 ') and fingerprint(key) == PIN:
            return 'github.com ' + key + '\n'
    raise CpanelError('Official GitHub host key fingerprint mismatch.')


def authorize(client):
    c = client.config
    if (c.host, c.port, c.username, c.account_home, c.allowed_root) != ('cp73.domains.co.za', 2083, 'archite4', HOME, HOME) or not c.api_token:
        raise CpanelError('Exact account token configuration required.')
    data = client._uapi('Variables', 'get_user_information', {'name': 'home'})
    if not isinstance(data, dict) or data.get('home') != HOME:
        raise CpanelError('Account home mismatch.')


def entries(client, directory):
    data = client._uapi('Fileman', 'list_files', {'dir': directory, 'show_hidden': '1', 'include_permissions': '1'})
    if isinstance(data, dict) and isinstance(data.get('files'), list) and isinstance(data.get('dirs'), list):
        rows = data['files'] + data['dirs']
    elif isinstance(data, list):
        rows = data
    else:
        raise CpanelError('Invalid file inventory.')
    rows = [dict(row, name=row.get('name', row.get('file'))) for row in rows if isinstance(row, dict)]
    return rows


def exists(client, path):
    parent, name = path.rsplit('/', 1)
    matches = [row for row in entries(client, parent) if row.get('name') == name]
    if len(matches) > 1 or any(row.get('type') in ('link', 'symlink') for row in matches):
        raise CpanelError('Target path is ambiguous or symlinked; refused.')
    return bool(matches)


def read(client, path):
    if path == KEY:
        raise CpanelError('Private key retrieval forbidden.')
    directory, name = path.rsplit('/', 1)
    data = client._uapi('Fileman', 'get_file_content', {'dir': directory, 'file': name, 'from_charset': 'US-ASCII', 'to_charset': 'US-ASCII', 'update_html_document_encoding': '0'})
    content = data.get('content') if isinstance(data, dict) else None
    if not isinstance(content, str) or not content.isascii():
        raise CpanelError('ASCII file preservation contract unavailable; refused.')
    return content


def chmod(client, path, mode):
    if path not in (SSH, KEY, KEY + '.pub', SSH + '/config', SSH + '/known_hosts'):
        raise CpanelError('Unknown SSH mutation path.')
    client._api2('fileop', {'op': 'chmod', 'sourcefiles': path[len(HOME)+1:], 'doubledecode': '0', 'metadata': mode})
    parent, name = path.rsplit('/', 1)
    row = next((r for r in entries(client, parent) if r.get('name') == name), {})
    permissions = str(row.get('nicemode', '')).lstrip('0')
    if permissions != mode.lstrip('0'):
        raise CpanelError('Remote permission read-back failed.')


def append_config(original):
    if BLOCK in original:
        if original.count(BLOCK) != 1 or not original.endswith(BLOCK):
            raise CpanelError('Owned alias placement collision.')
        append_config(original[:-len(BLOCK)])
        return original
    if 'a777-github' in original or '# BEGIN a777' in original:
        raise CpanelError('SSH alias collision; refused.')
    # Earlier wildcard directives win in OpenSSH. Refuse instead of changing user policy.
    for line in original.splitlines():
        words = line.strip().split()
        if not words or words[0].startswith('#'):
            continue
        if words[0].lower() in ('include', 'match'):
            raise CpanelError('Complex SSH configuration requires review.')
        if words[0].lower() == 'host' and any(fnmatch.fnmatchcase('a777-github', p.lower()) for p in words[1:] if not p.startswith('!')):
            raise CpanelError('Existing SSH wildcard policy collision; refused.')
    return original + ('' if not original or original.endswith('\n') else '\n') + BLOCK


def preserve_append(client, path, original, updated):
    if original != updated:
        # Re-read immediately before saving: refuse an observed concurrent edit.
        now = read(client, path) if exists(client, path) else ''
        if now != original:
            raise CpanelError('Concurrent SSH file change; refused.')
        client.save_file(path, updated)
    if read(client, path) != updated:
        raise CpanelError('SSH file read-back mismatch.')
    chmod(client, path, '0600')


class NativeKeyFailure(CpanelError):
    """Locally selected safe stage/state; provider reasons are never retained."""
    def __init__(self, stage, code, state):
        super().__init__('Native SSH key preparation refused.')
        self.stage, self.code, self.state = stage, code, state


def native_inventory(client):
    try:
        rows = client.api2('SSH', 'listkeys', {})
    except Exception:
        raise NativeKeyFailure('SSH/listkeys', 'native_ssh_inventory_unavailable', 'unknown') from None
    if not isinstance(rows, list) or any(not isinstance(row, dict) for row in rows):
        raise NativeKeyFailure('SSH/listkeys', 'native_ssh_inventory_contract', 'unknown')
    matches = []
    for row in rows:
        names = (row.get('name'), row.get('key'))
        if any(name in ('a777-deploy-key', 'a777-deploy-key.pub') for name in names) or row.get('file') in (KEY, KEY + '.pub'):
            if row.get('file') not in (KEY, KEY + '.pub') or not any(name in ('a777-deploy-key', 'a777-deploy-key.pub') for name in names):
                raise NativeKeyFailure('SSH/listkeys', 'native_ssh_inventory_collision', 'unknown')
            matches.append(row)
    if len({row['file'] for row in matches}) != len(matches):
        raise NativeKeyFailure('SSH/listkeys', 'native_ssh_inventory_collision', 'unknown')
    return matches


def prepare_key(client, public_path, expected=None):
    authorize(client)
    native_inventory(client)  # Discover SSH capability before any mutation.
    if not exists(client, SSH):
        client.mkdir(SSH, '0700')
    chmod(client, SSH, '0700')
    original = read(client, SSH + '/config') if exists(client, SSH + '/config') else ''
    updated = append_config(original)
    known = read(client, SSH + '/known_hosts') if exists(client, SSH + '/known_hosts') else ''
    host = github_host_key()
    # Do not replace user trust entries, including hashed ones. Dedicated ed25519 pin is appended.
    for line in known.splitlines():
        parts = line.split()
        if len(parts) >= 3 and 'github.com' in parts[0].split(',') and parts[1] == 'ssh-ed25519' and fingerprint(' '.join(parts[1:3])) != PIN:
            raise CpanelError('Existing GitHub host key collision; refused.')
    known_updated = known if host.strip() in known.splitlines() else known + ('' if not known or known.endswith('\n') else '\n') + host
    key_exists = exists(client, KEY)
    pub_exists = exists(client, KEY + '.pub')
    listed = native_inventory(client)
    if key_exists != pub_exists or (listed and not key_exists) or (key_exists and not listed) or (key_exists and not expected):
        raise CpanelError('Owned key collision: expected fingerprint required; no overwrite.')
    if not key_exists:
        if expected:
            raise CpanelError('Expected owned key absent; refusing replacement.')
        # Recheck both inventories immediately before generating; never overwrite a partial pair.
        if native_inventory(client) or exists(client, KEY) or exists(client, KEY + '.pub'):
            raise CpanelError('Owned key collision: expected fingerprint required; no overwrite.')
        try:
            data = client.api2('SSH', 'genkey', {'bits': '4096', 'name': 'a777-deploy-key', 'pass': '', 'type': 'rsa'})
            if not isinstance(data, list) or len(data) != 1 or not isinstance(data[0], dict) or data[0].get('result') not in (1, '1'):
                raise CpanelError('Native generation contract failed.')
        except Exception:
            # Lost response may have generated a pair. Never retry, delete, or print reason.
            raise NativeKeyFailure('SSH/genkey', 'native_ssh_generation_unavailable', 'possibly_generated') from None
    try:
        verified_inventory = native_inventory(client)
        if not verified_inventory or any(row.get('file') == KEY and row.get('haspub') not in (1, '1', True) for row in verified_inventory) or not exists(client, KEY) or not exists(client, KEY + '.pub'):
            raise CpanelError('Native pair read-back incomplete.')
        public = read(client, KEY + '.pub')
        actual = fingerprint(public)
        if expected and actual != expected:
            raise CpanelError('Owned public key fingerprint collision.')
        if not key_exists:
            fingerprint(public, required_rsa_bits=4096)
        chmod(client, KEY, '0600')
        chmod(client, KEY + '.pub', '0600')
        if read(client, KEY + '.pub') != public:
            raise CpanelError('Public key read-back mismatch.')
        # Public recovery artifact remains safe even if SSH config preparation fails next.
        public_path.write_text(public, encoding='ascii')
    except Exception:
        raise NativeKeyFailure('SSH/public_readback', 'native_ssh_key_verification_refused', 'existing' if key_exists else 'generated') from None
    try:
        preserve_append(client, SSH + '/known_hosts', known, known_updated)
        preserve_append(client, SSH + '/config', original, updated)
    except Exception:
        raise NativeKeyFailure('SSH/config_readback', 'native_ssh_configuration_incomplete', 'existing' if key_exists else 'generated') from None
    return {'mode': 'prepare-key', 'status': 'prepared', 'key_generation': 'cpanel_native', 'api_operation': 'SSH/genkey' if not key_exists else 'SSH/listkeys', 'public_key_fingerprint': actual, 'runtime_deployed': False}


def inventory(client):
    rows = client._uapi('VersionControl', 'retrieve', {})
    if not isinstance(rows, list) or any(not isinstance(r, dict) for r in rows):
        raise CpanelError('Invalid Git inventory.')
    matches = [r for r in rows if r.get('repository_root') == ROOT]
    if len(matches) > 1:
        raise CpanelError('Repository root collision.')
    row = matches[0] if matches else None
    if row and (row.get('name') != 'a777-production' or row.get('type') != 'git' or row.get('source_repository') != {'remote_name': 'origin', 'url': URL}):
        raise CpanelError('Repository identity collision; refused.')
    return row


def tasks(row):
    return [{'id': t.get('id') if isinstance(t.get('id'), str) and re.fullmatch(r'[A-Za-z0-9/-]{1,128}', t['id']) else None, 'action': t.get('action') if t.get('action') in ('create', 'update', 'pull') else None} for t in row.get('tasks', []) if isinstance(t, dict)]


def queue_errors(client, row):
    errors = []
    for task in row.get('tasks', []):
        if not isinstance(task, dict):
            continue
        args = task.get('args') or {}
        path = args.get('log_file') if isinstance(args, dict) else None
        if args.get('repository_root') != ROOT or not isinstance(path, str) or not re.fullmatch(re.escape(HOME) + r'/\.cpanel/logs/vc_[0-9.]+_git_(?:create|update|pull)\.log', path):
            continue
        try:
            text = read(client, path).lower()
            for marker, category in [('permission denied', 'ssh_authentication_failed'), ('host key verification failed', 'host_key_verification_failed'), ('repository not found', 'remote_repository_unavailable'), ('fatal:', 'git_operation_failed')]:
                if marker in text and category not in errors:
                    errors.append(category)
        except Exception:
            errors.append('task_log_unavailable')
    return errors or ['no_error_observed_or_task_log_unavailable']


def clone(client, expected, attempts=24, pause=5):
    authorize(client)
    config = read(client, SSH + '/config')
    append_config(config)  # Validate prior directives even when the owned block exists.
    if not expected or fingerprint(read(client, KEY + '.pub')) != expected or BLOCK not in config:
        raise CpanelError('Prepared key fingerprint and owned alias required.')
    row = inventory(client)
    queued = []
    if row is None:
        if not exists(client, HOME + '/repositories'):
            client.mkdir(HOME + '/repositories', '0700')
        if exists(client, ROOT):
            raise CpanelError('Unmanaged repository path collision; refused.')
        result = client._uapi('VersionControl', 'create', {'repository_root': ROOT, 'name': 'a777-production', 'type': 'git', 'source_repository': json.dumps({'remote_name': 'origin', 'url': URL})})
        queued = tasks(result if isinstance(result, dict) else {})
    # Clone completion must be read back before pulling master.
    pulled = False
    for attempt in range(attempts):
        row = inventory(client)
        if row and row.get('branch') and not row.get('tasks') and not pulled:
            result = client._uapi('VersionControl', 'update', {'repository_root': ROOT, 'branch': 'master'})
            queued += tasks(result if isinstance(result, dict) else {})
            pulled = True
            row = inventory(client)
        commit = (row or {}).get('last_update') or {}
        identifier = commit.get('identifier')
        if pulled and row.get('branch') == 'master' and not row.get('tasks') and isinstance(identifier, str) and re.fullmatch(r'[0-9a-f]{40}', identifier):
            return {'mode': 'clone', 'status': 'cloned_and_pulled', 'root': ROOT, 'branch': 'master', 'commit': identifier, 'tasks': [], 'runtime_deployed': False}
        if attempt + 1 < attempts:
            time.sleep(pause)
    return {'mode': 'clone', 'status': 'queued_or_incomplete', 'root': ROOT, 'tasks': tasks(row or {}) or queued, 'queue_errors': queue_errors(client, row or {}), 'runtime_deployed': False}


DEPLOY_ROOT = HOME + '/.a777-deploy'
PLAN = DEPLOY_ROOT + '/operator-plan.json'
DEPLOY_KEY_PIN = 'SHA256:T+6DmXaw3OeXcygLPc9tkeSt70j8dvHnCDYOV7bCras'
BOOL_FIELDS = frozenset(('sourceUnderPublicHtml', 'sourceStorageUnderPublicHtml', 'sourceStoragePrivacyUnproven', 'storageExists', 'storageCanonical', 'storageOutsidePublic', 'archiveOutsideStorage',
    'migrationAuthorized', 'backupAuthorized', 'promotionAuthorized', 'appuserHasScopedOnly',
    'appuserGrantsInspectionAvailable', 'accountPhpWorkerInventoryAvailable', 'managedRestoreSourceBound', 'sourceConfigPinned', 'databaseConfigParsed', 'databaseHostLocal', 'databaseNameMatches',
    'databaseUserInventoryMatches', 'databaseInventoryMatches', 'pdoDatabaseMatches', 'pdoUserMatches',
    'databaseDdlTouched', 'liveFilesTouched', 'zipAvailable', 'curlAvailable',
    'nativeRestoreCapabilityPresent', 'activeRootAvailable', 'documentRootAvailable',
    'runtimeConfigAvailable', 'liveDdlAuthorized', 'mysqlInventoryAvailable',
    'mysqlUserInventoryAvailable', 'cronInventoryAvailable', 'exclusiveGrantUserVisible',
    'sourceConnectionsDrainedAtInspection', 'runtimeConfigRegular', 'runtimeConfigNotSymlink',
    'runtimeConfigOwnerMatches', 'runtimeConfigPrivate600', 'privateConfigPermissionsChanged', 'runtimeConfigPermissionsTouched', 'runtimeConfigParentOwnerMatches', 'runtimeConfigCanonical', 'runtimeConfigAncestorSafe', 'runtimeConfigParentPermissionsTouched', 'privateConfigParentPermissionsChanged'))
GATES = frozenset(('managed_restore_class_path_mismatch','managed_restore_class_commit_mismatch','managed_restore_class_loading_mismatch','active_or_document_root_invalid', 'runtime_permission_identity_refused','runtime_permission_ancestry_refused','runtime_permission_canonical_refused','runtime_permission_ancestor_path_refused','runtime_permission_ancestor_write_refused','runtime_permission_ancestor_owner_refused','runtime_permission_parent_owner_refused','runtime_permission_parent_mode_refused','runtime_permission_parent_identity_refused','runtime_permission_parent_chmod_failed','runtime_permission_parent_readback_failed','runtime_permission_chmod_failed','runtime_permission_readback_failed','active_config_missing_or_not_private',
    'runtime_config_pin_mismatch', 'active_database_config_identity_mismatch', 'active_database_password_missing',
    'actual_source_pdo_identity_mismatch', 'native_uapi_failed', 'native_uapi_inventory_missing',
    'command_unavailable', 'command_failed', 'active_sql_root_invalid', 'active_sql_entry_invalid',
    'active_sql_empty', 'backup_storage_not_private_or_archive_inside_storage', 'native_preflight_failed',
    'unknown_writer_dispatch_exclusion_and_verified_promotion_not_implemented',
    'command_output_too_large', 'api_linux_identity_required', 'api_operator_identity_mismatch',
    'api_token_path_invalid', 'api_token_root_not_private', 'api_token_missing_or_path_invalid',
    'api_token_unreadable', 'api_token_not_private', 'api_token_invalid', 'api_http_failed',
    'api_response_too_large', 'api_response_invalid', 'api_uapi_failed', 'api_readonly_function_required',
    'api_curl_unavailable', 'api_transport_failed', 'api_account_home_mismatch'))


def owned_mode(client, path, mode, directory=False):
    parent, name = path.rsplit('/', 1)
    matches = [r for r in entries(client, parent) if r.get('name') == name]
    if len(matches) != 1 or matches[0].get('type') not in (('dir', 'directory') if directory else ('file',)) or str(matches[0].get('nicemode', '')).lstrip('0') != mode.lstrip('0'):
        raise CpanelError('Private deployment path permissions refused.')


TOKEN = DEPLOY_ROOT + '/cpanel-token'


def private_chmod(client, path):
    client._api2('fileop', {'op': 'chmod', 'sourcefiles': path[len(HOME)+1:],
                          'doubledecode': '0', 'metadata': '0600'})
    owned_mode(client, path, '0600')


def permission_only_safe(data):
    if any(data.get(k, False) is not False for k in ('migrationAuthorized', 'backupAuthorized', 'promotionAuthorized', 'liveDdlAuthorized')):
        return False
    if data.get('databaseDdlTouched') is not False:
        return False
    if data.get('liveFilesTouched') is False:
        return not data.get('runtimeConfigPermissionsTouched', False) and not data.get('runtimeConfigParentPermissionsTouched', False)
    return (data.get('liveFilesTouched') is True
        and data.get('runtimeConfigPermissionsTouched') is True
        and data.get('privateConfigPermissionsChanged') is True
        and all(data.get(k) is True for k in ('runtimeConfigRegular', 'runtimeConfigNotSymlink', 'runtimeConfigOwnerMatches', 'runtimeConfigPrivate600'))
        and data.get('runtimeConfigModeAfter') == '0600'
        and all(data.get(k) is True for k in ('runtimeConfigParentOwnerMatches', 'runtimeConfigCanonical', 'runtimeConfigAncestorSafe'))
        and data.get('runtimeConfigParentModeAfter') == '0700'
        and type(data.get('runtimeConfigParentPermissionsTouched')) is bool
        and data.get('privateConfigParentPermissionsChanged') is data.get('runtimeConfigParentPermissionsTouched')
        and isinstance(data.get('runtimeConfigParentModeBefore'), str)
        and re.fullmatch(r'0[0-7]{3}', data['runtimeConfigParentModeBefore']) is not None
        and (int(data['runtimeConfigParentModeBefore'], 8) & 0o022) == 0
        and data.get('runtimeConfigParentPermissionsTouched') is (data['runtimeConfigParentModeBefore'] != '0700')
        and isinstance(data.get('configContentUnchangedSHA'), str)
        and re.fullmatch(r'[a-f0-9]{64}', data['configContentUnchangedSHA']) is not None
        and type(data.get('runtimeConfigBytesUnchanged')) is int and data['runtimeConfigBytesUnchanged'] >= 0)


def permission_intent_guard(client, receipt):
    if receipt.get('liveFilesTouched') is not True:
        return
    name = receipt.get('runtimePermissionIntent')
    if not isinstance(name, str) or not re.fullmatch(r'runtime-permission-[a-f0-9]{16}\.json', name):
        raise CpanelError('Private permission intent reference refused.')
    owned_mode(client, DEPLOY_ROOT, '0700', True)
    path = DEPLOY_ROOT + '/' + name
    owned_mode(client, path, '0600')
    intent = json.loads(read(client, path))
    expected = {'format':'a777-runtime-permission-intent-v1', 'operation':'private0600',
                'commit':receipt.get('commit'), 'planSha256':receipt.get('planSha256'),
                'path':HOME + '/public_html/.a777-runtime-active/config/runtime.php',
                'device':receipt.get('runtimePermissionDevice'), 'inode':receipt.get('runtimePermissionInode'),
                'owner':receipt.get('runtimePermissionOwner'), 'bytes':receipt.get('runtimeConfigBytesUnchanged'),
                'beforeMode':receipt.get('runtimeConfigModeBefore'), 'sha256':receipt.get('configContentUnchangedSHA'),
                'parentPath':HOME + '/public_html/.a777-runtime-active/config',
                'parentDevice':receipt.get('runtimePermissionParentDevice'), 'parentInode':receipt.get('runtimePermissionParentInode'),
                'parentOwner':receipt.get('runtimePermissionParentOwner'), 'parentBeforeMode':receipt.get('runtimeConfigParentModeBefore'),
                'parentAfterMode':'0700', 'parentRepair':receipt.get('runtimeConfigParentPermissionsTouched')}
    if (intent != expected or any(type(expected[k]) is not int or expected[k] < 0 for k in ('device','inode','owner','bytes','parentDevice','parentInode','parentOwner'))
            or not permission_only_safe(receipt)):
        raise CpanelError('Private permission intent binding refused.')


REHEARSAL_STAGES = frozenset(('plan','account','repository','trustedclasses','runtimeconfig','source-storage','provider','source-engine','archive','create','grant','restore','compare'))
REHEARSAL_REASONS = frozenset(('unclassified','source_directory_required','source_public_storage_refused','plan_refused','linux_account_required','account_refused','private_directory_required','canonical_path_required','symlink_refused','clean_commit_required','git_unavailable','git_refused','pinned_config_refused','actual_config_mismatch','stable_key_refused','private_file_required','transactional_source_required','recovery_root_isolation_refused','source_transaction_active','recovery_directory_unavailable','archive_digest_unavailable','class_source_unavailable','untrusted_class_location','class_digest_mismatch','restore_report_mismatch','immutable_source_archive_changed','journal_unavailable','journal_identity_refused','journal_durability_refused','target_storage_unavailable','archive_unavailable','manifest_unavailable','archived_identity_mismatch','snapshot_table_mismatch','snapshot_content_mismatch','snapshot_evidence_absent'))
REHEARSAL_FUNCTIONS = frozenset(('run','account','canonical','privateDirectory','privateFile','privateTree','sourceTree','sourceDirectory','sourceDirectoryStat','sourceLocation','noLinks','git','trackedFile','trackedTree','trustedClass','append','manifest','assertSnapshotEquality'))
REHEARSAL_DIAGNOSTICS = ('sourceStorageUnderPublicHtml','sourceStoragePrivacyUnproven','sourceDirectoryMetadataOwnerOnly','failureStage','knownReason','failureFile','failureFunction','failureLine','repositoryMode','repositoryOwner','repositoryCanonical','storageMode','storageOwner','storageCanonical','gitCommandAvailability','fixedGitBinaryAvailability','lastCompletedStage')


def rehearsal_diagnostics_safe(data):
    for key in REHEARSAL_DIAGNOSTICS:
        if key not in data:
            continue
        value = data[key]
        if key in ('sourceDirectoryMetadataOwnerOnly','sourceStorageUnderPublicHtml','sourceStoragePrivacyUnproven'): valid = type(value) is bool
        elif key == 'failureStage': valid = isinstance(value, str) and value in REHEARSAL_STAGES
        elif key == 'knownReason': valid = isinstance(value, str) and value in REHEARSAL_REASONS
        elif key == 'failureFile': valid = value == 'CpanelRestoreRehearsal.php'
        elif key == 'failureFunction': valid = isinstance(value, str) and value in REHEARSAL_FUNCTIONS
        elif key == 'failureLine': valid = type(value) is int and 0 < value < 100000
        elif key.endswith('Mode'): valid = isinstance(value, str) and (value == 'unavailable' or re.fullmatch(r'0[0-7]{3}',value) is not None)
        elif key.endswith('Owner'): valid = value in ('account','other','unavailable')
        elif key == 'storageCanonical': valid = type(value) is bool or value in ('canonical','noncanonical')
        elif key.endswith('Canonical'): valid = value in ('canonical','noncanonical')
        elif key.endswith('Availability'): valid = value in ('available','unavailable')
        else: valid = value is None or value in ('backup-created','target-provisioned','rehearsal-verified')
        if not valid: return False
    return True


def rehearsal_safe(data, permissions_authorized=False):
    if not isinstance(data, dict) or data.get('format') != 'a777-cpanel-restore-rehearsal-v1' or data.get('mode') != 'restore-rehearsal':
        return False
    if any(data.get(k) is not False for k in ('sourceDatabaseDdlTouched', 'apacheConfigurationTouched', 'cronConfigurationTouched')):
        return False
    if any(data.get(k, False) is not False for k in ('migrationAuthorized', 'promotionAuthorized', 'liveDdlAuthorized')):
        return False
    if type(data.get('targetDatabaseDdlTouched')) is not bool or type(data.get('isolatedRehearsalPassed')) is not bool:
        return False
    if not rehearsal_diagnostics_safe(data):
        return False
    envelope = dict(data, databaseDdlTouched=False)
    if not permission_only_safe(envelope) or (data.get('liveFilesTouched') is not False and not permissions_authorized):
        return False
    if data.get('status') not in ('failed', 'capability-inspection-refused', 'rehearsal-verified'):
        return False
    if data['isolatedRehearsalPassed'] is not (data['status'] == 'rehearsal-verified'):
        return False
    if 'targetDatabase' in data and (not isinstance(data['targetDatabase'], str) or not re.fullmatch(r'archite4_ar_[a-f0-9]{16}_test', data['targetDatabase'])):
        return False
    if data['isolatedRehearsalPassed']:
        if not data['targetDatabaseDdlTouched'] or 'targetDatabase' not in data:
            return False
        if not isinstance(data.get('archiveSha256'), str) or not re.fullmatch(r'[a-f0-9]{64}', data['archiveSha256']):
            return False
        if not isinstance(data.get('backupId'), str) or not re.fullmatch(r'[A-Za-z0-9_-]{1,128}', data['backupId']):
            return False
        if any(type(data.get(k)) is not int or data[k] < 0 for k in ('rowCount','fileCount','tableCount')):
            return False
    return True


def rehearsal_receipts(client, commit, plan_sha, previous, permissions_authorized=False):
    reports = []
    for row in entries(client, DEPLOY_ROOT):
        name = row.get('name', '')
        if not isinstance(name, str) or not re.fullmatch(r'restore-rehearsal-' + re.escape(commit) + r'-[a-f0-9]{16}\.json', name) or name in previous:
            continue
        owned_mode(client, DEPLOY_ROOT + '/' + name, '0600')
        data = json.loads(read(client, DEPLOY_ROOT + '/' + name))
        if data.get('commit') != commit or data.get('planSha256') != plan_sha:
            raise CpanelError('Rehearsal receipt binding refused.')
        if not rehearsal_safe(data, permissions_authorized):
            raise CpanelError('Rehearsal source safety envelope refused.')
        permission_intent_guard(client, dict(data, databaseDdlTouched=False))
        safe = {'report_id':name, 'commit':commit, 'planSha256':plan_sha}
        for key in ('promotionAuthorized','migrationAuthorized','liveDdlAuthorized','status','sourceDatabaseDdlTouched','targetDatabaseDdlTouched','isolatedRehearsalPassed','liveFilesTouched','apacheConfigurationTouched','cronConfigurationTouched','targetDatabase','archiveSha256','backupId','rowCount','fileCount','tableCount'):
            if key in data: safe[key] = data[key]
        for key in REHEARSAL_DIAGNOSTICS:
            if key in data: safe[key] = data[key]
        reports.append(safe)
    if len(reports) > 1:
        raise CpanelError('Multiple fresh rehearsal receipts refused.')
    return reports


def deployment_inventory_guard(client):
    for row in entries(client, DEPLOY_ROOT):
        name = row.get('name')
        if isinstance(name, str) and re.fullmatch(r'recovery-[a-f0-9]{40}-[a-f0-9]{32}', name):
            # Metadata only: NEVER read journal.jsonl (contains archive password).
            authorize(client)  # exact authenticated provider account/home ownership context
            owned_mode(client, DEPLOY_ROOT + '/' + name, '0700', True)
            if ('owner' in row and row['owner'] != 'archite4') or ('user' in row and row['user'] != 'archite4'):
                raise CpanelError('Recovery owner context refused.')
            continue
        if isinstance(name, str) and re.fullmatch(r'restore-archite4_ar_[a-f0-9]{16}_test\.json', name):
            owned_mode(client, DEPLOY_ROOT + '/' + name, '0600')
            data = json.loads(read(client, DEPLOY_ROOT + '/' + name))
            allowed = {'account','target','source','user','binding','phase','create','grant','inventory','users','privileges'}
            if (not isinstance(data, dict) or not set(data) <= allowed
                    or data.get('account') != 'archite4' or data.get('source') != 'archite4_dev_rel1'
                    or not isinstance(data.get('target'), str) or name != 'restore-' + data['target'] + '.json'
                    or not re.fullmatch(r'archite4_ar_[a-f0-9]{16}_test', data['target'])
                    or data.get('phase') not in ('intent','created','grant-intent','granted','verified')
                    or not isinstance(data.get('binding'), dict) or data['binding'].get('source') != data['source']):
                raise CpanelError('Retained provider target metadata refused.')
            continue
        if isinstance(name, str) and re.fullmatch(r'rehearsal-intent-[a-f0-9]{64}\.json', name):
            owned_mode(client, DEPLOY_ROOT + '/' + name, '0600')
            data = json.loads(read(client, DEPLOY_ROOT + '/' + name))
            if (not isinstance(data, dict) or set(data) != {'format','commit','planSha256','sourceDatabaseDdlTouched','automaticResume'}
                    or data.get('format') != 'a777-rehearsal-intent-v1' or data.get('sourceDatabaseDdlTouched') is not False
                    or data.get('automaticResume') is not False or name != 'rehearsal-intent-' + data.get('planSha256','') + '.json'
                    or not re.fullmatch(r'[a-f0-9]{40}', data.get('commit',''))):
                raise CpanelError('Rehearsal intent refused.')
            continue
        if isinstance(name, str) and re.fullmatch(r'restore-rehearsal-[a-f0-9]{40}-[a-f0-9]{16}\.json', name):
            owned_mode(client, DEPLOY_ROOT + '/' + name, '0600')
            data = json.loads(read(client, DEPLOY_ROOT + '/' + name))
            if not rehearsal_safe(data, True) or not name.startswith('restore-rehearsal-' + data.get('commit','') + '-'):
                raise CpanelError('Retained rehearsal source safety refused.')
            continue
        if name == 'operator-plan.json':
            continue
        if name == 'deploy.lock':
            owned_mode(client, DEPLOY_ROOT + '/deploy.lock', '0600')
            continue
        if isinstance(name, str) and re.fullmatch(r'preflight-[a-f0-9]{40}-[a-f0-9]{16}\.json', name):
            path = DEPLOY_ROOT + '/' + name
            owned_mode(client, path, '0600')
            data = json.loads(read(client, path))
            if (not isinstance(data, dict) or data.get('format') != 'a777-cpanel-native-preflight-v1'
                    or data.get('mode') != 'preflight'
                    or data.get('status') not in ('inspected-not-authorized', 'capability-inspection-refused')
                    or not permission_only_safe(data)
                    or not isinstance(data.get('commit'), str) or not re.fullmatch(r'[a-f0-9]{40}', data['commit'])
                    or not name.startswith('preflight-' + data['commit'] + '-')
                    or not isinstance(data.get('planSha256'), str) or not re.fullmatch(r'[a-f0-9]{64}', data['planSha256'])):
                raise CpanelError('Private deployment receipt collision; refused.')
            permission_intent_guard(client, data)
            continue
        if isinstance(name, str) and re.fullmatch(r'plan-roll-[a-f0-9]{64}\.json', name):
            path = DEPLOY_ROOT + '/' + name
            owned_mode(client, path, '0600')
            data = json.loads(read(client, path))
            if not isinstance(data, dict) or set(data) != {'format', 'originalBytesBase64', 'originalSha256'} or data.get('format') != 'a777-preflight-plan-roll-intent-v1':
                raise CpanelError('Private plan intent collision; refused.')
            raw = base64.b64decode(data['originalBytesBase64'], validate=True)
            digest = hashlib.sha256(raw).hexdigest()
            if data['originalSha256'] != digest or name != 'plan-roll-' + digest + '.json':
                raise CpanelError('Private plan intent collision; refused.')
            continue
        if isinstance(name, str) and re.fullmatch(r'runtime-permission-[a-f0-9]{16}\.json', name):
            path = DEPLOY_ROOT + '/' + name
            owned_mode(client, path, '0600')
            data = json.loads(read(client, path))
            base_keys = {'format','operation','commit','planSha256','path','device','inode','owner','bytes','beforeMode','sha256'}
            parent_keys = {'parentPath','parentDevice','parentInode','parentOwner','parentBeforeMode','parentAfterMode','parentRepair'}
            if (not isinstance(data, dict) or set(data) not in (base_keys, base_keys | parent_keys)
                    or data.get('format') != 'a777-runtime-permission-intent-v1' or data.get('operation') != 'private0600'
                    or not isinstance(data.get('commit'), str) or not re.fullmatch(r'[a-f0-9]{40}', data['commit'])
                    or not isinstance(data.get('planSha256'), str) or not re.fullmatch(r'[a-f0-9]{64}', data['planSha256'])
                    or data.get('path') != HOME + '/public_html/.a777-runtime-active/config/runtime.php'
                    or any(type(data.get(k)) is not int or data[k] < 0 for k in ('device','inode','owner','bytes'))
                    or not isinstance(data.get('sha256'), str) or not re.fullmatch(r'[a-f0-9]{64}', data['sha256'])
                    or not isinstance(data.get('beforeMode'), str) or not re.fullmatch(r'[0-7]{4}', data['beforeMode'])):
                raise CpanelError('Private permission intent collision; refused.')
            if parent_keys <= set(data):
                if (data['parentPath'] != HOME + '/public_html/.a777-runtime-active/config'
                        or any(type(data[k]) is not int or data[k] < 0 for k in ('parentDevice','parentInode','parentOwner'))
                        or not isinstance(data['parentBeforeMode'], str) or not re.fullmatch(r'[0-7]{4}', data['parentBeforeMode'])
                        or int(data['parentBeforeMode'], 8) & 0o22 or data['parentAfterMode'] != '0700'
                        or type(data['parentRepair']) is not bool):
                    raise CpanelError('Private permission parent intent collision; refused.')
                bound = []
                for candidate in entries(client, DEPLOY_ROOT):
                    filename = candidate.get('name', '')
                    if isinstance(filename, str) and re.fullmatch(r'preflight-' + re.escape(data['commit']) + r'-[a-f0-9]{16}\.json', filename):
                        owned_mode(client, DEPLOY_ROOT + '/' + filename, '0600')
                        receipt = json.loads(read(client, DEPLOY_ROOT + '/' + filename))
                        if isinstance(receipt, dict) and receipt.get('runtimePermissionIntent') == name:
                            permission_intent_guard(client, receipt)
                            bound.append(receipt)
                if not bound:
                    raise CpanelError('Private permission parent intent has no completed receipt.')
            continue
        raise CpanelError('Private deployment inventory collision; refused.')


def provision_token(client, state):
    token = client.config.api_token
    if not isinstance(token, str) or not re.fullmatch(r'[A-Za-z0-9_-]{16,4096}', token):
        raise CpanelError('Private token contract refused.')
    owned_mode(client, DEPLOY_ROOT, '0700', True)
    if exists(client, TOKEN):
        raise CpanelError('Private token collision; refused.')
    # Mark intent before POST: a lost response must reconcile bytes, not retry upload.
    state['attempted'] = True
    try:
        client.save_file(TOKEN, token)
    except Exception:
        if not exists(client, TOKEN) or read(client, TOKEN) != token:
            raise CpanelError('Private token upload refused.') from None
    if read(client, TOKEN) != token:
        raise CpanelError('Private token read-back refused.')
    private_chmod(client, TOKEN)
    owned_mode(client, DEPLOY_ROOT, '0700', True)
    if read(client, TOKEN) != token:
        raise CpanelError('Private token read-back refused.')


def cleanup_token(client, state):
    if not state.get('attempted'):
        return not exists(client, TOKEN) if exists(client, DEPLOY_ROOT) else True
    owned_mode(client, DEPLOY_ROOT, '0700', True)
    if not exists(client, TOKEN):
        return True
    owned_mode(client, TOKEN, '0600')
    expected = hashlib.sha256(client.config.api_token.encode('ascii')).digest()
    if hashlib.sha256(read(client, TOKEN).encode('ascii')).digest() != expected:
        raise CpanelError('Private token cleanup refused.')
    try:
        client.trash(TOKEN)
    except Exception:
        if exists(client, TOKEN):
            raise CpanelError('Private token cleanup refused.') from None
    if exists(client, TOKEN):
        raise CpanelError('Private token cleanup refused.')
    return True


def retry_initial_rehearsal_plan(client, old, original, plan):
    if (plan['mode'] != 'restore-rehearsal' or old['expectedCommit'] == plan['expectedCommit']
            or old.get('expectedRuntimeConfigSha256') != plan.get('expectedRuntimeConfigSha256')):
        raise CpanelError('Initial rehearsal retry binding refused.')
    authorize(client)
    digest = hashlib.sha256(original.encode('ascii')).hexdigest()
    matched = []
    for row in entries(client, DEPLOY_ROOT):
        name = row.get('name', '')
        if not isinstance(name, str): raise CpanelError('Retry inventory refused.')
        if name.startswith('recovery-' + old['expectedCommit'] + '-') or re.fullmatch(r'restore-archite4_ar_[a-f0-9]{16}_test\.json', name):
            raise CpanelError('Retained recovery effects prohibit initial retry.')
        if re.fullmatch(r'restore-rehearsal-' + re.escape(old['expectedCommit']) + r'-[a-f0-9]{16}\.json', name):
            path = DEPLOY_ROOT + '/' + name
            owned_mode(client, path, '0600')
            receipt = json.loads(read(client, path))
            if (not rehearsal_safe(receipt, True) or receipt.get('planSha256') != digest
                    or receipt.get('status') != 'failed' or receipt.get('targetDatabaseDdlTouched') is not False
                    or receipt.get('databaseDdlTouched') is not False or receipt.get('isolatedRehearsalPassed') is not False
                    or receipt.get('lastCompletedStage') is not None
                    or any(k in receipt for k in ('targetDatabase','backupId','archiveSha256'))):
                raise CpanelError('Initial retry requires proof of no recovery effects.')
            matched.append(receipt)
    if len(matched) != 1: raise CpanelError('Exact initial failure receipt required.')
    databases = client._uapi('Mysql', 'list_databases', {})
    if (not isinstance(databases, list) or any(not isinstance(r, dict) or not isinstance(r.get('database'), str) for r in databases)
            or any(re.fullmatch(r'archite4_ar_[a-f0-9]{16}_test', r['database']) for r in databases)):
        raise CpanelError('Provider inventory prohibits initial rehearsal retry.')
    archive = DEPLOY_ROOT + '/plan-roll-' + digest + '.json'
    intent = json.dumps({'format':'a777-preflight-plan-roll-intent-v1',
                         'originalBytesBase64':base64.b64encode(original.encode('ascii')).decode('ascii'),
                         'originalSha256':digest}, sort_keys=True, separators=(',', ':')) + '\n'
    if exists(client, archive):
        owned_mode(client, archive, '0600')
        if read(client, archive) != intent: raise CpanelError('Retry intent collision.')
    else:
        client.save_file(archive, intent); private_chmod(client, archive)
    if read(client, archive) != intent or read(client, PLAN) != original:
        raise CpanelError('Retry intent read-back refused.')
    client.save_file(PLAN, json.dumps(plan, sort_keys=True, separators=(',', ':')) + '\n')


def preflight_plan(client, commit, secure_runtime_config_permissions=False, expected_runtime_config_sha256='', mode='preflight', retry_initial_rehearsal=False, allow_existing_public_source_for_rehearsal=False):
    if type(allow_existing_public_source_for_rehearsal) is not bool or (allow_existing_public_source_for_rehearsal and mode != 'restore-rehearsal'):
        raise CpanelError('Explicit rehearsal config pin required.')
    if mode not in ('preflight', 'restore-rehearsal') or (mode == 'restore-rehearsal' and not re.fullmatch(r'[a-f0-9]{64}', expected_runtime_config_sha256)):
        raise CpanelError('Explicit rehearsal config pin required.')
    if not isinstance(expected_runtime_config_sha256, str) or (expected_runtime_config_sha256 and not re.fullmatch(r'[a-f0-9]{64}', expected_runtime_config_sha256)):
        raise CpanelError('Explicit runtime config hash required.')
    if not exists(client, DEPLOY_ROOT):
        client.mkdir(DEPLOY_ROOT, '0700')
    # Existing directories are not repaired or broadened. Native helper checks UID too.
    owned_mode(client, DEPLOY_ROOT, '0700', True)
    plan = {'mode': mode, 'account': 'archite4', 'repository': ROOT,
            'activeRoot': HOME + '/public_html/.a777-runtime-active',
            'documentRoot': HOME + '/public_html/php.architex.co.za', 'expectedCommit': commit,
            'readOnlyUapiTransport': 'native-cli-then-private-token-https'}
    if type(secure_runtime_config_permissions) is not bool:
        raise CpanelError('Explicit permission authorization required.')
    if allow_existing_public_source_for_rehearsal:
        plan['allowExistingPublicSourceForRehearsal'] = True
    if secure_runtime_config_permissions:
        plan['secureRuntimeConfigPermissions'] = True
    if expected_runtime_config_sha256:
        plan['expectedRuntimeConfigSha256'] = expected_runtime_config_sha256
    deployment_inventory_guard(client)
    text = json.dumps(plan, sort_keys=True, separators=(',', ':')) + '\n'
    if exists(client, PLAN):
        owned_mode(client, PLAN, '0600')
        original = read(client, PLAN)
        old = json.loads(original)
        base = {k: v for k, v in plan.items() if k not in ('mode', 'expectedCommit', 'readOnlyUapiTransport', 'secureRuntimeConfigPermissions', 'expectedRuntimeConfigSha256', 'allowExistingPublicSourceForRehearsal')}
        if (not isinstance(old, dict) or any(old.get(k) != v for k, v in base.items())
                or not set(base) | {'expectedCommit'} <= set(old)
                or old.get('mode') not in ('preflight', 'restore-rehearsal')
                or not set(old) <= set(base) | {'mode', 'expectedCommit', 'readOnlyUapiTransport', 'secureRuntimeConfigPermissions', 'expectedRuntimeConfigSha256', 'allowExistingPublicSourceForRehearsal'}
                or ('expectedRuntimeConfigSha256' in old and (not isinstance(old['expectedRuntimeConfigSha256'], str) or not re.fullmatch(r'[a-f0-9]{64}', old['expectedRuntimeConfigSha256'])))
                or not isinstance(old.get('expectedCommit'), str)
                or not re.fullmatch(r'[a-f0-9]{40}', old['expectedCommit'])
                or ('allowExistingPublicSourceForRehearsal' in old and (type(old['allowExistingPublicSourceForRehearsal']) is not bool or (old['allowExistingPublicSourceForRehearsal'] and (old.get('mode') != 'restore-rehearsal' or not re.fullmatch(r'[a-f0-9]{64}', old.get('expectedRuntimeConfigSha256', ''))))))
                or ('secureRuntimeConfigPermissions' in old and type(old['secureRuntimeConfigPermissions']) is not bool)
                or ('readOnlyUapiTransport' in old and old['readOnlyUapiTransport'] != plan['readOnlyUapiTransport'])):
            raise CpanelError('Existing operator plan collision; refused.')
        if old.get('mode') == 'restore-rehearsal':
            if retry_initial_rehearsal is not True:
                raise CpanelError('Retained rehearsal plan requires independent operator review; no replay.')
            retry_initial_rehearsal_plan(client, old, original, plan)
        if old != plan and old.get('mode') != 'restore-rehearsal':
            old_sha = hashlib.sha256(original.encode('ascii')).hexdigest()
            matched = False
            for row in entries(client, DEPLOY_ROOT):
                name = row.get('name', '')
                if not isinstance(name, str) or not re.fullmatch(r'preflight-[a-f0-9]{40}-[a-f0-9]{16}\.json', name):
                    continue
                path = DEPLOY_ROOT + '/' + name
                owned_mode(client, path, '0600')
                receipt = json.loads(read(client, path))
                if (not isinstance(receipt, dict) or receipt.get('format') != 'a777-cpanel-native-preflight-v1'
                        or receipt.get('mode') != 'preflight'
                        or receipt.get('status') not in ('capability-inspection-refused', 'inspected-not-authorized')
                        or not permission_only_safe(receipt)
                        or not isinstance(receipt.get('commit'), str)
                        or not re.fullmatch(r'[a-f0-9]{40}', receipt['commit'])
                        or not name.startswith('preflight-' + receipt['commit'] + '-')
                        or not isinstance(receipt.get('planSha256'), str)
                        or not re.fullmatch(r'[a-f0-9]{64}', receipt['planSha256'])):
                    raise CpanelError('Private preflight receipt collision; refused.')
                permission_intent_guard(client, receipt)
                matched |= receipt['commit'] == old['expectedCommit'] and receipt['planSha256'] == old_sha
            if not matched:
                raise CpanelError('Matching old preflight receipt required.')
            intent_path = DEPLOY_ROOT + '/plan-roll-' + old_sha + '.json'
            intent = json.dumps({'format': 'a777-preflight-plan-roll-intent-v1',
                                 'originalBytesBase64': base64.b64encode(original.encode('ascii')).decode('ascii'),
                                 'originalSha256': old_sha}, sort_keys=True, separators=(',', ':')) + '\n'
            if exists(client, intent_path):
                owned_mode(client, intent_path, '0600')
                if read(client, intent_path) != intent:
                    raise CpanelError('Plan roll intent collision; refused.')
            else:
                client.save_file(intent_path, intent)
                private_chmod(client, intent_path)
            if read(client, intent_path) != intent or read(client, PLAN) != original:
                raise CpanelError('Plan roll read-back refused.')
            client.save_file(PLAN, text)
    else:
        client.save_file(PLAN, text)
        client._api2('fileop', {'op': 'chmod', 'sourcefiles': '.a777-deploy/operator-plan.json',
                              'doubledecode': '0', 'metadata': '0600'})
    owned_mode(client, PLAN, '0600')
    # Prove authenticated API 2 chmod capability before handing a token to Fileman.
    private_chmod(client, PLAN)
    actual = read(client, PLAN)
    if json.loads(actual) != plan:
        raise CpanelError('Private plan read-back mismatch.')
    return hashlib.sha256(actual.encode('ascii')).hexdigest()


def receipts(client, commit, plan_sha, previous, mode='preflight', permissions_authorized=False):
    if mode == 'restore-rehearsal':
        return rehearsal_receipts(client, commit, plan_sha, previous, permissions_authorized)
    reports = []
    pattern = r'preflight-' + re.escape(commit) + r'-[a-f0-9]{16}\.json'
    for row in entries(client, DEPLOY_ROOT):
        name = row.get('name', '')
        if not isinstance(name, str) or not re.fullmatch(pattern, name) or name in previous:
            continue
        owned_mode(client, DEPLOY_ROOT + '/' + name, '0600')
        data = json.loads(read(client, DEPLOY_ROOT + '/' + name))
        if not isinstance(data, dict) or data.get('format') != 'a777-cpanel-native-preflight-v1' or data.get('commit') != commit or data.get('mode') != 'preflight' or data.get('planSha256') != plan_sha:
            continue
        if not permission_only_safe(data):
            raise CpanelError('Preflight receipt write-safety contract refused.')
        permission_intent_guard(client, data)
        safe = {'report_id': name, 'commit': commit, 'planSha256': plan_sha}
        safe['status'] = data.get('status') if data.get('status') in ('inspected-not-authorized', 'capability-inspection-refused') else 'invalid_contract'
        for key in BOOL_FIELDS:
            if type(data.get(key)) is bool: safe[key] = data[key]
        if data.get('managedRestoreProviderStatus') in ('not-rehearsed', 'authenticated-inventory-read-only'):
            safe['managedRestoreProviderStatus'] = data['managedRestoreProviderStatus']
        if data.get('databaseHostClass') in ('tcp-loopback', 'local-socket', 'configured-managed', 'refused'):
            safe['databaseHostClass'] = data['databaseHostClass']
        for key in ('runtimeConfigModeBefore', 'runtimeConfigModeAfter', 'runtimeConfigParentModeBefore', 'runtimeConfigParentModeAfter'):
            if isinstance(data.get(key), str) and re.fullmatch(r'[0-7]{4}', data[key]): safe[key] = data[key]
        if data.get('storageClass') in ('missing', 'canonical-unproven', 'archive-inside-storage', 'outside-public', 'under-public-privacy-unproven'):
            safe['storageClass'] = data['storageClass']
        workers = data.get('accountPhpWorkerTypes')
        if isinstance(workers, dict) and all(k in ('php-cli', 'php-fpm', 'lsphp') and type(v) is int and 0 <= v <= 1000000 for k, v in workers.items()):
            safe['accountPhpWorkerTypes'] = workers
        for key in ('accountPhpWorkerCount', 'cronEntryCount', 'otherSourceConnectionCount', 'runtimeConfigBytesUnchanged'):
            if type(data.get(key)) is int and 0 <= data[key] <= 1000000: safe[key] = data[key]
        for key in ('runtimePermissionDevice','runtimePermissionInode','runtimePermissionOwner',
                    'runtimePermissionParentDevice','runtimePermissionParentInode','runtimePermissionParentOwner'):
            if type(data.get(key)) is int and data[key] >= 0: safe[key] = data[key]
        if isinstance(data.get('runtimePermissionIntent'), str) and re.fullmatch(r'runtime-permission-[a-f0-9]{16}\.json', data['runtimePermissionIntent']):
            safe['runtimePermissionIntent'] = data['runtimePermissionIntent']
        if 'blockingGate' in data:
            safe['blockingGate'] = data['blockingGate'] if data['blockingGate'] in GATES else 'native_preflight_failed'
        for key in ('runtimeConfigSha256', 'activeSqlTreeSha256', 'configContentUnchangedSHA'):
            if isinstance(data.get(key), str) and re.fullmatch(r'[a-f0-9]{64}', data[key]): safe[key] = data[key]
        for key in ('mysqlDatabaseTransport', 'mysqlUserTransport'):
            value = data.get(key)
            if isinstance(value, str) and value in ('native-cli', 'authenticated-https-fallback-attempted', 'authenticated-https'):
                safe[key] = value
            diagnostic = data.get(key + 'Cli')
            if isinstance(diagnostic, dict):
                clean = {}
                if diagnostic.get('status') in ('unavailable', 'success', 'failed'):
                    clean['status'] = diagnostic['status']
                exit_code = diagnostic.get('exitCode')
                if exit_code is None or type(exit_code) is int and -1 <= exit_code <= 255:
                    clean['exitCode'] = exit_code
                if diagnostic.get('stderrClass') in ('permission_denied', 'not_found', 'unknown_option', 'access_denied', 'command_not_found', 'empty', 'unclassified'):
                    clean['stderrClass'] = diagnostic['stderrClass']
                for observation in ('absoluteExecutablePresent', 'absoluteExecutableUsable', 'pathExecutableUsable'):
                    if type(diagnostic.get(observation)) is bool:
                        clean[observation] = diagnostic[observation]
                safe[key + 'Cli'] = clean
        if type(data.get('apiAccountHomeValidated')) is bool:
            safe['apiAccountHomeValidated'] = data['apiAccountHomeValidated']
        reports.append(safe)
    return sorted(reports, key=lambda r: r['report_id'])


def native_preflight(client, expected_commit, expected_key=DEPLOY_KEY_PIN, attempts=36, pause=5, secure_runtime_config_permissions=False, expected_runtime_config_sha256='', mode='preflight', retry_initial_rehearsal=False, allow_existing_public_source_for_rehearsal=False):
    report = {'mode': ('restore-rehearsal' if mode == 'restore-rehearsal' else 'native-preflight'), 'status': 'failed', 'runtime_deployed': False,
              'native_preflight_status': 'not_started', 'deployment_status': 'not_started', 'reports': [],
              'tokenAbsenceVerified': False}
    token_state = {}
    authorized = False
    stage = 'input'
    try:
        if not isinstance(expected_commit, str) or not re.fullmatch(r'[a-f0-9]{40}', expected_commit) or expected_key != DEPLOY_KEY_PIN:
            raise CpanelError('Native preflight input refused.')
        if not isinstance(expected_runtime_config_sha256, str) or (expected_runtime_config_sha256 and not re.fullmatch(r'[a-f0-9]{64}', expected_runtime_config_sha256)):
            raise CpanelError('Explicit runtime config hash required.')
        if mode not in ('preflight', 'restore-rehearsal') or (mode == 'restore-rehearsal' and not re.fullmatch(r'[a-f0-9]{64}', expected_runtime_config_sha256)):
            raise CpanelError('Explicit rehearsal intent required.')
        if mode == 'restore-rehearsal': attempts = max(attempts, 240)
        stage = 'pull_master'
        # Preflight must never create a new repository implicitly.
        authorize(client)
        authorized = True
        if inventory(client) is None: raise CpanelError('Existing managed repository required.')
        pulled = clone(client, expected_key, attempts=attempts, pause=pause)
        report['remote_head'] = pulled.get('commit')
        if pulled.get('status') != 'cloned_and_pulled' or pulled.get('commit') != expected_commit:
            raise CpanelError('Remote HEAD differs from workflow commit.')
        stage = 'private_plan'
        plan_sha = preflight_plan(client, expected_commit, secure_runtime_config_permissions, expected_runtime_config_sha256, mode=mode, retry_initial_rehearsal=retry_initial_rehearsal, allow_existing_public_source_for_rehearsal=allow_existing_public_source_for_rehearsal)
        previous = {r.get('name') for r in entries(client, DEPLOY_ROOT)}
        stage = 'private_token'
        provision_token(client, token_state)
        stage = 'native_create'
        created = client._uapi('VersionControlDeployment', 'create', {'repository_root': ROOT})
        if not isinstance(created, dict) or created.get('repository_root') != ROOT or not re.fullmatch(r'[0-9]+', str(created.get('deploy_id', ''))) or not isinstance(created.get('task_id'), str) or not re.fullmatch(r'[A-Za-z0-9/-]{1,128}', created['task_id']):
            raise CpanelError('Deployment create contract refused.')
        report.update(deploy_id=str(created['deploy_id']), task_id=created['task_id'], deployment_status='queued', native_preflight_status='pending')
        stage = 'native_poll'
        for attempt in range(attempts):
            rows = client._uapi('VersionControlDeployment', 'retrieve', {})
            if not isinstance(rows, list) or any(not isinstance(r, dict) for r in rows): raise CpanelError('Deployment retrieve contract refused.')
            matches = [r for r in rows if r.get('repository_root') == ROOT and str(r.get('deploy_id')) == report['deploy_id'] and r.get('task_id') == report['task_id']]
            if len(matches) > 1: raise CpanelError('Deployment task collision.')
            if matches:
                row = matches[0]
                state = row.get('repository_state') or {}
                identifier = state.get('identifier')
                report['deployment_last_commit'] = identifier if isinstance(identifier, str) and re.fullmatch(r'[a-f0-9]{40}', identifier) else None
                stamps = row.get('timestamps') or {}
                terminal = [k for k in ('succeeded', 'failed', 'canceled') if isinstance(stamps.get(k), (str, int, float)) and re.fullmatch(r'[0-9]+(?:\.[0-9]+)?', str(stamps[k]))]
                if len(terminal) > 1: raise CpanelError('Deployment terminal state collision.')
                if terminal:
                    report['deployment_status'] = terminal[0]
                    break
                report['deployment_status'] = 'active' if stamps.get('active') else 'queued'
            if attempt + 1 < attempts: time.sleep(pause)
        stage = 'private_report'
        report['reports'] = receipts(client, expected_commit, plan_sha, previous, mode, secure_runtime_config_permissions)
        if report['reports']:
            report['native_preflight_status'] = report['reports'][-1]['status']
        else: report['native_preflight_status'] = 'receipt_unavailable'
        report['status'] = ('restore_rehearsal_complete' if mode == 'restore-rehearsal' else 'preflight_complete') if report['deployment_status'] == 'succeeded' and report.get('deployment_last_commit') == expected_commit and report['native_preflight_status'] == ('rehearsal-verified' if mode == 'restore-rehearsal' else 'inspected-not-authorized') else 'failed_or_incomplete'
    except Exception as error:
        report['error'] = 'native_preflight_' + stage + '_refused'
        import traceback
        locations = [frame for frame in traceback.extract_tb(error.__traceback__) if os.path.basename(frame.filename) == 'cpanel_git_prepare.py']
        if locations: report['refusalLine'] = locations[-1].lineno
        reasons = {
            'Initial rehearsal retry binding refused.':'retry_binding',
            'Retained recovery effects prohibit initial retry.':'retained_recovery_effects',
            'Initial retry requires proof of no recovery effects.':'retry_effects_proof',
            'Exact initial failure receipt required.':'retry_receipt_count',
            'Provider inventory prohibits initial rehearsal retry.':'provider_target_inventory',
            'Private deployment inventory collision; refused.':'deployment_inventory',
            'Retained rehearsal source safety refused.':'retained_receipt_safety',
            'Existing operator plan collision; refused.':'operator_plan_contract',
            'Retained rehearsal plan requires independent operator review; no replay.':'retry_not_authorized',
        }
        if isinstance(error, CpanelError) and str(error) in reasons:
            report['refusalReason'] = reasons[str(error)]
        # Preserve available gate evidence even when task polling fails.
        if 'plan_sha' in locals() and 'previous' in locals():
            try:
                report['reports'] = receipts(client, expected_commit, plan_sha, previous, mode, secure_runtime_config_permissions)
                if report['reports']: report['native_preflight_status'] = report['reports'][-1]['status']
            except Exception: pass
    finally:
        if authorized:
            try:
                report['tokenAbsenceVerified'] = cleanup_token(client, token_state)
                if not report['tokenAbsenceVerified']:
                    raise CpanelError('Private token cleanup refused.')
            except Exception:
                report['cleanup'] = 'cleanupfailed'
                if report['status'] in ('preflight_complete', 'restore_rehearsal_complete'):
                    report['status'] = 'failed_or_incomplete'
    return report
