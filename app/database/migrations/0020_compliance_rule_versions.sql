-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS compliance_rule_versions (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  rule_key VARCHAR(80) NOT NULL,
  version VARCHAR(100) NOT NULL,
  status ENUM('review_required','verified','withdrawn') NOT NULL DEFAULT 'review_required',
  source_reference VARCHAR(500) NOT NULL,
  effective_date DATE NOT NULL,
  rules_payload JSON NOT NULL,
  payload_sha256 CHAR(64) NOT NULL,
  created_by BINARY(16) NOT NULL,
  reviewed_by BINARY(16) NULL,
  reviewed_at DATETIME(6) NULL,
  review_reason VARCHAR(500) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_compliance_rule_version (organisation_id,rule_key,version),
  KEY idx_compliance_rule_current (organisation_id,rule_key,status,effective_date,created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
