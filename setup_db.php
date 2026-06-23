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
    echo "Upgrades applied. ";
    
    // Create default admin user if not exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, is_active) VALUES ('admin', ?, 'المدير العام', 'admin', 1)");
        $stmt->execute([$hashed_password]);
        echo "Default admin user created (admin / admin123).\n";
    }

    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
