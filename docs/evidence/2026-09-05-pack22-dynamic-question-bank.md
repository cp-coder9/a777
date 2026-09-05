# Pack 22 dynamic CPD question bank evidence

Date: 2026-09-05

## Delivered slice

The CPD content-creator assessment form now provides an ordered question-bank
builder rather than a fixed two-question scaffold.  A creator can add, remove
and reorder up to 200 questions, choose `single_choice`, `multiple_choice`,
`scenario` or `text_review`, and supply the required outcome, source,
explanation and answer/rubric evidence.  Choice questions require at least two
options and a correct option before the form can post to the existing
MariaDB-backed assessment endpoint.

## Executed checks

- `node --check app/public/assets/js/shell.js` completed successfully.
- `node --test tools/tests/cpd-award-label.test.mjs tools/tests/cpd-course-access.test.mjs tools/tests/cpd-question-bank.test.mjs` passed: 13 tests, 0 failures.
- `git diff --check` completed successfully (Git only reported its existing LF-to-CRLF working-tree advisory).

## Chrome DevTools evidence

On local `http://127.0.0.1:8810/`, authenticated content creator
`cpd.browser.content_creator@local.test` created **Browser dynamic
question-bank assessment**.  The saved revision records four questions in the
explicit reordered sequence: single choice, scenario, multiple choice and text
review.

An authenticated CPD reviewer opened the review disclosure and saw all four
questions, source/outcome fields, answer keys and feedback/rubric data.  The
learner and accreditation-partner browser sessions did not receive the draft
assessment or any of its answer-key/rubric content; neither role received the
creator builder or review approval control.

The same browser check found an empty review-queue heading in learner and
partner navigation. It is now capability-gated with the queue itself. After a
cache-bypassing reload, learner navigation contained only Professional profile,
Course catalogue and My CPD record; partner navigation contained Accreditation
and CPD analytics but no review queue; reviewer navigation contained Human
review and the review disclosure. Computed-style checks found no rendered
restricted elements.

At a Chrome-emulated 390 x 844 mobile viewport, the creator question bank
rendered at 264px within a 292px form; document width remained 390px and no
interactive question-bank element extended beyond the viewport. Adding two
questions and changing them to scenario and text-review retained the expected
three-card sequence with option counts `2, 2, 0`. The local fixture uses demo
application mode, so its global demo-sandbox bootstrap correctly returned 403
for an ordinary member; the CPD workspace request itself returned 200. This is
not a CPD error or a production-mode console claim.

Finally, an independently authenticated platform-administrator browser session
rendered Content studio, the dynamic Assessment builder, Human review,
Accreditation, Commercial controls and the role manager. The administrator
navigation contained all eight relevant destinations and the builder rendered
its initial question card. This completes the browser role-visibility evidence
for learner, creator, reviewer, accreditation partner and platform
administrator for this slice.

## True / false extension

The Pack 22 source also requires true/false questions. A new browser contract
test initially failed with `Question 4 type is invalid`; the corresponding
MariaDB request then exposed that the original `cpd_questions.question_type`
enum lacked `true_false`. Migration `0061_cpd_true_false_questions.sql`
extends only that enum. Its migration-policy test first failed, because the
platform intentionally blocks generic `ALTER TABLE`; the policy now allows
only this exact forward CPD enum extension while its existing generic-ALTER
rejection remains in place.

Executed checks after the correction:

- CPD migration contract plus migration-policy tests: 16 tests, 72 assertions,
  0 failures.
- Question-bank browser contract: 4 tests, 0 failures.
- PHP syntax checks passed for the CPD service and migration infrastructure.
- Local browser fixture migration: one migration applied, status current;
  schema inspection reported `true_false` in `cpd_questions.question_type`.
- A creator saved **Browser true-false question-bank assessment** and an
  independently signed-in reviewer received its review disclosure and approval
  action.

## Boundary

This is local browser and targeted-test evidence for the question-bank slice.
Pack 22 remains open; no release screenshot or remote-release certification is
claimed by this record.
