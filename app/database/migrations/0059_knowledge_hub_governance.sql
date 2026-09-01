-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS knowledge_source_governance (
  knowledge_chunk_id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  source_category ENUM('nbr_sans','municipal_guideline','land_use_scheme','professional_body_rule','template_clause','practice_note','case_study') NOT NULL,
  source_key VARCHAR(160) NOT NULL,
  source_version INT UNSIGNED NOT NULL,
  review_status ENUM('review_required','approved','rejected') NOT NULL DEFAULT 'review_required',
  review_reason VARCHAR(1000) NULL,
  reviewed_by BINARY(16) NULL,
  reviewed_at DATETIME(6) NULL,
  record_version INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_knowledge_source_version (organisation_id,source_key,source_version),
  KEY idx_knowledge_review (organisation_id,review_status,source_category,updated_at),
  FOREIGN KEY (knowledge_chunk_id) REFERENCES wingman_knowledge_chunks(id) ON DELETE CASCADE,
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
