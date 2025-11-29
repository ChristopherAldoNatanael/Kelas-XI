<?php

try {
    $pdo = new PDO('mysql:host=localhost;dbname=db_sekolah', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== TEACHERS TABLE STRUCTURE ===\n";
    $result = $pdo->query('DESCRIBE teachers');
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo sprintf("%-20s %-15s %-8s %-10s\n", $row['Field'], $row['Type'], $row['Null'], $row['Key']);
    }

    echo "\n=== SAMPLE TEACHER DATA ===\n";
    $result = $pdo->query('SELECT * FROM teachers LIMIT 3');
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }

    echo "\n=== COUNT TEACHERS ===\n";
    $result = $pdo->query('SELECT COUNT(*) as total FROM teachers');
    $count = $result->fetch(PDO::FETCH_ASSOC);
    echo "Total teachers: " . $count['total'] . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
