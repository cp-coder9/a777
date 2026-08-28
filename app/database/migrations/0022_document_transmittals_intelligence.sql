-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS document_transmittals (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  project_id BINARY(16) NOT NULL,
  code VARCHAR(80) NOT NULL,
  title VARCHAR(220) NOT NULL,
  purpose ENUM('for_review','for_information','for_municipal_submission','for_tender','for_construction','as_built','closeout') NOT NULL,
  recipient_roles JSON NOT NULL,
  notes TEXT NOT NULL,
  status ENUM('draft','issued','superseded') NOT NULL DEFAULT 'draft',
  issue_reason VARCHAR(500) NULL,
  snapshot_payload JSON NULL,
  snapshot_sha256 CHAR(64) NULL,
  project_record_id BINARY(16) NULL,
  created_by BINARY(16) NOT NULL,
  issued_by BINARY(16) NULL,
  issued_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_document_transmittal_code (organisation_id,project_id,code),
  KEY idx_document_transmittal_project (organisation_id,project_id,created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id),
  FOREIGN KEY (project_record_id) REFERENCES project_records(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  FOREIGN KEY (issued_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_transmittal_items (
  transmittal_id BINARY(16) NOT NULL,
  revision_id BINARY(16) NOT NULL,
  item_order SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (transmittal_id,revision_id),
  KEY idx_document_transmittal_revision (revision_id),
  FOREIGN KEY (transmittal_id) REFERENCES document_transmittals(id),
  FOREIGN KEY (revision_id) REFERENCES document_revisions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS drawing_intelligence_candidates (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  project_id BINARY(16) NOT NULL,
  revision_id BINARY(16) NOT NULL,
  provenance ENUM('human_entered','provider_result') NOT NULL,
  provider_name VARCHAR(120) NULL,
  provider_reference VARCHAR(180) NULL,
  confidence DECIMAL(5,4) NULL,
  candidate_payload JSON NOT NULL,
  candidate_sha256 CHAR(64) NOT NULL,
  status ENUM('pending_review','accepted','rejected') NOT NULL DEFAULT 'pending_review',
  review_reason VARCHAR(500) NULL,
  reviewed_by BINARY(16) NULL,
  reviewed_at DATETIME(6) NULL,
  project_record_id BINARY(16) NULL,
  created_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_drawing_intelligence_revision (organisation_id,revision_id,created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id),
  FOREIGN KEY (revision_id) REFERENCES document_revisions(id),
  FOREIGN KEY (project_record_id) REFERENCES project_records(id),
  FOREIGN KEY (reviewed_by) REFERENCES users(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
