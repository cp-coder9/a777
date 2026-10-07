"""Locked production artifacts and explicitly authorized native cPanel deployment.

No provider operation runs on import. Incoming PHP is never executed by this runner.
"""
import base64
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import re
import secrets
import subprocess
import time
import zipfile

from cpanel_client import CpanelError
import cpanel_git_prepare as p


def require(condition):
    if not condition:
        raise CpanelError('Native deployment contract refused.')


def encoded(value):
    return json.dumps(value, ensure_ascii=False, separators=(',', ':'))


def digest(raw):
    return hashlib.sha256(raw).hexdigest()


def commit(value):
    require(isinstance(value, str) and re.fullmatch(r'[a-f0-9]{40}', value))
    return value


def inventory(root):
    files = {}
    for path in sorted(root.rglob('*')):
        require(not path.is_symlink())
        if path.is_dir():
            continue
        require(path.is_file())
        name = path.relative_to(root).as_posix()
        require(name.isascii() and '\\' not in name and not re.search(r'(^|/)(?:\.git|\.private|\.env(?:\.[^/]*)?|runtime\.php)(/|$)', name, re.I))
        raw = path.read_bytes()
        files[name] = {'sha256': digest(raw), 'bytes': len(raw)}
    return dict(sorted(files.items()))


def validate_release(root, expected):
    commit(expected)
    metadata = json.loads((root / 'release.json').read_text())
    require(metadata.get('sourceCommit') == expected and metadata.get('sourceDirty') is False and metadata.get('environment') == 'production')
    files = inventory(root)
    manifest = json.loads((root / 'release-manifest.json').read_text())
    require(manifest.get('format') == 'architex-release-manifest-v1')
    require(manifest['files'] == {k: v for k, v in files.items() if k != 'release-manifest.json'})
    require('public/.htaccess' in files and 'vendor/autoload.php' in files and len(files) >= 766)
    return {'artifactTreeSha256': digest(encoded(files).encode()),
            'artifactManifestSha256': files['release-manifest.json']['sha256'],
            'buildSha256': files['release.json']['sha256'], 'fileCount': len(files)}


def build(source, output, expected):
    source, output = Path(source).resolve(), Path(output).resolve()
    commit(expected)
    require(output != source and source not in output.parents)
    def git(*args):
        return subprocess.check_output(['git', '-C', str(source), *args]).decode().strip()
    require(git('rev-parse', 'HEAD') == expected and git('status', '--porcelain', '--untracked-files=all') == '')
    output.mkdir(parents=True, exist_ok=True)
    # Load the approved builder and verifier only; no application bootstrap or borrowed vendor.
    code = r'''if(PHP_VERSION_ID<80300)exit(2);$s=$argv[1];foreach(['ReleaseVerificationReport','ReleaseVerifier','ReleaseBuilder'] as $c)require $s.'/app/src/Infrastructure/Deployment/'.$c.'.php';$r=(new Architex\Infrastructure\Deployment\ReleaseBuilder($s,$argv[2]))->build($argv[3],'production',false);$v=(new Architex\Infrastructure\Deployment\ReleaseVerifier)->verify($r);if(!$v->valid)exit(3);echo $r;'''
    release = Path(subprocess.check_output(['php', '-r', code, str(source), str(output), expected]).decode().strip())
    result = validate_release(release, expected)
    require(git('rev-parse', 'HEAD') == expected and git('status', '--porcelain', '--untracked-files=all') == '')
    return release, result


def archive_release(release, archive, expected):
    result = validate_release(release, expected)
    with zipfile.ZipFile(archive, 'x', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as z:
        for name in inventory(release):
            info = zipfile.ZipInfo(expected + '/' + name, (1980, 1, 1, 0, 0, 0))
            info.create_system = 3
            info.external_attr = 0o100600 << 16
            info.compress_type = zipfile.ZIP_DEFLATED
            z.writestr(info, (release / name).read_bytes())
    validate_archive(archive, expected)
    return result


def validate_archive(archive, expected):
    commit(expected)
    with zipfile.ZipFile(archive) as z:
        names = z.namelist()
        require(len(names) == len(set(names)) and len(names) >= 766)
        for item in z.infolist():
            path = PurePosixPath(item.filename)
            require(item.filename.isascii() and '\\' not in item.filename and not path.is_absolute() and '..' not in path.parts and path.parts[0] == expected)
            require(not item.is_dir() and (item.external_attr >> 16) & 0o170000 == 0o100000)
        require(z.testzip() is None)


def completed_rehearsal(client, old, original):
    require(old.get('mode') == 'restore-rehearsal' and old.get('account') == 'archite4' and old.get('repository') == p.ROOT)
    old_commit = commit(old.get('expectedCommit'))
    plan_sha = digest(original.encode('ascii'))
    matched = []
    for row in p.entries(client, p.DEPLOY_ROOT):
        name = row.get('name', '')
        if not isinstance(name, str) or not re.fullmatch(r'restore-rehearsal-' + old_commit + r'-[a-f0-9]{16}\.json', name):
            continue
        path = p.DEPLOY_ROOT + '/' + name
        p.owned_mode(client, path, '0600')
        data = json.loads(p.read(client, path))
        if data.get('planSha256') != plan_sha:
            continue
        require(data.get('commit') == old_commit and p.rehearsal_safe(data, False))
        require(data.get('isolatedRehearsalPassed') is True and data.get('status') == 'rehearsal-verified' and data.get('lastCompletedStage') == 'rehearsal-verified')
        require(data.get('sourceDatabaseDdlTouched') is False and data.get('liveFilesTouched') is False)
        matched.append(data)
    require(len(matched) == 1)
    databases = client._uapi('Mysql', 'list_databases', {})
    require(isinstance(databases, list) and all(isinstance(r, dict) and isinstance(r.get('database'), str) for r in databases))
    require(sum(r['database'] == matched[0]['targetDatabase'] for r in databases) == 1)
    # Preserve encrypted recovery/password directories. Never inspect journal.jsonl.
    return plan_sha


def save_plan(client, plan):
    p.deployment_inventory_guard(client)
    if p.exists(client, p.PLAN):
        p.owned_mode(client, p.PLAN, '0600')
        original = p.read(client, p.PLAN)
        old_sha = completed_rehearsal(client, json.loads(original), original)
        archive = p.DEPLOY_ROOT + '/plan-roll-' + old_sha + '.json'
        require(not p.exists(client, archive))
        intent = encoded({'format': 'a777-preflight-plan-roll-intent-v1', 'originalBytesBase64': base64.b64encode(original.encode('ascii')).decode(), 'originalSha256': old_sha}) + '\n'
        client.save_file(archive, intent)
        p.private_chmod(client, archive)
        require(p.read(client, archive) == intent and p.read(client, p.PLAN) == original)
    text = encoded(plan) + '\n'
    client.save_file(p.PLAN, text)
    p.private_chmod(client, p.PLAN)
    require(p.read(client, p.PLAN) == text)
    return digest(text.encode('ascii'))


def native_receipt(client, expected, plan_sha, previous):
    found = []
    for row in p.entries(client, p.DEPLOY_ROOT):
        name = row.get('name', '')
        if name in previous or not isinstance(name, str) or not re.fullmatch(r'native-deploy-' + expected + r'-[a-f0-9]{16}\.json', name):
            continue
        p.owned_mode(client, p.DEPLOY_ROOT + '/' + name, '0600')
        data = json.loads(p.read(client, p.DEPLOY_ROOT + '/' + name))
        require(data.get('format') == 'a777-cpanel-native-deploy-v1' and data.get('commit') == expected and data.get('planSha256') == plan_sha and data.get('mode') == 'native-deploy')
        require(data.get('phase') in ('complete', 'failed', 'recovery_required'))
        safe = {k: data[k] for k in ('format', 'mode', 'commit', 'planSha256', 'phase')}
        for k in ('deployed', 'readinessPassed', 'maintenanceActive', 'sourcePrivilegesRevoked', 'sourcePrivilegeStateUnknown', 'operatorPrivilegesRevoked', 'operatorStateUnknown', 'artifactVerified', 'sourceDatabaseDdlTouched', 'maintenanceRetained'):
            if k in data:
                require(type(data[k]) is bool)
                safe[k] = data[k]
        for k in ('archiveSha256', 'buildSha256', 'artifactTreeSha256', 'artifactManifestSha256'):
            if k in data:
                require(isinstance(data[k], str) and re.fullmatch(r'[a-f0-9]{64}', data[k]))
                safe[k] = data[k]
        if 'sourceMigrationCount' in data:
            require(type(data['sourceMigrationCount']) is int and 0 <= data['sourceMigrationCount'] <= 100000)
            safe['sourceMigrationCount'] = data['sourceMigrationCount']
        if 'backupId' in data:
            require(isinstance(data['backupId'], str) and re.fullmatch(r'[A-Za-z0-9_-]{1,128}', data['backupId']))
            safe['backupId'] = data['backupId']
        require(data.get('deployed') is not True or data['phase'] == 'complete')
        if data['phase'] == 'complete':
            require(all(data.get(k) is True for k in ('deployed','readinessPassed','artifactVerified','operatorPrivilegesRevoked')))
            require(all(data.get(k) is False for k in ('maintenanceActive','maintenanceRetained','sourcePrivilegesRevoked','sourcePrivilegeStateUnknown','operatorStateUnknown')))
        found.append(safe)
    require(len(found) <= 1)
    return found[0] if found else None


def native_deploy(client, source, output, expected, config_sha, attempts=360, pause=5):
    report = {'mode': 'native-deploy', 'status': 'failed', 'runtime_deployed': False,
              'tokenAbsenceVerified': False, 'tokenRetainedForRecovery': False}
    state, previous, plan_sha = {}, set(), None
    issued = False
    authorized = False
    receipt = None
    stage = 'input'
    try:
        commit(expected)
        require(isinstance(config_sha, str) and re.fullmatch(r'[a-f0-9]{64}', config_sha))
        require(config_sha == '5206e3a1f9447e6834fc3e2dd7cd254ce5dd47af33406c0c83085c4b4f28d0b8')
        stage = 'production_build'
        release, artifact = build(source, output, expected)
        archive = Path(output) / ('incoming-' + expected + '-' + secrets.token_hex(8) + '.zip')
        archive_release(release, archive, expected)
        stage = 'account_authentication'
        p.authorize(client)
        authorized = True
        require(p.inventory(client) is not None)
        stage = 'repository_pull'
        pulled = p.clone(client, p.DEPLOY_KEY_PIN)
        report['remote_head'] = pulled.get('commit')
        require(pulled.get('status') == 'cloned_and_pulled' and pulled.get('commit') == expected)
        p.owned_mode(client, p.DEPLOY_ROOT, '0700', True)
        incoming = p.DEPLOY_ROOT + '/incoming'
        stage = 'private_inventory'
        p.deployment_inventory_guard(client)
        if not p.exists(client, incoming):
            client.mkdir(incoming, '0700')
        p.owned_mode(client, incoming, '0700', True)
        require(not p.exists(client, incoming + '/' + expected))
        remote_zip = incoming + '/' + archive.name
        require(not p.exists(client, remote_zip))
        stage = 'artifact_upload'
        client.upload_file(archive, remote_zip)
        p.private_chmod(client, remote_zip)
        stage = 'artifact_extraction'
        client.extract_archive(remote_zip)
        client._api2('fileop', {'op': 'chmod', 'sourcefiles': '.a777-deploy/incoming/' + expected,
                              'doubledecode': '0', 'metadata': '0700'})
        p.owned_mode(client, incoming + '/' + expected, '0700', True)
        # Exact manifest bytes read back through authenticated API. The reviewed native
        # hook must independently verify the full digest before executing any artifact PHP.
        stage = 'artifact_manifest_readback'
        actual = p.read(client, incoming + '/' + expected + '/release-manifest.json').encode('ascii')
        require(digest(actual) == artifact['artifactManifestSha256'])
        client.trash(remote_zip)
        require(not p.exists(client, remote_zip))
        require(config_sha == '5206e3a1f9447e6834fc3e2dd7cd254ce5dd47af33406c0c83085c4b4f28d0b8')
        pending = [f.stem for f in sorted((release / 'database/migrations').glob('*.sql'))][-8:]
        require(len(pending) == 8)
        plan = {'mode': 'native-deploy', 'account': 'archite4', 'repository': p.ROOT,
                'sourceDatabase': 'archite4_dev_rel1', 'runtimeConfigSha256': config_sha,
                'artifactPath': incoming + '/' + expected,
                'treeDigest': artifact['artifactTreeSha256'], 'pendingMigrations': pending,
                'activeRoot': p.HOME + '/public_html/.a777-runtime-active',
                'documentRoot': p.HOME + '/public_html/php.architex.co.za',
                'expectedCommit': expected, 'expectedRuntimeConfigSha256': config_sha,
                'readOnlyUapiTransport': 'native-cli-then-private-token-https',
                'artifactTreeSha256': artifact['artifactTreeSha256'],
                'artifactManifestSha256': artifact['artifactManifestSha256'],
                'buildSha256': artifact['buildSha256']}
        stage = 'operator_plan'
        plan_sha = save_plan(client, plan)
        previous = {r.get('name') for r in p.entries(client, p.DEPLOY_ROOT)}
        stage = 'token_staging'
        p.provision_token(client, state)
        issued = True  # Lost create response is potentially active, never assume no mutation.
        stage = 'native_task_create'
        created = client._uapi('VersionControlDeployment', 'create', {'repository_root': p.ROOT})
        require(isinstance(created, dict) and created.get('repository_root') == p.ROOT and re.fullmatch(r'[0-9]+', str(created.get('deploy_id', ''))) and isinstance(created.get('task_id'), str) and re.fullmatch(r'[A-Za-z0-9/-]{1,128}', created['task_id']))
        report.update(task_id=created['task_id'], deploy_id=str(created['deploy_id']), deployment_status='queued')
        stage = 'native_task_poll'
        for attempt in range(attempts):
            rows = client._uapi('VersionControlDeployment', 'retrieve', {})
            require(isinstance(rows, list) and all(isinstance(r, dict) for r in rows))
            matches = [r for r in rows if r.get('repository_root') == p.ROOT and str(r.get('deploy_id')) == report['deploy_id'] and r.get('task_id') == report['task_id']]
            require(len(matches) <= 1)
            if matches:
                row = matches[0]
                identifier = (row.get('repository_state') or {}).get('identifier')
                require(identifier == expected)
                terminal = [k for k in ('succeeded', 'failed', 'canceled') if re.fullmatch(r'[0-9]+(?:\.[0-9]+)?', str((row.get('timestamps') or {}).get(k, '')))]
                require(len(terminal) <= 1)
                if terminal:
                    report['deployment_status'] = terminal[0]
                    break
            if attempt + 1 < attempts:
                time.sleep(pause)
        stage = 'deployment_receipt'
        receipt = native_receipt(client, expected, plan_sha, previous)
        require(receipt is not None)
        report['receipt'] = receipt
        require(report['deployment_status'] == 'succeeded' and receipt['phase'] == 'complete' and receipt.get('deployed') is True and receipt.get('readinessPassed') is True and receipt.get('artifactVerified') is True)
        require(receipt.get('operatorPrivilegesRevoked') is True and receipt.get('operatorStateUnknown') is False and receipt.get('sourcePrivilegeStateUnknown') is False)
        require(receipt.get('maintenanceActive') is False and receipt.get('sourcePrivilegesRevoked') is False and receipt.get('sourceMigrationCount') == len(pending))
        require(all(receipt.get(k) == artifact[k] for k in ('artifactTreeSha256', 'artifactManifestSha256', 'buildSha256')))
        report.update(status='native_deploy_complete', runtime_deployed=True)
    except Exception as error:
        report['error'] = 'native_deployment_refused_or_incomplete'
        report['failureStage'] = stage
        import traceback
        frames = [frame for frame in traceback.extract_tb(error.__traceback__) if Path(frame.filename).name in ('cpanel_native_stage.py','cpanel_git_prepare.py','cpanel_client.py')]
        if frames:
            report['failureFile'] = Path(frames[-1].filename).name
            report['failureLine'] = frames[-1].lineno
        report['failureType'] = type(error).__name__ if type(error).__name__ in ('CpanelError','JSONDecodeError','ValueError','OSError') else 'operation_refused'
        if issued and plan_sha:
            try:
                receipt = native_receipt(client, expected, plan_sha, previous)
                if receipt:
                    report['receipt'] = receipt
            except Exception:
                pass
    finally:
        if authorized:
            # Unknown task/receipt state is conservative recovery, not proof of safety.
            retain = issued and (receipt is None or receipt.get('phase') == 'recovery_required'
                or receipt.get('sourcePrivilegesRevoked') is not False
                or receipt.get('sourcePrivilegeStateUnknown') is not False
                or receipt.get('operatorStateUnknown') is not False
                or receipt.get('operatorPrivilegesRevoked') is not True
                or receipt.get('maintenanceActive') is not False
                or receipt.get('maintenanceRetained') is True
                or (receipt.get('phase') != 'complete' and receipt.get('sourceDatabaseDdlTouched') is not False))
            if retain:
                report['tokenRetainedForRecovery'] = True
                report['recovery'] = 'manual_authenticated_recovery_required'
                try:
                    p.owned_mode(client, p.TOKEN, '0600')
                except Exception:
                    report['tokenProtectionVerified'] = False
                else:
                    report['tokenProtectionVerified'] = True
            else:
                try:
                    report['tokenAbsenceVerified'] = p.cleanup_token(client, state)
                except Exception:
                    report['cleanup'] = 'cleanupfailed'
            if not report['tokenAbsenceVerified']:
                report['status'] = 'failed_or_incomplete'
                report['runtime_deployed'] = False
    return report
