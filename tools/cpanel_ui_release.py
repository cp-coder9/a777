#!/usr/bin/env python3
"""Read-only, fixed-account UI inspection. No artifact build or deployment.

Uses existing CpanelClient and documented Variables, DomainInfo(format=hash),
Fileman/list_files and Fileman/get_file_content UAPI contracts. Latin-1 to UTF-8
conversion is reversed in memory so binary files are not hashed as JSON text.
Provider metadata is NOT native canonical-path, ownership or link proof.
"""
import argparse
from fnmatch import fnmatchcase
import hashlib
import json
import os
from pathlib import Path
import posixpath
import re
import subprocess
from typing import Any
from cpanel_client import CpanelClient, CpanelConfig

HOME = '/home/archite4'
DOMAIN = 'php.architex.co.za'
DOCROOT = HOME + '/public_html/' + DOMAIN
RUNTIME = HOME + '/.a777-runtime-private/7eee8a25050fadb3d36b74c40f485061'
BASELINE = '6f21a24c6fc7bca06b14d4d48d352d12135a2f9f'


class InspectionClient(CpanelClient):
    """One bounded listing per relevant parent, not an account-wide tree crawl."""
    _READ_OPERATIONS = frozenset({('Variables', 'get_user_information'),
        ('DomainInfo', 'domains_data'), ('Fileman', 'list_files'),
        ('Fileman', 'get_file_content')})
    _READ_PATHS = frozenset('/execute/' + module + '/' + function
        for module, function in _READ_OPERATIONS)

    def _uapi(self, module, function, params=None, body=None, headers=None):
        need((module, function) in self._READ_OPERATIONS and body is None, 'operation_not_read_only')
        return super()._uapi(module, function, params, body, headers)

    def _request_json(self, method, path, params=None, body=None, headers=None):
        # Match only literal paths produced by _uapi; never decode or normalize
        # caller paths into approval (queries belong solely in params).
        need(method == 'GET' and body is None and isinstance(path, str) and
             path in self._READ_PATHS and (params is None or type(params) is dict),
             'operation_not_read_only')
        payload = super()._request_json(method, path, params, body, headers)
        result = payload.get('result', payload)
        need(isinstance(result, dict) and type(result.get('status')) is int and
             result['status'] == 1 and result.get('errors') in (None, []), 'provider_envelope_unknown')
        return payload

    def list_files(self, path):
        cache = getattr(self, '_inspection_listings', None)
        if cache is None:
            cache = self._inspection_listings = {}
        if path not in cache:
            cache[path] = super().list_files(path)
        return cache[path]


class Refusal(Exception):
    def __init__(self, code):
        self.code = code


def need(value, code):
    if not value:
        raise Refusal(code)


def digest(value):
    return hashlib.sha256(value).hexdigest()


def shim(name):
    need(name in ('index.php', 'app.php', 'legal.php'), 'entry_unknown')
    return ("<?php\ndeclare(strict_types=1);\numask(0077);\nrequire '" + RUNTIME + '/public/' + name + "';\n").encode()


def parse_shim(content, name):
    # Entire source equals the reviewed CpanelPrivateRuntimeRelocation::shim
    # producer. No regex suffix extraction, PHP execution or arbitrary include.
    need(content == shim(name), 'published_shim_unknown')
    return RUNTIME


def safe_path(path):
    need(isinstance(path, str) and posixpath.normpath(path) == path and
         '\\' not in path and '\x00' not in path and
         any(path.startswith(root + '/') for root in (DOCROOT, RUNTIME)), 'read_scope_refused')
    return path


def read_bytes(client, path):
    safe_path(path)
    # Fresh provider observations reject reported links/unknown types; they do
    # not establish realpath, inode or POSIX ownership. No native proof inferred.
    parts = path[len(HOME) + 1:].split('/')
    parent = HOME
    for index, part in enumerate(parts):
        rows = client.list_files(parent)
        matches = [row for row in rows if row.get('name', row.get('file')) == part]
        need(len(matches) == 1, 'provider_path_metadata_unknown')
        row = matches[0]
        expected = 'file' if index == len(parts) - 1 else 'dir'
        need(row.get('type') == expected and not row.get('link') and not row.get('islink'),
             'provider_link_or_type_unknown')
        parent += '/' + part
    data = client._uapi('Fileman', 'get_file_content', {
        'dir': posixpath.dirname(path), 'file': posixpath.basename(path),
        'from_charset': 'ISO-8859-1', 'to_charset': 'UTF-8',
        'update_html_document_encoding': '0'})
    need(isinstance(data, dict) and isinstance(data.get('content'), str), 'file_content_unknown')
    try:
        return data['content'].encode('latin-1')
    except UnicodeError:
        raise Refusal('file_encoding_unknown') from None


def domain_roots(data, *, authenticated_home=None, metadata=None):
    """Complete account inventory, not native-stage's target-only filter.

    https://api.docs.cpanel.net/openapi/cpanel/operation/domains_data/
    format=hash returns objects by domain type; userdata fields may be absent.
    Missing categories do NOT establish emptiness, so all four remain required.
    Only one-level domain maps and legacy row lists are accepted, never recursive
    searching that could silently skip an opaque entry. Missing user/home is
    unknown metadata, bound only by the prior authenticated Variables read.

    The documented addon example has domain=exampledomain.com and
    servername=subdomain.example.com: the addon identifier (and hash key when
    present) is distinct from its canonical vhost. Both are literal hosts and
    both participate in cross-row identity/alias collision checks. Only the
    addon group permits this distinction; main/sub/parked identifiers remain
    canonical-consistent. Absent domain uses servername, never a guessed key.
    Target binding requires an explicit servername=DOMAIN, a consistent domain
    and map key, a non-parked producer group and exactly DOCROOT. Every row's
    root/account is validated, including unrelated addons; no alias binds a
    target and no provider field is interpreted as native ownership proof.
    """
    groups = ('main_domain', 'sub_domains', 'addon_domains', 'parked_domains')
    need(type(data) is dict and set(data) == set(groups) and
         type(data['main_domain']) is dict, 'domain_inventory_unknown')
    need(authenticated_home is None or authenticated_home == HOME, 'domain_account_unknown')
    rows: list[tuple[str, Any, Any]] = [('main_domain', None, data['main_domain'])]
    for category in groups[1:]:
        value: Any = data[category]
        need(type(value) in (dict, list) and len(value) <= 20000, 'domain_inventory_unknown')
        if type(value) is dict:
            need(all(isinstance(key, str) for key in value), 'domain_identity_unknown')
        entries = value.items() if type(value) is dict else ((None, row) for row in value)
        rows.extend((category, key, row) for key, row in entries)
    need(len(rows) <= 20000, 'domain_inventory_unknown')
    roots, targets, identities = [], [], set()
    missing_user = missing_home = 0
    name_owners, wildcard_aliases = {}, []
    for row_index, (category, key, row) in enumerate(rows):
        need(type(row) is dict, 'domain_inventory_unknown')
        domain = row.get('servername', row.get('domain'))
        identifier = row.get('domain', domain)
        for name in (domain, identifier):
            need(isinstance(name, str) and len(name) <= 253 and
                 re.fullmatch(r'[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?', name) and
                 all(label and len(label) <= 63 and not label.startswith('-') and
                     not label.endswith('-') for label in name.split('.')),
                 'domain_identity_unknown')
        need((key is None or key == identifier) and
             (category == 'addon_domains' or identifier == domain), 'domain_identity_unknown')
        row_identities = {domain, identifier}
        need(not identities.intersection(row_identities), 'domain_identity_unknown')
        identities.update(row_identities)
        need(identifier != DOMAIN or domain == DOMAIN, 'domain_identity_unknown')
        # Same-row addon identifier/servername aliases are intentional; names
        # belonging to any other inventory row must never collide.
        aliases = row.get('serveralias', '')
        need(isinstance(aliases, str), 'domain_identity_unknown')
        for name in row_identities | {alias.lower() for alias in aliases.split()}:
            if any(char in name for char in '*?['):
                wildcard_aliases.append((row_index, name))
            else:
                need(name_owners.get(name, row_index) == row_index, 'domain_identity_unknown')
                name_owners[name] = row_index
        if domain != DOMAIN:
            need(not any(fnmatchcase(DOMAIN, alias.lower()) for alias in aliases.split()),
                 'domain_identity_unknown')
        root = row.get('documentroot')
        need(isinstance(root, str) and root.startswith(HOME + '/') and
             posixpath.normpath(root) == root and '\\' not in root and '\x00' not in root,
             'domain_root_unknown')
        for field, expected in (('homedir', HOME), ('user', 'archite4')):
            need(row[field] == expected if field in row else authenticated_home == HOME,
                 'domain_account_unknown')
        missing_user += 'user' not in row
        missing_home += 'homedir' not in row
        need(not (RUNTIME == root or RUNTIME.startswith(root + '/') or root.startswith(RUNTIME + '/')),
             'private_root_overlaps_documentroot')
        roots.append(root)
        if domain == DOMAIN:
            need(row.get('servername') == DOMAIN and identifier == DOMAIN and
                 category in ('main_domain', 'sub_domains', 'addon_domains'), 'domain_identity_unknown')
            targets.append(root)
    for owner, pattern in wildcard_aliases:
        need(not any(other != owner and fnmatchcase(name, pattern)
                     for name, other in name_owners.items()), 'domain_identity_unknown')
    need(targets == [DOCROOT], 'serving_root_unknown')
    if metadata is not None:
        metadata.update(rowCount=len(rows), missingUserCount=missing_user, missingHomeCount=missing_home)
    return sorted(set(roots))


def manifest(data):
    need(isinstance(data, dict) and data.get('format') == 'architex-release-manifest-v1' and
         isinstance(data.get('version'), str) and isinstance(data.get('files'), dict), 'manifest_unknown')
    files = data['files']
    need(0 < len(files) <= 20000, 'manifest_unknown')
    for name, row in files.items():
        need(isinstance(name, str) and re.fullmatch(r'[A-Za-z0-9_.-]+(?:/[A-Za-z0-9_.-]+)*', name) and
             '..' not in name.split('/') and name != 'config/runtime.php', 'manifest_path_unknown')
        need(isinstance(row, dict) and type(row.get('bytes')) is int and row['bytes'] >= 0 and
             isinstance(row.get('sha256'), str) and re.fullmatch('[a-f0-9]{64}', row['sha256']), 'manifest_hash_unknown')
    return files


def git(root, *args):
    result = subprocess.run(['git', '-C', str(root), *args], capture_output=True)
    need(result.returncode == 0, 'candidate_git_unknown')
    return result.stdout


def source_inventory(root):
    need(not git(root, 'status', '--porcelain', '--untracked-files=all').strip(), 'candidate_checkout_dirty')
    git(root, 'rev-parse', '--verify', BASELINE + '^{commit}')
    commit = git(root, 'rev-parse', 'HEAD').decode().strip()
    need(re.fullmatch('[a-f0-9]{40}', commit), 'candidate_commit_unknown')
    tree = git(root, 'ls-tree', '-r', 'HEAD').decode().splitlines()
    need(not any(line.startswith('120000 ') for line in tree), 'candidate_symlink_refused')
    names = git(root, 'ls-tree', '-r', '--name-only', 'HEAD').decode().splitlines()
    relevant = [n for n in names if n.startswith(('app/public/', 'app/views/', 'app/src/',
                'app/database/', 'app/cron/', 'app/config/')) or n in
                ('app/bootstrap.php', 'composer.json', 'composer.lock') or n.startswith('app/bin/')]
    need('app/config/runtime.php' not in relevant, 'tracked_runtime_config_refused')
    inventory = {}
    for name in relevant:
        content = git(root, 'show', 'HEAD:' + name)
        try:
            old = git(root, 'show', BASELINE + ':' + name)
        except Refusal:
            old = None
        inventory[name] = {'sha256': digest(content), 'bytes': len(content), 'baselineSha256': digest(old) if old is not None else None}
    # Include removals and prohibit all backend/bootstrap/Composer changes.
    immutable = ['app/src', 'app/database', 'app/cron', 'app/bin', 'app/config', 'app/bootstrap.php', 'composer.json', 'composer.lock']
    need(not git(root, 'diff', '--name-only', BASELINE, 'HEAD', '--', *immutable).strip(), 'backend_source_changed')
    changes = git(root, 'diff', '--name-only', BASELINE, 'HEAD', '--', 'app/public', 'app/views').decode().splitlines()
    need(all(n.startswith('app/public/') or n == 'app/views/v1-clone.php' for n in changes), 'ui_scope_changed')
    return {'commit': commit, 'files': inventory, 'changes': changes,
            'inventorySha256': digest(json.dumps(inventory, sort_keys=True, separators=(',', ':')).encode())}


def inspect(client, candidate):
    report = {'mode': 'inspect', 'deployed': False, 'writesPerformed': False,
              'nativeCanonicalVerified': False, 'nativeOwnershipVerified': False,
              'backupVerified': False, 'migrationLedgerVerified': False,
              'inspectionComplete': False, 'errorCode': None}
    try:
        need(client.account_home() == HOME, 'account_identity_unknown')
        domain_metadata = {}
        report['documentRoots'] = domain_roots(client._uapi('DomainInfo', 'domains_data',
            {'format': 'hash', 'hide_temporary_domains': '0'}),
            authenticated_home=HOME, metadata=domain_metadata)
        report['domainAccountMetadata'] = domain_metadata
        for name in ('index.php', 'app.php', 'legal.php'):
            parse_shim(read_bytes(client, DOCROOT + '/' + name), name)
        report['shimBoundRuntime'] = RUNTIME
        report['configSha256'] = digest(read_bytes(client, RUNTIME + '/config/runtime.php'))
        metadata = json.loads(read_bytes(client, RUNTIME + '/release.json'))
        need(isinstance(metadata, dict) and metadata.get('format') == 'architex-release-v1' and
             metadata.get('sourceCommit') == BASELINE and metadata.get('sourceDirty') is False and
             metadata.get('environment') == 'production', 'live_baseline_unknown')
        raw_manifest = json.loads(read_bytes(client, RUNTIME + '/release-manifest.json'))
        files = manifest(raw_manifest)
        need(metadata.get('version') == raw_manifest['version'], 'live_version_unknown')
        hashes = {}
        for name, source in candidate['files'].items():
            relative = name[4:] if name.startswith('app/') else name
            if relative == 'composer.json':
                continue  # ReleaseBuilder rewrites this; never claim raw-source equality.
            if relative.startswith('bin/') and relative not in {
                    'bin/' + name for name in ('migrate.php', 'database-status.php', 'backup.php',
                    'restore.php', 'verify-release.php', 'deliver-community-notifications.php',
                    'deliver-dedicated-app-notifications.php')}:
                continue  # Exact ReleaseBuilder selected-script list.
            expected = source['baselineSha256']
            if expected is None:
                need(relative.startswith('public/'), 'immutable_baseline_file_unknown')
                # Candidate additions have no baseline bytes to compare. This
                # inspection does not assert remote absence or overwrite safety.
                continue
            need(relative in files, 'live_inventory_incomplete')
            need(expected is not None and files[relative]['sha256'] == expected, 'live_manifest_baseline_mismatch')
            actual = read_bytes(client, RUNTIME + '/' + relative)
            need(digest(actual) == expected and len(actual) == files[relative]['bytes'], 'live_bytes_baseline_mismatch')
            hashes[relative] = digest(actual)
            if relative.startswith('public/') and not relative.endswith('.php') and relative != 'public/.htaccess':
                need(digest(read_bytes(client, DOCROOT + '/' + relative[7:])) == expected, 'published_static_baseline_mismatch')
        report.update(candidateCommit=candidate['commit'], candidateInventorySha256=candidate['inventorySha256'],
            candidateUiHashes={name: candidate['files'].get(name, {}).get('sha256') for name in candidate['changes']},
            canonicalMigrationSourceCount=sum(name.startswith('app/database/migrations/') and name.endswith('.sql') for name in candidate['files']),
            candidateAdditionAbsenceVerified=False,
            changedPublicCount=sum(n.startswith('app/public/') for n in candidate['changes']),
            changedViewCount=sum(n.startswith('app/views/') for n in candidate['changes']),
            observedFileHashes=hashes, immutableSourceDiffVerified=True,
            inspectionComplete=True, composerArtifactEquivalenceVerified=False)
        need(digest(read_bytes(client, RUNTIME + '/config/runtime.php')) == report['configSha256'], 'config_changed_during_inspection')
    except Exception as error:
        report['inspectionComplete'] = False
        report['errorCode'] = error.code if type(error) is Refusal else 'provider_or_contract_failed'
    return report


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('mode', choices=['inspect'])
    parser.add_argument('--report', type=Path, required=True)
    args = parser.parse_args()
    os.umask(0o077)
    try:
        candidate = source_inventory(Path(__file__).resolve().parents[1])
        need(os.environ.get('A777_CPANEL_HOST') == 'cp73.domains.co.za' and
             os.environ.get('A777_CPANEL_PORT') == '2083' and
             os.environ.get('A777_CPANEL_USER') == 'archite4' and
             os.environ.get('A777_CPANEL_ACCOUNT_HOME') == HOME, 'configuration_identity_unknown')
        client = InspectionClient(CpanelConfig(host='cp73.domains.co.za', port=2083, username='archite4',
                              password='', account_home=HOME, allowed_root=HOME,
                              api_token=os.environ.get('A777_CPANEL_API_TOKEN')))
        report = inspect(client, candidate)
    except Exception as error:
        report = {'mode': 'inspect', 'deployed': False, 'writesPerformed': False,
                  'inspectionComplete': False, 'nativeCanonicalVerified': False,
                  'errorCode': error.code if type(error) is Refusal else 'configuration_or_source_failed'}
    with args.report.open('x', encoding='utf-8') as stream:
        json.dump(report, stream, sort_keys=True)
        stream.write('\n')
    print(json.dumps(report, sort_keys=True))
    return 0 if report['inspectionComplete'] else 1


if __name__ == '__main__':
    raise SystemExit(main())
