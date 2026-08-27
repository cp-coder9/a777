-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS professional_appointments (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  project_id BINARY(16) NOT NULL,
  proposal_id BINARY(16) NOT NULL,
  supersedes_appointment_id BINARY(16) NULL,
  revision INT UNSIGNED NOT NULL DEFAULT 1,
  status ENUM('pending_professional_confirmation','confirmed','revision_required') NOT NULL DEFAULT 'pending_professional_confirmation',
  proposal_snapshot JSON NOT NULL,
  proposal_hash CHAR(64) NOT NULL,
  project_facts JSON NOT NULL,
  missing_facts JSON NOT NULL,
  professional_confirmed_by BINARY(16) NULL,
  professional_confirmation_reason VARCHAR(500) NULL,
  professional_confirmed_at DATETIME(6) NULL,
  requires_human_approval TINYINT(1) NOT NULL DEFAULT 1,
  formally_issued_by BINARY(16) NULL,
  formal_issue_reason VARCHAR(500) NULL,
  formally_issued_at DATETIME(6) NULL,
  created_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_appointment_proposal (organisation_id,proposal_id),
  KEY idx_appointment_project_revision (organisation_id,project_id,revision),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id),
  FOREIGN KEY (proposal_id) REFERENCES professional_proposals(id),
  FOREIGN KEY (supersedes_appointment_id) REFERENCES professional_appointments(id),
  FOREIGN KEY (professional_confirmed_by) REFERENCES users(id),
  FOREIGN KEY (formally_issued_by) REFERENCES users(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kickoff_checklist_items (
  id BINARY(16) PRIMARY KEY,
  appointment_id BINARY(16) NOT NULL,
  item_key VARCHAR(80) NOT NULL,
  label VARCHAR(500) NOT NULL,
  owner_role VARCHAR(60) NOT NULL,
  required TINYINT(1) NOT NULL DEFAULT 1,
  completed TINYINT(1) NOT NULL DEFAULT 0,
  completed_at DATETIME(6) NULL,
  UNIQUE KEY uq_kickoff_item (appointment_id,item_key),
  FOREIGN KEY (appointment_id) REFERENCES professional_appointments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS appointment_initial_tasks (
  id BINARY(16) PRIMARY KEY,
  appointment_id BINARY(16) NOT NULL,
  project_id BINARY(16) NOT NULL,
  title VARCHAR(220) NOT NULL,
  phase VARCHAR(60) NOT NULL,
  owner_role VARCHAR(60) NOT NULL,
  status ENUM('open','completed','cancelled') NOT NULL DEFAULT 'open',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  FOREIGN KEY (appointment_id) REFERENCES professional_appointments(id),
  FOREIGN KEY (project_id) REFERENCES projects(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS appointment_documents (
  id BINARY(16) PRIMARY KEY,
  appointment_id BINARY(16) NOT NULL,
  project_id BINARY(16) NOT NULL,
  kind VARCHAR(80) NOT NULL,
  title VARCHAR(220) NOT NULL,
  status ENUM('placeholder','requires_human_approval','ready_to_generate','issued') NOT NULL,
  source_revision_id VARCHAR(80) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_appointment_document (appointment_id,kind),
  FOREIGN KEY (appointment_id) REFERENCES professional_appointments(id),
  FOREIGN KEY (project_id) REFERENCES projects(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
