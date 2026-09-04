-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS cpd_content_revisions (
  content_item_id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  supersedes_content_id BINARY(16) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_cpd_content_lineage (organisation_id,supersedes_content_id),
  FOREIGN KEY (content_item_id) REFERENCES cpd_content_items(id) ON DELETE CASCADE,
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (supersedes_content_id) REFERENCES cpd_content_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cpd_assessment_governance (
  assessment_id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  supersedes_assessment_id BINARY(16) NULL,
  generation_model VARCHAR(180) NULL,
  generation_request_id VARCHAR(180) NULL,
  generation_usage JSON NULL,
  risk_flags JSON NOT NULL,
  question_bank_hash CHAR(64) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_cpd_assessment_lineage (organisation_id,supersedes_assessment_id),
  FOREIGN KEY (assessment_id) REFERENCES cpd_assessments(id) ON DELETE CASCADE,
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (supersedes_assessment_id) REFERENCES cpd_assessments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cpd_question_governance (
  question_id BINARY(16) PRIMARY KEY,
  learning_outcome VARCHAR(500) NULL,
  source_reference VARCHAR(500) NULL,
  difficulty ENUM('easy','medium','hard') NOT NULL DEFAULT 'medium',
  model_answer TEXT NULL,
  FOREIGN KEY (question_id) REFERENCES cpd_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cpd_attempt_timing (
  attempt_id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  expires_at DATETIME(6) NULL,
  last_activity_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_cpd_attempt_expiry (organisation_id,expires_at),
  FOREIGN KEY (attempt_id) REFERENCES cpd_attempts(id) ON DELETE CASCADE,
  FOREIGN KEY (organisation_id) REFERENCES organisations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
