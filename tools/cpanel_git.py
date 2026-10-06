#!/usr/bin/env python3
"""Explicit cPanel Git discovery, preparation and private native preflight."""
import argparse
import json
import os
import re
from pathlib import Path

from cpanel_client import CpanelClient, CpanelConfig, CpanelError

HOME = "/home/archite4"
ROOT = HOME + "/repositories/a777"
FIELDS = "name,repository_root,branch,deployable,last_update,last_deployment"


def safe_text(value, secrets=()):
    if not isinstance(value, str) or len(value) > 512:
        return None
    if any(secret and secret in value for secret in secrets):
        return None
    if "://" in value or "@" in value or "PRIVATE KEY" in value:
        return None
    return value if re.fullmatch(r"[A-Za-z0-9_./ -]+", value) else None


def sha(value):
    return value if isinstance(value, str) and re.fullmatch(r"[0-9a-fA-F]{40}", value) else None


def obj(value):
    return value if isinstance(value, dict) else {}


def repository(row, secrets=()):
    return {
        "name": safe_text(row.get("name"), secrets),
        "root": safe_text(row.get("repository_root"), secrets),
        "branch": safe_text(row.get("branch"), secrets),
        "deployable": row.get("deployable") if type(row.get("deployable")) is int and row["deployable"] in (0, 1) else None,
        "last_update_sha": sha(obj(row.get("last_update")).get("identifier")),
        "deploy_sha": sha(obj(obj(row.get("last_deployment")).get("repository_state")).get("identifier")),
    }


def require_mutation_root(root):
    """Future callers must not treat discovery of an arbitrary path as authority."""
    if root != ROOT:
        raise CpanelError("Unknown repository root; mutation refused.")
    return root


def inspect(client):
    report = {"mode": "inspect", "account_home": None, "git_capability": "unknown",
              "api_status": {}, "repositories": [], "deployment_states": [],
              "mutations_enabled": False}
    try:
        if client.config.host != "cp73.domains.co.za" or client.config.port != 2083 or client.config.username != "archite4" or client.config.account_home != HOME or not client.config.api_token:
            raise CpanelError("Exact account token configuration required.")
        # account_home() normalizes paths; additionally require the exact provider value.
        data = client._uapi("Variables", "get_user_information", {"name": "home"})
        if not isinstance(data, dict) or data.get("home") != HOME:
            raise CpanelError("Account home mismatch.")
        report["account_home"] = HOME
        report["api_status"]["account_home"] = "success"
    except Exception:
        report["api_status"]["account_home"] = "failed_or_unavailable"
        return report
    for module in ("VersionControl", "VersionControlDeployment"):
        key = module + "/retrieve"
        try:
            data = client._uapi(module, "retrieve", {"fields": FIELDS} if module == "VersionControl" else {})
            if not isinstance(data, list) or any(not isinstance(row, dict) for row in data):
                report["api_status"][key] = "invalid_contract"
                continue
            report["api_status"][key] = "success"
            if module == "VersionControl":
                report["git_capability"] = "retrieve_available"
                report["repositories"] = [repository(row, client.config.secrets) for row in data]
            else:
                report["deployment_states"] = [
                    {"root": safe_text(row.get("repository_root"), client.config.secrets),
                     "branch": safe_text(obj(row.get("repository_state")).get("branch"), client.config.secrets),
                     "deploy_sha": sha(obj(row.get("repository_state")).get("identifier"))}
                    for row in data]
        except Exception:
            report["api_status"][key] = "failed_or_unavailable"
    return report


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("mode", choices=["inspect", "prepare-key", "clone", "native-preflight"], nargs="?", default="inspect")
    parser.add_argument("--report", type=Path)
    parser.add_argument("--public-key", type=Path)
    parser.add_argument("--expected-key-fingerprint")
    parser.add_argument("--expected-commit")
    parser.add_argument("--secure-runtime-config-permissions", action="store_true")
    args = parser.parse_args()
    try:
        config = CpanelConfig(host=os.environ.get("A777_CPANEL_HOST", ""),
                              port=int(os.environ.get("A777_CPANEL_PORT", "2083")),
                              username=os.environ.get("A777_CPANEL_USER", ""), password="",
                              account_home=os.environ.get("A777_CPANEL_ACCOUNT_HOME", ""),
                              allowed_root=HOME,
                              api_token=os.environ.get("A777_CPANEL_API_TOKEN"))
        client = CpanelClient(config)
        if args.mode == 'inspect':
            report = inspect(client)
        else:
            from cpanel_git_prepare import prepare_key, clone
            if args.mode == 'prepare-key':
                if not args.public_key:
                    raise CpanelError('Public key destination required.')
                report = prepare_key(client, args.public_key, args.expected_key_fingerprint)
            elif args.mode == "native-preflight":
                from cpanel_git_prepare import native_preflight, DEPLOY_KEY_PIN
                report = native_preflight(client, args.expected_commit, args.expected_key_fingerprint or DEPLOY_KEY_PIN, secure_runtime_config_permissions=args.secure_runtime_config_permissions)
            else:
                report = clone(client, args.expected_key_fingerprint)
    except Exception as error:
        # Match only known constant refusals; never emit arbitrary exception text.
        reasons = {
            'Account home mismatch.': 'account_home_mismatch',
            'Invalid file inventory.': 'file_inventory_contract',
            'Remote permission read-back failed.': 'permission_readback',
            'ASCII file preservation contract unavailable; refused.': 'ssh_file_encoding',
            'Existing SSH wildcard policy collision; refused.': 'ssh_policy_collision',
            'Complex SSH configuration requires review.': 'ssh_policy_complex',
            'Official GitHub host key fingerprint mismatch.': 'github_host_key_mismatch',
            'Owned key collision: expected fingerprint required; no overwrite.': 'owned_key_already_exists',
            'cPanel UAPI operation failed.': 'provider_uapi_refused',
            'cPanel Fileman operation failed.': 'provider_fileman_refused',
        }
        reason = reasons.get(str(error), 'configuration_or_operation_refused') if isinstance(error, CpanelError) else 'configuration_or_operation_refused'
        report = {"mode": args.mode, "status": "failed", "error": reason, "runtime_deployed": False}
        from cpanel_git_prepare import NativeKeyFailure
        if type(error) is NativeKeyFailure:
            report.update(error=error.code, api_operation=error.stage, native_key_state=error.state,
                          key_generation='cpanel_native')
        import traceback
        # Locations only: never include source lines, locals or exception messages.
        report['failure_locations'] = [
            {'file': Path(frame.filename).name, 'function': frame.name, 'line': frame.lineno}
            for frame in traceback.extract_tb(error.__traceback__)
            if Path(frame.filename).name in ('cpanel_git.py', 'cpanel_git_prepare.py', 'cpanel_client.py')
        ]
        report['failure_type'] = type(error).__name__ if type(error).__name__ in ('CpanelError', 'TypeError', 'KeyError', 'ValueError', 'OSError') else 'operation_error'
    text = json.dumps(report, indent=2, sort_keys=True) + "\n"
    if args.report:
        args.report.write_text(text, encoding="utf-8")
    print(text, end="")
    return 0 if (report.get("api_status", {}).get("VersionControl/retrieve") == "success" or report.get('status') in ('prepared', 'cloned_and_pulled', 'preflight_complete')) else 1


if __name__ == "__main__":
    raise SystemExit(main())
