-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS v1_feedback_records (
  id BINARY(16) NOT NULL PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  user_id BINARY(16) NOT NULL,
  project_id BINARY(16) NULL,
  category ENUM('Bug','Feature request','Usability','Praise') NOT NULL,
  message TEXT NOT NULL,
  context VARCHAR(512) NOT NULL DEFAULT '',
  status ENUM('Received') NOT NULL DEFAULT 'Received',
  client_request_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_feedback_retry (organisation_id,user_id,client_request_id),
  KEY ix_feedback_owner (organisation_id,user_id,created_at,id),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (project_id) REFERENCES projects(id),
  CONSTRAINT ck_feedback_message CHECK (CHAR_LENGTH(message) BETWEEN 10 AND 2000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
