-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS practice_pipeline_items (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL,
  title VARCHAR(220) NOT NULL, stage ENUM('lead','qualified','proposal','negotiation','won','lost','abandoned') NOT NULL,
  probability_percent TINYINT UNSIGNED NOT NULL, forecast_value_cents BIGINT UNSIGNED NOT NULL, weighted_value_cents BIGINT UNSIGNED NOT NULL,
  outcome_reason VARCHAR(1000) NULL, created_by BINARY(16) NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  KEY idx_practice_pipeline (organisation_id,project_id,stage,updated_at), FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS practice_tasks (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL, title VARCHAR(220) NOT NULL,
  assigned_user_id BINARY(16) NOT NULL, due_at DATETIME(6) NOT NULL, sla_hours SMALLINT UNSIGNED NOT NULL, priority ENUM('low','medium','high','critical') NOT NULL,
  status ENUM('open','in_progress','blocked','completed','cancelled') NOT NULL DEFAULT 'open', created_by BINARY(16) NOT NULL, completed_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  KEY idx_practice_tasks (organisation_id,project_id,status,due_at), KEY idx_practice_tasks_assignee (organisation_id,assigned_user_id,status,due_at), FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (assigned_user_id) REFERENCES users(id), FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS practice_resource_allocations (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL, user_id BINARY(16) NOT NULL,
  starts_on DATE NOT NULL, ends_on DATE NOT NULL, allocated_minutes INT UNSIGNED NOT NULL, capacity_minutes INT UNSIGNED NOT NULL, over_allocated BOOLEAN NOT NULL DEFAULT FALSE,
  status ENUM('planned','confirmed','cancelled') NOT NULL DEFAULT 'planned', created_by BINARY(16) NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_practice_resources (organisation_id,project_id,user_id,starts_on,ends_on), FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (user_id) REFERENCES users(id), FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS practice_timesheets (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL, user_id BINARY(16) NOT NULL, task_id BINARY(16) NULL,
  workstage VARCHAR(100) NOT NULL, work_date DATE NOT NULL, minutes SMALLINT UNSIGNED NOT NULL, billable BOOLEAN NOT NULL, description VARCHAR(1000) NOT NULL,
  status ENUM('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft', reviewed_by BINARY(16) NULL, review_reason VARCHAR(1000) NULL, submitted_at DATETIME(6) NULL, reviewed_at DATETIME(6) NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  KEY idx_practice_time (organisation_id,project_id,status,work_date), KEY idx_practice_time_user (organisation_id,user_id,work_date), FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (user_id) REFERENCES users(id), FOREIGN KEY (task_id) REFERENCES practice_tasks(id), FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS practice_supervision_reviews (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL, candidate_user_id BINARY(16) NOT NULL, mentor_user_id BINARY(16) NOT NULL,
  period_start DATE NOT NULL, period_end DATE NOT NULL, summary TEXT NOT NULL, competencies JSON NOT NULL, status ENUM('review_required','signed_off','changes_required') NOT NULL DEFAULT 'review_required',
  created_by BINARY(16) NOT NULL, signed_off_by BINARY(16) NULL, signoff_reason VARCHAR(1000) NULL, signed_off_at DATETIME(6) NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_practice_supervision (organisation_id,project_id,candidate_user_id,status,period_end), FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (candidate_user_id) REFERENCES users(id), FOREIGN KEY (mentor_user_id) REFERENCES users(id), FOREIGN KEY (created_by) REFERENCES users(id), FOREIGN KEY (signed_off_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS practice_invoice_readiness (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL, approved_billable_minutes INT UNSIGNED NOT NULL,
  expenses_ready BOOLEAN NOT NULL, disbursements_ready BOOLEAN NOT NULL, blockers JSON NOT NULL, status ENUM('blocked','ready_for_review') NOT NULL,
  creates_invoice BOOLEAN NOT NULL DEFAULT FALSE, moves_money BOOLEAN NOT NULL DEFAULT FALSE, snapshot_sha256 CHAR(64) NOT NULL, created_by BINARY(16) NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_practice_invoice_ready (organisation_id,project_id,status,created_at), FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS practice_registration_renewals (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, user_id BINARY(16) NOT NULL, professional_body VARCHAR(100) NOT NULL, registration_number VARCHAR(160) NOT NULL,
  expires_on DATE NOT NULL, required_cpd_points DECIMAL(8,2) NOT NULL, recorded_cpd_points DECIMAL(8,2) NOT NULL, pi_expires_on DATE NULL,
  status ENUM('current','expiring','cpd_shortfall','expired') NOT NULL, verification_guaranteed BOOLEAN NOT NULL DEFAULT FALSE, created_by BINARY(16) NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  KEY idx_practice_registration (organisation_id,user_id,status,expires_on), FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (user_id) REFERENCES users(id), FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS practice_templates (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, template_key BINARY(16) NOT NULL, name VARCHAR(220) NOT NULL, category VARCHAR(100) NOT NULL,
  version INT UNSIGNED NOT NULL, allowed_roles JSON NOT NULL, content MEDIUMTEXT NOT NULL, content_sha256 CHAR(64) NOT NULL, status ENUM('draft','active','superseded') NOT NULL DEFAULT 'active',
  supersedes_id BINARY(16) NULL, revision_reason VARCHAR(1000) NULL, created_by BINARY(16) NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_practice_template_version (organisation_id,template_key,version), KEY idx_practice_templates (organisation_id,status,category,name), FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (supersedes_id) REFERENCES practice_templates(id), FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS practice_agent_recommendations (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NULL, source_type VARCHAR(80) NOT NULL, source_object_id BINARY(16) NOT NULL,
  title VARCHAR(220) NOT NULL, rationale VARCHAR(1000) NOT NULL, severity ENUM('low','medium','high','critical') NOT NULL, status ENUM('open','dismissed','resolved') NOT NULL DEFAULT 'open', created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_practice_recommendations (organisation_id,project_id,status,severity), FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
