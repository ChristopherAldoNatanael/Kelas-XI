<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$config = config('database');
$host = $config['connections']['mysql']['host'];
$database = $config['connections']['mysql']['database'];
$username = $config['connections']['mysql']['username'];
$password = $config['connections']['mysql']['password'];

echo "Importing database from SQL dump...\n";

try {
    // Connect to MySQL server (without specifying database)
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Drop and recreate database
    $pdo->exec("DROP DATABASE IF EXISTS `$database`");
    $pdo->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$database`");

    // Read and execute SQL file
    $sql = file_get_contents('DB MYSQL SAYA DB_SEKOLAH.sql');

    // Split SQL file into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $statement) {
        if (!empty($statement) && !preg_match('/^--/', $statement)) {
            try {
                $pdo->exec($statement);
            } catch (Exception $e) {
                // Skip some non-critical errors
                if (!preg_match('/(DROP TABLE|CREATE TABLE|INSERT INTO|ALTER TABLE|SET |COMMIT)/', $statement) ||
                    !str_contains($e->getMessage(), 'already exists')) {
                    echo "Warning on statement: " . substr($statement, 0, 50) . "...\n";
                    echo "Error: " . $e->getMessage() . "\n";
                }
            }
        }
    }

    echo "✅ Database imported successfully!\n";

} catch (Exception $e) {
    echo "❌ Error importing database: " . $e->getMessage() . "\n";
}
