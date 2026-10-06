import sys
import unittest
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import cpanel_git_prepare as g

class Diagnostics(unittest.TestCase):
    def test_source_metadata_boolean_and_legacy_failure(self):
        legacy = dict(failureStage='source-storage', knownReason='private_directory_required',
                      failureFunction='privateDirectory', failureFile='CpanelRestoreRehearsal.php', failureLine=235)
        self.assertTrue(g.rehearsal_diagnostics_safe(legacy))
        for value in (True, False):
            self.assertTrue(g.rehearsal_diagnostics_safe(dict(legacy, sourceDirectoryMetadataOwnerOnly=value)))
        for value in (0, 1, None, 'false', [], {}):
            self.assertFalse(g.rehearsal_diagnostics_safe(dict(legacy, sourceDirectoryMetadataOwnerOnly=value)))
        for reason, function in (('source_directory_required','sourceDirectoryStat'), ('source_public_storage_refused','sourceLocation')):
            self.assertTrue(g.rehearsal_diagnostics_safe(dict(legacy, knownReason=reason, failureFunction=function)))

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
