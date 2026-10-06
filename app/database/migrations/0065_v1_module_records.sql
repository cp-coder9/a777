-- architex:migration-mode=ddl
-- Module records: the shared, typed record store behind the v1.html modules whose
-- original had no domain form. One table instead of ~12 near-identical ones, with the
-- per-module field set and status ladder enforced in PHP (ModuleRecordSchema), so a new
-- module ships without a schema migration.
--
-- Design notes:
--   * status is VARCHAR, not ENUM: each module owns its own ladder (open->corrective_action
--     ->verified_closed for an NCR; draft->issued->acknowledged for a site instruction).
--     Validating in PHP keeps the DB from becoming the bottleneck for product scope.
--   * revision carries the same optimistic-concurrency contract the project editor uses,
--     so a stale write is rejected rather than silently overwriting a decision.
--   * client_request_id makes a retried POST idempotent (same rule as v1_feedback_records).
--   * project_id is nullable: a handful of these registers are organisation-scoped.
CREATE TABLE IF NOT EXISTS module_records (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  project_id BINARY(16) NULL,
  module_key VARCHAR(60) NOT NULL,
  record_type VARCHAR(80) NOT NULL,
  reference VARCHAR(120) NULL,
  title VARCHAR(220) NOT NULL,
  detail TEXT NOT NULL,
  status VARCHAR(40) NOT NULL,
  severity VARCHAR(40) NULL,
  owner_role VARCHAR(80) NULL,
  due_on DATE NULL,
  payload JSON NOT NULL,
  evidence_file_ids JSON NOT NULL,
  linked_record_ids JSON NOT NULL,
  human_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  confirmation_note VARCHAR(500) NULL,
  revision INT UNSIGNED NOT NULL DEFAULT 1,
  client_request_id VARCHAR(64) NULL,
  created_by BINARY(16) NOT NULL,
  updated_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_module_record_request (organisation_id, client_request_id),
  KEY idx_module_records_scope (organisation_id, project_id, module_key, status, updated_at),
  KEY idx_module_records_due (organisation_id, module_key, due_on),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every status change is written here, so a decision ("NCR closed") can be audited
-- against who moved it and why, rather than only showing the current status.
CREATE TABLE IF NOT EXISTS module_record_events (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  record_id BINARY(16) NOT NULL,
  module_key VARCHAR(60) NOT NULL,
  from_status VARCHAR(40) NULL,
  to_status VARCHAR(40) NOT NULL,
  reason VARCHAR(500) NOT NULL,
  actor_user_id BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_module_record_events_record (organisation_id, record_id, created_at),
  FOREIGN KEY (record_id) REFERENCES module_records(id) ON DELETE CASCADE,
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
