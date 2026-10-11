import importlib.util
from pathlib import Path
import unittest
import copy
spec = importlib.util.spec_from_file_location('runner', Path(__file__).with_name('material3-native-acceptance-runner.py'))
assert spec is not None and spec.loader is not None
runner = importlib.util.module_from_spec(spec)
spec.loader.exec_module(runner)

class SelectionTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        import json, subprocess
        cls.parent=json.loads(subprocess.check_output([runner.NODE,str(Path(__file__).with_suffix('.cjs')),'--inventory-json'],text=True))['fullKeys']

    def report(self, keys):
        keys=[self.parent[0] if k=='a' else self.parent[1] if k=='b' else k for k in keys]
        return dict(candidateHash='candidate', fixedIdentity={'origin':'fixed'}, manifestVersion=1, harnessSha256='harness', parentFullExpected=517, parentFullExpectedKeys=self.parent.copy(), expectedKeys=keys, results=[dict(key=k,status='FAIL',verification='UNVERIFIED') for k in keys])
    def test_union_and_exact_dedupe(self):
        a,b=self.report(['a']),self.report(['b'])
        result=runner.reconcile_reports([a,b,a])
        self.assertEqual(result['completed'],2)
        self.assertEqual(result['missingKeys'],sorted(set(self.parent)-{self.parent[0],self.parent[1]}))
        self.assertEqual(result['counts']['PASS'],0)
    def test_reject_identity_and_conflicts(self):
        a=self.report(['a'])
        for field in ('candidateHash','fixedIdentity','manifestVersion','harnessSha256'):
            b=copy.deepcopy(a);b[field]='changed'
            with self.assertRaises(ValueError):runner.reconcile_reports([a,b])
        b=copy.deepcopy(a);b['results'][0]['status']='PASS'
        with self.assertRaises(ValueError):runner.reconcile_reports([a,b])
    def test_missing_scope_is_not_placeholder(self):
        result=runner.reconcile_reports([self.report(['a'])])
        self.assertEqual(result['missingKeys'],sorted(set(self.parent)-{self.parent[0]}))
        self.assertEqual(len(result['results']),1)

    def test_reject_invalid_result_status(self):
        for status in (None, True, False, '', 'INVALID', 'pass', 1, [], {}):
            report=self.report(['a']);report['results'][0]['status']=status
            with self.subTest(status=status),self.assertRaises(ValueError):runner.reconcile_reports([report])
        report=self.report(['a']);del report['results'][0]['status']
        with self.assertRaises(ValueError):runner.reconcile_reports([report])

    def test_reject_failed_collector_metadata(self):
        for field, values in (
            ('collectorFailure', (True, 'failure', '', 1, 0, None, [], {})),
            ('fatal', ('Collector stopped', True, False, 1, 0, [], {})),
            ('candidateChanged', (True, 'false', 1, 0, None, [], {})),
        ):
            for value in values:
                report=self.report(['b']);report['results'][0]['status']='PASS';report[field]=value
                with self.subTest(field=field,value=value),self.assertRaises(ValueError):
                    runner.reconcile_reports([self.report(['a']),report])

    def test_valid_status_counts_and_nonfatal_partial_evidence(self):
        report=self.report(self.parent[:3])
        for row,status in zip(report['results'],('PASS','FAIL','UNSUPPORTED')):row['status']=status
        for fatal in (None, ''):
            report.update(collectorFailure=False,fatal=fatal,candidateChanged=False)
            result=runner.reconcile_reports([report,copy.deepcopy(report)])
            self.assertEqual(result['counts'],{'PASS':1,'FAIL':1,'UNSUPPORTED':1})
            self.assertEqual(sum(result['counts'].values()),result['completed'])
            self.assertEqual(result['completed'],3)
            self.assertEqual(result['missingKeys'],sorted(set(self.parent)-set(self.parent[:3])))

    def test_reject_duplicate_parent(self):
        report=self.report(['a'])
        report['parentFullExpectedKeys']=[self.parent[0],self.parent[0]]
        with self.assertRaises(ValueError):runner.reconcile_reports([report])

    def test_reject_arbitrary_shared_parent(self):
        a,b=self.report(['a']),self.report(['b'])
        for report in (a,b):report['parentFullExpectedKeys'][-1]='invented/parent'
        with self.assertRaises(ValueError):runner.reconcile_reports([a,b])

    def test_reject_parent_count_mismatch(self):
        report=self.report(['a']);report['parentFullExpected']=3
        with self.assertRaises(ValueError):runner.reconcile_reports([report])

    def test_source_backed_full_union(self):
        groups=[keys for keys in (
            [k for k in self.parent if k.startswith('profile/')],
            [k for k in self.parent if k.split('/')[0] in ('owner','viewer') and k.split('/')[1] in ('drawing_compliance_navigator','valuations')],
        )]
        groups.append([k for k in self.parent if k not in set(groups[0]+groups[1])])
        self.assertEqual([len(g) for g in groups],[84,16,417])
        result=runner.reconcile_reports([self.report(g) for g in groups])
        self.assertEqual((result['expected'],result['completed'],result['missingKeys']),(517,517,[]))
        self.assertEqual(result['counts'],{'PASS':0,'FAIL':517,'UNSUPPORTED':0})

    def test_reject_parent_schema(self):
        for parent in (None,'not-a-list',{},[None],[''],[' '], [1], [self.parent[0]]*517):
            report=self.report(['a']);report['parentFullExpectedKeys']=parent
            with self.subTest(parent=type(parent).__name__),self.assertRaises(ValueError):runner.reconcile_reports([report])
        for count in (None,'517',517.0,True,516,518):
            report=self.report(['a']);report['parentFullExpected']=count
            with self.subTest(count=count),self.assertRaises(ValueError):runner.reconcile_reports([report])

if __name__=='__main__':unittest.main()
