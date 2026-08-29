-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS community_channels (
  id VARCHAR(80) PRIMARY KEY,
  label VARCHAR(120) NOT NULL,
  description VARCHAR(500) NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL,
  status ENUM('active','archived') NOT NULL DEFAULT 'active',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_preferences (
  user_id BINARY(16) PRIMARY KEY,
  home_organisation_id BINARY(16) NOT NULL,
  professional_role VARCHAR(80) NOT NULL,
  region VARCHAR(120) NULL,
  verification_status VARCHAR(40) NOT NULL DEFAULT 'self_declared',
  opted_in TINYINT(1) NOT NULL DEFAULT 0,
  blocked_user_ids JSON NOT NULL,
  muted_roles JSON NOT NULL,
  digest_mode ENUM('immediate','daily','weekly','none') NOT NULL DEFAULT 'daily',
  popia_consent_at DATETIME(6) NULL,
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  KEY idx_community_preference_audience (opted_in,professional_role,region,verification_status),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (home_organisation_id) REFERENCES organisations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_threads (
  id BINARY(16) PRIMARY KEY,
  author_organisation_id BINARY(16) NOT NULL,
  author_user_id BINARY(16) NOT NULL,
  channel_id VARCHAR(80) NOT NULL,
  category VARCHAR(80) NOT NULL,
  title VARCHAR(220) NOT NULL,
  body TEXT NOT NULL,
  tags JSON NOT NULL,
  audience JSON NOT NULL,
  moderation_status ENUM('pending_approval','approved','rejected','hidden') NOT NULL,
  visibility_status ENUM('pending','visible','hidden') NOT NULL,
  context_type VARCHAR(40) NOT NULL DEFAULT 'community_informal',
  app_shell_mode ENUM('embedded_platform','standalone_mobile') NOT NULL,
  recipient_count INT UNSIGNED NOT NULL DEFAULT 0,
  reply_count INT UNSIGNED NOT NULL DEFAULT 0,
  helpful_count INT UNSIGNED NOT NULL DEFAULT 0,
  report_count INT UNSIGNED NOT NULL DEFAULT 0,
  reviewed_by BINARY(16) NULL,
  review_note VARCHAR(1000) NULL,
  reviewed_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  KEY idx_community_feed (moderation_status,created_at),
  KEY idx_community_author (author_user_id,created_at),
  FOREIGN KEY (author_organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (author_user_id) REFERENCES users(id),
  FOREIGN KEY (channel_id) REFERENCES community_channels(id),
  FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_thread_recipients (
  thread_id BINARY(16) NOT NULL,
  user_id BINARY(16) NOT NULL,
  organisation_id BINARY(16) NOT NULL,
  professional_role VARCHAR(80) NOT NULL,
  resolved_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (thread_id,user_id),
  KEY idx_community_recipient_feed (user_id,resolved_at),
  FOREIGN KEY (thread_id) REFERENCES community_threads(id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_replies (
  id BINARY(16) PRIMARY KEY,
  thread_id BINARY(16) NOT NULL,
  author_organisation_id BINARY(16) NOT NULL,
  author_user_id BINARY(16) NOT NULL,
  body TEXT NOT NULL,
  moderation_status ENUM('visible','hidden') NOT NULL DEFAULT 'visible',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_community_replies (thread_id,created_at),
  FOREIGN KEY (thread_id) REFERENCES community_threads(id),
  FOREIGN KEY (author_organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (author_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_reports (
  id BINARY(16) PRIMARY KEY,
  thread_id BINARY(16) NOT NULL,
  reporter_organisation_id BINARY(16) NOT NULL,
  reporter_user_id BINARY(16) NOT NULL,
  reason VARCHAR(80) NOT NULL,
  detail VARCHAR(1000) NOT NULL,
  status ENUM('open','reviewed','dismissed','actioned') NOT NULL DEFAULT 'open',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_community_reporter_thread (thread_id,reporter_user_id),
  FOREIGN KEY (thread_id) REFERENCES community_threads(id),
  FOREIGN KEY (reporter_organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (reporter_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_notifications (
  id BINARY(16) PRIMARY KEY,
  thread_id BINARY(16) NOT NULL,
  user_id BINARY(16) NULL,
  notification_type ENUM('community_digest','watched_channel_post','moderation_required','reply') NOT NULL,
  priority ENUM('digest','medium','high') NOT NULL,
  status ENUM('pending','delivered','read','cancelled') NOT NULL DEFAULT 'pending',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_community_notification_delivery (status,user_id,created_at),
  FOREIGN KEY (thread_id) REFERENCES community_threads(id),
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_moderation_events (
  id BINARY(16) PRIMARY KEY,
  thread_id BINARY(16) NOT NULL,
  actor_organisation_id BINARY(16) NOT NULL,
  actor_user_id BINARY(16) NOT NULL,
  action ENUM('submitted','approved','rejected','hidden','restored') NOT NULL,
  note VARCHAR(1000) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_community_moderation_thread (thread_id,created_at),
  FOREIGN KEY (thread_id) REFERENCES community_threads(id),
  FOREIGN KEY (actor_organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_conversions (
  id BINARY(16) PRIMARY KEY,
  thread_id BINARY(16) NOT NULL,
  organisation_id BINARY(16) NOT NULL,
  project_id BINARY(16) NULL,
  approved_by BINARY(16) NOT NULL,
  conversion_type VARCHAR(80) NOT NULL,
  title VARCHAR(220) NOT NULL,
  target_context JSON NOT NULL,
  status ENUM('draft','finalised','cancelled') NOT NULL DEFAULT 'draft',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_community_conversion (thread_id,organisation_id,conversion_type),
  FOREIGN KEY (thread_id) REFERENCES community_threads(id),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id),
  FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
