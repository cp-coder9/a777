-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS qc_control_definitions (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, control_key VARCHAR(100) NOT NULL DEFAULT 'nuanced_gap_test',
  definition_version INT UNSIGNED NOT NULL DEFAULT 1, category_key VARCHAR(100) NOT NULL, title VARCHAR(220) NOT NULL,
  description TEXT NULL, sort_order SMALLINT UNSIGNED NOT NULL, is_active BOOLEAN NOT NULL DEFAULT TRUE, created_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_qc_definition_category (organisation_id,control_key,definition_version,category_key),
  KEY idx_qc_definitions (organisation_id,is_active,sort_order),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS qc_run_records (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NULL, control_key VARCHAR(100) NOT NULL DEFAULT 'nuanced_gap_test',
  definition_version INT UNSIGNED NOT NULL DEFAULT 1, title VARCHAR(220) NOT NULL,
  scope_type ENUM('pr','repo','live_site','feature','implementation_pack','site_audit') NOT NULL, target VARCHAR(1000) NOT NULL,
  reviewer_user_id BINARY(16) NOT NULL, assistance_label VARCHAR(160) NULL, technical_verification_summary TEXT NULL,
  pack_goal_match_summary TEXT NULL, nuanced_gap_summary TEXT NULL,
  recommendation ENUM('continue_qc','accept','accept_with_notes','changes_requested','block_merge_or_deploy') NOT NULL DEFAULT 'continue_qc',
  lifecycle_status ENUM('draft','in_review','changes_requested','accepted','accepted_with_notes','blocked') NOT NULL DEFAULT 'draft',
  signoff_status ENUM('not_signed','accepted','accepted_with_notes','changes_requested','block_merge','block_deploy') NOT NULL DEFAULT 'not_signed',
  idempotency_key VARCHAR(120) NOT NULL, expected_version INT UNSIGNED NOT NULL DEFAULT 1,
  started_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), completed_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_qc_run_idempotency (organisation_id,idempotency_key), KEY idx_qc_runs (organisation_id,project_id,lifecycle_status,updated_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (reviewer_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS qc_run_scope_items (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, run_id BINARY(16) NOT NULL,
  scope_kind ENUM('pack','brief','repo','pr','url','route','file','project','workflow') NOT NULL, reference VARCHAR(500) NOT NULL,
  source_promise TEXT NOT NULL, affected_workflow VARCHAR(220) NULL, cross_pack_target VARCHAR(220) NULL, created_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_qc_scope_item (run_id,scope_kind,reference), KEY idx_qc_scope_items (organisation_id,run_id,scope_kind),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (run_id) REFERENCES qc_run_records(id), FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS qc_category_results (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, run_id BINARY(16) NOT NULL, category_key VARCHAR(100) NOT NULL,
  revision INT UNSIGNED NOT NULL, status ENUM('pass','fail','partial','not_applicable','deferred','blocked','manually_satisfied','external_system_handled') NOT NULL,
  reason TEXT NOT NULL, reviewed_by BINARY(16) NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_qc_category_revision (run_id,category_key,revision), KEY idx_qc_category_results (organisation_id,run_id,category_key,revision),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (run_id) REFERENCES qc_run_records(id), FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS qc_gap_findings (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NULL, run_id BINARY(16) NOT NULL,
  category_key VARCHAR(100) NOT NULL, severity ENUM('blocker','high','medium','low') NOT NULL, title VARCHAR(220) NOT NULL,
  description TEXT NOT NULL, evidence_type ENUM('file','route','screenshot','test_output','api_output','assumption','user_report') NOT NULL,
  evidence_ref VARCHAR(1000) NOT NULL, affected_pack VARCHAR(220) NULL, affected_workflow VARCHAR(220) NULL, cross_pack_impact TEXT NULL,
  required_fix TEXT NOT NULL, retest_steps TEXT NOT NULL, owner_user_id BINARY(16) NULL,
  status ENUM('open','in_progress','fixed','retest_passed','accepted_risk','not_applicable') NOT NULL DEFAULT 'open',
  expected_version INT UNSIGNED NOT NULL DEFAULT 1, created_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  KEY idx_qc_findings (organisation_id,run_id,severity,status), KEY idx_qc_project_findings (organisation_id,project_id,status),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (run_id) REFERENCES qc_run_records(id),
  FOREIGN KEY (owner_user_id) REFERENCES users(id), FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS qc_evidence_links (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NULL, run_id BINARY(16) NOT NULL,
  finding_id BINARY(16) NULL, stored_file_id BINARY(16) NULL,
  evidence_type ENUM('file','route','screenshot','test_output','api_output','assumption','user_report') NOT NULL,
  evidence_ref VARCHAR(1000) NULL, label VARCHAR(220) NOT NULL, notes TEXT NULL, idempotency_key VARCHAR(120) NOT NULL,
  linked_by BINARY(16) NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_qc_evidence_idempotency (organisation_id,idempotency_key), KEY idx_qc_evidence (organisation_id,run_id,finding_id,created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (run_id) REFERENCES qc_run_records(id),
  FOREIGN KEY (finding_id) REFERENCES qc_gap_findings(id), FOREIGN KEY (stored_file_id) REFERENCES stored_files(id), FOREIGN KEY (linked_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS qc_retest_tasks (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NULL, run_id BINARY(16) NOT NULL, finding_id BINARY(16) NOT NULL,
  assigned_user_id BINARY(16) NULL, assigned_label VARCHAR(160) NULL, due_at DATETIME(6) NULL, expected_test TEXT NOT NULL,
  result_summary TEXT NULL, evidence_ref VARCHAR(1000) NULL, status ENUM('open','in_progress','passed','failed','cancelled') NOT NULL DEFAULT 'open',
  expected_version INT UNSIGNED NOT NULL DEFAULT 1, created_by BINARY(16) NOT NULL, completed_by BINARY(16) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), completed_at DATETIME(6) NULL, updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  KEY idx_qc_retests (organisation_id,run_id,status,due_at), KEY idx_qc_finding_retests (organisation_id,finding_id,created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (run_id) REFERENCES qc_run_records(id),
  FOREIGN KEY (finding_id) REFERENCES qc_gap_findings(id), FOREIGN KEY (assigned_user_id) REFERENCES users(id), FOREIGN KEY (created_by) REFERENCES users(id), FOREIGN KEY (completed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS qc_risk_entries (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NULL, run_id BINARY(16) NOT NULL, finding_id BINARY(16) NOT NULL,
  severity ENUM('blocker','high') NOT NULL, title VARCHAR(220) NOT NULL, description TEXT NOT NULL,
  status ENUM('open','mitigating','accepted','closed') NOT NULL DEFAULT 'open', risk_accepted_by BINARY(16) NULL,
  risk_acceptance_reason TEXT NULL, risk_review_date DATE NULL, human_confirmed BOOLEAN NOT NULL DEFAULT FALSE,
  expected_version INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_qc_finding_risk (finding_id), KEY idx_qc_risks (organisation_id,project_id,severity,status),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (run_id) REFERENCES qc_run_records(id),
  FOREIGN KEY (finding_id) REFERENCES qc_gap_findings(id), FOREIGN KEY (risk_accepted_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS qc_signoffs (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NULL, run_id BINARY(16) NOT NULL, signed_off_by BINARY(16) NOT NULL,
  decision ENUM('accepted','accepted_with_notes','changes_requested','block_merge','block_deploy') NOT NULL, reason TEXT NOT NULL,
  human_confirmed BOOLEAN NOT NULL DEFAULT FALSE, snapshot_sha256 CHAR(64) NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_qc_signoffs (organisation_id,run_id,created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (run_id) REFERENCES qc_run_records(id), FOREIGN KEY (signed_off_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS qc_role_assignments (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, user_id BINARY(16) NOT NULL,
  qc_role ENUM('qc_reviewer','developer','professional_reviewer','site_auditor','viewer') NOT NULL, assigned_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_qc_role (organisation_id,user_id), KEY idx_qc_roles (organisation_id,qc_role),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (user_id) REFERENCES users(id), FOREIGN KEY (assigned_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
