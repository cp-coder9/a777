-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS communication_threads (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  project_id BINARY(16) NOT NULL,
  title VARCHAR(220) NOT NULL,
  phase VARCHAR(40) NOT NULL,
  channel VARCHAR(60) NOT NULL,
  visibility ENUM('project_team','client_visible','internal_team','admin_only') NOT NULL,
  created_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_communication_threads_project (organisation_id,project_id,created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_thread_participants (
  id BINARY(16) PRIMARY KEY,
  thread_id BINARY(16) NOT NULL,
  user_id BINARY(16) NOT NULL,
  added_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_communication_thread_participant (thread_id,user_id),
  FOREIGN KEY (thread_id) REFERENCES communication_threads(id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (added_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_messages (
  id BINARY(16) PRIMARY KEY,
  thread_id BINARY(16) NOT NULL,
  organisation_id BINARY(16) NOT NULL,
  project_id BINARY(16) NOT NULL,
  sender_user_id BINARY(16) NOT NULL,
  reply_to_message_id BINARY(16) NULL,
  attachment_file_id BINARY(16) NULL,
  message_kind ENUM('text','photo','document','voice_note','system') NOT NULL DEFAULT 'text',
  body TEXT NOT NULL,
  phase VARCHAR(40) NOT NULL,
  capture_type VARCHAR(80) NULL,
  visibility ENUM('project_team','client_visible','internal_team','admin_only') NOT NULL,
  structured_status ENUM('raw','converted','linked','archived') NOT NULL DEFAULT 'raw',
  status ENUM('draft','sent','archived') NOT NULL DEFAULT 'draft',
  context JSON NOT NULL,
  transcribed_text TEXT NULL,
  location JSON NULL,
  sent_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_project_messages_thread_time (thread_id,status,created_at),
  KEY idx_project_messages_filter (organisation_id,project_id,phase,capture_type,status),
  FOREIGN KEY (thread_id) REFERENCES communication_threads(id),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id),
  FOREIGN KEY (sender_user_id) REFERENCES users(id),
  FOREIGN KEY (reply_to_message_id) REFERENCES project_messages(id),
  FOREIGN KEY (attachment_file_id) REFERENCES stored_files(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_actions (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  project_id BINARY(16) NOT NULL,
  source_message_id BINARY(16) NOT NULL,
  conversion_type ENUM('action','rfi','site_instruction','approval') NOT NULL,
  title VARCHAR(220) NOT NULL,
  assigned_user_id BINARY(16) NULL,
  due_on DATE NULL,
  status ENUM('open','in_progress','done','cancelled') NOT NULL DEFAULT 'open',
  approved_by BINARY(16) NOT NULL,
  payload JSON NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_communication_conversion (source_message_id,conversion_type),
  KEY idx_communication_actions_project (organisation_id,project_id,status,due_on),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id),
  FOREIGN KEY (source_message_id) REFERENCES project_messages(id),
  FOREIGN KEY (assigned_user_id) REFERENCES users(id),
  FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_read_cursors (
  thread_id BINARY(16) NOT NULL,
  user_id BINARY(16) NOT NULL,
  last_read_message_id BINARY(16) NULL,
  read_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (thread_id,user_id),
  FOREIGN KEY (thread_id) REFERENCES communication_threads(id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (last_read_message_id) REFERENCES project_messages(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
