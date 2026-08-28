-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS governed_meetings (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  project_id BINARY(16) NULL,
  title VARCHAR(220) NOT NULL,
  meeting_type VARCHAR(80) NOT NULL,
  phase VARCHAR(60) NOT NULL,
  status ENUM('draft','scheduled','live','review_required','published','cancelled') NOT NULL DEFAULT 'scheduled',
  starts_at DATETIME(6) NOT NULL,
  duration_minutes SMALLINT UNSIGNED NOT NULL,
  agenda JSON NOT NULL,
  attendees JSON NOT NULL,
  policy JSON NOT NULL,
  started_at DATETIME(6) NULL,
  concluded_at DATETIME(6) NULL,
  created_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_meetings_org_project_time (organisation_id,project_id,starts_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS meeting_consents (
  id BINARY(16) PRIMARY KEY,
  meeting_id BINARY(16) NOT NULL,
  attendee_identity VARCHAR(320) NOT NULL,
  consented TINYINT(1) NOT NULL,
  recorded_by BINARY(16) NOT NULL,
  recorded_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_meeting_consent_identity (meeting_id,attendee_identity),
  FOREIGN KEY (meeting_id) REFERENCES governed_meetings(id),
  FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS governed_meeting_minutes (
  id BINARY(16) PRIMARY KEY,
  meeting_id BINARY(16) NOT NULL,
  supersedes_minutes_id BINARY(16) NULL,
  revision INT UNSIGNED NOT NULL,
  status ENUM('draft','published','superseded') NOT NULL DEFAULT 'draft',
  transcript JSON NOT NULL,
  minutes_content JSON NOT NULL,
  outcomes JSON NOT NULL,
  correction_reason VARCHAR(500) NULL,
  publication_reason VARCHAR(500) NULL,
  audit_hash CHAR(64) NULL,
  reviewed_by BINARY(16) NULL,
  published_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_meeting_minutes_revision (meeting_id,revision),
  FOREIGN KEY (meeting_id) REFERENCES governed_meetings(id),
  FOREIGN KEY (supersedes_minutes_id) REFERENCES governed_meeting_minutes(id),
  FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS meeting_outcome_writebacks (
  id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  project_id BINARY(16) NOT NULL,
  meeting_id BINARY(16) NOT NULL,
  minutes_id BINARY(16) NOT NULL,
  outcome_index INT UNSIGNED NOT NULL,
  outcome_type VARCHAR(40) NOT NULL,
  destination VARCHAR(80) NOT NULL,
  payload JSON NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_meeting_outcome_once (minutes_id,outcome_index),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_id) REFERENCES projects(id),
  FOREIGN KEY (meeting_id) REFERENCES governed_meetings(id),
  FOREIGN KEY (minutes_id) REFERENCES governed_meeting_minutes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
