"""Authenticated source inspection for safe native retry (no build or source DDL)."""
from pathlib import Path
import re
import subprocess

from tools.migration_inspect import inspect_migrations, migration_manifest
from tools.repair_php_deployment import DeploymentConfig, DeploymentFailure

HOME = '/home/archite4'
DOCUMENT_ROOT = HOME + '/public_html/php.architex.co.za'
RUNTIME_ROOT = HOME + '/public_html/.a777-runtime-active'
TARGET_URL = 'https://php.architex.co.za'
DATABASE = 'archite4_dev_rel1'


def require(condition):
    if not condition:
        raise DeploymentFailure('Independent read-only source preflight refused.')


def readonly_source_preflight(source):
    """Capture the approved checkout, not caller-supplied JSON attestations.

    inspect_migrations returns only after its authenticated helper identity/cleanup
    and absence readback succeed. Exceptions must propagate before native build.
    """
    source = Path(source).resolve()

    def inspect(client, expected, config_sha):
        require(isinstance(expected, str) and re.fullmatch(r'[a-f0-9]{40}', expected))
        require(isinstance(config_sha, str) and re.fullmatch(r'[a-f0-9]{64}', config_sha))

        def clean_checkout():
            def git(*args):
                return subprocess.check_output(
                    ['git', '-C', str(source), *args], stderr=subprocess.DEVNULL
                ).decode('ascii').strip()
            require(Path(git('rev-parse', '--show-toplevel')).resolve() == source)
            require(git('rev-parse', 'HEAD') == expected)
            require(git('status', '--porcelain', '--untracked-files=all') == '')

        clean_checkout()  # Before any helper upload.
        manifest = migration_manifest(source / 'app/database/migrations')
        require(len(manifest) == 69)
        pending = sorted(manifest)[-8:]
        config = DeploymentConfig(
            release=source / 'app', target_url=TARGET_URL,
            document_root=DOCUMENT_ROOT, account_home=HOME,
        )
        result = inspect_migrations(config, client, database=DATABASE)
        require(isinstance(result, dict))
        identity = result.get('databaseIdentity')
        require(isinstance(identity, dict))
        require(all(identity.get(k) == v for k, v in {
            'database': DATABASE, 'runtimeConfigSha256': config_sha,
            'runtimeRoot': RUNTIME_ROOT, 'accountHome': HOME,
            'authenticatedAccount': 'archite4', 'targetUrl': TARGET_URL,
            'documentRoot': DOCUMENT_ROOT, 'inventorySource': 'UAPI Mysql/list_databases',
        }.items()))
        require(identity.get('independentlyConfirmed') is True)
        require(result.get('targetDatabaseMatches') is True)
        require(result.get('ledgerExists') is True and result.get('ledgerChecksumsMatch') is True)
        require(result.get('ddlAuthorized') is False)
        require(type(result.get('pendingCount')) is int and result['pendingCount'] == 8)
        require(result.get('pendingVersions') == pending)
        clean_checkout()
        require(migration_manifest(source / 'app/database/migrations') == manifest)
        return {'commit': expected, 'runtimeConfigSha256': config_sha,
                'sourceDatabase': DATABASE, 'readOnly': True,
                'sourceDatabaseDdlTouched': False, 'pendingMigrationCount': 8}

    return inspect
