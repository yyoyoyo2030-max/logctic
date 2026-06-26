<?php
require_once '../config/config.php';
try {
    $conn->exec("DELETE FROM system_logs WHERE action = 'test_action'");
    echo "Test logs deleted.";
} catch (Exception $e) {}
