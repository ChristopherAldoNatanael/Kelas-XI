-- PetHeal schema snapshot
-- Database: pet_heal
-- Generated: 2026-11-26T12:07:09+00:00
-- WARNING: schema only, no row data.

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table: app_settings
-- ----------------------------
CREATE TABLE `app_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `app_settings_key_unique` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: audit_logs
-- ----------------------------
CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model_id` bigint unsigned DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `clinic_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_logs_user_id_foreign` (`user_id`),
  KEY `audit_logs_model_type_model_id_index` (`model_type`,`model_id`),
  KEY `audit_logs_action_index` (`action`),
  KEY `audit_logs_clinic_id_foreign` (`clinic_id`),
  CONSTRAINT `audit_logs_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE SET NULL,
  CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=493 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: booking_refunds
-- ----------------------------
CREATE TABLE `booking_refunds` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint unsigned NOT NULL,
  `clinic_id` bigint unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'transfer',
  `reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_refund_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proof_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `recorded_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `booking_refunds_recorded_by_foreign` (`recorded_by`),
  KEY `booking_refunds_booking_id_created_at_index` (`booking_id`,`created_at`),
  KEY `booking_refunds_clinic_id_created_at_index` (`clinic_id`,`created_at`),
  CONSTRAINT `booking_refunds_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `booking_refunds_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `booking_refunds_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: bookings
-- ----------------------------
CREATE TABLE `bookings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `pet_id` bigint unsigned NOT NULL,
  `doctor_id` bigint unsigned NOT NULL,
  `booking_date` date NOT NULL,
  `booking_time` time NOT NULL,
  `status` enum('pending','confirmed','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `service_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medical_checkup',
  `clinic_id` bigint unsigned NOT NULL,
  `payment_method` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_type` enum('dp','full') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dp_amount` decimal(10,2) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `paid_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `remaining_amount` decimal(10,2) DEFAULT NULL COMMENT 'Amount still to be paid after DP',
  `payment_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `payment_date` timestamp NULL DEFAULT NULL,
  `cancellation_reason` text COLLATE utf8mb4_unicode_ci,
  `queue_number` int unsigned DEFAULT NULL,
  `queue_date` date DEFAULT NULL,
  `queue_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checked_in_at` timestamp NULL DEFAULT NULL,
  `called_at` timestamp NULL DEFAULT NULL,
  `queue_finished_at` timestamp NULL DEFAULT NULL,
  `queue_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `service_id` bigint unsigned DEFAULT NULL,
  `medical_record_id` bigint unsigned DEFAULT NULL,
  `last_reminder_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bookings_queue_unique` (`clinic_id`,`queue_date`,`queue_number`),
  KEY `bookings_pet_id_foreign` (`pet_id`),
  KEY `idx_doctor_date_time` (`doctor_id`,`booking_date`,`booking_time`),
  KEY `idx_user_status` (`user_id`,`status`),
  KEY `idx_bookings_user_id` (`user_id`),
  KEY `idx_bookings_doctor_id` (`doctor_id`),
  KEY `idx_bookings_date` (`booking_date`),
  KEY `idx_bookings_status` (`status`),
  KEY `idx_bookings_date_status` (`booking_date`,`status`),
  KEY `idx_bookings_payment_status` (`payment_status`),
  KEY `bookings_service_id_foreign` (`service_id`),
  KEY `idx_bookings_payment_status_updated` (`payment_status`,`updated_at`),
  KEY `idx_bookings_payment_type_updated` (`payment_type`,`updated_at`),
  KEY `idx_bookings_payment_method_updated` (`payment_method`,`updated_at`),
  KEY `bookings_clinic_id_status_booking_date_index` (`clinic_id`,`status`,`booking_date`),
  KEY `bookings_clinic_id_doctor_id_booking_date_booking_time_index` (`clinic_id`,`doctor_id`,`booking_date`,`booking_time`),
  KEY `bookings_queue_board` (`clinic_id`,`queue_date`,`queue_status`),
  KEY `bookings_medical_record_id_foreign` (`medical_record_id`),
  CONSTRAINT `bookings_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bookings_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bookings_medical_record_id_foreign` FOREIGN KEY (`medical_record_id`) REFERENCES `medical_records` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bookings_pet_id_foreign` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bookings_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bookings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=691 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: cache
-- ----------------------------
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: cache_locks
-- ----------------------------
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: clinic_join_requests
-- ----------------------------
CREATE TABLE `clinic_join_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'join',
  `clinic_id` bigint unsigned DEFAULT NULL,
  `requested_clinic_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requested_clinic_slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requested_clinic_address` text COLLATE utf8mb4_unicode_ci,
  `requested_clinic_phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requested_clinic_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requested_primary_color` char(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requested_description` text COLLATE utf8mb4_unicode_ci,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `clinic_join_requests_reviewed_by_foreign` (`reviewed_by`),
  KEY `clinic_join_requests_clinic_id_status_index` (`clinic_id`,`status`),
  KEY `clinic_join_requests_status_index` (`status`),
  KEY `clinic_join_requests_type_status_index` (`type`,`status`),
  KEY `clinic_join_requests_slug_index` (`requested_clinic_slug`),
  CONSTRAINT `clinic_join_requests_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE SET NULL,
  CONSTRAINT `clinic_join_requests_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: clinic_merchant_configs
-- ----------------------------
CREATE TABLE `clinic_merchant_configs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `clinic_id` bigint unsigned NOT NULL,
  `provider` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'midtrans',
  `environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sandbox',
  `merchant_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `partner_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `onboarding_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_started',
  `is_online_payment_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `commission_percent` decimal(5,2) NOT NULL DEFAULT '0.00',
  `verified_at` timestamp NULL DEFAULT NULL,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clinic_merchant_configs_clinic_id_unique` (`clinic_id`),
  KEY `clinic_merchant_configs_environment_onboarding_status_index` (`environment`,`onboarding_status`),
  CONSTRAINT `clinic_merchant_configs_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: clinic_payment_accounts
-- ----------------------------
CREATE TABLE `clinic_payment_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `clinic_id` bigint unsigned NOT NULL,
  `provider` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'midtrans',
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_configured',
  `active_environment` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_credential_version_id` bigint unsigned DEFAULT NULL,
  `last_validated_at` timestamp NULL DEFAULT NULL,
  `last_validation_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `production_approved_at` timestamp NULL DEFAULT NULL,
  `production_approved_by` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clinic_payment_accounts_clinic_id_unique` (`clinic_id`),
  KEY `clinic_payment_accounts_production_approved_by_foreign` (`production_approved_by`),
  KEY `clinic_payment_accounts_created_by_foreign` (`created_by`),
  KEY `clinic_payment_accounts_updated_by_foreign` (`updated_by`),
  KEY `clinic_payment_accounts_provider_status_index` (`provider`,`status`),
  KEY `clinic_payment_accounts_current_version_fk` (`current_credential_version_id`),
  CONSTRAINT `clinic_payment_accounts_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `clinic_payment_accounts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `clinic_payment_accounts_current_version_fk` FOREIGN KEY (`current_credential_version_id`) REFERENCES `payment_credential_versions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `clinic_payment_accounts_production_approved_by_foreign` FOREIGN KEY (`production_approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `clinic_payment_accounts_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: clinic_products
-- ----------------------------
CREATE TABLE `clinic_products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `clinic_id` bigint unsigned NOT NULL,
  `product_category_id` bigint unsigned NOT NULL,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `brand` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clinic_products_clinic_id_slug_unique` (`clinic_id`,`slug`),
  KEY `clinic_products_clinic_id_is_active_index` (`clinic_id`,`is_active`),
  KEY `clinic_products_product_category_id_is_active_index` (`product_category_id`,`is_active`),
  KEY `clinic_products_name_index` (`name`),
  CONSTRAINT `clinic_products_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `clinic_products_product_category_id_foreign` FOREIGN KEY (`product_category_id`) REFERENCES `product_categories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=585 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: clinics
-- ----------------------------
CREATE TABLE `clinics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `license_number` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_expiry` date DEFAULT NULL,
  `responsible_doctor_id` bigint unsigned DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `district` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo_path` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `primary_color` char(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#18C964',
  `description` text COLLATE utf8mb4_unicode_ci,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `rating` decimal(3,2) DEFAULT NULL,
  `reviews_count` int unsigned NOT NULL DEFAULT '0',
  `opening_time` time DEFAULT NULL,
  `closing_time` time DEFAULT NULL,
  `operating_days` json DEFAULT NULL COMMENT 'Array of days: ["monday","tuesday",...] or ["all"]',
  `website` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `featured_image_path` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `pickup_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clinics_slug_unique` (`slug`),
  KEY `clinics_is_active_index` (`is_active`),
  KEY `clinics_responsible_doctor_id_foreign` (`responsible_doctor_id`),
  CONSTRAINT `clinics_responsible_doctor_id_foreign` FOREIGN KEY (`responsible_doctor_id`) REFERENCES `doctors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2578 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: consents
-- ----------------------------
CREATE TABLE `consents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `purpose` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `policy_version` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `granted_at` timestamp NULL DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `consents_user_purpose_index` (`user_id`,`purpose`,`revoked_at`),
  CONSTRAINT `consents_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: device_tokens
-- ----------------------------
CREATE TABLE `device_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_device_tokens_token` (`token`),
  KEY `device_tokens_token_index` (`token`),
  KEY `idx_device_tokens_user_id` (`user_id`),
  KEY `idx_device_tokens_token` (`token`),
  KEY `idx_device_tokens_user_updated` (`user_id`,`updated_at`),
  KEY `idx_device_tokens_user_status` (`user_id`,`status`),
  CONSTRAINT `device_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: doctor_reviews
-- ----------------------------
CREATE TABLE `doctor_reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `doctor_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `booking_id` bigint unsigned NOT NULL,
  `rating` tinyint unsigned NOT NULL,
  `review` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `doctor_reviews_booking_id_unique` (`booking_id`),
  KEY `doctor_reviews_user_id_foreign` (`user_id`),
  KEY `doctor_reviews_doctor_id_rating_index` (`doctor_id`,`rating`),
  CONSTRAINT `doctor_reviews_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `doctor_reviews_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `doctor_reviews_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: doctor_schedule_exceptions
-- ----------------------------
CREATE TABLE `doctor_schedule_exceptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `doctor_id` bigint unsigned NOT NULL,
  `clinic_id` bigint unsigned NOT NULL,
  `date` date NOT NULL,
  `is_closed` tinyint(1) NOT NULL DEFAULT '1',
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `reason` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `doctor_schedule_exceptions_doctor_id_date_unique` (`doctor_id`,`date`),
  KEY `doctor_schedule_exceptions_created_by_foreign` (`created_by`),
  KEY `doctor_schedule_exceptions_clinic_id_date_index` (`clinic_id`,`date`),
  CONSTRAINT `doctor_schedule_exceptions_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `doctor_schedule_exceptions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `doctor_schedule_exceptions_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: doctors
-- ----------------------------
CREATE TABLE `doctors` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `specialization` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `str_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `str_expiry` date DEFAULT NULL,
  `sip_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sip_expiry` date DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signature_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `available_days` json NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `clinic_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `invite_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invite_token_expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `doctors_user_id_unique` (`user_id`),
  UNIQUE KEY `doctors_invite_token_unique` (`invite_token`),
  KEY `idx_doctors_is_active` (`is_active`),
  KEY `doctors_clinic_id_is_active_index` (`clinic_id`,`is_active`),
  CONSTRAINT `doctors_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `doctors_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1974 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: failed_jobs
-- ----------------------------
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: job_batches
-- ----------------------------
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: jobs
-- ----------------------------
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: lab_results
-- ----------------------------
CREATE TABLE `lab_results` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `medical_record_id` bigint unsigned NOT NULL,
  `test_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parameter` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_range` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `flag` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `performed_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lab_results_record_test_index` (`medical_record_id`,`test_name`),
  CONSTRAINT `lab_results_medical_record_id_foreign` FOREIGN KEY (`medical_record_id`) REFERENCES `medical_records` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: medical_record_attachments
-- ----------------------------
CREATE TABLE `medical_record_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `medical_record_id` bigint unsigned NOT NULL,
  `path` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `caption` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size_bytes` int unsigned DEFAULT NULL,
  `sha256` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `medical_record_attachments_medical_record_id_foreign` (`medical_record_id`),
  KEY `medical_record_attachments_uploaded_by_foreign` (`uploaded_by`),
  CONSTRAINT `medical_record_attachments_medical_record_id_foreign` FOREIGN KEY (`medical_record_id`) REFERENCES `medical_records` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medical_record_attachments_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: medical_record_revisions
-- ----------------------------
CREATE TABLE `medical_record_revisions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `medical_record_id` bigint unsigned NOT NULL,
  `changed_by` bigint unsigned DEFAULT NULL,
  `action` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `before` json DEFAULT NULL,
  `after` json DEFAULT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `medical_record_revisions_changed_by_foreign` (`changed_by`),
  KEY `medical_record_revisions_record_created_index` (`medical_record_id`,`created_at`),
  CONSTRAINT `medical_record_revisions_changed_by_foreign` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `medical_record_revisions_medical_record_id_foreign` FOREIGN KEY (`medical_record_id`) REFERENCES `medical_records` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: medical_record_vitals
-- ----------------------------
CREATE TABLE `medical_record_vitals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `medical_record_id` bigint unsigned NOT NULL,
  `temperature_c` decimal(4,1) DEFAULT NULL,
  `heart_rate` smallint unsigned DEFAULT NULL,
  `respiratory_rate` smallint unsigned DEFAULT NULL,
  `crt_seconds` decimal(3,1) DEFAULT NULL,
  `mucosa` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hydration` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bcs` tinyint unsigned DEFAULT NULL,
  `pain_score` tinyint unsigned DEFAULT NULL,
  `weight_kg` decimal(6,2) DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `measured_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `medical_record_vitals_medical_record_id_unique` (`medical_record_id`),
  CONSTRAINT `medical_record_vitals_medical_record_id_foreign` FOREIGN KEY (`medical_record_id`) REFERENCES `medical_records` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: medical_records
-- ----------------------------
CREATE TABLE `medical_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint unsigned NOT NULL,
  `pet_id` bigint unsigned NOT NULL,
  `doctor_id` bigint unsigned NOT NULL,
  `subjective` text COLLATE utf8mb4_unicode_ci,
  `objective` text COLLATE utf8mb4_unicode_ci,
  `assessment` text COLLATE utf8mb4_unicode_ci,
  `plan` text COLLATE utf8mb4_unicode_ci,
  `diagnosis` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `treatment` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `medicine` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `treatment_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `medicine_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_medical_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `extra_payment_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `extra_payment_paid_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `extra_payment_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_required',
  `extra_payment_order_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `extra_payment_date` timestamp NULL DEFAULT NULL,
  `clinic_id` bigint unsigned DEFAULT NULL,
  `status` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'final',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `finalized_at` timestamp NULL DEFAULT NULL,
  `finalized_by` bigint unsigned DEFAULT NULL,
  `voided_at` timestamp NULL DEFAULT NULL,
  `voided_by` bigint unsigned DEFAULT NULL,
  `void_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `archived_at` timestamp NULL DEFAULT NULL,
  `outcome` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cause_of_death` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `euthanasia_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `disposal_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `next_visit_date` date DEFAULT NULL,
  `next_visit_time` time DEFAULT NULL,
  `reminder_sent` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `medical_records_booking_id_unique` (`booking_id`),
  UNIQUE KEY `medical_records_extra_payment_order_id_unique` (`extra_payment_order_id`),
  KEY `medical_records_doctor_id_foreign` (`doctor_id`),
  KEY `idx_next_visit_date` (`next_visit_date`),
  KEY `idx_medical_records_pet_id` (`pet_id`),
  KEY `idx_medical_records_booking_id` (`booking_id`),
  KEY `idx_medical_records_extra_payment_status` (`extra_payment_status`,`created_at`),
  KEY `idx_medical_records_clinic_id` (`clinic_id`),
  KEY `medical_records_created_by_foreign` (`created_by`),
  KEY `medical_records_updated_by_foreign` (`updated_by`),
  KEY `medical_records_finalized_by_foreign` (`finalized_by`),
  KEY `medical_records_voided_by_foreign` (`voided_by`),
  KEY `medical_records_pet_created_index` (`pet_id`,`created_at`),
  KEY `medical_records_status_created_index` (`status`,`created_at`),
  CONSTRAINT `medical_records_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medical_records_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE SET NULL,
  CONSTRAINT `medical_records_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `medical_records_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medical_records_finalized_by_foreign` FOREIGN KEY (`finalized_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `medical_records_pet_id_foreign` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medical_records_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `medical_records_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=151 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: medicine_batches
-- ----------------------------
CREATE TABLE `medicine_batches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `clinic_id` bigint unsigned NOT NULL,
  `medicine_id` bigint unsigned NOT NULL,
  `batch_number` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `stock` decimal(12,2) NOT NULL DEFAULT '0.00',
  `quarantined_stock` decimal(12,2) NOT NULL DEFAULT '0.00',
  `received_at` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `medicine_batches_medicine_id_batch_number_unique` (`medicine_id`,`batch_number`),
  KEY `medicine_batches_clinic_id_expiry_date_index` (`clinic_id`,`expiry_date`),
  KEY `medicine_batches_medicine_id_expiry_date_index` (`medicine_id`,`expiry_date`),
  CONSTRAINT `medicine_batches_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medicine_batches_medicine_id_foreign` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=76 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: medicine_stock_movements
-- ----------------------------
CREATE TABLE `medicine_stock_movements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `transaction_id` bigint unsigned DEFAULT NULL,
  `medicine_id` bigint unsigned NOT NULL,
  `batch_id` bigint unsigned DEFAULT NULL,
  `clinic_id` bigint unsigned NOT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stock_state` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available',
  `quantity` decimal(12,2) NOT NULL,
  `stock_after` decimal(12,2) NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `medical_record_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `medicine_stock_movements_created_by_foreign` (`created_by`),
  KEY `medicine_stock_movements_medicine_id_created_at_index` (`medicine_id`,`created_at`),
  KEY `medicine_stock_movements_clinic_id_created_at_index` (`clinic_id`,`created_at`),
  KEY `medicine_stock_movements_medical_record_id_index` (`medical_record_id`),
  KEY `medicine_stock_movements_transaction_id_index` (`transaction_id`),
  KEY `medicine_stock_movements_batch_id_created_at_index` (`batch_id`,`created_at`),
  CONSTRAINT `medicine_stock_movements_batch_id_foreign` FOREIGN KEY (`batch_id`) REFERENCES `medicine_batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `medicine_stock_movements_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medicine_stock_movements_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `medicine_stock_movements_medical_record_id_foreign` FOREIGN KEY (`medical_record_id`) REFERENCES `medical_records` (`id`) ON DELETE SET NULL,
  CONSTRAINT `medicine_stock_movements_medicine_id_foreign` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medicine_stock_movements_transaction_id_foreign` FOREIGN KEY (`transaction_id`) REFERENCES `medicine_stock_transactions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=107 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: medicine_stock_transactions
-- ----------------------------
CREATE TABLE `medicine_stock_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `clinic_id` bigint unsigned NOT NULL,
  `medicine_id` bigint unsigned NOT NULL,
  `batch_id` bigint unsigned DEFAULT NULL,
  `type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `quantity` decimal(12,2) NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `destination` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `supplier_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `return_date` date DEFAULT NULL,
  `document_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destruction_date` date DEFAULT NULL,
  `destruction_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `witness_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `minutes_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quarantine_location` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_quarantine` tinyint(1) NOT NULL DEFAULT '0',
  `source_transaction_id` bigint unsigned DEFAULT NULL,
  `direction` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `processed_by` bigint unsigned DEFAULT NULL,
  `completed_by` bigint unsigned DEFAULT NULL,
  `cancelled_by` bigint unsigned DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancel_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `medicine_stock_transactions_reference_unique` (`reference`),
  KEY `medicine_stock_transactions_source_transaction_id_foreign` (`source_transaction_id`),
  KEY `medicine_stock_transactions_created_by_foreign` (`created_by`),
  KEY `medicine_stock_transactions_processed_by_foreign` (`processed_by`),
  KEY `medicine_stock_transactions_completed_by_foreign` (`completed_by`),
  KEY `medicine_stock_transactions_cancelled_by_foreign` (`cancelled_by`),
  KEY `medicine_stock_transactions_clinic_id_status_index` (`clinic_id`,`status`),
  KEY `medicine_stock_transactions_clinic_id_type_index` (`clinic_id`,`type`),
  KEY `medicine_stock_transactions_medicine_id_created_at_index` (`medicine_id`,`created_at`),
  KEY `medicine_stock_transactions_batch_id_status_index` (`batch_id`,`status`),
  CONSTRAINT `medicine_stock_transactions_batch_id_foreign` FOREIGN KEY (`batch_id`) REFERENCES `medicine_batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `medicine_stock_transactions_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `medicine_stock_transactions_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medicine_stock_transactions_completed_by_foreign` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `medicine_stock_transactions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `medicine_stock_transactions_medicine_id_foreign` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medicine_stock_transactions_processed_by_foreign` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `medicine_stock_transactions_source_transaction_id_foreign` FOREIGN KEY (`source_transaction_id`) REFERENCES `medicine_stock_transactions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: medicines
-- ----------------------------
CREATE TABLE `medicines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `clinic_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tablet',
  `form` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `drug_class` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'bebas_terbatas',
  `is_antibiotic` tinyint(1) NOT NULL DEFAULT '0',
  `stock` decimal(12,2) NOT NULL DEFAULT '0.00',
  `quarantined_stock` decimal(12,2) NOT NULL DEFAULT '0.00',
  `min_stock` decimal(12,2) NOT NULL DEFAULT '0.00',
  `price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `expiry_date` date DEFAULT NULL,
  `manufacturer` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `disposed_at` timestamp NULL DEFAULT NULL,
  `disposal_method` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `disposal_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `disposed_quantity` decimal(12,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `medicines_clinic_id_name_unique` (`clinic_id`,`name`),
  KEY `medicines_clinic_id_is_active_index` (`clinic_id`,`is_active`),
  KEY `medicines_clinic_expiry_index` (`clinic_id`,`expiry_date`),
  KEY `medicines_clinic_disposed_index` (`clinic_id`,`disposed_at`),
  KEY `medicines_clinic_drug_class_index` (`clinic_id`,`drug_class`),
  CONSTRAINT `medicines_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=98 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: migrations
-- ----------------------------
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=101 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: notifications
-- ----------------------------
CREATE TABLE `notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `data` json DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `clinic_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `notifications_type_index` (`type`),
  KEY `notifications_read_at_index` (`read_at`),
  KEY `idx_notifications_user_read` (`user_id`,`read_at`),
  KEY `notifications_clinic_id_foreign` (`clinic_id`),
  CONSTRAINT `notifications_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE SET NULL,
  CONSTRAINT `notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=232 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: otp_codes
-- ----------------------------
CREATE TABLE `otp_codes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `purpose` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` json DEFAULT NULL,
  `attempts` tinyint unsigned NOT NULL DEFAULT '0',
  `expires_at` timestamp NOT NULL,
  `consumed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `otp_codes_email_purpose_unique` (`email`,`purpose`),
  KEY `otp_codes_expires_at_index` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: password_reset_tokens
-- ----------------------------
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: payment_credential_versions
-- ----------------------------
CREATE TABLE `payment_credential_versions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_account_id` bigint unsigned NOT NULL,
  `environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `merchant_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_key` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `server_key` text COLLATE utf8mb4_unicode_ci,
  `version` int unsigned NOT NULL DEFAULT '1',
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `validation_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_validated_at` timestamp NULL DEFAULT NULL,
  `activated_at` timestamp NULL DEFAULT NULL,
  `retired_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_credential_versions_unique` (`payment_account_id`,`environment`,`version`),
  KEY `payment_credential_versions_created_by_foreign` (`created_by`),
  KEY `payment_credential_versions_updated_by_foreign` (`updated_by`),
  KEY `payment_credential_versions_lookup` (`payment_account_id`,`environment`,`status`),
  CONSTRAINT `payment_credential_versions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payment_credential_versions_payment_account_id_foreign` FOREIGN KEY (`payment_account_id`) REFERENCES `clinic_payment_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_credential_versions_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: payment_events
-- ----------------------------
CREATE TABLE `payment_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint unsigned NOT NULL,
  `medical_record_id` bigint unsigned DEFAULT NULL,
  `clinic_id` bigint unsigned DEFAULT NULL,
  `order_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `transaction_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gross_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_events_order_id_unique` (`order_id`),
  KEY `idx_payment_events_booking_created` (`booking_id`,`created_at`),
  KEY `idx_payment_events_medical_record_created` (`medical_record_id`,`created_at`),
  KEY `idx_payment_events_clinic_id` (`clinic_id`),
  CONSTRAINT `payment_events_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_events_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payment_events_medical_record_id_foreign` FOREIGN KEY (`medical_record_id`) REFERENCES `medical_records` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=82 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: payment_methods
-- ----------------------------
CREATE TABLE `payment_methods` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('qris','bank','ewallet') COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `icon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=357 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: payment_notifications
-- ----------------------------
CREATE TABLE `payment_notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_id` bigint unsigned DEFAULT NULL,
  `provider` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'midtrans',
  `provider_order_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signature_valid` tinyint(1) NOT NULL DEFAULT '0',
  `transaction_status` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gross_amount` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` json NOT NULL,
  `processed` tinyint(1) NOT NULL DEFAULT '0',
  `processing_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_notifications_payment_id_foreign` (`payment_id`),
  KEY `payment_notifications_provider_order_id_index` (`provider_order_id`),
  KEY `payment_notifications_processed_created_at_index` (`processed`,`created_at`),
  CONSTRAINT `payment_notifications_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: payment_refunds
-- ----------------------------
CREATE TABLE `payment_refunds` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_id` bigint unsigned NOT NULL,
  `refund_code` varchar(48) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` bigint unsigned NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'requested',
  `provider_refund_id` varchar(96) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requested_by` bigint unsigned DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_refunds_refund_code_unique` (`refund_code`),
  KEY `payment_refunds_requested_by_foreign` (`requested_by`),
  KEY `payment_refunds_payment_id_status_index` (`payment_id`,`status`),
  CONSTRAINT `payment_refunds_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_refunds_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: payment_routing_logs
-- ----------------------------
CREATE TABLE `payment_routing_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `clinic_id` bigint unsigned DEFAULT NULL,
  `payment_id` bigint unsigned DEFAULT NULL,
  `payable_type` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payable_id` bigint unsigned DEFAULT NULL,
  `environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `routing_source` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_account_id` bigint unsigned DEFAULT NULL,
  `credential_version_id` bigint unsigned DEFAULT NULL,
  `merchant_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `outcome` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_routing_logs_payment_id_foreign` (`payment_id`),
  KEY `payment_routing_logs_payment_account_id_foreign` (`payment_account_id`),
  KEY `payment_routing_logs_credential_version_id_foreign` (`credential_version_id`),
  KEY `payment_routing_logs_clinic_id_created_at_index` (`clinic_id`,`created_at`),
  CONSTRAINT `payment_routing_logs_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payment_routing_logs_credential_version_id_foreign` FOREIGN KEY (`credential_version_id`) REFERENCES `payment_credential_versions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payment_routing_logs_payment_account_id_foreign` FOREIGN KEY (`payment_account_id`) REFERENCES `clinic_payment_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payment_routing_logs_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: payment_status_histories
-- ----------------------------
CREATE TABLE `payment_status_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_id` bigint unsigned NOT NULL,
  `from_status` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_status` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'webhook',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_status_histories_actor_id_foreign` (`actor_id`),
  KEY `payment_status_histories_payment_id_created_at_index` (`payment_id`,`created_at`),
  CONSTRAINT `payment_status_histories_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payment_status_histories_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: payments
-- ----------------------------
CREATE TABLE `payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_code` varchar(48) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'midtrans',
  `payment_type` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `clinic_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `payable_type` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payable_id` bigint unsigned NOT NULL,
  `provider_order_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_transaction_id` varchar(96) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `partner_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_account_id` bigint unsigned DEFAULT NULL,
  `payment_credential_version_id` bigint unsigned DEFAULT NULL,
  `routing_source` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `merchant_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sandbox',
  `gross_amount` bigint unsigned NOT NULL,
  `amount_received` bigint unsigned DEFAULT NULL COMMENT 'Nominal uang yang diterima petugas (offline)',
  `change_amount` bigint unsigned DEFAULT NULL COMMENT 'Uang kembalian = amount_received - gross_amount',
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'IDR',
  `payment_method` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `provider_status` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `snap_token` text COLLATE utf8mb4_unicode_ci,
  `redirect_url` text COLLATE utf8mb4_unicode_ci,
  `expires_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `refunded_amount` bigint unsigned NOT NULL DEFAULT '0',
  `split_settings` json DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `last_reconciled_at` timestamp NULL DEFAULT NULL,
  `reconcile_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_payment_code_unique` (`payment_code`),
  UNIQUE KEY `payments_provider_order_id_unique` (`provider_order_id`),
  KEY `payments_user_id_foreign` (`user_id`),
  KEY `payments_clinic_id_status_index` (`clinic_id`,`status`),
  KEY `payments_payment_type_status_index` (`payment_type`,`status`),
  KEY `payments_payable_type_index` (`payable_type`),
  KEY `payments_payable_index` (`payable_type`,`payable_id`),
  KEY `payments_created_at_index` (`created_at`),
  KEY `payments_payment_account_id_foreign` (`payment_account_id`),
  KEY `payments_payment_credential_version_id_foreign` (`payment_credential_version_id`),
  KEY `payments_routing_source_index` (`routing_source`),
  CONSTRAINT `payments_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payments_payment_account_id_foreign` FOREIGN KEY (`payment_account_id`) REFERENCES `clinic_payment_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payments_payment_credential_version_id_foreign` FOREIGN KEY (`payment_credential_version_id`) REFERENCES `payment_credential_versions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: personal_access_tokens
-- ----------------------------
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=1067 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: pet_allergies
-- ----------------------------
CREATE TABLE `pet_allergies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pet_id` bigint unsigned NOT NULL,
  `allergen` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reaction` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `severity` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sedang',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `noted_by` bigint unsigned DEFAULT NULL,
  `noted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pet_allergies_noted_by_foreign` (`noted_by`),
  KEY `pet_allergies_pet_active_index` (`pet_id`,`is_active`),
  CONSTRAINT `pet_allergies_noted_by_foreign` FOREIGN KEY (`noted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pet_allergies_pet_id_foreign` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: pets
-- ----------------------------
CREATE TABLE `pets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `microchip_number` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `species` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `species_code` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `breed` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `markings` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `age` int DEFAULT NULL,
  `weight` decimal(8,2) DEFAULT NULL,
  `gender` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `neutered_at` date DEFAULT NULL,
  `blood_type` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pets_microchip_number_unique` (`microchip_number`),
  KEY `idx_pets_user_id` (`user_id`),
  KEY `idx_pets_name` (`name`),
  CONSTRAINT `pets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1694 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: prescription_items
-- ----------------------------
CREATE TABLE `prescription_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `medical_record_id` bigint unsigned NOT NULL,
  `medicine_id` bigint unsigned DEFAULT NULL,
  `medicine_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dose` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `route` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `frequency` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duration_days` smallint unsigned DEFAULT NULL,
  `instruction` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `drug_class` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_antibiotic` tinyint(1) NOT NULL DEFAULT '0',
  `dispensed_at` timestamp NULL DEFAULT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT '1.00',
  `unit` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `batch_number` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `unit_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prescription_items_medicine_id_foreign` (`medicine_id`),
  KEY `prescription_items_medical_record_id_index` (`medical_record_id`),
  CONSTRAINT `prescription_items_medical_record_id_foreign` FOREIGN KEY (`medical_record_id`) REFERENCES `medical_records` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prescription_items_medicine_id_foreign` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: product_categories
-- ----------------------------
CREATE TABLE `product_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_categories_slug_unique` (`slug`),
  KEY `product_categories_is_active_sort_order_index` (`is_active`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: product_order_items
-- ----------------------------
CREATE TABLE `product_order_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_order_id` bigint unsigned NOT NULL,
  `clinic_product_id` bigint unsigned DEFAULT NULL,
  `product_variant_id` bigint unsigned DEFAULT NULL,
  `product_name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `variant_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sku` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_price` bigint unsigned NOT NULL,
  `discount_price` bigint unsigned DEFAULT NULL,
  `quantity` int unsigned NOT NULL,
  `subtotal` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_order_items_clinic_product_id_foreign` (`clinic_product_id`),
  KEY `product_order_items_product_variant_id_foreign` (`product_variant_id`),
  KEY `product_order_items_product_order_id_index` (`product_order_id`),
  CONSTRAINT `product_order_items_clinic_product_id_foreign` FOREIGN KEY (`clinic_product_id`) REFERENCES `clinic_products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `product_order_items_product_order_id_foreign` FOREIGN KEY (`product_order_id`) REFERENCES `product_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_order_items_product_variant_id_foreign` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: product_order_status_histories
-- ----------------------------
CREATE TABLE `product_order_status_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_order_id` bigint unsigned NOT NULL,
  `from_status` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'system',
  `actor_id` bigint unsigned DEFAULT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_order_status_histories_actor_id_foreign` (`actor_id`),
  KEY `product_order_status_histories_product_order_id_created_at_index` (`product_order_id`,`created_at`),
  CONSTRAINT `product_order_status_histories_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `product_order_status_histories_product_order_id_foreign` FOREIGN KEY (`product_order_id`) REFERENCES `product_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: product_orders
-- ----------------------------
CREATE TABLE `product_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_number` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `clinic_id` bigint unsigned NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_payment',
  `payment_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `payment_method` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtotal` bigint unsigned NOT NULL,
  `discount_total` bigint unsigned NOT NULL DEFAULT '0',
  `total_amount` bigint unsigned NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'IDR',
  `pickup_clinic_name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pickup_clinic_address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `pickup_clinic_phone` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_phone` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `stock_reserved_at` timestamp NULL DEFAULT NULL,
  `reservation_expires_at` timestamp NULL DEFAULT NULL,
  `stock_committed_at` timestamp NULL DEFAULT NULL,
  `stock_released_at` timestamp NULL DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `ready_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `picked_up_at` timestamp NULL DEFAULT NULL,
  `picked_up_by` bigint unsigned DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancellation_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_orders_order_number_unique` (`order_number`),
  KEY `product_orders_clinic_id_status_index` (`clinic_id`,`status`),
  KEY `product_orders_clinic_id_payment_status_index` (`clinic_id`,`payment_status`),
  KEY `product_orders_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `product_orders_clinic_id_created_at_index` (`clinic_id`,`created_at`),
  KEY `product_orders_picked_up_by_foreign` (`picked_up_by`),
  CONSTRAINT `product_orders_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_orders_picked_up_by_foreign` FOREIGN KEY (`picked_up_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `product_orders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: product_pickup_handovers
-- ----------------------------
CREATE TABLE `product_pickup_handovers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_order_id` bigint unsigned NOT NULL,
  `product_pickup_pass_id` bigint unsigned DEFAULT NULL,
  `clinic_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `handed_over_by` bigint unsigned DEFAULT NULL,
  `items_snapshot` json NOT NULL,
  `verification_method` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `handed_over_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_pickup_handovers_product_order_id_unique` (`product_order_id`),
  KEY `product_pickup_handovers_product_pickup_pass_id_foreign` (`product_pickup_pass_id`),
  KEY `product_pickup_handovers_clinic_id_foreign` (`clinic_id`),
  KEY `product_pickup_handovers_user_id_foreign` (`user_id`),
  KEY `product_pickup_handovers_handed_over_by_foreign` (`handed_over_by`),
  CONSTRAINT `product_pickup_handovers_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_pickup_handovers_handed_over_by_foreign` FOREIGN KEY (`handed_over_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `product_pickup_handovers_product_order_id_foreign` FOREIGN KEY (`product_order_id`) REFERENCES `product_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_pickup_handovers_product_pickup_pass_id_foreign` FOREIGN KEY (`product_pickup_pass_id`) REFERENCES `product_pickup_passes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `product_pickup_handovers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: product_pickup_passes
-- ----------------------------
CREATE TABLE `product_pickup_passes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `clinic_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `token` varchar(96) COLLATE utf8mb4_unicode_ci NOT NULL,
  `manual_code` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `used_at` timestamp NULL DEFAULT NULL,
  `used_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_pickup_passes_order_id_unique` (`order_id`),
  UNIQUE KEY `product_pickup_passes_token_unique` (`token`),
  UNIQUE KEY `product_pickup_passes_manual_code_unique` (`manual_code`),
  KEY `product_pickup_passes_user_id_foreign` (`user_id`),
  KEY `product_pickup_passes_used_by_foreign` (`used_by`),
  KEY `product_pickup_passes_clinic_id_is_active_index` (`clinic_id`,`is_active`),
  KEY `product_pickup_passes_order_id_index` (`order_id`),
  KEY `product_pickup_passes_token_index` (`token`),
  KEY `product_pickup_passes_manual_code_index` (`manual_code`),
  CONSTRAINT `product_pickup_passes_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_pickup_passes_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `product_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_pickup_passes_used_by_foreign` FOREIGN KEY (`used_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `product_pickup_passes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: product_stock_movements
-- ----------------------------
CREATE TABLE `product_stock_movements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `clinic_id` bigint unsigned NOT NULL,
  `clinic_product_id` bigint unsigned NOT NULL,
  `product_variant_id` bigint unsigned NOT NULL,
  `type` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL,
  `stock_after` int unsigned NOT NULL,
  `reserved_after` int unsigned NOT NULL DEFAULT '0',
  `reference_type` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` bigint unsigned DEFAULT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_stock_movements_product_variant_id_foreign` (`product_variant_id`),
  KEY `product_stock_movements_actor_id_foreign` (`actor_id`),
  KEY `product_stock_movements_clinic_id_type_index` (`clinic_id`,`type`),
  KEY `product_stock_movements_clinic_product_id_created_at_index` (`clinic_product_id`,`created_at`),
  KEY `product_stock_movements_reference_type_reference_id_index` (`reference_type`,`reference_id`),
  CONSTRAINT `product_stock_movements_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `product_stock_movements_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_stock_movements_clinic_product_id_foreign` FOREIGN KEY (`clinic_product_id`) REFERENCES `clinic_products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_stock_movements_product_variant_id_foreign` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=772 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: product_variants
-- ----------------------------
CREATE TABLE `product_variants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `clinic_product_id` bigint unsigned NOT NULL,
  `sku` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` bigint unsigned NOT NULL,
  `discount_price` bigint unsigned DEFAULT NULL,
  `stock_on_hand` int unsigned NOT NULL DEFAULT '0',
  `stock_reserved` int unsigned NOT NULL DEFAULT '0',
  `low_stock_threshold` smallint unsigned NOT NULL DEFAULT '5',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_variants_sku_unique` (`sku`),
  KEY `product_variants_clinic_product_id_is_active_index` (`clinic_product_id`,`is_active`),
  CONSTRAINT `product_variants_clinic_product_id_foreign` FOREIGN KEY (`clinic_product_id`) REFERENCES `clinic_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=755 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: referrals
-- ----------------------------
CREATE TABLE `referrals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `medical_record_id` bigint unsigned NOT NULL,
  `clinic_id` bigint unsigned DEFAULT NULL,
  `referred_to` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sent',
  `referred_at` timestamp NULL DEFAULT NULL,
  `returned_at` timestamp NULL DEFAULT NULL,
  `result_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `referrals_medical_record_id_foreign` (`medical_record_id`),
  KEY `referrals_clinic_id_foreign` (`clinic_id`),
  CONSTRAINT `referrals_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE SET NULL,
  CONSTRAINT `referrals_medical_record_id_foreign` FOREIGN KEY (`medical_record_id`) REFERENCES `medical_records` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: service_categories
-- ----------------------------
CREATE TABLE `service_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `icon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Material icon name',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `service_categories_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: service_requests
-- ----------------------------
CREATE TABLE `service_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `pet_id` bigint unsigned NOT NULL,
  `clinic_id` bigint unsigned NOT NULL,
  `doctor_id` bigint unsigned DEFAULT NULL,
  `medical_record_id` bigint unsigned DEFAULT NULL,
  `booking_id` bigint unsigned DEFAULT NULL,
  `need_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `complaint` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `preferred_date` date DEFAULT NULL,
  `preferred_time` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'submitted',
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `doctor_recommendation` text COLLATE utf8mb4_unicode_ci,
  `doctor_notes` text COLLATE utf8mb4_unicode_ci,
  `needs_follow_up` tinyint(1) NOT NULL DEFAULT '0',
  `offer_service_id` bigint unsigned DEFAULT NULL,
  `offer_service_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `offer_description` text COLLATE utf8mb4_unicode_ci,
  `offer_price` decimal(12,2) DEFAULT NULL,
  `offer_price_type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'final',
  `offer_duration_minutes` int unsigned DEFAULT NULL,
  `offer_doctor_id` bigint unsigned DEFAULT NULL,
  `offer_schedule_date` date DEFAULT NULL,
  `offer_schedule_time` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `offer_notes` text COLLATE utf8mb4_unicode_ci,
  `offer_valid_until` datetime DEFAULT NULL,
  `offered_at` timestamp NULL DEFAULT NULL,
  `accepted_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `owner_response_note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `service_requests_code_unique` (`code`),
  KEY `service_requests_pet_id_foreign` (`pet_id`),
  KEY `service_requests_doctor_id_foreign` (`doctor_id`),
  KEY `service_requests_medical_record_id_foreign` (`medical_record_id`),
  KEY `service_requests_booking_id_foreign` (`booking_id`),
  KEY `service_requests_offer_service_id_foreign` (`offer_service_id`),
  KEY `service_requests_offer_doctor_id_foreign` (`offer_doctor_id`),
  KEY `service_requests_clinic_id_status_index` (`clinic_id`,`status`),
  KEY `service_requests_user_id_status_index` (`user_id`,`status`),
  KEY `service_requests_doctor_scope` (`clinic_id`,`doctor_id`,`status`),
  CONSTRAINT `service_requests_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `service_requests_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `service_requests_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `service_requests_medical_record_id_foreign` FOREIGN KEY (`medical_record_id`) REFERENCES `medical_records` (`id`) ON DELETE SET NULL,
  CONSTRAINT `service_requests_offer_doctor_id_foreign` FOREIGN KEY (`offer_doctor_id`) REFERENCES `doctors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `service_requests_offer_service_id_foreign` FOREIGN KEY (`offer_service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL,
  CONSTRAINT `service_requests_pet_id_foreign` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `service_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: services
-- ----------------------------
CREATE TABLE `services` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `duration` int unsigned DEFAULT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_category_id` bigint unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `clinic_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `services_clinic_id_is_active_index` (`clinic_id`,`is_active`),
  KEY `services_service_category_id_foreign` (`service_category_id`),
  CONSTRAINT `services_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `services_service_category_id_foreign` FOREIGN KEY (`service_category_id`) REFERENCES `service_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1539 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: sessions
-- ----------------------------
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: treatment_consents
-- ----------------------------
CREATE TABLE `treatment_consents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `medical_record_id` bigint unsigned DEFAULT NULL,
  `pet_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `consent_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `risks_explained` text COLLATE utf8mb4_unicode_ci,
  `signer_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `signer_relation` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pemilik',
  `signature_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signed_at` timestamp NULL DEFAULT NULL,
  `witness_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `treatment_consents_pet_id_foreign` (`pet_id`),
  KEY `treatment_consents_user_id_foreign` (`user_id`),
  KEY `treatment_consents_record_type_index` (`medical_record_id`,`consent_type`),
  CONSTRAINT `treatment_consents_medical_record_id_foreign` FOREIGN KEY (`medical_record_id`) REFERENCES `medical_records` (`id`) ON DELETE CASCADE,
  CONSTRAINT `treatment_consents_pet_id_foreign` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `treatment_consents_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: users
-- ----------------------------
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `firebase_uid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `clinic_id` bigint unsigned DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_firebase_uid_unique` (`firebase_uid`),
  KEY `idx_users_name` (`name`),
  KEY `idx_users_phone` (`phone`),
  KEY `users_clinic_id_role_index` (`clinic_id`,`role`),
  CONSTRAINT `users_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4349 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: vaccinations
-- ----------------------------
CREATE TABLE `vaccinations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pet_id` bigint unsigned NOT NULL,
  `vaccine_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'e.g., Rabies, Distemper, Parvovirus',
  `batch_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Vaccine batch/lot number',
  `date_administered` date NOT NULL COMMENT 'When vaccination was given',
  `next_due_date` date DEFAULT NULL COMMENT 'When next vaccination is due',
  `veterinarian` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Vet who administered vaccine',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT 'Side effects, reactions, etc.',
  `reminder_sent` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Whether reminder was sent',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pet_vaccination_due` (`pet_id`,`next_due_date`),
  KEY `idx_upcoming_vaccinations` (`next_due_date`,`reminder_sent`),
  CONSTRAINT `vaccinations_pet_id_foreign` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: weight_records
-- ----------------------------
CREATE TABLE `weight_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pet_id` bigint unsigned NOT NULL,
  `weight` decimal(5,2) NOT NULL COMMENT 'Weight in kg',
  `recorded_at` date NOT NULL COMMENT 'Date when weight was recorded',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT 'Optional notes (diet change, health condition, etc.)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pet_weight_date` (`pet_id`,`recorded_at`),
  CONSTRAINT `weight_records_pet_id_foreign` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
