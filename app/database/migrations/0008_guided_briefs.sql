-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS guided_briefs (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  created_by BINARY(16) NOT NULL,
  project_id BINARY(16) NULL,
  title VARCHAR(180) NOT NULL,
  answers JSON NOT NULL,
  current_step SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  status ENUM('draft','submitted','converted','superseded') NOT NULL DEFAULT 'draft',
  version INT UNSIGNED NOT NULL DEFAULT 1,
  submitted_at DATETIME(6) NULL,
  converted_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  KEY idx_guided_briefs_owner (organisation_id, created_by, status, updated_at),
  KEY idx_guided_briefs_project (organisation_id, project_id, status),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  FOREIGN KEY (project_id) REFERENCES projects(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS brief_interpretations (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  brief_id BINARY(16) NOT NULL,
  brief_version INT UNSIGNED NOT NULL,
  ruleset_version VARCHAR(40) NOT NULL,
  interpretation JSON NOT NULL,
  created_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_brief_interpretation_version (brief_id, brief_version),
  KEY idx_brief_interpretation_org (organisation_id, brief_id, created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (brief_id) REFERENCES guided_briefs(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
