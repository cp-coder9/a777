-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS projects (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  project_code VARCHAR(60) NOT NULL,
  project_name VARCHAR(180) NOT NULL,
  client_name VARCHAR(180) NOT NULL,
  municipality VARCHAR(180) NOT NULL,
  property_reference VARCHAR(180) NOT NULL,
  property_use VARCHAR(120) NOT NULL,
  land_use_notes TEXT NOT NULL,
  lead_professional_role VARCHAR(60) NOT NULL,
  current_phase ENUM('onboarding','feasibility','appointment','concept_design','design_development','municipal_submission','tender_procurement','construction_execution','closeout') NOT NULL DEFAULT 'onboarding',
  version INT UNSIGNED NOT NULL DEFAULT 1,
  created_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_projects_org_code (organisation_id, project_code),
  KEY idx_projects_org_phase (organisation_id, current_phase),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_records (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  project_id BINARY(16) NOT NULL,
  phase VARCHAR(40) NOT NULL,
  module_key VARCHAR(40) NOT NULL,
  record_type VARCHAR(80) NOT NULL,
  title VARCHAR(220) NOT NULL,
  status ENUM('draft','pending_review','approved','issued','superseded','rejected','missing') NOT NULL,
  payload JSON NOT NULL,
  approvals JSON NOT NULL,
  linked_record_ids JSON NOT NULL,
  created_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_records_project_type_status (organisation_id, project_id, record_type, status),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS workflow_events (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  project_id BINARY(16) NOT NULL,
  event_type VARCHAR(80) NOT NULL,
  priority ENUM('low','medium','high','critical') NOT NULL,
  source_module VARCHAR(40) NOT NULL,
  payload JSON NOT NULL,
  occurred_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_events_project_time (organisation_id, project_id, occurred_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inbox_items (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  project_id BINARY(16) NOT NULL,
  assigned_user_id BINARY(16) NULL,
  assigned_role VARCHAR(60) NULL,
  event_type VARCHAR(80) NOT NULL,
  title VARCHAR(220) NOT NULL,
  detail TEXT NOT NULL,
  priority ENUM('low','medium','high','critical') NOT NULL,
  status ENUM('open','read','resolved','dismissed') NOT NULL DEFAULT 'open',
  payload JSON NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  resolved_at DATETIME(6) NULL,
  KEY idx_inbox_org_status (organisation_id, status, created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id),
  FOREIGN KEY (assigned_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_events (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NULL,
  actor_user_id BINARY(16) NULL,
  action VARCHAR(100) NOT NULL,
  subject_type VARCHAR(80) NOT NULL,
  subject_id BINARY(16) NOT NULL,
  metadata JSON NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_audit_org_time (organisation_id, created_at),
  KEY idx_audit_subject (subject_type, subject_id, created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
