<?php
require_once '../config/config.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $stmt = $conn->query("DESCRIBE system_logs");
    $schema = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($schema, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
