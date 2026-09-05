-- architex:migration-mode=ddl
-- Preserve existing governed assessment data while allowing the Pack 22
-- true/false question contract in new question-bank revisions.
ALTER TABLE cpd_questions
  MODIFY question_type ENUM('single_choice','multiple_choice','scenario','true_false','text_review') NOT NULL;
