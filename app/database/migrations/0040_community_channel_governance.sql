-- architex:migration-mode=dml
INSERT INTO community_channel_governance(channel_id,eligible_roles,visibility,default_moderation_policy) VALUES
('general_built_environment',NULL,'public','standard'),
('role-architects',JSON_ARRAY('architect'),'role_restricted','standard'),
('role-candidate-architects',JSON_ARRAY('candidate_architect'),'role_restricted','standard'),
('role-quantity-surveyors',JSON_ARRAY('quantity_surveyor'),'role_restricted','standard'),
('role-engineers',JSON_ARRAY('engineer'),'role_restricted','standard'),
('role-town-planners',JSON_ARRAY('town_planner'),'role_restricted','standard'),
('role-land-surveyors',JSON_ARRAY('land_surveyor'),'role_restricted','standard'),
('role-contractors',JSON_ARRAY('contractor'),'role_restricted','standard'),
('role-subcontractors',JSON_ARRAY('subcontractor'),'role_restricted','standard'),
('role-suppliers',JSON_ARRAY('supplier'),'role_restricted','standard'),
('role-clients-developers',JSON_ARRAY('client_developer'),'role_restricted','standard'),
('regulations-sans',NULL,'public','standard'),
('municipal-process',NULL,'public','standard'),
('cpd-learning',NULL,'public','standard'),
('jobs-opportunities',NULL,'public','standard'),
('products-materials',NULL,'public','standard'),
('site-issues',NULL,'public','standard'),
('announcements',NULL,'public','preapprove_all')
ON DUPLICATE KEY UPDATE eligible_roles=VALUES(eligible_roles),visibility=VALUES(visibility),default_moderation_policy=VALUES(default_moderation_policy);
