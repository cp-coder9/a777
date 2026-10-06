"""Explicitly authorized Git preparation; never deploys or invokes runtime code."""
import base64
import fnmatch
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import tempfile
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


def fingerprint(public):
    parts = public.strip().split()
    if len(parts) < 2 or parts[0] != 'ssh-ed25519':
        raise CpanelError('Invalid owned public key.')
    try:
        raw = base64.b64decode(parts[1], validate=True)
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


def ssh_command(args):
    result = subprocess.run(['ssh-keygen', *args], capture_output=True, check=False)
    if result.returncode:
        raise CpanelError('Local key operation failed.')
    return result.stdout.decode('ascii')


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
    for row in rows:
        if row.get('type') in ('link', 'symlink'):
            raise CpanelError('Symlink in preparation directory; refused.')
    return rows


def exists(client, path):
    parent, name = path.rsplit('/', 1)
    return any(row.get('name') == name for row in entries(client, parent))


def read(client, path):
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


def prepare_key(client, public_path, expected=None):
    authorize(client)
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
    if key_exists != pub_exists or (key_exists and not expected):
        raise CpanelError('Owned key collision: expected fingerprint required; no overwrite.')
    with tempfile.TemporaryDirectory(prefix='a777-key-', dir=os.environ.get('RUNNER_TEMP')) as directory:
        os.chmod(directory, 0o700)
        private = Path(directory) / 'key'
        if key_exists:
            public = read(client, KEY + '.pub')
            if fingerprint(public) != expected:
                raise CpanelError('Owned public key fingerprint collision.')
            private.write_text(read(client, KEY), encoding='ascii', newline='\n')
            os.chmod(private, 0o600)
            if fingerprint(ssh_command(['-y', '-f', str(private)])) != expected:
                raise CpanelError('Owned private/public key mismatch.')
        else:
            if expected:
                raise CpanelError('Expected owned key absent; refusing replacement.')
            ssh_command(['-q', '-t', 'ed25519', '-N', '', '-C', 'a777-production-read-only', '-f', str(private)])
            os.chmod(private, 0o600)
            public = Path(str(private) + '.pub').read_text(encoding='ascii')
            client.upload_file(private, KEY)
            chmod(client, KEY, '0600')
            if read(client, KEY) != private.read_text(encoding='ascii'):
                raise CpanelError('Private key upload read-back mismatch.')
            client.save_file(KEY + '.pub', public)
        chmod(client, KEY, '0600')
        chmod(client, KEY + '.pub', '0600')
        if read(client, KEY + '.pub') != public:
            raise CpanelError('Public key read-back mismatch.')
    preserve_append(client, SSH + '/known_hosts', known, known_updated)
    preserve_append(client, SSH + '/config', original, updated)
    public_path.write_text(public, encoding='ascii')
    return {'mode': 'prepare-key', 'status': 'prepared', 'public_key_fingerprint': fingerprint(public), 'runtime_deployed': False}


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
