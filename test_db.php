<?php
echo "Testing DB...\n";
try {
    $conn = new PDO("mysql:host=127.0.0.1;dbname=logistic_system;charset=utf8mb4", "root", "", [PDO::ATTR_TIMEOUT => 2]);
    echo "Connected successfully!\n";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
