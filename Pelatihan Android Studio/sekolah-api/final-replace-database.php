<?php

// Database configuration
$host = '127.0.0.1';
$port = 3306;
$database = 'db_sekolah';
$username = 'root';
$password = '';

echo "=== FINAL DATABASE REPLACEMENT ===\n";
echo "Database: {$database}\n";
echo "Host: {$host}:{$port}\n";
echo "Username: {$username}\n";
echo "======================================\n\n";

try {
    // Koneksi ke MySQL
    $pdo = new PDO("mysql:host={$host};port={$port}", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);

    echo "✓ Berhasil terhubung ke MySQL\n";

    // Use database
    $pdo->exec("USE `{$database}`");
    echo "✓ Menggunakan database '{$database}'\n\n";

    // Disable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // Drop all tables first
    echo "🗑️ Menghapus semua tabel yang ada...\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        echo "  ✓ Dropped: {$table}\n";
    }
    echo "✓ Semua tabel berhasil dihapus\n\n";

    echo "🔨 Membuat struktur database baru...\n\n";

    // Execute SQL statements manually to ensure proper execution
    $statements = [
        // Cache tables
        "CREATE TABLE `cache` (
            `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
            `expiration` int NOT NULL,
            PRIMARY KEY (`key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE `cache_locks` (
            `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `expiration` int NOT NULL,
            PRIMARY KEY (`key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Classes table
        "CREATE TABLE `classes` (
            `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
            `nama_kelas` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `kode_kelas` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `created_at` timestamp NULL DEFAULT NULL,
            `updated_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `classes_kode_kelas_unique` (`kode_kelas`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Job tables
        "CREATE TABLE `failed_jobs` (
            `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
            `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
            `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
            `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
            `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
            `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE `jobs` (
            `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
            `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
            `attempts` tinyint UNSIGNED NOT NULL,
            `reserved_at` int UNSIGNED DEFAULT NULL,
            `available_at` int UNSIGNED NOT NULL,
            `created_at` int UNSIGNED NOT NULL,
            PRIMARY KEY (`id`),
            KEY `jobs_queue_index` (`queue`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE `job_batches` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Migrations
        "CREATE TABLE `migrations` (
            `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
            `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `batch` int NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Password reset
        "CREATE TABLE `password_reset_tokens` (
            `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `created_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Personal access tokens
        "CREATE TABLE `personal_access_tokens` (
            `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
            `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `tokenable_id` bigint UNSIGNED NOT NULL,
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Sessions
        "CREATE TABLE `sessions` (
            `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `user_id` bigint UNSIGNED DEFAULT NULL,
            `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `user_agent` text COLLATE utf8mb4_unicode_ci,
            `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
            `last_activity` int NOT NULL,
            PRIMARY KEY (`id`),
            KEY `sessions_user_id_index` (`user_id`),
            KEY `sessions_last_activity_index` (`last_activity`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Subjects
        "CREATE TABLE `subjects` (
            `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
            `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `kode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `created_at` timestamp NULL DEFAULT NULL,
            `updated_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `subjects_kode_unique` (`kode`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Teachers
        "CREATE TABLE `teachers` (
            `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `email_verified_at` timestamp NULL DEFAULT NULL,
            `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `mata_pelajaran` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `is_banned` tinyint(1) NOT NULL DEFAULT '0',
            `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT NULL,
            `updated_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `teachers_email_unique` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Users
        "CREATE TABLE `users` (
            `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `email_verified_at` timestamp NULL DEFAULT NULL,
            `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `role` enum('admin','siswa','kurikulum','kepala_sekolah') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'siswa',
            `mata_pelajaran` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `is_banned` tinyint(1) NOT NULL DEFAULT '0',
            `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT NULL,
            `updated_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `users_email_unique` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    // Execute table creation
    foreach ($statements as $sql) {
        try {
            $pdo->exec($sql);
            if (preg_match('/CREATE TABLE `?(\w+)`?/i', $sql, $matches)) {
                echo "  ✓ Created table: {$matches[1]}\n";
            }
        } catch (PDOException $e) {
            echo "  ❌ Error creating table: " . $e->getMessage() . "\n";
        }
    }

    // Create schedules table (with foreign key dependency)
    echo "\n📋 Membuat tabel dengan foreign keys...\n";

    $schedulesSQL = "CREATE TABLE `schedules` (
        `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
        `hari` enum('Senin','Selasa','Rabu','Kamis','Jumat','Sabtu') COLLATE utf8mb4_unicode_ci NOT NULL,
        `kelas` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
        `mata_pelajaran` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
        `guru_id` bigint UNSIGNED NOT NULL,
        `jam_mulai` time NOT NULL,
        `jam_selesai` time NOT NULL,
        `ruang` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT NULL,
        `updated_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `schedules_guru_id_foreign` (`guru_id`),
        CONSTRAINT `schedules_guru_id_foreign` FOREIGN KEY (`guru_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($schedulesSQL);
    echo "  ✓ Created table: schedules\n";

    // Create teacher_attendances table
    $attendancesSQL = "CREATE TABLE `teacher_attendances` (
        `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
        `schedule_id` bigint UNSIGNED NOT NULL,
        `guru_id` bigint UNSIGNED NOT NULL,
        `guru_asli_id` bigint UNSIGNED DEFAULT NULL,
        `tanggal` date NOT NULL,
        `jam_masuk` time DEFAULT NULL,
        `status` enum('hadir','telat','tidak_hadir','diganti') COLLATE utf8mb4_unicode_ci DEFAULT 'tidak_hadir',
        `keterangan` text COLLATE utf8mb4_unicode_ci,
        `created_by` bigint UNSIGNED DEFAULT NULL,
        `assigned_by` bigint UNSIGNED DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT NULL,
        `updated_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `teacher_attendances_schedule_id_guru_id_tanggal_unique` (`schedule_id`,`guru_id`,`tanggal`),
        KEY `teacher_attendances_guru_id_foreign` (`guru_id`),
        KEY `teacher_attendances_created_by_foreign` (`created_by`),
        KEY `teacher_attendances_guru_asli_id_foreign` (`guru_asli_id`),
        KEY `teacher_attendances_assigned_by_foreign` (`assigned_by`),
        CONSTRAINT `teacher_attendances_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
        CONSTRAINT `teacher_attendances_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
        CONSTRAINT `teacher_attendances_schedule_id_foreign` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($attendancesSQL);
    echo "  ✓ Created table: teacher_attendances\n";

    // Insert migration data
    echo "\n📊 Memasukkan data dasar...\n";

    $migrationData = [
        "INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
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
        (20, '2025_10_29_000000_update_teacher_attendances_to_reference_teachers_table', 2)"
    ];

    $pdo->exec($migrationData[0]);
    echo "  ✓ Inserted migration data\n";

    // Insert sample users
    $userData = "INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `role`, `mata_pelajaran`, `is_banned`, `remember_token`, `created_at`, `updated_at`) VALUES
    (1, 'zupa', 'zupa.admin@sekolah.com', NULL, '\$2y\$12\$wJAnWuayo5jITtUYzncYUevkU5NMdRGfgq33irFhGiz0DZJaC8psK', 'siswa', NULL, 0, NULL, '2025-11-16 02:10:18', '2025-11-16 02:10:18'),
    (2, 'kurikulum', 'siti.kurikulum@sekolah.com', NULL, '\$2y\$12\$7v7WJv/Az/arxO1Pp518u.irANM7QoP/F1eUOmAjNpeM0KKpJpOAm', 'kurikulum', NULL, 0, NULL, '2025-11-16 02:11:23', '2025-11-16 02:11:23')";

    $pdo->exec($userData);
    echo "  ✓ Inserted sample users\n";

    // Set AUTO_INCREMENT values
    $autoIncrements = [
        "ALTER TABLE `classes` AUTO_INCREMENT = 1",
        "ALTER TABLE `failed_jobs` AUTO_INCREMENT = 1",
        "ALTER TABLE `jobs` AUTO_INCREMENT = 1",
        "ALTER TABLE `migrations` AUTO_INCREMENT = 21",
        "ALTER TABLE `personal_access_tokens` AUTO_INCREMENT = 2",
        "ALTER TABLE `schedules` AUTO_INCREMENT = 1",
        "ALTER TABLE `subjects` AUTO_INCREMENT = 1",
        "ALTER TABLE `teachers` AUTO_INCREMENT = 1",
        "ALTER TABLE `teacher_attendances` AUTO_INCREMENT = 1",
        "ALTER TABLE `users` AUTO_INCREMENT = 3"
    ];

    foreach ($autoIncrements as $sql) {
        $pdo->exec($sql);
    }
    echo "  ✓ Set AUTO_INCREMENT values\n";

    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "\n✅ BERHASIL SEMPURNA!\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "🗄️ Database: {$database}\n";
    echo "📊 Struktur baru berhasil dibuat\n";
    echo "👥 Sample users berhasil ditambahkan\n";
    echo "🔗 Foreign key constraints aktif\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    // Verify final structure
    echo "\n🔍 VERIFIKASI STRUKTUR FINAL:\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "📋 Total tabel: " . count($tables) . "\n";
    foreach ($tables as $table) {
        echo "   • {$table}\n";
    }

    // Check users
    echo "\n👥 DATA USERS:\n";
    $users = $pdo->query("SELECT id, name, email, role FROM users")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as $user) {
        echo "   • ID: {$user['id']}, Name: {$user['name']}, Email: {$user['email']}, Role: {$user['role']}\n";
    }

    // Check constraints
    echo "\n🔗 FOREIGN KEY CONSTRAINTS:\n";
    $constraints = $pdo->query("
        SELECT
            TABLE_NAME,
            COLUMN_NAME,
            CONSTRAINT_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE REFERENCED_TABLE_SCHEMA = '{$database}'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($constraints as $constraint) {
        echo "   • {$constraint['TABLE_NAME']}.{$constraint['COLUMN_NAME']} -> {$constraint['REFERENCED_TABLE_NAME']}.{$constraint['REFERENCED_COLUMN_NAME']}\n";
    }
} catch (PDOException $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . __FILE__ . "\n";
    echo "Line: " . $e->getLine() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎉 DATABASE REPLACEMENT COMPLETED!\n";
echo "Database siap digunakan dengan struktur sesuai SQL dump.\n";
