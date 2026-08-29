-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS analytics_kpi_formulas (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, formula_key VARCHAR(100) NOT NULL,
  title VARCHAR(220) NOT NULL, unit VARCHAR(40) NOT NULL, expression VARCHAR(1000) NOT NULL, version INT UNSIGNED NOT NULL,
  status ENUM('active','superseded','withdrawn') NOT NULL DEFAULT 'active', created_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_analytics_formula_version (organisation_id,formula_key,version), KEY idx_analytics_formula_active (organisation_id,formula_key,status),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS analytics_kpi_snapshots (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL,
  formula_id BINARY(16) NOT NULL, formula_key VARCHAR(100) NOT NULL, formula_version INT UNSIGNED NOT NULL,
  value_decimal DECIMAL(20,4) NOT NULL, unit VARCHAR(40) NOT NULL, as_of_date DATE NOT NULL,
  calculation JSON NOT NULL, source_record_ids JSON NOT NULL, snapshot_sha256 CHAR(64) NOT NULL, created_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_analytics_snapshot_project (organisation_id,project_id,formula_key,created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id),
  FOREIGN KEY (formula_id) REFERENCES analytics_kpi_formulas(id), FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS analytics_dashboard_layouts (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL,
  dashboard_role ENUM('admin','professional','client','contractor') NOT NULL, widget_keys JSON NOT NULL,
  refresh_seconds INT UNSIGNED NOT NULL DEFAULT 60, version INT UNSIGNED NOT NULL, status ENUM('active','superseded') NOT NULL DEFAULT 'active',
  created_by BINARY(16) NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_analytics_layout_version (organisation_id,project_id,dashboard_role,version), KEY idx_analytics_layout_active (organisation_id,project_id,dashboard_role,status),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS analytics_alert_rules (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL,
  name VARCHAR(220) NOT NULL, record_type VARCHAR(100) NOT NULL, payload_path VARCHAR(200) NOT NULL,
  comparison_operator ENUM('equals','not_equals','greater_than','less_than','contains') NOT NULL,
  comparison_value JSON NOT NULL, severity ENUM('low','medium','high','critical') NOT NULL, recipient_role VARCHAR(60) NOT NULL,
  throttle_minutes INT UNSIGNED NOT NULL DEFAULT 60, status ENUM('active','inactive') NOT NULL DEFAULT 'active', created_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  KEY idx_analytics_rules (organisation_id,project_id,status), FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS analytics_alert_events (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL,
  rule_id BINARY(16) NOT NULL, source_record_id BINARY(16) NOT NULL, inbox_item_id BINARY(16) NOT NULL,
  status ENUM('open','acknowledged') NOT NULL DEFAULT 'open', acknowledgement_required BOOLEAN NOT NULL DEFAULT TRUE,
  acknowledged_by BINARY(16) NULL, acknowledgement_note VARCHAR(1000) NULL, acknowledged_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_analytics_alert_throttle (organisation_id,project_id,rule_id,source_record_id,created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id),
  FOREIGN KEY (rule_id) REFERENCES analytics_alert_rules(id), FOREIGN KEY (source_record_id) REFERENCES project_records(id),
  FOREIGN KEY (inbox_item_id) REFERENCES inbox_items(id), FOREIGN KEY (acknowledged_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS analytics_observability_metrics (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, module_key VARCHAR(100) NOT NULL,
  metric_type ENUM('latency_ms','error_count','memory_boundary_violation') NOT NULL, metric_value DECIMAL(20,4) NOT NULL,
  technical_context JSON NOT NULL, retention_until DATE NOT NULL, recorded_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), KEY idx_analytics_observability (organisation_id,metric_type,retention_until,created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
