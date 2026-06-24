<?php
require 'config/config.php';
try {
    $conn->exec("ALTER TABLE system_logs ADD INDEX idx_created_at (created_at)");
    echo "Index created_at added.<br>";
} catch(Exception $e) { echo $e->getMessage() . "<br>"; }

try {
    $conn->exec("ALTER TABLE system_logs ADD INDEX idx_user_id (user_id)");
    echo "Index user_id added.<br>";
} catch(Exception $e) { echo $e->getMessage() . "<br>"; }

try {
    $conn->exec("ALTER TABLE system_logs ADD INDEX idx_log_type (log_type)");
    echo "Index log_type added.<br>";
} catch(Exception $e) { echo $e->getMessage() . "<br>"; }

echo "Done.";
?>
