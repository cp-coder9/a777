-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS provider_release_dispatches (
  release_request_id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  provider_id BINARY(16) NOT NULL,
  idempotency_key VARCHAR(120) NOT NULL,
  request_payload JSON NOT NULL,
  request_sha256 CHAR(64) NOT NULL,
  status ENUM('prepared','unknown','accepted') NOT NULL DEFAULT 'prepared',
  provider_reference VARCHAR(220) COLLATE utf8mb4_bin NULL,
  last_error_code VARCHAR(100) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_release_dispatch_key (idempotency_key),
  UNIQUE KEY uq_release_dispatch_reference (provider_id,provider_reference),
  FOREIGN KEY (release_request_id) REFERENCES provider_release_requests(id),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (provider_id) REFERENCES financial_providers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS remote_desktop_sessions (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  project_id BINARY(16) NOT NULL,
  actor_user_id BINARY(16) NOT NULL,
  host_id VARCHAR(100) COLLATE utf8mb4_bin NOT NULL,
  app_id VARCHAR(100) COLLATE utf8mb4_bin NOT NULL,
  booking_reference VARCHAR(100) COLLATE utf8mb4_bin NOT NULL,
  client_request_id VARCHAR(100) COLLATE utf8mb4_bin NOT NULL,
  status ENUM('requested','ready','active','closing','closed','failed','expired') NOT NULL DEFAULT 'requested',
  broker_reference VARCHAR(220) COLLATE utf8mb4_bin NULL,
  expires_at DATETIME(6) NOT NULL,
  policy_sha256 CHAR(64) NOT NULL,
  last_error_code VARCHAR(100) NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_desktop_request (organisation_id,actor_user_id,client_request_id),
  UNIQUE KEY uq_desktop_reference (organisation_id,broker_reference),
  KEY idx_desktop_scope (organisation_id,project_id,actor_user_id,status),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id),
  FOREIGN KEY (actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS remote_desktop_events (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  session_id BINARY(16) NOT NULL,
  external_event_id VARCHAR(180) COLLATE utf8mb4_bin NOT NULL,
  payload_sha256 CHAR(64) NOT NULL,
  status VARCHAR(20) NOT NULL,
  received_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_desktop_event (organisation_id,external_event_id),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (session_id) REFERENCES remote_desktop_sessions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
