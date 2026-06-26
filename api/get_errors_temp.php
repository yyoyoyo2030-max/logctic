<?php
require_once '../config/config.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $stmt = $conn->query("SELECT * FROM system_logs WHERE log_type = 'error' ORDER BY id DESC LIMIT 5");
    $errors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($errors, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
