-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS municipal_requirement_versions (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  municipality_key VARCHAR(120) NOT NULL,
  municipality_name VARCHAR(180) NOT NULL,
  version VARCHAR(100) NOT NULL,
  status ENUM('review_required','verified','withdrawn') NOT NULL DEFAULT 'review_required',
  source_reference VARCHAR(500) NOT NULL,
  effective_date DATE NOT NULL,
  requirements_payload JSON NOT NULL,
  payload_sha256 CHAR(64) NOT NULL,
  created_by BINARY(16) NOT NULL,
  reviewed_by BINARY(16) NULL,
  reviewed_at DATETIME(6) NULL,
  review_reason VARCHAR(500) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_municipal_requirement_version (organisation_id,municipality_key,version),
  KEY idx_municipal_requirement_current (organisation_id,municipality_key,status,effective_date,created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS municipal_readiness_assessments (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  project_id BINARY(16) NOT NULL,
  matrix_version_id BINARY(16) NOT NULL,
  complexity ENUM('low','medium','high') NOT NULL,
  overall_score TINYINT UNSIGNED NOT NULL,
  ready_for_professional_review TINYINT(1) NOT NULL DEFAULT 0,
  assessment_snapshot JSON NOT NULL,
  snapshot_sha256 CHAR(64) NOT NULL,
  project_record_id BINARY(16) NULL,
  created_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_municipal_assessment_project (organisation_id,project_id,created_at),
  KEY idx_municipal_assessment_matrix (matrix_version_id,created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id),
  FOREIGN KEY (matrix_version_id) REFERENCES municipal_requirement_versions(id),
  FOREIGN KEY (project_record_id) REFERENCES project_records(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS municipal_readiness_reviews (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  assessment_id BINARY(16) NOT NULL,
  decision ENUM('approved_for_professional_submission_review','rejected_for_revision') NOT NULL,
  reason VARCHAR(500) NOT NULL,
  assessment_sha256 CHAR(64) NOT NULL,
  reviewed_by BINARY(16) NOT NULL,
  reviewed_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_municipal_assessment_review (organisation_id,assessment_id),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (assessment_id) REFERENCES municipal_readiness_assessments(id),
  FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
