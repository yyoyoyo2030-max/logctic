<?php
$host = 'localhost';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create DB if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS logistic_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE logistic_system");
    
    // Check if tables exist
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $usersTableExists = $stmt->rowCount() > 0;
    
    if (!$usersTableExists) {
        $sql1 = file_get_contents(__DIR__ . '/sql/complete_database_setup.sql');
        $pdo->exec($sql1);
        echo "Base database imported.\n";
    }
    
    $sql2 = file_get_contents(__DIR__ . '/sql/upgrade_roles.sql');
    $pdo->exec($sql2);
    echo "Upgrades applied.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
