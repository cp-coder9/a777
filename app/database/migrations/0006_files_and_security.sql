-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS stored_files (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  project_id BINARY(16) NOT NULL,
  uploaded_by BINARY(16) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  storage_name CHAR(48) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  byte_size BIGINT UNSIGNED NOT NULL,
  sha256 CHAR(64) NOT NULL,
  status ENUM('active','deleted') NOT NULL DEFAULT 'active',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  deleted_at DATETIME(6) NULL,
  UNIQUE KEY uq_stored_file_storage_name (storage_name),
  KEY idx_stored_files_project (organisation_id, project_id, status, created_at),
  KEY idx_stored_files_uploader (uploaded_by, created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id),
  FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
