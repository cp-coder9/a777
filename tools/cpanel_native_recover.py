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


RECOVERY_KEYS = frozenset(('format', 'mode', 'commit', 'expectedRecoveryCommit', 'releaseCommit', 'oldPlanSha256', 'planSha256', 'recoveredDeploymentId', 'phase', 'deployed', 'sourceMigrationsReplayed', 'sourceAlreadyCurrent', 'sourceAppliedPriorCount', 'maintenanceActive', 'maintenanceRetained', 'appPrivilegesRestored', 'operatorPrivilegesRevoked', 'operatorStateUnknown', 'sourcePrivilegeStateUnknown', 'appConfigDeltaVerified', 'readinessPassed', 'artifactVerified', 'backupId', *HASH_FIELDS))
RECOVERY_BOOL_KEYS = frozenset(('deployed', 'sourceMigrationsReplayed', 'sourceAlreadyCurrent', 'maintenanceActive', 'maintenanceRetained', 'appPrivilegesRestored', 'operatorPrivilegesRevoked', 'operatorStateUnknown', 'sourcePrivilegeStateUnknown', 'appConfigDeltaVerified', 'readinessPassed', 'artifactVerified'))


def recovery_metadata(client, recovery, plan_sha, old_sha, release, prior, previous):
    """Only exact fresh flat receipts; failure observations are not safety assertions."""
    found = []
    for row in p.entries(client, p.DEPLOY_ROOT):
        name = row.get('name')
        if not isinstance(name, str) or name in previous or not re.fullmatch(r'native-recover-' + recovery + r'-[a-f0-9]{16}\.json', name):
            continue
        data = json.loads(private_read(client, p.DEPLOY_ROOT + '/' + name))
        require(isinstance(data, dict) and set(data) == RECOVERY_KEYS)
        require(data['format'] == 'a777-cpanel-native-recover-v1' and data['mode'] == 'native-recover')
        require(data['commit'] == data['expectedRecoveryCommit'] == recovery and data['releaseCommit'] == release)
        require(data['planSha256'] == plan_sha and data['oldPlanSha256'] == old_sha and data['recoveredDeploymentId'] == prior['deploymentId'])
        require(data['phase'] in ('complete', 'failed', 'recovery_required'))
        require(all(type(data[k]) is bool for k in RECOVERY_BOOL_KEYS))
        require(type(data['sourceAppliedPriorCount']) is int and 0 <= data['sourceAppliedPriorCount'] <= 100000)
        require(all(data[k] == prior[k] or (data['phase'] != 'complete' and data[k] is None) for k in (*HASH_FIELDS, 'backupId')))
        found.append({k: data[k] for k in RECOVERY_KEYS})
    require(len(found) <= 1)
    return found[0] if found else None


# The PHP owner must emit only these fixed codes, never exception messages.
DIAGNOSTIC_CODES = frozenset(('operation_refused', 'recovery_refused', 'source_audit_target_missing', 'source_state_invalid', 'private_lease_invalid', 'current_schema_invalid', 'recovery_evidence_refused', 'native_recovery_contract_refused', 'native_deployment_refused', 'unclassified'))


def recovery_diagnostics(client, recovery, previous):
    found = []
    for row in p.entries(client, p.DEPLOY_ROOT):
        name = row.get('name')
        if not isinstance(name, str) or name in previous or not re.fullmatch(r'recoverydiag-' + recovery + r'-[a-f0-9]{16}\.json', name):
            continue
        data = json.loads(private_read(client, p.DEPLOY_ROOT + '/' + name))
        require(isinstance(data, dict) and set(data) == {'file', 'reasonCode', 'line'})
        require(data['file'] in ('CpanelNativeRecovery.php', 'CpanelNativeDeploymentRecovery.php', 'CpanelNativeDeployment.php', 'cpanel_git_deploy.php'))
        require(data['reasonCode'] in DIAGNOSTIC_CODES)
        require(type(data['line']) is int and 1 <= data['line'] <= 100000)
        found.append({k: data[k] for k in ('file', 'reasonCode', 'line')})
    return found


def failed_recovery_guard(client, recovery):
    rows = client._uapi('VersionControlDeployment', 'retrieve', {})
    require(isinstance(rows, list) and all(isinstance(row, dict) for row in rows))
    rows = [row for row in rows if row.get('repository_root') == p.ROOT]
    require(rows)
    for row in rows:
        require(re.fullmatch(r'[0-9]+', str(row.get('deploy_id', ''))))
        require(isinstance(row.get('timestamps'), dict))
        states = [key for key in ('succeeded', 'failed', 'canceled') if re.fullmatch(r'[0-9]+(?:\.[0-9]+)?', str(row['timestamps'].get(key, '')))]
        require(len(states) == 1)  # Unknown or running tasks forbid all pointer writes.
    latest = max(rows, key=lambda row: int(row['deploy_id']))
    matches = [row for row in rows if isinstance(row.get('repository_state'), dict) and row['repository_state'].get('identifier') == recovery]
    require(len(matches) == 1 and matches[0] == latest)
    require(any(re.fullmatch(r'[0-9]+(?:\.[0-9]+)?', str(latest['timestamps'].get(key, ''))) for key in ('failed', 'canceled')))
    require(isinstance(latest.get('task_id'), str) and re.fullmatch(r'[A-Za-z0-9/-]{1,128}', latest['task_id']))
    return latest


def pointer_guard_commit(client, plan, pointer):
    recovery = pointer['expectedRecoveryCommit']
    if recovery != plan['expectedRecoveryCommit']:
        failed_recovery_guard(client, recovery)
        return recovery
    # Resume only an exact newly prepared plan that has never been dispatched.
    rows = client._uapi('VersionControlDeployment', 'retrieve', {})
    require(isinstance(rows, list) and all(isinstance(row, dict) for row in rows))
    rows = [row for row in rows if row.get('repository_root') == p.ROOT]
    require(rows and not any(isinstance(row.get('repository_state'), dict) and row['repository_state'].get('identifier') == recovery for row in rows))
    require(all(re.fullmatch(r'[0-9]+', str(row.get('deploy_id', ''))) for row in rows))
    latest = max(rows, key=lambda row: int(row['deploy_id']))
    previous = commit((latest.get('repository_state') or {}).get('identifier'))
    failed_recovery_guard(client, previous)
    shadow = dict(plan, expectedRecoveryCommit=previous)
    previous_raw = private_read(client, p.DEPLOY_ROOT + '/recovery-plan-' + previous + '.json')
    if 'privateRuntimeRelocation' not in json.loads(previous_raw):
        shadow.pop('privateRuntimeRelocation', None)
        shadow.pop('privateRuntimeDestination', None)
    require(previous_raw == encoded(shadow) + '\n')
    return previous


def validated_pointer(client, plan, original, consumed_path, consumed):
    before = private_read(client, POINTER)
    pointer = json.loads(before)
    require(isinstance(pointer, dict) and set(pointer) == {'mode', 'expectedRecoveryCommit', 'planPath', 'planSha256'})
    recovery = commit(pointer['expectedRecoveryCommit'])
    require(pointer['mode'] == 'native-recover' and pointer['planPath'] == p.DEPLOY_ROOT + '/recovery-plan-' + recovery + '.json')
    require(isinstance(pointer['planSha256'], str) and re.fullmatch(r'[a-f0-9]{64}', pointer['planSha256']))
    raw = private_read(client, pointer['planPath'])
    require(digest(raw.encode('ascii')) == pointer['planSha256'])
    shadow = dict(plan, expectedRecoveryCommit=recovery)
    if recovery != plan['expectedRecoveryCommit'] and 'privateRuntimeRelocation' not in json.loads(raw):
        shadow.pop('privateRuntimeRelocation', None)
        shadow.pop('privateRuntimeDestination', None)
    require(json.loads(raw) == shadow and raw == encoded(shadow) + '\n')
    pointer_guard_commit(client, plan, pointer)
    require(private_read(client, p.PLAN) == original and private_read(client, consumed_path) == consumed)
    require(private_read(client, POINTER) == before and private_read(client, pointer['planPath']) == raw)
    return before


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
            directory = row.get('type') in ('dir', 'directory')
            # Promotion retains the original public docroot, whose normal mode
            # is 0755. Recovery validates its canonical ownership and inventory
            # on-host; it is not a private recovery-output directory.
            mode = '0700' if directory else '0600'
            if name in {'old-docroot', 'old-runtime'} and directory:
                observed = str(row.get('nicemode', '')).lstrip('0')
                require(observed in {'700', '750', '755'})
                mode = '0' + observed
            report.setdefault('workInventory', []).append({'name': name, 'type': row.get('type'), 'mode': str(row.get('nicemode', ''))})
            p.owned_mode(client, work + '/' + name, mode, directory)
        stage = 'approved_recovery_code_pull'
        pulled = p.clone(client, p.DEPLOY_KEY_PIN)
        require(pulled.get('status') == 'cloned_and_pulled' and pulled.get('commit') == expected)
        plan_path = p.DEPLOY_ROOT + '/recovery-plan-' + expected + '.json'
        # Existing recovery state is validated below before any private writes.
        plan = {'mode': 'native-recover', 'account': 'archite4', 'repository': p.ROOT, 'target': p.HOME,
                'privateRuntimeRelocation': True,
                'privateRuntimeDestination': '/home/archite4/.a777-runtime-private/7eee8a25050fadb3d36b74c40f485061',
                'expectedRecoveryCommit': expected, 'releaseCommit': release, 'oldPlanSha256': old_sha,
                'recoveredDeploymentId': prior['deploymentId'], 'originalPlanPath': p.PLAN,
                'artifactPath': old['artifactPath'], 'backupId': prior['backupId'],
                **{k: prior[k] for k in HASH_FIELDS}}
        text = encoded(plan) + '\n'
        plan_sha = digest(text.encode('ascii'))
        pointer = encoded({'mode': 'native-recover', 'expectedRecoveryCommit': expected, 'planPath': plan_path, 'planSha256': plan_sha}) + '\n'
        stage = 'separate_private_recovery_plan'
        prior_pointer = validated_pointer(client, plan, original, marker_path, consumed) if p.exists(client, POINTER) else None
        plan_exists = p.exists(client, plan_path)
        if plan_exists:
            require(prior_pointer is not None and private_read(client, plan_path) == text)
            # pointer_guard_commit refuses any task already triggered at this SHA.
            pointer_guard_commit(client, plan, json.loads(prior_pointer))
        else:
            client.save_file(plan_path, text); p.private_chmod(client, plan_path)
        require(private_read(client, plan_path) == text)
        if prior_pointer is not None:
            pointer_guard_commit(client, plan, json.loads(prior_pointer))
            require(private_read(client, POINTER) == prior_pointer)
        else:
            require(not p.exists(client, POINTER))
        require(private_read(client, p.PLAN) == original and private_read(client, marker_path) == consumed)
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
        report['terminalTaskState'] = terminal
        stage = 'fresh_recovery_metadata'
        report['recoveryDiagnostics'] = recovery_diagnostics(client, expected, previous)
        final_path = work + '/final-readiness-check.json'
        if p.exists(client, final_path):
            final = json.loads(private_read(client, final_path))
            require(isinstance(final, dict) and set(final) == {'status', 'hasJson', 'readinessStatus', 'components', 'maintenanceLeasePresent'})
            require(type(final['status']) is int and type(final['hasJson']) is bool and type(final['maintenanceLeasePresent']) is bool)
            states = {None, 'ready', 'not_ready', 'unavailable', 'degraded', 'error'}
            require(final['readinessStatus'] in states and isinstance(final['components'], dict) and set(final['components']) == {'database', 'migrations', 'jobs', 'mail'} and all(v in states for v in final['components'].values()))
            report['finalReadiness'] = final
        response_path = work + '/web-probe-response.json'
        if p.exists(client, response_path):
            response_flags = json.loads(private_read(client, response_path))
            require(isinstance(response_flags, dict) and set(response_flags) == {'maintenanceBody', 'leaseMatched', 'emptyBody', 'proofPresent'} and all(type(v) is bool for v in response_flags.values()))
            report['webProbeResponse'] = response_flags
        preflight_path = work + '/web-probe-preflight.json'
        if p.exists(client, preflight_path):
            preflight = json.loads(private_read(client, preflight_path))
            flags = {'headerPresent', 'headerMatched', 'gateMatched', 'opcacheAvailable'}
            require(isinstance(preflight, dict) and set(preflight) == flags | {'probeStage'})
            require(all(type(preflight[k]) is bool for k in flags) and preflight['probeStage'] in {'authorization', 'opcache', 'bootstrap'})
            report['webProbePreflight'] = preflight
        for name in ('web-probe-check.json', 'runtime-origin-check.json'):
            diagnostic_path = work + '/' + name
            if p.exists(client, diagnostic_path):
                data = json.loads(private_read(client, diagnostic_path))
                allowed = {'status', 'curlErrorCode', 'proofPresent'} if name == 'web-probe-check.json' else {'transport', 'status', 'peerVerified', 'curlErrorCode'}
                require(isinstance(data, dict) and set(data) == allowed)
                require(type(data['status']) is int and 0 <= data['status'] <= 599 and type(data['curlErrorCode']) is int)
                if name == 'runtime-origin-check.json':
                    require(data['transport'] in {'account-origin-http', 'account-origin-https'} and type(data['peerVerified']) is bool)
                report[name] = data
        metadata = recovery_metadata(client, expected, plan_sha, old_sha, release, prior, previous)
        if metadata is not None:
            report['receipt'] = metadata
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
    except Exception as failure:
        report.update(error='native_recovery_refused_or_incomplete', failureStage=stage)
        import os, traceback
        report['failureLocations'] = [{'file': os.path.basename(frame.filename), 'line': frame.lineno, 'function': frame.name} for frame in traceback.extract_tb(failure.__traceback__) if os.path.basename(frame.filename) in {'cpanel_native_recover.py', 'cpanel_git_prepare.py', 'cpanel_native_stage.py'}]
        try:
            retained_token(client)
            report['tokenProtectionVerified'] = True
        except Exception:
            report['tokenProtectionVerified'] = False
    return report
