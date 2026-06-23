<?php
/**
 * API لجلب جميع التحويلات
 */

require_once '../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$branch_id = $_SESSION['branch_id'] ?? null;

try {
    // جلب جميع التحويلات
    if (isAdmin()) {
        $stmt = $conn->query("
            SELECT t.*, 
                   b_from.name as from_branch_name, 
                   b_to.name as to_branch_name,
                   d.name as driver_name
            FROM transfers t
            LEFT JOIN branches b_from ON t.from_branch = b_from.id
            LEFT JOIN branches b_to ON t.to_branch = b_to.id
            LEFT JOIN drivers d ON t.driver_id = d.id
            ORDER BY t.created_at DESC
            LIMIT 50
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT t.*, 
                   b_from.name as from_branch_name, 
                   b_to.name as to_branch_name,
                   d.name as driver_name
            FROM transfers t
            LEFT JOIN branches b_from ON t.from_branch = b_from.id
            LEFT JOIN branches b_to ON t.to_branch = b_to.id
            LEFT JOIN drivers d ON t.driver_id = d.id
            WHERE t.branch_id = ?
            ORDER BY t.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$branch_id]);
    }
    
    $transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'transfers' => $transfers,
        'count' => count($transfers),
        'timestamp' => time()
    ]);
    
} catch (PDOException $e) {
    error_log("Error in all-transfers.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في قاعدة البيانات'
    ]);
}
