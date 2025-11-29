-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 23, 2025 at 03:31 AM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_sekolah`
--

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cache`
--

INSERT INTO `cache` (`key`, `value`, `expiration`) VALUES
('aplikasi-monitoring-kelas-cache-livewire-rate-limiter:16d36dff9abd246c67dfac3e63b993a169af77e6', 'i:1;', 1763284287),
('aplikasi-monitoring-kelas-cache-livewire-rate-limiter:16d36dff9abd246c67dfac3e63b993a169af77e6:timer', 'i:1763284287;', 1763284287);

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `id` bigint UNSIGNED NOT NULL,
  `nama_kelas` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `kode_kelas` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` int DEFAULT NULL,
  `major` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `academic_year` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `homeroom_teacher_id` bigint UNSIGNED DEFAULT NULL,
  `capacity` int NOT NULL DEFAULT '36',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`id`, `nama_kelas`, `kode_kelas`, `level`, `major`, `academic_year`, `created_at`, `updated_at`, `homeroom_teacher_id`, `capacity`, `status`, `deleted_at`) VALUES
(1, 'X RPL 1', 'XRPL1', 10, 'Rekayasa Perangkat Lunak', '2024/2025', '2025-11-20 04:56:31', '2025-11-20 04:56:31', NULL, 36, 'active', NULL),
(2, 'X RPL 2', 'XRPL2', 10, 'Rekayasa Perangkat Lunak', '2024/2025', '2025-11-20 05:17:57', '2025-11-21 09:25:23', NULL, 36, 'active', NULL),
(3, 'XI RPL 1', 'XIRPL1', 11, 'Rekayasa Perangkat Lunak', '2025/2026', '2025-11-20 05:17:57', '2025-11-21 09:34:22', NULL, 36, 'active', NULL),
(4, 'XI RPL 2', 'XIRPL2', 11, 'Rekayasa Perangkat Lunak', '2024/2025', '2025-11-20 05:17:57', '2025-11-20 05:17:57', NULL, 36, 'active', NULL),
(5, 'XII RPL 1', 'XIIRPL1', 12, 'Rekayasa Perangkat Lunak', '2024/2025', '2025-11-20 05:17:57', '2025-11-20 05:17:57', NULL, 36, 'active', NULL),
(6, 'XII RPL 2', 'XIIRPL2', 12, 'Rekayasa Perangkat Lunak', '2024/2025', '2025-11-20 05:17:57', '2025-11-20 05:17:57', NULL, 36, 'active', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint UNSIGNED NOT NULL,
  `reserved_at` int UNSIGNED DEFAULT NULL,
  `available_at` int UNSIGNED NOT NULL,
  `created_at` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int UNSIGNED NOT NULL,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2025_10_08_025216_add_role_to_users_table', 1),
(5, '2025_10_08_030038_create_personal_access_tokens_table', 1),
(6, '2025_10_08_031926_create_schedules_table', 1),
(7, '2025_10_08_032247_create_monitoring_table', 1),
(8, '2025_10_15_000001_create_assignments_table', 1),
(9, '2025_10_15_000002_create_assignment_submissions_table', 1),
(10, '2025_10_15_000003_create_grades_table', 1),
(11, '2025_10_15_051252_add_mata_pelajaran_to_users_table', 1),
(12, '2025_10_15_100000_create_teacher_attendances_table', 1),
(13, '2025_10_15_123009_create_guru_pengganti_table', 1),
(14, '2025_10_15_123013_add_is_banned_to_users_table', 1),
(15, '2025_10_17_000001_update_teacher_attendances_add_diganti_status', 1),
(16, '2025_10_22_002511_remove_guru_from_users_role', 1),
(17, '2025_10_22_002938_create_teachers_table', 1),
(18, '2025_10_22_003353_create_classes_table', 1),
(19, '2025_10_22_003608_create_subjects_table', 1),
(20, '2025_10_29_000000_update_teacher_attendances_to_reference_teachers_table', 2),
(21, '2025_10_14_005322_create_gurus_table', 3),
(22, '2025_11_19_031714_add_deleted_at_to_users_table', 4),
(23, '2025_11_18_080820_add_missing_columns_to_classes_table', 5),
(24, '2025_10_14_011521_create_teachers_table', 6),
(25, '2025_11_19_035243_add_name_to_teachers_table', 7),
(26, '2025_11_19_040238_update_teachers_table_structure', 8),
(27, '2025_11_20_105609_add_soft_deletes_to_teachers_table', 9),
(28, '2025_11_04_100000_add_class_id_to_users_table', 10),
(29, '2025_11_20_123839_drop_class_id_from_users_table', 11),
(30, '2025_11_20_125716_add_class_id_back_to_users_table', 12),
(31, '2025_11_20_152547_add_missing_columns_to_subjects_table', 13),
(32, '2025_11_21_181240_fix_schedules_guru_id_foreign_key', 14);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint UNSIGNED NOT NULL,
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `personal_access_tokens`
--

INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES
(1, 'App\\Models\\User', 2, 'auth_token_kurikulum_1763284398', 'c576d521cb628f08d7451680ad1050ce48db770f0125f4acfcda3f68f2a5fbff', '[\"*\"]', '2025-11-16 02:13:24', '2025-12-16 02:13:18', '2025-11-16 02:13:18', '2025-11-16 02:13:24'),
(2, 'App\\Models\\User', 3, 'web-token', '54ba95ab10ec1fc2ee37f13538cd3a0d2ee7828affde1976f3d9d8ff0dbbc2cc', '[\"*\"]', NULL, NULL, '2025-11-20 03:38:16', '2025-11-20 03:38:16'),
(3, 'App\\Models\\User', 3, 'web-token', '279d41621d9ce3962776f1439ad0b42eb45c7f3fa4afbe94f96660529e25021c', '[\"*\"]', NULL, NULL, '2025-11-20 14:50:26', '2025-11-20 14:50:26'),
(4, 'App\\Models\\User', 3, 'web-token', 'f84bcd1468a83ed0e24be6e5932434f540e4b6e64dcdede260ac11473d74ac14', '[\"*\"]', NULL, NULL, '2025-11-21 08:14:56', '2025-11-21 08:14:56'),
(5, 'App\\Models\\User', 3, 'web-token', 'e943f5cb1a671f420f4d9c225eb3969ba82fe7fc9bd4cc586de776be193e3ce3', '[\"*\"]', NULL, NULL, '2025-11-21 23:06:42', '2025-11-21 23:06:42'),
(6, 'App\\Models\\User', 3, 'web-token', '9b44b06c4fe69cdced63ab8d5c3d4d6ddfb7164c21b3305d983c971088751ed1', '[\"*\"]', NULL, NULL, '2025-11-22 04:44:07', '2025-11-22 04:44:07');

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `id` bigint UNSIGNED NOT NULL,
  `hari` enum('Senin','Selasa','Rabu','Kamis','Jumat','Sabtu') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `kelas` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mata_pelajaran` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `guru_id` bigint UNSIGNED NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `ruang` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`id`, `hari`, `kelas`, `mata_pelajaran`, `guru_id`, `jam_mulai`, `jam_selesai`, `ruang`, `created_at`, `updated_at`) VALUES
(6, 'Selasa', 'XI RPL 2', 'Bahasa Indonesia', 2, '08:00:00', '09:30:00', 'Lab Bahasa', '2025-11-22 07:47:37', '2025-11-22 07:47:37'),
(7, 'Rabu', 'XII RPL 1', 'Fisika Lanjutan', 3, '09:00:00', '10:30:00', 'Lab Fisika', '2025-11-22 07:47:37', '2025-11-22 07:47:37');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('MmJbGouOJ5wbc57xDEf9kPKVsdgN6BOkKTaGVwLq', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'YTo4OntzOjY6Il90b2tlbiI7czo0MDoiZjlPNjc3V05HZVlpV1ptRGZKSTFSTmNRVXlnTTRwWWtkSnNKUEVnbiI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MzU6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9hZG1pbi91c2Vycy8yIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czozOiJ1cmwiO2E6MDp7fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjE7czoxNzoicGFzc3dvcmRfaGFzaF93ZWIiO3M6NjA6IiQyeSQxMiR3SkFuV3VheW81aklUdFVZem5jWVVldmtVNU5NZFJHZmdxMzNpckZoR2l6MERaSmFDOHBzSyI7czo2OiJ0YWJsZXMiO2E6MTp7czo0MDoiZTY0NDgzM2Y0ZTRlMDg3MTIzMTVkYTcxYjMzZmFjZDJfY29sdW1ucyI7YTo5OntpOjA7YTo3OntzOjQ6InR5cGUiO3M6NjoiY29sdW1uIjtzOjQ6Im5hbWUiO3M6NDoibmFtZSI7czo1OiJsYWJlbCI7czo0OiJOYW1lIjtzOjg6ImlzSGlkZGVuIjtiOjA7czo5OiJpc1RvZ2dsZWQiO2I6MTtzOjEyOiJpc1RvZ2dsZWFibGUiO2I6MDtzOjI0OiJpc1RvZ2dsZWRIaWRkZW5CeURlZmF1bHQiO047fWk6MTthOjc6e3M6NDoidHlwZSI7czo2OiJjb2x1bW4iO3M6NDoibmFtZSI7czo1OiJlbWFpbCI7czo1OiJsYWJlbCI7czoxMzoiRW1haWwgYWRkcmVzcyI7czo4OiJpc0hpZGRlbiI7YjowO3M6OToiaXNUb2dnbGVkIjtiOjE7czoxMjoiaXNUb2dnbGVhYmxlIjtiOjA7czoyNDoiaXNUb2dnbGVkSGlkZGVuQnlEZWZhdWx0IjtOO31pOjI7YTo3OntzOjQ6InR5cGUiO3M6NjoiY29sdW1uIjtzOjQ6Im5hbWUiO3M6MTc6ImVtYWlsX3ZlcmlmaWVkX2F0IjtzOjU6ImxhYmVsIjtzOjE3OiJFbWFpbCB2ZXJpZmllZCBhdCI7czo4OiJpc0hpZGRlbiI7YjowO3M6OToiaXNUb2dnbGVkIjtiOjE7czoxMjoiaXNUb2dnbGVhYmxlIjtiOjA7czoyNDoiaXNUb2dnbGVkSGlkZGVuQnlEZWZhdWx0IjtOO31pOjM7YTo3OntzOjQ6InR5cGUiO3M6NjoiY29sdW1uIjtzOjQ6Im5hbWUiO3M6NDoicm9sZSI7czo1OiJsYWJlbCI7czo0OiJSb2xlIjtzOjg6ImlzSGlkZGVuIjtiOjA7czo5OiJpc1RvZ2dsZWQiO2I6MTtzOjEyOiJpc1RvZ2dsZWFibGUiO2I6MDtzOjI0OiJpc1RvZ2dsZWRIaWRkZW5CeURlZmF1bHQiO047fWk6NDthOjc6e3M6NDoidHlwZSI7czo2OiJjb2x1bW4iO3M6NDoibmFtZSI7czoxNjoia2VsYXMubmFtYV9rZWxhcyI7czo1OiJsYWJlbCI7czo1OiJDbGFzcyI7czo4OiJpc0hpZGRlbiI7YjowO3M6OToiaXNUb2dnbGVkIjtiOjE7czoxMjoiaXNUb2dnbGVhYmxlIjtiOjA7czoyNDoiaXNUb2dnbGVkSGlkZGVuQnlEZWZhdWx0IjtOO31pOjU7YTo3OntzOjQ6InR5cGUiO3M6NjoiY29sdW1uIjtzOjQ6Im5hbWUiO3M6MTQ6Im1hdGFfcGVsYWphcmFuIjtzOjU6ImxhYmVsIjtzOjE0OiJNYXRhIHBlbGFqYXJhbiI7czo4OiJpc0hpZGRlbiI7YjowO3M6OToiaXNUb2dnbGVkIjtiOjE7czoxMjoiaXNUb2dnbGVhYmxlIjtiOjA7czoyNDoiaXNUb2dnbGVkSGlkZGVuQnlEZWZhdWx0IjtOO31pOjY7YTo3OntzOjQ6InR5cGUiO3M6NjoiY29sdW1uIjtzOjQ6Im5hbWUiO3M6OToiaXNfYmFubmVkIjtzOjU6ImxhYmVsIjtzOjk6IklzIGJhbm5lZCI7czo4OiJpc0hpZGRlbiI7YjowO3M6OToiaXNUb2dnbGVkIjtiOjE7czoxMjoiaXNUb2dnbGVhYmxlIjtiOjA7czoyNDoiaXNUb2dnbGVkSGlkZGVuQnlEZWZhdWx0IjtOO31pOjc7YTo3OntzOjQ6InR5cGUiO3M6NjoiY29sdW1uIjtzOjQ6Im5hbWUiO3M6MTA6ImNyZWF0ZWRfYXQiO3M6NToibGFiZWwiO3M6MTA6IkNyZWF0ZWQgYXQiO3M6ODoiaXNIaWRkZW4iO2I6MDtzOjk6ImlzVG9nZ2xlZCI7YjowO3M6MTI6ImlzVG9nZ2xlYWJsZSI7YjoxO3M6MjQ6ImlzVG9nZ2xlZEhpZGRlbkJ5RGVmYXVsdCI7YjoxO31pOjg7YTo3OntzOjQ6InR5cGUiO3M6NjoiY29sdW1uIjtzOjQ6Im5hbWUiO3M6MTA6InVwZGF0ZWRfYXQiO3M6NToibGFiZWwiO3M6MTA6IlVwZGF0ZWQgYXQiO3M6ODoiaXNIaWRkZW4iO2I6MDtzOjk6ImlzVG9nZ2xlZCI7YjowO3M6MTI6ImlzVG9nZ2xlYWJsZSI7YjoxO3M6MjQ6ImlzVG9nZ2xlZEhpZGRlbkJ5RGVmYXVsdCI7YjoxO319fXM6ODoiZmlsYW1lbnQiO2E6MDp7fX0=', 1763284293);

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` bigint UNSIGNED NOT NULL,
  `nama` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `kode` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `category` enum('wajib','peminatan','mulok') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'wajib',
  `description` text COLLATE utf8mb4_unicode_ci,
  `credit_hours` int NOT NULL DEFAULT '2',
  `semester` int NOT NULL DEFAULT '1',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `nama`, `kode`, `created_at`, `updated_at`, `category`, `description`, `credit_hours`, `semester`, `status`) VALUES
(4, 'Mathematics', 'MTK003', '2025-11-21 08:33:39', '2025-11-21 08:34:03', 'wajib', 'Belajar Aljabar', 2, 1, 'active'),
(7, 'Matematika Dasar', 'MTK101', '2025-11-22 02:21:08', '2025-11-22 02:21:08', 'wajib', 'Pengantar matematika dasar', 3, 1, 'active'),
(9, 'Pendidikan Pancasila', 'PPN103', '2025-11-22 02:21:08', '2025-11-22 02:21:08', 'wajib', 'Pendidikan kewarganegaraan dan Pancasila', 2, 1, 'active'),
(10, 'Pendidikan Agama', 'AGR104', '2025-11-22 02:21:08', '2025-11-22 02:21:08', 'wajib', 'Pendidikan agama dan etika', 2, 1, 'active'),
(11, 'Algoritma dan Pemrograman Dasar', 'RPL105', '2025-11-22 02:21:08', '2025-11-22 02:21:08', 'wajib', 'Dasar-dasar algoritma dan pemrograman menggunakan Python', 4, 1, 'active'),
(12, 'Dasar Logika Komputer', 'RPL106', '2025-11-22 02:21:08', '2025-11-22 02:21:08', 'wajib', 'Pengenalan logika matematika untuk komputasi', 2, 1, 'active'),
(13, 'Bahasa Inggris', 'BIG201', '2025-11-22 02:21:08', '2025-11-22 02:21:08', 'wajib', 'Keterampilan berbahasa Inggris dasar', 2, 2, 'active'),
(14, 'Struktur Data', 'RPL202', '2025-11-22 02:21:08', '2025-11-22 02:21:08', 'wajib', 'Implementasi struktur data dasar (array', 2, 1, 'active'),
(15, 'Pemrograman Berorientasi Objek', 'RPL203', '2025-11-22 02:21:08', '2025-11-22 02:21:08', 'wajib', 'Konsep OOP menggunakan Java', 3, 2, 'active'),
(16, 'Basis Data Dasar', 'RPL204', '2025-11-22 02:21:08', '2025-11-22 02:21:08', 'wajib', 'Konsep basis data dan SQL sederhana', 3, 2, 'active'),
(18, 'Pemrograman Web Dasar', 'RPL301', '2025-11-22 02:21:08', '2025-11-22 02:21:08', 'wajib', 'Pengembangan front-end web (HTML', 2, 1, 'active'),
(19, 'Pemrograman Web Lanjutan', 'RPL302', '2025-11-22 02:21:08', '2025-11-22 02:21:08', 'wajib', 'Pengembangan back-end web dengan PHP/Node.js', 4, 3, 'active'),
(20, 'Rekayasa Perangkat Lunak', 'RPL303', '2025-11-22 02:21:08', '2025-11-22 02:21:08', 'wajib', 'Pengenalan siklus pengembangan software', 2, 3, 'active'),
(21, 'Bahasa Inggris Teknik', 'BIG304', '2025-11-22 02:21:08', '2025-11-22 02:21:08', 'wajib', 'Keterampilan bahasa Inggris untuk dunia kerja IT', 2, 3, 'active'),
(24, 'Jaringan Komputer Dasar', 'RPL402', '2025-11-22 02:21:08', '2025-11-22 02:21:08', 'wajib', 'Pengenalan jaringan dan konfigurasi dasar', 3, 4, 'active'),
(26, 'Kewirausahaan', 'KWR404', '2025-11-22 02:21:08', '2025-11-22 02:21:08', 'wajib', 'Dasar-dasar kewirausahaan di bidang teknologi', 2, 4, 'active'),
(28, 'Proyek Perangkat Lunak', 'RPL501', '2025-11-22 02:21:08', '2025-11-22 02:21:08', 'wajib', 'Pengembangan project aplikasi secara berkelompok', 5, 5, 'active'),
(29, 'Pengujian Perangkat Lunak', 'RPL502', '2025-11-22 02:21:08', '2025-11-22 02:21:08', 'wajib', 'Teknik testing software dasar', 2, 5, 'active'),
(31, 'Praktik Kerja Industri', 'PKL504', '2025-11-22 02:21:08', '2025-11-22 02:21:08', 'wajib', 'Magang di industri perangkat lunak', 6, 5, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `id` bigint UNSIGNED NOT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nip` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `teacher_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `department` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expertise` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `certification` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `join_date` date NOT NULL,
  `status` enum('active','inactive','retired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`id`, `nama`, `nip`, `teacher_code`, `position`, `department`, `expertise`, `certification`, `join_date`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(2, 'Dr. Ahmad Santoso', '198001012010011001', 'TCH001', 'Guru Senior', 'Teknik Informatika', 'Pemrograman Web, Database', 'Certified Laravel Developer', '2020-01-01', 'active', '2025-11-18 21:26:58', '2025-11-18 21:26:58', NULL),
(3, 'Budi Santoso', '198502152015032001', 'TCH002', 'Guru Matematika & RPL', 'Matematika & RPL', 'Matematika, Kalkulus, Aljabar, Logika Programming', 'Certified Math & Programming Teacher', '2019-03-15', 'active', '2025-11-18 21:28:21', '2025-11-18 21:28:21', NULL),
(4, 'Siti Nurhaliza', '198703202018011002', 'TCH003', 'Guru Bahasa Indonesia', 'Bahasa Indonesia', 'Bahasa Indonesia, Sastra, Linguistik, Penulisan Teknis', 'Certified Indonesian Language Teacher', '2018-07-10', 'active', '2025-11-18 21:28:21', '2025-11-18 21:28:21', NULL),
(5, 'Adi Wijaya', '198904252019022003', 'TCH004', 'Guru Bahasa Inggris & RPL', 'Bahasa Inggris & RPL', 'Bahasa Inggris, TOEFL, IELTS, Technical English, Programming English', 'Certified English & Programming Teacher', '2019-08-20', 'active', '2025-11-18 21:28:21', '2025-11-18 21:28:21', NULL),
(6, 'Maya Sari', '199005302020013004', 'TCH005', 'Guru Fisika & RPL', 'Fisika & RPL', 'Fisika, Mekanika, Elektromagnetik, Fisika Komputasi', 'Certified Physics & Computing Teacher', '2020-02-14', 'active', '2025-11-18 21:28:21', '2025-11-18 21:28:21', NULL),
(7, 'Rizki Ramadhan', '199106102021014005', 'TCH006', 'Guru RPL Senior', 'Rekayasa Perangkat Lunak', 'Pemrograman Web, Database, Mobile App, Laravel, React, Flutter', 'Certified Full-Stack Developer & Teacher', '2021-01-15', 'active', '2025-11-18 21:28:21', '2025-11-18 21:28:21', NULL),
(8, 'Diana Putriii', '199207202022015006', 'TCH007', 'Guru Basis Data & RPL', 'Basis Data & RPL', 'Database, SQL, MySQL, PostgreSQL, Database Design, Data Modeling', 'Certified Database Administrator & Teacher', '2022-03-10', 'active', '2025-11-18 21:28:21', '2025-11-20 08:06:47', NULL),
(9, 'Eko Prasetyo', '199308252023016007', 'TCH008', 'Guru Mobile Programming', 'Mobile Programming', 'Android, iOS, Flutter, React Native, Kotlin, Swift', 'Certified Mobile App Developer & Teacher', '2023-05-20', 'active', '2025-11-18 21:28:21', '2025-11-18 21:28:21', NULL),
(10, 'Lisa Permata', '199409152024017008', 'TCH009', 'Guru UI/UX & Design', 'Design & User Experience', 'UI/UX Design, Graphic Design, Adobe Creative Suite, Figma, Prototyping', 'Certified UI/UX Designer & Teacher', '2024-01-10', 'active', '2025-11-18 21:28:21', '2025-11-18 21:28:21', NULL),
(11, 'Hendra Gunawan', '199510202025018009', 'TCH010', 'Guru Jaringan', 'Network', 'Computer Network, Cisco, Network Security, Cloud Computing, DevOps', 'Certified Network Engineer & Teacher', '2025-02-15', 'active', '2025-11-18 21:28:21', '2025-11-20 08:06:23', '2025-11-20 08:06:23'),
(13, 'aldo ANJAYYY', '128390123091', 'TCH-192', 'PROGRAMMER', 'MACHINE LEARNING', 'SEMBARANG WES', 'S.KOM', '2025-11-20', 'inactive', '2025-11-20 07:52:05', '2025-11-20 07:53:15', '2025-11-20 07:53:15'),
(14, 'John Doe', '123456789', 'T001', 'Guru', 'Matematika', 'Matematika', 'S.Pd', '2023-01-15', 'active', '2025-11-22 01:27:29', '2025-11-22 07:28:12', '2025-11-22 07:28:12'),
(15, 'Jane Smith', '987654321', 'T002', 'Guru Senior', 'Bahasa Indonesia', 'Bahasa Indonesia', 'M.Pd', '2022-08-20', 'active', '2025-11-22 01:27:29', '2025-11-22 07:28:12', '2025-11-22 07:28:12'),
(16, 'Bob Johnson', '456789123', 'T003', 'Guru', 'Fisika', 'Fisika', 'S.Pd', '2023-03-10', 'active', '2025-11-22 01:27:29', '2025-11-22 07:28:12', '2025-11-22 07:28:12');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_attendances`
--

CREATE TABLE `teacher_attendances` (
  `id` bigint UNSIGNED NOT NULL,
  `schedule_id` bigint UNSIGNED NOT NULL,
  `guru_id` bigint UNSIGNED NOT NULL,
  `guru_asli_id` bigint UNSIGNED DEFAULT NULL,
  `tanggal` date NOT NULL,
  `jam_masuk` time DEFAULT NULL,
  `status` enum('hadir','telat','tidak_hadir','diganti') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'tidak_hadir',
  `keterangan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `assigned_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','siswa','kurikulum','kepala_sekolah') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'siswa',
  `mata_pelajaran` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_banned` tinyint(1) NOT NULL DEFAULT '0',
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `class_id` bigint UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `role`, `mata_pelajaran`, `is_banned`, `remember_token`, `created_at`, `updated_at`, `deleted_at`, `class_id`) VALUES
(1, 'zupa', 'zupa.admin@sekolah.com', NULL, '$2y$12$wJAnWuayo5jITtUYzncYUevkU5NMdRGfgq33irFhGiz0DZJaC8psK', 'siswa', NULL, 0, NULL, '2025-11-16 02:10:18', '2025-11-20 04:37:36', '2025-11-20 04:37:36', NULL),
(2, 'kurikulum', 'siti.kurikulum@sekolah.com', NULL, '$2y$12$7v7WJv/Az/arxO1Pp518u.irANM7QoP/F1eUOmAjNpeM0KKpJpOAm', 'kurikulum', NULL, 0, NULL, '2025-11-16 02:11:23', '2025-11-22 06:54:02', '2025-11-22 06:54:02', NULL),
(3, 'Admin SMK', 'admin@smk.com', NULL, '$2y$12$/Tl70.L3nxCEoFVeDDqlhezJUrHViLKZjRa8bxjovbtKz/W6LgNqe', 'admin', NULL, 0, NULL, '2025-11-18 20:28:39', '2025-11-18 20:28:39', NULL, NULL),
(4, 'Budi Santoso', 'budi.santoso@school.com', NULL, '$2y$12$AcQHiNY1/Jrx0AuDpdNtg.EB5SpJA4BY9mGLclWqvIIQ2TSFLuAHK', 'kurikulum', NULL, 0, NULL, '2025-11-18 21:25:56', '2025-11-20 04:09:24', '2025-11-20 04:09:24', NULL),
(5, 'CHRISTOPHER ALDO', 'aldo@gmail.com', NULL, '$2y$12$uVNByqPTDvYSjxJAfEeleeLmk089VLTptXZ5H9WYD0.rV5b4bPv9y', 'siswa', NULL, 0, NULL, '2025-11-20 05:29:31', '2025-11-22 06:58:32', '2025-11-22 06:58:32', NULL),
(6, 'daniel anjay', 'danielanjay@gmail.com', NULL, '$2y$12$Xq85Hmf/kh9Z4Tj9QNbk7OacpzHODp6RJ2Hk0pCdD1Q0I.wrkRqiC', 'siswa', NULL, 0, NULL, '2025-11-20 06:10:48', '2025-11-22 05:40:16', '2025-11-22 05:40:16', 3),
(7, 'Budi Santoso', 'budi.santoso@example.com', NULL, '$2y$12$rj0b13XUkQtDR.qWRCXbeOQsInSGYSFc1C3xJ3lvWaR5FNHgACvhC', 'admin', NULL, 0, NULL, '2025-11-21 11:58:12', '2025-11-21 11:58:12', NULL, NULL),
(8, 'Siti Rahmawati', 'siti.rahmawati@example.com', NULL, '$2y$12$5HX.hu3sA1H3u4aM6zzKQOBzL9dc432fiVOOgOtUb8AkPEN9fL356', 'siswa', NULL, 0, NULL, '2025-11-21 11:58:12', '2025-11-22 07:19:19', NULL, 1),
(9, 'Dewi Lestari', 'dewi.lestari@example.com', NULL, '$2y$12$CEH7PsTGp2y41zVuutIo5u.x.UIw2fTYSS4Pqi4TBwBxLGtFniZ8y', 'kurikulum', NULL, 0, NULL, '2025-11-21 11:58:13', '2025-11-22 07:19:20', NULL, NULL),
(10, 'Rizky Firmansyah', 'rizky.firmansyah@example.com', NULL, '$2y$12$F3pYx9at5p.ZdZeLwzAjdOD3TD7nJnXi9ToJ1iytSrulDN9T6up6.', 'siswa', NULL, 0, NULL, '2025-11-21 11:58:13', '2025-11-22 07:19:20', NULL, 2),
(11, 'Agus Pratama', 'agus.pratama@example.com', NULL, '$2y$12$rN3eXWGvcf8a/J3ej5tXmuYz9PGQP/A3aROUmiyU.oz3KMHnwP1d.', 'kepala_sekolah', NULL, 0, NULL, '2025-11-21 23:18:43', '2025-11-22 07:19:19', NULL, NULL),
(16, 'Andi Wijaya', 'andi.wijaya@example.com', NULL, '$2y$12$NGVj4T/f0wxiSEGdvUyDwu0ZXX0PRznG/9HFdYHTXlAYYuN5C11g2', 'admin', NULL, 0, NULL, '2025-11-22 01:19:05', '2025-11-22 01:19:05', NULL, NULL),
(17, 'Putri Amalia', 'putri.amalia@example.com', NULL, '$2y$12$7.W3MvVvUnd7y5IN0eY1O.IWKURTsexJ9uUN7xaGIEsxTF61mYHuG', 'siswa', NULL, 0, NULL, '2025-11-22 01:19:05', '2025-11-22 06:58:32', '2025-11-22 06:58:32', 1),
(18, 'Hendra Kurniawan', 'hendra.kurniawan@example.com', NULL, '$2y$12$Iz8Sa8.viUWgCMTTAlLoP.sgcus1O9dFw/m.4QWi1xUithWtkiQcq', 'kepala_sekolah', NULL, 0, NULL, '2025-11-22 01:19:06', '2025-11-22 06:58:32', '2025-11-22 06:58:32', NULL),
(19, 'Maya Sari', 'maya.sari@example.com', NULL, '$2y$12$PGYG2e.UQKWX4.6bHAvXV.c0pVykQ0RwiwXvimxG/Mq3WxuAlyAK6', 'kurikulum', NULL, 0, NULL, '2025-11-22 01:19:06', '2025-11-22 06:58:12', '2025-11-22 06:58:12', NULL),
(20, 'Fajar Nugroho', 'fajar.nugroho@example.com', NULL, '$2y$12$bauIop9QYYTZVLogDd4os.QrAXKn/FJzVmhMPtw0mokTb6r0gQLsK', 'siswa', NULL, 0, NULL, '2025-11-22 01:19:07', '2025-11-22 06:54:17', '2025-11-22 06:54:17', 2);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `classes_kode_kelas_unique` (`kode_kelas`),
  ADD KEY `classes_homeroom_teacher_id_foreign` (`homeroom_teacher_id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  ADD KEY `personal_access_tokens_expires_at_index` (`expires_at`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `schedules_guru_id_foreign` (`guru_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `subjects_kode_unique` (`kode`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `teachers_nip_unique` (`nip`),
  ADD UNIQUE KEY `teachers_teacher_code_unique` (`teacher_code`);

--
-- Indexes for table `teacher_attendances`
--
ALTER TABLE `teacher_attendances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `teacher_attendances_schedule_id_guru_id_tanggal_unique` (`schedule_id`,`guru_id`,`tanggal`),
  ADD KEY `teacher_attendances_guru_id_foreign` (`guru_id`),
  ADD KEY `teacher_attendances_created_by_foreign` (`created_by`),
  ADD KEY `teacher_attendances_guru_asli_id_foreign` (`guru_asli_id`),
  ADD KEY `teacher_attendances_assigned_by_foreign` (`assigned_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD KEY `users_role_class_index` (`role`),
  ADD KEY `users_class_id_foreign` (`class_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `teacher_attendances`
--
ALTER TABLE `teacher_attendances`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `classes_homeroom_teacher_id_foreign` FOREIGN KEY (`homeroom_teacher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `schedules`
--
ALTER TABLE `schedules`
  ADD CONSTRAINT `schedules_guru_id_foreign` FOREIGN KEY (`guru_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_attendances`
--
ALTER TABLE `teacher_attendances`
  ADD CONSTRAINT `teacher_attendances_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `teacher_attendances_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `teacher_attendances_schedule_id_foreign` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
