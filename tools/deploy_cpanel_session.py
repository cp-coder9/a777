"""Deploy a verified release through a cPanel web session, preserving runtime settings."""
import http.cookiejar
import json
import os
import sys
import urllib.parse
import urllib.request
from dataclasses import asdict
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from tools.cpanel_client import CpanelClient, CpanelConfig, redact_text, RejectRedirects
from tools.repair_php_deployment import DeploymentConfig, DeploymentOrchestrator, UrllibHttpVerifier
from tools.deploy_php_ftps import probe_source

def main():
    if len(sys.argv) > 3 and sys.argv[3] == 'migrate':
        raise RuntimeError('Remote DDL disabled: legacy single helper cannot quiesce writers; use tools.production_migration.MigrationPipeline with a verified lease-owning HostOperations adapter.')
    host = os.environ['A777_CPANEL_HOST']
    user = os.environ['A777_CPANEL_USER']
    jar = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), RejectRedirects())
    credentials = urllib.parse.urlencode({'user': user, 'pass': os.environ['A777_CPANEL_PASSWORD']}).encode()
    with opener.open(f'https://{host}:2083/login/?login_only=1', credentials, timeout=30) as response:
        login = json.load(response)
    if login.get('status') != 1:
        raise RuntimeError('cPanel login failed')
    client = CpanelClient(CpanelConfig(host, 2083, user, '', '/home/archite4', '/home/archite4/public_html',
        session_id=login['security_token'].strip('/'),
        session_cookie=next('cpsession='+cookie.value for cookie in jar if cookie.name=='cpsession'), timeout=300))
    client.account_home()
    if len(sys.argv) > 2:
        import re
        token = sys.argv[2]
        if not re.fullmatch(r'\d{8}-\d{6}-[0-9a-f]{8}-[0-9a-f]{16}', token):
            raise RuntimeError('Invalid deployment identifier')
        name = '__a777_preflight_'+token+'.php'
        path = '/home/archite4/public_html/php.architex.co.za/'+name
        if len(sys.argv) > 3 and sys.argv[3] in ('status', 'migrate'):
            import secrets
            secret = secrets.token_hex(32)
            action = sys.argv[3]
            if action == 'migrate':
                raise RuntimeError('Remote DDL disabled: this helper cannot establish target identity, write quiescence and verified recoverable backup.')
            source = r'''<?php
declare(strict_types=1);
ini_set('display_errors', '0');
header('Content-Type: application/json');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals('__SECRET__', $_SERVER['HTTP_X_A777_DEPLOY'] ?? '')) { http_response_code(404); exit; }
try {
require dirname(__DIR__) . '/.a777-runtime-candidate-__TOKEN__/bootstrap.php';
$env = getenv();
$pdo = \Architex\Infrastructure\Database\ConnectionFactory::create(\Architex\Infrastructure\Config\AppConfig::fromEnvironment(is_array($env) ? $env : []));
$runner = new \Architex\Infrastructure\Database\MigrationRunner($pdo, dirname(__DIR__) . '/.a777-runtime-candidate-__TOKEN__/database/migrations');
$report = $runner->__ACTION__();
echo json_encode($report, JSON_THROW_ON_ERROR);
} catch (Throwable) { http_response_code(500); echo json_encode(['error' => 'Migration operation failed']); }
'''.replace('__SECRET__', secret).replace('__TOKEN__', token).replace('__ACTION__', action)
            try:
                client.save_file(path, source)
                request = urllib.request.Request('https://php.architex.co.za/'+name, data=b'', headers={'X-A777-Deploy': secret}, method='POST')
                with opener.open(request, timeout=300) as response:
                    print(response.read().decode())
            finally:
                client.trash(path)
                if client.exists(path):
                    raise RuntimeError('Temporary status helper cleanup unverified; recovery required')
            return
        try:
            client.save_file(path, probe_source('.a777-runtime-candidate-'+token).decode())
            check = UrllibHttpVerifier().request('https://php.architex.co.za/'+name)
            print(json.dumps(check.json()))
        finally:
            client.trash(path)
            if client.exists(path):
                raise RuntimeError('Temporary preflight helper cleanup unverified; recovery required')
        return
    runtime = client._uapi('Fileman', 'get_file_content', {'dir':'/home/archite4/public_html/.a777-runtime-active/config', 'file':'runtime.php'})['content']
    if not runtime.startswith('<?php'):
        raise RuntimeError('Active runtime configuration unavailable')
    config = DeploymentConfig(Path(sys.argv[1]), 'https://php.architex.co.za', '/home/archite4/public_html/php.architex.co.za', '/home/archite4')
    print('Staging deployment '+str(config.deployment_id), flush=True)
    try:
        result = DeploymentOrchestrator(config, client, UrllibHttpVerifier(), runtime_content=runtime.encode()).deploy()
    except Exception as error:
        print('Deployment failed; inspect recovery paths before retrying.', file=sys.stderr)
        raise
    print(json.dumps(asdict(result)), flush=True)

if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        print('Deployment stopped: '+type(error).__name__, file=sys.stderr)
        sys.exit(1)
