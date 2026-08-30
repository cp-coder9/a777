-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS supplier_profiles (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL, name VARCHAR(180) NOT NULL,
  capability_level TINYINT UNSIGNED NOT NULL DEFAULT 0, connector_status ENUM('manual_ready','catalogue_upload_ready','integration_required','configured','suspended') NOT NULL,
  service_regions JSON NOT NULL, contact_email VARCHAR(320) NULL, status ENUM('active','suspended') NOT NULL DEFAULT 'active', version INT UNSIGNED NOT NULL DEFAULT 1,
  created_by BINARY(16) NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_supplier_profile_name (organisation_id,project_id,name), KEY idx_supplier_profiles (organisation_id,project_id,status),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_catalogue_revisions (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL, supplier_id BINARY(16) NOT NULL, revision INT UNSIGNED NOT NULL,
  source_kind ENUM('manual','csv_upload') NOT NULL, source_file_id BINARY(16) NULL, idempotency_key VARCHAR(120) NOT NULL, snapshot_payload JSON NOT NULL, snapshot_sha256 CHAR(64) NOT NULL,
  created_by BINARY(16) NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_supplier_catalogue_revision (supplier_id,revision), UNIQUE KEY uq_supplier_catalogue_idempotency (organisation_id,idempotency_key),
  KEY idx_supplier_catalogues (organisation_id,project_id,supplier_id,created_at), FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id),
  FOREIGN KEY (supplier_id) REFERENCES supplier_profiles(id), FOREIGN KEY (source_file_id) REFERENCES stored_files(id), FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_catalogue_items (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL, supplier_id BINARY(16) NOT NULL, catalogue_revision_id BINARY(16) NOT NULL,
  sku VARCHAR(120) NOT NULL, name VARCHAR(220) NOT NULL, category VARCHAR(120) NOT NULL, unit VARCHAR(40) NOT NULL, price_cents BIGINT UNSIGNED NOT NULL, stock_quantity DECIMAL(18,4) NOT NULL,
  lead_time_days INT UNSIGNED NOT NULL, standards JSON NOT NULL, delivery_regions JSON NOT NULL, datasheet_file_id BINARY(16) NULL, substitution_eligible BOOLEAN NOT NULL DEFAULT FALSE,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), UNIQUE KEY uq_supplier_catalogue_sku (catalogue_revision_id,sku), KEY idx_supplier_catalogue_match (organisation_id,project_id,category,unit),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (supplier_id) REFERENCES supplier_profiles(id),
  FOREIGN KEY (catalogue_revision_id) REFERENCES supplier_catalogue_revisions(id), FOREIGN KEY (datasheet_file_id) REFERENCES stored_files(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_procurement_baselines (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL, title VARCHAR(220) NOT NULL, status ENUM('draft','approved','superseded') NOT NULL DEFAULT 'draft',
  source_file_ids JSON NOT NULL, version INT UNSIGNED NOT NULL DEFAULT 1, snapshot_payload JSON NULL, snapshot_sha256 CHAR(64) NULL, created_by BINARY(16) NOT NULL, approved_by BINARY(16) NULL,
  approval_reason VARCHAR(1000) NULL, approved_at DATETIME(6) NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  KEY idx_supplier_baselines (organisation_id,project_id,status), FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id),
  FOREIGN KEY (created_by) REFERENCES users(id), FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_procurement_baseline_lines (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL, baseline_id BINARY(16) NOT NULL, source_item_ref VARCHAR(160) NOT NULL,
  description VARCHAR(500) NOT NULL, category VARCHAR(120) NOT NULL, specification VARCHAR(500) NULL, required_standard VARCHAR(180) NULL, quantity DECIMAL(18,4) NOT NULL, unit VARCHAR(40) NOT NULL,
  allow_substitution BOOLEAN NOT NULL DEFAULT FALSE, required_on_site_date DATE NOT NULL, source_document_ids JSON NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_supplier_baseline_line (baseline_id,source_item_ref), KEY idx_supplier_baseline_lines (organisation_id,project_id,baseline_id),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (baseline_id) REFERENCES supplier_procurement_baselines(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_integration_packages (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL, baseline_id BINARY(16) NOT NULL, title VARCHAR(220) NOT NULL,
  project_region VARCHAR(120) NOT NULL, status ENUM('procurement_package_created','supplier_matching_in_progress','rfq_issued','quote_comparison_ready','award_approved','purchase_order_issued','delivery_in_progress','site_checked','payment_review','paid_or_closed') NOT NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1, snapshot_payload JSON NOT NULL, snapshot_sha256 CHAR(64) NOT NULL, created_by BINARY(16) NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_supplier_package_baseline (baseline_id), KEY idx_supplier_packages (organisation_id,project_id,status), FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (baseline_id) REFERENCES supplier_procurement_baselines(id), FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_integration_package_lines (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL, package_id BINARY(16) NOT NULL, baseline_line_id BINARY(16) NOT NULL,
  description VARCHAR(500) NOT NULL, category VARCHAR(120) NOT NULL, required_standard VARCHAR(180) NULL, quantity DECIMAL(18,4) NOT NULL, unit VARCHAR(40) NOT NULL, allow_substitution BOOLEAN NOT NULL,
  required_on_site_date DATE NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), UNIQUE KEY uq_supplier_package_line (package_id,baseline_line_id), KEY idx_supplier_package_lines (organisation_id,project_id,package_id),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (package_id) REFERENCES supplier_integration_packages(id), FOREIGN KEY (baseline_line_id) REFERENCES supplier_procurement_baseline_lines(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_catalogue_matches (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL, package_id BINARY(16) NOT NULL, package_line_id BINARY(16) NOT NULL,
  catalogue_item_id BINARY(16) NOT NULL, score TINYINT UNSIGNED NOT NULL, risk_flags JSON NOT NULL, latest_order_date DATE NOT NULL, advisory_only BOOLEAN NOT NULL DEFAULT TRUE,
  price_cents_snapshot BIGINT UNSIGNED NOT NULL, stock_quantity_snapshot DECIMAL(18,4) NOT NULL, lead_time_days_snapshot INT UNSIGNED NOT NULL, snapshot_sha256 CHAR(64) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), UNIQUE KEY uq_supplier_match (package_line_id,catalogue_item_id), KEY idx_supplier_matches (organisation_id,project_id,package_id,score),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (package_id) REFERENCES supplier_integration_packages(id),
  FOREIGN KEY (package_line_id) REFERENCES supplier_integration_package_lines(id), FOREIGN KEY (catalogue_item_id) REFERENCES supplier_catalogue_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_rfqs (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL, package_id BINARY(16) NOT NULL, supplier_id BINARY(16) NOT NULL,
  token_hash CHAR(64) NOT NULL, status ENUM('issued','viewed','quote_submitted','closed','revoked') NOT NULL DEFAULT 'issued', expires_at DATETIME(6) NOT NULL, issued_by BINARY(16) NOT NULL,
  issued_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), UNIQUE KEY uq_supplier_rfq_token (token_hash), KEY idx_supplier_rfqs (organisation_id,project_id,package_id,supplier_id,status),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (package_id) REFERENCES supplier_integration_packages(id),
  FOREIGN KEY (supplier_id) REFERENCES supplier_profiles(id), FOREIGN KEY (issued_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_quote_revisions (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL, rfq_id BINARY(16) NOT NULL, revision INT UNSIGNED NOT NULL,
  quote_reference VARCHAR(120) NOT NULL, valid_until DATE NOT NULL, currency CHAR(3) NOT NULL DEFAULT 'ZAR', line_payload JSON NOT NULL, total_cents BIGINT UNSIGNED NOT NULL,
  exclusions JSON NOT NULL, qualifications JSON NOT NULL, source_file_id BINARY(16) NULL, snapshot_payload JSON NOT NULL, snapshot_sha256 CHAR(64) NOT NULL, submitted_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_supplier_quote_revision (rfq_id,revision), KEY idx_supplier_quotes (organisation_id,project_id,rfq_id,submitted_at), FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (rfq_id) REFERENCES supplier_rfqs(id), FOREIGN KEY (source_file_id) REFERENCES stored_files(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_substitution_decisions (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL, match_id BINARY(16) NOT NULL, decision ENUM('approved','rejected') NOT NULL,
  reason VARCHAR(1000) NOT NULL, snapshot_sha256 CHAR(64) NOT NULL, decided_by BINARY(16) NOT NULL, decided_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_supplier_substitution_decision (match_id), KEY idx_supplier_substitution (organisation_id,project_id,decision), FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (match_id) REFERENCES supplier_catalogue_matches(id), FOREIGN KEY (decided_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_award_decisions (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL, package_id BINARY(16) NOT NULL, selected_match_ids JSON NOT NULL,
  decision ENUM('approved','rejected') NOT NULL, reason VARCHAR(1000) NOT NULL, snapshot_payload JSON NOT NULL, snapshot_sha256 CHAR(64) NOT NULL, decided_by BINARY(16) NOT NULL, decided_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_supplier_award_package (package_id), KEY idx_supplier_awards (organisation_id,project_id,decision), FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (package_id) REFERENCES supplier_integration_packages(id), FOREIGN KEY (decided_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_purchase_orders (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL, package_id BINARY(16) NOT NULL, award_id BINARY(16) NOT NULL, order_number VARCHAR(100) NOT NULL,
  status ENUM('issued','supplier_accepted','picking_or_manufacturing','dispatched','delivered_to_site','site_checked','closed') NOT NULL DEFAULT 'issued', total_cents BIGINT UNSIGNED NOT NULL,
  snapshot_payload JSON NOT NULL, snapshot_sha256 CHAR(64) NOT NULL, issued_by BINARY(16) NOT NULL, issued_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_supplier_po_package (package_id), UNIQUE KEY uq_supplier_po_number (organisation_id,order_number), KEY idx_supplier_pos (organisation_id,project_id,status),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (package_id) REFERENCES supplier_integration_packages(id),
  FOREIGN KEY (award_id) REFERENCES supplier_award_decisions(id), FOREIGN KEY (issued_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_deliveries (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL, purchase_order_id BINARY(16) NOT NULL, delivery_reference VARCHAR(120) NOT NULL,
  status ENUM('picking_or_manufacturing','dispatched','delivered_to_site','rejected_or_partial') NOT NULL, delivered_at DATETIME(6) NULL, evidence_file_ids JSON NOT NULL,
  idempotency_key VARCHAR(120) NOT NULL, version INT UNSIGNED NOT NULL DEFAULT 1, created_by BINARY(16) NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_supplier_delivery_idempotency (organisation_id,idempotency_key), KEY idx_supplier_deliveries (organisation_id,project_id,purchase_order_id,status),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (purchase_order_id) REFERENCES supplier_purchase_orders(id), FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_site_acceptances (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL, delivery_id BINARY(16) NOT NULL, decision ENUM('accepted','rejected','partial') NOT NULL,
  reason VARCHAR(1000) NOT NULL, evidence_file_ids JSON NOT NULL, snapshot_sha256 CHAR(64) NOT NULL, decided_by BINARY(16) NOT NULL, decided_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_supplier_site_acceptance (delivery_id), KEY idx_supplier_site_acceptance (organisation_id,project_id,decision), FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (delivery_id) REFERENCES supplier_deliveries(id), FOREIGN KEY (decided_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_invoices (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL, purchase_order_id BINARY(16) NOT NULL, invoice_number VARCHAR(120) NOT NULL,
  amount_cents BIGINT UNSIGNED NOT NULL, source_file_id BINARY(16) NOT NULL, status ENUM('received','disputed','payment_review','closed') NOT NULL DEFAULT 'received', created_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), UNIQUE KEY uq_supplier_invoice_number (organisation_id,purchase_order_id,invoice_number), KEY idx_supplier_invoices (organisation_id,project_id,status),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (purchase_order_id) REFERENCES supplier_purchase_orders(id),
  FOREIGN KEY (source_file_id) REFERENCES stored_files(id), FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_payment_recommendations (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL, invoice_id BINARY(16) NOT NULL, decision ENUM('release_requested','blocked') NOT NULL,
  reason VARCHAR(1000) NOT NULL, provider_status ENUM('integration_required','configured') NOT NULL DEFAULT 'integration_required', provider_reference VARCHAR(500) NULL,
  moves_money BOOLEAN NOT NULL DEFAULT FALSE, idempotency_key VARCHAR(120) NOT NULL, snapshot_payload JSON NOT NULL, snapshot_sha256 CHAR(64) NOT NULL, decided_by BINARY(16) NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_supplier_payment_idempotency (organisation_id,idempotency_key), KEY idx_supplier_payments (organisation_id,project_id,invoice_id,decision), FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (invoice_id) REFERENCES supplier_invoices(id), FOREIGN KEY (decided_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
