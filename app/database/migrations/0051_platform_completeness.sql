-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS platform_completeness_workstreams (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, catalogue_key VARCHAR(100) NOT NULL, title VARCHAR(220) NOT NULL,
  goal TEXT NOT NULL, build_area TEXT NOT NULL, historical_reference TEXT NOT NULL, notes TEXT NOT NULL,
  implementation_status ENUM('not_started','in_progress','implemented','in_review','changes_requested','accepted','deferred') NOT NULL DEFAULT 'not_started',
  qc_status ENUM('not_reviewed','pass','fail','partial','blocked') NOT NULL DEFAULT 'not_reviewed', branch_or_pr VARCHAR(500) NULL,
  blockers TEXT NULL, expected_version INT UNSIGNED NOT NULL DEFAULT 1, sort_order SMALLINT UNSIGNED NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_pc_workstream (organisation_id,catalogue_key), FOREIGN KEY (organisation_id) REFERENCES organisations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_completeness_sections (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, catalogue_key VARCHAR(100) NOT NULL,
  title VARCHAR(200) NOT NULL, sort_order SMALLINT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_pc_section (organisation_id,catalogue_key), FOREIGN KEY (organisation_id) REFERENCES organisations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_completeness_items (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, section_id BINARY(16) NOT NULL, catalogue_key VARCHAR(140) NOT NULL,
  title VARCHAR(500) NOT NULL, description TEXT NULL, pack_source VARCHAR(180) NOT NULL DEFAULT '25. architex-workflow-checklists',
  priority ENUM('critical','high','medium','low') NOT NULL DEFAULT 'medium',
  implementation_status ENUM('not_started','in_progress','implemented','in_review','changes_requested','accepted','deferred') NOT NULL DEFAULT 'not_started',
  qc_status ENUM('not_reviewed','pass','fail','partial','blocked') NOT NULL DEFAULT 'not_reviewed', owner_user_id BINARY(16) NULL,
  reviewer_user_id BINARY(16) NULL, route_or_file_path VARCHAR(500) NULL, branch_or_pr VARCHAR(500) NULL, blockers TEXT NULL,
  next_action TEXT NULL, acceptance_criteria TEXT NULL, expected_version INT UNSIGNED NOT NULL DEFAULT 1, last_reviewed_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_pc_item (organisation_id,catalogue_key), KEY idx_pc_item_state (organisation_id,implementation_status,qc_status),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (section_id) REFERENCES platform_completeness_sections(id),
  FOREIGN KEY (owner_user_id) REFERENCES users(id), FOREIGN KEY (reviewer_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_completeness_evidence (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, item_id BINARY(16) NOT NULL, category VARCHAR(60) NOT NULL,
  label VARCHAR(200) NOT NULL, evidence_url VARCHAR(1000) NOT NULL, notes TEXT NULL, created_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), KEY idx_pc_evidence (organisation_id,item_id,created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (item_id) REFERENCES platform_completeness_items(id), FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_completeness_qc_reviews (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, item_id BINARY(16) NOT NULL,
  result ENUM('pass','fail','partial','blocked') NOT NULL, notes TEXT NOT NULL, required_changes TEXT NULL, reviewer_user_id BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), KEY idx_pc_review (organisation_id,item_id,created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (item_id) REFERENCES platform_completeness_items(id), FOREIGN KEY (reviewer_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_completeness_uploads (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, item_id BINARY(16) NOT NULL, stored_file_id BINARY(16) NOT NULL,
  category VARCHAR(60) NOT NULL, linked_by BINARY(16) NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_pc_upload (item_id,stored_file_id), KEY idx_pc_upload (organisation_id,item_id), FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (item_id) REFERENCES platform_completeness_items(id), FOREIGN KEY (stored_file_id) REFERENCES stored_files(id), FOREIGN KEY (linked_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_completeness_role_assignments (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, user_id BINARY(16) NOT NULL,
  platform_role ENUM('developer','qa_reviewer','viewer') NOT NULL, assigned_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_pc_role (organisation_id,user_id), FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (user_id) REFERENCES users(id), FOREIGN KEY (assigned_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_completeness_signoffs (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, item_id BINARY(16) NOT NULL, signed_off_by BINARY(16) NOT NULL,
  decision ENUM('accepted','changes_requested') NOT NULL, reason TEXT NOT NULL, human_confirmed BOOLEAN NOT NULL DEFAULT FALSE,
  snapshot_sha256 CHAR(64) NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_pc_signoff (organisation_id,item_id,created_at), FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (item_id) REFERENCES platform_completeness_items(id), FOREIGN KEY (signed_off_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_completeness_workstream_signoffs (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, workstream_id BINARY(16) NOT NULL, signed_off_by BINARY(16) NOT NULL,
  decision ENUM('accepted','changes_requested') NOT NULL, reason TEXT NOT NULL, human_confirmed BOOLEAN NOT NULL DEFAULT FALSE,
  snapshot_sha256 CHAR(64) NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_pc_workstream_signoff (organisation_id,workstream_id,created_at), FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (workstream_id) REFERENCES platform_completeness_workstreams(id), FOREIGN KEY (signed_off_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
