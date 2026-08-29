-- architex:migration-mode=ddl
CREATE TABLE IF NOT EXISTS cpd_professional_body_rules (
  body_code ENUM('SACAP','ECSA','SACQSP','SACPCMP','SACPLAN','SACLAP','SAGC','SACPVP') PRIMARY KEY,
  body_name VARCHAR(220) NOT NULL, cycle_years TINYINT UNSIGNED NULL, unit_label VARCHAR(40) NOT NULL,
  rule_summary VARCHAR(2000) NOT NULL, category_rules JSON NOT NULL, calculation_policy ENUM('estimate_only','approved_value_required','manual_compliance_only') NOT NULL,
  source_note VARCHAR(1000) NOT NULL, version_label VARCHAR(80) NOT NULL, reviewed_on DATE NOT NULL,
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cpd_professional_profiles (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, user_id BINARY(16) NOT NULL,
  full_name VARCHAR(220) NOT NULL, profession VARCHAR(160) NOT NULL,
  professional_body ENUM('SACAP','ECSA','SACQSP','SACPCMP','SACPLAN','SACLAP','SAGC','SACPVP') NOT NULL,
  registration_number VARCHAR(180) NOT NULL, registration_category VARCHAR(120) NOT NULL,
  cycle_start DATE NOT NULL, cycle_end DATE NOT NULL,
  participation_role ENUM('learner','content_creator','cpd_reviewer','accreditation_partner','platform_admin') NOT NULL DEFAULT 'learner',
  popia_consent_at DATETIME(6) NOT NULL, status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_cpd_profile_user (organisation_id,user_id), KEY idx_cpd_profile_body (organisation_id,professional_body,status),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (user_id) REFERENCES users(id), FOREIGN KEY (professional_body) REFERENCES cpd_professional_body_rules(body_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cpd_content_items (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, title VARCHAR(220) NOT NULL,
  content_type ENUM('webinar','article','course','platform_training','other') NOT NULL, provider_name VARCHAR(220) NOT NULL,
  duration_minutes SMALLINT UNSIGNED NOT NULL, permission_status ENUM('owned','licensed','permission_required','rejected') NOT NULL,
  learning_outcomes JSON NOT NULL, source_text MEDIUMTEXT NOT NULL, target_bodies JSON NOT NULL,
  status ENUM('draft','human_review_required','approved','rejected','archived') NOT NULL DEFAULT 'draft', version INT UNSIGNED NOT NULL DEFAULT 1,
  created_by BINARY(16) NOT NULL, reviewed_by BINARY(16) NULL, review_reason VARCHAR(2000) NULL, reviewed_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  KEY idx_cpd_content (organisation_id,status,content_type), FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (created_by) REFERENCES users(id), FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cpd_assessments (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, content_item_id BINARY(16) NOT NULL,
  title VARCHAR(220) NOT NULL, version INT UNSIGNED NOT NULL DEFAULT 1, pass_percent DECIMAL(5,2) NOT NULL,
  attempt_limit TINYINT UNSIGNED NOT NULL, time_limit_minutes SMALLINT UNSIGNED NULL, generated_with_ai BOOLEAN NOT NULL DEFAULT FALSE,
  status ENUM('human_review_required','approved_for_accreditation','changes_required','retired') NOT NULL DEFAULT 'human_review_required',
  created_by BINARY(16) NOT NULL, reviewed_by BINARY(16) NULL, review_reason VARCHAR(2000) NULL, reviewed_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_cpd_assessment_version (content_item_id,version), KEY idx_cpd_assessment (organisation_id,status),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (content_item_id) REFERENCES cpd_content_items(id),
  FOREIGN KEY (created_by) REFERENCES users(id), FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cpd_questions (
  id BINARY(16) PRIMARY KEY, assessment_id BINARY(16) NOT NULL, sequence_no SMALLINT UNSIGNED NOT NULL,
  question_type ENUM('single_choice','multiple_choice','scenario','text_review') NOT NULL, prompt TEXT NOT NULL,
  points DECIMAL(8,2) NOT NULL, rationale TEXT NULL, requires_manual_review BOOLEAN NOT NULL DEFAULT FALSE,
  UNIQUE KEY uq_cpd_question_sequence (assessment_id,sequence_no), FOREIGN KEY (assessment_id) REFERENCES cpd_assessments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cpd_question_options (
  id BINARY(16) PRIMARY KEY, question_id BINARY(16) NOT NULL, sequence_no SMALLINT UNSIGNED NOT NULL,
  option_text VARCHAR(1000) NOT NULL, is_correct BOOLEAN NOT NULL DEFAULT FALSE,
  UNIQUE KEY uq_cpd_option_sequence (question_id,sequence_no), FOREIGN KEY (question_id) REFERENCES cpd_questions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cpd_accreditation_applications (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, assessment_id BINARY(16) NOT NULL,
  professional_body ENUM('SACAP','ECSA','SACQSP','SACPCMP','SACPLAN','SACLAP','SAGC','SACPVP') NOT NULL,
  connector_mode ENUM('api','portal','document_export','email','manual') NOT NULL, provider_name VARCHAR(220) NOT NULL,
  requested_category VARCHAR(160) NOT NULL, requested_credits DECIMAL(10,2) NOT NULL,
  approved_category VARCHAR(160) NULL, approved_credits DECIMAL(10,2) NULL, accreditation_reference VARCHAR(180) NULL,
  valid_from DATE NULL, valid_until DATE NULL, status ENUM('draft','submitted','under_review','accredited','changes_required','rejected','expired') NOT NULL DEFAULT 'submitted',
  rule_snapshot JSON NOT NULL, submitted_by BINARY(16) NOT NULL, decided_by BINARY(16) NULL, decision_reason VARCHAR(2000) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), decided_at DATETIME(6) NULL,
  KEY idx_cpd_accreditation (organisation_id,professional_body,status), FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (assessment_id) REFERENCES cpd_assessments(id), FOREIGN KEY (professional_body) REFERENCES cpd_professional_body_rules(body_code),
  FOREIGN KEY (submitted_by) REFERENCES users(id), FOREIGN KEY (decided_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cpd_courses (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, accreditation_application_id BINARY(16) NOT NULL,
  assessment_id BINARY(16) NOT NULL, title VARCHAR(220) NOT NULL, provider_name VARCHAR(220) NOT NULL,
  professional_body ENUM('SACAP','ECSA','SACQSP','SACPCMP','SACPLAN','SACLAP','SAGC','SACPVP') NOT NULL,
  approved_category VARCHAR(160) NOT NULL, approved_credits DECIMAL(10,2) NOT NULL, accreditation_reference VARCHAR(180) NOT NULL,
  valid_from DATE NOT NULL, valid_until DATE NOT NULL, price_cents BIGINT UNSIGNED NOT NULL, currency CHAR(3) NOT NULL DEFAULT 'ZAR',
  commercial_model ENUM('free_funnel','partner_bundle','webinar_addon','article_assessment','dedicated_course') NOT NULL,
  status ENUM('live','suspended','expired','retired') NOT NULL DEFAULT 'live', published_by BINARY(16) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_cpd_course_application (accreditation_application_id), KEY idx_cpd_course_catalogue (organisation_id,status,professional_body,valid_until),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (accreditation_application_id) REFERENCES cpd_accreditation_applications(id),
  FOREIGN KEY (assessment_id) REFERENCES cpd_assessments(id), FOREIGN KEY (published_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cpd_commercial_settings (
  organisation_id BINARY(16) PRIMARY KEY, platform_fee_basis_points SMALLINT UNSIGNED NOT NULL DEFAULT 1000,
  minimum_platform_fee_cents BIGINT UNSIGNED NOT NULL DEFAULT 100, payout_enabled BOOLEAN NOT NULL DEFAULT FALSE,
  configured_providers JSON NOT NULL, updated_by BINARY(16) NOT NULL, updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cpd_entitlements (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, course_id BINARY(16) NOT NULL, learner_user_id BINARY(16) NOT NULL,
  payment_method ENUM('free','partner_bundle','manual_eft','payfast','yoco','other_provider') NOT NULL,
  payment_reference VARCHAR(220) NULL, amount_cents BIGINT UNSIGNED NOT NULL, currency CHAR(3) NOT NULL DEFAULT 'ZAR',
  status ENUM('review_required','active','declined','refunded','revoked') NOT NULL, requested_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  reviewed_by BINARY(16) NULL, review_reason VARCHAR(2000) NULL, activated_at DATETIME(6) NULL,
  UNIQUE KEY uq_cpd_entitlement (course_id,learner_user_id), KEY idx_cpd_entitlement (organisation_id,learner_user_id,status),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (course_id) REFERENCES cpd_courses(id),
  FOREIGN KEY (learner_user_id) REFERENCES users(id), FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cpd_payment_events (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, provider VARCHAR(80) NOT NULL, provider_event_id VARCHAR(220) NOT NULL,
  event_type VARCHAR(120) NOT NULL, signature_verified BOOLEAN NOT NULL DEFAULT FALSE, payload_hash CHAR(64) NOT NULL,
  processing_status ENUM('received','processed','ignored','failed') NOT NULL, entitlement_id BINARY(16) NULL,
  received_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), processed_at DATETIME(6) NULL,
  UNIQUE KEY uq_cpd_provider_event (organisation_id,provider,provider_event_id), FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (entitlement_id) REFERENCES cpd_entitlements(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cpd_attempts (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, course_id BINARY(16) NOT NULL, assessment_id BINARY(16) NOT NULL,
  learner_user_id BINARY(16) NOT NULL, entitlement_id BINARY(16) NOT NULL, attempt_number TINYINT UNSIGNED NOT NULL,
  status ENUM('in_progress','manual_review_required','passed','failed','expired') NOT NULL DEFAULT 'in_progress',
  auto_score DECIMAL(5,2) NULL, final_score DECIMAL(5,2) NULL, passed BOOLEAN NULL, manual_review_required BOOLEAN NOT NULL DEFAULT FALSE,
  started_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), submitted_at DATETIME(6) NULL, reviewed_by BINARY(16) NULL, review_reason VARCHAR(2000) NULL, reviewed_at DATETIME(6) NULL,
  UNIQUE KEY uq_cpd_attempt_number (course_id,learner_user_id,attempt_number), KEY idx_cpd_attempt (organisation_id,learner_user_id,status),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (course_id) REFERENCES cpd_courses(id), FOREIGN KEY (assessment_id) REFERENCES cpd_assessments(id),
  FOREIGN KEY (learner_user_id) REFERENCES users(id), FOREIGN KEY (entitlement_id) REFERENCES cpd_entitlements(id), FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cpd_attempt_answers (
  id BINARY(16) PRIMARY KEY, attempt_id BINARY(16) NOT NULL, question_id BINARY(16) NOT NULL,
  selected_option_ids JSON NOT NULL, answer_text TEXT NULL, awarded_points DECIMAL(8,2) NULL, is_correct BOOLEAN NULL,
  UNIQUE KEY uq_cpd_attempt_answer (attempt_id,question_id), FOREIGN KEY (attempt_id) REFERENCES cpd_attempts(id), FOREIGN KEY (question_id) REFERENCES cpd_questions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cpd_certificates (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, attempt_id BINARY(16) NOT NULL, learner_user_id BINARY(16) NOT NULL,
  course_id BINARY(16) NOT NULL, verification_code CHAR(32) NOT NULL, learner_name VARCHAR(220) NOT NULL,
  professional_body VARCHAR(20) NOT NULL, registration_number VARCHAR(180) NOT NULL, approved_category VARCHAR(160) NOT NULL,
  credits_awarded DECIMAL(10,2) NOT NULL, accreditation_reference VARCHAR(180) NOT NULL, provider_name VARCHAR(220) NOT NULL,
  issued_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), revoked_at DATETIME(6) NULL, revocation_reason VARCHAR(1000) NULL,
  UNIQUE KEY uq_cpd_certificate_attempt (attempt_id), UNIQUE KEY uq_cpd_verification_code (verification_code),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (attempt_id) REFERENCES cpd_attempts(id),
  FOREIGN KEY (learner_user_id) REFERENCES users(id), FOREIGN KEY (course_id) REFERENCES cpd_courses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cpd_professional_records (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, learner_user_id BINARY(16) NOT NULL, certificate_id BINARY(16) NOT NULL,
  attempt_id BINARY(16) NOT NULL, professional_body VARCHAR(20) NOT NULL, registration_number VARCHAR(180) NOT NULL,
  activity_title VARCHAR(220) NOT NULL, completed_on DATE NOT NULL, approved_category VARCHAR(160) NOT NULL,
  credits_awarded DECIMAL(10,2) NOT NULL, accreditation_reference VARCHAR(180) NOT NULL, rule_snapshot JSON NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_cpd_record_attempt (attempt_id), KEY idx_cpd_record_learner (organisation_id,learner_user_id,completed_on),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (learner_user_id) REFERENCES users(id),
  FOREIGN KEY (certificate_id) REFERENCES cpd_certificates(id), FOREIGN KEY (attempt_id) REFERENCES cpd_attempts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cpd_referral_events (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NULL, user_id BINARY(16) NULL,
  referral_source VARCHAR(120) NOT NULL, campaign VARCHAR(160) NULL, event_type ENUM('landing','catalogue_view','course_view','checkout_started','enrolled','completed') NOT NULL,
  course_id BINARY(16) NULL, consented BOOLEAN NOT NULL DEFAULT FALSE, occurred_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_cpd_referral (referral_source,campaign,event_type,occurred_at), FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (user_id) REFERENCES users(id), FOREIGN KEY (course_id) REFERENCES cpd_courses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cpd_audit_events (
  id BINARY(16) PRIMARY KEY, organisation_id BINARY(16) NOT NULL, actor_user_id BINARY(16) NOT NULL,
  action VARCHAR(160) NOT NULL, object_type VARCHAR(100) NOT NULL, object_id BINARY(16) NOT NULL, context JSON NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), KEY idx_cpd_audit (organisation_id,action,created_at),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id), FOREIGN KEY (actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
