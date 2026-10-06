"""Explicit Git preparation and CLI-only native read-only preflight orchestration."""
import base64
import fnmatch
import hashlib
import json
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
BOOL_FIELDS = frozenset(('databaseDdlTouched', 'liveFilesTouched', 'zipAvailable', 'curlAvailable',
    'nativeRestoreCapabilityPresent', 'activeRootAvailable', 'documentRootAvailable',
    'runtimeConfigAvailable', 'liveDdlAuthorized', 'mysqlInventoryAvailable',
    'mysqlUserInventoryAvailable', 'cronInventoryAvailable', 'exclusiveGrantUserVisible',
    'sourceConnectionsDrainedAtInspection'))
GATES = frozenset(('active_or_document_root_invalid', 'active_config_missing_or_not_private',
    'active_database_config_identity_mismatch', 'active_database_password_missing',
    'actual_source_pdo_identity_mismatch', 'native_uapi_failed', 'native_uapi_inventory_missing',
    'command_unavailable', 'command_failed', 'active_sql_root_invalid', 'active_sql_entry_invalid',
    'active_sql_empty', 'backup_storage_not_private_or_archive_inside_storage', 'native_preflight_failed',
    'unknown_writer_dispatch_exclusion_and_verified_promotion_not_implemented'))


def owned_mode(client, path, mode, directory=False):
    parent, name = path.rsplit('/', 1)
    matches = [r for r in entries(client, parent) if r.get('name') == name]
    if len(matches) != 1 or matches[0].get('type') not in (('dir', 'directory') if directory else ('file',)) or str(matches[0].get('nicemode', '')).lstrip('0') != mode.lstrip('0'):
        raise CpanelError('Private deployment path permissions refused.')


def preflight_plan(client, commit):
    if not exists(client, DEPLOY_ROOT):
        client.mkdir(DEPLOY_ROOT, '0700')
    # Existing directories are not repaired or broadened. Native helper checks UID too.
    owned_mode(client, DEPLOY_ROOT, '0700', True)
    plan = {'mode': 'preflight', 'account': 'archite4', 'repository': ROOT,
            'activeRoot': HOME + '/public_html/.a777-runtime-active',
            'documentRoot': HOME + '/public_html/php.architex.co.za', 'expectedCommit': commit}
    text = json.dumps(plan, sort_keys=True, separators=(',', ':')) + '\n'
    if exists(client, PLAN):
        owned_mode(client, PLAN, '0600')
        old = json.loads(read(client, PLAN))
        if not isinstance(old, dict) or old.get('mode') != 'preflight':
            raise CpanelError('Existing operator plan collision; refused.')
        if old != plan:
            raise CpanelError('Existing operator plan commit collision; operator review required.')
    else:
        client.save_file(PLAN, text)
        client._api2('fileop', {'op': 'chmod', 'sourcefiles': '.a777-deploy/operator-plan.json',
                              'doubledecode': '0', 'metadata': '0600'})
    owned_mode(client, PLAN, '0600')
    actual = read(client, PLAN)
    if json.loads(actual) != plan:
        raise CpanelError('Private plan read-back mismatch.')
    return hashlib.sha256(actual.encode('ascii')).hexdigest()


def receipts(client, commit, plan_sha, previous):
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
        safe = {'report_id': name, 'commit': commit, 'planSha256': plan_sha}
        safe['status'] = data.get('status') if data.get('status') in ('inspected-not-authorized', 'capability-inspection-refused') else 'invalid_contract'
        for key in BOOL_FIELDS:
            if type(data.get(key)) is bool: safe[key] = data[key]
        for key in ('cronEntryCount', 'otherSourceConnectionCount'):
            if type(data.get(key)) is int and 0 <= data[key] <= 1000000: safe[key] = data[key]
        if 'blockingGate' in data:
            safe['blockingGate'] = data['blockingGate'] if data['blockingGate'] in GATES else 'native_preflight_failed'
        for key in ('runtimeConfigSha256', 'activeSqlTreeSha256'):
            if isinstance(data.get(key), str) and re.fullmatch(r'[a-f0-9]{64}', data[key]): safe[key] = data[key]
        reports.append(safe)
    return sorted(reports, key=lambda r: r['report_id'])


def native_preflight(client, expected_commit, expected_key=DEPLOY_KEY_PIN, attempts=36, pause=5):
    report = {'mode': 'native-preflight', 'status': 'failed', 'runtime_deployed': False,
              'native_preflight_status': 'not_started', 'deployment_status': 'not_started', 'reports': []}
    stage = 'input'
    try:
        if not isinstance(expected_commit, str) or not re.fullmatch(r'[a-f0-9]{40}', expected_commit) or expected_key != DEPLOY_KEY_PIN:
            raise CpanelError('Native preflight input refused.')
        stage = 'pull_master'
        # Preflight must never create a new repository implicitly.
        authorize(client)
        if inventory(client) is None: raise CpanelError('Existing managed repository required.')
        pulled = clone(client, expected_key, attempts=attempts, pause=pause)
        report['remote_head'] = pulled.get('commit')
        if pulled.get('status') != 'cloned_and_pulled' or pulled.get('commit') != expected_commit:
            raise CpanelError('Remote HEAD differs from workflow commit.')
        stage = 'private_plan'
        plan_sha = preflight_plan(client, expected_commit)
        previous = {r.get('name') for r in entries(client, DEPLOY_ROOT)}
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
        report['reports'] = receipts(client, expected_commit, plan_sha, previous)
        if report['reports']:
            report['native_preflight_status'] = report['reports'][-1]['status']
        else: report['native_preflight_status'] = 'receipt_unavailable'
        report['status'] = 'preflight_complete' if report['deployment_status'] == 'succeeded' and report.get('deployment_last_commit') == expected_commit and report['native_preflight_status'] == 'inspected-not-authorized' else 'failed_or_incomplete'
    except Exception:
        report['error'] = 'native_preflight_' + stage + '_refused'
        # Preserve available gate evidence even when task polling fails.
        if 'plan_sha' in locals() and 'previous' in locals():
            try:
                report['reports'] = receipts(client, expected_commit, plan_sha, previous)
                if report['reports']: report['native_preflight_status'] = report['reports'][-1]['status']
            except Exception: pass
    return report
