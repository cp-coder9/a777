import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { test } from 'node:test';

const shell = readFileSync(new URL('../../app/public/assets/js/shell.js', import.meta.url), 'utf8');
const start = shell.indexOf('  function cpdLearnerCourseAccess(');
const end = shell.indexOf('  function renderCpd()', start);
assert.ok(start >= 0 && end > start);
const context = vm.createContext({});
vm.runInContext(shell.slice(start, end), context);
const access = context.cpdLearnerCourseAccess;
const course = { id: 'course' };
const entitlement = { courseId: 'course', learnerUserId: 'me', status: 'active' };
const attempt = status => ({ courseId: 'course', learnerUserId: 'me', status });

test('another learner cannot supply my access or consume my retry allowance', () => {
  const other = { ...entitlement, learnerUserId: 'other' };
  assert.equal(access(course, 'me', [other], [], { attemptLimit: 3 }).canStart, false);
  const result = access(course, 'me', [other, entitlement], [{ ...attempt('passed'), learnerUserId: 'other' }], { attemptLimit: 3 });
  assert.equal(result.canStart, true);
  assert.equal(result.label, 'Start assessment');
});

test('failed and expired attempts permit a retry until the limit', () => {
  const result = access(course, 'me', [entitlement], [attempt('failed')], { attemptLimit: 2 });
  assert.equal(result.canStart, true);
  assert.equal(result.label, 'Retry assessment');
  assert.equal(access(course, 'me', [entitlement], [attempt('failed'), attempt('expired')], { attemptLimit: 2 }).canStart, false);
});

test('an open attempt remains resumable at the attempt limit', () => {
  const result = access(course, 'me', [entitlement], [attempt('in_progress')], { attemptLimit: 1 });
  assert.equal(result.canStart, true);
  assert.equal(result.label, 'Resume assessment');
});

test('pending review, passed and revoked access prevent new attempts', () => {
  for (const status of ['manual_review_required', 'passed']) {
    assert.equal(access(course, 'me', [entitlement], [attempt(status)], { attemptLimit: 3 }).canStart, false);
  }
  assert.equal(access(course, 'me', [{ ...entitlement, status: 'revoked' }], [], { attemptLimit: 3 }).canStart, false);
});
