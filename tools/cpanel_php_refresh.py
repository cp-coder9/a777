#!/usr/bin/env python3
"""Explicit single-vhost same-version UAPI refresh; never deployment/DDL/file mutation.

Official schemas (retrieved before implementation):
https://api.docs.cpanel.net/specifications/cpanel.openapi/php-settings/langphp-php_set_vhost_versions.md
https://api.docs.cpanel.net/specifications/cpanel.openapi/php-settings/langphp-php_get_vhost_versions.md
https://api.docs.cpanel.net/specifications/cpanel.openapi/domain-information/domaininfo-single_domain_data.md
The documented setter accepts version and one vhost; no service restart is claimed.
A same-version call may regenerate provider configuration; cache clearance is unproven.
"""
import argparse
import json
import os
from pathlib import Path
import re
import urllib.error
import urllib.parse
import urllib.request
import time

from cpanel_client import CpanelClient, CpanelConfig, CpanelError, RejectRedirects
from cpanel_git_prepare import authorize, HOME

DOMAIN = 'php.architex.co.za'
DOCROOT = HOME + '/public_html/' + DOMAIN
RELEASE = '6f21a24c6fc7bca06b14d4d48d352d12135a2f9f'


class Refusal(CpanelError):
    def __init__(self, code):
        self.code = code
        super().__init__('PHP refresh refused.')


class RefreshClient(CpanelClient):
    """Reuse the authenticated transport, strictly normalize documented envelopes.

    Provider error text is never returned. providerStatus is the actual integer
    status; providerErrorCode is only an actual integer code when supplied.
    """
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.provider_status = None
        self.provider_error_code = None

    def _uapi(self, module, function, params=None, body=None, headers=None):
        self.last_operation = module + '/' + function
        self.provider_status = None
        self.provider_error_code = None
        payload = self._request_json('POST' if body is not None else 'GET',
                                     '/execute/' + module + '/' + function,
                                     params, body, headers)
        if 'result' in payload:
            if any(k in payload for k in ('status', 'data', 'errors')):
                raise Refusal('provider_envelope_invalid')
            result = payload['result']
        else:
            result = payload
        if not isinstance(result, dict) or type(result.get('status')) is not int or result['status'] not in (0, 1):
            raise Refusal('provider_envelope_invalid')
        self.provider_status = result['status']
        if type(result.get('error_code')) is int:
            self.provider_error_code = result['error_code']
        if result['status'] != 1:
            raise Refusal('provider_operation_failed')
        if 'errors' not in result or result['errors'] not in (None, []):
            raise Refusal('provider_envelope_invalid')
        return result.get('data')


def require(value, code):
    if not value:
        raise Refusal(code)


def vhost(client):
    data = client._uapi('LangPHP', 'php_get_vhost_versions', {'vhost': DOMAIN})
    rows = [data] if isinstance(data, dict) else data
    require(isinstance(rows, list) and len(rows) == 1 and isinstance(rows[0], dict), 'vhost_ambiguous')
    row = rows[0]
    require(all(row.get(k) == v for k, v in {'vhost': DOMAIN, 'account': 'archite4',
            'homedir': HOME, 'documentroot': DOCROOT}.items()), 'vhost_identity_mismatch')
    version = row.get('version')
    # Literal installed PHP 8.3 provider tokens only; never trim, translate or repair.
    require(isinstance(version, str) and re.fullmatch(r'(?:ea-php83|alt-php83)', version) is not None,
            'php83_version_required')
    return version


def domain_identity(client):
    data = client._uapi('DomainInfo', 'single_domain_data', {'domain': DOMAIN})
    if isinstance(data, dict) and isinstance(data.get('domain_data'), dict):
        data = data['domain_data']
    require(isinstance(data, dict) and data.get('documentroot') == DOCROOT,
            'documentroot_mismatch')
    require(data.get('domain', data.get('servername')) == DOMAIN and
            data.get('user') == 'archite4' and data.get('homedir') == HOME,
            'domain_identity_mismatch')


def public_get(path):
    opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), RejectRedirects())
    request = urllib.request.Request('https://' + DOMAIN + path + '?refresh=' + str(time.time_ns()),
                                    headers={'Cache-Control': 'no-cache, no-store', 'Accept': '*/*'})
    try:
        response = opener.open(request, timeout=30)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        limit = 4194304 if path == '/app' else 1048576
        body = response.read(limit + 1)
        require(len(body) <= limit, 'public_response_too_large')
        return response.code, body


def public_checks(report, get=public_get):
    # Collect every status even when one endpoint is a WAF HTML challenge or 503.
    parsed = {}
    for name, path in (('build', '/build-info.json'), ('readiness', '/api/v1/readiness'),
                       ('health', '/api/v1/health'), ('root', '/'), ('login', '/app')):
        try:
            status, body = get(path)
            report['httpStatus'][name] = status
            parsed[name] = body
        except Exception:
            report['httpStatus'][name] = None
    def obj(name):
        try:
            value = json.loads(parsed.get(name, b''))
            return value if isinstance(value, dict) else {}
        except (ValueError, UnicodeError):
            return {}
    build = obj('build')
    report['buildVerified'] = (report['httpStatus']['build'] == 200 and
        build.get('sourceCommit') == RELEASE and build.get('sourceDirty') is False and
        build.get('environment') == 'production')
    ready = obj('readiness').get('data')
    components = ready.get('components') if isinstance(ready, dict) else None
    report['readinessPassed'] = (report['httpStatus']['readiness'] == 200 and
        isinstance(ready, dict) and ready.get('status') == 'ready' and isinstance(components, dict) and
        all(isinstance(components.get(k), dict) and components[k].get('status') == 'ready'
            for k in ('database', 'migrations', 'jobs', 'mail')))
    report['healthPassed'] = (report['httpStatus']['health'] == 200 and obj('health').get('data') == {'status': 'ok'})
    root = parsed.get('root', b'')
    login = parsed.get('login', b'')
    report['entryPassed'] = (report['httpStatus']['root'] == 401 and
        b'<title>Sign in required</title>' in root and b'href="/app"' in root and
        report['httpStatus']['login'] == 200 and b'id="publicAccess"' in login and
        b'/assets/js/public-access.js' in login and b'<title>Architex OS' in login)
    report['deployed'] = all(report[k] for k in ('refreshPerformed', 'versionReadbackVerified',
        'documentrootVerified', 'buildVerified', 'readinessPassed', 'healthPassed', 'entryPassed'))


def refresh(client, get=public_get):
    report = {'mode': 'account-php-refresh', 'refreshPerformed': False, 'deployed': False,
              'version': None, 'versionReadbackVerified': False, 'documentrootVerified': False,
              'buildVerified': False, 'readinessPassed': False, 'healthPassed': False,
              'entryPassed': False, 'httpStatus': {}, 'errorCode': None,
              'providerStatus': None, 'providerErrorCode': None}
    try:
        authorize(client)
        version = vhost(client)
        domain_identity(client)
        report['version'] = version
        # Documented singular vhost parameter, unchanged literal version, form POST.
        data = client._uapi('LangPHP', 'php_set_vhost_versions',
                            body=urllib.parse.urlencode({'vhost': DOMAIN, 'version': version}).encode('ascii'),
                            headers={'Content-Type': 'application/x-www-form-urlencoded'})
        require(isinstance(data, dict) and data.get('vhosts') == [DOMAIN], 'setter_targets_unverified')
        require(vhost(client) == version, 'version_readback_mismatch')
        report['versionReadbackVerified'] = True
        # Successful setter + exact version readback proves operation, not readiness.
        report['refreshPerformed'] = True
        domain_identity(client)
        report['documentrootVerified'] = True
        public_checks(report, get)
        if not report['deployed']:
            report['errorCode'] = 'public_verification_failed'
    except Exception as error:
        report['errorCode'] = error.code if isinstance(error, Refusal) else 'transport_or_account_contract_failed'
        report['providerStatus'] = getattr(client, 'provider_status', None)
        report['providerOperation'] = getattr(client, 'last_operation', None)
        report['providerErrorCode'] = getattr(client, 'provider_error_code', None)
    return report


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('mode', choices=['account-php-refresh'])
    parser.add_argument('--report', type=Path, required=True)
    args = parser.parse_args()
    os.umask(0o077)
    try:
        config = CpanelConfig(host=os.environ.get('A777_CPANEL_HOST', ''),
            port=int(os.environ.get('A777_CPANEL_PORT', '2083')),
            username=os.environ.get('A777_CPANEL_USER', ''), password='',
            account_home=os.environ.get('A777_CPANEL_ACCOUNT_HOME', ''), allowed_root=HOME,
            api_token=os.environ.get('A777_CPANEL_API_TOKEN'))
        report = refresh(RefreshClient(config))
    except Exception:
        report = {'mode': 'account-php-refresh', 'refreshPerformed': False,
                  'deployed': False, 'errorCode': 'configuration_failed'}
    # Exclusive owner-private output; no raw response, headers, exception or secret.
    with args.report.open('x', encoding='utf-8') as stream:
        json.dump(report, stream, sort_keys=True)
        stream.write('\n')
    print(json.dumps(report, sort_keys=True))
    return 0 if report['deployed'] else 1


if __name__ == '__main__':
    raise SystemExit(main())
