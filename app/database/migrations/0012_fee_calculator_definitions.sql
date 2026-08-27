-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS fee_source_versions (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  profession VARCHAR(60) NOT NULL,
  body VARCHAR(120) NOT NULL,
  title VARCHAR(255) NOT NULL,
  source_url VARCHAR(1000) NULL,
  effective_date DATE NOT NULL,
  status ENUM('draft','verified','retired') NOT NULL DEFAULT 'draft',
  source_sha256 CHAR(64) NOT NULL,
  source_metadata JSON NOT NULL,
  created_by BINARY(16) NOT NULL,
  reviewed_by BINARY(16) NULL,
  reviewed_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_fee_source_active (organisation_id,profession,status,effective_date),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fee_calculator_definitions (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  profession VARCHAR(60) NOT NULL,
  name VARCHAR(180) NOT NULL,
  version VARCHAR(40) NOT NULL,
  formula_type ENUM('sliding_scale','percentage_of_cost','stage_apportioned','time_based','area_unit','hybrid') NOT NULL,
  source_version_id BINARY(16) NOT NULL,
  configuration JSON NOT NULL,
  status ENUM('draft','verified','retired') NOT NULL DEFAULT 'draft',
  created_by BINARY(16) NOT NULL,
  reviewed_by BINARY(16) NULL,
  reviewed_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_fee_definition_version (organisation_id,profession,version),
  KEY idx_fee_definition_active (organisation_id,profession,status,created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (source_version_id) REFERENCES fee_source_versions(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
