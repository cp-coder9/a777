"""Synthetic receipt/code-wiring proof; no host calls, runtime files or DDL."""
import hashlib
import json
from pathlib import Path
import sys
import unittest
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import cpanel_git_prepare as g
from test_cpanel_git_preflight import NativeFake, SHA

class ManagedRestoreRehearsalTests(unittest.TestCase):
    def receipt(self, **fields):
        fake = NativeFake(receipt=False)
        fake.dirs.add(g.DEPLOY_ROOT)
        fake.modes[g.DEPLOY_ROOT] = 700
        name = 'preflight-' + SHA + '-0123456789abcdef.json'
        fake.files[g.DEPLOY_ROOT + '/' + name] = json.dumps(dict(
            format='a777-cpanel-native-preflight-v1', mode='preflight', commit=SHA,
            planSha256='a'*64, status='inspected-not-authorized',
            databaseDdlTouched=False, liveFilesTouched=False, **fields))
        fake.modes[g.DEPLOY_ROOT + '/' + name] = 600
        return g.receipts(fake, SHA, 'a'*64, set())[0]

    def test_allowlisted_read_only_binding(self):
        result = self.receipt(managedRestoreSourceBound=True,
                              managedRestoreProviderStatus='authenticated-inventory-read-only',
                              token='PRIVATE_SENTINEL', targetCreated='PRIVATE_SENTINEL')
        self.assertIs(result['managedRestoreSourceBound'], True)
        self.assertEqual(result['managedRestoreProviderStatus'], 'authenticated-inventory-read-only')
        self.assertNotIn('PRIVATE_SENTINEL', json.dumps(result))

    def test_forged_types_and_status_not_forwarded(self):
        result = self.receipt(managedRestoreSourceBound='true', managedRestoreProviderStatus='target-created')
        self.assertNotIn('managedRestoreSourceBound', result)
        self.assertNotIn('managedRestoreProviderStatus', result)

    def test_actual_hook_wiring_is_inventory_only(self):
        hook = (Path(__file__).resolve().parents[1] / 'cpanel_git_deploy.php').read_text()
        rehearsal = hook.split("$evidence['managedRestoreSourceBound'] = false;", 1)[1].split('$exclusive = true;', 1)[0]
        self.assertIn('CpanelRestoreProvisioner::fromAccountToken()', rehearsal)
        self.assertIn('AccountOwnedRestoreTarget::preflightManaged($pdo, $typed, $configSha, $provider)', rehearsal)
        self.assertNotIn('provisionManaged(', rehearsal)
        self.assertNotIn('->provision(', rehearsal)
        self.assertIn("'HEAD:' . $relative", rehearsal)
        self.assertIn("hash_file('sha256', $path)", rehearsal)
        self.assertIn('getFileName() === $path', rehearsal)
        self.assertNotIn('eval(', rehearsal)
        self.assertIn("$evidence['managedRestoreProviderStatus'] = 'authenticated-inventory-read-only'", rehearsal)

if __name__ == '__main__':
    unittest.main()
