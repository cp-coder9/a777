"""Publish the generated radar artifact to an isolated GitHub Pages repository."""
import base64
import hashlib
import json
from pathlib import Path
import subprocess
import time
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
NAME = 'architex-technology-radar'


def api(endpoint, method='GET', payload=None, allowed_error=False):
    cmd = ['gh', 'api', endpoint, '--method', method]
    if payload is not None:
        cmd.extend(['--input', '-'])
    run = subprocess.run(cmd, input=json.dumps(payload) if payload is not None else None, capture_output=True, text=True, encoding='utf-8', errors='replace', cwd=ROOT)
    if run.returncode:
        if allowed_error and '(HTTP 404)' in run.stderr:
            return None
        raise RuntimeError(run.stderr.strip())
    return json.loads(run.stdout) if run.stdout.strip() else {}


def preflight():
    """Read and reproduce every artifact before even querying the remote API."""
    import generate_radar
    expected = generate_radar.render_document()
    outputs = [ROOT / path for path in (
        'docs/techmap/architex-techmap.html', 'docs/techmap/index.html',
        'app/public/assets/techmap/index.html', 'docs/techmap/architex-radar.html')]
    artifacts = [path.read_bytes() for path in outputs]
    for path, contents in zip(outputs, artifacts):
        if contents.decode('utf-8').replace('\r\n', '\n') != expected:
            raise RuntimeError(f'STALE: {path}')
    return artifacts[-1]


def main():
    local = preflight()
    readme = b'# Architex radar artifact-only publication\n\nThis repository hosts only the generated static technology radar. It does not contain the application codebase, credentials or deployment claim tokens. Status and relationships are source-backed declarations, not independent readiness certification.\n'
    publication = [('README.md', readme), ('.nojekyll', b''), ('index.html', local)]
    owner = api('user')['login']
    repo_path = owner + '/' + NAME
    existing = api('repos/' + repo_path, allowed_error=True)
    if existing:
        # Avoid overwriting any existing publication without a verified ownership marker.
        marker = api('repos/' + repo_path + '/contents/README.md', allowed_error=True)
        contents = base64.b64decode(marker.get('content','')).decode('utf-8') if marker else ''
        if not contents.startswith('# Architex radar artifact-only publication\n'):
            raise RuntimeError('Existing repository is not a verified artifact-only radar publication')
        if existing.get('full_name') != repo_path or existing.get('owner', {}).get('login') != owner:
            raise RuntimeError('Existing repository canonical owner/path mismatch')
        repo = existing
    else:
        repo = api('user/repos', 'POST', {'name':NAME, 'description':'Source-backed Architex technology radar — static artifact only', 'private':False, 'auto_init':True, 'has_issues':False, 'has_wiki':False, 'has_projects':False})
        verify_repo = api('repos/' + repo_path)
        assert verify_repo['full_name'] == repo_path, 'Created repository canonical path mismatch'
        assert not verify_repo['private'], 'Publication repository must be public for this Pages deployment'
        repo = verify_repo
    if repo['private']:
        raise RuntimeError('Publication repository must be public')
    branch = repo['default_branch']


    def publish(filename, contents):
        current = api('repos/' + repo_path + '/contents/' + filename, allowed_error=True)
        if current and base64.b64decode(current.get('content','')) == contents:
            print('unchanged', filename)
            return
        payload = {'message':'Publish polished Architex radar artifact', 'content':base64.b64encode(contents).decode('ascii'), 'branch':branch}
        if current:
            payload['sha'] = current['sha']
        api('repos/' + repo_path + '/contents/' + filename, 'PUT', payload)
        saved = api('repos/' + repo_path + '/contents/' + filename)
        assert base64.b64decode(saved['content']) == contents, 'Remote artifact differs: ' + filename
        print('verified published file', filename)


    for filename, contents in publication:
        publish(filename, contents)
    endpoint = 'repos/' + repo_path + '/pages'
    pages = api(endpoint, allowed_error=True)
    if not pages:
        api(endpoint, 'POST', {'build_type':'legacy', 'source':{'branch':branch, 'path':'/'}})
    else:
        api(endpoint, 'PUT', {'build_type':'legacy', 'source':{'branch':branch, 'path':'/'}})
    pages = api(endpoint)
    assert pages['source'] == {'branch':branch,'path':'/'}, 'Pages source mismatch'
    url = pages['html_url']
    print('Pages URL:', url)
    api('repos/' + repo_path + '/pages/builds', 'POST')
    for attempt in range(24):
        build = api('repos/' + repo_path + '/pages/builds/latest', allowed_error=True)
        status = build.get('status') if build else 'pending'
        print('Pages build:', status, flush=True)
        if status == 'errored':
            raise RuntimeError(json.dumps(build.get('error')))
        if status == 'built':
            break
        time.sleep(8)
    else:
        raise RuntimeError('Pages build has not completed in the bounded verification window')
    for attempt in range(8):
        try:
            response = urllib.request.urlopen(url + '?verification=' + str(attempt), timeout=40)
            remote = response.read()
            if remote == local:
                print('HTTP:', response.status)
                print('Exact artifact match SHA256:', hashlib.sha256(remote).hexdigest())
                break
            print('Waiting for published artifact edge propagation', flush=True)
        except Exception as error:
            print('Live read:', str(error), flush=True)
        time.sleep(8)
    else:
        raise RuntimeError('Public site has not returned the exact published artifact')
    record={'live_url':url,'repository_url':repo['html_url'],'branch':branch,'expires':None,'sha256':hashlib.sha256(local).hexdigest(),'http_status':response.status,'artifact':'docs/techmap/architex-radar.html'}
    print(json.dumps(record,indent=2))
    (ROOT / 'docs/evidence/techmap/github-pages-deployment.json').write_text(json.dumps(record,indent=2),encoding='utf-8')


if __name__ == '__main__':
    main()
