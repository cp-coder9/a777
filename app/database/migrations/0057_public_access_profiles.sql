-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS registration_profiles (
  id BINARY(16) PRIMARY KEY,
  user_id BINARY(16) NOT NULL,
  organisation_id BINARY(16) NOT NULL,
  full_name VARCHAR(180) NOT NULL,
  role_key VARCHAR(80) NOT NULL,
  profile JSON NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_registration_profile_identity (user_id, organisation_id),
  KEY idx_registration_profile_role (organisation_id, role_key),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
