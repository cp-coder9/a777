const assert=require('node:assert/strict');
const eligibility=require('../../public/assets/js/cpd-eligibility.js');

const course={professionalBody:'SACAP'};
const sacapProfile={professionalBody:'SACAP'};
const ecsaProfile={professionalBody:'ECSA'};
const ownAttempt={learnerUserId:'learner-1'};
const foreignAttempt={learnerUserId:'learner-2'};

assert.equal(eligibility.course(course,null).eligible,false,'a profile is required before enrolment');
assert.equal(eligibility.course(course,ecsaProfile).eligible,false,'another professional body cannot enrol');
assert.equal(eligibility.course(course,sacapProfile).eligible,true,'a matching professional body can enrol');
assert.equal(eligibility.attemptSubmission(ownAttempt,course,sacapProfile,'learner-1').eligible,true,'the learner can submit a matching-body attempt');
assert.equal(eligibility.attemptSubmission(ownAttempt,course,ecsaProfile,'learner-1').eligible,false,'the learner cannot submit after a body mismatch');
const foreign=eligibility.attemptSubmission(foreignAttempt,course,sacapProfile,'learner-1');
assert.equal(foreign.eligible,false,'another learner attempt cannot be submitted');
assert.match(foreign.attemptDetail,/belongs to another learner/);
