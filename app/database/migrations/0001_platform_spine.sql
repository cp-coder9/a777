-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS legal_notices (
  id BINARY(16) PRIMARY KEY,
  notice_type VARCHAR(64) NOT NULL,
  version VARCHAR(32) NOT NULL,
  title VARCHAR(200) NOT NULL,
  content_sha256 CHAR(64) NOT NULL,
  published_at DATETIME(6) NOT NULL,
  effective_at DATETIME(6) NOT NULL,
  retired_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_legal_notice_type_version (notice_type, version),
  KEY idx_legal_notice_effective (notice_type, effective_at, retired_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS idempotency_keys (
  id BINARY(16) PRIMARY KEY,
  scope VARCHAR(100) NOT NULL,
  idempotency_key VARCHAR(200) NOT NULL,
  request_hash CHAR(64) NOT NULL,
  status ENUM('processing', 'completed', 'failed') NOT NULL DEFAULT 'processing',
  response_status SMALLINT UNSIGNED NULL,
  response_body JSON NULL,
  locked_until DATETIME(6) NULL,
  expires_at DATETIME(6) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_idempotency_scope_key (scope, idempotency_key),
  KEY idx_idempotency_expiry (expires_at),
  KEY idx_idempotency_status_lock (status, locked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scheduled_jobs (
  id BINARY(16) PRIMARY KEY,
  job_type VARCHAR(100) NOT NULL,
  payload JSON NOT NULL,
  status ENUM('pending', 'running', 'completed', 'failed', 'dead') NOT NULL DEFAULT 'pending',
  attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  available_at DATETIME(6) NOT NULL,
  locked_at DATETIME(6) NULL,
  locked_by VARCHAR(100) NULL,
  completed_at DATETIME(6) NULL,
  failed_at DATETIME(6) NULL,
  last_error_code VARCHAR(100) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  KEY idx_scheduled_jobs_claim (status, available_at, locked_at),
  KEY idx_scheduled_jobs_type_status (job_type, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tool_definitions (
  id BINARY(16) PRIMARY KEY,
  tool_key VARCHAR(100) NOT NULL,
  label VARCHAR(160) NOT NULL,
  description TEXT NULL,
  release_status ENUM('active', 'disabled') NOT NULL DEFAULT 'disabled',
  required_permission VARCHAR(100) NULL,
  configuration_schema JSON NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_tool_definition_key (tool_key),
  KEY idx_tool_definition_status (release_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
