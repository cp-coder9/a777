import json
import sys
import unittest
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import cpanel_git_prepare as g
from cpanel_client import CpanelError
from test_cpanel_git_preflight import NativeFake, SHA

class PublicSourceExceptionTests(unittest.TestCase):
    def test_opt_in_requires_rehearsal_and_pin(self):
        for mode,pin,flag in [('preflight','a'*64,True),('restore-rehearsal','',True),('restore-rehearsal','a'*64,1)]:
            with self.assertRaises(CpanelError):
                g.preflight_plan(NativeFake(),SHA,mode=mode,expected_runtime_config_sha256=pin,allow_existing_public_source_for_rehearsal=flag)
    def test_private_plan_explicit_only(self):
        f=NativeFake()
        g.preflight_plan(f,SHA,mode='restore-rehearsal',expected_runtime_config_sha256='a'*64,allow_existing_public_source_for_rehearsal=True)
        self.assertIs(json.loads(f.files[g.PLAN])['allowExistingPublicSourceForRehearsal'],True)
    def test_boolean_receipts(self):
        for field in ('sourceStoragePrivacyUnproven','sourceStorageUnderPublicHtml'):
            self.assertTrue(g.rehearsal_diagnostics_safe({field:True}))
            for value in (1,'true',None):
                self.assertFalse(g.rehearsal_diagnostics_safe({field:value}))
