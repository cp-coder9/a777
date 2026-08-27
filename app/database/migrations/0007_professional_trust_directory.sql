-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS professional_profiles (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  user_id BINARY(16) NOT NULL,
  display_name VARCHAR(180) NOT NULL,
  profession VARCHAR(80) NOT NULL,
  bio TEXT NOT NULL,
  service_areas JSON NOT NULL,
  directory_visible TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_professional_profile_member (organisation_id, user_id),
  KEY idx_professional_directory (profession, directory_visible),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS verification_records (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  user_id BINARY(16) NOT NULL,
  kind ENUM('professional_registration','company_document','insurance_compliance','contractor_supplier_compliance') NOT NULL,
  grade ENUM('self_declared','document_uploaded','manually_reviewed','externally_verified','rejected') NOT NULL,
  registration_body VARCHAR(120) NULL,
  registration_number VARCHAR(120) NULL,
  evidence_file_id BINARY(16) NULL,
  provider VARCHAR(160) NULL,
  provider_reference VARCHAR(180) NULL,
  popia_consent_at DATETIME(6) NOT NULL,
  processing_purpose VARCHAR(500) NOT NULL,
  retention_until DATE NOT NULL,
  expires_on DATE NULL,
  reviewed_by BINARY(16) NULL,
  reviewed_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  KEY idx_verification_subject (organisation_id, user_id, kind, created_at),
  KEY idx_verification_expiry (expires_on, grade),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (evidence_file_id) REFERENCES stored_files(id),
  FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS verification_reviews (
  id BINARY(16) PRIMARY KEY,
  verification_id BINARY(16) NOT NULL,
  organisation_id BINARY(16) NOT NULL,
  reviewer_user_id BINARY(16) NOT NULL,
  decision ENUM('manually_reviewed','externally_verified','rejected') NOT NULL,
  review_note TEXT NOT NULL,
  provider VARCHAR(160) NULL,
  provider_reference VARCHAR(180) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_verification_reviews (organisation_id, verification_id, created_at),
  FOREIGN KEY (verification_id) REFERENCES verification_records(id),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (reviewer_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
