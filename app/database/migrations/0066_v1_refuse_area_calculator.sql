-- architex:migration-mode=ddl
-- Municipal Refuse Area Calculator — real persistence for the screen the mockup describes.
--
-- Two tables, mirroring the pattern the municipal requirement matrix already uses:
--
--   refuse_rule_packs    the thresholds an assessment needs, versioned and requiring a named
--                        professional verification before any verdict can be declared. The app
--                        never invents a criterion: a rule pack is data with a source_reference,
--                        and an unverified pack produces "not assessed", never a green Pass.
--
--   refuse_calculations  one saved calculation: the inputs the professional entered, the
--                        arithmetic the server performed on them, and the resulting assessment
--                        snapshot tied to the exact rule pack version used.
CREATE TABLE IF NOT EXISTS refuse_rule_packs (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  municipality_key VARCHAR(120) NOT NULL,
  municipality_name VARCHAR(180) NOT NULL,
  version VARCHAR(100) NOT NULL,
  status ENUM('review_required','verified','withdrawn') NOT NULL DEFAULT 'review_required',
  source_reference VARCHAR(500) NOT NULL,
  criteria JSON NOT NULL,
  bin_footprint_m2 DECIMAL(6,3) NOT NULL DEFAULT 0.438,
  default_bin_capacity_litres SMALLINT UNSIGNED NOT NULL DEFAULT 240,
  storage_height_mm SMALLINT UNSIGNED NOT NULL DEFAULT 2000,
  circulation_allowance_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_by BINARY(16) NOT NULL,
  reviewed_by BINARY(16) NULL,
  reviewed_at DATETIME(6) NULL,
  review_reason VARCHAR(500) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_refuse_rule_pack_version (organisation_id,municipality_key,version),
  KEY idx_refuse_rule_pack_current (organisation_id,municipality_key,status,created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS refuse_calculations (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  project_id BINARY(16) NOT NULL,
  rule_pack_id BINARY(16) NULL,
  rule_pack_version VARCHAR(100) NULL,
  rule_pack_status VARCHAR(40) NULL,
  title VARCHAR(220) NOT NULL,
  municipality VARCHAR(180) NOT NULL,
  building_type VARCHAR(120) NOT NULL,
  occupancy SMALLINT UNSIGNED NOT NULL,
  gross_floor_area_m2 DECIMAL(10,2) NULL,
  generation_rate_litres_per_person_day DECIMAL(8,3) NOT NULL,
  collection_interval_days TINYINT UNSIGNED NOT NULL,
  bin_capacity_litres SMALLINT UNSIGNED NOT NULL,
  bins_provided SMALLINT UNSIGNED NULL,
  provided_storage_area_m2 DECIMAL(10,2) NULL,
  access_width_m DECIMAL(6,2) NULL,
  ventilation_strategy VARCHAR(120) NULL,   -- natural / mechanical / none (the recorded strategy, not prose)
  drainage_provided TINYINT(1) NOT NULL DEFAULT 0,
  inputs JSON NOT NULL,
  results JSON NOT NULL,
  assessments JSON NOT NULL,
  advisory VARCHAR(500) NOT NULL DEFAULT '',
  status ENUM('draft','calculated','declared') NOT NULL DEFAULT 'draft',
  declaration_note VARCHAR(500) NULL,
  declared_by BINARY(16) NULL,
  declared_at DATETIME(6) NULL,
  revision INT UNSIGNED NOT NULL DEFAULT 1,
  client_request_id VARCHAR(64) NULL,
  created_by BINARY(16) NOT NULL,
  updated_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_refuse_calculation_request (organisation_id,client_request_id),
  KEY idx_refuse_calculation_project (organisation_id,project_id,status,updated_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id),
  FOREIGN KEY (rule_pack_id) REFERENCES refuse_rule_packs(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
