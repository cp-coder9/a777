-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS wingman_conversations (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  user_id BINARY(16) NOT NULL,
  project_id BINARY(16) NULL,
  title VARCHAR(180) NOT NULL,
  status ENUM('active','archived','expired') NOT NULL DEFAULT 'active',
  provider VARCHAR(40) NOT NULL,
  model VARCHAR(180) NOT NULL,
  route_context VARCHAR(500) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  retention_expires_at DATETIME(6) NOT NULL DEFAULT (CURRENT_TIMESTAMP(6) + INTERVAL 90 DAY),
  KEY idx_wingman_conversation_scope (organisation_id,user_id,project_id,status,updated_at),
  KEY idx_wingman_conversation_retention (status,retention_expires_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wingman_messages (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  conversation_id BINARY(16) NOT NULL,
  author_user_id BINARY(16) NULL,
  role ENUM('user','assistant','tool') NOT NULL,
  status ENUM('complete','provider_unavailable','rate_limited','context_denied','failed') NOT NULL DEFAULT 'complete',
  content LONGTEXT NOT NULL,
  context_summary JSON NULL,
  provider VARCHAR(40) NULL,
  model VARCHAR(180) NULL,
  provider_request_id VARCHAR(180) NULL,
  input_tokens INT UNSIGNED NULL,
  output_tokens INT UNSIGNED NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_wingman_message_scope (organisation_id,conversation_id,created_at),
  KEY idx_wingman_message_author (author_user_id,created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (conversation_id) REFERENCES wingman_conversations(id) ON DELETE CASCADE,
  FOREIGN KEY (author_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wingman_citations (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  message_id BINARY(16) NOT NULL,
  source_type ENUM('knowledge','project','record','route') NOT NULL,
  source_id VARCHAR(180) NULL,
  source_reference VARCHAR(500) NOT NULL,
  title VARCHAR(220) NOT NULL,
  excerpt VARCHAR(1000) NULL,
  route_target VARCHAR(500) NULL,
  ordinal SMALLINT UNSIGNED NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_wingman_citation_ordinal (message_id,ordinal),
  KEY idx_wingman_citation_scope (organisation_id,message_id,source_type),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (message_id) REFERENCES wingman_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wingman_suggestions (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  conversation_id BINARY(16) NOT NULL,
  message_id BINARY(16) NOT NULL,
  suggestion_type ENUM('navigate','review','learn') NOT NULL,
  label VARCHAR(180) NOT NULL,
  reason VARCHAR(1000) NOT NULL,
  route_target VARCHAR(500) NULL,
  status ENUM('proposed','accepted','dismissed','expired') NOT NULL DEFAULT 'proposed',
  decided_by BINARY(16) NULL,
  decided_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_wingman_suggestion_scope (organisation_id,conversation_id,status,created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (conversation_id) REFERENCES wingman_conversations(id) ON DELETE CASCADE,
  FOREIGN KEY (message_id) REFERENCES wingman_messages(id) ON DELETE CASCADE,
  FOREIGN KEY (decided_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wingman_suggestion_feedback (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  suggestion_id BINARY(16) NOT NULL,
  user_id BINARY(16) NOT NULL,
  rating ENUM('helpful','not_helpful') NOT NULL,
  note VARCHAR(1000) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_wingman_feedback_user (suggestion_id,user_id),
  KEY idx_wingman_feedback_scope (organisation_id,user_id,created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (suggestion_id) REFERENCES wingman_suggestions(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wingman_knowledge_chunks (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  project_id BINARY(16) NULL,
  permission_key VARCHAR(120) NOT NULL,
  source_type VARCHAR(100) NOT NULL,
  source_id VARCHAR(180) NULL,
  source_reference VARCHAR(500) NOT NULL,
  route_target VARCHAR(500) NULL,
  title VARCHAR(220) NOT NULL,
  content MEDIUMTEXT NOT NULL,
  content_sha256 CHAR(64) NOT NULL,
  status ENUM('active','superseded','withdrawn') NOT NULL DEFAULT 'active',
  effective_at DATETIME(6) NULL,
  expires_at DATETIME(6) NULL,
  created_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_wingman_knowledge_sha (organisation_id,content_sha256),
  KEY idx_wingman_knowledge_scope (organisation_id,project_id,permission_key,status,updated_at),
  FULLTEXT KEY ft_wingman_knowledge (title,content),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wingman_audit_records (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  user_id BINARY(16) NOT NULL,
  conversation_id BINARY(16) NULL,
  message_id BINARY(16) NULL,
  action VARCHAR(120) NOT NULL,
  outcome ENUM('allowed','denied','failed') NOT NULL,
  route_context VARCHAR(500) NULL,
  metadata_json JSON NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_wingman_audit_scope (organisation_id,user_id,action,created_at),
  KEY idx_wingman_audit_conversation (conversation_id,created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (conversation_id) REFERENCES wingman_conversations(id) ON DELETE SET NULL,
  FOREIGN KEY (message_id) REFERENCES wingman_messages(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
