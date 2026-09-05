// Run in Chrome DevTools after opening the authenticated CPD workspace.
() => {
  const root = document.getElementById('cpdAssessmentWorkspace');
  if (!root) throw new Error('Open the CPD workspace before running this check');
  const hidden = [...root.querySelectorAll('[hidden]')];
  if (!hidden.length) throw new Error('No restricted elements found: check the test role');
  const failures = hidden.filter(element => {
    const rect = element.getBoundingClientRect();
    return getComputedStyle(element).display !== 'none' || rect.width !== 0 || rect.height !== 0;
  }).map(element => element.id || element.tagName);
  if (failures.length) throw new Error('Hidden CPD controls are rendered: ' + failures.join(', '));
  const visibleForms = [...root.querySelectorAll('form:not([hidden])')].filter(element => element.getBoundingClientRect().width > 0).map(element => element.id);
  if (!visibleForms.includes('cpdProfileForm')) throw new Error('Learner profile must remain visible');
  return {passed: true, hiddenCount: hidden.length, visibleForms, viewport: innerWidth};
}
