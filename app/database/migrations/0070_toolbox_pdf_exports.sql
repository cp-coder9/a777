-- architex:migration-mode=ddl
-- Preserve the existing export store while enabling real binary PDF artifacts.
ALTER TABLE tool_run_exports MODIFY format ENUM('json','csv','html','pdf') NOT NULL, MODIFY content MEDIUMBLOB NOT NULL;
