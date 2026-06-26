<?php
require_once '../config/config.php';
try {
    $stmt = $conn->query("SELECT * FROM whatsapp_settings LIMIT 1");
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($data);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
