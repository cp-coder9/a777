-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS guided_brief_files (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  brief_id BINARY(16) NOT NULL,
  uploaded_by BINARY(16) NOT NULL,
  category ENUM('existing_plan','title_deed','site_photo','brief_document','other') NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  storage_name CHAR(48) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  byte_size BIGINT UNSIGNED NOT NULL,
  sha256 CHAR(64) NOT NULL,
  processing_purpose VARCHAR(500) NOT NULL,
  consent_at DATETIME(6) NOT NULL,
  retention_until DATE NOT NULL,
  status ENUM('active','deleted') NOT NULL DEFAULT 'active',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  deleted_at DATETIME(6) NULL,
  UNIQUE KEY uq_guided_brief_file_storage_name (storage_name),
  KEY idx_guided_brief_files (organisation_id,brief_id,status,created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (brief_id) REFERENCES guided_briefs(id),
  FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
