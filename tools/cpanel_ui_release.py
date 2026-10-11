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
SHIM_OBSERVATION_LIMIT = 4096
SHIM_PARSE_LIMIT = 1024


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
    def __init__(self, code, *, predicate=None):
        self.code = code
        self.predicate = predicate


def need(value, code, *, predicate=None):
    if not value:
        raise Refusal(code, predicate=predicate)


def digest(value):
    return hashlib.sha256(value).hexdigest()


def shim(name):
    need(name in ('index.php', 'app.php', 'legal.php'), 'entry_unknown')
    return ("<?php\ndeclare(strict_types=1);\numask(0077);\nrequire '" + RUNTIME + '/public/' + name + "';\n").encode()


def shim_shape(content, name):
    """Observe bytes only; no evaluation, normalization or new accepted form.

    Source chain: PrivateRuntimeRelocation::shim -> legacyShim ->
    NativeDeployment::publicShim. Both replacements preserve every other byte.
    Unknown source literals never leave this function.
    """
    if len(content) > SHIM_OBSERVATION_LIMIT:
        return {'oversize': True}
    expected = shim(name)
    legacy = expected.replace(b'umask(0077);\n', b'')
    old_root = HOME + '/public_html/.a777-runtime-active'
    native = legacy.replace(RUNTIME.encode('ascii'), old_root.encode('ascii'))
    forms = {expected: 'private_relocation_exact', legacy: 'private_legacy_exact',
             native: 'native_active_exact'}
    # Diagnostic grammar only: never returned as a root or used for binding.
    # Match the entire producer envelope within the existing parser byte bound.
    private_parent = (HOME + '/.a777-runtime-private/').encode('ascii')
    literal = None
    if len(content) <= SHIM_PARSE_LIMIT:
        literal = re.fullmatch(
            rb"<\?php\ndeclare\(strict_types=1\);\numask\(0077\);\nrequire '" +
            re.escape(private_parent) + rb"([A-Za-z0-9_-]{1,128})/public/" +
            re.escape(name.encode('ascii')) + rb"';\n", content)
    component = literal[1] if literal else None
    component_type = 'unmatched'
    if component is not None:
        component_type = ('lowercasehex32' if re.fullmatch(rb'[0-9a-f]{32}', component)
            else 'uuid36' if re.fullmatch(
                rb'[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}', component)
            else 'other')
    crlf = content.count(b'\r\n')
    lf = content.count(b'\n')
    bare_cr = content.count(b'\r') - crlf
    try:
        parse_shim(content, name)
        forms[content] = 'private_relocation_exact'
        structural_matched = True
    except Refusal:
        structural_matched = False
    return {'byteCount': len(content), 'sha256': digest(content),
            'crlfCount': crlf, 'lfCount': lf, 'bareCrCount': bare_cr,
            'finalNewline': content.endswith(b'\n'),
            'bomPresent': content.startswith(b'\xef\xbb\xbf'),
            'lineEndings': ('mixed' if bare_cr or (crlf and lf != crlf) else
                            'crlf' if crlf else 'lf' if lf else 'none'),
            'headerMatched': content.startswith(b'<?php\ndeclare(strict_types=1);\n'),
            'phpOpenCount': content.count(b'<?php'),
            'declareCount': content.count(b'declare(strict_types=1);'),
            'umaskCount': content.count(b'umask(0077);'),
            'literalRequireCount': len(re.findall(rb"require '[^'\r\n]*';", content)),
            'fixedPrivateParentPresent': private_parent in content,
            'repositoryParentPresent': (HOME + '/repositories/a777/').encode('ascii') in content,
            'literalComponentType': component_type,
            'literalComponentLength': len(component) if component is not None else None,
            'expectedEntryMatched': literal is not None,
            # Lexical account scope only; account identity is checked by inspect.
            # This is NOT native canonical-path or ownership evidence.
            'rootInsideVerifiedAccount': literal is not None,
            'producerWhitespaceEnvelopeMatched': literal is not None,
            'outputRootProvenance': 'unverified',
            'knownPrivateRootPresent': RUNTIME.encode('ascii') in content,
            'oldActiveRootPresent': old_root.encode('ascii') in content,
            'format': forms.get(content, 'unknown'),
            'accountScopedStructureMatched': structural_matched,
            'historicalTemplateParity': content in (expected, legacy, native),
            'currentProducerProvenanceVerified': False}


def file_content_shape(data):
    """Fixed encoding flags, never provider values/keys or file content.

    get_file_content returns a JSON string, not a base64 payload. Schema/example:
    https://api.docs.cpanel.net/specifications/cpanel.openapi/manage-files/get_file_content
    Its example uses from_char/to_char (and nullable from_charset/to_charset).
    The request
    explicitly converts ISO-8859-1 to UTF-8; encoding the decoded JSON string
    as Latin-1 reverses that conversion without newline/BOM normalization.
    """
    is_dict = type(data) is dict
    content = data.get('content') if is_dict else None
    if type(content) is str and len(content) > SHIM_OBSERVATION_LIMIT:
        return {'oversize': True}
    reversible = False
    if type(content) is str:
        try:
            content.encode('latin-1')
            reversible = True
        except UnicodeError:
            pass
    return {'dataType': 'dict' if is_dict else 'other',
            'contentPresent': is_dict and 'content' in data,
            'contentType': 'str' if type(content) is str else 'other',
            'latin1Reversible': reversible,
            'fromCharPresent': is_dict and 'from_char' in data,
            'toCharPresent': is_dict and 'to_char' in data,
            'fromCharMatchesRequest': is_dict and data.get('from_char') == 'ISO-8859-1',
            'toCharMatchesRequest': is_dict and data.get('to_char') == 'UTF-8',
            'fromCharsetPresent': is_dict and 'from_charset' in data,
            'toCharsetPresent': is_dict and 'to_charset' in data,
            'fromCharsetMatchesRequest': is_dict and data.get('from_charset') == 'ISO-8859-1',
            'toCharsetMatchesRequest': is_dict and data.get('to_charset') == 'UTF-8',
            'contract': 'json_string_latin1_to_utf8_no_base64'}


def parse_shim(content, name):
    # Whole reviewed template with an account-scoped root substitution only.
    # This is structural discovery, NOT proof of the current producer's origin.
    need(name in ('index.php', 'app.php', 'legal.php'), 'entry_unknown')
    need(type(content) is bytes and len(content) <= SHIM_PARSE_LIMIT, 'published_shim_unknown')
    pattern = (rb"<\?php\ndeclare\(strict_types=1\);\numask\(0077\);\nrequire '" +
               re.escape((HOME + '/.a777-runtime-private/').encode('ascii')) +
               rb"([0-9a-f]{32})/public/" + re.escape(name.encode('ascii')) + rb"';\n")
    match = re.fullmatch(pattern, content)
    need(match is not None, 'published_shim_unknown')
    return HOME + '/.a777-runtime-private/' + match[1].decode('ascii')


def safe_path(path, runtime_root=None):
    need(runtime_root is None or (isinstance(runtime_root, str) and
         re.fullmatch(re.escape(HOME + '/.a777-runtime-private/') + r'[0-9a-f]{32}', runtime_root)),
         'read_scope_refused')
    need(isinstance(path, str) and posixpath.normpath(path) == path and
         '\\' not in path and '\x00' not in path and
         any(path.startswith(root + '/') for root in (DOCROOT,) + ((runtime_root,) if runtime_root else ())), 'read_scope_refused')
    return path


def read_bytes(client, path):
    safe_path(path, getattr(client, '_inspection_bound_runtime', None)
              if getattr(client, '_inspection_active', False) else None)
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
    # Retain only a fixed shape for an allowlisted public entry. No provider
    # response (including path/filename/unknown fields) is retained or exported.
    if path in {DOCROOT + '/' + name for name in ('index.php', 'app.php', 'legal.php')}:
        client._inspection_shim_encoding = file_content_shape(data)
        need(client._inspection_shim_encoding != {'oversize': True}, 'published_shim_oversize')
    need(isinstance(data, dict) and isinstance(data.get('content'), str), 'file_content_unknown')
    try:
        return data['content'].encode('latin-1')
    except UnicodeError:
        raise Refusal('file_encoding_unknown') from None


def domain_shape(data):
    """Value-free observation of the existing getter, never a parser fallback.

    All keys are literals below; provider map keys and scalar values are never
    copied. Counts saturate at 20000; traversal stops at depth 8 / 20000 nodes.
    Depth is relative to the getter result (root=0), including opaque wrappers.
    """
    limit = 20000

    def kind(value):
        return 'dict' if type(value) is dict else 'list' if type(value) is list else 'other'

    def group(name):
        present = type(data) is dict and name in data
        value: Any = data[name] if present else None
        container = type(value) in (dict, list)
        return {'present': present, 'type': kind(value),
                'count': min(len(value), limit) if container else 0,
                'countBounded': container and len(value) > limit}

    main = data.get('main_domain') if type(data) is dict else None
    counts = {'dict': 0, 'list': 0, 'other': 0}
    max_depth = 0
    bounded = False
    remaining = limit

    def visit(value, depth):
        nonlocal remaining, max_depth, bounded
        if remaining == 0:
            bounded = True
            return
        remaining -= 1
        counts[kind(value)] += 1
        max_depth = max(max_depth, depth)
        if type(value) not in (dict, list):
            return
        if depth == 8:
            bounded = bounded or bool(value)
            return
        children = value.values() if type(value) is dict else value
        for child in children:
            if remaining == 0:
                bounded = True
                break
            visit(child, depth + 1)

    groups = ('main_domain', 'sub_domains', 'addon_domains', 'parked_domains')
    additional = {'dict': 0, 'list': 0, 'other': 0}
    row_counts = {}
    if type(data) is dict:
        for index, (name, value) in enumerate(data.items()):
            if index == limit:
                break
            if name not in groups:
                additional[kind(value)] += 1
    for name in groups:
        tally = {'dict': 0, 'list': 0, 'other': 0}
        value = data.get(name) if type(data) is dict else None
        entries = ([value] if name == 'main_domain' and type(data) is dict and name in data
                   else value.values() if type(value) is dict
                   else value if type(value) is list else ())
        for index, row in enumerate(entries):
            if index == limit:
                break
            tally[kind(row)] += 1
        row_counts[name] = tally
    visit(data, 0)
    return {'topLevelType': kind(data),
            'topLevelKeyCount': min(len(data), limit) if type(data) is dict else 0,
            'topLevelKeyCountBounded': type(data) is dict and len(data) > limit,
            'requiredGroupsCount': sum(name in data for name in groups) if type(data) is dict else 0,
            'additionalTopLevelTypeCounts': additional,
            'groupRowTypeCounts': row_counts,
            'groups': {name: group(name) for name in
                       ('main_domain', 'sub_domains', 'addon_domains', 'parked_domains')},
            'mainFieldPresence': {name: type(main) is dict and name in main for name in
                                  ('servername', 'domain', 'documentroot', 'user', 'homedir')},
            'maxDepth': max_depth, 'typeCounts': counts, 'traversalBounded': bounded}


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

    Official sources: api.docs.cpanel.net/openapi/cpanel/operation/domains_data
    and its /page-data/shared/oas-specifications/cpanel.openapi.yaml.json
    (HashOutput references Contents/MainDomainContents). HashOutput describes
    parked objects, but the operation example supplies parked hostname strings
    present in main_domain.serveralias. The separate official Domains contract
    docs.cpanel.net/cpanel/domains/domains/create-a-new-domain/132 defines parked
    aliases as pointing to Main Domain and sharing its document root. Accept
    only bounded literal string-list aliases with exact DNS-case-normalized
    main serveralias membership and a validated explicit main servername,
    root/account. Count these as main-vhost metadata, not independent vhosts;
    retain object-row validation for schema-compatible parked objects.
    """
    groups = ('main_domain', 'sub_domains', 'addon_domains', 'parked_domains')
    need(type(data) is dict and set(data) == set(groups), 'domain_inventory_unknown',
         predicate='domain_inventory_keys')
    need(type(data['main_domain']) is dict, 'domain_inventory_unknown',
         predicate='domain_inventory_main')
    need(authenticated_home is None or authenticated_home == HOME, 'domain_account_unknown')
    rows: list[tuple[str, Any, Any]] = [('main_domain', None, data['main_domain'])]
    for category in groups[1:]:
        value: Any = data[category]
        need(type(value) in (dict, list) and len(value) <= 20000, 'domain_inventory_unknown',
             predicate='domain_inventory_group')
        if type(value) is dict:
            need(all(isinstance(key, str) for key in value), 'domain_identity_unknown')
        entries = value.items() if type(value) is dict else ((None, row) for row in value)
        rows.extend((category, key, row) for key, row in entries)
    need(len(rows) <= 20000, 'domain_inventory_unknown', predicate='domain_inventory_total')
    roots, targets, identities = [], [], set()
    missing_user = missing_home = 0
    name_owners, wildcard_aliases = {}, []
    parked_aliases, main_alias_names = set(), set()
    for row_index, (category, key, row) in enumerate(rows):
        if category == 'parked_domains' and key is None and type(row) is str:
            # Official domains_data example supplies parked hostname strings
            # also present in main_domain.serveralias. cPanel's Domains docs
            # define parked aliases as sharing the Main Domain document root.
            # Main row (index 0) has already passed root/account validation;
            # this is metadata owned by that vhost, never an independent row.
            alias = row.lower()
            need(len(alias) <= 253 and '.' in alias and
                 re.fullmatch(r'[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?', alias) and
                 all(label and len(label) <= 63 and not label.startswith('-') and
                     not label.endswith('-') for label in alias.split('.')),
                 'domain_identity_unknown')
            main: Any = data['main_domain']
            need(main.get('servername') is not None and
                 alias in main_alias_names and
                 name_owners.get(alias) == 0 and alias not in identities and
                 alias not in parked_aliases and alias != DOMAIN,
                 'domain_identity_unknown')
            parked_aliases.add(alias)
            continue
        need(type(row) is dict, 'domain_inventory_unknown', predicate='domain_inventory_row')
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
        if category == 'main_domain':
            main_alias_names = {alias.lower() for alias in aliases.split()}
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
        need(not any(fnmatchcase(alias, pattern) for alias in parked_aliases),
             'domain_identity_unknown')
        need(not any(other != owner and fnmatchcase(name, pattern)
                     for name, other in name_owners.items()), 'domain_identity_unknown')
    need(targets == [DOCROOT], 'serving_root_unknown')
    if metadata is not None:
        metadata.update(rowCount=len(roots), missingUserCount=missing_user, missingHomeCount=missing_home)
        if parked_aliases:
            metadata.update(parkedAliasCount=len(parked_aliases),
                            mainBoundParkedAliasCount=len(parked_aliases))
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
    # Revoke prior authorization before account/domain reads can raise, and
    # invalidate cached provider observations for this fresh invocation.
    client._inspection_bound_runtime = None
    client._inspection_listings = {}
    client._inspection_active = True
    report = {'mode': 'inspect', 'deployed': False, 'writesPerformed': False,
              'nativeCanonicalVerified': False, 'nativeOwnershipVerified': False,
              'backupVerified': False, 'migrationLedgerVerified': False,
              'inspectionComplete': False, 'errorCode': None,
              'currentProducerProvenanceVerified': False,
              'deploymentAuthorized': False}
    try:
        need(client.account_home() == HOME, 'account_identity_unknown')
        domain_metadata = {}
        domain_data = client._uapi('DomainInfo', 'domains_data',
            {'format': 'hash', 'hide_temporary_domains': '0'})
        report['domainShape'] = domain_shape(domain_data)
        report['documentRoots'] = domain_roots(domain_data,
            authenticated_home=HOME, metadata=domain_metadata)
        report['domainAccountMetadata'] = domain_metadata
        client._inspection_bound_runtime = None
        runtime_roots = []
        for name in ('index.php', 'app.php', 'legal.php'):
            report['shimEntry'] = name
            report['shimStage'] = 'fileman_read'
            client._inspection_shim_encoding = None
            report.pop('shimShape', None)
            report.pop('shimEncoding', None)
            try:
                content = read_bytes(client, DOCROOT + '/' + name)
            finally:
                encoding = getattr(client, '_inspection_shim_encoding', None)
                if encoding is not None:
                    report['shimEncoding'] = encoding
            report['shimShape'] = shim_shape(content, name)
            report['shimStage'] = 'structural_binding'
            runtime_roots.append(parse_shim(content, name))
        need(len(set(runtime_roots)) == 1, 'published_shim_roots_mismatch')
        runtime = runtime_roots[0]
        need(all(not (runtime == root or runtime.startswith(root + '/') or root.startswith(runtime + '/'))
                 for root in report['documentRoots']), 'runtime_inside_document_root')
        client._inspection_bound_runtime = runtime
        report['shimStage'] = 'all_entries_bound'
        report.update(runtimeRootBindingVerified=True, runtimeRootPolicy='account_scoped_structural_discovery_v2',
                      boundShimCount=3, activeRuntimeSha256=digest(runtime.encode('ascii')))
        report['configSha256'] = digest(read_bytes(client, runtime + '/config/runtime.php'))
        metadata = json.loads(read_bytes(client, runtime + '/release.json'))
        need(isinstance(metadata, dict) and metadata.get('format') == 'architex-release-v1' and
             metadata.get('sourceCommit') == BASELINE and metadata.get('sourceDirty') is False and
             metadata.get('environment') == 'production', 'live_baseline_unknown')
        raw_manifest = json.loads(read_bytes(client, runtime + '/release-manifest.json'))
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
            actual = read_bytes(client, runtime + '/' + relative)
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
        need(digest(read_bytes(client, runtime + '/config/runtime.php')) == report['configSha256'], 'config_changed_during_inspection')
    except Exception as error:
        report['inspectionComplete'] = False
        report['errorCode'] = error.code if type(error) is Refusal else 'provider_or_contract_failed'
        if type(error) is Refusal and error.predicate in {
                'domain_inventory_keys', 'domain_inventory_main', 'domain_inventory_group',
                'domain_inventory_total', 'domain_inventory_row'}:
            report['domainInventoryPredicate'] = error.predicate
    finally:
        client._inspection_bound_runtime = None
        client._inspection_active = False
        client._inspection_listings = {}
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
