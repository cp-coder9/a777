"""Explicit recovery transport. Never builds artifacts or replays source migrations."""
import hashlib
import hmac
import json
from pathlib import Path
import re
import subprocess
import time
import urllib.request


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def live_readbacks(release):
    opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), NoRedirect())
    def read_json(path):
        request = urllib.request.Request('https://php.architex.co.za' + path, headers={'Cache-Control': 'no-cache'})
        with opener.open(request, timeout=30) as response:
            require(response.status == 200)
            raw = response.read(1048577)
            require(len(raw) <= 1048576)
            return json.loads(raw)
    build = read_json('/build-info.json')
    require(build.get('sourceCommit') == release and build.get('sourceDirty') is False and build.get('environment') == 'production')
    ready = read_json('/api/v1/readiness').get('data')
    require(isinstance(ready, dict) and ready.get('status') == 'ready')
    components = ready.get('components')
    require(isinstance(components, dict) and all(isinstance(components.get(k), dict) and components[k].get('status') == 'ready' for k in ('database', 'migrations', 'jobs', 'mail')))
    return {'buildReleaseCommit': release, 'readinessPassed': True, 'coreComponentsReady': True}

import cpanel_git_prepare as p
from cpanel_native_stage import commit, digest, encoded, require

POINTER = p.DEPLOY_ROOT + '/recovery-plan-pointer.json'
HASH_FIELDS = ('archiveSha256', 'artifactTreeSha256', 'artifactManifestSha256', 'buildSha256')


def private_read(client, path):
    p.owned_mode(client, path, '0600')
    return p.read(client, path)


def retained_token(client):
    raw = private_read(client, p.TOKEN).encode('ascii')
    secret = client.config.api_token
    require(isinstance(secret, str) and re.fullmatch(r'[A-Za-z0-9_-]{16,4096}', secret))
    require(hmac.compare_digest(hashlib.sha256(raw).digest(), hashlib.sha256(secret.encode('ascii')).digest()))
    p.owned_mode(client, p.TOKEN, '0600')


def old_receipt(client, release, old_sha):
    found = []
    for row in p.entries(client, p.DEPLOY_ROOT):
        name = row.get('name', '')
        if isinstance(name, str) and re.fullmatch(r'native-deploy-' + release + r'-[a-f0-9]{16}\.json', name):
            data = json.loads(private_read(client, p.DEPLOY_ROOT + '/' + name))
            if data.get('planSha256') == old_sha:
                require(data.get('format') == 'a777-cpanel-native-deploy-v1' and data.get('commit') == release and data.get('mode') == 'native-deploy')
                require(data.get('phase') == 'recovery_required' and data.get('sourceMigrationCount') == 8 and type(data.get('sourceMigrationCount')) is int)
                require(all(data.get(k) is True for k in ('maintenanceActive', 'maintenanceRetained', 'sourcePrivilegesRevoked', 'operatorPrivilegesRevoked', 'sourceDatabaseDdlTouched', 'sourceMutationDdlStarted', 'artifactVerified')))
                require(all(data.get(k) is False for k in ('deployed', 'sourcePrivilegeStateUnknown', 'operatorStateUnknown')))
                require(isinstance(data.get('deploymentId'), str) and re.fullmatch(r'[a-f0-9]{32}', data['deploymentId']))
                require(isinstance(data.get('backupId'), str) and re.fullmatch(r'[A-Za-z0-9_-]{1,128}', data['backupId']))
                for key in HASH_FIELDS:
                    require(isinstance(data.get(key), str) and re.fullmatch(r'[a-f0-9]{64}', data[key]))
                found.append(data)
    require(len(found) == 1)
    return found[0]


def recovery_receipt(client, recovery, plan_sha, old_sha, release, prior, previous):
    found = []
    required = {'format', 'mode', 'commit', 'expectedRecoveryCommit', 'releaseCommit', 'oldPlanSha256', 'planSha256', 'recoveredDeploymentId', 'phase', 'deployed', 'sourceMigrationsReplayed', 'sourceAlreadyCurrent', 'sourceAppliedPriorCount', 'maintenanceActive', 'maintenanceRetained', 'appPrivilegesRestored', 'operatorPrivilegesRevoked', 'operatorStateUnknown', 'sourcePrivilegeStateUnknown', 'appConfigDeltaVerified', 'readinessPassed', 'artifactVerified', 'backupId', *HASH_FIELDS}
    for row in p.entries(client, p.DEPLOY_ROOT):
        name = row.get('name', '')
        if name in previous or not isinstance(name, str) or not re.fullmatch(r'native-recover-' + recovery + r'-[a-f0-9]{16}\.json', name):
            continue
        data = json.loads(private_read(client, p.DEPLOY_ROOT + '/' + name))
        require(isinstance(data, dict) and set(data) == required)
        require(data['format'] == 'a777-cpanel-native-recover-v1' and data['mode'] == 'native-recover')
        require(data['commit'] == data['expectedRecoveryCommit'] == recovery and data['releaseCommit'] == release)
        require(data['planSha256'] == plan_sha and data['oldPlanSha256'] == old_sha and data['recoveredDeploymentId'] == prior['deploymentId'])
        require(data['phase'] == 'complete' and type(data['sourceAppliedPriorCount']) is int and data['sourceAppliedPriorCount'] == 8)
        require(all(data[k] is True for k in ('deployed', 'sourceAlreadyCurrent', 'appPrivilegesRestored', 'operatorPrivilegesRevoked', 'appConfigDeltaVerified', 'readinessPassed', 'artifactVerified')))
        require(all(data[k] is False for k in ('sourceMigrationsReplayed', 'maintenanceActive', 'maintenanceRetained', 'operatorStateUnknown', 'sourcePrivilegeStateUnknown')))
        require(all(data[k] == prior[k] for k in (*HASH_FIELDS, 'backupId')))
        found.append(data)
    require(len(found) == 1)
    return found[0]


def native_recover(client, source, expected, attempts=360, pause=5):
    report = {'mode': 'native-recover', 'status': 'failed_or_incomplete', 'runtime_deployed': False, 'tokenAbsenceVerified': False, 'tokenRetainedForRecovery': True}
    stage = 'input'
    try:
        commit(expected)
        source = Path(source).resolve()
        require(subprocess.check_output(['git', '-C', str(source), 'rev-parse', 'HEAD']).decode().strip() == expected)
        require(subprocess.check_output(['git', '-C', str(source), 'status', '--porcelain', '--untracked-files=all']).decode().strip() == '')
        # PHP agent publishes this contract; refuse activation until dispatcher agrees.
        contract = json.loads((source / 'tools/native-recovery-contract.json').read_text())
        require(contract.get('mode') == 'native-recover' and contract.get('pointerPath') == POINTER and contract.get('receiptFormat') == 'a777-cpanel-native-recover-v1')
        stage = 'authenticated_original_state'
        p.authorize(client)
        p.owned_mode(client, p.DEPLOY_ROOT, '0700', True)
        original = private_read(client, p.PLAN)
        old = json.loads(original)
        require(old.get('mode') == 'native-deploy' and old.get('account') == 'archite4' and old.get('repository') == p.ROOT)
        release = commit(old.get('expectedCommit'))
        require(release != expected)
        old_sha = digest(original.encode('ascii'))
        prior = old_receipt(client, release, old_sha)
        require(all(old.get(k) == prior[k] for k in ('artifactTreeSha256', 'artifactManifestSha256', 'buildSha256')))
        require(old.get('artifactPath') == p.DEPLOY_ROOT + '/incoming/' + release)
        marker_path = p.DEPLOY_ROOT + '/native-consumed-' + old_sha + '.jsonl'
        consumed = private_read(client, marker_path)
        marker = json.loads(consumed)
        require(set(marker) == {'deploymentId', 'commit'} and marker['deploymentId'] == prior['deploymentId'] and marker['commit'] == release)
        retained_token(client)
        # Metadata only: no password-bearing native journals are exported/read.
        work = p.DEPLOY_ROOT + '/deployment-' + prior['deploymentId']
        p.owned_mode(client, work, '0700', True)
        for row in p.entries(client, work):
            name = row.get('name')
            require(isinstance(name, str) and re.fullmatch(r'[A-Za-z0-9_.-]+', name))
            p.owned_mode(client, work + '/' + name, '0700' if row.get('type') in ('dir', 'directory') else '0600', row.get('type') in ('dir', 'directory'))
        stage = 'approved_recovery_code_pull'
        pulled = p.clone(client, p.DEPLOY_KEY_PIN)
        require(pulled.get('status') == 'cloned_and_pulled' and pulled.get('commit') == expected)
        plan_path = p.DEPLOY_ROOT + '/recovery-plan-' + expected + '.json'
        require(not p.exists(client, plan_path) and not p.exists(client, POINTER))
        plan = {'mode': 'native-recover', 'account': 'archite4', 'repository': p.ROOT, 'target': p.HOME,
                'expectedRecoveryCommit': expected, 'releaseCommit': release, 'oldPlanSha256': old_sha,
                'recoveredDeploymentId': prior['deploymentId'], 'originalPlanPath': p.PLAN,
                'artifactPath': old['artifactPath'], 'backupId': prior['backupId'],
                **{k: prior[k] for k in HASH_FIELDS}}
        text = encoded(plan) + '\n'
        plan_sha = digest(text.encode('ascii'))
        pointer = encoded({'mode': 'native-recover', 'expectedRecoveryCommit': expected, 'planPath': plan_path, 'planSha256': plan_sha}) + '\n'
        stage = 'separate_private_recovery_plan'
        client.save_file(plan_path, text); p.private_chmod(client, plan_path)
        require(private_read(client, plan_path) == text)
        client.save_file(POINTER, pointer); p.private_chmod(client, POINTER)
        require(private_read(client, POINTER) == pointer and private_read(client, p.PLAN) == original and private_read(client, marker_path) == consumed)
        retained_token(client)
        previous = {r.get('name') for r in p.entries(client, p.DEPLOY_ROOT)}
        stage = 'recovery_task'
        created = client._uapi('VersionControlDeployment', 'create', {'repository_root': p.ROOT})
        require(isinstance(created, dict) and created.get('repository_root') == p.ROOT and re.fullmatch(r'[0-9]+', str(created.get('deploy_id', ''))) and isinstance(created.get('task_id'), str) and re.fullmatch(r'[A-Za-z0-9/-]{1,128}', created['task_id']))
        report.update(task_id=created['task_id'], deploy_id=str(created['deploy_id']))
        terminal = None
        for attempt in range(attempts):
            rows = client._uapi('VersionControlDeployment', 'retrieve', {})
            require(isinstance(rows, list) and all(isinstance(r, dict) for r in rows))
            matches = [r for r in rows if r.get('repository_root') == p.ROOT and str(r.get('deploy_id')) == report['deploy_id'] and r.get('task_id') == report['task_id']]
            require(len(matches) <= 1)
            if matches:
                row = matches[0]
                require(isinstance(row.get('repository_state'), dict) and row['repository_state'].get('identifier') == expected)
                states = [k for k in ('succeeded', 'failed', 'canceled') if re.fullmatch(r'[0-9]+(?:\.[0-9]+)?', str((row.get('timestamps') or {}).get(k, '')))]
                require(len(states) <= 1)
                if states:
                    terminal = states[0]; break
            if attempt + 1 < attempts: time.sleep(pause)
        require(terminal == 'succeeded')
        stage = 'fresh_recovery_receipt'
        receipt = recovery_receipt(client, expected, plan_sha, old_sha, release, prior, previous)
        require(private_read(client, p.PLAN) == original and private_read(client, plan_path) == text)
        stage = 'independent_live_readbacks'
        report['liveReadbacks'] = live_readbacks(release)
        report['receipt'] = receipt
        stage = 'closed_recovery_cleanup'
        require(private_read(client, POINTER) == pointer)
        retained_token(client)
        client.trash(POINTER); require(not p.exists(client, POINTER))
        require(p.cleanup_token(client, {'attempted': True}))
        report.update(status='native_recover_complete', runtime_deployed=True, tokenAbsenceVerified=True, tokenRetainedForRecovery=False, originalPlanUnchanged=True)
    except Exception:
        report.update(error='native_recovery_refused_or_incomplete', failureStage=stage)
        try:
            retained_token(client)
            report['tokenProtectionVerified'] = True
        except Exception:
            report['tokenProtectionVerified'] = False
    return report
