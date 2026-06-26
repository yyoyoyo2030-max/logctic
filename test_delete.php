<?php
require_once 'config/config.php';
try {
    echo 'Test';
    // Simulate DELETE logic
    $transfer_id = 25;
    $stmt = $conn->prepare("SELECT file_path FROM transfers WHERE id = ?");
    $stmt->execute([$transfer_id]);
    $transfer = $stmt->fetch();
    echo ' - fetched - ';
    $stmt = $conn->prepare("DELETE FROM driver_assignments WHERE transfer_id = ?");
    $stmt->execute([$transfer_id]);
    echo ' - deleted assignments - ';
    
    // Check if error happens here
    $stmt = $conn->prepare("DELETE FROM transfers WHERE id = ?");
    $stmt->execute([$transfer_id]);
    echo ' - deleted transfer - ';
    
    logActivity('ÍĞİ ÊÍæíá', ['transfer_id' => $transfer_id]);
    echo ' - logged activity - ';
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
}
