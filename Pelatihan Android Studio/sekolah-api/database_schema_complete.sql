-- =====================================================
-- DATABASE SCHEMA LENGKAP - SEKOLAH API
-- =====================================================
-- Generated from Laravel Migrations
-- Database: db_sekolah (MySQL)
-- Date: October 25, 2025
-- =====================================================

-- Gunakan database
USE db_sekolah;

-- =====================================================
-- 1. TABEL USERS (Core Authentication)
-- =====================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nama` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `email_verified_at` TIMESTAMP NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'kurikulum', 'siswa', 'kepala-sekolah') NOT NULL DEFAULT 'siswa',
    `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    `avatar` VARCHAR(255) NULL,
    `phone` VARCHAR(255) NULL,
    `address` TEXT NULL,
    `last_login_at` TIMESTAMP NULL,
    `remember_token` VARCHAR(100) NULL,
    `deleted_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    INDEX `users_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. TABEL PASSWORD RESET TOKENS
-- =====================================================
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
    `email` VARCHAR(255) NOT NULL,
    `token` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NULL,
    PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. TABEL SESSIONS
-- =====================================================
CREATE TABLE IF NOT EXISTS `sessions` (
    `id` VARCHAR(255) NOT NULL,
    `user_id` BIGINT UNSIGNED NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `payload` LONGTEXT NOT NULL,
    `last_activity` INT NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `sessions_user_id_index` (`user_id`),
    INDEX `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4. TABEL CACHE
-- =====================================================
CREATE TABLE IF NOT EXISTS `cache` (
    `key` VARCHAR(255) NOT NULL,
    `value` MEDIUMTEXT NOT NULL,
    `expiration` INT NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache_locks` (
    `key` VARCHAR(255) NOT NULL,
    `owner` VARCHAR(255) NOT NULL,
    `expiration` INT NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 5. TABEL JOBS (Queue System)
-- =====================================================
CREATE TABLE IF NOT EXISTS `jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue` VARCHAR(255) NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `attempts` TINYINT UNSIGNED NOT NULL,
    `reserved_at` INT UNSIGNED NULL,
    `available_at` INT UNSIGNED NOT NULL,
    `created_at` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_batches` (
    `id` VARCHAR(255) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `total_jobs` INT NOT NULL,
    `pending_jobs` INT NOT NULL,
    `failed_jobs` INT NOT NULL,
    `failed_job_ids` LONGTEXT NOT NULL,
    `options` MEDIUMTEXT NULL,
    `cancelled_at` INT NULL,
    `created_at` INT NOT NULL,
    `finished_at` INT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `failed_jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid` VARCHAR(255) NOT NULL UNIQUE,
    `connection` TEXT NOT NULL,
    `queue` TEXT NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `exception` LONGTEXT NOT NULL,
    `failed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 6. TABEL PERSONAL ACCESS TOKENS (Sanctum)
-- =====================================================
CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tokenable_type` VARCHAR(255) NOT NULL,
    `tokenable_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `token` VARCHAR(64) NOT NULL UNIQUE,
    `abilities` TEXT NULL,
    `last_used_at` TIMESTAMP NULL,
    `expires_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    INDEX `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`, `tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 7. TABEL GURUS (Legacy Teacher Data)
-- =====================================================
CREATE TABLE IF NOT EXISTS `gurus` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kode` VARCHAR(255) NOT NULL UNIQUE,
    `nama_guru` VARCHAR(255) NOT NULL,
    `telepon` VARCHAR(255) NULL,
    `deleted_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    INDEX `gurus_kode_index` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 8. TABEL TEACHERS (Modern Teacher Management)
-- =====================================================
CREATE TABLE IF NOT EXISTS `teachers` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `nip` VARCHAR(255) NOT NULL UNIQUE,
    `teacher_code` VARCHAR(255) NOT NULL UNIQUE,
    `position` VARCHAR(255) NOT NULL,
    `department` VARCHAR(255) NOT NULL,
    `expertise` VARCHAR(255) NULL,
    `certification` VARCHAR(255) NULL,
    `join_date` DATE NOT NULL,
    `status` ENUM('active', 'inactive', 'retired') NOT NULL DEFAULT 'active',
    `deleted_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `teachers_user_id_index` (`user_id`),
    INDEX `teachers_nip_index` (`nip`),
    INDEX `teachers_teacher_code_index` (`teacher_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 9. TABEL SUBJECTS (Mata Pelajaran)
-- =====================================================
CREATE TABLE IF NOT EXISTS `subjects` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `code` VARCHAR(255) NOT NULL UNIQUE,
    `category` ENUM('wajib', 'peminatan', 'mulok') NOT NULL,
    `description` TEXT NULL,
    `credit_hours` INT NOT NULL DEFAULT 2,
    `semester` INT NOT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `deleted_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    INDEX `subjects_code_index` (`code`),
    INDEX `subjects_category_index` (`category`),
    INDEX `subjects_semester_index` (`semester`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 10. TABEL CLASSES (Kelas)
-- =====================================================
CREATE TABLE IF NOT EXISTS `classes` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `level` INT NOT NULL,
    `major` VARCHAR(255) NOT NULL,
    `academic_year` VARCHAR(255) NOT NULL,
    `homeroom_teacher_id` BIGINT UNSIGNED NULL,
    `capacity` INT NOT NULL DEFAULT 36,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `deleted_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`homeroom_teacher_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `classes_homeroom_teacher_id_index` (`homeroom_teacher_id`),
    INDEX `classes_level_index` (`level`),
    INDEX `classes_academic_year_index` (`academic_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 11. TABEL CLASSROOMS (Ruang Kelas)
-- =====================================================
CREATE TABLE IF NOT EXISTS `classrooms` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `code` VARCHAR(255) NOT NULL UNIQUE,
    `type` ENUM('regular', 'laboratory', 'special', 'hall') NOT NULL,
    `capacity` INT NOT NULL DEFAULT 36,
    `floor` INT NOT NULL,
    `building` VARCHAR(255) NOT NULL,
    `facilities` JSON NULL,
    `status` ENUM('available', 'maintenance', 'unavailable') NOT NULL DEFAULT 'available',
    `deleted_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    INDEX `classrooms_code_index` (`code`),
    INDEX `classrooms_type_index` (`type`),
    INDEX `classrooms_building_index` (`building`),
    INDEX `classrooms_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 12. TABEL SUBJECT_TEACHER (Pivot Table)
-- =====================================================
CREATE TABLE IF NOT EXISTS `subject_teacher` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `subject_id` BIGINT UNSIGNED NOT NULL,
    `teacher_id` BIGINT UNSIGNED NOT NULL,
    `is_primary` BOOLEAN NOT NULL DEFAULT FALSE,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `subject_teacher_unique` (`subject_id`, `teacher_id`),
    INDEX `subject_teacher_subject_id_index` (`subject_id`),
    INDEX `subject_teacher_teacher_id_index` (`teacher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 13. TABEL SCHEDULES (Jadwal Pelajaran)
-- =====================================================
CREATE TABLE IF NOT EXISTS `schedules` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `class_id` BIGINT UNSIGNED NOT NULL,
    `subject_id` BIGINT UNSIGNED NOT NULL,
    `teacher_id` BIGINT UNSIGNED NOT NULL,
    `classroom_id` BIGINT UNSIGNED NULL,
    `day_of_week` ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') NOT NULL,
    `period_number` INT NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `academic_year` VARCHAR(255) NOT NULL,
    `semester` ENUM('ganjil', 'genap') NOT NULL,
    `status` ENUM('active', 'cancelled', 'substituted') NOT NULL DEFAULT 'active',
    `notes` TEXT NULL,
    `created_by` BIGINT UNSIGNED NOT NULL,
    `updated_by` BIGINT UNSIGNED NULL,
    `deleted_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`classroom_id`) REFERENCES `classrooms`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `unique_schedule` (`class_id`, `day_of_week`, `period_number`, `academic_year`, `semester`),
    INDEX `schedules_class_id_index` (`class_id`),
    INDEX `schedules_subject_id_index` (`subject_id`),
    INDEX `schedules_teacher_id_index` (`teacher_id`),
    INDEX `schedules_classroom_id_index` (`classroom_id`),
    INDEX `schedules_day_period_index` (`day_of_week`, `period_number`),
    INDEX `schedules_academic_year_index` (`academic_year`),
    INDEX `schedules_semester_index` (`semester`),
    INDEX `schedules_performance_index` (`day_of_week`, `period_number`, `academic_year`, `semester`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 14. TABEL TEACHER_SUBSTITUTIONS (Penggantian Guru)
-- =====================================================
CREATE TABLE IF NOT EXISTS `teacher_substitutions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `original_schedule_id` BIGINT UNSIGNED NOT NULL,
    `substitute_teacher_id` BIGINT UNSIGNED NOT NULL,
    `reason` TEXT NOT NULL,
    `substitute_date` DATE NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected', 'completed') NOT NULL DEFAULT 'pending',
    `approved_by` BIGINT UNSIGNED NULL,
    `approved_at` TIMESTAMP NULL,
    `notes` TEXT NULL,
    `created_by` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`original_schedule_id`) REFERENCES `schedules`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`substitute_teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `teacher_substitutions_original_schedule_id_index` (`original_schedule_id`),
    INDEX `teacher_substitutions_substitute_teacher_id_index` (`substitute_teacher_id`),
    INDEX `teacher_substitutions_substitute_date_index` (`substitute_date`),
    INDEX `teacher_substitutions_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 15. TABEL EMPTY_CLASSROOMS (Ruang Kosong)
-- =====================================================
CREATE TABLE IF NOT EXISTS `empty_classrooms` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `classroom_id` BIGINT UNSIGNED NOT NULL,
    `day_of_week` ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') NOT NULL,
    `period_number` INT NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `reason` ENUM('no_schedule', 'teacher_absent', 'class_cancelled', 'maintenance') NOT NULL,
    `notes` TEXT NULL,
    `academic_year` VARCHAR(255) NOT NULL,
    `semester` ENUM('ganjil', 'genap') NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`classroom_id`) REFERENCES `classrooms`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_empty_classroom` (`classroom_id`, `day_of_week`, `period_number`, `academic_year`, `semester`),
    INDEX `empty_classrooms_classroom_id_index` (`classroom_id`),
    INDEX `empty_classrooms_day_period_index` (`day_of_week`, `period_number`),
    INDEX `empty_classrooms_reason_index` (`reason`),
    INDEX `empty_classrooms_academic_year_index` (`academic_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity logs table removed for performance optimization

-- =====================================================
-- 17. TABEL NOTIFICATIONS (Notifikasi)
-- =====================================================
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('info', 'success', 'warning', 'error') NOT NULL DEFAULT 'info',
    `is_read` BOOLEAN NOT NULL DEFAULT FALSE,
    `read_at` TIMESTAMP NULL,
    `data` JSON NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `notifications_user_id_is_read_index` (`user_id`, `is_read`),
    INDEX `notifications_created_at_index` (`created_at`),
    INDEX `notifications_type_index` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 18. DATA CONTOH UNTUK TESTING
-- =====================================================

-- Insert Admin User
INSERT IGNORE INTO `users` (`id`, `nama`, `email`, `password`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Administrator', 'admin@sekolah.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', NOW(), NOW()),
(2, 'Kepala Sekolah', 'kepsek@sekolah.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kepala-sekolah', 'active', NOW(), NOW()),
(3, 'Staff Kurikulum', 'kurikulum@sekolah.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kurikulum', 'active', NOW(), NOW());

-- Insert Sample Classrooms
INSERT IGNORE INTO `classrooms` (`id`, `name`, `code`, `type`, `capacity`, `floor`, `building`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Ruang XI RPL 1', 'R-XI-RPL-1', 'regular', 36, 2, 'Gedung Utama', 'available', NOW(), NOW()),
(2, 'Ruang XI RPL 2', 'R-XI-RPL-2', 'regular', 36, 2, 'Gedung Utama', 'available', NOW(), NOW()),
(3, 'Lab Komputer 1', 'LAB-KOMP-1', 'laboratory', 30, 1, 'Gedung Lab', 'available', NOW(), NOW()),
(4, 'Ruang Multimedia', 'R-MULTIMEDIA', 'special', 40, 1, 'Gedung Utama', 'available', NOW(), NOW());

-- Insert Sample Subjects
INSERT IGNORE INTO `subjects` (`id`, `name`, `code`, `category`, `description`, `credit_hours`, `semester`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Pemrograman Dasar', 'PD-001', 'peminatan', 'Mata pelajaran pemrograman dasar untuk jurusan RPL', 4, 1, 'active', NOW(), NOW()),
(2, 'Basis Data', 'BD-001', 'peminatan', 'Mata pelajaran basis data dan SQL', 4, 1, 'active', NOW(), NOW()),
(3, 'Matematika', 'MTK-001', 'wajib', 'Mata pelajaran matematika wajib', 4, 1, 'active', NOW(), NOW()),
(4, 'Bahasa Indonesia', 'BI-001', 'wajib', 'Mata pelajaran bahasa Indonesia', 4, 1, 'active', NOW(), NOW()),
(5, 'Bahasa Inggris', 'BING-001', 'wajib', 'Mata pelajaran bahasa Inggris', 2, 1, 'active', NOW(), NOW());

-- Insert Sample Classes
INSERT IGNORE INTO `classes` (`id`, `name`, `level`, `major`, `academic_year`, `capacity`, `status`, `created_at`, `updated_at`) VALUES
(1, 'XI RPL 1', 11, 'Rekayasa Perangkat Lunak', '2024/2025', 36, 'active', NOW(), NOW()),
(2, 'XI RPL 2', 11, 'Rekayasa Perangkat Lunak', '2024/2025', 36, 'active', NOW(), NOW()),
(3, 'XI TKJ 1', 11, 'Teknik Komputer dan Jaringan', '2024/2025', 36, 'active', NOW(), NOW());

-- =====================================================
-- INDEXES TAMBAHAN UNTUK PERFORMA
-- =====================================================

-- Index untuk query jadwal yang sering digunakan
CREATE INDEX IF NOT EXISTS `idx_schedules_weekly_view` ON `schedules` (`day_of_week`, `period_number`, `status`);
CREATE INDEX IF NOT EXISTS `idx_schedules_teacher_view` ON `schedules` (`teacher_id`, `day_of_week`, `status`);
CREATE INDEX IF NOT EXISTS `idx_schedules_classroom_view` ON `schedules` (`classroom_id`, `day_of_week`, `status`);

-- Activity log indexes removed for performance optimization

-- Index untuk notifications
CREATE INDEX IF NOT EXISTS `idx_notifications_unread` ON `notifications` (`user_id`, `is_read`, `created_at`);

-- =====================================================
-- VIEWS UNTUK QUERY YANG SERING DIGUNAKAN
-- =====================================================

-- View untuk jadwal lengkap dengan detail
CREATE OR REPLACE VIEW `view_schedule_details` AS
SELECT
    s.id,
    s.day_of_week,
    s.period_number,
    s.start_time,
    s.end_time,
    s.academic_year,
    s.semester,
    s.status,
    c.name as class_name,
    c.level,
    c.major,
    sub.name as subject_name,
    sub.code as subject_code,
    u.nama as teacher_name,
    t.nip as teacher_nip,
    cr.name as classroom_name,
    cr.code as classroom_code,
    cr.building
FROM schedules s
LEFT JOIN classes c ON s.class_id = c.id
LEFT JOIN subjects sub ON s.subject_id = sub.id
LEFT JOIN teachers t ON s.teacher_id = t.id
LEFT JOIN users u ON t.user_id = u.id
LEFT JOIN classrooms cr ON s.classroom_id = cr.id
WHERE s.deleted_at IS NULL;

-- View untuk ruang kosong
CREATE OR REPLACE VIEW `view_empty_classrooms_today` AS
SELECT
    ec.id,
    ec.day_of_week,
    ec.period_number,
    ec.start_time,
    ec.end_time,
    ec.reason,
    cr.name as classroom_name,
    cr.code as classroom_code,
    cr.building,
    cr.capacity,
    ec.academic_year,
    ec.semester
FROM empty_classrooms ec
LEFT JOIN classrooms cr ON ec.classroom_id = cr.id
WHERE ec.day_of_week = LOWER(DAYNAME(CURDATE()));

-- =====================================================
-- STORED PROCEDURES UNTUK OPERASI UMUM
-- =====================================================

DELIMITER //

-- Procedure untuk mendapatkan jadwal hari ini
CREATE PROCEDURE IF NOT EXISTS GetTodaySchedule()
BEGIN
    SELECT * FROM view_schedule_details
    WHERE day_of_week = LOWER(DAYNAME(CURDATE()))
    AND status = 'active'
    ORDER BY period_number;
END //

-- Procedure untuk cek konflik jadwal
CREATE PROCEDURE IF NOT EXISTS CheckScheduleConflict(
    IN p_class_id BIGINT,
    IN p_day_of_week VARCHAR(10),
    IN p_period_number INT,
    IN p_academic_year VARCHAR(20),
    IN p_semester VARCHAR(10)
)
BEGIN
    SELECT COUNT(*) as conflict_count
    FROM schedules
    WHERE class_id = p_class_id
    AND day_of_week = p_day_of_week
    AND period_number = p_period_number
    AND academic_year = p_academic_year
    AND semester = p_semester
    AND status = 'active'
    AND deleted_at IS NULL;
END //

DELIMITER ;

-- Activity log triggers removed for performance optimization

-- =====================================================
-- SELESAI - DATABASE SCHEMA LENGKAP
-- =====================================================

-- Tampilkan informasi database
SELECT
    'Database Schema Created Successfully!' as status,
    COUNT(*) as total_tables
FROM information_schema.tables
WHERE table_schema = 'db_sekolah';

-- Tampilkan daftar semua tabel
SELECT
    table_name as 'Tabel',
    table_rows as 'Jumlah Data',
    ROUND(((data_length + index_length) / 1024 / 1024), 2) as 'Ukuran (MB)'
FROM information_schema.tables
WHERE table_schema = 'db_sekolah'
ORDER BY table_name;
