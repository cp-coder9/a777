#!/bin/bash
set -euo pipefail
umask 077
# Fixed private repository; never copy tools or runtime helpers into public_html.
readonly REPO=/home/archite4/repositories/a777
[[ "$(/usr/bin/id -un)" == archite4 ]] || exit 1
[[ "$(/usr/bin/readlink -f "$REPO")" == "$REPO" ]] || exit 1
readonly PHP=/opt/cpanel/ea-php83/root/usr/bin/php
[[ -x "$PHP" ]] || { printf '%s\n' 'ea-php83 CLI unavailable; deployment refused' >&2; exit 1; }
readonly MODE="${1:---preflight}"
[[ "$#" -le 1 && ( "$MODE" == --from-plan || "$MODE" == --native-deploy || "$MODE" == --restore-rehearsal || "$MODE" == --preflight || "$MODE" == --deploy || "$MODE" == --stage-only ) ]] || exit 1
exec "$PHP" "$REPO/tools/cpanel_git_deploy.php" "$MODE"
