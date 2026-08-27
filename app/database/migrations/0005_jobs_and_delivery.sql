-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS scheduled_job_deduplication_keys (
  deduplication_key CHAR(64) PRIMARY KEY,
  job_id BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_scheduled_job_dedupe_job (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
