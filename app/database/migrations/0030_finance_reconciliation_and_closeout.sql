-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS provider_webhook_events (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  provider_id BINARY(16) NOT NULL,
  release_request_id BINARY(16) NULL,
  external_event_id VARCHAR(180) NOT NULL,
  event_type ENUM('payment_processing','payment_paid','payment_failed','payment_refunded') NOT NULL,
  provider_reference VARCHAR(220) NOT NULL,
  amount_cents BIGINT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL,
  signature_sha256 CHAR(64) NOT NULL,
  payload_sha256 CHAR(64) NOT NULL,
  payload JSON NOT NULL,
  outcome ENUM('applied','rejected') NOT NULL,
  rejection_reason VARCHAR(180) NULL,
  received_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_provider_webhook_event (provider_id,external_event_id),
  KEY idx_provider_webhook_reference (organisation_id,provider_id,provider_reference,received_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (provider_id) REFERENCES financial_providers(id),
  FOREIGN KEY (release_request_id) REFERENCES provider_release_requests(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commercial_cashflow_snapshots (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  baseline_id BINARY(16) NOT NULL,
  snapshot_payload JSON NOT NULL,
  snapshot_sha256 CHAR(64) NOT NULL,
  created_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_commercial_cashflow_sha (baseline_id,snapshot_sha256),
  KEY idx_commercial_cashflow (organisation_id,baseline_id,created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (baseline_id) REFERENCES commercial_baselines(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commercial_retention_release_requests (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  baseline_id BINARY(16) NOT NULL,
  provider_id BINARY(16) NOT NULL,
  requested_amount_cents BIGINT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL,
  status ENUM('approval_required','provider_configuration_required','approved_for_provider_request','submitted_to_provider','provider_confirmed_paid','disputed_locked','rejected') NOT NULL,
  provider_reference VARCHAR(220) NULL,
  provider_paid_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  conditions_snapshot JSON NOT NULL,
  snapshot_sha256 CHAR(64) NOT NULL,
  reason VARCHAR(1000) NOT NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  created_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  KEY idx_retention_release (organisation_id,baseline_id,status,updated_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (baseline_id) REFERENCES commercial_baselines(id),
  FOREIGN KEY (provider_id) REFERENCES financial_providers(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commercial_retention_release_approvals (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  retention_release_id BINARY(16) NOT NULL,
  approval_type ENUM('lead_professional','client') NOT NULL,
  decision ENUM('approved','rejected') NOT NULL,
  reason VARCHAR(1000) NOT NULL,
  decided_by BINARY(16) NOT NULL,
  decided_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_retention_release_approval (retention_release_id,approval_type),
  UNIQUE KEY uq_retention_release_approver (retention_release_id,decided_by),
  KEY idx_retention_release_approval (organisation_id,retention_release_id,decision),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (retention_release_id) REFERENCES commercial_retention_release_requests(id),
  FOREIGN KEY (decided_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
