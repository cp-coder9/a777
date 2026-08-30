-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS dedicated_app_event_spine_links (
  workflow_event_id BINARY(16) PRIMARY KEY,
  organisation_id BINARY(16) NOT NULL,
  project_record_id BINARY(16) NULL,
  inbox_item_id BINARY(16) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_dedicated_app_project_record (project_record_id),
  UNIQUE KEY uq_dedicated_app_inbox_item (inbox_item_id),
  KEY idx_dedicated_app_spine_links_org (organisation_id,created_at),
  FOREIGN KEY (workflow_event_id) REFERENCES dedicated_app_workflow_events(id),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (project_record_id) REFERENCES project_records(id),
  FOREIGN KEY (inbox_item_id) REFERENCES inbox_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
