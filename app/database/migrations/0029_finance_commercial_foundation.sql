-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS commercial_baselines (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  project_id BINARY(16) NOT NULL,
  source_appointment_id BINARY(16) NOT NULL,
  original_contract_sum_cents BIGINT UNSIGNED NOT NULL,
  current_contract_sum_cents BIGINT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL,
  vat_included TINYINT(1) NOT NULL,
  contingency_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  retention_basis_points SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('active','superseded','closed') NOT NULL DEFAULT 'active',
  revision INT UNSIGNED NOT NULL DEFAULT 1,
  created_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_commercial_active_project (organisation_id,project_id,status),
  KEY idx_commercial_baseline_project (organisation_id,project_id,updated_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id),
  FOREIGN KEY (source_appointment_id) REFERENCES professional_appointments(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commercial_baseline_revisions (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  baseline_id BINARY(16) NOT NULL,
  revision INT UNSIGNED NOT NULL,
  change_type ENUM('baseline_created','approved_variation') NOT NULL,
  source_id BINARY(16) NULL,
  contract_sum_cents BIGINT UNSIGNED NOT NULL,
  snapshot_payload JSON NOT NULL,
  snapshot_sha256 CHAR(64) NOT NULL,
  reason VARCHAR(1000) NOT NULL,
  created_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_commercial_baseline_revision (baseline_id,revision),
  UNIQUE KEY uq_commercial_baseline_sha (baseline_id,snapshot_sha256),
  KEY idx_commercial_revision_tenant (organisation_id,baseline_id,created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (baseline_id) REFERENCES commercial_baselines(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commercial_payment_schedules (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  baseline_id BINARY(16) NOT NULL,
  title VARCHAR(220) NOT NULL,
  scheduled_total_cents BIGINT UNSIGNED NOT NULL,
  status ENUM('issued','superseded') NOT NULL DEFAULT 'issued',
  snapshot_payload JSON NOT NULL,
  snapshot_sha256 CHAR(64) NOT NULL,
  reason VARCHAR(1000) NOT NULL,
  created_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_commercial_schedule (organisation_id,baseline_id,status,created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (baseline_id) REFERENCES commercial_baselines(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commercial_payment_milestones (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  schedule_id BINARY(16) NOT NULL,
  sequence_number SMALLINT UNSIGNED NOT NULL,
  label VARCHAR(220) NOT NULL,
  amount_cents BIGINT UNSIGNED NOT NULL,
  due_date DATE NOT NULL,
  UNIQUE KEY uq_commercial_milestone_sequence (schedule_id,sequence_number),
  KEY idx_commercial_milestone_tenant (organisation_id,schedule_id,due_date),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (schedule_id) REFERENCES commercial_payment_schedules(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commercial_variations (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  baseline_id BINARY(16) NOT NULL,
  reference VARCHAR(80) NOT NULL,
  title VARCHAR(220) NOT NULL,
  description TEXT NOT NULL,
  amount_cents BIGINT NOT NULL,
  time_impact_days INT NOT NULL DEFAULT 0,
  status ENUM('draft','under_review','approved','rejected','incorporated') NOT NULL DEFAULT 'draft',
  version INT UNSIGNED NOT NULL DEFAULT 1,
  created_by BINARY(16) NOT NULL,
  submitted_by BINARY(16) NULL,
  submitted_at DATETIME(6) NULL,
  approved_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_commercial_variation_reference (baseline_id,reference),
  KEY idx_commercial_variation (organisation_id,baseline_id,status,updated_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (baseline_id) REFERENCES commercial_baselines(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  FOREIGN KEY (submitted_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commercial_variation_revisions (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  variation_id BINARY(16) NOT NULL,
  revision INT UNSIGNED NOT NULL,
  snapshot_payload JSON NOT NULL,
  snapshot_sha256 CHAR(64) NOT NULL,
  reason VARCHAR(1000) NOT NULL,
  created_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_commercial_variation_revision (variation_id,revision),
  UNIQUE KEY uq_commercial_variation_sha (variation_id,snapshot_sha256),
  KEY idx_commercial_variation_revision_tenant (organisation_id,variation_id,created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (variation_id) REFERENCES commercial_variations(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commercial_variation_approvals (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  variation_id BINARY(16) NOT NULL,
  approval_type ENUM('quantity_surveyor','lead_professional','client') NOT NULL,
  decision ENUM('approved','rejected') NOT NULL,
  reason VARCHAR(1000) NOT NULL,
  decided_by BINARY(16) NOT NULL,
  decided_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_commercial_variation_approval (variation_id,approval_type),
  UNIQUE KEY uq_commercial_variation_approver (variation_id,decided_by),
  KEY idx_commercial_variation_approval_tenant (organisation_id,variation_id,decision),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (variation_id) REFERENCES commercial_variations(id),
  FOREIGN KEY (decided_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
