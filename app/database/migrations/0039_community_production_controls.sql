-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS community_channel_governance (
  channel_id VARCHAR(80) PRIMARY KEY,
  eligible_roles JSON NULL,
  visibility ENUM('public','role_restricted','invite_only','admin_only') NOT NULL DEFAULT 'public',
  default_moderation_policy ENUM('standard','preapprove_all','admin_only') NOT NULL DEFAULT 'standard',
  FOREIGN KEY (channel_id) REFERENCES community_channels(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_channel_preferences (
  user_id BINARY(16) NOT NULL,
  channel_id VARCHAR(80) NOT NULL,
  watching TINYINT(1) NOT NULL DEFAULT 0,
  muted TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (user_id,channel_id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (channel_id) REFERENCES community_channels(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_saved_threads (
  user_id BINARY(16) NOT NULL,
  thread_id BINARY(16) NOT NULL,
  saved_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (user_id,thread_id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (thread_id) REFERENCES community_threads(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_thread_attachments (
  thread_id BINARY(16) NOT NULL,
  file_id BINARY(16) NOT NULL,
  PRIMARY KEY (thread_id,file_id),
  FOREIGN KEY (thread_id) REFERENCES community_threads(id),
  FOREIGN KEY (file_id) REFERENCES stored_files(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_reply_attachments (
  reply_id BINARY(16) NOT NULL,
  file_id BINARY(16) NOT NULL,
  PRIMARY KEY (reply_id,file_id),
  FOREIGN KEY (reply_id) REFERENCES community_replies(id),
  FOREIGN KEY (file_id) REFERENCES stored_files(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_notification_outbox (
  id BINARY(16) PRIMARY KEY,
  thread_id BINARY(16) NOT NULL,
  user_id BINARY(16) NOT NULL,
  notification_type VARCHAR(80) NOT NULL,
  priority ENUM('digest','medium','high') NOT NULL,
  status ENUM('pending','delivered','read','cancelled') NOT NULL DEFAULT 'pending',
  available_at DATETIME(6) NOT NULL,
  delivered_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_community_outbox_delivery (status,available_at,user_id),
  FOREIGN KEY (thread_id) REFERENCES community_threads(id),
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
