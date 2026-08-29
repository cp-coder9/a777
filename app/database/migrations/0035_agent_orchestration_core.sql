-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS agent_identities (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, user_id BINARY(16) NOT NULL,
  display_name VARCHAR(160) NOT NULL, status ENUM('active','suspended') NOT NULL DEFAULT 'active', created_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_agent_identity_user (organisation_id,user_id), FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (user_id) REFERENCES users(id), FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agent_user_contexts (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL, user_id BINARY(16) NOT NULL,
  agent_identity_id BINARY(16) NOT NULL, purpose VARCHAR(500) NOT NULL, status ENUM('active','suspended') NOT NULL DEFAULT 'active',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_agent_user_context (organisation_id,project_id,user_id), KEY idx_agent_user_context (organisation_id,user_id,status),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id),
  FOREIGN KEY (user_id) REFERENCES users(id), FOREIGN KEY (agent_identity_id) REFERENCES agent_identities(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agent_project_contexts (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL,
  purpose VARCHAR(500) NOT NULL, status ENUM('active','suspended') NOT NULL DEFAULT 'active', created_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_agent_project_context (organisation_id,project_id), FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agent_governance_policies (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, policy_key VARCHAR(100) NOT NULL, title VARCHAR(220) NOT NULL,
  version INT UNSIGNED NOT NULL, rules JSON NOT NULL, status ENUM('active','superseded','withdrawn') NOT NULL DEFAULT 'active',
  created_by BINARY(16) NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_agent_policy_version (organisation_id,policy_key,version), KEY idx_agent_policy_active (organisation_id,policy_key,status),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agent_event_routes (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL,
  event_type VARCHAR(100) NOT NULL, source_module VARCHAR(80) NOT NULL, target_role VARCHAR(60) NOT NULL,
  policy_id BINARY(16) NOT NULL, create_draft BOOLEAN NOT NULL DEFAULT FALSE, approval_required BOOLEAN NOT NULL DEFAULT TRUE,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active', created_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_agent_routes (organisation_id,project_id,event_type,status), FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (policy_id) REFERENCES agent_governance_policies(id), FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agent_recommendations (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL, user_id BINARY(16) NOT NULL,
  route_id BINARY(16) NOT NULL, policy_id BINARY(16) NOT NULL, source_type VARCHAR(80) NOT NULL, source_object_id BINARY(16) NOT NULL,
  title VARCHAR(220) NOT NULL, rationale VARCHAR(2000) NOT NULL, severity ENUM('low','medium','high','critical') NOT NULL,
  requires_approval BOOLEAN NOT NULL DEFAULT TRUE, status ENUM('open','acknowledged','dismissed') NOT NULL DEFAULT 'open',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), KEY idx_agent_recommendations (organisation_id,project_id,status,severity),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (route_id) REFERENCES agent_event_routes(id), FOREIGN KEY (policy_id) REFERENCES agent_governance_policies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agent_message_drafts (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL, authored_for_user_id BINARY(16) NOT NULL,
  route_id BINARY(16) NULL, policy_id BINARY(16) NOT NULL, source_type VARCHAR(80) NOT NULL, source_object_id BINARY(16) NOT NULL,
  recipient_role VARCHAR(60) NOT NULL, subject VARCHAR(220) NOT NULL, body TEXT NOT NULL, formal_instruction BOOLEAN NOT NULL DEFAULT TRUE,
  status ENUM('draft','pending_approval','approved_for_human_send','rejected') NOT NULL DEFAULT 'draft', external_dispatch_performed BOOLEAN NOT NULL DEFAULT FALSE,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  KEY idx_agent_drafts (organisation_id,project_id,status,created_at), FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (authored_for_user_id) REFERENCES users(id),
  FOREIGN KEY (route_id) REFERENCES agent_event_routes(id), FOREIGN KEY (policy_id) REFERENCES agent_governance_policies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agent_approval_gates (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL,
  object_type VARCHAR(80) NOT NULL, object_id BINARY(16) NOT NULL, required_role VARCHAR(60) NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending', requested_by BINARY(16) NOT NULL,
  decided_by BINARY(16) NULL, decision_reason VARCHAR(1000) NULL, decided_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), KEY idx_agent_approval_gates (organisation_id,project_id,status,created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id),
  FOREIGN KEY (requested_by) REFERENCES users(id), FOREIGN KEY (decided_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agent_memory_boundaries (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL, user_id BINARY(16) NOT NULL,
  scope_key VARCHAR(100) NOT NULL DEFAULT 'project_user_context', allowed_scopes JSON NOT NULL, excluded_scopes JSON NOT NULL,
  retention_until DATE NOT NULL, status ENUM('active','expired','withdrawn') NOT NULL DEFAULT 'active',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_agent_memory_boundary (organisation_id,project_id,user_id,scope_key), KEY idx_agent_memory_user (organisation_id,user_id,status,retention_until),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agent_monitoring_records (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, project_id BINARY(16) NOT NULL, user_id BINARY(16) NOT NULL,
  agent_key VARCHAR(100) NOT NULL, event_type VARCHAR(100) NOT NULL, outcome VARCHAR(100) NOT NULL, details JSON NOT NULL,
  permission_bypass BOOLEAN NOT NULL DEFAULT FALSE, external_action_performed BOOLEAN NOT NULL DEFAULT FALSE,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), KEY idx_agent_monitoring (organisation_id,project_id,created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (project_id) REFERENCES projects(id), FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
