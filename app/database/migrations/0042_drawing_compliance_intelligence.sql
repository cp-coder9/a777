-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS drawing_compliance_sources (
  source_key VARCHAR(80) NOT NULL,
  version VARCHAR(40) NOT NULL,
  title VARCHAR(180) NOT NULL,
  source_url VARCHAR(500) NULL,
  source_status ENUM('illustrative_poc_until_verified','licensed_verified') NOT NULL,
  source_sha256 CHAR(64) NOT NULL,
  production_reliance_allowed TINYINT(1) NOT NULL DEFAULT 0,
  source_payload JSON NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (source_key,version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS drawing_compliance_reviews (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  user_id BINARY(16) NOT NULL,
  project_id BINARY(16) NULL,
  source_key VARCHAR(80) NOT NULL,
  source_version VARCHAR(40) NOT NULL,
  mode ENUM('standalone','project') NOT NULL,
  title VARCHAR(220) NOT NULL,
  drawing_revision VARCHAR(80) NULL,
  responsible_role VARCHAR(80) NULL,
  overall_status ENUM('pass','watch','fail','needs_input') NOT NULL,
  professional_review_status ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  input_snapshot JSON NOT NULL,
  review_snapshot JSON NOT NULL,
  snapshot_sha256 CHAR(64) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_drawing_compliance_org_time (organisation_id,created_at),
  KEY idx_drawing_compliance_project (organisation_id,project_id,created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (project_id) REFERENCES projects(id),
  FOREIGN KEY (source_key,source_version) REFERENCES drawing_compliance_sources(source_key,version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
