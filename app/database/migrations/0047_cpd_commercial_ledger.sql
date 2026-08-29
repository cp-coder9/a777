-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS cpd_purchase_ledger (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, entitlement_id BINARY(16) NOT NULL,
  course_id BINARY(16) NOT NULL, learner_user_id BINARY(16) NOT NULL, content_owner_user_id BINARY(16) NOT NULL,
  gross_cents BIGINT UNSIGNED NOT NULL, platform_fee_cents BIGINT UNSIGNED NOT NULL, content_owner_net_cents BIGINT UNSIGNED NOT NULL,
  platform_fee_basis_points SMALLINT UNSIGNED NOT NULL, currency CHAR(3) NOT NULL DEFAULT 'ZAR',
  status ENUM('recorded','refunded','reversed') NOT NULL DEFAULT 'recorded', recorded_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_cpd_purchase_entitlement (entitlement_id), KEY idx_cpd_purchase_owner (organisation_id,content_owner_user_id,status,recorded_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (entitlement_id) REFERENCES cpd_entitlements(id),
  FOREIGN KEY (course_id) REFERENCES cpd_courses(id), FOREIGN KEY (learner_user_id) REFERENCES users(id), FOREIGN KEY (content_owner_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cpd_payouts (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, purchase_ledger_id BINARY(16) NOT NULL,
  content_owner_user_id BINARY(16) NOT NULL, payout_cents BIGINT UNSIGNED NOT NULL, currency CHAR(3) NOT NULL DEFAULT 'ZAR',
  status ENUM('pending','approved','paid','held','cancelled') NOT NULL DEFAULT 'pending', moves_money BOOLEAN NOT NULL DEFAULT FALSE,
  reviewed_by BINARY(16) NULL, review_reason VARCHAR(2000) NULL, provider_reference VARCHAR(220) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), reviewed_at DATETIME(6) NULL, paid_at DATETIME(6) NULL,
  UNIQUE KEY uq_cpd_payout_purchase (purchase_ledger_id), KEY idx_cpd_payout_owner (organisation_id,content_owner_user_id,status,created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (purchase_ledger_id) REFERENCES cpd_purchase_ledger(id),
  FOREIGN KEY (content_owner_user_id) REFERENCES users(id), FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
