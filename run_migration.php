<?php
require 'config/config.php';
try {
    $conn->exec("ALTER TABLE users ADD COLUMN remember_token VARCHAR(255) NULL AFTER is_active");
    echo "Success";
} catch(Exception $e) {
    echo $e->getMessage();
}
