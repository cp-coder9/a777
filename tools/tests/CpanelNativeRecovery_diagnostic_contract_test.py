"""Synthetic PHP producer -> real Python diagnostic consumer; no host calls."""
import importlib.util
import json
from pathlib import Path
import subprocess
import sys
from types import SimpleNamespace

root = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(root / 'tools'))
spec = importlib.util.spec_from_file_location('recover', root / 'tools/cpanel_native_recover.py')
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)
php = "require 'tools/CpanelNativeDeploymentRecovery.php'; echo json_encode(array_map([CpanelNativeDeploymentRecovery::class,'diagnostic'], [['sourceFile'=>'CpanelNativeRecovery.php','sourceLine'=>183,'reason'=>'recovery_evidence_refused','exceptionType'=>'PRIVATE'],['sourceFile'=>'foreign.php','sourceLine'=>null,'reason'=>'SECRET']]));"
rows = json.loads(subprocess.check_output(['php', '-r', php], cwd=root, text=True))
sha = 'a' * 40
names = ['recoverydiag-' + sha + '-' + str(i) * 16 + '.json' for i in range(2)]
m.p = SimpleNamespace(DEPLOY_ROOT='/synthetic-private', entries=lambda client, path: [{'name': n} for n in names])
m.private_read = lambda client, path: json.dumps(rows[names.index(path.rsplit('/', 1)[1])])
actual = m.recovery_diagnostics(object(), sha, set())
assert actual == rows
assert rows[0] == {'file':'CpanelNativeRecovery.php','reasonCode':'recovery_evidence_refused','line':183}
assert rows[1]['file'] == 'CpanelNativeDeploymentRecovery.php' and rows[1]['reasonCode'] == 'unclassified'
assert m.recovery_diagnostics(object(), sha, set(names)) == []
print('PASS PHP producer -> Python exact diagnostic schema; unknown-source redaction and previous-nonce exclusion')
