-- ServiceYar schema-only database reference from 2026-11-13 snapshot
-- No live records, user accounts, credentials or tokens included.
-- Reference only; do not execute against a production database.
SET NAMES utf8mb4;

CREATE TABLE `accounting_accounts` (
  `id` bigint UNSIGNED NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `code` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bank_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_account` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `iban` varchar(34) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_id` bigint UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `accounting_documents` (
  `id` bigint UNSIGNED NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `document_no` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_date` date NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `total_amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `accounting_document_lines` (
  `id` bigint UNSIGNED NOT NULL,
  `document_id` bigint UNSIGNED NOT NULL,
  `account_id` bigint UNSIGNED NOT NULL,
  `description` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tracking_no` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `debit` decimal(18,2) NOT NULL DEFAULT '0.00',
  `credit` decimal(18,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `activity_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `agency_id` bigint UNSIGNED DEFAULT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `action` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` bigint UNSIGNED DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `agencies` (
  `id` bigint UNSIGNED NOT NULL,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city_id` bigint UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `agency_school_catalog` (
  `agency_id` bigint UNSIGNED NOT NULL,
  `catalog_school_id` bigint UNSIGNED NOT NULL,
  `linked_school_id` bigint UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `agency_school_tariffs` (
  `id` bigint UNSIGNED NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `academic_year` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `region` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `vehicle_class` tinyint UNSIGNED NOT NULL,
  `distance_km` smallint UNSIGNED NOT NULL,
  `amount_irr` bigint UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `agency_settings` (
  `id` bigint UNSIGNED NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `setting_key` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `is_secret` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `agency_users` (
  `agency_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ai_conversations` (
  `id` bigint UNSIGNED NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ai_messages` (
  `id` bigint UNSIGNED NOT NULL,
  `conversation_id` bigint UNSIGNED NOT NULL,
  `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `api_idempotency_keys` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `key_value` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_method` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `response_code` smallint UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `arta_before_20261104_agencies` (
  `id` bigint UNSIGNED NOT NULL DEFAULT '0',
  `uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city_id` bigint UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `arta_before_20261104_agency_settings` (
  `id` bigint UNSIGNED NOT NULL DEFAULT '0',
  `agency_id` bigint UNSIGNED NOT NULL,
  `setting_key` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_secret` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `arta_before_20261104_drivers` (
  `id` bigint UNSIGNED NOT NULL DEFAULT '0',
  `uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `national_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `rating` decimal(4,2) DEFAULT NULL,
  `vehicle_id` bigint UNSIGNED DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_representative` tinyint(1) NOT NULL DEFAULT '0',
  `bank_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_account` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `iban` varchar(34) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `arta_before_20261104_schools` (
  `id` bigint UNSIGNED NOT NULL DEFAULT '0',
  `uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `school_type` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manager_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `region` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `gender` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shift` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `education_levels` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `arta_before_20261104_students` (
  `id` bigint UNSIGNED NOT NULL DEFAULT '0',
  `uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `school_id` bigint UNSIGNED NOT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `national_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grade` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `guardian_first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guardian_last_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guardian_national_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guardian_relation` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `father_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nationality` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'iranian',
  `health_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_phone` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `return_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `academic_year` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shift` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no_request',
  `monthly_fee` decimal(18,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `arta_before_20261104_vehicles` (
  `id` bigint UNSIGNED NOT NULL DEFAULT '0',
  `agency_id` bigint UNSIGNED NOT NULL,
  `plate` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capacity` smallint UNSIGNED NOT NULL DEFAULT '4',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `arta_before_vehicle_fix_drivers` (
  `id` bigint UNSIGNED NOT NULL DEFAULT '0',
  `uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `national_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `rating` decimal(4,2) DEFAULT NULL,
  `vehicle_id` bigint UNSIGNED DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_representative` tinyint(1) NOT NULL DEFAULT '0',
  `bank_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_account` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `iban` varchar(34) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `arta_before_vehicle_fix_vehicles` (
  `id` bigint UNSIGNED NOT NULL DEFAULT '0',
  `agency_id` bigint UNSIGNED NOT NULL,
  `plate` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capacity` smallint UNSIGNED NOT NULL DEFAULT '4',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `arta_import_sources` (
  `agency_id` bigint UNSIGNED NOT NULL,
  `source_key` char(64) NOT NULL,
  `source_name` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `audit_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `action` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `auth_login_attempts` (
  `id` bigint UNSIGNED NOT NULL,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `succeeded` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cheque_events` (
  `id` bigint UNSIGNED NOT NULL,
  `cheque_id` bigint UNSIGNED NOT NULL,
  `actor_id` bigint UNSIGNED NOT NULL,
  `event_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_date` date NOT NULL,
  `note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cheque_receipts` (
  `id` bigint UNSIGNED NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `bank_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `branch_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `serial_no` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bank_account` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `received_from` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_id` bigint UNSIGNED NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `received_date` date NOT NULL,
  `due_date` date NOT NULL,
  `description` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'received',
  `created_by` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `contracts` (
  `id` bigint UNSIGNED NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contract_no` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `party_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `party_id` bigint UNSIGNED DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `drivers` (
  `id` bigint UNSIGNED NOT NULL,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `national_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `rating` decimal(4,2) DEFAULT NULL,
  `vehicle_id` bigint UNSIGNED DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_representative` tinyint(1) NOT NULL DEFAULT '0',
  `bank_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_account` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `iban` varchar(34) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `driver_changes` (
  `id` bigint UNSIGNED NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `service_id` bigint UNSIGNED NOT NULL,
  `old_driver_id` bigint UNSIGNED DEFAULT NULL,
  `new_driver_id` bigint UNSIGNED DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `reason` text COLLATE utf8mb4_unicode_ci,
  `reviewed_by` bigint UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `driver_contracts` (
  `id` bigint UNSIGNED NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `driver_id` bigint UNSIGNED NOT NULL,
  `contract_id` bigint UNSIGNED NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `driver_documents` (
  `id` bigint UNSIGNED NOT NULL,
  `driver_id` bigint UNSIGNED NOT NULL,
  `document_type` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_no` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'valid',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `driver_payments` (
  `id` bigint UNSIGNED NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `driver_id` bigint UNSIGNED NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `reference_no` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `driver_ratings` (
  `id` bigint UNSIGNED NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `driver_id` bigint UNSIGNED NOT NULL,
  `score` decimal(3,1) NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `driver_work` (
  `id` bigint UNSIGNED NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `driver_id` bigint UNSIGNED NOT NULL,
  `work_date` date NOT NULL,
  `service_id` bigint UNSIGNED DEFAULT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `files` (
  `id` bigint UNSIGNED NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `entity_type` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` bigint UNSIGNED DEFAULT NULL,
  `storage_key` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size_bytes` bigint UNSIGNED NOT NULL DEFAULT '0',
  `is_private` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `guardians` (
  `id` bigint UNSIGNED NOT NULL,
  `student_id` bigint UNSIGNED NOT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `relation_title` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `holidays` (
  `id` bigint UNSIGNED NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_jalali` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_gregorian` date DEFAULT NULL,
  `holiday_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'holiday',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `invoices` (
  `id` bigint UNSIGNED NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `party_type` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `party_id` bigint UNSIGNED DEFAULT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `issue_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ledger_entries` (
  `id` bigint UNSIGNED NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `party_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `party_id` bigint UNSIGNED NOT NULL,
  `company_account_id` bigint UNSIGNED DEFAULT NULL,
  `direction` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `entry_date` date NOT NULL,
  `method` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_no` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `mehr1405_financial_import` (
  `student_national_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `school_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `driver_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vehicle_class` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `distance_km` decimal(10,3) DEFAULT NULL,
  `days_served` decimal(8,2) DEFAULT NULL,
  `driver_base_irr` decimal(18,2) DEFAULT NULL,
  `driver_month_irr` decimal(18,2) DEFAULT NULL,
  `parent_month_irr` decimal(18,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `messages` (
  `id` bigint UNSIGNED NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `channel` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'internal',
  `recipient_type` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recipient_id` bigint UNSIGNED DEFAULT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `recipient_mobile` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_status` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attempts` int NOT NULL DEFAULT '0',
  `last_error` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `migrations` (
  `id` bigint UNSIGNED NOT NULL,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `packs` (
  `id` bigint UNSIGNED NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `capacity` smallint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `parent_reports` (
  `id` bigint UNSIGNED NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `student_id` bigint UNSIGNED NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `response` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `payments` (
  `id` bigint UNSIGNED NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `invoice_id` bigint UNSIGNED DEFAULT NULL,
  `amount` decimal(18,2) NOT NULL,
  `payment_date` date NOT NULL,
  `method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_no` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `payment_intents` (
  `id` bigint UNSIGNED NOT NULL,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `invoice_id` bigint UNSIGNED NOT NULL,
  `created_by` bigint UNSIGNED NOT NULL,
  `payload` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `chat_id` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` bigint UNSIGNED NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'created',
  `transaction_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_id` bigint UNSIGNED DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `paid_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `payroll_documents` (
  `id` bigint UNSIGNED NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `document_no` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_from` date NOT NULL,
  `date_to` date NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `total_amount` decimal(18,2) NOT NULL,
  `created_by` bigint UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `payroll_document_lines` (
  `id` bigint UNSIGNED NOT NULL,
  `document_id` bigint UNSIGNED NOT NULL,
  `driver_id` bigint UNSIGNED NOT NULL,
  `earned` decimal(18,2) NOT NULL,
  `agreed` decimal(18,2) NOT NULL,
  `paid` decimal(18,2) NOT NULL,
  `salary` decimal(18,2) NOT NULL,
  `supervision_deduction` decimal(18,2) DEFAULT NULL,
  `coordination_deduction` decimal(18,2) DEFAULT NULL,
  `overhead_deduction` decimal(18,2) DEFAULT NULL,
  `insurance_deduction` decimal(18,2) DEFAULT NULL,
  `total_deductions` decimal(18,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `permissions` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `platform_settings` (
  `setting_key` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `roles` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `role_permissions` (
  `role_id` bigint UNSIGNED NOT NULL,
  `permission_id` bigint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `schools` (
  `id` bigint UNSIGNED NOT NULL,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `school_type` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manager_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `region` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `gender` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shift` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `education_levels` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `school_assignment_import_mehr1405` (
  `student_national_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `school_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `driver_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vehicle_class` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `distance_km` decimal(10,3) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `school_catalog` (
  `id` bigint UNSIGNED NOT NULL,
  `education_code` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `region` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shift_name` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `education_level` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `phone` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manager_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `school_charges` (
  `id` bigint UNSIGNED NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `school_id` bigint UNSIGNED NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `due_date` date DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `school_monthly_statements` (
  `id` bigint UNSIGNED NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `jalali_year` smallint UNSIGNED NOT NULL,
  `jalali_month` tinyint UNSIGNED NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `academic_year` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('final','deleted') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'final',
  `snapshot_json` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` bigint UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_by` bigint UNSIGNED DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `restored_by` bigint UNSIGNED DEFAULT NULL,
  `restored_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `school_service_import_fees` (
  `agency_id` bigint UNSIGNED NOT NULL,
  `academic_year` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `period` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `student_national_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `student_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `driver_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `school_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `distance_km` decimal(18,4) DEFAULT NULL,
  `service_days` decimal(18,4) DEFAULT NULL,
  `driver_fee` decimal(20,4) DEFAULT NULL,
  `oversight_fee` decimal(20,4) DEFAULT NULL,
  `municipality_fee` decimal(20,4) DEFAULT NULL,
  `municipality_vat` decimal(20,4) DEFAULT NULL,
  `road_police_fee` decimal(20,4) DEFAULT NULL,
  `education_fee` decimal(20,4) DEFAULT NULL,
  `company_fee` decimal(20,4) DEFAULT NULL,
  `certificate_fee` decimal(20,4) DEFAULT NULL,
  `insurance_fee` decimal(20,4) DEFAULT NULL,
  `parent_total` decimal(20,4) DEFAULT NULL,
  `statement_total` decimal(20,4) DEFAULT NULL,
  `guardian_iban` varchar(34) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `driver_id` bigint UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `services` (
  `id` bigint UNSIGNED NOT NULL,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `school_id` bigint UNSIGNED DEFAULT NULL,
  `driver_id` bigint UNSIGNED DEFAULT NULL,
  `capacity` smallint UNSIGNED NOT NULL DEFAULT '4',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `start_address` text COLLATE utf8mb4_unicode_ci,
  `end_address` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `pack_id` bigint UNSIGNED DEFAULT NULL,
  `shift` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monthly_fee` decimal(18,2) NOT NULL DEFAULT '0.00',
  `agreed_amount` decimal(18,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `service_charges` (
  `id` bigint UNSIGNED NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `service_id` bigint UNSIGNED NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `due_date` date DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `service_locations` (
  `id` bigint UNSIGNED NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `service_id` bigint UNSIGNED NOT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `recorded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `service_schools` (
  `service_id` bigint UNSIGNED NOT NULL,
  `school_id` bigint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `service_students` (
  `service_id` bigint UNSIGNED NOT NULL,
  `student_id` bigint UNSIGNED NOT NULL,
  `pickup_address` text COLLATE utf8mb4_unicode_ci,
  `pickup_order` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `students` (
  `id` bigint UNSIGNED NOT NULL,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `school_id` bigint UNSIGNED NOT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `national_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grade` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `guardian_first_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guardian_last_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guardian_national_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guardian_relation` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `father_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nationality` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'iranian',
  `health_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_phone` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `return_address` text COLLATE utf8mb4_unicode_ci,
  `academic_year` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shift` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no_request',
  `monthly_fee` decimal(18,2) NOT NULL DEFAULT '0.00',
  `tariff_distance_km` decimal(10,3) DEFAULT NULL,
  `tariff_vehicle_class` tinyint UNSIGNED DEFAULT NULL,
  `tariff_calculated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `student_contracts` (
  `id` bigint UNSIGNED NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `student_id` bigint UNSIGNED NOT NULL,
  `contract_id` bigint UNSIGNED NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id` bigint UNSIGNED NOT NULL,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_roles` (
  `user_id` bigint UNSIGNED NOT NULL,
  `role_id` bigint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_sessions` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `csrf_token` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `last_seen_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vehicles` (
  `id` bigint UNSIGNED NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `plate` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capacity` smallint UNSIGNED NOT NULL DEFAULT '4',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `wallets` (
  `id` bigint UNSIGNED NOT NULL,
  `agency_id` bigint UNSIGNED NOT NULL,
  `owner_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_id` bigint UNSIGNED DEFAULT NULL,
  `balance` decimal(18,2) NOT NULL DEFAULT '0.00',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Indexes, keys, auto increments and constraints

ALTER TABLE `accounting_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_account_code` (`agency_id`,`code`),
  ADD KEY `fk_accounts_parent` (`parent_id`);

ALTER TABLE `accounting_documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_accounting_doc` (`agency_id`,`document_no`);

ALTER TABLE `accounting_document_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_journal_document` (`document_id`),
  ADD KEY `ix_journal_account` (`account_id`,`document_id`);

ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_activity_agency_date` (`agency_id`,`created_at`),
  ADD KEY `fk_activity_user` (`user_id`);

ALTER TABLE `agencies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uuid` (`uuid`),
  ADD UNIQUE KEY `code` (`code`);

ALTER TABLE `agency_school_catalog`
  ADD PRIMARY KEY (`agency_id`,`catalog_school_id`),
  ADD KEY `fk_asc_catalog` (`catalog_school_id`),
  ADD KEY `fk_asc_school` (`linked_school_id`);

ALTER TABLE `agency_school_tariffs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_agency_tariff` (`agency_id`,`academic_year`,`region`,`vehicle_class`,`distance_km`);

ALTER TABLE `agency_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_agency_setting` (`agency_id`,`setting_key`),
  ADD KEY `idx_agency_settings_agency` (`agency_id`);

ALTER TABLE `agency_users`
  ADD PRIMARY KEY (`agency_id`,`user_id`),
  ADD KEY `fk_agency_users_user` (`user_id`);

ALTER TABLE `ai_conversations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ai_conversations_scope` (`agency_id`,`user_id`),
  ADD KEY `fk_ai_conv_user` (`user_id`);

ALTER TABLE `ai_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ai_messages_conversation` (`conversation_id`);

ALTER TABLE `api_idempotency_keys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_idempotency_user_key` (`user_id`,`key_value`),
  ADD KEY `idx_idempotency_created` (`created_at`);

ALTER TABLE `arta_import_sources`
  ADD PRIMARY KEY (`agency_id`,`source_key`);

ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uuid` (`uuid`),
  ADD KEY `idx_audit_user_created` (`user_id`,`created_at`),
  ADD KEY `idx_audit_entity` (`entity_type`,`entity_id`);

ALTER TABLE `auth_login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_login_attempts_ip_time` (`ip_address`,`attempted_at`),
  ADD KEY `idx_login_attempts_user_time` (`username`,`attempted_at`);

ALTER TABLE `cheque_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cheque_event_actor` (`actor_id`),
  ADD KEY `ix_cheque_events` (`cheque_id`,`id`);

ALTER TABLE `cheque_receipts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cheque_serial` (`agency_id`,`bank_name`,`serial_no`),
  ADD KEY `ix_cheque_status_date` (`agency_id`,`status`,`due_date`),
  ADD KEY `fk_cheque_account` (`account_id`),
  ADD KEY `fk_cheque_creator` (`created_by`);

ALTER TABLE `contracts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_contracts_agency_status` (`agency_id`,`status`);

ALTER TABLE `drivers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uuid` (`uuid`),
  ADD KEY `idx_drivers_agency_status` (`agency_id`,`status`,`is_active`),
  ADD KEY `fk_drivers_vehicle` (`vehicle_id`);

ALTER TABLE `driver_changes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_driver_changes` (`agency_id`,`status`),
  ADD KEY `fk_driver_changes_service` (`service_id`);

ALTER TABLE `driver_contracts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_driver_contract` (`driver_id`,`contract_id`),
  ADD KEY `idx_driver_contracts_agency` (`agency_id`),
  ADD KEY `fk_driver_contracts_contract` (`contract_id`);

ALTER TABLE `driver_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_driver_docs` (`driver_id`);

ALTER TABLE `driver_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_driver_payments_agency_date` (`agency_id`,`payment_date`),
  ADD KEY `fk_driver_payments_driver` (`driver_id`);

ALTER TABLE `driver_ratings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `driver_id` (`driver_id`),
  ADD KEY `idx_rating_driver` (`agency_id`,`driver_id`);

ALTER TABLE `driver_work`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_driver_work` (`driver_id`,`work_date`,`service_id`),
  ADD KEY `idx_driver_work_agency_date` (`agency_id`,`work_date`),
  ADD KEY `fk_driver_work_service` (`service_id`);

ALTER TABLE `files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_files_entity` (`entity_type`,`entity_id`),
  ADD KEY `fk_files_agency` (`agency_id`);

ALTER TABLE `guardians`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_guardians_student` (`student_id`);

ALTER TABLE `holidays`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_holidays_agency_date` (`agency_id`,`date_gregorian`);

ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_invoices_agency_status` (`agency_id`,`status`);

ALTER TABLE `ledger_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ledger_party` (`agency_id`,`party_type`,`party_id`,`entry_date`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_ledger_company_account` (`agency_id`,`company_account_id`),
  ADD KEY `fk_ledger_company_account` (`company_account_id`);

ALTER TABLE `mehr1405_financial_import`
  ADD PRIMARY KEY (`student_national_code`);

ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_messages_agency_status` (`agency_id`,`status`);

ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `migration` (`migration`);

ALTER TABLE `packs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_packs_agency` (`agency_id`);

ALTER TABLE `parent_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `agency_id` (`agency_id`),
  ADD KEY `student_id` (`student_id`);

ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payments_agency_date` (`agency_id`,`payment_date`),
  ADD KEY `fk_payments_invoice` (`invoice_id`);

ALTER TABLE `payment_intents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uuid` (`uuid`),
  ADD UNIQUE KEY `payload` (`payload`),
  ADD UNIQUE KEY `transaction_id` (`transaction_id`),
  ADD UNIQUE KEY `payment_id` (`payment_id`),
  ADD KEY `idx_payment_intents_scope` (`agency_id`,`invoice_id`,`status`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `created_by` (`created_by`);

ALTER TABLE `payroll_documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_payroll_period` (`agency_id`,`date_from`,`date_to`),
  ADD KEY `created_by` (`created_by`);

ALTER TABLE `payroll_document_lines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_payroll_driver` (`document_id`,`driver_id`),
  ADD KEY `driver_id` (`driver_id`);

ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

ALTER TABLE `platform_settings`
  ADD PRIMARY KEY (`setting_key`);

ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `fk_role_permissions_permission` (`permission_id`);

ALTER TABLE `schools`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uuid` (`uuid`),
  ADD UNIQUE KEY `uq_school_code` (`agency_id`,`code`),
  ADD KEY `idx_schools_agency_active` (`agency_id`,`is_active`);

ALTER TABLE `school_assignment_import_mehr1405`
  ADD PRIMARY KEY (`student_national_code`);

ALTER TABLE `school_catalog`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_school_catalog_code` (`education_code`),
  ADD KEY `idx_school_catalog_name` (`name`);

ALTER TABLE `school_charges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_school_charges` (`agency_id`,`school_id`,`status`),
  ADD KEY `fk_school_charges_school` (`school_id`);

ALTER TABLE `school_monthly_statements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_school_statement_agency_period` (`agency_id`,`jalali_year`,`jalali_month`,`status`);

ALTER TABLE `school_service_import_fees`
  ADD PRIMARY KEY (`agency_id`,`academic_year`,`period`,`student_national_code`),
  ADD KEY `idx_school_import_student` (`agency_id`,`student_national_code`),
  ADD KEY `fk_school_import_driver` (`driver_id`);

ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uuid` (`uuid`),
  ADD UNIQUE KEY `uq_service_code` (`agency_id`,`code`),
  ADD KEY `idx_services_agency_status` (`agency_id`,`status`,`is_active`),
  ADD KEY `fk_services_school` (`school_id`),
  ADD KEY `fk_services_driver` (`driver_id`),
  ADD KEY `fk_services_pack` (`pack_id`);

ALTER TABLE `service_charges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_service_charges` (`agency_id`,`service_id`,`status`),
  ADD KEY `fk_service_charges_service` (`service_id`);

ALTER TABLE `service_locations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_service_locations` (`service_id`,`recorded_at`),
  ADD KEY `idx_service_locations_agency` (`agency_id`);

ALTER TABLE `service_schools`
  ADD PRIMARY KEY (`service_id`,`school_id`),
  ADD KEY `school_id` (`school_id`);

ALTER TABLE `service_students`
  ADD PRIMARY KEY (`service_id`,`student_id`),
  ADD KEY `fk_service_students_student` (`student_id`);

ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uuid` (`uuid`),
  ADD KEY `idx_students_agency_active` (`agency_id`,`is_active`),
  ADD KEY `idx_students_school` (`school_id`),
  ADD KEY `idx_student_status` (`agency_id`,`service_status`,`academic_year`);

ALTER TABLE `student_contracts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_student_contract` (`student_id`,`contract_id`),
  ADD KEY `idx_student_contracts_agency` (`agency_id`),
  ADD KEY `fk_student_contracts_contract` (`contract_id`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uuid` (`uuid`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_users_active` (`is_active`);

ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`user_id`,`role_id`),
  ADD KEY `fk_user_roles_role` (`role_id`);

ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_hash` (`token_hash`),
  ADD KEY `idx_sessions_user_active` (`user_id`,`revoked_at`,`expires_at`),
  ADD KEY `idx_sessions_expiry` (`expires_at`);

ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_vehicle_plate` (`agency_id`,`plate`),
  ADD KEY `idx_vehicle_agency` (`agency_id`);

ALTER TABLE `wallets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_wallets_agency` (`agency_id`);

ALTER TABLE `accounting_accounts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `accounting_documents`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `accounting_document_lines`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `activity_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=917;

ALTER TABLE `agencies`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

ALTER TABLE `agency_school_tariffs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

ALTER TABLE `agency_settings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

ALTER TABLE `ai_conversations`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `ai_messages`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `api_idempotency_keys`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `audit_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

ALTER TABLE `auth_login_attempts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

ALTER TABLE `cheque_events`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `cheque_receipts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `contracts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `drivers`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=126;

ALTER TABLE `driver_changes`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `driver_contracts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `driver_documents`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `driver_payments`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `driver_ratings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `driver_work`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `files`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `guardians`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `holidays`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `invoices`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `ledger_entries`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=602;

ALTER TABLE `messages`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `migrations`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

ALTER TABLE `packs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `parent_reports`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `payments`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `payment_intents`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `payroll_documents`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `payroll_document_lines`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `permissions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

ALTER TABLE `roles`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

ALTER TABLE `schools`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2088;

ALTER TABLE `school_catalog`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1163;

ALTER TABLE `school_charges`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `school_monthly_statements`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `services`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=201;

ALTER TABLE `service_charges`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `service_locations`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `students`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=647;

ALTER TABLE `student_contracts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

ALTER TABLE `user_sessions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

ALTER TABLE `vehicles`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=112;

ALTER TABLE `wallets`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `accounting_accounts`
  ADD CONSTRAINT `fk_accounts_agency` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_accounts_parent` FOREIGN KEY (`parent_id`) REFERENCES `accounting_accounts` (`id`) ON DELETE SET NULL;

ALTER TABLE `accounting_documents`
  ADD CONSTRAINT `fk_accounting_docs_agency` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE;

ALTER TABLE `accounting_document_lines`
  ADD CONSTRAINT `fk_journal_account` FOREIGN KEY (`account_id`) REFERENCES `accounting_accounts` (`id`),
  ADD CONSTRAINT `fk_journal_document` FOREIGN KEY (`document_id`) REFERENCES `accounting_documents` (`id`) ON DELETE CASCADE;

ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_activity_agency` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `agency_school_catalog`
  ADD CONSTRAINT `fk_asc_agency` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_asc_catalog` FOREIGN KEY (`catalog_school_id`) REFERENCES `school_catalog` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_asc_school` FOREIGN KEY (`linked_school_id`) REFERENCES `schools` (`id`) ON DELETE SET NULL;

ALTER TABLE `agency_school_tariffs`
  ADD CONSTRAINT `fk_tariff_agency` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE;

ALTER TABLE `agency_settings`
  ADD CONSTRAINT `fk_agency_settings_agency` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE;

ALTER TABLE `agency_users`
  ADD CONSTRAINT `fk_agency_users_agency` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_agency_users_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `ai_conversations`
  ADD CONSTRAINT `fk_ai_conv_agency` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ai_conv_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `ai_messages`
  ADD CONSTRAINT `fk_ai_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `ai_conversations` (`id`) ON DELETE CASCADE;

ALTER TABLE `api_idempotency_keys`
  ADD CONSTRAINT `fk_idempotency_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `cheque_events`
  ADD CONSTRAINT `fk_cheque_event_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_cheque_event_receipt` FOREIGN KEY (`cheque_id`) REFERENCES `cheque_receipts` (`id`);

ALTER TABLE `cheque_receipts`
  ADD CONSTRAINT `fk_cheque_account` FOREIGN KEY (`account_id`) REFERENCES `accounting_accounts` (`id`),
  ADD CONSTRAINT `fk_cheque_agency` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`),
  ADD CONSTRAINT `fk_cheque_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

ALTER TABLE `contracts`
  ADD CONSTRAINT `fk_contracts_agency` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE;

ALTER TABLE `drivers`
  ADD CONSTRAINT `fk_drivers_agency` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_drivers_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL;

ALTER TABLE `driver_changes`
  ADD CONSTRAINT `fk_driver_changes_agency` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_driver_changes_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;

ALTER TABLE `driver_contracts`
  ADD CONSTRAINT `fk_driver_contracts_agency` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_driver_contracts_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_driver_contracts_driver` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE CASCADE;

ALTER TABLE `driver_documents`
  ADD CONSTRAINT `fk_driver_docs_driver` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE CASCADE;

ALTER TABLE `driver_payments`
  ADD CONSTRAINT `fk_driver_payments_agency` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_driver_payments_driver` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE CASCADE;

ALTER TABLE `driver_ratings`
  ADD CONSTRAINT `driver_ratings_ibfk_1` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`),
  ADD CONSTRAINT `driver_ratings_ibfk_2` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`);

ALTER TABLE `driver_work`
  ADD CONSTRAINT `fk_driver_work_agency` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_driver_work_driver` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_driver_work_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL;

ALTER TABLE `files`
  ADD CONSTRAINT `fk_files_agency` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE;

ALTER TABLE `holidays`
  ADD CONSTRAINT `fk_holidays_agency` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE;

ALTER TABLE `invoices`
  ADD CONSTRAINT `fk_invoices_agency` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE;

ALTER TABLE `ledger_entries`
  ADD CONSTRAINT `fk_ledger_company_account` FOREIGN KEY (`company_account_id`) REFERENCES `accounting_accounts` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `ledger_entries_ibfk_1` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`),
  ADD CONSTRAINT `ledger_entries_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

ALTER TABLE `messages`
  ADD CONSTRAINT `fk_messages_agency` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE;

ALTER TABLE `packs`
  ADD CONSTRAINT `fk_packs_agency` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE;

ALTER TABLE `parent_reports`
  ADD CONSTRAINT `parent_reports_ibfk_1` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`),
  ADD CONSTRAINT `parent_reports_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`);

ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_agency` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL;

ALTER TABLE `payment_intents`
  ADD CONSTRAINT `payment_intents_ibfk_1` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`),
  ADD CONSTRAINT `payment_intents_ibfk_2` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`),
  ADD CONSTRAINT `payment_intents_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `payment_intents_ibfk_4` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`);

ALTER TABLE `payroll_documents`
  ADD CONSTRAINT `payroll_documents_ibfk_1` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`),
  ADD CONSTRAINT `payroll_documents_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

ALTER TABLE `payroll_document_lines`
  ADD CONSTRAINT `payroll_document_lines_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `payroll_documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payroll_document_lines_ibfk_2` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`);

ALTER TABLE `role_permissions`
  ADD CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

ALTER TABLE `schools`
  ADD CONSTRAINT `fk_schools_agency` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE;

ALTER TABLE `school_charges`
  ADD CONSTRAINT `fk_school_charges_agency` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_school_charges_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

ALTER TABLE `school_monthly_statements`
  ADD CONSTRAINT `fk_school_statement_agency` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE;

ALTER TABLE `school_service_import_fees`
  ADD CONSTRAINT `fk_school_import_driver` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE SET NULL;

ALTER TABLE `services`
  ADD CONSTRAINT `fk_services_agency` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_services_driver` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_services_pack` FOREIGN KEY (`pack_id`) REFERENCES `packs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_services_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE SET NULL;

ALTER TABLE `service_charges`
  ADD CONSTRAINT `fk_service_charges_agency` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_service_charges_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;

ALTER TABLE `service_locations`
  ADD CONSTRAINT `fk_service_locations_agency` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_service_locations_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;

ALTER TABLE `service_schools`
  ADD CONSTRAINT `service_schools_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `service_schools_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`);

ALTER TABLE `service_students`
  ADD CONSTRAINT `fk_service_students_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_service_students_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_agency` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_students_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`);

ALTER TABLE `student_contracts`
  ADD CONSTRAINT `fk_student_contracts_agency` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_student_contracts_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_student_contracts_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

ALTER TABLE `user_roles`
  ADD CONSTRAINT `fk_user_roles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user_roles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `user_sessions`
  ADD CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `vehicles`
  ADD CONSTRAINT `fk_vehicles_agency` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE;

ALTER TABLE `wallets`
  ADD CONSTRAINT `fk_wallets_agency` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE;
