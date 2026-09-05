# Pack 22 Chrome role screens and draft review

Executed Chrome DevTools MCP against localhost:8810 using real sign-in form submission in isolated browser contexts for creator, reviewer, accreditation partner and platform administrator; learner sign-in was verified in the previous evidence file. Workspace responses returned HTTP 200 and the expected participation roles.

Creator: content and builder visible, role manager hidden. Reviewer: accreditation submission visible, content/builder/publishing/commercial/role management hidden. Partner: accreditation submission and course publishing visible, content/builder/commercial/role management hidden. Administrator: all checked controls visible. Reviewer, partner and administrator had zero document horizontal overflow.

Through the creator forms, created Browser role workflow course and Browser professional responsibility assessment with two questions. The reviewer received the draft. This exposed a missing question-inspection view before approval. Added expandable question/answer guidance to the review queue, with full question keys returned only when canReviewAssessments is true; ordinary learners continue through the stripped workspaceQuestions path.

In a fresh reviewer browser context, inspected the rendered correct and incorrect options, learning outcome, source reference, rationale and model-answer guidance. Executed the approval action. This is partial local workflow evidence; accreditation, learner completion, denial tests and complete all-role E2E remain outstanding. No completed-module screenshot or release certification is claimed.
