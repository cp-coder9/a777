-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS wingman_tutorial_progress (
  organisation_id BINARY(16) NOT NULL,
  user_id BINARY(16) NOT NULL,
  role_profile VARCHAR(80) NOT NULL,
  tutorial_version INT UNSIGNED NOT NULL,
  step_index SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('in_progress','dismissed','completed') NOT NULL,
  revision INT UNSIGNED NOT NULL DEFAULT 1,
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (organisation_id,user_id,role_profile,tutorial_version),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
