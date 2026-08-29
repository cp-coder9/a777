-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS trust_compliance_items (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL,
  subject_type ENUM('professional','company','contractor','supplier') NOT NULL, subject_name VARCHAR(220) NOT NULL,
  category ENUM('professional_registration','company_document','insurance','contractor_supplier') NOT NULL, document_type VARCHAR(100) NOT NULL,
  authority VARCHAR(160) NOT NULL, reference_number VARCHAR(180) NOT NULL,
  grade ENUM('self_declared','document_uploaded','manually_reviewed','externally_verified','rejected') NOT NULL,
  evidence_file_id BINARY(16) NULL, provider VARCHAR(160) NULL, provider_reference VARCHAR(180) NULL,
  coverage_amount_cents BIGINT UNSIGNED NULL, required_coverage_cents BIGINT UNSIGNED NULL, details JSON NOT NULL,
  expires_on DATE NULL, status ENUM('current','expiring','expired','coverage_gap','rejected') NOT NULL,
  popia_consent_at DATETIME(6) NOT NULL, processing_purpose VARCHAR(500) NOT NULL, retention_until DATE NOT NULL,
  created_by BINARY(16) NOT NULL, reviewed_by BINARY(16) NULL, reviewed_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  KEY idx_trust_compliance_project (organisation_id,project_id,status,category,expires_on),
  KEY idx_trust_compliance_subject (organisation_id,subject_type,subject_name),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id),
  FOREIGN KEY (evidence_file_id) REFERENCES stored_files(id), FOREIGN KEY (created_by) REFERENCES users(id), FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trust_compliance_reviews (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, compliance_item_id BINARY(16) NOT NULL, reviewer_user_id BINARY(16) NOT NULL,
  decision ENUM('manually_reviewed','externally_verified','rejected') NOT NULL, review_note VARCHAR(4000) NOT NULL,
  provider VARCHAR(160) NULL, provider_reference VARCHAR(180) NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_trust_compliance_reviews (organisation_id,compliance_item_id,created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (compliance_item_id) REFERENCES trust_compliance_items(id), FOREIGN KEY (reviewer_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS popia_processing_activities (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL,
  activity_name VARCHAR(220) NOT NULL, purpose VARCHAR(500) NOT NULL, lawful_basis ENUM('consent','contract','legal_obligation','legitimate_interest','public_law_duty') NOT NULL,
  data_subjects JSON NOT NULL, data_categories JSON NOT NULL, recipients JSON NOT NULL, safeguards VARCHAR(2000) NOT NULL,
  retention_until DATE NOT NULL, owner_user_id BINARY(16) NOT NULL, status ENUM('active','review_due','closed') NOT NULL DEFAULT 'active',
  created_by BINARY(16) NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  KEY idx_popia_processing (organisation_id,project_id,status,retention_until), FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (owner_user_id) REFERENCES users(id), FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS popia_data_subject_requests (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL,
  request_type ENUM('access','correction','deletion','objection','portability') NOT NULL, requester_reference VARCHAR(180) NOT NULL,
  received_on DATE NOT NULL, due_on DATE NOT NULL, scope VARCHAR(2000) NOT NULL,
  status ENUM('received','in_progress','fulfilled','refused') NOT NULL DEFAULT 'received', response_summary VARCHAR(2000) NULL,
  decided_by BINARY(16) NULL, decision_reason VARCHAR(1000) NULL, completed_at DATETIME(6) NULL, created_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  KEY idx_popia_dsr (organisation_id,project_id,status,due_on), FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (decided_by) REFERENCES users(id), FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS popia_breaches (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL,
  detected_at DATETIME(6) NOT NULL, summary VARCHAR(1000) NOT NULL, data_categories JSON NOT NULL, subjects_affected INT UNSIGNED NOT NULL,
  risk_rating ENUM('low','medium','high','critical') NOT NULL, regulator_notification_required BOOLEAN NOT NULL,
  regulator_notified_at DATETIME(6) NULL, status ENUM('open','contained','closed') NOT NULL DEFAULT 'open', response_reason VARCHAR(2000) NULL,
  created_by BINARY(16) NOT NULL, managed_by BINARY(16) NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  KEY idx_popia_breaches (organisation_id,project_id,status,risk_rating,detected_at), FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (created_by) REFERENCES users(id), FOREIGN KEY (managed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trust_compliance_risks (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL,
  source_type VARCHAR(80) NOT NULL, source_object_id BINARY(16) NOT NULL, title VARCHAR(220) NOT NULL, reason VARCHAR(1000) NOT NULL,
  severity ENUM('low','medium','high','critical') NOT NULL, blocks_appointment BOOLEAN NOT NULL DEFAULT FALSE,
  status ENUM('open','resolved') NOT NULL DEFAULT 'open', resolved_by BINARY(16) NULL, resolution_reason VARCHAR(1000) NULL, resolved_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), KEY idx_trust_risks (organisation_id,project_id,status,severity),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (resolved_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trust_agent_recommendations (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL,
  source_type VARCHAR(80) NOT NULL, source_object_id BINARY(16) NOT NULL, title VARCHAR(220) NOT NULL, rationale VARCHAR(1000) NOT NULL,
  severity ENUM('low','medium','high','critical') NOT NULL, status ENUM('open','dismissed','resolved') NOT NULL DEFAULT 'open',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), KEY idx_trust_recommendations (organisation_id,project_id,status,severity),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
