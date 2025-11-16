-- =====================================================
-- DATABASE SCHEMA LENGKAP - SEKOLAH API (FIXED VERSION)
-- =====================================================
-- Generated from Laravel Migrations
-- Database: db_sekolah (MySQL)
-- Date: October 25, 2025
-- Compatible dengan MySQL 5.7+ dan MariaDB 10.2+
-- =====================================================

-- Buat database jika belum ada
CREATE DATABASE IF NOT EXISTS `db_sekolah`
DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Gunakan database
USE `db_sekolah`;

-- =====================================================
-- 1. TABEL USERS (Core Authentication)
-- =====================================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nama` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'kurikulum', 'siswa', 'kepala-sekolah') NOT NULL DEFAULT 'siswa',
    `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    `avatar` VARCHAR(255) NULL DEFAULT NULL,
    `phone` VARCHAR(255) NULL DEFAULT NULL,
    `address` TEXT NULL DEFAULT NULL,
    `last_login_at` TIMESTAMP NULL DEFAULT NULL,
    `remember_token` VARCHAR(100) NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_email_unique` (`email`),
    KEY `users_email_index` (`email`),
    KEY `users_role_index` (`role`),
    KEY `users_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. TABEL PASSWORD RESET TOKENS
-- =====================================================
DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens` (
    `email` VARCHAR(255) NOT NULL,
    `token` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. TABEL SESSIONS
-- =====================================================
DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
    `id` VARCHAR(255) NOT NULL,
    `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `ip_address` VARCHAR(45) NULL DEFAULT NULL,
    `user_agent` TEXT NULL DEFAULT NULL,
    `payload` LONGTEXT NOT NULL,
    `last_activity` INT NOT NULL,
    PRIMARY KEY (`id`),
    KEY `sessions_user_id_index` (`user_id`),
    KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4. TABEL CACHE
-- =====================================================
DROP TABLE IF EXISTS `cache`;
CREATE TABLE `cache` (
    `key` VARCHAR(255) NOT NULL,
    `value` MEDIUMTEXT NOT NULL,
    `expiration` INT NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE `cache_locks` (
    `key` VARCHAR(255) NOT NULL,
    `owner` VARCHAR(255) NOT NULL,
    `expiration` INT NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 5. TABEL JOBS (Queue System)
-- =====================================================
DROP TABLE IF EXISTS `jobs`;
CREATE TABLE `jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue` VARCHAR(255) NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `attempts` TINYINT UNSIGNED NOT NULL,
    `reserved_at` INT UNSIGNED NULL DEFAULT NULL,
    `available_at` INT UNSIGNED NOT NULL,
    `created_at` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `job_batches`;
CREATE TABLE `job_batches` (
    `id` VARCHAR(255) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `total_jobs` INT NOT NULL,
    `pending_jobs` INT NOT NULL,
    `failed_jobs` INT NOT NULL,
    `failed_job_ids` LONGTEXT NOT NULL,
    `options` MEDIUMTEXT NULL DEFAULT NULL,
    `cancelled_at` INT NULL DEFAULT NULL,
    `created_at` INT NOT NULL,
    `finished_at` INT NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE `failed_jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid` VARCHAR(255) NOT NULL,
    `connection` TEXT NOT NULL,
    `queue` TEXT NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `exception` LONGTEXT NOT NULL,
    `failed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 6. TABEL PERSONAL ACCESS TOKENS (Sanctum)
-- =====================================================
DROP TABLE IF EXISTS `personal_access_tokens`;
CREATE TABLE `personal_access_tokens` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tokenable_type` VARCHAR(255) NOT NULL,
    `tokenable_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `token` VARCHAR(64) NOT NULL,
    `abilities` TEXT NULL DEFAULT NULL,
    `last_used_at` TIMESTAMP NULL DEFAULT NULL,
    `expires_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
    KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`, `tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 7. TABEL CLASSROOMS (Ruang Kelas) - Buat dulu karena direferensi
-- =====================================================
DROP TABLE IF EXISTS `classrooms`;
CREATE TABLE `classrooms` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `code` VARCHAR(255) NOT NULL,
    `type` ENUM('regular', 'laboratory', 'special', 'hall') NOT NULL,
    `capacity` INT NOT NULL DEFAULT 36,
    `floor` INT NOT NULL,
    `building` VARCHAR(255) NOT NULL,
    `facilities` JSON NULL DEFAULT NULL,
    `status` ENUM('available', 'maintenance', 'unavailable') NOT NULL DEFAULT 'available',
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `classrooms_code_unique` (`code`),
    KEY `classrooms_type_index` (`type`),
    KEY `classrooms_building_index` (`building`),
    KEY `classrooms_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 8. TABEL SUBJECTS (Mata Pelajaran)
-- =====================================================
DROP TABLE IF EXISTS `subjects`;
CREATE TABLE `subjects` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `code` VARCHAR(255) NOT NULL,
    `category` ENUM('wajib', 'peminatan', 'mulok') NOT NULL,
    `description` TEXT NULL DEFAULT NULL,
    `credit_hours` INT NOT NULL DEFAULT 2,
    `semester` INT NOT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `subjects_code_unique` (`code`),
    KEY `subjects_category_index` (`category`),
    KEY `subjects_semester_index` (`semester`),
    KEY `subjects_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 9. TABEL CLASSES (Kelas)
-- =====================================================
DROP TABLE IF EXISTS `classes`;
CREATE TABLE `classes` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `level` INT NOT NULL,
    `major` VARCHAR(255) NOT NULL,
    `academic_year` VARCHAR(255) NOT NULL,
    `homeroom_teacher_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `capacity` INT NOT NULL DEFAULT 36,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `classes_homeroom_teacher_id_index` (`homeroom_teacher_id`),
    KEY `classes_level_index` (`level`),
    KEY `classes_academic_year_index` (`academic_year`),
    KEY `classes_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 10. TABEL TEACHERS (Modern Teacher Management)
-- =====================================================
DROP TABLE IF EXISTS `teachers`;
CREATE TABLE `teachers` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `nip` VARCHAR(255) NOT NULL,
    `teacher_code` VARCHAR(255) NOT NULL,
    `position` VARCHAR(255) NOT NULL,
    `department` VARCHAR(255) NOT NULL,
    `expertise` VARCHAR(255) NULL DEFAULT NULL,
    `certification` VARCHAR(255) NULL DEFAULT NULL,
    `join_date` DATE NOT NULL,
    `status` ENUM('active', 'inactive', 'retired') NOT NULL DEFAULT 'active',
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `teachers_nip_unique` (`nip`),
    UNIQUE KEY `teachers_teacher_code_unique` (`teacher_code`),
    KEY `teachers_user_id_index` (`user_id`),
    KEY `teachers_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 11. TABEL GURUS (Legacy Teacher Data)
-- =====================================================
DROP TABLE IF EXISTS `gurus`;
CREATE TABLE `gurus` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kode` VARCHAR(255) NOT NULL,
    `nama_guru` VARCHAR(255) NOT NULL,
    `telepon` VARCHAR(255) NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `gurus_kode_unique` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 12. TABEL SUBJECT_TEACHER (Pivot Table)
-- =====================================================
DROP TABLE IF EXISTS `subject_teacher`;
CREATE TABLE `subject_teacher` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `subject_id` BIGINT UNSIGNED NOT NULL,
    `teacher_id` BIGINT UNSIGNED NOT NULL,
    `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `subject_teacher_unique` (`subject_id`, `teacher_id`),
    KEY `subject_teacher_subject_id_index` (`subject_id`),
    KEY `subject_teacher_teacher_id_index` (`teacher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 13. TABEL SCHEDULES (Jadwal Pelajaran)
-- =====================================================
DROP TABLE IF EXISTS `schedules`;
CREATE TABLE `schedules` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `class_id` BIGINT UNSIGNED NOT NULL,
    `subject_id` BIGINT UNSIGNED NOT NULL,
    `teacher_id` BIGINT UNSIGNED NOT NULL,
    `classroom_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `day_of_week` ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') NOT NULL,
    `period_number` INT NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `academic_year` VARCHAR(255) NOT NULL,
    `semester` ENUM('ganjil', 'genap') NOT NULL,
    `status` ENUM('active', 'cancelled', 'substituted') NOT NULL DEFAULT 'active',
    `notes` TEXT NULL DEFAULT NULL,
    `created_by` BIGINT UNSIGNED NOT NULL,
    `updated_by` BIGINT UNSIGNED NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_schedule` (`class_id`, `day_of_week`, `period_number`, `academic_year`, `semester`),
    KEY `schedules_class_id_index` (`class_id`),
    KEY `schedules_subject_id_index` (`subject_id`),
    KEY `schedules_teacher_id_index` (`teacher_id`),
    KEY `schedules_classroom_id_index` (`classroom_id`),
    KEY `schedules_day_period_index` (`day_of_week`, `period_number`),
    KEY `schedules_academic_year_index` (`academic_year`),
    KEY `schedules_semester_index` (`semester`),
    KEY `schedules_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 14. TABEL TEACHER_SUBSTITUTIONS (Penggantian Guru)
-- =====================================================
DROP TABLE IF EXISTS `teacher_substitutions`;
CREATE TABLE `teacher_substitutions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `original_schedule_id` BIGINT UNSIGNED NOT NULL,
    `substitute_teacher_id` BIGINT UNSIGNED NOT NULL,
    `reason` TEXT NOT NULL,
    `substitute_date` DATE NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected', 'completed') NOT NULL DEFAULT 'pending',
    `approved_by` BIGINT UNSIGNED NULL DEFAULT NULL,
    `approved_at` TIMESTAMP NULL DEFAULT NULL,
    `notes` TEXT NULL DEFAULT NULL,
    `created_by` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `teacher_substitutions_original_schedule_id_index` (`original_schedule_id`),
    KEY `teacher_substitutions_substitute_teacher_id_index` (`substitute_teacher_id`),
    KEY `teacher_substitutions_substitute_date_index` (`substitute_date`),
    KEY `teacher_substitutions_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 15. TABEL EMPTY_CLASSROOMS (Ruang Kosong)
-- =====================================================
DROP TABLE IF EXISTS `empty_classrooms`;
CREATE TABLE `empty_classrooms` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `classroom_id` BIGINT UNSIGNED NOT NULL,
    `day_of_week` ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') NOT NULL,
    `period_number` INT NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `reason` ENUM('no_schedule', 'teacher_absent', 'class_cancelled', 'maintenance') NOT NULL,
    `notes` TEXT NULL DEFAULT NULL,
    `academic_year` VARCHAR(255) NOT NULL,
    `semester` ENUM('ganjil', 'genap') NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_empty_classroom` (`classroom_id`, `day_of_week`, `period_number`, `academic_year`, `semester`),
    KEY `empty_classrooms_classroom_id_index` (`classroom_id`),
    KEY `empty_classrooms_day_period_index` (`day_of_week`, `period_number`),
    KEY `empty_classrooms_reason_index` (`reason`),
    KEY `empty_classrooms_academic_year_index` (`academic_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 16. TABEL ACTIVITY_LOGS (Log Aktivitas)
-- =====================================================
-- Activity logs table dropped for performance optimization
CREATE TABLE `activity_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `action` ENUM('create', 'update', 'delete', 'login', 'logout', 'view', 'export', 'import') NOT NULL,
    `model_type` VARCHAR(255) NULL DEFAULT NULL,
    `model_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `old_values` JSON NULL DEFAULT NULL,
    `new_values` JSON NULL DEFAULT NULL,
    `ip_address` VARCHAR(45) NULL DEFAULT NULL,
    `user_agent` TEXT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `activity_logs_user_id_index` (`user_id`),
    KEY `activity_logs_created_at_index` (`created_at`),
    KEY `activity_logs_model_type_model_id_index` (`model_type`, `model_id`),
    KEY `activity_logs_action_index` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 17. TABEL NOTIFICATIONS (Notifikasi)
-- =====================================================
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('info', 'success', 'warning', 'error') NOT NULL DEFAULT 'info',
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `read_at` TIMESTAMP NULL DEFAULT NULL,
    `data` JSON NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `notifications_user_id_index` (`user_id`),
    KEY `notifications_is_read_index` (`is_read`),
    KEY `notifications_created_at_index` (`created_at`),
    KEY `notifications_type_index` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TAMBAH FOREIGN KEY CONSTRAINTS SETELAH SEMUA TABEL DIBUAT
-- =====================================================

-- Foreign Keys untuk tabel teachers
ALTER TABLE `teachers`
ADD CONSTRAINT `teachers_user_id_foreign`
FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;

-- Foreign Keys untuk tabel classes
ALTER TABLE `classes`
ADD CONSTRAINT `classes_homeroom_teacher_id_foreign`
FOREIGN KEY (`homeroom_teacher_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;

-- Foreign Keys untuk tabel subject_teacher
ALTER TABLE `subject_teacher`
ADD CONSTRAINT `subject_teacher_subject_id_foreign`
FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE;

ALTER TABLE `subject_teacher`
ADD CONSTRAINT `subject_teacher_teacher_id_foreign`
FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE;

-- Foreign Keys untuk tabel schedules
ALTER TABLE `schedules`
ADD CONSTRAINT `schedules_class_id_foreign`
FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE;

ALTER TABLE `schedules`
ADD CONSTRAINT `schedules_subject_id_foreign`
FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE;

ALTER TABLE `schedules`
ADD CONSTRAINT `schedules_teacher_id_foreign`
FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE;

ALTER TABLE `schedules`
ADD CONSTRAINT `schedules_classroom_id_foreign`
FOREIGN KEY (`classroom_id`) REFERENCES `classrooms`(`id`) ON DELETE SET NULL;

ALTER TABLE `schedules`
ADD CONSTRAINT `schedules_created_by_foreign`
FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE;

ALTER TABLE `schedules`
ADD CONSTRAINT `schedules_updated_by_foreign`
FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;

-- Foreign Keys untuk tabel teacher_substitutions
ALTER TABLE `teacher_substitutions`
ADD CONSTRAINT `teacher_substitutions_original_schedule_id_foreign`
FOREIGN KEY (`original_schedule_id`) REFERENCES `schedules`(`id`) ON DELETE CASCADE;

ALTER TABLE `teacher_substitutions`
ADD CONSTRAINT `teacher_substitutions_substitute_teacher_id_foreign`
FOREIGN KEY (`substitute_teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE;

ALTER TABLE `teacher_substitutions`
ADD CONSTRAINT `teacher_substitutions_approved_by_foreign`
FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;

ALTER TABLE `teacher_substitutions`
ADD CONSTRAINT `teacher_substitutions_created_by_foreign`
FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE;

-- Foreign Keys untuk tabel empty_classrooms
ALTER TABLE `empty_classrooms`
ADD CONSTRAINT `empty_classrooms_classroom_id_foreign`
FOREIGN KEY (`classroom_id`) REFERENCES `classrooms`(`id`) ON DELETE CASCADE;

-- Foreign Keys untuk tabel activity_logs
ALTER TABLE `activity_logs`
ADD CONSTRAINT `activity_logs_user_id_foreign`
FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;

-- Foreign Keys untuk tabel notifications
ALTER TABLE `notifications`
ADD CONSTRAINT `notifications_user_id_foreign`
FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;

-- =====================================================
-- INSERT SAMPLE DATA
-- =====================================================

-- Insert Admin Users
INSERT INTO `users` (`id`, `nama`, `email`, `password`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Administrator', 'admin@sekolah.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', NOW(), NOW()),
(2, 'Kepala Sekolah', 'kepsek@sekolah.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kepala-sekolah', 'active', NOW(), NOW()),
(3, 'Staff Kurikulum', 'kurikulum@sekolah.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kurikulum', 'active', NOW(), NOW()),
(4, 'Guru Pemrograman', 'guru.programming@sekolah.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kurikulum', 'active', NOW(), NOW()),
(5, 'Guru Matematika', 'guru.math@sekolah.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kurikulum', 'active', NOW(), NOW());

-- Insert Sample Classrooms
INSERT INTO `classrooms` (`id`, `name`, `code`, `type`, `capacity`, `floor`, `building`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Ruang XI RPL 1', 'R-XI-RPL-1', 'regular', 36, 2, 'Gedung Utama', 'available', NOW(), NOW()),
(2, 'Ruang XI RPL 2', 'R-XI-RPL-2', 'regular', 36, 2, 'Gedung Utama', 'available', NOW(), NOW()),
(3, 'Lab Komputer 1', 'LAB-KOMP-1', 'laboratory', 30, 1, 'Gedung Lab', 'available', NOW(), NOW()),
(4, 'Ruang Multimedia', 'R-MULTIMEDIA', 'special', 40, 1, 'Gedung Utama', 'available', NOW(), NOW()),
(5, 'Lab Komputer 2', 'LAB-KOMP-2', 'laboratory', 30, 1, 'Gedung Lab', 'available', NOW(), NOW());

-- Insert Sample Subjects
INSERT INTO `subjects` (`id`, `name`, `code`, `category`, `description`, `credit_hours`, `semester`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Pemrograman Dasar', 'PD-001', 'peminatan', 'Mata pelajaran pemrograman dasar untuk jurusan RPL', 4, 1, 'active', NOW(), NOW()),
(2, 'Basis Data', 'BD-001', 'peminatan', 'Mata pelajaran basis data dan SQL', 4, 1, 'active', NOW(), NOW()),
(3, 'Matematika', 'MTK-001', 'wajib', 'Mata pelajaran matematika wajib', 4, 1, 'active', NOW(), NOW()),
(4, 'Bahasa Indonesia', 'BI-001', 'wajib', 'Mata pelajaran bahasa Indonesia', 4, 1, 'active', NOW(), NOW()),
(5, 'Bahasa Inggris', 'BING-001', 'wajib', 'Mata pelajaran bahasa Inggris', 2, 1, 'active', NOW(), NOW()),
(6, 'Pemrograman Web', 'PW-001', 'peminatan', 'Mata pelajaran pemrograman web HTML, CSS, JavaScript', 4, 2, 'active', NOW(), NOW()),
(7, 'Sistem Operasi', 'SO-001', 'peminatan', 'Mata pelajaran sistem operasi dan jaringan', 3, 2, 'active', NOW(), NOW());

-- Insert Sample Classes
INSERT INTO `classes` (`id`, `name`, `level`, `major`, `academic_year`, `homeroom_teacher_id`, `capacity`, `status`, `created_at`, `updated_at`) VALUES
(1, 'XI RPL 1', 11, 'Rekayasa Perangkat Lunak', '2024/2025', 4, 36, 'active', NOW(), NOW()),
(2, 'XI RPL 2', 11, 'Rekayasa Perangkat Lunak', '2024/2025', 5, 36, 'active', NOW(), NOW()),
(3, 'XI TKJ 1', 11, 'Teknik Komputer dan Jaringan', '2024/2025', NULL, 36, 'active', NOW(), NOW());

-- Insert Sample Teachers
INSERT INTO `teachers` (`id`, `user_id`, `nip`, `teacher_code`, `position`, `department`, `expertise`, `join_date`, `status`, `created_at`, `updated_at`) VALUES
(1, 4, '19850101001', 'T001', 'Guru Mata Pelajaran', 'RPL', 'Programming, Database', '2020-07-01', 'active', NOW(), NOW()),
(2, 5, '19800505002', 'T002', 'Guru Mata Pelajaran', 'Umum', 'Matematika, Statistik', '2018-08-01', 'active', NOW(), NOW());

-- Insert Sample Legacy Gurus
INSERT INTO `gurus` (`id`, `kode`, `nama_guru`, `telepon`, `created_at`, `updated_at`) VALUES
(1, 'G001', 'Budi Santoso, S.Kom', '081234567890', NOW(), NOW()),
(2, 'G002', 'Siti Rahayu, S.Pd', '081234567891', NOW(), NOW()),
(3, 'G003', 'Ahmad Fauzi, S.T', '081234567892', NOW(), NOW());

-- Insert Subject-Teacher Relations
INSERT INTO `subject_teacher` (`subject_id`, `teacher_id`, `is_primary`, `created_at`, `updated_at`) VALUES
(1, 1, 1, NOW(), NOW()), -- Pemrograman Dasar - Guru Programming (Primary)
(2, 1, 1, NOW(), NOW()), -- Basis Data - Guru Programming (Primary)
(6, 1, 1, NOW(), NOW()), -- Pemrograman Web - Guru Programming (Primary)
(3, 2, 1, NOW(), NOW()), -- Matematika - Guru Math (Primary)
(7, 1, 0, NOW(), NOW()); -- Sistem Operasi - Guru Programming (Secondary)

-- =====================================================
-- SAMPLE SCHEDULES DATA
-- =====================================================
INSERT INTO `schedules` (`class_id`, `subject_id`, `teacher_id`, `classroom_id`, `day_of_week`, `period_number`, `start_time`, `end_time`, `academic_year`, `semester`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
-- Senin XI RPL 1
(1, 1, 1, 3, 'monday', 1, '07:00:00', '08:30:00', '2024/2025', 'ganjil', 'active', 1, NOW(), NOW()),
(1, 1, 1, 3, 'monday', 2, '08:30:00', '10:00:00', '2024/2025', 'ganjil', 'active', 1, NOW(), NOW()),
(1, 3, 2, 1, 'monday', 3, '10:15:00', '11:45:00', '2024/2025', 'ganjil', 'active', 1, NOW(), NOW()),
(1, 4, 2, 1, 'monday', 4, '12:30:00', '14:00:00', '2024/2025', 'ganjil', 'active', 1, NOW(), NOW()),

-- Selasa XI RPL 1
(1, 2, 1, 3, 'tuesday', 1, '07:00:00', '08:30:00', '2024/2025', 'ganjil', 'active', 1, NOW(), NOW()),
(1, 2, 1, 3, 'tuesday', 2, '08:30:00', '10:00:00', '2024/2025', 'ganjil', 'active', 1, NOW(), NOW()),
(1, 5, 2, 1, 'tuesday', 3, '10:15:00', '11:45:00', '2024/2025', 'ganjil', 'active', 1, NOW(), NOW()),

-- Senin XI RPL 2
(2, 3, 2, 2, 'monday', 1, '07:00:00', '08:30:00', '2024/2025', 'ganjil', 'active', 1, NOW(), NOW()),
(2, 1, 1, 5, 'monday', 2, '08:30:00', '10:00:00', '2024/2025', 'ganjil', 'active', 1, NOW(), NOW()),
(2, 1, 1, 5, 'monday', 3, '10:15:00', '11:45:00', '2024/2025', 'ganjil', 'active', 1, NOW(), NOW());

-- =====================================================
-- CREATE INDEXES UNTUK PERFORMA
-- =====================================================

-- Index tambahan untuk query yang sering digunakan
CREATE INDEX `idx_schedules_weekly_view` ON `schedules` (`day_of_week`, `period_number`, `status`);
CREATE INDEX `idx_schedules_teacher_view` ON `schedules` (`teacher_id`, `day_of_week`, `status`);
CREATE INDEX `idx_schedules_classroom_view` ON `schedules` (`classroom_id`, `day_of_week`, `status`);
CREATE INDEX `idx_activity_logs_recent` ON `activity_logs` (`created_at` DESC);
CREATE INDEX `idx_notifications_unread` ON `notifications` (`user_id`, `is_read`, `created_at`);

-- =====================================================
-- INFORMASI DATABASE
-- =====================================================
SELECT
    'Database Schema Created Successfully!' as message,
    DATABASE() as current_database,
    NOW() as created_at;

-- Tampilkan daftar semua tabel yang berhasil dibuat
SELECT
    TABLE_NAME as 'Nama Tabel',
    TABLE_ROWS as 'Jumlah Data',
    ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) as 'Ukuran (MB)',
    ENGINE as 'Storage Engine'
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME;

-- =====================================================
-- VERIFIKASI FOREIGN KEY CONSTRAINTS
-- =====================================================
SELECT
    CONSTRAINT_NAME as 'Constraint Name',
    TABLE_NAME as 'Table',
    COLUMN_NAME as 'Column',
    REFERENCED_TABLE_NAME as 'Referenced Table',
    REFERENCED_COLUMN_NAME as 'Referenced Column'
FROM information_schema.KEY_COLUMN_USAGE
WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
AND REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY TABLE_NAME, CONSTRAINT_NAME;
