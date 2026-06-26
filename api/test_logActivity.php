<?php
require_once '../config/config.php';
header('Content-Type: text/plain; charset=utf-8');

try {
    session_start();
    $_SESSION['user_id'] = 1;
    logActivity('test_action', ['test' => '123']);
    echo "Called logActivity.\n";
    
    $stmt = $conn->query("SELECT * FROM system_logs WHERE action = 'test_action'");
    $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($res) > 0) {
        echo "Inserted successfully!";
    } else {
        echo "Insert failed! But no exception was caught or it was swallowed.";
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage();
}
