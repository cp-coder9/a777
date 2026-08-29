-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS demo_sandboxes (
  id BINARY(16) PRIMARY KEY,
  owner_user_id BINARY(16) NOT NULL,
  organisation_id BINARY(16) NOT NULL,
  generation INT UNSIGNED NOT NULL,
  selected_role VARCHAR(80) NOT NULL,
  seed_version VARCHAR(40) NOT NULL,
  seed_hash CHAR(64) NOT NULL,
  status ENUM('active','superseded','failed') NOT NULL DEFAULT 'active',
  reset_reason VARCHAR(500) NULL,
  failure_reason VARCHAR(500) NULL,
  superseded_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  active_owner_key BINARY(16) GENERATED ALWAYS AS (CASE WHEN status = 'active' THEN owner_user_id ELSE NULL END) STORED,
  UNIQUE KEY uq_demo_sandbox_owner_generation (owner_user_id, generation),
  UNIQUE KEY uq_demo_sandbox_organisation (organisation_id),
  UNIQUE KEY uq_demo_sandbox_active_owner (active_owner_key),
  KEY idx_demo_sandbox_owner_status (owner_user_id, status),
  FOREIGN KEY (owner_user_id) REFERENCES users(id),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS demo_personas (
  id BINARY(16) PRIMARY KEY,
  sandbox_id BINARY(16) NOT NULL,
  organisation_id BINARY(16) NOT NULL,
  role_key VARCHAR(80) NOT NULL,
  label VARCHAR(160) NOT NULL,
  group_key VARCHAR(80) NOT NULL,
  canonical_role VARCHAR(80) NOT NULL,
  synthetic_email VARCHAR(254) NOT NULL,
  canonical_payload JSON NOT NULL,
  payload_sha256 CHAR(64) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_demo_persona_role (sandbox_id, role_key),
  KEY idx_demo_persona_org_role (organisation_id, role_key),
  FOREIGN KEY (sandbox_id) REFERENCES demo_sandboxes(id),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS demo_project_role_assignments (
  sandbox_id BINARY(16) NOT NULL,
  organisation_id BINARY(16) NOT NULL,
  project_id BINARY(16) NOT NULL,
  role_key VARCHAR(80) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (sandbox_id, project_id, role_key),
  KEY idx_demo_assignment_role (sandbox_id, role_key, project_id),
  KEY idx_demo_assignment_org_project (organisation_id, project_id),
  FOREIGN KEY (sandbox_id) REFERENCES demo_sandboxes(id),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS demo_seed_items (
  id BINARY(16) PRIMARY KEY,
  sandbox_id BINARY(16) NOT NULL,
  organisation_id BINARY(16) NOT NULL,
  project_id BINARY(16) NULL,
  item_type ENUM('submission','message','compliance','learning_article','learning_assessment','learning_module','learning_certificate') NOT NULL,
  source_key VARCHAR(160) NOT NULL,
  reliance_label ENUM('illustrative_demo_not_for_reliance','not_accredited_demo_record') NOT NULL,
  canonical_payload JSON NOT NULL,
  payload_sha256 CHAR(64) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_demo_seed_source (sandbox_id, source_key),
  KEY idx_demo_seed_type (sandbox_id, item_type),
  KEY idx_demo_seed_org_project (organisation_id, project_id),
  FOREIGN KEY (sandbox_id) REFERENCES demo_sandboxes(id),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS demo_sandbox_events (
  id BINARY(16) PRIMARY KEY,
  sandbox_id BINARY(16) NOT NULL,
  organisation_id BINARY(16) NOT NULL,
  actor_user_id BINARY(16) NOT NULL,
  event_type ENUM('ensured','seeded','role_changed','reset_requested','superseded','reset_completed','seed_failed') NOT NULL,
  canonical_payload JSON NOT NULL,
  payload_sha256 CHAR(64) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_demo_event_sandbox_time (sandbox_id, created_at),
  KEY idx_demo_event_actor_time (actor_user_id, created_at),
  FOREIGN KEY (sandbox_id) REFERENCES demo_sandboxes(id),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
