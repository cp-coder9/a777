-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS work_permits (
 id BINARY(16) PRIMARY KEY,
 organisation_id BINARY(16) NOT NULL,
 project_id BINARY(16) NOT NULL,
 reference_code VARCHAR(120) NOT NULL,
 status ENUM('draft','requested','reviewed','issued','closed','revoked') NOT NULL DEFAULT 'draft',
 payload JSON NOT NULL,
 version INT UNSIGNED NOT NULL DEFAULT 1,
 created_by BINARY(16) NOT NULL,
 reviewed_by BINARY(16) NULL,
 issued_by BINARY(16) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_work_permit_reference (organisation_id,project_id,reference_code),
 KEY idx_work_permit_scope (organisation_id,project_id,status),
 FOREIGN KEY (organisation_id) REFERENCES organisations(id),
 FOREIGN KEY (project_id) REFERENCES projects(id),
 FOREIGN KEY (created_by) REFERENCES users(id),
 FOREIGN KEY (reviewed_by) REFERENCES users(id),
 FOREIGN KEY (issued_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS work_permit_versions (
 permit_id BINARY(16) NOT NULL,
 organisation_id BINARY(16) NOT NULL,
 project_id BINARY(16) NOT NULL,
 version INT UNSIGNED NOT NULL,
 actor_id BINARY(16) NOT NULL,
 action VARCHAR(30) NOT NULL,
 reason VARCHAR(2000) NOT NULL,
 snapshot JSON NOT NULL,
 sha256 CHAR(64) NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY (permit_id,version),
 FOREIGN KEY (permit_id) REFERENCES work_permits(id),
 FOREIGN KEY (organisation_id) REFERENCES organisations(id),
 FOREIGN KEY (project_id) REFERENCES projects(id),
 FOREIGN KEY (actor_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;