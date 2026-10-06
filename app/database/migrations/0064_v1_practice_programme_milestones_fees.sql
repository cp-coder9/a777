-- architex:migration-mode=ddl
-- Practice module: construction programme (workstreams), milestones and fee settings.
-- These were prototype-only in v1.html; the user directed building real persistence.
CREATE TABLE IF NOT EXISTS practice_programme (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL,
  title VARCHAR(220) NOT NULL, owner_role VARCHAR(60) NOT NULL, weeks SMALLINT UNSIGNED NOT NULL,
  progress TINYINT UNSIGNED NOT NULL DEFAULT 0, sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_by BINARY(16) NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  KEY idx_practice_programme (organisation_id,project_id,sort_order), FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS practice_milestones (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL,
  title VARCHAR(220) NOT NULL, target_date DATE NOT NULL, completed BOOLEAN NOT NULL DEFAULT FALSE,
  created_by BINARY(16) NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  KEY idx_practice_milestones (organisation_id,project_id,target_date), FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS practice_fee_settings (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL,
  construction_cost_cents BIGINT UNSIGNED NOT NULL, fee_percent DECIMAL(6,2) NOT NULL,
  planned_delivery_cents BIGINT UNSIGNED NOT NULL, invoiced_cents BIGINT UNSIGNED NOT NULL, expenses_cents BIGINT UNSIGNED NOT NULL,
  created_by BINARY(16) NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_practice_fee_project (organisation_id,project_id), FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
