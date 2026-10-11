"""Fixed isolated acceptance dependencies; never provision or reset a database."""
import argparse
import datetime
import hashlib
import json
import os
from pathlib import Path
import shutil
import socket
import subprocess
import time

ROOT = Path('E:/a777/.worktrees/material3expressive')
WORK = Path('E:/Hermes/home/cache/scratch/material3-runtime')
DATA = WORK / 'data'
DB = Path('E:/Hermes/mariadb/bin/mariadbd.exe')
PHP = shutil.which('php') or ''
NODE = shutil.which('node') or ''
HARNESS = ROOT / 'tools/v1-clone/material3-browser-menu-proof.cjs'
ENV = dict(os.environ, TEMP=str(WORK), TMP=str(WORK), TMPDIR=str(WORK))

def powershell(code):
    raw = subprocess.check_output(['powershell.exe', '-NoProfile', '-Command', code], env=ENV, text=True, timeout=30).strip()
    value = json.loads(raw) if raw else []
    return value if isinstance(value, list) else [value]

def processes():
    return powershell("Get-CimInstance Win32_Process | Where-Object {$_.Name -in @('mysqld.exe','mariadbd.exe','php.exe')} | Select-Object ProcessId,ExecutablePath,CommandLine | ConvertTo-Json -Compress")

def listeners():
    return powershell("Get-NetTCPConnection -State Listen -ErrorAction SilentlyContinue | Where-Object {$_.LocalPort -in @(59946,59947)} | Select-Object LocalAddress,LocalPort,OwningProcess | ConvertTo-Json -Compress")

def norm(value):
    return str(value or '').replace('\\', '/').lower()

def command(port):
    if port == 59946:
        return [str(DB), '--no-defaults', '--basedir=E:/Hermes/mariadb', '--datadir='+str(DATA), '--bind-address=127.0.0.1', '--port=59946', '--console']
    return [PHP, '-S', '127.0.0.1:59947', '-t', str(ROOT / 'app/public'), str(WORK / 'router.php')]

def owned(proc, port):
    cmd = norm(proc.get('CommandLine'))
    if port == 59946:
        return norm(proc.get('ExecutablePath')) == norm(DB) and '--no-defaults' in cmd and '--port=59946' in cmd and '--bind-address=127.0.0.1' in cmd and norm(DATA) in cmd
    return norm(proc.get('ExecutablePath')) == norm(PHP) and '127.0.0.1:59947' in cmd and norm(ROOT / 'app/public') in cmd and norm(WORK / 'router.php') in cmd

HASH_POLICY = 'runtime-frontend-allowlist-v2'

def protected_path(path):
    for part in path.parts:
        name = part.lower()
        if name == 'config' or name.startswith('.env') or '.private' in name or any(x in name for x in ('credential', 'secret', 'password')) or name in ('auth.json', 'auth.php', '.htpasswd', '.htaccess'):
            return True
    return False

def source_hash():
    h = hashlib.sha256(HASH_POLICY.encode())
    root = ROOT.resolve()
    files = []
    for relative in ('app/public', 'app/views', 'app/src'):
        base = ROOT / relative
        if protected_path(base.relative_to(ROOT)) or any(p.is_symlink() or p.is_junction() for p in (ROOT / 'app', base)):
            continue
        if not base.exists() or not base.resolve().is_relative_to(root):
            continue
        for directory, dirs, names in os.walk(base, followlinks=False):
            parent = Path(directory)
            dirs[:] = [name for name in dirs if not protected_path((parent/name).relative_to(ROOT)) and not (parent/name).is_symlink() and not (parent/name).is_junction()]
            for name in names:
                p = parent/name
                if protected_path(p.relative_to(ROOT)) or p.is_symlink() or p.is_junction():
                    continue
                if p.is_file() and p.resolve().is_relative_to(base.resolve()):
                    files.append(p)
    for p in sorted(files):
        h.update(p.relative_to(ROOT).as_posix().encode()); h.update(p.read_bytes())
    return h.hexdigest()

def require_native_verification(check):
    if any(check.get(key) is not True for key in ('receiptVerified', 'nativeMigrationCurrent', 'datadirVerified')):
        raise RuntimeError('Native verification predicates rejected')

def run_json(cmd):
    result = subprocess.run(cmd, env=ENV, cwd=ROOT, capture_output=True, text=True, timeout=600)
    if result.returncode:
        raise RuntimeError('Read-only dependency identity check failed: '+Path(cmd[1]).name)
    return json.loads(result.stdout)

def finalize(created, handles, receipt, out, before, rc):
    for child in reversed(created):
        row = {'pid': child.pid, 'gracefulRequested': False, 'gracefulSucceeded': False, 'gracefulTimeout': False, 'gracefulError': None, 'forced': False, 'stillAlive': True, 'errors': []}
        database = any(d['pid'] == child.pid and d['port'] == 59946 and d.get('created', True) for d in receipt['dependencies'])
        try:
            if child.poll() is None and database:
                row['gracefulRequested'] = True
                try:
                    shutdown = subprocess.run(['E:/Hermes/mariadb/bin/mariadb-admin.exe', '--no-defaults', '--host=127.0.0.1', '--port=59946', '--user=root', 'shutdown'], env=ENV, stdin=subprocess.DEVNULL, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=30)
                    if shutdown.returncode != 0:
                        raise RuntimeError('ShutdownRejected')
                    child.wait(timeout=60)
                    row['gracefulSucceeded'] = True
                except Exception as error:
                    row['gracefulTimeout'] = isinstance(error, (subprocess.TimeoutExpired, TimeoutError))
                    row['gracefulError'] = type(error).__name__
                    rc = 1
            if child.poll() is None:
                row['forced'] = True
                try:
                    child.terminate()
                    child.wait(timeout=20)
                except Exception as error:
                    row['errors'].append(type(error).__name__)
                    try:
                        child.kill()
                        child.wait(timeout=10)
                    except Exception as error:
                        row['errors'].append(type(error).__name__)
                        rc = 1
            row['stillAlive'] = child.poll() is None
            row['exitCode'] = child.returncode
        except Exception as error:
            row['errors'].append(type(error).__name__)
            rc = 1
        row['cleaned'] = not row['stillAlive']
        if row['stillAlive']:
            rc = 1
        receipt['createdProcessesCleaned'].append(row)
    for handle in handles:
        try:
            handle.close()
        except Exception as error:
            receipt.setdefault('handleCloseErrors', []).append(type(error).__name__)
            rc = 1
    receipt.update(sourceBefore=before, sourceHashPolicy=HASH_POLICY, unreadSensitive=True, candidateChanged=None)
    try:
        receipt['sourceAfter'] = source_hash()
        if before is None:
            rc = 1
        else:
            receipt['candidateChanged'] = before != receipt['sourceAfter']
            if receipt['candidateChanged']:
                rc = 1
    except Exception as error:
        receipt['sourceHashError'] = type(error).__name__
        rc = 1
    receipt['exitCode'] = rc
    receipt['finishedAt'] = datetime.datetime.now(datetime.timezone.utc).isoformat()
    target = out/'lifecycle.json'
    temporary = out/'lifecycle.json.tmp'
    temporary.write_text(json.dumps(receipt, indent=2))
    temporary.replace(target)
    print(json.dumps({'receipt': str(target), 'exitCode': rc, 'acceptance': receipt.get('acceptance'), 'collectorFailure': receipt.get('collectorFailure'), 'candidateChanged': receipt['candidateChanged']}))
    return rc

def reconcile_reports(reports):
    if not reports:
        raise ValueError('No batch reports')
    inventory = subprocess.run([NODE, '-e', 'console.log(JSON.stringify(require(process.argv[1]).sourceExpectedInventory()))', str(HARNESS)], cwd=ROOT, capture_output=True, text=True, timeout=30, check=True)
    source_parent = json.loads(inventory.stdout)['fullKeys']
    if len(source_parent) != 517 or len(set(source_parent)) != 517:
        raise ValueError('Source parent inventory mismatch')
    # Validate every parent and collector outcome before consuming scoped evidence.
    for report in reports:
        if 'collectorFailure' in report and report['collectorFailure'] is not False:
            raise ValueError('Failed or malformed batch collector outcome')
        fatal = report.get('fatal')
        if fatal is not None and (type(fatal) is not str or fatal != ''):
            raise ValueError('Fatal or malformed batch collector outcome')
        if 'candidateChanged' in report and report['candidateChanged'] is not False:
            raise ValueError('Changed or malformed batch candidate outcome')
        parent = report.get('parentFullExpectedKeys')
        count = report.get('parentFullExpected')
        if (not isinstance(parent, list) or not all(isinstance(key, str) and key.strip() for key in parent)
                or len(set(parent)) != len(parent) or type(count) is not int or count != len(parent)
                or parent != source_parent):
            raise ValueError('Invalid source-backed parent manifest')
    first = reports[0]
    identity = (first['candidateHash'], first['fixedIdentity'], first['manifestVersion'], first['harnessSha256'], first['parentFullExpectedKeys'])
    rows = {}
    scoped = set()
    for report in reports:
        if (report['candidateHash'], report['fixedIdentity'], report['manifestVersion'], report['harnessSha256'], report['parentFullExpectedKeys']) != identity or report.get('candidateChanged'):
            raise ValueError('Batch candidate/identity/version mismatch')
        expected = set(report['expectedKeys'])
        if len(expected) != len(report['expectedKeys']) or not expected.issubset(first['parentFullExpectedKeys']):
            raise ValueError('Invalid scoped manifest')
        for row in report['results']:
            if type(row.get('status')) is not str or row['status'] not in ('PASS', 'FAIL', 'UNSUPPORTED'):
                raise ValueError('Invalid batch result status')
        actual = {row['key'] for row in report['results']}
        if actual != expected or len(actual) != len(report['results']):
            raise ValueError('Batch result manifest mismatch')
        scoped.update(expected)
        for row in report['results']:
            key = row['key']
            if key in rows and rows[key] != row:
                raise ValueError('Conflicting duplicate evidence; explicit rerun adjudication required')
            rows[key] = row
    missing = set(first['parentFullExpectedKeys']) - scoped
    return {'expected': len(first['parentFullExpectedKeys']), 'completed': len(rows), 'missingKeys': sorted(missing), 'results': list(rows.values()), 'counts': {status: sum(r['status'] == status for r in rows.values()) for status in ('PASS', 'FAIL', 'UNSUPPORTED')}}

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--smoke', action='store_true')
    parser.add_argument('--full', action='store_true')
    parser.add_argument('--refresh-expired', action='store_true', help='Use existing guarded native fixture refresh; no business changes')
    parser.add_argument('--profiles-only', action='store_true')
    parser.add_argument('--batch', choices=['profiles', 'core'])
    parser.add_argument('--tools', help='Comma-separated exact native registry IDs')
    args = parser.parse_args()
    batch = args.batch or ('profiles' if args.profiles_only else None)
    if args.smoke and (batch or args.tools):
        parser.error('Smoke cannot select acceptance batches')
    if batch == 'profiles' and args.tools:
        parser.error('Profiles batch cannot select tools')
    selection = (['--batch', batch] if batch else [])
    if args.tools:
        ids = args.tools.split(',')
        known = {t['id'] for t in json.loads((ROOT/'app/public/assets/tool-registry.json').read_text())['tools']}
        if len(set(ids)) != len(ids) or not all(i in known for i in ids):
            parser.error('Duplicate or unknown native tool IDs')
        selection += ['--tools', args.tools]
    if datetime.datetime.now(datetime.timezone.utc) >= datetime.datetime(2026, 10, 11, 6, 45, tzinfo=datetime.timezone.utc) and not args.refresh_expired:
        parser.error('Fixture session expiry reached; guarded refresh required')
    if args.smoke == args.full:
        parser.error('Choose exactly one of --smoke or --full')
    stamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')
    out = WORK / ('supervisor-'+stamp)
    out.mkdir()
    receipt = {'supervisorPid': os.getpid(), 'scope': 'smoke' if args.smoke else 'full', 'dependencies': [], 'createdProcessesCleaned': [], 'rootCauseServerTermination': 'Not observable; previous processes absent, cause not established.', 'normalProductionLogin': False}
    created, handles = [], []
    rc = 1
    before = None
    try:
        before = source_hash()
        if not DATA.is_dir() or not (DATA / 'architex_taskm37aa281_0bf7a858_test').is_dir():
            raise RuntimeError('Owned initialized data folder absent; refusing initialization')
        state = json.loads((WORK / 'state.json').read_text())
        canonical = json.loads((WORK / 'receipt.json').read_text())
        if state['schema'] != 'architex_taskm37aa281_0bf7a858_test' or state['dbPort'] != 59946 or canonical['migrationCount'] != 69 or canonical['pending'] != 0 or canonical.get('datadirVerified') is not True:
            raise RuntimeError('Fixed schema/migration receipt guard rejected')
        for port in [59946, 59947]:
            procs = processes()
            listening = [x for x in listeners() if x['LocalPort'] == port]
            if listening:
                if len(listening) != 1 or listening[0]['LocalAddress'] != '127.0.0.1':
                    raise RuntimeError('Unknown/non-loopback listener')
                p = next((p for p in procs if p['ProcessId'] == listening[0]['OwningProcess']), {})
                if not owned(p, port):
                    raise RuntimeError('Unknown listener ownership; refusing reuse')
                receipt['dependencies'].append({'port': port, 'pid': p['ProcessId'], 'created': False, 'command': command(port)})
                continue
            if any((norm(DATA) in norm(p.get('CommandLine')) or not p.get('CommandLine') or Path(str(p.get('ExecutablePath') or '')).name.lower() in ['mysqld.exe','mariadbd.exe'] if port == 59946 else '127.0.0.1:59947' in norm(p.get('CommandLine'))) for p in procs):
                raise RuntimeError('Existing same-datadir/endpoint process is not listening; refusing duplicate')
            log = open(out / ('dependency-'+str(port)+'.log'), 'wb'); handles.append(log)
            child = subprocess.Popen(command(port), env=ENV, cwd=WORK, stdin=subprocess.DEVNULL, stdout=log, stderr=subprocess.STDOUT)
            created.append(child)
            receipt['dependencies'].append({'port': port, 'pid': child.pid, 'created': True, 'command': command(port)})
            deadline = time.monotonic()+480
            while True:
                if child.poll() is not None:
                    raise RuntimeError('Owned dependency exited before readiness')
                try:
                    with socket.create_connection(('127.0.0.1', port), 1):
                        break
                except OSError:
                    if time.monotonic() > deadline:
                        raise RuntimeError('Owned dependency readiness timeout')
                    time.sleep(.2)
            actual = next((p for p in processes() if p['ProcessId'] == child.pid), {})
            if not owned(actual, port):
                raise RuntimeError('Created dependency identity mismatch')
        receipt['inspect'] = run_json([PHP, str(WORK/'inspect.php')])
        if receipt['inspect']['migrationCount'] != 69:
            raise RuntimeError('Native migration count mismatch')
        receipt['bootstrapCheck'] = run_json([PHP, str(WORK/'http-bootstrap.php'), '--check'])
        require_native_verification(receipt['bootstrapCheck'])
        dry = run_json([PHP, str(WORK/'http-bootstrap.php'), '--dry-run'])
        if dry['lensCount'] != 22 or not all(norm(v).startswith(norm(ROOT/'app/src')+'/') for v in dry['candidatePaths'].values()):
            raise RuntimeError('Native candidate classpath mismatch')
        receipt['candidatePaths'] = dry['candidatePaths']
        if args.refresh_expired:
            journal = WORK/'session-refresh-intent.private.json'
            reconciled = False
            if journal.exists():
                old = json.loads(journal.read_text())
                if old.get('state') == 'intent':
                    receipt['sessionRefresh'] = run_json([PHP,str(WORK/'reconcile-test-session-refresh.php')])
                    old = json.loads(journal.read_text())
                    reconciled = True
                docs = [json.loads((WORK/name).read_text()) for name in ['fixture.json','browser-fixtures.private.json']]
                accounts = {a['userId']:a for d in docs for a in (d['accounts'] if 'accounts' in d else [d['owner'],d['viewer']])}
                if old.get('state') != 'completed' or old.get('ownedUsers') != 86 or old.get('httpVerified') != 86 or any(accounts.get(uid,{}).get('token') != session['token'] for uid,session in old.get('sessions',{}).items()):
                    raise RuntimeError('Prior refresh intent unresolved; refusing replay')
                if not reconciled:
                    journal.rename(out/'previous-refresh-intent.private.json')
            if not reconciled:
                receipt['sessionRefresh'] = run_json([PHP,str(WORK/'refresh-test-sessions.php')])
        for flag in ['--redaction-regression', '--preflight-regression', '--classification-regression', '--readiness-regression', '--auth-preflight-regression']:
            with open(out/(flag[2:]+'.jsonl'), 'wb') as log:
                test = subprocess.run([NODE, str(HARNESS), flag], env=ENV, cwd=ROOT, stdin=subprocess.DEVNULL, stdout=log, stderr=subprocess.STDOUT, timeout=180)
            if test.returncode:
                raise RuntimeError('Regression failed: '+flag)
        receipt['selection'] = selection
        cmd = [NODE, str(HARNESS), '--headless'] + (['--iframe-smoke'] if args.smoke else selection)
        with open(out/'acceptance.jsonl', 'wb') as log:
            test = subprocess.Popen(cmd, env=ENV, cwd=ROOT, stdin=subprocess.DEVNULL, stdout=log, stderr=subprocess.STDOUT)
            created.append(test)
            receipt['harnessPid'] = test.pid
            deadline = time.monotonic()+(600 if args.smoke else 7500)
            while test.poll() is None:
                if any(p.poll() is not None for p in created[:-1]):
                    raise RuntimeError('Collector dependency terminated during acceptance')
                if time.monotonic() > deadline:
                    raise RuntimeError('Bounded acceptance deadline exceeded')
                time.sleep(.5)
            rc = test.returncode
        rows = [json.loads(x) for x in (out/'acceptance.jsonl').read_text().splitlines() if x.strip()]
        final = rows[-1]
        receipt['acceptance'] = final
        report = json.loads((Path(final['out'])/'report.json').read_text())
        progress = [json.loads(x) for x in (Path(final['out'])/'progress.jsonl').read_text().splitlines()]
        if len(progress) != report['completed'] or len({x['key'] for x in progress}) != report['expected'] or report['missingKeys']:
            raise RuntimeError('Collector JSON/count integrity rejected')
        if args.smoke and (report['expected'] != 4 or report['counts'] != {'PASS':4,'FAIL':0,'UNSUPPORTED':0} or report.get('fatal') or report['candidateChanged']):
            raise RuntimeError('Smoke acceptance failed; no partial pass accepted')
        expected_keys = json.loads((Path(final['out'])/'expected.json').read_text())
        if len(expected_keys) != len(set(expected_keys)) or set(expected_keys) != {x['key'] for x in progress} or expected_keys != report['expectedKeys'] or report['parentFullExpected'] != 517:
            raise RuntimeError('Scoped expected-key manifest integrity rejected')
        receipt['expectedKeys'] = expected_keys
        receipt['parentFullExpected'] = report['parentFullExpected']
        receipt['jsonIntegrityVerified'] = True
    except Exception as error:
        receipt['collectorFailure'] = str(error)
        rc = 1
    finally:
        rc = finalize(created, handles, receipt, out, before, rc)
    return rc

if __name__ == '__main__':
    raise SystemExit(main())
