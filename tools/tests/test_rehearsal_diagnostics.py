import sys
import unittest
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import cpanel_git_prepare as g

class Diagnostics(unittest.TestCase):
    def test_constants_only(self):
        fields = dict(failureStage='repository', knownReason='private_directory_required',
            failureFile='CpanelRestoreRehearsal.php', failureFunction='privateDirectory', failureLine=152,
            repositoryMode='0755', repositoryOwner='account', repositoryCanonical='canonical',
            storageMode='0700', storageOwner='account', storageCanonical='canonical',
            gitCommandAvailability='unavailable', fixedGitBinaryAvailability='available', lastCompletedStage=None)
        self.assertTrue(g.rehearsal_diagnostics_safe(fields))
        # Native preflight has always emitted an exact boolean for this field.
        self.assertTrue(g.rehearsal_diagnostics_safe(dict(fields, storageCanonical=True)))
        self.assertTrue(g.rehearsal_diagnostics_safe(dict(fields, storageCanonical=False)))
        for key in fields:
            for hostile in ('/private/password', 'SELECT secret', {}, [], 1 if key == 'storageCanonical' else True):
                with self.subTest(key=key, hostile=hostile):
                    self.assertFalse(g.rehearsal_diagnostics_safe(dict(fields, **{key:hostile})))

if __name__ == '__main__': unittest.main()
