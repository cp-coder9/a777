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

## Boundary

This is local browser and targeted-test evidence for the question-bank slice.
Pack 22 remains open; no release screenshot or remote-release certification is
claimed by this record.
