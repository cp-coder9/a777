#!/usr/bin/env python3
"""Owned same-value document-root update; no reload or public recovery inferred.

Schemas read before implementation:
https://api.docs.cpanel.net/cpanel-api-2/cpanel-api-2-modules-subdomain/cpanel-api-2-functions-subdomain-changedocroot
https://api.docs.cpanel.net/cpanel-api-2/cpanel-api-2-modules-subdomain/cpanel-api-2-functions-subdomain-listsubdomains
https://api.docs.cpanel.net/specifications/cpanel.openapi/subdomains/subdomain-changedocroot
UAPI equivalent exists (domain/docroot), but no fallback is attempted after refusal.
API2 uses subdomain/rootdomain/dir, relative to account home. Same-value
acceptance and vhost rebuild/reload are NOT promised by these schemas.
"""
import argparse
import ipaddress
import json
import os
from pathlib import Path
from cpanel_client import CpanelConfig
from cpanel_php_refresh import RefreshClient, Refusal, require, public_checks, public_get, DOMAIN, DOCROOT
from cpanel_git_prepare import authorize, HOME

RELATIVE = 'public_html/php.architex.co.za'
PARENT = 'architex.co.za'
OPERATIONS = {'Variables/get_user_information', 'DomainInfo/domains_data',
              'SubDomain/listsubdomains', 'SubDomain/changedocroot'}


class DocrootClient(RefreshClient):
    def _request_json(self, method, path, params=None, body=None, headers=None):
        payload = super()._request_json(method, path, params, body, headers)
        if path == '/json-api/cpanel':
            result = payload.get('cpanelresult')
            require(isinstance(result, dict) and result.get('module') == 'SubDomain' and
                    result.get('func') in ('listsubdomains', 'changedocroot') and
                    type(result.get('apiversion')) is int and result['apiversion'] == 2,
                    'provider_envelope_invalid')
            self.last_operation = 'SubDomain/' + result['func']
            event = result.get('event')
            require(isinstance(event, dict) and type(event.get('result')) is int and
                    event['result'] in (0, 1), 'provider_envelope_invalid')
            self.provider_status = event['result']
            self.provider_error_code = None
            require(event['result'] == 1, 'provider_operation_failed')
            require(not result.get('error'), 'provider_operation_failed')
            rows = result.get('data')
            require(isinstance(rows, list), 'provider_envelope_invalid')
            for row in rows:
                require(isinstance(row, dict), 'provider_envelope_invalid')
                if 'result' in row:
                    require(type(row['result']) is int and row['result'] in (0, 1), 'provider_envelope_invalid')
                    self.provider_status = row['result']
                    require(row['result'] == 1, 'provider_operation_failed')
        return payload


def identity(client):
    authorize(client)
    data = client._uapi('DomainInfo', 'domains_data', {'format': 'hash'})
    require(isinstance(data, dict), 'domain_inventory_invalid')
    main = data.get('main_domain')
    require(isinstance(main, dict) and main.get('domain') == PARENT,
            'primary_domain_mismatch')
    rows = data.get('sub_domains')
    require(isinstance(rows, list), 'subdomain_classification_unverified')
    targets = [r for r in rows if isinstance(r, dict) and r.get('domain') == DOMAIN]
    require(len(targets) == 1, 'subdomain_classification_unverified')
    row = targets[0]
    require(row.get('documentroot') == DOCROOT and row.get('user') == 'archite4' and
            row.get('homedir') == HOME, 'domain_identity_mismatch')
    ip = row.get('ip')
    try:
        require(isinstance(ip, str), 'assigned_ip_invalid')
        ipaddress.ip_address(ip)
    except ValueError:
        raise Refusal('assigned_ip_invalid') from None
    for category in ('addon_domains', 'parked_domains'):
        others = data.get(category)
        require(isinstance(others, list), 'domain_inventory_invalid')
        require(not any(isinstance(r, dict) and r.get('domain') == DOMAIN for r in others),
                'subdomain_classification_unverified')
    listed = client.api2('SubDomain', 'listsubdomains', {})
    matches = [r for r in listed if r.get('domain') == DOMAIN]
    require(len(matches) == 1 and all(matches[0].get(k) == v for k, v in
            {'subdomain': 'php', 'rootdomain': PARENT, 'domain': DOMAIN, 'dir': DOCROOT,
             'basedir': RELATIVE, 'reldir': 'home:' + RELATIVE}.items()),
            'subdomain_identity_mismatch')
    return ip


def refresh(client, get=public_get):
    report = {'mode': 'account-docroot-refresh', 'setterAttempted': False,
              'setterPerformed': False, 'apiReadbackVerified': False, 'publicRestored': False,
              'refreshPerformed': False, 'versionReadbackVerified': False,
              'documentrootVerified': False, 'deployed': False, 'buildVerified': False,
              'readinessPassed': False, 'healthPassed': False, 'entryPassed': False,
              'httpStatus': {}, 'errorCode': None}
    try:
        original = identity(client)
        report['setterAttempted'] = True
        result = client.api2('SubDomain', 'changedocroot',
                             {'subdomain': 'php', 'rootdomain': PARENT, 'dir': RELATIVE})
        require(len(result) == 1 and result[0].get('result') == 1 and
                result[0].get('dir') == DOCROOT and result[0].get('reldir') == RELATIVE,
                'setter_readback_invalid')
        report['setterPerformed'] = True
        require(identity(client) == original, 'assigned_ip_changed')
        require(identity(client) == original, 'assigned_ip_changed')
        report['apiReadbackVerified'] = True
        report['documentrootVerified'] = True
        report['refreshPerformed'] = True
        public_checks(report, get)
        report['publicRestored'] = all(report[k] for k in
                ('buildVerified', 'readinessPassed', 'healthPassed', 'entryPassed'))
        report['deployed'] = report['publicRestored'] and report['apiReadbackVerified']
        if not report['deployed']:
            report['errorCode'] = 'public_verification_failed'
    except Exception as error:
        report['errorCode'] = error.code if type(error) is Refusal else 'transport_or_account_contract_failed'
        operation = getattr(client, 'last_operation', None)
        report['providerOperation'] = operation if operation in OPERATIONS else None
        report['providerStatus'] = getattr(client, 'provider_status', None)
    # Internal compatibility inputs for the existing public verifier are not
    # PHP/version evidence and must not appear in the docroot result.
    for key in ('refreshPerformed', 'versionReadbackVerified', 'documentrootVerified'):
        report.pop(key, None)
    return report


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('mode', choices=['account-docroot-refresh'])
    parser.add_argument('--report', type=Path, required=True)
    args = parser.parse_args()
    os.umask(0o077)
    try:
        config = CpanelConfig(host=os.environ.get('A777_CPANEL_HOST', ''),
            port=int(os.environ.get('A777_CPANEL_PORT', '2083')),
            username=os.environ.get('A777_CPANEL_USER', ''), password='',
            account_home=os.environ.get('A777_CPANEL_ACCOUNT_HOME', ''), allowed_root=HOME,
            api_token=os.environ.get('A777_CPANEL_API_TOKEN'))
        report = refresh(DocrootClient(config))
    except Exception:
        report = {'mode': args.mode, 'setterAttempted': False, 'setterPerformed': False,
                  'apiReadbackVerified': False, 'publicRestored': False, 'deployed': False,
                  'errorCode': 'configuration_failed'}
    with args.report.open('x', encoding='utf-8') as stream:
        json.dump(report, stream, sort_keys=True)
        stream.write('\n')
    print(json.dumps(report, sort_keys=True))
    return 0 if report['deployed'] else 1


if __name__ == '__main__':
    raise SystemExit(main())
