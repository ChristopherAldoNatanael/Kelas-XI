<?php
echo "🔍 Checking MySQL Ports...\n";
echo "=============================\n";

// Test common MySQL ports
$ports = [3306, 3307, 3308];
$host = '127.0.0.1';

foreach ($ports as $port) {
    echo "Testing port $port: ";

    $connection = @fsockopen($host, $port, $errno, $errstr, 5);

    if ($connection) {
        echo "✅ OPEN\n";
        fclose($connection);

        // Try to connect to MySQL on this port
        try {
            $pdo = new PDO("mysql:host=$host;port=$port", 'root', '', [
                PDO::ATTR_TIMEOUT => 5,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            echo "   MySQL connection: ✅ SUCCESS\n";

            // Check if our database exists
            $stmt = $pdo->query("SHOW DATABASES LIKE 'db_sekolah'");
            if ($stmt && $stmt->rowCount() > 0) {
                echo "   Database 'db_sekolah': ✅ EXISTS\n";
            } else {
                echo "   Database 'db_sekolah': ❌ NOT FOUND\n";
            }

            $pdo = null;
        } catch (Exception $e) {
            echo "   MySQL connection: ❌ FAILED - " . $e->getMessage() . "\n";
        }
    } else {
        echo "❌ CLOSED ($errstr)\n";
    }
}

echo "\n🔍 Checking Windows Services...\n";
echo "===============================\n";

// Check MySQL services
$services = ['MySQL', 'MySQL80', 'MySQL57', 'MariaDB'];
foreach ($services as $service) {
    $output = shell_exec("sc query \"$service\" 2>nul");
    if ($output && strpos($output, 'RUNNING') !== false) {
        echo "Service '$service': ✅ RUNNING\n";
    } elseif ($output) {
        echo "Service '$service': ⚠️ EXISTS but not running\n";
    }
}

echo "\n";
