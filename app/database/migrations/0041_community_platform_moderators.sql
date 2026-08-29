-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS community_platform_moderators (
  user_id BINARY(16) PRIMARY KEY,
  status ENUM('active','suspended','revoked') NOT NULL DEFAULT 'active',
  grant_reason VARCHAR(500) NOT NULL,
  granted_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  revoked_at DATETIME(6) NULL,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
