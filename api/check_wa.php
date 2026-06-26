<?php
require_once '../config/config.php';
try {
    $stmt = $conn->query("SELECT * FROM whatsapp_settings");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($data);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
