-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS survey_jobs (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL,
  job_number VARCHAR(80) NOT NULL, survey_type ENUM('topographical','boundary','beacon_relocation','subdivision','consolidation','sectional_title','servitude','lease_diagram','general_plan','as_built','other') NOT NULL,
  title VARCHAR(220) NOT NULL, responsible_role VARCHAR(80) NOT NULL, coordinate_reference_system VARCHAR(180) NOT NULL, vertical_datum VARCHAR(180) NOT NULL, source_basis JSON NOT NULL,
  status ENUM('draft','issued') NOT NULL DEFAULT 'draft', version INT UNSIGNED NOT NULL DEFAULT 1,
  fee_payload JSON NULL, private_professional_fee_cents BIGINT UNSIGNED NULL, statutory_fee_cents BIGINT UNSIGNED NULL, total_fee_cents BIGINT UNSIGNED NULL,
  issue_reference VARCHAR(180) NULL, issue_reason VARCHAR(500) NULL, snapshot_payload JSON NULL, snapshot_sha256 CHAR(64) NULL, project_record_id BINARY(16) NULL, issued_by BINARY(16) NULL, issued_at DATETIME(6) NULL,
  created_by BINARY(16) NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_survey_job_number (organisation_id,job_number), KEY idx_survey_project (organisation_id,project_id,status,created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (project_record_id) REFERENCES project_records(id), FOREIGN KEY (issued_by) REFERENCES users(id), FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS survey_observations (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL, survey_job_id BINARY(16) NOT NULL,
  point_code VARCHAR(80) NOT NULL, observation_type ENUM('control_point','benchmark','beacon','boundary_marker','detail_point','other') NOT NULL,
  easting DECIMAL(20,4) NOT NULL, northing DECIMAL(20,4) NOT NULL, elevation DECIMAL(20,4) NULL, status ENUM('observed','verified','rejected','superseded') NOT NULL,
  source_reference VARCHAR(180) NOT NULL, source_url VARCHAR(1000) NOT NULL, observed_on DATE NOT NULL, payload JSON NOT NULL, created_by BINARY(16) NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_survey_point (survey_job_id,point_code), KEY idx_survey_observations (organisation_id,survey_job_id,status),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (survey_job_id) REFERENCES survey_jobs(id), FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS survey_deliverables (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL, survey_job_id BINARY(16) NOT NULL,
  deliverable_type ENUM('topographical_plan','cadastral_diagram','general_plan','beacon_certificate','control_report','servitude_diagram','sectional_title_plan','as_built_plan','coordinate_schedule','other') NOT NULL,
  title VARCHAR(220) NOT NULL, status ENUM('draft','pending_review','complete','submitted_externally','accepted','rejected','superseded') NOT NULL,
  external_reference VARCHAR(180) NOT NULL, source_url VARCHAR(1000) NOT NULL, payload JSON NOT NULL, created_by BINARY(16) NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_survey_deliverables (organisation_id,survey_job_id,status), FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (survey_job_id) REFERENCES survey_jobs(id), FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
