<?php
require_once __DIR__ . '/config/config.php';

try {
    // $pdo is already created in config.php
    
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
